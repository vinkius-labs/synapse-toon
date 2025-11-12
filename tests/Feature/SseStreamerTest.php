<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use Illuminate\Http\Request;
use VinkiusLabs\SynapseToon\Streaming\SynapseToonSseStreamer;
use VinkiusLabs\SynapseToon\Test\TestCase;
use function collect;

class SynapseToonSseStreamerTest extends TestCase
{
    public function test_streamer_outputs_sse_events(): void
    {
        $streamer = $this->app->make(SynapseToonSseStreamer::class);
        $request = Request::create('/api/chat', 'GET');

        $chunks = collect(['hello', 'world']);
        $response = $streamer->stream($request, $chunks, fn ($chunk) => ['delta' => $chunk]);

        $output = '';
        ob_start(function ($buffer) use (&$output) {
            $output .= $buffer;

            return '';
        });

        $response->sendContent();

        ob_end_clean();

        $this->assertStringContainsString('event: update', $output);
        $this->assertStringContainsString('data:', $output);
        $this->assertStringContainsString('hello', $output);
        $this->assertStringContainsString('world', $output);
    }
}
