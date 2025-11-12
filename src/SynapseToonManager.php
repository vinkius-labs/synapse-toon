<?php

namespace VinkiusLabs\SynapseToon;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics;
use VinkiusLabs\SynapseToon\Caching\SynapseToonEdgeCache;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonDecoder;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;
use VinkiusLabs\SynapseToon\GraphQL\SynapseToonGraphQLAdapter;
use VinkiusLabs\SynapseToon\Rag\SynapseToonRagService;
use VinkiusLabs\SynapseToon\Routing\SynapseToonLLMRouter;
use VinkiusLabs\SynapseToon\Streaming\SynapseToonSseStreamer;
use VinkiusLabs\SynapseToon\Support\SynapseToonPayloadAnalyzer;

class SynapseToonManager
{
    protected ConfigRepository $config;

    public function __construct(protected Container $app)
    {
        $this->config = $app['config'];
    }

    public function config(string $key, $default = null)
    {
        return $this->config->get('synapse-toon.' . $key, $default);
    }

    public function encoder(): SynapseToonEncoder
    {
        return $this->app->make(SynapseToonEncoder::class);
    }

    public function decoder(): SynapseToonDecoder
    {
        return $this->app->make(SynapseToonDecoder::class);
    }

    public function encode(mixed $payload, array $options = []): string
    {
        return $this->encoder()->encode($payload, $options);
    }

    public function encodeChunk(string $chunk, array $options = []): string
    {
        return $this->encoder()->encodeChunk($chunk, $options);
    }

    public function decode(string $payload, array $options = []): mixed
    {
        return $this->encoder()->decode($payload, $options);
    }

    public function metrics(): SynapseToonMetrics
    {
        return $this->app->make(SynapseToonMetrics::class);
    }

    public function analyzer(): SynapseToonPayloadAnalyzer
    {
        return $this->app->make(SynapseToonPayloadAnalyzer::class);
    }

    public function rag(): SynapseToonRagService
    {
        return $this->app->make(SynapseToonRagService::class);
    }

    public function router(): SynapseToonLLMRouter
    {
        return $this->app->make(SynapseToonLLMRouter::class);
    }

    public function edgeCache(): SynapseToonEdgeCache
    {
        return $this->app->make(SynapseToonEdgeCache::class);
    }

    public function streamer(): SynapseToonSseStreamer
    {
        return $this->app->make(SynapseToonSseStreamer::class);
    }

    public function graphql(): SynapseToonGraphQLAdapter
    {
        return $this->app->make(SynapseToonGraphQLAdapter::class);
    }
}
