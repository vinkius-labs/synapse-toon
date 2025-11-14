<?php

namespace VinkiusLabs\SynapseToon\Test;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Illuminate\Testing\TestResponse;
use VinkiusLabs\SynapseToon\SynapseToonServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Required by Laravel 11/Testbench 9 HTTP testing internals.
     */
    public static ?TestResponse $latestResponse = null;

    protected function getPackageProviders($app)
    {
        return [
            SynapseToonServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('synapse-toon.metrics.enabled', false);
        $app['config']->set('synapse-toon.compression.enabled', false);
    }
}
