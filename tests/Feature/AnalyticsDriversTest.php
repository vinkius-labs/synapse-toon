<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonDatadogMetricsDriver;
use VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonLogMetricsDriver;
use VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonNullMetricsDriver;
use VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonPrometheusMetricsDriver;
use VinkiusLabs\SynapseToon\Test\TestCase;

class AnalyticsDriversTest extends TestCase
{
    public function test_log_driver_uses_default_channel(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('[Synapse TOON] metrics', ['foo' => 'bar']);

        $driver = new SynapseToonLogMetricsDriver();
        $driver->record(['foo' => 'bar']);
    }

    public function test_log_driver_uses_custom_channel(): void
    {
        Log::shouldReceive('channel')
            ->once()
            ->with('stack')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('[Synapse TOON] metrics', ['foo' => 'bar']);

        $driver = new SynapseToonLogMetricsDriver('stack');
        $driver->record(['foo' => 'bar']);
    }

    public function test_prometheus_driver_skips_when_gateway_missing(): void
    {
        Http::fake();

        $driver = new SynapseToonPrometheusMetricsDriver(null);
        $driver->record(['foo' => 'bar']);

        Http::assertNothingSent();
    }

    public function test_prometheus_driver_pushes_metrics(): void
    {
        Http::fake();

        $driver = new SynapseToonPrometheusMetricsDriver('https://push.gateway', 'toon-job');
        $driver->record(['foo bar' => 10, 'flag' => true]);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && str_contains($request->url(), 'https://push.gateway/metrics/job/toon-job')
                && str_contains($request->body(), 'foo_bar 10')
                && str_contains($request->body(), 'flag 1');
        });
    }

    public function test_datadog_driver_skips_without_api_key(): void
    {
        Http::fake();

        $driver = new SynapseToonDatadogMetricsDriver(null);
        $driver->record(['savings_percent' => 12]);

        Http::assertNothingSent();
    }

    public function test_datadog_driver_posts_payload(): void
    {
        Http::fake();

        $driver = new SynapseToonDatadogMetricsDriver('abc123', 'https://api.example.com/series');
        $driver->record([
            'savings_percent' => 25,
            'endpoint' => 'batch',
        ]);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://api.example.com/series'
                && $request->hasHeader('DD-API-KEY', 'abc123')
                && ($payload['series'][0]['metric'] ?? null) === 'synapse_toon.savings'
                && ($payload['series'][0]['tags'] ?? null) === ['savings_percent:25', 'endpoint:batch'];
        });
    }

    public function test_null_driver_is_noop(): void
    {
        $driver = new SynapseToonNullMetricsDriver();

        $this->assertNull($driver->record(['anything' => 'goes']));
    }
}
