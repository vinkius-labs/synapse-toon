<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use VinkiusLabs\SynapseToon\Compression\SynapseToonCompressor;
use VinkiusLabs\SynapseToon\Http\Middleware\SynapseToonHttp3Middleware;
use VinkiusLabs\SynapseToon\Test\TestCase;

class Http3MiddlewareTest extends TestCase
{
    public function test_http3_middleware_sets_headers_for_http3_clients(): void
    {
        $this->app['config']->set('synapse-toon.http3.enabled', true);

        $middleware = $this->app->make(SynapseToonHttp3Middleware::class);
        $request = Request::create('/api/test', 'GET', [], [], [], [
            'SERVER_PROTOCOL' => 'HTTP/3',
        ]);

        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertSame('enabled', $response->headers->get('X-Synapse-TOON-HTTP3'));
        $this->assertContains($response->headers->get('Content-Encoding'), ['br', null]);
    }

    public function test_http3_middleware_detects_alt_svc_header(): void
    {
        $this->app['config']->set('synapse-toon.http3.enabled', true);

        $this->bindCompressorStub('br');

        $middleware = $this->app->make(SynapseToonHttp3Middleware::class);
        $request = Request::create('/api/test', 'GET', [], [], [], [
            'HTTP_ALT_SVC' => 'h3=":443"',
        ]);

        $response = $middleware->handle($request, fn () => response('alt svc body'));

        $this->assertSame('enabled', $response->headers->get('X-Synapse-TOON-HTTP3'));
        $this->assertSame('br', $response->headers->get('Content-Encoding'));
        $this->assertSame('compressed-body', $response->getContent());
    }

    public function test_http3_middleware_detects_sec_ch_ua(): void
    {
        $this->app['config']->set('synapse-toon.http3.enabled', true);

        $this->bindCompressorStub(null);

        $middleware = $this->app->make(SynapseToonHttp3Middleware::class);
        $request = Request::create('/api/test', 'GET', [], [], [], [
            'HTTP_SEC_CH_UA' => 'browser;h3',
        ]);

        $response = $middleware->handle($request, fn () => response('sec-ch-ua body'));

        $this->assertSame('enabled', $response->headers->get('X-Synapse-TOON-HTTP3'));
        $this->assertNull($response->headers->get('Content-Encoding'));
        $this->assertSame('sec-ch-ua body', $response->getContent());
    }

    public function test_http3_middleware_returns_early_when_disabled(): void
    {
        $this->app['config']->set('synapse-toon.http3.enabled', false);

        $middleware = $this->app->make(SynapseToonHttp3Middleware::class);
        $request = Request::create('/api/test', 'GET', [], [], [], [
            'SERVER_PROTOCOL' => 'HTTP/3',
        ]);

        $response = $middleware->handle($request, fn () => response('disabled body'));

        $this->assertNull($response->headers->get('X-Synapse-TOON-HTTP3'));
    }

    public function test_http3_middleware_skips_binary_responses(): void
    {
        $this->app['config']->set('synapse-toon.http3.enabled', true);

        $middleware = $this->app->make(SynapseToonHttp3Middleware::class);
        $request = Request::create('/api/test', 'GET', [], [], [], [
            'SERVER_PROTOCOL' => 'HTTP/3',
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'toon');
        file_put_contents($tempFile, 'binary');

        $response = $middleware->handle($request, fn () => new BinaryFileResponse($tempFile));

        $this->assertNull($response->headers->get('Content-Encoding'));

        @unlink($tempFile);
    }

    public function test_http3_middleware_respects_existing_encoding(): void
    {
        $this->app['config']->set('synapse-toon.http3.enabled', true);

        $middleware = $this->app->make(SynapseToonHttp3Middleware::class);
        $request = Request::create('/api/test', 'GET', [], [], [], [
            'SERVER_PROTOCOL' => 'HTTP/3',
        ]);

        $response = $middleware->handle($request, fn () => response('already compressed', 200, ['Content-Encoding' => 'gzip']));

        $this->assertSame('gzip', $response->headers->get('Content-Encoding'));
        $this->assertSame('already compressed', $response->getContent());
    }

    protected function bindCompressorStub(?string $encoding): void
    {
        $this->app->instance(SynapseToonCompressor::class, new class($this->app['config'], $encoding) extends SynapseToonCompressor {
            public function __construct($config, private ?string $encoding)
            {
                parent::__construct($config);
            }

            public function compress(string $payload, ?string $acceptEncoding = null, array $options = []): array
            {
                if (! $this->encoding) {
                    return ['body' => $payload, 'encoding' => null, 'algorithm' => 'none'];
                }

                return ['body' => 'compressed-body', 'encoding' => $this->encoding, 'algorithm' => $this->encoding];
            }
        });
    }
}
