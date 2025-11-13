<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Rag;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

final readonly class ContextConfig
{
    /**
     * @param callable|string|null $summarizer
     */
    public function __construct(
        public int $limit,
        public int $searchLimit,
        public int $maxTokens,
        public float $minScore,
        public int $maxSnippet,
        public int $cacheTtl,
        public bool $summarize,
        public mixed $summarizer,
        public array $metadataFilters,
    ) {
    }

    public static function fromConfig(ConfigRepository $config): self
    {
        return new self(
            (int) $config->get('synapse-toon.rag.context.limit', 3),
            (int) $config->get('synapse-toon.rag.context.search_limit', 10),
            (int) $config->get('synapse-toon.rag.context.max_tokens', 512),
            (float) $config->get('synapse-toon.rag.context.min_score', 0.0),
            (int) $config->get('synapse-toon.rag.context.max_snippet_length', 200),
            (int) $config->get('synapse-toon.rag.context.cache_ttl', 0),
            (bool) $config->get('synapse-toon.rag.context.summarize', false),
            $config->get('synapse-toon.rag.context.summarizer_service'),
            $config->get('synapse-toon.rag.context.metadata_filters', []),
        );
    }
}
