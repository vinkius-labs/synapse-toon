<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;
use VinkiusLabs\SynapseToon\Test\TestCase;

class SynapseToonEncoderTest extends TestCase
{
    public function test_encode_and_decode_with_dictionary(): void
    {
        $encoder = $this->app->make(SynapseToonEncoder::class);

        $payload = [
            'message' => 'hello world',
            'meta' => ['tokens' => 120],
        ];

        $encoded = $encoder->encode($payload, ['dictionary' => ['message' => 'm']]);
        $decoded = $encoder->decode($encoded, ['dictionary' => ['message' => 'm']]);

        $this->assertSame($payload, $decoded);
        $this->assertStringNotContainsString('message', $encoded);
    }

    public function test_encode_chunk_truncates_large_payloads(): void
    {
        $encoder = $this->app->make(SynapseToonEncoder::class);
        $chunk = str_repeat('large ', 1000);

        $encoded = $encoder->encodeChunk($chunk, ['max_size' => 128, 'delimiter' => '|']);

        $this->assertStringEndsWith('|', $encoded);
        $this->assertLessThanOrEqual(129, strlen($encoded));
    }
}
