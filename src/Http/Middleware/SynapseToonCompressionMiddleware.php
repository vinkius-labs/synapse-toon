<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics;
use VinkiusLabs\SynapseToon\Compression\SynapseToonCompressor;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;
use VinkiusLabs\SynapseToon\Support\SynapseToonPayloadAnalyzer;

class SynapseToonCompressionMiddleware
{
    public function __construct(
        protected SynapseToonEncoder $encoder,
        protected SynapseToonCompressor $compressor,
        protected SynapseToonMetrics $metrics,
        protected SynapseToonPayloadAnalyzer $analyzer,
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        return $this->shouldBypass($response)
            ? $response
            : $this->processResponse($request, $response);
    }

    private function processResponse(Request $request, SymfonyResponse $response): SymfonyResponse
    {
        $original = $this->extractPayload($response);
        $encoded = $this->encoder->encode($original);

        $this->setEncodedContent($response, $encoded);
        $this->applyCompression($request, $response, $encoded);
        $this->recordMetrics($request, $original, $encoded);

        return $response;
    }

    private function shouldBypass(SymfonyResponse $response): bool
    {
        return $response instanceof StreamedResponse
            || $response instanceof BinaryFileResponse
            || Str::contains((string) $response->headers->get('Content-Type'), 'text/event-stream');
    }

    private function setEncodedContent(SymfonyResponse $response, string $encoded): void
    {
        $response->setContent($encoded);
        $response->headers->set(
            'Content-Type',
            (string) config('synapse-toon.defaults.content_type', 'application/x-synapse-toon')
        );
    }

    private function applyCompression(Request $request, SymfonyResponse $response, string $encoded): void
    {
        $compression = $this->compressor->compress($encoded, $request->header('Accept-Encoding'));

        ($compression['encoding'] ?? null) !== null && (
            $response->setContent($compression['body'])
            && $response->headers->set('Content-Encoding', $compression['encoding'])
        );

        $response->headers->set(
            (string) config('synapse-toon.compression.header', 'X-Synapse-TOON-Compressed'),
            $compression['algorithm'] ?? 'none'
        );
    }

    private function recordMetrics(Request $request, mixed $original, string $encoded): void
    {
        $this->metrics->record([
            ...$this->analyzer->analyze($original, $encoded),
            'endpoint' => $request->path(),
        ]);
    }

    private function extractPayload(SymfonyResponse $response): mixed
    {
        return match (true) {
            $response instanceof JsonResponse => $response->getData(true),
            $response instanceof Response => $response->getOriginalContent(),
            default => $this->decodePayload($response),
        };
    }

    private function decodePayload(SymfonyResponse $response): mixed
    {
        $content = $response->getContent();
        $decoded = json_decode($content, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $content;
    }
}
