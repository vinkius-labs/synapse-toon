<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Streaming;

use Closure;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;

class SynapseToonSseStreamer
{
    private const DEFAULT_HEADERS = [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache, no-transform',
        'X-Accel-Buffering' => 'no',
        'X-Synapse-TOON-Format' => 'streaming',
    ];

    public function __construct(
        protected ResponseFactory $responseFactory,
        protected SynapseToonEncoder $encoder,
    ) {
    }

    /**
     * @param iterable<mixed> $stream
     * @param array<string, string> $headers
     */
    public function stream(Request $request, iterable $stream, ?Closure $transform = null, array $headers = []): StreamedResponse
    {
        return $this->responseFactory->stream(
            fn () => $this->streamChunks($stream, $transform),
            200,
            [...self::DEFAULT_HEADERS, ...$headers]
        );
    }

    private function streamChunks(iterable $stream, ?Closure $transform): void
    {
        foreach ($stream as $chunk) {
            $this->emitChunk($transform ? $transform($chunk) : $chunk);
            $this->flushBuffers();
        }
    }

    private function emitChunk(mixed $payload): void
    {
        $encoded = $this->encoder->encodeChunk(
            is_string($payload) ? $payload : $this->encoder->encode($payload)
        );

        echo sprintf(
            "id: %s\nevent: update\ndata: %s\n\n",
            Str::uuid()->toString(),
            $encoded
        );
    }

    private function flushBuffers(): void
    {
        rescue(function () {
            if (function_exists('ob_flush')) {
                @ob_flush();
            }
        }, null);

        flush();
    }
}
