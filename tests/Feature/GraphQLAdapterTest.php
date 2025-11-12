<?php

namespace VinkiusLabs\SynapseToon\Test\Feature;

use Illuminate\Http\Request;
use VinkiusLabs\SynapseToon\GraphQL\SynapseToonGraphQLAdapter;
use VinkiusLabs\SynapseToon\Test\TestCase;

class GraphQLAdapterTest extends TestCase
{
    public function test_graphql_adapter_encodes_result(): void
    {
        $adapter = $this->app->make(SynapseToonGraphQLAdapter::class);
        $request = Request::create('/graphql', 'POST');

        $result = new class
        {
            public array $data = ['hello' => 'world'];
        };

        $response = $adapter->toResponse($request, $result);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/x-synapse-toon', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('hello', $response->getContent());
    }

    public function test_graphql_adapter_detects_errors(): void
    {
        $adapter = $this->app->make(SynapseToonGraphQLAdapter::class);
        $request = Request::create('/graphql', 'POST');

        $result = new class
        {
            public array $data = ['foo' => 'bar'];
            public array $errors = [['message' => 'Boom']];
        };

        $response = $adapter->toResponse($request, $result);

        $this->assertSame(206, $response->getStatusCode());
        $this->assertStringContainsString('"errors"', $response->getContent());
    }

    public function test_graphql_adapter_handles_to_array_and_json_serializable(): void
    {
        $adapter = $this->app->make(SynapseToonGraphQLAdapter::class);
        $request = Request::create('/graphql', 'POST');

        $toArray = new class
        {
            public function toArray(): array
            {
                return ['data' => ['alpha' => 1]];
            }
        };

        $jsonSerializable = new class implements \JsonSerializable
        {
            public function jsonSerialize(): mixed
            {
                return ['data' => ['beta' => 2]];
            }
        };

        $arrayResponse = $adapter->toResponse($request, $toArray);
        $jsonResponse = $adapter->toResponse($request, $jsonSerializable);

        $this->assertStringContainsString('alpha', $arrayResponse->getContent());
        $this->assertStringContainsString('beta', $jsonResponse->getContent());
    }

    public function test_graphql_adapter_wraps_scalars_in_data_key(): void
    {
        $adapter = $this->app->make(SynapseToonGraphQLAdapter::class);
        $request = Request::create('/graphql', 'POST');

        $response = $adapter->toResponse($request, 'plain-value');

        $this->assertStringContainsString('plain-value', $response->getContent());
        $this->assertStringContainsString('"data"', $response->getContent());
    }
}
