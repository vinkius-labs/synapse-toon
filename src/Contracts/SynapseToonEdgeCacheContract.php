<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Contracts;

use Closure;

interface SynapseToonEdgeCacheContract
{
    /**
     * Retrieve a cached encoded payload or compute and cache it.
     *
     * @template TValue
     * @param Closure(): TValue $callback
     */
    public function remember(string $key, Closure $callback, ?int $ttl = null): string;
}
