<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use ReflectionProperty;
use VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;
use VinkiusLabs\SynapseToon\Jobs\SynapseToonProcessLLMBatchJob;
use VinkiusLabs\SynapseToon\Routing\SynapseToonLLMRouter;
use VinkiusLabs\SynapseToon\Test\TestCase;

class BatchMetricsSpy extends SynapseToonMetrics
{
    public array $records = [];

    public function __construct($config, $container)
    {
        parent::__construct($config, $container);
    }

    public function record(array $payload): void
    {
        $this->records[] = $payload;
    }
}

class RouterSpy extends SynapseToonLLMRouter
{
    public array $requests = [];

    public function __construct($config, $container, SynapseToonEncoder $encoder)
    {
        parent::__construct($config, $container, $encoder);
    }

    public function send(mixed $payload, array $context = []): array
    {
        $this->requests[] = compact('payload', 'context');

        return ['status' => 'accepted'];
    }
}

class SynapseToonProcessLLMBatchJobTest extends TestCase
{
    public function test_job_handles_batch_and_records_metrics(): void
    {
        $encoder = $this->app->make(SynapseToonEncoder::class);

        $metrics = new BatchMetricsSpy($this->app['config'], $this->app);
        $router = new RouterSpy($this->app['config'], $this->app, $encoder);

        $job = new SynapseToonProcessLLMBatchJob([
            ['prompt' => 'hello'],
            ['prompt' => 'world'],
        ], [
            'queue' => 'llm',
            'dictionary' => ['requests' => 'r'],
            'estimated_json_tokens' => 200,
            'savings_percent' => 35,
        ]);

        $queueProperty = new ReflectionProperty(SynapseToonProcessLLMBatchJob::class, 'queue');
        $queueProperty->setAccessible(true);

        $this->assertSame('llm', $queueProperty->getValue($job));

        $job->handle($encoder, $metrics, $router);

        $this->assertCount(1, $metrics->records);

        $record = $metrics->records[0];

        $this->assertSame('batch', $record['endpoint']);
        $this->assertSame(2, $record['batch_size']);
        $this->assertSame('accepted', $record['response_status']);
        $this->assertSame(200, $record['json_tokens']);
        $this->assertSame(35, $record['savings_percent']);
        $this->assertIsInt($record['toon_tokens']);

        $this->assertCount(1, $router->requests);
        $this->assertTrue($router->requests[0]['context']['batch']);
        $this->assertSame(2, $router->requests[0]['context']['batch_size']);
    }
}
