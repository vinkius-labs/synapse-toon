<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use Illuminate\Support\Facades\Cache;
use VinkiusLabs\SynapseToon\Caching\SynapseToonEdgeCache;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;
use VinkiusLabs\SynapseToon\Test\TestCase;

class SynapseToonEdgeCacheTest extends TestCase
{
    public function test_edge_cache_remember_encodes_and_caches_payload(): void
    {
        $this->app['config']->set('synapse-toon.edge_cache.store', 'array');
        $this->app['config']->set('synapse-toon.edge_cache.ttl', 120);

        Cache::store('array')->forget('edge:test');

        $cache = $this->app->make(SynapseToonEdgeCache::class);
        $encoder = $this->app->make(SynapseToonEncoder::class);

        $invocations = 0;

        $result = $cache->remember('edge:test', function () use (&$invocations) {
            $invocations++;

            return ['foo' => 'bar'];
        }, null, ['api']);

        $this->assertSame(1, $invocations);
        $this->assertIsString($result);
        $this->assertSame(['foo' => 'bar'], $encoder->decode($result));

        $cachedResult = $cache->remember('edge:test', function () use (&$invocations) {
            $invocations++;

            return ['foo' => 'baz'];
        });

        $this->assertSame($result, $cachedResult);
        $this->assertSame(1, $invocations, 'Callback should not run when cache hit occurs');
    }
}
