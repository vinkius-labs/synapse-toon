<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Test\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonDatadogMetricsDriver;
use VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonPrometheusMetricsDriver;
use VinkiusLabs\SynapseToon\Test\TestCase;

class MetricsHttpFailureTest extends TestCase
{
    public function test_prometheus_driver_does_not_throw_on_http_failure(): void
    {
        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === '[Synapse TOON] Prometheus push failed'
                    && isset($context['error']);
            });

        $driver = new SynapseToonPrometheusMetricsDriver('https://push.gateway', 'toon-job');

        // Should not throw
        $driver->record(['savings_percent' => 42]);

        $this->assertTrue(true); // Assertion: reached this point without exception
    }

    public function test_prometheus_driver_does_not_throw_on_connection_error(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === '[Synapse TOON] Prometheus push failed';
            });

        $driver = new SynapseToonPrometheusMetricsDriver('https://unreachable.host', 'toon-job');
        $driver->record(['savings_percent' => 30]);

        $this->assertTrue(true);
    }

    public function test_datadog_driver_does_not_throw_on_http_failure(): void
    {
        Http::fake([
            '*' => Http::response(null, 500),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === '[Synapse TOON] Datadog push failed'
                    && isset($context['error']);
            });

        $driver = new SynapseToonDatadogMetricsDriver('api-key-123', 'https://api.example.com/series');

        // Should not throw
        $driver->record(['savings_percent' => 25, 'endpoint' => 'test']);

        $this->assertTrue(true);
    }

    public function test_datadog_driver_does_not_throw_on_connection_error(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('DNS resolution failed');
        });

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === '[Synapse TOON] Datadog push failed';
            });

        $driver = new SynapseToonDatadogMetricsDriver('api-key-123', 'https://unreachable.host/series');
        $driver->record(['savings_percent' => 15]);

        $this->assertTrue(true);
    }
}
