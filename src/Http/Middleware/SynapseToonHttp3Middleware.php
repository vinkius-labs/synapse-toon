<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use VinkiusLabs\SynapseToon\Compression\SynapseToonCompressor;

class SynapseToonHttp3Middleware
{
    public function __construct(
        protected ConfigRepository $config,
        protected SynapseToonCompressor $compressor,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        return $this->shouldProcessHttp3($request, $response)
            ? $this->processHttp3Response($request, $response)
            : $response;
    }

    private function shouldProcessHttp3(Request $request, Response $response): bool
    {
        return (bool) $this->config->get('synapse-toon.http3.enabled', true)
            && $this->isHttp3($request)
            && ! $response instanceof BinaryFileResponse
            && ! $response->headers->has('Content-Encoding')
            && $response->getContent() !== false;
    }

    private function processHttp3Response(Request $request, Response $response): Response
    {
        $this->setHttp3Headers($response);

        return $this->applyCompression($response);
    }

    private function setHttp3Headers(Response $response): void
    {
        $response->headers->set('X-Synapse-TOON-HTTP3', 'enabled');
        $response->headers->set(
            'Alt-Svc',
            (string) $this->config->get('synapse-toon.http3.alt_svc_header', 'h3=":443"')
        );
    }

    private function applyCompression(Response $response): Response
    {
        $body = $response->getContent();
        
        if ($body === false || $body === '') {
            return $response;
        }

        $algorithm = (string) $this->config->get('synapse-toon.http3.prefer_compression', 'brotli');
        $compression = $this->compressor->compress($body, $algorithm === 'brotli' ? 'br' : $algorithm);

        if (($compression['encoding'] ?? null) !== null) {
            $response->setContent($compression['body']);
            $response->headers->set('Content-Encoding', $compression['encoding']);
        }

        return $response;
    }

    private function isHttp3(Request $request): bool
    {
        return str_contains(strtolower((string) $request->server('SERVER_PROTOCOL')), 'http/3')
            || $this->hasHttp3AltSvc($request)
            || $this->hasHttp3Capability($request);
    }

    private function hasHttp3AltSvc(Request $request): bool
    {
        $altSvc = strtolower((string) ($request->server('HTTP_ALT_SVC') ?? $request->header('Alt-Svc')));
        
        return $altSvc !== '' && str_contains($altSvc, 'h3');
    }

    private function hasHttp3Capability(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Sec-CH-UA')), 'h3');
    }
}
