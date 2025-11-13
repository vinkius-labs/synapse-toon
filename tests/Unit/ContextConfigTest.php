<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Test\Unit;

use VinkiusLabs\SynapseToon\Rag\ContextConfig;
use VinkiusLabs\SynapseToon\Test\TestCase;

class ContextConfigTest extends TestCase
{
    public function test_context_config_holds_values(): void
    {
        $config = new ContextConfig(
            limit: 5,
            searchLimit: 20,
            maxTokens: 1000,
            minScore: 0.5,
            maxSnippet: 300,
            cacheTtl: 3600,
            summarize: true,
            summarizer: 'summarizer.service',
            metadataFilters: ['key' => 'value']
        );

        $this->assertEquals(5, $config->limit);
        $this->assertEquals(20, $config->searchLimit);
        $this->assertEquals(1000, $config->maxTokens);
        $this->assertEquals(0.5, $config->minScore);
        $this->assertEquals(300, $config->maxSnippet);
        $this->assertEquals(3600, $config->cacheTtl);
        $this->assertTrue($config->summarize);
        $this->assertEquals('summarizer.service', $config->summarizer);
        $this->assertEquals(['key' => 'value'], $config->metadataFilters);
    }

    public function test_context_config_with_null_summarizer(): void
    {
        $config = new ContextConfig(
            limit: 3,
            searchLimit: 10,
            maxTokens: 500,
            minScore: 0.0,
            maxSnippet: 200,
            cacheTtl: 0,
            summarize: false,
            summarizer: null,
            metadataFilters: []
        );

        $this->assertNull($config->summarizer);
    }
}