<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use VinkiusLabs\SynapseToon\SynapseToonServiceProvider;
use VinkiusLabs\SynapseToon\Test\TestCase;

class SynapseToonServiceProviderTest extends TestCase
{
    public function test_register_binds_singleton_and_aliases(): void
    {
        $provider = new SynapseToonServiceProvider($this->app);
        $provider->register();

        $manager = $this->app->make('synapsetoon');

        $this->assertSame($manager, $this->app->make('synapsetoon.manager'));
        $this->assertSame($manager, $this->app->make('synapsetoon'));
    }

    public function test_boot_registers_publishing_and_is_running(): void
    {
        $provider = new SynapseToonServiceProvider($this->app);

        $this->invokePrivate($provider, 'registerPublishing', $this->fakeApp(false));
        $this->invokePrivate($provider, 'registerPublishing', $this->app);

        $provider->boot();

        $published = SynapseToonServiceProvider::pathsToPublish(SynapseToonServiceProvider::class, 'synapse-toon-config');
        $this->assertNotEmpty($published);
    }

    private function invokePrivate(SynapseToonServiceProvider $provider, string $method, $app): void
    {
        $reflection = new \ReflectionClass(SynapseToonServiceProvider::class);
        $property = $reflection->getProperty('app');
        $property->setAccessible(true);
        $original = $property->getValue($provider);
        $property->setValue($provider, $app);

        $callable = \Closure::bind(function () use ($method) {
            return $this->{$method}();
        }, $provider, SynapseToonServiceProvider::class);

        $callable();

        $property->setValue($provider, $original);
    }

    private function fakeApp(bool $console)
    {
        return new class($console, $this->app)
        {
            private bool $console;

            private $delegate;

            public function __construct(bool $console, $delegate)
            {
                $this->console = $console;
                $this->delegate = $delegate;
            }

            public function runningInConsole(): bool
            {
                return $this->console;
            }

            public function __call($method, $parameters)
            {
                return $this->delegate->{$method}(...$parameters);
            }
        };
    }
}
