<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Caching;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;

class SynapseToonEdgeCache
{
    public function __construct(protected SynapseToonEncoder $encoder)
    {
    }

    /**
     * @template TValue
     * @param Closure(): TValue $callback
     */
    public function remember(string $key, Closure $callback, ?int $ttl = null): string
    {
        return (string) $this->repository()->remember(
            $key,
            $ttl ?? $this->defaultTtl(),
            fn () => $this->encoder->encode($callback())
        );
    }

    private function repository(): Repository
    {
        return Cache::store((string) config('synapse-toon.edge_cache.store'));
    }

    private function defaultTtl(): int
    {
        return (int) config('synapse-toon.edge_cache.ttl', 3600);
    }
}
