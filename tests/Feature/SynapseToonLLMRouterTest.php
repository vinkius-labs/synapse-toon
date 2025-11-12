<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use VinkiusLabs\SynapseToon\Routing\SynapseToonLLMRouter;
use VinkiusLabs\SynapseToon\Test\TestCase;

class SynapseToonLLMRouterTest extends TestCase
{
    public function test_router_selects_strategy_based_on_complexity(): void
    {
        $router = $this->app->make(SynapseToonLLMRouter::class);
        $payload = ['message' => str_repeat('hi', 10)];

        $target = $router->route($payload);

        $this->assertSame('gpt-4o-mini', $target);
    }

    public function test_router_falls_back_to_default(): void
    {
        $router = $this->app->make(SynapseToonLLMRouter::class);
        $payload = ['message' => str_repeat('complex', 5000)];

        $target = $router->route($payload);

        $this->assertSame(config('synapse-toon.router.default_target'), $target);
    }
}
