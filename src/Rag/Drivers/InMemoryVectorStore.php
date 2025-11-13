<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Rag\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonVectorStore;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonVectorStoreManager;

/**
 * Simple in-memory vector store used for local development and tests.
 * This does not implement real vector similarity but provides a predictable
 * dataset for tests and demos.
 */
class InMemoryVectorStore implements SynapseToonVectorStore, SynapseToonVectorStoreManager
{
    /** @var array<string, array<string, mixed>> */
    protected array $index = [];

    public function __construct(array $seed = [])
    {
        foreach ($seed as $item) {
            $id = $item['id'] ?? (string) Str::uuid();
            $this->index[$id] = [
                'id' => $id,
                'content' => $item['content'] ?? '',
                'metadata' => $item['metadata'] ?? [],
                'score' => $item['score'] ?? 1.0,
            ];
        }
    }

    public function store(string $id, string $content, array $metadata = []): void
    {
        $this->index[$id] = [
            'id' => $id,
            'content' => $content,
            'metadata' => $metadata,
            'score' => $metadata['score'] ?? 1.0,
        ];
    }

    public function delete(string $id): void
    {
        unset($this->index[$id]);
    }

    /**
     * Naive search using substring match and score sorting.
     * Returns top $limit documents as a Collection.
     */
    public function search(string $query, int $limit = 3): Collection
    {
        $query = Str::of(trim($query))->lower()->toString();

        $items = Collection::make($this->index)
            ->map(function ($item) use ($query) {
                $score = $item['score'] ?? 0.0;

                $score += ($query !== '' && Str::contains(Str::lower($item['content']), $query)) ? 0.5 : 0;

                return [
                    'id' => $item['id'],
                    'content' => $item['content'],
                    'metadata' => $item['metadata'] ?? [],
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->values();

        return $items->take($limit);
    }
}
