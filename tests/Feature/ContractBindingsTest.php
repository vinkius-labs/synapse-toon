<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Test\Feature;

use VinkiusLabs\SynapseToon\Caching\SynapseToonEdgeCache;
use VinkiusLabs\SynapseToon\Compression\SynapseToonCompressor;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonCompressorContract;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonEdgeCacheContract;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonEncoderContract;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonStreamerContract;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;
use VinkiusLabs\SynapseToon\Streaming\SynapseToonSseStreamer;
use VinkiusLabs\SynapseToon\Test\TestCase;

class ContractBindingsTest extends TestCase
{
    public function test_encoder_contract_resolves_to_concrete(): void
    {
        $instance = $this->app->make(SynapseToonEncoderContract::class);
        $this->assertInstanceOf(SynapseToonEncoder::class, $instance);
    }

    public function test_compressor_contract_resolves_to_concrete(): void
    {
        $instance = $this->app->make(SynapseToonCompressorContract::class);
        $this->assertInstanceOf(SynapseToonCompressor::class, $instance);
    }

    public function test_streamer_contract_resolves_to_concrete(): void
    {
        $instance = $this->app->make(SynapseToonStreamerContract::class);
        $this->assertInstanceOf(SynapseToonSseStreamer::class, $instance);
    }

    public function test_edge_cache_contract_resolves_to_concrete(): void
    {
        $instance = $this->app->make(SynapseToonEdgeCacheContract::class);
        $this->assertInstanceOf(SynapseToonEdgeCache::class, $instance);
    }
}
