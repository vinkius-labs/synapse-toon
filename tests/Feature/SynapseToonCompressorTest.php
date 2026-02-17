<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use VinkiusLabs\SynapseToon\Compression\SynapseToonCompressor;
use VinkiusLabs\SynapseToon\Test\TestCase;

class SynapseToonCompressorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('synapse-toon.compression.enabled', true);
        $this->app['config']->set('synapse-toon.compression.prefer', 'gzip');
        $this->app['config']->set('synapse-toon.compression.minimum_size', 0);
    }

    public function test_gzip_compression_is_selected_when_available(): void
    {
        if (! function_exists('gzencode')) {
            $this->markTestSkipped('The zlib extension is required to perform gzip compression tests.');
        }

        $compressor = $this->app->make(SynapseToonCompressor::class);
        $result = $compressor->compress('payload-data', 'gzip;q=0.9, br;q=0');

        $this->assertSame('gzip', $result['algorithm']);
        $this->assertSame('gzip', $result['encoding']);
        $this->assertNotSame('payload-data', $result['body']);
    }

    public function test_identity_falls_back_to_original_payload(): void
    {
        $compressor = $this->app->make(SynapseToonCompressor::class);
        $result = $compressor->compress('payload-data', 'identity;q=1, br;q=0');

        $this->assertSame('none', $result['algorithm']);
        $this->assertNull($result['encoding']);
        $this->assertSame('payload-data', $result['body']);
    }
}
