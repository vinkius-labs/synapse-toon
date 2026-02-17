<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Contracts;

interface SynapseToonCompressorContract
{
    /**
     * Compress data using the best available algorithm based on request headers and configuration.
     *
     * @param array<string, mixed> $options
     * @return array{body: string, encoding: string|null, algorithm: string|null}
     */
    public function compress(string $payload, ?string $acceptEncoding = null, array $options = []): array;
}
