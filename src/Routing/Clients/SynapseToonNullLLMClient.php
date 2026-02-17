<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Routing\Clients;

use VinkiusLabs\SynapseToon\Contracts\SynapseToonLLMClient;

class SynapseToonNullLLMClient implements SynapseToonLLMClient
{
    public function send(array $payload): array
    {
        return [
            'status' => 'noop',
            'payload' => $payload,
        ];
    }
}
