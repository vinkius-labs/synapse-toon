<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use VinkiusLabs\SynapseToon\SynapseToonServiceProvider;
use VinkiusLabs\SynapseToon\Test\TestCase;

class ResponseMacroTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new SynapseToonServiceProvider($this->app))->boot();
    }

    public function test_synapse_toon_macro_encodes_payload(): void
    {
        $response = Response::synapseToon(['message' => 'hi']);

        $this->assertSame('application/x-synapse-toon', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('message', $response->getContent());
    }

    public function test_request_macro_detects_accept_header(): void
    {
        $request = Request::create('/demo', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/x-synapse-toon']);

        $this->assertTrue($request->wantsToon());
    }
}
