# Cost Optimization Guide

NOTE: This guide only covers cost optimization strategies that directly apply to Synapse TOON.

This guide focuses on maximizing token savings and reducing LLM API costs using Synapse TOON's advanced features.

## Understanding Token Economics

### Cost Breakdown (GPT-4o Example)

```
Input: $5.00 per 1M tokens
Output: $15.00 per 1M tokens

Baseline API call (no optimization):
- Request: 1,500 tokens × $5.00 = $0.0075
- Response: 800 tokens × $15.00 = $0.0120
- Total: $0.0195 per call

With Synapse TOON (40% reduction):
- Request: 900 tokens × $5.00 = $0.0045
- Response: 480 tokens × $15.00 = $0.0072
- Total: $0.0117 per call

Savings: $0.0078 per call (40%)
At 100K calls/month: $780 saved monthly
```

## Optimization Strategies

### 1. Dictionary Compression

Pre-define common terms to reduce payload size:

```php
// config/synapse-toon.php
'encoding' => [
    'dictionary' => [
        'user_id' => 'u',
        'product_id' => 'p',
        'created_at' => 'c',
        'updated_at' => 'u',
        'description' => 'd',
        'metadata' => 'm',
    ],
],
```

**Impact**: Additional 5-12% token reduction on payloads with repetitive keys.

### 2. RAG Context Optimization

Trim vector search results before sending to LLM:

```php
// Baseline: ~850 tokens
$context = $vectorStore->search($query, limit: 5);

// Optimized: ~485 tokens (43% reduction)
$context = SynapseToon::rag()->buildContext($query, [
    'limit' => 3,
    'max_snippet_length' => 150,
]);

OpenAI::chat()->create([
    'messages' => [
        ['role' => 'system', 'content' => $context],
        ['role' => 'user', 'content' => $query],
    ],
]);
```

**ROI**: On 10K RAG queries/month, saves ~$45-65 at GPT-4o pricing.

### 3. Batch Processing

Group multiple requests into single API calls:

```php
// Inefficient: 100 individual API calls
foreach ($prompts as $prompt) {
    OpenAI::chat()->create(['messages' => [['role' => 'user', 'content' => $prompt]]]);
}
// Cost: 100 × $0.015 = $1.50

// Optimized: 2 batch jobs (50 prompts each)
SynapseToonProcessLLMBatchJob::dispatch($prompts, [
    'batch_size' => 50,
    'queue' => 'llm-batch',
]);
// Cost: 2 × $0.008 = $0.016 (89% reduction)
```

**Why it works**:
- Single request/response overhead
- Shared context reduces redundant tokens
- TOON encoding compresses batch delimiter

**ROI**: On 10K prompts/month, saves ~$145-175.

### 4. Smart Model Routing

Route simple queries to cheaper models:

```php
// config/synapse-toon.php
'router' => [
    'strategies' => [
        [
            'name' => 'ultra-light',
            'max_complexity' => 0.3,
            'max_tokens' => 512,
            'target' => 'gpt-4o-mini', // $0.15/1M vs $5/1M
        ],
        [
            'name' => 'balanced',
            'max_complexity' => 0.7,
            'max_tokens' => 2048,
            'target' => 'gpt-4o',
        ],
    ],
    'default_target' => 'o1-preview',
],
```

**Impact**:
- 60% of queries routed to gpt-4o-mini
- Average cost per call drops from $0.015 to $0.006
- 60% cost reduction on routed queries

**ROI**: On 100K queries/month, saves ~$540-720.

### 5. Edge Caching

Cache pre-encoded responses:

```php
// Without caching: Encode on every request
Route::get('/products', function () {
    return response()->synapseToon(
        Product::with('category')->get()
    );
});
// Cost: 10K requests × 500 tokens = 5M tokens = $25/month

// With edge caching: Encode once, serve 10K times
Route::get('/products', function () {
    return SynapseToon::edgeCache()->remember(
        'products.toon',
        fn () => Product::with('category')->get(),
        ttl: 3600
    );
});
// Cost: 1 encode × 500 tokens = 500 tokens = $0.0025/month
```

**ROI**: On frequently accessed endpoints, 99.9% cost reduction.

### 6. Compression Strategy

Tune Brotli quality for optimal CPU vs token tradeoff:

```php
// High quality (production APIs with low traffic)
'compression' => [
    'brotli' => [
        'quality' => 11, // Best compression, slowest
    ],
],
// Token reduction: 42-48%
// Encoding time: +15-25ms

// Balanced (recommended for most cases)
'compression' => [
    'brotli' => [
        'quality' => 8, // Good compression, fast
    ],
],
// Token reduction: 38-43%
// Encoding time: +2-5ms

// Speed optimized (high traffic, latency sensitive)
'compression' => [
    'brotli' => [
        'quality' => 4, // Moderate compression, very fast
    ],
],
// Token reduction: 28-35%
// Encoding time: +0.5-1.5ms
```

### 7. Streaming Optimization

Use streaming for long-running LLM responses:

```php
// Buffered: Wait for full response, then compress
return response()->synapseToon($llmResponse);
// Time to first byte: 8-12s
// Total tokens: 2,400

// Streaming: Compress and send as chunks arrive
return response()->synapseToonStream($llmStream, function ($chunk) {
    return ['delta' => $chunk['choices'][0]['delta']['content']];
});
// Time to first byte: 80-150ms
// Total tokens: 2,400 (but perceived latency 95% lower)
```

**Impact**: Better UX, no token savings, but enables request cancellation (saves on partial responses).

## Real-World Case Studies

### Case Study 1: E-commerce Product API

**Setup**:
- 50K products with categories, reviews, inventory
- 500K API calls/month
- Average response: 1,200 tokens

**Optimizations Applied**:
1. Dictionary compression for common keys
2. Edge caching (TTL: 15 minutes)
3. Brotli quality 8

**Results**:
- Token reduction: 44%
- Cache hit rate: 87%
- Effective token savings: 91% (44% encoding + 87% caching)
- Cost: $300/month → $27/month
- **Savings: $273/month**

### Case Study 2: RAG-Powered Chatbot

**Setup**:
- 10K queries/day
- Average context: 850 tokens
- GPT-4o ($5/1M input tokens)

**Optimizations Applied**:
1. RAG context optimization (limit: 3, snippet: 150)
2. Smart routing (70% to gpt-4o-mini)
3. Batch processing for training data

**Results**:
- Context tokens: 850 → 485 (43% reduction)
- Average cost per query: $0.004 → $0.0011
- Monthly cost: $1,200 → $330
- **Savings: $870/month**

### Case Study 3: API Aggregator

**Setup**:
- Aggregates data from 12 microservices
- 1M API calls/month
- Average payload: 3.2KB

**Optimizations Applied**:
1. HTTP/3 detection + Brotli
2. GraphQL adapter for nested queries
3. Metrics threshold (only log >10% savings)

**Results**:
- Payload size: 3.2KB → 1.1KB (66% reduction)
- Bandwidth savings: 2.1GB/month
- CDN cost reduction: $840/month
- LLM token reduction: 38%
- **Total savings: $1,120/month**

## Monitoring ROI

Track savings in real-time:

```php
// Prometheus metrics
synapse_toon_tokens_saved_total{endpoint="/api/products"} 1,240,567
synapse_toon_cost_saved_dollars_total{model="gpt-4o"} 62.03

// Datadog dashboard
sum:synapse_toon.savings_percent{env:production}
avg:synapse_toon.compression_ratio{service:api}
```

## Cost Calculator

Use this formula to estimate your savings:

```
Monthly API Calls (C)
Average Tokens per Call (T)
Token Reduction % (R)
Token Cost per 1M (P)

Monthly Savings = C × T × R × P / 1,000,000

Example:
100,000 calls × 1,500 tokens × 0.40 × $5 / 1M
= $300 saved/month
```

## Best Practices

1. **Start with metrics** – Enable logging to establish baseline
2. **Optimize hot paths** – Focus on high-volume endpoints first
3. **Test incrementally** – Apply one optimization at a time
4. **Monitor quality** – Ensure compression doesn't impact UX
5. **Set thresholds** – Only log significant savings (>10%)
6. **Review monthly** – Adjust strategies based on usage patterns

## Advanced: Custom Compression

For domain-specific payloads:

```php
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;

class CustomProductEncoder extends SynapseToonEncoder
{
    protected function compressDictionary(array $payload): array
    {
        // Custom logic for e-commerce products
        return parent::compressDictionary($payload);
    }
}

// Register in SynapseToonServiceProvider
$this->app->singleton(SynapseToonEncoder::class, CustomProductEncoder::class);
```

## Next Steps

- [Metrics & Analytics](metrics-analytics.md) – Set up dashboards
- [Performance Tuning](performance-tuning.md) – Optimize for latency
- [Technical Reference](technical-reference.md) – Deep dive into algorithms

**Remember**: Every 1% token reduction scales linearly with API volume. At high scale, even small optimizations yield significant savings.
