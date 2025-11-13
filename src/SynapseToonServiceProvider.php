<?php

namespace VinkiusLabs\SynapseToon;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics;
use VinkiusLabs\SynapseToon\Caching\SynapseToonEdgeCache;
use VinkiusLabs\SynapseToon\Compression\SynapseToonCompressor;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonDecoder;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;
use VinkiusLabs\SynapseToon\GraphQL\SynapseToonGraphQLAdapter;
use VinkiusLabs\SynapseToon\Http\Middleware\SynapseToonCompressionMiddleware;
use VinkiusLabs\SynapseToon\Http\Middleware\SynapseToonHttp3Middleware;
use VinkiusLabs\SynapseToon\Rag\SynapseToonRagService;
use VinkiusLabs\SynapseToon\Routing\SynapseToonLLMRouter;
use VinkiusLabs\SynapseToon\Streaming\SynapseToonSseStreamer;
use VinkiusLabs\SynapseToon\Support\SynapseToonPayloadAnalyzer;

class SynapseToonServiceProvider extends BaseServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/synapse-toon.php', 'synapse-toon');

        $this->app->singleton(SynapseToonManager::class, static fn ($app) => new SynapseToonManager($app));
        $this->app->alias(SynapseToonManager::class, 'synapsetoon.manager');
        $this->app->alias(SynapseToonManager::class, 'synapsetoon');

        $this->registerCoreBindings();
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerCommands();
        $this->registerMacros();
        $this->registerMiddleware();
    }

    private function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/synapse-toon.php' => $this->app->configPath('synapse-toon.php'),
        ], 'synapse-toon-config');
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Reserved for future artisan commands
        $this->commands([
            // Example: SynapseToonOptimizeCommand::class,
        ]);
    }

    private function registerMacros(): void
    {
        Response::macro('synapseToon', function ($payload, int $status = 200, array $headers = []) {
            /** @var \VinkiusLabs\SynapseToon\SynapseToonManager $manager */
            $manager = app(SynapseToonManager::class);
            $encoded = $manager->encoder()->encode($payload);

            return Response::make($encoded, $status, array_merge([
                'Content-Type' => config('synapse-toon.defaults.content_type', 'application/x-synapse-toon'),
            ], $headers));
        });

        Response::macro('synapseToonStream', function (iterable $stream, ?callable $transform = null, array $headers = []) {
            /** @var \VinkiusLabs\SynapseToon\Streaming\SynapseToonSseStreamer $streamer */
            $streamer = app(SynapseToonSseStreamer::class);

            return $streamer->stream(request(), $stream, $transform ? \Closure::fromCallable($transform) : null, $headers);
        });

        Response::macro('toon', function ($payload, int $status = 200, array $headers = []) {
            return Response::synapseToon($payload, $status, $headers);
        });

        if (! Request::hasMacro('wantsToon')) {
            Request::macro('wantsToon', function (): bool {
                /** @var Request $this */
                return \Illuminate\Support\Str::contains($this->header('Accept', ''), 'application/x-synapse-toon')
                    || $this->boolean('synapse_toon');
            });
        }
    }

    private function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('synapsetoon.http3', SynapseToonHttp3Middleware::class);
        $router->aliasMiddleware('synapsetoon.compression', SynapseToonCompressionMiddleware::class);
    }

    private function registerCoreBindings(): void
    {
        $this->app->singleton(SynapseToonEncoder::class, fn ($app) => new SynapseToonEncoder($app['config']));
        $this->app->singleton(SynapseToonDecoder::class, fn ($app) => new SynapseToonDecoder($app->make(SynapseToonEncoder::class)));
        $this->app->singleton(SynapseToonCompressor::class, fn ($app) => new SynapseToonCompressor($app['config']));
        $this->app->singleton(SynapseToonMetrics::class, fn ($app) => new SynapseToonMetrics($app['config'], $app));
        $this->app->singleton(SynapseToonPayloadAnalyzer::class, fn ($app) => new SynapseToonPayloadAnalyzer($app->make(SynapseToonEncoder::class)));
        $this->app->singleton(SynapseToonRagService::class, fn ($app) => new SynapseToonRagService($app['config'], $app, $app->make(SynapseToonEncoder::class)));
        $this->app->singleton(SynapseToonLLMRouter::class, fn ($app) => new SynapseToonLLMRouter($app['config'], $app, $app->make(SynapseToonEncoder::class)));
        $this->app->singleton(SynapseToonEdgeCache::class, fn ($app) => new SynapseToonEdgeCache($app->make(SynapseToonEncoder::class)));
        $this->app->singleton(SynapseToonSseStreamer::class, fn ($app) => new SynapseToonSseStreamer($app->make(ResponseFactory::class), $app->make(SynapseToonEncoder::class)));
        $this->app->singleton(SynapseToonGraphQLAdapter::class, fn ($app) => new SynapseToonGraphQLAdapter($app->make(SynapseToonEncoder::class)));
    }
}
