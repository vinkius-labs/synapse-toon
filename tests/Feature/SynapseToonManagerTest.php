<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use VinkiusLabs\SynapseToon\Test\TestCase;
use VinkiusLabs\SynapseToon\SynapseToonManager;

class SynapseToonManagerTest extends TestCase
{
    public function test_manager_reads_config_values(): void
    {
        $manager = $this->app->make(SynapseToonManager::class);

        $this->assertNotNull($manager);
        $this->assertSame(true, $manager->config('defaults.enabled'));
        $this->assertSame(80, $manager->config('defaults.quality'));
    }

    public function test_manager_proxies_encoding_methods(): void
    {
        $manager = $this->app->make(SynapseToonManager::class);

        $payload = ['foo' => 'bar'];

        $encoded = $manager->encode($payload);

        $this->assertIsString($encoded);
        $this->assertStringContainsString('"foo":"bar"', $encoded);
        $this->assertSame($payload, $manager->decode($encoded));

        $chunk = $manager->encodeChunk('   streamed data   ');
        $this->assertStringContainsString('streamed data', $chunk);
    }
}
