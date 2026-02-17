<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Contracts;

interface SynapseToonMetricsDriver
{
    /**
     * Record metric values captured from a Synapse TOON operation.
     *
     * @param array<string, mixed> $payload
     */
    public function record(array $payload): void;
}
