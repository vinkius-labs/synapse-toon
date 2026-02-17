<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Test\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics;
use VinkiusLabs\SynapseToon\Compression\SynapseToonCompressor;
use VinkiusLabs\SynapseToon\Http\Middleware\SynapseToonCompressionMiddleware;
use VinkiusLabs\SynapseToon\Test\TestCase;

class DoubleEncodingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('synapse-toon.compression.enabled', true);
        $this->app['config']->set('synapse-toon.metrics.enabled', false);
    }

    public function test_middleware_does_not_double_encode_synapse_toon_response(): void
    {
        // Simulate a response already encoded via Response::synapseToon()
        $this->app->instance(SynapseToonCompressor::class, new class($this->app['config']) extends SynapseToonCompressor {
            public function __construct($config)
            {
                parent::__construct($config);
            }

            public function compress(string $payload, ?string $acceptEncoding = null, array $options = []): array
            {
                return ['body' => $payload, 'encoding' => null, 'algorithm' => 'none'];
            }
        });

        $middleware = $this->app->make(SynapseToonCompressionMiddleware::class);
        $request = Request::create('/api/test', 'GET');

        $encoder = $this->app->make(\VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder::class);
        $originalPayload = ['message' => 'hello', 'status' => 'ok'];
        $encoded = $encoder->encode($originalPayload);

        // Create a response that already has the TOON content type (as if Response::synapseToon() was used)
        $contentType = config('synapse-toon.defaults.content_type', 'application/x-synapse-toon');
        $preEncodedResponse = response($encoded, 200, ['Content-Type' => $contentType]);

        $response = $middleware->handle($request, fn () => $preEncodedResponse);

        // The content should be the same as the original encoded content (not double-encoded)
        $this->assertSame($encoded, $response->getContent());
    }

    public function test_middleware_encodes_normal_json_response(): void
    {
        $this->app->instance(SynapseToonCompressor::class, new class($this->app['config']) extends SynapseToonCompressor {
            public function __construct($config)
            {
                parent::__construct($config);
            }

            public function compress(string $payload, ?string $acceptEncoding = null, array $options = []): array
            {
                return ['body' => $payload, 'encoding' => null, 'algorithm' => 'none'];
            }
        });

        $middleware = $this->app->make(SynapseToonCompressionMiddleware::class);
        $request = Request::create('/api/test', 'GET');

        // Create a normal JSON response (not pre-encoded)
        $response = $middleware->handle($request, fn () => response()->json(['message' => 'hello']));

        // Response should contain encoded content with TOON content type
        $contentType = config('synapse-toon.defaults.content_type', 'application/x-synapse-toon');
        $this->assertSame($contentType, $response->headers->get('Content-Type'));
        $this->assertStringContainsString('"message"', $response->getContent() ?: '');
    }
}
