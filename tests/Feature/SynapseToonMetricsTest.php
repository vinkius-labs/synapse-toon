<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonMetricsDriver;
use VinkiusLabs\SynapseToon\Test\TestCase;

class SynapseToonMetricsTest extends TestCase
{
    public function test_threshold_prevents_metrics_dispatch(): void
    {
        $this->app['config']->set('synapse-toon.metrics.enabled', true);
        $this->app['config']->set('synapse-toon.metrics.thresholds.minimum_savings_percent', 50);

        $driver = new class implements SynapseToonMetricsDriver {
            public array $records = [];

            public function record(array $payload): void
            {
                $this->records[] = $payload;
            }
        };

        $metrics = $this->makeMetrics($driver);

        $metrics->record([
            'savings_percent' => 10,
            'json_tokens' => 1000,
            'toon_tokens' => 900,
        ]);

        $this->assertCount(0, $driver->records);
    }

    public function test_metrics_are_dispatched_when_threshold_met(): void
    {
        $this->app['config']->set('synapse-toon.metrics.enabled', true);
        $this->app['config']->set('synapse-toon.metrics.thresholds.minimum_savings_percent', 10);

        $driver = new class implements SynapseToonMetricsDriver {
            public array $records = [];

            public function record(array $payload): void
            {
                $this->records[] = $payload;
            }
        };

        $metrics = $this->makeMetrics($driver);

        $metrics->record([
            'savings_percent' => 12,
            'json_tokens' => 1000,
            'toon_tokens' => 880,
        ]);

        $this->assertCount(1, $driver->records);
        $this->assertSame(880, $driver->records[0]['toon_tokens']);
        $this->assertSame(12, $driver->records[0]['savings_percent']);
        $this->assertArrayHasKey('timestamp', $driver->records[0]);
    }

    public function test_metrics_bails_when_disabled(): void
    {
        $this->app['config']->set('synapse-toon.metrics.enabled', false);

        $driver = new class implements SynapseToonMetricsDriver {
            public int $calls = 0;

            public function record(array $payload): void
            {
                $this->calls++;
            }
        };

        $metrics = $this->makeMetrics($driver);
        $metrics->record(['savings_percent' => 99]);

        $this->assertSame(0, $driver->calls);
    }

    public function test_metrics_uses_log_driver_configuration(): void
    {
        $this->app['config']->set('synapse-toon.metrics.enabled', true);
        $this->app['config']->set('synapse-toon.metrics.driver', 'log');
        $this->app['config']->set('synapse-toon.metrics.drivers.log.channel', 'stack');

        Log::shouldReceive('channel')->once()->with('stack')->andReturnSelf();
        Log::shouldReceive('info')->once();

        $metrics = $this->app->make(SynapseToonMetrics::class);
        $metrics->record(['savings_percent' => 100]);

        $this->assertInstanceOf(\VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonLogMetricsDriver::class, $this->extractDriver($metrics));
    }

    public function test_metrics_uses_prometheus_driver(): void
    {
        $this->app['config']->set('synapse-toon.metrics.enabled', true);
        $this->app['config']->set('synapse-toon.metrics.driver', 'prometheus');
        $this->app['config']->set('synapse-toon.metrics.drivers.prometheus.push_gateway', 'https://push.example');
        $this->app['config']->set('synapse-toon.metrics.drivers.prometheus.job', 'toon');

        Http::fake();

        $metrics = $this->app->make(SynapseToonMetrics::class);
        $metrics->record(['savings_percent' => 42]);

        Http::assertSent(function ($request) {
            return $request->method() === 'PUT' && str_contains($request->url(), 'https://push.example/metrics/job/toon');
        });

        $this->assertInstanceOf(\VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonPrometheusMetricsDriver::class, $this->extractDriver($metrics));
    }

    public function test_metrics_uses_datadog_driver(): void
    {
        $this->app['config']->set('synapse-toon.metrics.enabled', true);
        $this->app['config']->set('synapse-toon.metrics.driver', 'datadog');
        $this->app['config']->set('synapse-toon.metrics.drivers.datadog.api_key', 'xyz');
        $this->app['config']->set('synapse-toon.metrics.drivers.datadog.endpoint', 'https://api.datadoghq.com');

        Http::fake();

        $metrics = $this->app->make(SynapseToonMetrics::class);
        $metrics->record(['savings_percent' => 15, 'endpoint' => 'demo']);

        Http::assertSentCount(1);

        $this->assertInstanceOf(\VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonDatadogMetricsDriver::class, $this->extractDriver($metrics));
    }

    public function test_metrics_falls_back_to_null_driver(): void
    {
        $this->app['config']->set('synapse-toon.metrics.enabled', true);
        $this->app['config']->set('synapse-toon.metrics.driver', 'missing');

        $metrics = $this->app->make(SynapseToonMetrics::class);
        $metrics->record(['savings_percent' => 5]);

        $this->assertInstanceOf(\VinkiusLabs\SynapseToon\Analytics\Drivers\SynapseToonNullMetricsDriver::class, $this->extractDriver($metrics));
    }

    protected function makeMetrics(SynapseToonMetricsDriver $driver): SynapseToonMetrics
    {
        return new class($this->app['config'], $this->app, $driver) extends SynapseToonMetrics {
            public function __construct($config, $container, private SynapseToonMetricsDriver $fakeDriver)
            {
                parent::__construct($config, $container);
            }

            protected function driver(): SynapseToonMetricsDriver
            {
                return $this->fakeDriver;
            }
        };
    }

    protected function extractDriver(SynapseToonMetrics $metrics): SynapseToonMetricsDriver
    {
        $accessor = \Closure::bind(function () {
            return $this->driver();
        }, $metrics, SynapseToonMetrics::class);

        return $accessor();
    }
}
