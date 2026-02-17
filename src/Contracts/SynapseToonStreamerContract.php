<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Contracts;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface SynapseToonStreamerContract
{
    /**
     * Stream chunks using Server-Sent Events.
     *
     * @param iterable<mixed> $stream
     * @param array<string, string> $headers
     */
    public function stream(Request $request, iterable $stream, ?Closure $transform = null, array $headers = []): StreamedResponse;
}
