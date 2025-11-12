<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics;
use VinkiusLabs\SynapseToon\Compression\SynapseToonCompressor;
use VinkiusLabs\SynapseToon\Http\Middleware\SynapseToonCompressionMiddleware;
use VinkiusLabs\SynapseToon\Test\TestCase;

class CompressionMetricsSpy extends SynapseToonMetrics
{
    public bool $called = false;

    public function __construct($config, $container)
    {
        parent::__construct($config, $container);
    }

    public function record(array $payload): void
    {
        $this->called = true;
    }
}

class CompressionMiddlewareTest extends TestCase
{
    public function test_middleware_encodes_and_compresses_response(): void
    {
        $this->app['config']->set('synapse-toon.compression.enabled', true);
        $this->app['config']->set('synapse-toon.metrics.enabled', true);
        $this->app['config']->set('synapse-toon.metrics.driver', 'null');

        $metrics = new CompressionMetricsSpy($this->app['config'], $this->app);
        $this->app->instance(SynapseToonMetrics::class, $metrics);

        $middleware = $this->app->make(SynapseToonCompressionMiddleware::class);

        $request = Request::create('/api/test', 'GET', [], [], [], ['HTTP_ACCEPT_ENCODING' => 'gzip']);

        $response = $middleware->handle($request, fn () => response()->json(['message' => 'hello']));

        $this->assertSame('application/x-synapse-toon', $response->headers->get('Content-Type'));
        $this->assertSame('gzip', $response->headers->get('Content-Encoding'));
        $this->assertSame('gzip', $response->headers->get('X-Synapse-TOON-Compressed'));

        $this->assertTrue($metrics->called);
    }

    public function test_middleware_bypasses_streamed_response(): void
    {
        $middleware = $this->app->make(SynapseToonCompressionMiddleware::class);
        $request = Request::create('/stream', 'GET');

        $stream = new StreamedResponse(function () {
            echo 'chunk';
        });

        $response = $middleware->handle($request, fn () => $stream);

        $this->assertSame($stream, $response);
    }

    public function test_middleware_bypasses_binary_response(): void
    {
        $middleware = $this->app->make(SynapseToonCompressionMiddleware::class);
        $request = Request::create('/binary', 'GET');

        $tempFile = tempnam(sys_get_temp_dir(), 'toon-bin');
        file_put_contents($tempFile, 'content');

        $binary = new BinaryFileResponse($tempFile);

        $response = $middleware->handle($request, fn () => $binary);

        $this->assertSame($binary, $response);

        @unlink($tempFile);
    }

    public function test_middleware_bypasses_sse_content_type(): void
    {
        $middleware = $this->app->make(SynapseToonCompressionMiddleware::class);
        $request = Request::create('/sse', 'GET');

        $sse = response('event stream', 200, ['Content-Type' => 'text/event-stream']);

        $response = $middleware->handle($request, fn () => $sse);

        $this->assertSame($sse, $response);
    }

    public function test_middleware_extracts_original_payload_from_responses(): void
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
        $request = Request::create('/payload', 'GET');

        $jsonResponse = new JsonResponse(['one' => 1]);
        $response = $middleware->handle($request, fn () => $jsonResponse);
        $this->assertStringContainsString('"one"', $response->getContent() ?: '');

        $originalResponse = new class extends Response {
            public function __construct()
            {
                parent::__construct('ignored');
            }

            public function getOriginalContent()
            {
                return ['two' => 2];
            }
        };

        $responseOriginal = $middleware->handle($request, fn () => $originalResponse);
        $this->assertStringContainsString('"two"', $responseOriginal->getContent() ?: '');

        $stringResponse = response(json_encode(['three' => 3]));
        $responseString = $middleware->handle($request, fn () => $stringResponse);
        $this->assertStringContainsString('"three"', $responseString->getContent() ?: '');
    }
}
