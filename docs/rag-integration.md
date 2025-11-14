# RAG Integration

Semantic search with vector databases.

## Vector Store Architecture

### Abstraction Layer

The package exposes a lightweight `SynapseToonVectorStore` contract that focuses on read (search) operations. For drivers that support index management, an optional `SynapseToonVectorStoreManager` contract is available with `store` and `delete` operations.

```php
namespace VinkiusLabs\SynapseToon\Contracts;
use Illuminate\Support\Collection;

interface SynapseToonVectorStore
{
    /**
     * Retrieve the most relevant documents for a query.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 3): Collection;
}

interface SynapseToonVectorStoreManager
{
    public function store(string $id, string $content, array $metadata = []): void;
    public function delete(string $id): void;
}
```

### Pinecone Implementation

```php
namespace VinkiusLabs\SynapseToon\Rag;

use Pinecone\Client as PineconeClient;
use Illuminate\Support\Collection;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonVectorStore;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonVectorStoreManager;

class PineconeVectorStore implements SynapseToonVectorStore, SynapseToonVectorStoreManager
{
    private PineconeClient $client;
    
    public function __construct(string $apiKey, string $environment)
    {
        $this->client = new PineconeClient([
            'apiKey' => $apiKey,
            'environment' => $environment,
        ]);
    }
    
    public function search(string $query, int $limit = 3): Collection
    {
        // Embed query
        $embedding = $this->embedText($query);
        
        // Search Pinecone
        $results = $this->client->query(
            vector: $embedding,
            topK: $limit,
            includeMetadata: true,
        );
        
        return Collection::make(array_map(fn($match) => [
            'id' => $match['id'],
            'score' => $match['score'],
            'metadata' => $match['metadata'],
        ], $results['matches']));
    }
    
    public function store(string $id, string $content, array $metadata = []): void
    {
        // Embed content before upsert
        $vector = $this->embedText($content);
        $this->client->upsert([
            [
                'id' => $id,
                'values' => $vector,
                'metadata' => $metadata,
            ],
        ]);
    }
    
    public function delete(string $id): void
    {
        $this->client->delete(ids: [$id]);
    }
}
```

### Supabase Vector Implementation

```php
use Illuminate\Support\Collection;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonVectorStore;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonVectorStoreManager;

class SupabaseVectorStore implements SynapseToonVectorStore, SynapseToonVectorStoreManager
{
    private SupabaseClient $client;
    private string $table = 'documents';
    
    public function __construct(SupabaseClient $client)
    {
        $this->client = $client;
    }
    
    public function search(string $query, int $limit = 3): Collection
    {
        $embedding = $this->embedText($query);
        
        $results = $this->client->rpc('match_documents', [
            'query_embedding' => $embedding,
            'match_count' => $limit,
        ]);
        
        return Collection::make($results);
    }
    
    public function store(string $id, string $content, array $metadata = []): void
    {
        $vector = $this->embedText($content);
        $this->client->table($this->table)->insert([
            'id' => $id,
            'embedding' => $vector,
            'metadata' => json_encode($metadata),
        ]);
    }
    
    public function delete(string $id): void
    {
        $this->client->table($this->table)->delete()
            ->eq('id', $id)
            ->execute();
    }
}
```

### In-Memory Vector Store (Dev & Tests)

```php
namespace VinkiusLabs\SynapseToon\Rag\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use VinkiusLabs\SynapseToon\Contracts\SynapseToonVectorStore;

class InMemoryVectorStore implements SynapseToonVectorStore
{
    protected array $index = [];

    public function __construct(array $seed = [])
    {
        foreach ($seed as $item) {
            $id = $item['id'] ?? (string) Str::uuid();
            $this->index[$id] = [
                'id' => $id,
                'content' => $item['content'] ?? '',
                'metadata' => $item['metadata'] ?? [],
                'score' => $item['score'] ?? 1.0,
            ];
        }
    }

    public function search(string $query, int $limit = 3): Collection
    {
        $query = trim($query);

        $items = Collection::make($this->index)
            ->map(function ($item) use ($query) {
                $score = $item['score'] ?? 0.0;
                if ($query !== '' && str_contains(strtolower($item['content']), strtolower($query))) {
                    $score += 0.5;
                }

                return [
                    'id' => $item['id'],
                    'content' => $item['content'],
                    'metadata' => $item['metadata'] ?? [],
                    'score' => $score,
                ];
            })
            ->sortByDesc('score')
            ->values();

        return $items->take($limit);
    }
}
```

## RAG Context Building

### Hybrid Search

```php
use VinkiusLabs\SynapseToon\Contracts\SynapseToonVectorStore;

class RagService
{
    public function __construct(
        private readonly SynapseToonVectorStore $vectorStore,
        private readonly DatabaseRepository $db,
    ) {}
    
    public function buildContext(string $query, array $metadata = []): string
    {
        $searchLimit = (int) (config('synapse-toon.rag.context.search_limit', 10));
        $finalLimit = (int) (config('synapse-toon.rag.context.limit', 3));
        // Vector search for semantic similarity (pre-select across 'search_limit' then score+token-budget selection)
        $vectorResults = $this->vectorStore->search($query, $searchLimit)->all();
        
        // Keyword search for exact matches
        $keywordResults = $this->db->search($query, $searchLimit);
        
        // Merge and rank
        $combined = $this->mergeResults($vectorResults, $keywordResults);
        
        // Build context string
        // Build a compact context suitable for LLM injection (the actual service chooses
        // document chunks by score and token budget before returning the final encoded context)
        return implode(PHP_EOL . PHP_EOL, array_map(
            fn($doc) => $doc['content'],
            array_slice($combined, 0, $finalLimit)
        ));
    }
    
    private function mergeResults(array $vector, array $keyword): array
    {
        $merged = [];
        
        foreach (array_merge($vector, $keyword) as $result) {
            $key = $result['id'];
            if (!isset($merged[$key])) {
                $merged[$key] = $result;
                $merged[$key]['score'] = 0;
            }
            $merged[$key]['score'] += $result['score'] ?? 0.5;
        }
        
        usort($merged, fn($a, $b) => $b['score'] <=> $a['score']);
        return $merged;
    }
}
```

## Configuration

Add the following to your `synapse-toon.php` configuration under the `rag.context` key to fine-tune budget and selection:

```php
'context' => [
    'limit' => 3,                    // how many documents to include in final context
    'search_limit' => 10,            // how many results to prefetch from the vector store
    'max_tokens' => 512,             // total token budget (query + documents)
    'min_score' => 0.0,              // minimum relevance score to include a doc
    'max_snippet_length' => 200,     // fallback snippet length in chars
    'cache_ttl' => 0,                // seconds: 0 disables cache
    'summarize' => false,            // whether to use a summarizer service when docs are too large
    'summarizer_service' => null,    // container binding for summarizer service (callable or object with summarize())
    'metadata_filters' => [],        // optional metadata filters applied to the search results
],
```

## Summarizer Example

If you'd like to use an on-demand summarizer to compact large documents into the token budget, bind a summarizer service into the container:

```php
$this->app->bind('synapse-toon.summarizer', function () {
    return function (string $content, int $targetTokens = 0): string {
        // This naive summarizer attempts to shrink content by characters
        $maxChars = max(0, $targetTokens * 4);
        return \Illuminate\Support\Str::substr($content, 0, $maxChars);
    };
});

$this->app['config']->set('synapse-toon.rag.context.summarize', true);
$this->app['config']->set('synapse-toon.rag.context.summarizer_service', 'synapse-toon.summarizer');
```

The summarizer should aim to respect the `targetTokens` hint and return a compacted, semantically-representative string that fits into the token budget.

## Embedding Models

### OpenAI Embeddings

```php
class EmbeddingService
{
    public function __construct(
        private readonly OpenAI $client,
    ) {}
    
    public function embed(string $text, string $model = 'text-embedding-3-small'): array
    {
        $response = $this->client->embeddings()->create([
            'model' => $model,
            'input' => $text,
        ]);
        
        return $response->data[0]->embedding;
    }
    
    public function batchEmbed(array $texts, string $model = 'text-embedding-3-small'): array
    {
        $response = $this->client->embeddings()->create([
            'model' => $model,
            'input' => $texts,
        ]);
        
        return array_map(fn($item) => $item->embedding, $response->data);
    }
}
```

## Document Chunking

### Semantic Chunking

```php
class DocumentChunker
{
    private int $chunkSize = 512;
    private int $overlap = 50;
    
    public function chunk(string $document): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $document);
        $chunks = [];
        $current = '';
        
        foreach ($sentences as $sentence) {
            if (strlen($current) + strlen($sentence) > $this->chunkSize) {
                if (!empty($current)) {
                    $chunks[] = trim($current);
                }
                $current = $sentence;
            } else {
                $current .= ' ' . $sentence;
            }
        }
        
        if (!empty($current)) {
            $chunks[] = trim($current);
        }
        
        return $chunks;
    }
}
```

## LLM Context Injection

### Prompt Building

```php
class RagPromptBuilder
{
    public function build(string $query, string $context): string
    {
        $prompt = "Based on the following context, answer the question.";
        $prompt .= PHP_EOL . PHP_EOL . "Context:";
        $prompt .= PHP_EOL . $context;
        $prompt .= PHP_EOL . PHP_EOL . "Question: " . $query;
        
        return $prompt;
    }
}
```

### Response Generation

```php
class RagLLMService
{
    public function __construct(
        private readonly RagService $rag,
        private readonly OpenAI $openai,
        private readonly RagPromptBuilder $promptBuilder,
    ) {}
    
    public function answer(string $query): string
    {
        $context = $this->rag->buildContext($query);
        $prompt = $this->promptBuilder->build($query, $context);
        
        $response = $this->openai->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);
        
        return $response->choices[0]->message->content;
    }
}
```

## Caching Strategy

### Vector Cache

```php
class VectorCache
{
    private int $ttl = 86400;
    
    public function get(string $query): ?array
    {
        $key = 'rag_vector:' . md5($query);
        return Cache::get($key);
    }
    
    public function set(string $query, array $results): void
    {
        $key = 'rag_vector:' . md5($query);
        Cache::put($key, $results, $this->ttl);
    }
}
```

## Real-World Example

### Synapse TOON Use Case — Newsletter Generation

```php
class NewsletterRagGenerator
{
    public function __construct(
        private readonly RagLLMService $ragService,
        private readonly EmbeddingService $embeddings,
    ) {}
    
    public function generateSection(string $topic): string
    {
        $query = 'latest news about ' . $topic;
        return $this->ragService->answer($query);
    }
}

// Usage
$generator = app(NewsletterRagGenerator::class);
$section = $generator->generateSection('AI');
```

## Monitoring

### RAG Metrics

```php
class RagMetrics
{
    public function recordSearch(
        string $query,
        int $resultCount,
        float $latencyMs,
    ): void {
        SynapseToon::metrics()->record([
            'type' => 'rag_search',
            'query_length' => strlen($query),
            'result_count' => $resultCount,
            'latency_ms' => $latencyMs,
        ]);
    }
}
```

## Testing

```php
class RagServiceTest extends TestCase
{
    public function testBuildContext(): void
    {
        $context = $this->ragService->buildContext('test query');
        
        $this->assertIsString($context);
        $this->assertNotEmpty($context);
    }
    
    public function testHybridSearch(): void
    {
        $results = $this->ragService->buildContext('AI news', 5);
        
        $this->assertStringContainsString('AI', $results);
    }
}
```

## Next Steps

- [Batch Processing](batch-processing.md) – Queue RAG jobs
- [Performance Tuning](performance-tuning.md) – Optimize search
- [Technical Reference](technical-reference.md) – System design
