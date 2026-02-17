<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Test\Feature;

use VinkiusLabs\SynapseToon\Compression\SynapseToonCompressor;
use VinkiusLabs\SynapseToon\Test\TestCase;

class BrotliCompressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('synapse-toon.compression.enabled', true);
        $this->app['config']->set('synapse-toon.compression.prefer', 'brotli');
        $this->app['config']->set('synapse-toon.compression.minimum_size', 0);
    }

    public function test_brotli_compression_when_extension_available(): void
    {
        if (! function_exists('brotli_compress')) {
            $this->markTestSkipped('The brotli extension is required for this test.');
        }

        $compressor = $this->app->make(SynapseToonCompressor::class);
        $payload = str_repeat('This is a test payload for brotli compression. ', 20);

        $result = $compressor->compress($payload, 'br;q=1.0');

        $this->assertSame('brotli', $result['algorithm']);
        $this->assertSame('br', $result['encoding']);
        $this->assertNotSame($payload, $result['body']);

        // Verify the compressed data can be decompressed
        $decompressed = brotli_uncompress($result['body']);
        $this->assertSame($payload, $decompressed);
    }

    public function test_brotli_preferred_over_gzip_when_both_available(): void
    {
        if (! function_exists('brotli_compress')) {
            $this->markTestSkipped('The brotli extension is required for this test.');
        }

        $compressor = $this->app->make(SynapseToonCompressor::class);
        $payload = str_repeat('Test data for compression preference. ', 20);

        $result = $compressor->compress($payload, 'gzip;q=0.8, br;q=1.0');

        $this->assertSame('brotli', $result['algorithm']);
        $this->assertSame('br', $result['encoding']);
    }

    public function test_falls_back_to_gzip_when_brotli_unavailable_but_preferred(): void
    {
        if (function_exists('brotli_compress')) {
            // When brotli is available, it won't fall back, so we test the gzip path differently
            $this->markTestSkipped('This test requires brotli extension to be absent.');
        }

        if (! function_exists('gzencode')) {
            $this->markTestSkipped('The zlib extension is required for this test.');
        }

        $compressor = $this->app->make(SynapseToonCompressor::class);
        $payload = str_repeat('Fallback test payload for compression. ', 20);

        $result = $compressor->compress($payload, 'br;q=1.0, gzip;q=0.8');

        $this->assertSame('gzip', $result['algorithm']);
        $this->assertSame('gzip', $result['encoding']);
    }

    public function test_brotli_with_custom_quality(): void
    {
        if (! function_exists('brotli_compress')) {
            $this->markTestSkipped('The brotli extension is required for this test.');
        }

        $this->app['config']->set('synapse-toon.compression.brotli.quality', 4);

        $compressor = $this->app->make(SynapseToonCompressor::class);
        $payload = str_repeat('Quality test payload. ', 20);

        $result = $compressor->compress($payload, 'br');

        $this->assertSame('brotli', $result['algorithm']);
        $this->assertNotSame($payload, $result['body']);
    }

    public function test_brotli_text_mode(): void
    {
        if (! function_exists('brotli_compress')) {
            $this->markTestSkipped('The brotli extension is required for this test.');
        }

        $this->app['config']->set('synapse-toon.compression.brotli.mode', 'text');

        $compressor = $this->app->make(SynapseToonCompressor::class);
        $payload = str_repeat('Text mode compression test. ', 20);

        $result = $compressor->compress($payload, 'br');

        $this->assertSame('brotli', $result['algorithm']);
        $this->assertSame('br', $result['encoding']);
    }
}
