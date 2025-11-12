# RAG Integration

Semantic search with vector databases.

## Vector Store Architecture

### Abstraction Layer

```php
namespace VinkiusLabs\SynapseToon\Contracts;

interface VectorStore
{
    public function search(string $query, int $limit = 3): array;
    public function store(string $id, array $vector, array $metadata): void;
    public function delete(string $id): void;
}
```

### Pinecone Implementation

```php
namespace VinkiusLabs\SynapseToon\Rag;

use Pinecone\Client as PineconeClient;

class PineconeVectorStore implements VectorStore
{
    private PineconeClient $client;
    
    public function __construct(string $apiKey, string $environment)
    {
        $this->client = new PineconeClient([
            'apiKey' => $apiKey,
            'environment' => $environment,
        ]);
    }
    
    public function search(string $query, int $limit = 3): array
    {
        // Embed query
        $embedding = $this->embedText($query);
        
        // Search Pinecone
        $results = $this->client->query(
            vector: $embedding,
            topK: $limit,
            includeMetadata: true,
        );
        
        return array_map(fn($match) => [
            'id' => $match['id'],
            'score' => $match['score'],
            'metadata' => $match['metadata'],
        ], $results['matches']);
    }
    
    public function store(string $id, array $vector, array $metadata): void
    {
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
class SupabaseVectorStore implements VectorStore
{
    private SupabaseClient $client;
    private string $table = 'documents';
    
    public function __construct(SupabaseClient $client)
    {
        $this->client = $client;
    }
    
    public function search(string $query, int $limit = 3): array
    {
        $embedding = $this->embedText($query);
        
        $results = $this->client->rpc('match_documents', [
            'query_embedding' => $embedding,
            'match_count' => $limit,
        ]);
        
        return $results;
    }
    
    public function store(string $id, array $vector, array $metadata): void
    {
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

## RAG Context Building

### Hybrid Search

```php
class RagService
{
    public function __construct(
        private readonly VectorStore $vectorStore,
        private readonly DatabaseRepository $db,
    ) {}
    
    public function buildContext(string $query, int $limit = 5): string
    {
        // Vector search for semantic similarity
        $vectorResults = $this->vectorStore->search($query, $limit);
        
        // Keyword search for exact matches
        $keywordResults = $this->db->search($query, $limit);
        
        // Merge and rank
        $combined = $this->mergeResults($vectorResults, $keywordResults);
        
        // Build context string
        return implode(PHP_EOL . PHP_EOL, array_map(
            fn($doc) => $doc['content'],
            array_slice($combined, 0, $limit)
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
- [Architecture](architecture.md) – System design
