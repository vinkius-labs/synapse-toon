<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Contracts;

interface SynapseToonLLMClient
{
    /**
     * Dispatch a prompt to the underlying LLM provider.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function send(array $payload): array;
}
