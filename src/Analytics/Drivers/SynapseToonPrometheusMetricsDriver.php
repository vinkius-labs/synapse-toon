<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Analytics\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonMetricsDriver;

class SynapseToonPrometheusMetricsDriver implements SynapseToonMetricsDriver
{
    public function __construct(protected ?string $pushGateway = null, protected string $job = 'synapse-toon')
    {
    }

    public function record(array $payload): void
    {
        if (! $this->pushGateway) {
            return;
        }

        $line = $this->formatLine($payload);

        Http::withBody($line, 'text/plain')
            ->put(rtrim($this->pushGateway, '/') . '/metrics/job/' . $this->job);
    }

    protected function formatLine(array $payload): string
    {
        $lines = [];

        foreach ($payload as $key => $value) {
            $lines[] = sprintf('%s %s', $this->sanitize($key), $this->normalizeValue($value));
        }

        return implode("\n", $lines) . "\n";
    }

    protected function sanitize(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9:_]/', '_', Str::lower($key)) ?? $key;
    }

    protected function normalizeValue(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? '1' : '0',
            is_numeric($value) => (string) $value,
            default => '"' . addslashes((string) $value) . '"',
        };
    }
}
