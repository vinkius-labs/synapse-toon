<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use function collect;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonVectorStore;
use VinkiusLabs\SynapseToon\Rag\SynapseToonRagService;
use VinkiusLabs\SynapseToon\Test\TestCase;

class SynapseToonRagServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('synapse-toon.rag.enabled', true);
        $this->app['config']->set('synapse-toon.rag.driver', 'synapse-toon.test-vector-store');

        $this->app->bind('synapse-toon.test-vector-store', function () {
            return new class implements SynapseToonVectorStore {
                public function search(string $query, int $limit = 3): Collection
                {
                    return collect(range(1, $limit))->map(function ($index) use ($query) {
                        return [
                            'id' => $index,
                            'content' => Str::repeat($query, 5),
                            'score' => 1 - ($index * 0.1),
                        ];
                    });
                }
            };
        });
    }

    public function test_build_context_encodes_vector_results(): void
    {
        $service = $this->app->make(SynapseToonRagService::class);

        $encoded = $service->buildContext('synapse', ['meta' => ['foo' => 'bar']]);

        $this->assertStringContainsString('"query":"synapse"', $encoded);
        $this->assertStringContainsString('"meta"', $encoded);
        $this->assertStringContainsString('"documents"', $encoded);
    }

    public function test_token_aware_selection_respects_max_tokens(): void
    {
        $this->app['config']->set('synapse-toon.rag.context.max_tokens', 10);
        $this->app['config']->set('synapse-toon.rag.driver', 'synapse-toon.test-vector-store');

        $service = $this->app->make(SynapseToonRagService::class);

        $encoded = $service->buildContext(str_repeat('x', 200)); // long query to create large docs
        $decoded = json_decode($encoded, true);

        $this->assertArrayHasKey('documents', $decoded);

        $totalTokens = array_sum(array_column($decoded['documents'], 'tokens'));
        $this->assertLessThanOrEqual(10, $totalTokens, 'Total tokens exceed configured max_tokens');
    }

    public function test_min_score_filters_low_scoring_documents(): void
    {
        $this->app['config']->set('synapse-toon.rag.context.min_score', 0.85);
        $this->app['config']->set('synapse-toon.rag.driver', 'synapse-toon.test-vector-store');

        $service = $this->app->make(SynapseToonRagService::class);

        $encoded = $service->buildContext('synapse');
        $decoded = json_decode($encoded, true);

        foreach ($decoded['documents'] as $doc) {
            $this->assertGreaterThanOrEqual(0.85, $doc['score']);
        }
    }

    public function test_cache_uses_cache_and_skips_driver_on_subsequent_call(): void
    {
        $searchCalls = 0;

        $this->app->bind('synapse-toon.test-vector-store-cache', function () use (&$searchCalls) {
            return new class($searchCalls) implements SynapseToonVectorStore {
                private $searchCallsRef;

                public function __construct(&$searchCallsRef)
                {
                    $this->searchCallsRef = &$searchCallsRef;
                }

                public function search(string $query, int $limit = 3): Collection
                {
                    $this->searchCallsRef++;

                    return collect([[
                        'id' => 1,
                        'content' => Str::repeat($query, 5),
                        'score' => 1.0,
                    ]]);
                }
            };
        });

        $this->app['config']->set('synapse-toon.rag.driver', 'synapse-toon.test-vector-store-cache');
        $this->app['config']->set('synapse-toon.rag.context.cache_ttl', 60);

        $service = $this->app->make(SynapseToonRagService::class);

        $service->buildContext('cache-test');
        $service->buildContext('cache-test');

        // assert driver search called only once
        $this->assertEquals(1, $searchCalls);
    }

    public function test_summarizer_truncates_large_docs_to_fit_budget(): void
    {
        $this->app['config']->set('synapse-toon.rag.context.max_tokens', 12);
        $this->app['config']->set('synapse-toon.rag.context.summarize', true);
        $this->app['config']->set('synapse-toon.rag.context.summarizer_service', 'synapse-toon.test-summarizer');
        $this->app['config']->set('synapse-toon.rag.driver', 'synapse-toon.test-vector-store-summarization');

        // Override driver to return a single very large doc irrespective of query
        $this->app->bind('synapse-toon.test-vector-store-summarization', function () {
            return new class implements SynapseToonVectorStore {
                public function search(string $query, int $limit = 3): Collection
                {
                    return collect([[
                        'id' => 1,
                        'content' => Str::repeat('X', 800),
                        'score' => 1.0,
                    ]]);
                }
            };
        });

        $this->app->bind('synapse-toon.test-summarizer', function () {
            return function (string $content, int $targetTokens = 0): string {
                // naive summarizer: return only up to targetTokens * 4 characters
                return Str::limit($content, max(0, $targetTokens * 4));
            };
        });

        $service = $this->app->make(SynapseToonRagService::class);

        // The driver should return a large document that needs summarizing
        $driver = $this->app->make('synapse-toon.test-vector-store-summarization');
        $this->assertNotEmpty($driver->search('short'));
        $this->assertTrue($this->app->bound('synapse-toon.test-summarizer'));

        $encoded = $service->buildContext('short');
        //--- debug echo removed
        $decoded = json_decode($encoded, true);

        $this->assertNotEmpty($decoded['documents']);
        $totalTokens = array_sum(array_column($decoded['documents'], 'tokens'));
        $this->assertLessThanOrEqual(12, $totalTokens);
    }

    public function test_metrics_are_recorded(): void
    {
        $called = [];

        $this->app['config']->set('synapse-toon.metrics.enabled', true);

        $this->app->bind(\VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics::class, function () use (&$called) {
            return new class($called) {
                private $calledRef;

                public function __construct(&$calledRef)
                {
                    $this->calledRef = &$calledRef;
                }

                public function record(array $payload): void
                {
                    $this->calledRef[] = $payload;
                }
            };
        });

        $this->app['config']->set('synapse-toon.rag.driver', 'synapse-toon.test-vector-store');

        $service = $this->app->make(SynapseToonRagService::class);
        $service->buildContext('metrics-test');

        $this->assertNotEmpty($called);
        $this->assertSame('rag_search', $called[0]['type']);
    }

    public function test_metadata_filters_include_and_exclude_documents(): void
    {
        $this->app['config']->set('synapse-toon.rag.context.metadata_filters', ['source' => 'wiki']);
        $this->app['config']->set('synapse-toon.rag.driver', 'synapse-toon.test-vector-store-metadata');

        $this->app->bind('synapse-toon.test-vector-store-metadata', function () {
            return new class implements SynapseToonVectorStore {
                public function search(string $query, int $limit = 3): Collection
                {
                    return collect([
                        ['id' => 1, 'content' => 'match', 'score' => 1.0, 'metadata' => ['source' => 'wiki']],
                        ['id' => 2, 'content' => 'no-match', 'score' => 1.0, 'metadata' => ['source' => 'blog']],
                    ]);
                }
            };
        });

        $service = $this->app->make(SynapseToonRagService::class);
        $encoded = $service->buildContext('topic');
        $decoded = json_decode($encoded, true);

        $this->assertCount(1, $decoded['documents']);
        $this->assertSame('wiki', $decoded['documents'][0]['metadata']['source']);
    }
}
