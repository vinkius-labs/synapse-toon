<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Test\Unit;

use Illuminate\Contracts\Container\Container;
use Mockery;
use VinkiusLabs\SynapseToon\Rag\Summarizer;
use VinkiusLabs\SynapseToon\Test\TestCase;

class SummarizerTest extends TestCase
{
    private Summarizer $summarizer;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = Mockery::mock(Container::class);
        $this->summarizer = new Summarizer($this->container);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_summarize_with_null_service_definition_returns_content(): void
    {
        $content = 'Test content';
        $targetTokens = 100;

        $result = $this->summarizer->summarize(null, $content, $targetTokens);

        $this->assertEquals($content, $result);
    }

    public function test_summarize_with_string_service_definition_resolves_from_container(): void
    {
        $serviceId = 'summarizer.service';
        $content = 'Original content';
        $targetTokens = 50;
        $summarizedContent = 'Summarized';

        $summarizerInstance = new class {
            public function summarize(string $content, int $tokens): string
            {
                return 'Summarized';
            }
        };

        $this->container->shouldReceive('bound')->with($serviceId)->andReturn(true);
        $this->container->shouldReceive('make')->with($serviceId)->andReturn($summarizerInstance);

        $result = $this->summarizer->summarize($serviceId, $content, $targetTokens);

        $this->assertEquals($summarizedContent, $result);
    }

    public function test_summarize_with_callable_service_definition(): void
    {
        $content = 'Content to summarize';
        $targetTokens = 30;
        $expected = 'Callable result';

        $callable = fn(string $c, int $t) => $expected;

        $result = $this->summarizer->summarize($callable, $content, $targetTokens);

        $this->assertEquals($expected, $result);
    }

    public function test_summarize_with_object_having_summarize_method(): void
    {
        $content = 'Object content';
        $targetTokens = 20;
        $expected = 'Object summarized';

        $object = new class {
            public function summarize(string $content, int $tokens): string
            {
                return 'Object summarized';
            }
        };

        $result = $this->summarizer->summarize($object, $content, $targetTokens);

        $this->assertEquals($expected, $result);
    }

    public function test_summarize_falls_back_to_content_on_exception(): void
    {
        $content = 'Fallback content';
        $targetTokens = 10;

        $failingCallable = function () {
            throw new \Exception('Summarization failed');
        };

        $result = $this->summarizer->summarize($failingCallable, $content, $targetTokens);

        $this->assertEquals($content, $result);
    }

    public function test_summarize_with_unbound_string_service_definition_uses_definition_as_is(): void
    {
        $serviceId = 'unbound.service';
        $content = 'Content';
        $targetTokens = 40;

        $this->container->shouldReceive('bound')->with($serviceId)->andReturn(false);

        $result = $this->summarizer->summarize($serviceId, $content, $targetTokens);

        $this->assertEquals($content, $result); // Since $serviceId is not callable/object, it falls back
    }
}