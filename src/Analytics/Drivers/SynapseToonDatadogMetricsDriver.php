<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Analytics\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        rescue(
            fn () => Http::withHeaders([
                'Content-Type' => 'application/json',
                'DD-API-KEY' => $this->apiKey,
            ])->post($this->endpoint, $series)
                ->throw(),
            fn (\Throwable $e) => Log::warning('[Synapse TOON] Datadog push failed', [
                'error' => $e->getMessage(),
            ]),
            false
        );
    }

    protected function buildTags(array $payload): array
    {
        return collect($payload)
            ->filter(fn ($value) => is_scalar($value))
            ->map(fn ($value, $key) => sprintf('%s:%s', $key, $value))
            ->values()
            ->all();
    }
}
