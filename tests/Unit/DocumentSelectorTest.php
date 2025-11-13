<?php

namespace VinkiusLabs\SynapseToon\Test\Unit;

use Illuminate\Support\Collection;
use Mockery;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;
use VinkiusLabs\SynapseToon\Rag\ContextConfig;
use VinkiusLabs\SynapseToon\Rag\DocumentSelector;
use VinkiusLabs\SynapseToon\Rag\Summarizer;
use VinkiusLabs\SynapseToon\Test\TestCase;

class DocumentSelectorTest extends TestCase
{
    private DocumentSelector $selector;
    private SynapseToonEncoder $encoder;
    private Summarizer $summarizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encoder = Mockery::mock(SynapseToonEncoder::class);
        $this->summarizer = Mockery::mock(Summarizer::class);

        $this->selector = new DocumentSelector($this->encoder, $this->summarizer);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_select_with_empty_documents(): void
    {
        $config = new ContextConfig(100, 10, 50, 0.5, 100, 60, false, null, []);
        $documents = collect([]);

        $this->encoder->shouldNotReceive('estimatedTokens');

        [$selected, $tokens] = $this->selector->select($documents, $config);

        $this->assertEmpty($selected);
        $this->assertEquals(0, $tokens);
    }

    public function test_select_filters_empty_content(): void
    {
        $config = new ContextConfig(100, 10, 50, 0.5, 100, 60, false, null, []);
        $documents = collect([
            ['id' => '1', 'content' => '', 'score' => 1.0, 'metadata' => []],
            ['id' => '2', 'content' => 'valid', 'score' => 1.0, 'metadata' => []],
        ]);

        $this->encoder->shouldReceive('estimatedTokens')->with('valid')->andReturn(10);

        [$selected, $tokens] = $this->selector->select($documents, $config);

        $this->assertCount(1, $selected);
        $this->assertEquals('2', $selected[0]['id']);
        $this->assertEquals(10, $tokens);
    }

    public function test_select_documents_that_fit(): void
    {
        $config = new ContextConfig(100, 10, 50, 0.5, 100, 60, false, null, []);
        $documents = collect([
            ['id' => '1', 'content' => 'short content', 'score' => 1.0, 'metadata' => []],
        ]);

        $this->encoder->shouldReceive('estimatedTokens')->with('short content')->andReturn(20);
        $this->encoder->shouldReceive('estimatedTokens')->with('short content')->andReturn(20); // for final

        [$selected, $tokens] = $this->selector->select($documents, $config);

        $this->assertCount(1, $selected);
        $this->assertEquals(20, $tokens);
    }

    public function test_select_summarizes_when_not_fitting(): void
    {
        $config = new ContextConfig(100, 10, 10, 0.5, 100, 60, true, 'summarizer', []);
        $documents = collect([
            ['id' => '1', 'content' => 'long content that needs summarization', 'score' => 1.0, 'metadata' => []],
        ]);

        $this->encoder->shouldReceive('estimatedTokens')->with('long content that needs summarization')->andReturn(50); // doesn't fit 10
        $this->summarizer->shouldReceive('summarize')->with('summarizer', 'long content that needs summarization', 10)->andReturn('summary');
        $this->encoder->shouldReceive('estimatedTokens')->with('summary')->andReturn(5);
        $this->encoder->shouldReceive('estimatedTokens')->with('summary')->andReturn(5); // final

        [$selected, $tokens] = $this->selector->select($documents, $config);

        $this->assertCount(1, $selected);
        $this->assertEquals('summary', $selected[0]['content']);
        $this->assertEquals(5, $tokens);
    }

    public function test_select_skips_when_budget_exhausted(): void
    {
        $config = new ContextConfig(100, 10, 5, 0.5, 100, 60, false, null, []); // maxTokens 5
        $documents = collect([
            ['id' => '1', 'content' => 'content', 'score' => 1.0, 'metadata' => []],
            ['id' => '2', 'content' => 'another', 'score' => 1.0, 'metadata' => []],
        ]);

        $this->encoder->shouldReceive('estimatedTokens')->with('content')->andReturn(10); // doesn't fit
        $this->encoder->shouldReceive('estimatedTokens')->with('another')->andReturn(3); // fits
        $this->encoder->shouldReceive('estimatedTokens')->with('another')->andReturn(3); // final

        [$selected, $tokens] = $this->selector->select($documents, $config);

        $this->assertCount(1, $selected);
        $this->assertEquals('2', $selected[0]['id']);
        $this->assertEquals(3, $tokens);
    }
}