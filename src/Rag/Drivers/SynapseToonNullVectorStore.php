<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Rag\Drivers;

use Illuminate\Support\Collection;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonVectorStore;

class SynapseToonNullVectorStore implements SynapseToonVectorStore
{
    public function search(string $query, int $limit = 3): Collection
    {
        return Collection::empty();
    }
}
