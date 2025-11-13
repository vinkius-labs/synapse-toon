<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Rag;

use Illuminate\Contracts\Container\Container;

final class Summarizer
{
    public function __construct(private Container $container)
    {
    }

    /**
     * Summarize content using the provided summarizer definition.
     */
    public function summarize(mixed $serviceDefinition, string $content, int $targetTokens): string
    {
        $summarizer = match (true) {
            $serviceDefinition === null => null,
            is_string($serviceDefinition) && $this->container->bound($serviceDefinition) => $this->container->make($serviceDefinition),
            default => $serviceDefinition,
        };

        return match (true) {
            $summarizer === null => $content,
            default => rescue(function () use ($summarizer, $content, $targetTokens) {
                return match (true) {
                    is_callable($summarizer) => (string) $summarizer($content, $targetTokens),
                    is_object($summarizer) && method_exists($summarizer, 'summarize') => (string) $summarizer->summarize($content, $targetTokens),
                    default => $content,
                };
            }, $content),
        };
    }
}
