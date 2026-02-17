<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Contracts;

use Illuminate\Support\Collection;

interface SynapseToonVectorStore
{
    /**
     * Retrieve the most relevant documents for a query.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 3): Collection;
}
