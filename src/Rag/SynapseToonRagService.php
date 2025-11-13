<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Rag;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics;
use VinkiusLabs\SynapseToon\Rag\ContextConfig;
use VinkiusLabs\SynapseToon\Rag\DocumentSelector;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonVectorStore;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;
use VinkiusLabs\SynapseToon\Rag\Drivers\SynapseToonNullVectorStore;

class SynapseToonRagService
{
    private ?SynapseToonVectorStore $driver = null;

    public function __construct(
        protected ConfigRepository $config,
        protected Container $container,
        protected SynapseToonEncoder $encoder,
        protected ?DocumentSelector $selector = null,
    ) {
        $this->selector = $selector ?? new DocumentSelector($encoder, new Summarizer($container));
    }

    public function buildContext(string $query, array $metadata = []): string
    {
        $config = ContextConfig::fromConfig($this->config);

        $cacheKey = $this->getCacheKey($query, $metadata, $config);

        if ($config->cacheTtl > 0 && Cache::has($cacheKey)) {
            $encoded = Cache::get($cacheKey);

            // record metric: cache hit
            $this->maybeRecordMetrics('rag_search', [
                'query' => $query,
                'cache_hit' => true,
            ]);

            return (string) $encoded;
        }

        $start = microtime(true);

        $documents = $this->fetchDocuments($query, $config->searchLimit);
        $documents = $this->applyFilters($documents, $config);

        $queryTokens = $this->encoder->estimatedTokens($query);
        [$selected, $usedTokens] = $this->selector->select(
            $documents,
            $config,
            $queryTokens
        );

        $payload = $this->buildPayload($metadata, $query, $selected);
        $encoded = $this->encoder->encode($payload);

        // record metrics
        $latencyMs = (microtime(true) - $start) * 1000;
        $this->maybeRecordMetrics('rag_search', [
            'query' => $query,
            'document_count' => count($selected),
            'total_tokens' => $usedTokens,
            'query_tokens' => $queryTokens,
            'latency_ms' => $latencyMs,
            'cache_hit' => false,
        ]);

        if ($config->cacheTtl > 0) {
            Cache::put($cacheKey, $encoded, $config->cacheTtl);
        }

        return $encoded;
    }


    private function getCacheKey(string $query, array $metadata, ContextConfig $config): string
    {
        return 'synapse-toon:rag:' . md5(serialize([$query, $metadata, $config->limit, $config->searchLimit, $config->maxTokens, $config->minScore, $config->metadataFilters]));
    }

    private function fetchDocuments(string $query, int $searchLimit): Collection
    {
        return $this->driver()
            ->search($query, $searchLimit)
            ->map(fn ($document) => [
                'id' => $document['id'] ?? Str::uuid()->toString(),
                'content' => (string) ($document['content'] ?? ''),
                'score' => $document['score'] ?? 0.0,
                'metadata' => $document['metadata'] ?? [],
            ])
            ->values();
    }

    private function applyFilters(Collection $documents, ContextConfig $config): Collection
    {
        return $documents
            ->filter(fn ($d) => (float) ($d['score'] ?? 0.0) >= $config->minScore)
            ->when(! empty($config->metadataFilters), fn ($collection) => $collection->filter(fn ($d) => $this->matchesMetadataFilters($d['metadata'] ?? [], $config->metadataFilters)))
            ->sortByDesc('score')
            ->values();
    }

    private function matchesMetadataFilters(array $metadata, array $filters): bool
    {
        // Compose a functional & expressive predicate using match() and collection helpers
        return collect($filters)->every(function ($expected, $key) use ($metadata) {
            $value = data_get($metadata, $key);

            return match (true) {
                is_null($expected) => Arr::has($metadata, $key),
                is_callable($expected) => (bool) $expected($value, $metadata),
                is_string($expected) && Str::startsWith($expected, '/') && Str::endsWith($expected, '/') => is_string($value) && preg_match($expected, (string) $value) === 1,
                default => $value == $expected,
            };
        });
    }

    // Selection handled by DocumentSelector

    // Summarization handled in DocumentSelector

    private function buildPayload(array $metadata, string $query, array $selected): array
    {
        return [
            ...$metadata,
            'query' => $query,
            'documents' => $selected,
        ];
    }

    private function maybeRecordMetrics(string $type, array $payload): void
    {
        rescue(function () use ($type, $payload) {
            if (! $this->container->bound(SynapseToonMetrics::class)) {
                return;
            }

            /** @var SynapseToonMetrics $metrics */
            $metrics = $this->container->make(SynapseToonMetrics::class);
            $metrics->record(array_merge(['type' => $type], $payload));
        }, null);
    }

    private function driver(): SynapseToonVectorStore
    {
        return $this->driver ??= $this->resolveDriver();
    }

    private function resolveDriver(): SynapseToonVectorStore
    {
        $enabled = (bool) $this->config->get('synapse-toon.rag.enabled', true);
        $driverName = (string) $this->config->get('synapse-toon.rag.driver', 'null');

        return match (true) {
            ! $enabled => new SynapseToonNullVectorStore(),
            $driverName === 'null' => new SynapseToonNullVectorStore(),
            default => $this->container->make($driverName),
        };
    }
}
