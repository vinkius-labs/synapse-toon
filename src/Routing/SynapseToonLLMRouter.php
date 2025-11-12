<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Routing;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonLLMClient;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;
use VinkiusLabs\SynapseToon\Routing\Clients\SynapseToonNullLLMClient;

class SynapseToonLLMRouter
{
    private array $strategies;

    public function __construct(
        protected ConfigRepository $config,
        protected Container $container,
        protected SynapseToonEncoder $encoder,
    ) {
        $this->strategies = (array) $this->config->get('synapse-toon.router.strategies', []);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function route(mixed $payload, array $context = []): string
    {
        if (! (bool) $this->config->get('synapse-toon.router.enabled', true)) {
            return $this->getDefaultTarget();
        }

        $complexity = $context['complexity'] ?? $this->encoder->complexityScore($payload);
        $tokens = $context['tokens'] ?? $this->encoder->estimatedTokens($payload);

        foreach ($this->strategies as $strategy) {
            if ($this->matchesStrategy($complexity, $tokens, $strategy)) {
                return $strategy['target'] ?? $this->getDefaultTarget();
            }
        }

        return $this->getDefaultTarget();
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function send(mixed $payload, array $context = []): array
    {
        $target = $this->route($payload, $context);
        $client = $this->resolveClient($target, $context['connection'] ?? null);

        return $client->send([
            'target' => $target,
            'payload' => $payload,
            'context' => $context,
        ]);
    }

    private function resolveClient(string $target, ?string $connection): SynapseToonLLMClient
    {
        if ($connection !== null) {
            return $this->container->bound($connection)
                ? $this->container->make($connection)
                : new SynapseToonNullLLMClient();
        }

        $clientKey = "synapse-toon.router.clients.{$target}";

        return $this->container->bound($clientKey)
            ? $this->container->make($clientKey)
            : new SynapseToonNullLLMClient();
    }

    private function matchesStrategy(float|int $complexity, int $tokens, array $strategy): bool
    {
        $maxComplexity = $strategy['max_complexity'] ?? 1;
        $maxTokens = $strategy['max_tokens'] ?? PHP_INT_MAX;

        return $complexity <= $maxComplexity && $tokens <= $maxTokens;
    }

    private function getDefaultTarget(): string
    {
        return (string) $this->config->get('synapse-toon.router.default_target', 'default');
    }
}
