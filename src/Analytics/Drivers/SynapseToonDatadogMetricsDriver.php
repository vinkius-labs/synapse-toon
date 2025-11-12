<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Analytics\Drivers;

use Illuminate\Support\Facades\Http;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonMetricsDriver;

class SynapseToonDatadogMetricsDriver implements SynapseToonMetricsDriver
{
    public function __construct(
        protected ?string $apiKey,
        protected string $endpoint = 'https://api.datadoghq.com/api/v1/series',
    ) {
    }

    public function record(array $payload): void
    {
        if (! $this->apiKey) {
            return;
        }

        $series = [
            'series' => [[
                'metric' => 'synapse_toon.savings',
                'points' => [[time(), $payload['savings_percent'] ?? 0]],
                'type' => 'gauge',
                'tags' => $this->buildTags($payload),
            ]],
        ];

        Http::withHeaders([
            'Content-Type' => 'application/json',
            'DD-API-KEY' => $this->apiKey,
        ])->post($this->endpoint, $series);
    }

    protected function buildTags(array $payload): array
    {
        $tags = [];

        foreach ($payload as $key => $value) {
            if (is_scalar($value)) {
                $tags[] = sprintf('%s:%s', $key, $value);
            }
        }

        return $tags;
    }
}
