<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use Illuminate\Support\Collection;
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
}
