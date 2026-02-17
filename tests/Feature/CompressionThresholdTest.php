<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Test\Feature;

use VinkiusLabs\SynapseToon\Compression\SynapseToonCompressor;
use VinkiusLabs\SynapseToon\Test\TestCase;

class CompressionThresholdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('synapse-toon.compression.enabled', true);
        $this->app['config']->set('synapse-toon.compression.prefer', 'gzip');
    }

    public function test_payload_below_minimum_size_is_not_compressed(): void
    {
        $this->app['config']->set('synapse-toon.compression.minimum_size', 256);

        $compressor = $this->app->make(SynapseToonCompressor::class);
        $payload = 'small';

        $result = $compressor->compress($payload, 'gzip');

        $this->assertSame('none', $result['algorithm']);
        $this->assertNull($result['encoding']);
        $this->assertSame($payload, $result['body']);
    }

    public function test_payload_at_minimum_size_is_compressed(): void
    {
        if (! function_exists('gzencode')) {
            $this->markTestSkipped('The zlib extension is required.');
        }

        $this->app['config']->set('synapse-toon.compression.minimum_size', 10);

        $compressor = $this->app->make(SynapseToonCompressor::class);
        $payload = str_repeat('x', 20);

        $result = $compressor->compress($payload, 'gzip');

        $this->assertSame('gzip', $result['algorithm']);
        $this->assertSame('gzip', $result['encoding']);
    }

    public function test_default_minimum_size_skips_tiny_payloads(): void
    {
        // Default minimum_size is 128
        $compressor = $this->app->make(SynapseToonCompressor::class);
        $payload = 'tiny';

        $result = $compressor->compress($payload, 'gzip');

        $this->assertSame('none', $result['algorithm']);
        $this->assertSame($payload, $result['body']);
    }

    public function test_minimum_size_zero_compresses_everything(): void
    {
        if (! function_exists('gzencode')) {
            $this->markTestSkipped('The zlib extension is required.');
        }

        $this->app['config']->set('synapse-toon.compression.minimum_size', 0);

        $compressor = $this->app->make(SynapseToonCompressor::class);
        $payload = 'x';

        $result = $compressor->compress($payload, 'gzip');

        $this->assertSame('gzip', $result['algorithm']);
    }
}
