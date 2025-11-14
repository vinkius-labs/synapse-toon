# GraphQL Adapter

Transform GraphQL execution results into TOON-optimized format.

## GraphQL Result Normalization

### Standard Flow

```php
namespace App\Synapse\GraphQL;

use VinkiusLabs\SynapseToon\Facades\SynapseToon;

class SynapseToonGraphQLAdapter
{
    public function adapt(array $executionResult): array
    {
        // Normalize result structure
        $normalized = $this->normalizeResult($executionResult);
        
        // Encode to TOON format
        $encoded = SynapseToon::encoder()->encode($normalized);
        
        // Compress payload
        $compressed = SynapseToon::compressor()->compress($encoded);
        
        return [
            'data' => $normalized['data'],
            'toon_encoded' => $encoded,
            'toon_size' => strlen($compressed),
            'original_size' => strlen(json_encode($normalized)),
            'compression_ratio' => strlen($compressed) / strlen(json_encode($normalized)),
        ];
    }
    
    private function normalizeResult(array $result): array
    {
        return match(true) {
            isset($result['errors']) && !empty($result['errors']) => $this->normalizeErrors($result),
            isset($result['data']) => $this->normalizeObject($result['data']),
            default => ['data' => null, 'status' => 204],
        };
    }
    
    private function normalizeObject(mixed $obj): array
    {
        if (is_null($obj)) {
            return ['data' => null];
        }
        
        return match(true) {
            is_array($obj) => ['data' => $this->normalizeArray($obj)],
            is_scalar($obj) => ['data' => $obj],
            default => ['data' => (string) $obj],
        };
    }
    
    private function normalizeArray(array $arr): array
    {
        // Recursively normalize nested structures
        return array_map(fn($item) => 
            is_array($item) ? $this->normalizeArray($item) : $item,
            $arr
        );
    }
    
    private function normalizeErrors(array $result): array
    {
        $status = $result['errors'][0]['extensions']['code'] === 'UNAUTHENTICATED' ? 401 : 400;
        
        return [
            'errors' => array_map(fn($err) => [
                'message' => $err['message'],
                'path' => $err['path'] ?? [],
            ], $result['errors']),
            'status' => $status,
        ];
    }
}
```

## Schema Integration

### GraphQL Type Definition

```graphql
type SynapseToonMetrics {
  originalSize: Int!
  compressedSize: Int!
  compressionRatio: Float!
  tokensOriginal: Int!
  tokensCompressed: Int!
  tokensSaved: Int!
}

extend type Query {
  user(id: ID!): User
}

extend type User {
  id: ID!
  name: String!
  email: String!
  synapseToonMetrics: SynapseToonMetrics!
}
```

### Field Resolver with Metrics

```php
class UserResolver
{
    public function resolve($obj, array $args, $context, $info): array
    {
        $startTime = microtime(true);
        
        $user = User::find($args['id'])?->toArray();
        
        if (!$user) {
            throw new \Exception('User not found', 404);
        }
        
        // Add TOON metrics
        $original = json_encode($user);
        $encoded = SynapseToon::encoder()->encode($user);
        
        $user['synapseToonMetrics'] = [
            'originalSize' => strlen($original),
            'compressedSize' => strlen($encoded),
            'compressionRatio' => strlen($encoded) / strlen($original),
            'tokensOriginal' => intval(strlen($original) / 4),
            'tokensCompressed' => intval(strlen($encoded) / 4),
            'tokensSaved' => intval((strlen($original) - strlen($encoded)) / 4),
        ];
        
        $latency = (microtime(true) - $startTime) * 1000;
        
        SynapseToon::metrics()->record([
            'resolver' => 'UserResolver',
            'field' => $info->fieldName,
            'latency_ms' => $latency,
            'tokens_saved' => $user['synapseToonMetrics']['tokensSaved'],
        ]);
        
        return $user;
    }
}
```

## Connection-based Pagination

```graphql
type UserConnection {
  edges: [UserEdge!]!
  pageInfo: PageInfo!
  synapseToonMetrics: SynapseToonMetrics!
}

type UserEdge {
  node: User!
  cursor: String!
}

type PageInfo {
  hasNextPage: Boolean!
  hasPreviousPage: Boolean!
  startCursor: String
  endCursor: String
}

type Query {
  users(first: Int, after: String): UserConnection!
}
```

### Connection Resolver

```php
class UserConnectionResolver
{
    public function resolve($obj, array $args, $context, $info): array
    {
        $first = $args['first'] ?? 10;
        $after = $args['after'] ? base64_decode($args['after']) : 0;
        
        $query = User::query()
            ->skip($after)
            ->take($first + 1);
        
        $users = $query->get()->toArray();
        $hasNextPage = count($users) > $first;
        
        if ($hasNextPage) {
            array_pop($users);
        }
        
        $edges = array_map(function($user, $index) use ($after) {
            return [
                'node' => $user,
                'cursor' => base64_encode((string)($after + $index)),
            ];
        }, $users, array_keys($users));
        
        $encoded = SynapseToon::encoder()->encode([
            'edges' => $edges,
            'pageInfo' => [
                'hasNextPage' => $hasNextPage,
                'endCursor' => end($edges)['cursor'] ?? null,
            ],
        ]);
        
        return [
            'edges' => $edges,
            'pageInfo' => [
                'hasNextPage' => $hasNextPage,
                'hasPreviousPage' => $after > 0,
                'endCursor' => end($edges)['cursor'] ?? null,
            ],
            'synapseToonMetrics' => [
                'originalSize' => strlen(json_encode($edges)),
                'compressedSize' => strlen($encoded),
                'compressionRatio' => strlen($encoded) / strlen(json_encode($edges)),
            ],
        ];
    }
}
```

## Error Handling

### GraphQL Errors

```php
class ErrorHandler
{
    public function formatError(\GraphQL\Error\Error $error): array
    {
        $previous = $error->getPrevious();
        
        return match(true) {
            $previous instanceof ValidationException => [
                'message' => 'Validation failed',
                'extensions' => [
                    'code' => 'VALIDATION_ERROR',
                    'errors' => $previous->errors(),
                ],
            ],
            $previous instanceof AuthorizationException => [
                'message' => 'Unauthorized',
                'extensions' => ['code' => 'UNAUTHORIZED'],
            ],
            default => [
                'message' => $error->getMessage(),
                'extensions' => ['code' => 'INTERNAL_ERROR'],
            ],
        };
    }
}
```

## Subscription Support

```php
class UserSubscriptionResolver
{
    public function subscribe($obj, array $args, $context): iterable
    {
        $userId = $args['userId'];
        
        // Subscribe to Redis channel
        return $this->redis->subscribe("user:{$userId}", function ($message) {
            $user = json_decode($message, true);
            
            // Encode update to TOON format
            $encoded = SynapseToon::encoder()->encode($user);
            
            yield [
                'user' => $user,
                'compressed_size' => strlen($encoded),
                'saved_bytes' => strlen(json_encode($user)) - strlen($encoded),
            ];
        });
    }
}
```

### Subscription Schema

```graphql
type Subscription {
  userUpdated(userId: ID!): UserUpdate!
}

type UserUpdate {
  user: User!
  compressedSize: Int!
  savedBytes: Int!
}
```

## Performance Optimization

### Query Depth Limiting

```php
class DepthAnalyzer
{
    public function validate(DocumentNode $document): void
    {
        $maxDepth = 5; // Prevent deep nesting attacks
        $depth = $this->calculateDepth($document);
        
        if ($depth > $maxDepth) {
            throw new \Exception("Query depth exceeds limit of {$maxDepth}");
        }
    }
    
    private function calculateDepth(Node $node, int $depth = 0): int
    {
        if ($node instanceof SelectionSetNode) {
            $max = $depth;
            foreach ($node->selections as $selection) {
                $max = max($max, $this->calculateDepth($selection, $depth + 1));
            }
            return $max;
        }
        
        return $depth;
    }
}
```

### Query Complexity Analysis

```php
class ComplexityAnalyzer
{
    private array $complexities = [
        'user' => 1,
        'users' => 5,  // Multiple users
        'posts' => 10, // Heavy query
    ];
    
    public function analyze(SelectionSetNode $selectionSet): int
    {
        $complexity = 0;
        
        foreach ($selectionSet->selections as $selection) {
            if ($selection instanceof FieldNode) {
                $fieldComplexity = $this->complexities[$selection->name->value] ?? 1;
                $complexity += $fieldComplexity;
                
                // Recursive sub-fields
                if ($selection->selectionSet) {
                    $complexity += $this->analyze($selection->selectionSet);
                }
            }
        }
        
        return $complexity;
    }
}
```

## Dataloader Pattern

```php
class UserDataLoader
{
    public function load(array $userIds): array
    {
        // Batch load to prevent N+1 queries
        $users = User::whereIn('id', $userIds)
            ->get()
            ->keyBy('id')
            ->toArray();
        
        // Encode batch result
        $encoded = SynapseToon::encoder()->encode($users);
        
        return [
            'users' => $users,
            'tokens_saved' => strlen(json_encode($users)) - strlen($encoded),
        ];
    }
}
```

## Real-World Example

```graphql
query GetUserWithPosts {
  user(id: 123) {
    id
    name
    email
    posts(first: 10) {
      edges {
        node {
          id
          title
          content
        }
      }
      synapseToonMetrics {
        originalSize
        compressedSize
        compressionRatio
      }
    }
  }
}
```

```json
Response:
{
  "data": {
    "user": {
      "id": "123",
      "name": "John Doe",
    "email": "john@synapse-toon.local",
      "posts": {
        "edges": [{"node": {...}}],
        "synapseToonMetrics": {
          "originalSize": 4820,
          "compressedSize": 2410,
          "compressionRatio": 0.50
        }
      }
    }
  }
}
```

## Testing

```php
class GraphQLAdapterTest extends TestCase
{
    public function testNormalizeComplexResult(): void
    {
        $result = [
            'data' => [
                'user' => ['id' => '1', 'name' => 'John'],
                'posts' => [['id' => '1', 'title' => 'Post 1']],
            ],
        ];
        
        $normalized = $this->adapter->normalizeResult($result['data']);
        
        $this->assertIsArray($normalized['data']);
        $this->assertNotEmpty($normalized['data']);
    }
}
```

## Next Steps

- [Edge Cache](edge-cache.md) – Cache GraphQL responses
- [Performance Tuning](performance-tuning.md) – Optimize GraphQL latency
- [Technical Reference](technical-reference.md) – Understand system design
