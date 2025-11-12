<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Analytics\Drivers;

use Illuminate\Support\Facades\Http;
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
        return preg_replace('/[^a-zA-Z0-9:_]/', '_', strtolower($key)) ?? $key;
    }

    protected function normalizeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return '"' . addslashes((string) $value) . '"';
    }
}
