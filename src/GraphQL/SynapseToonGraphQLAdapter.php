<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\GraphQL;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;

class SynapseToonGraphQLAdapter
{
    public function __construct(protected SynapseToonEncoder $encoder)
    {
    }

    public function toResponse(Request $request, mixed $result): Response
    {
        $payload = $this->normalizeResult($result);
        $encoded = $this->encoder->encode($payload);

        return new Response(
            $encoded,
            Arr::has($payload, 'errors') ? 206 : 200,
            [
                'Content-Type' => (string) config('synapse-toon.defaults.content_type', 'application/x-synapse-toon'),
                'X-Synapse-TOON-GraphQL' => 'enabled',
            ]
        );
    }

    private function normalizeResult(mixed $result): array
    {
        return match (true) {
            is_array($result) => $result,
            is_object($result) => $this->normalizeObject($result),
            default => ['data' => $result],
        };
    }

    private function normalizeObject(object $result): array
    {
        return match (true) {
            method_exists($result, 'toArray') => $result->toArray(),
            method_exists($result, 'jsonSerialize') => (array) $result->jsonSerialize(),
            $this->isGraphQLResult($result) => array_filter([
                'data' => $result->data ?? null,
                'errors' => $result->errors ?? null,
            ]),
            default => ['data' => $result],
        };
    }

    private function isGraphQLResult(object $result): bool
    {
        return property_exists($result, 'data') || property_exists($result, 'errors');
    }
}
