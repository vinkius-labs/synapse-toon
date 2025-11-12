<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Rag;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
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
    ) {
    }

    public function buildContext(string $query, array $metadata = []): string
    {
        $limit = (int) $this->config->get('synapse-toon.rag.context.limit', 3);
        $maxSnippet = (int) $this->config->get('synapse-toon.rag.context.max_snippet_length', 200);

        $documents = $this->driver()
            ->search($query, $limit)
            ->map(fn ($document) => [
                'id' => $document['id'] ?? Str::uuid()->toString(),
                'content' => Str::limit((string) ($document['content'] ?? ''), $maxSnippet),
                'score' => $document['score'] ?? null,
                'metadata' => $document['metadata'] ?? [],
            ])
            ->values();

        return $this->encoder->encode([
            ...$metadata,
            'query' => $query,
            'documents' => $documents,
        ]);
    }

    private function driver(): SynapseToonVectorStore
    {
        return $this->driver ??= $this->resolveDriver();
    }

    private function resolveDriver(): SynapseToonVectorStore
    {
        if (! (bool) $this->config->get('synapse-toon.rag.enabled', true)) {
            return new SynapseToonNullVectorStore();
        }

        $driverName = (string) $this->config->get('synapse-toon.rag.driver', 'null');

        return match ($driverName) {
            'null' => new SynapseToonNullVectorStore(),
            default => $this->container->make($driverName),
        };
    }
}
