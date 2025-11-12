<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Analytics\Drivers;

use Illuminate\Support\Facades\Log;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonMetricsDriver;

class SynapseToonLogMetricsDriver implements SynapseToonMetricsDriver
{
    public function __construct(protected ?string $channel = null)
    {
    }

    public function record(array $payload): void
    {
        if ($this->channel) {
            Log::channel($this->channel)->info('[Synapse TOON] metrics', $payload);

            return;
        }

        Log::info('[Synapse TOON] metrics', $payload);
    }
}
