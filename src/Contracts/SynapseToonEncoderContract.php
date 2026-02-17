<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Contracts;

interface SynapseToonEncoderContract
{
    /**
     * Encode a payload into Synapse TOON format.
     *
     * @param mixed $payload
     * @param array<string, mixed> $options
     */
    public function encode(mixed $payload, array $options = []): string;

    /**
     * Decode a Synapse TOON payload back to its original structure.
     *
     * @param array<string, mixed> $options
     */
    public function decode(string $payload, array $options = []): mixed;

    /**
     * Encode a streaming chunk applying TOON heuristics.
     *
     * @param array<string, mixed> $options
     */
    public function encodeChunk(string $chunk, array $options = []): string;

    /**
     * Approximate complexity score between 0 and 1 based on payload structure.
     */
    public function complexityScore(mixed $payload): float;

    /**
     * Estimate the number of tokens for a given payload.
     */
    public function estimatedTokens(mixed $payload): int;

    /**
     * Normalize a payload into an array representation.
     *
     * @return array<string, mixed>|array<int, mixed>
     */
    public function normalize(mixed $payload): array;
}
