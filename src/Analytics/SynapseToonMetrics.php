<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Analytics;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonDatadogMetricsDriver;
use VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonLogMetricsDriver;
use VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonNullMetricsDriver;
use VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonPrometheusMetricsDriver;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonMetricsDriver;

class SynapseToonMetrics
{
    protected ?SynapseToonMetricsDriver $driver = null;

    public function __construct(protected ConfigRepository $config, protected Container $container)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function record(array $payload): void
    {
        if (! $this->config->get('synapse-toon.metrics.enabled', true)) {
            return;
        }

        $payload = $this->enrich($payload);

        if ($this->belowThreshold($payload)) {
            return;
        }

        $this->driver()->record($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function enrich(array $payload): array
    {
        $defaults = [
            'timestamp' => microtime(true),
        ];

        return array_merge($defaults, $payload);
    }

    protected function belowThreshold(array $payload): bool
    {
        $threshold = $this->config->get('synapse-toon.metrics.thresholds.minimum_savings_percent', 0);

        if ($threshold <= 0) {
            return false;
        }

        $savings = Arr::get($payload, 'savings_percent', 0);

        return is_numeric($savings) && $savings < $threshold;
    }

    protected function driver(): SynapseToonMetricsDriver
    {
        if ($this->driver instanceof SynapseToonMetricsDriver) {
            return $this->driver;
        }

        $driver = $this->config->get('synapse-toon.metrics.driver', 'log');
        $config = $this->config->get('synapse-toon.metrics.drivers.' . $driver, []);

        return $this->driver = match ($driver) {
            'log' => new SynapseToonLogMetricsDriver($config['channel'] ?? null),
            'prometheus' => new SynapseToonPrometheusMetricsDriver($config['push_gateway'] ?? null, $config['job'] ?? 'synapse-toon'),
            'datadog' => new SynapseToonDatadogMetricsDriver($config['api_key'] ?? null, $config['endpoint'] ?? 'https://api.datadoghq.com/api/v1/series'),
            default => new SynapseToonNullMetricsDriver(),
        };
    }
}
