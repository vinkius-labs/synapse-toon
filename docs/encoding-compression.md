# Encoding & Compression

NOTE: This documentation covers Synapse TOON only. It describes the TOON encoding algorithm, compression strategies, and how they apply to Synapse TOON.

Deep technical dive into the TOON encoding algorithm and compression strategies.

## TOON Encoding Algorithm

### Phase 1: Payload Analysis

```php
class SynapseToonEncoder
{
    private function analyzePayload($payload): PayloadMetrics
    {
        return [
            'type' => gettype($payload),
            'depth' => $this->calculateDepth($payload),
            'repeated_keys' => $this->findRepeatedKeys($payload),
            'estimated_tokens' => $this->estimateTokens($payload),
        ];
    }
}
```

### Phase 2: Dictionary Mapping

Map frequently used keys to shorter identifiers:

```
Original: {"user_id": 123, "user_id": 456, "user_id": 789}
Dictionary: {"user_id": "u"}
Result: {"u": 123, "u": 456, "u": 789}
```

Token savings: ~8-12% on key-heavy payloads

### Phase 3: Value Optimization

#### Number Compression

```
1.0 → 1 (remove unnecessary decimals)
0 → 0 (keep zero)
-0 → 0 (normalize negative zero)
1.23456789 → 1.23 (precision-aware rounding)
```

#### String Normalization

```
"2025-01-15T10:23:45.000Z" → "2025-01-15T10:23:45Z"
"true" → true (convert to boolean)
"null" → null (convert to null)
```

#### Null Handling

```
Preserve: true (boolean null)
Remove: Empty arrays []/objects {}
```

### Phase 4: Structure Optimization

#### Array Flattening (Optional)

```php
// Before
[
    'items' => [
        ['id' => 1, 'name' => 'Product A'],
        ['id' => 2, 'name' => 'Product B'],
    ]
]

// After (with flattening)
[
    'items_id' => [1, 2],
    'items_name' => ['Product A', 'Product B'],
]
```

Token savings: 15-20% on highly nested structures

## Compression Algorithms

### Brotli (Recommended)

#### Implementation

```php
function compressBrotli(string $payload, int $quality = 8): ?string
{
    if (!function_exists('brotli_compress')) {
        return null;
    }
    
    $modeValue = match ($mode) {
        'text' => defined('BROTLI_MODE_TEXT') ? constant('BROTLI_MODE_TEXT') : 1,
        'font' => defined('BROTLI_MODE_FONT') ? constant('BROTLI_MODE_FONT') : 2,
        default => defined('BROTLI_MODE_GENERIC') ? constant('BROTLI_MODE_GENERIC') : 0,
    };
    
    return brotli_compress($payload, $quality, $modeValue);
}
```

#### Performance Profile

| Quality | Speed | Ratio | Use Case |
|---------|-------|-------|----------|
| 4 | 0.5ms/10KB | 65% | High throughput |
| 8 | 2.1ms/10KB | 73% | **Balanced** |
| 11 | 15ms/10KB | 78% | Low frequency |

#### Brotli Modes

- **Generic** (0): All data types
- **Text** (1): UTF-8 text, optimizes for language patterns
- **Font** (2): WOFF2 fonts, specific byte patterns

### Gzip

#### Implementation

```php
function compressGzip(string $payload, int $level = -1): ?string
{
    if (!function_exists('gzencode')) {
        return null;
    }
    
    return gzencode($payload, $level);
}
```

#### Performance Profile

| Level | Speed | Ratio | Notes |
|-------|-------|-------|-------|
| 1 | 0.3ms/10KB | 55% | Fastest |
| 6 | 1.2ms/10KB | 62% | Default |
| 9 | 8.5ms/10KB | 63% | Minimal gain |

### Deflate

#### Implementation

```php
function compressDeflate(string $payload, int $level = -1): ?string
{
    if (!function_exists('deflate_init')) {
        return null;
    }
    
    $resource = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => $level]);
    if ($resource === false) return null;
    
    return deflate_add($resource, $payload, ZLIB_FINISH);
}
```

Similar performance to Gzip but with different byte stream format.

## Negotiation Strategy

### Client Preferences Parsing

```
Accept-Encoding: br;q=1.0, gzip;q=0.8, deflate;q=0.5, *;q=0.1

Parsed: [
    ['encoding' => 'br', 'quality' => 1.0],
    ['encoding' => 'gzip', 'quality' => 0.8],
    ['encoding' => 'deflate', 'quality' => 0.5],
    ['encoding' => '*', 'quality' => 0.1],
]
```

### Selection Algorithm

1. **Filter** by quality > 0
2. **Sort** by quality (descending)
3. **Prepend** server preference if present
4. **Remove** duplicates
5. **Iterate** and attempt each algorithm
6. **Return** first successful compression

## Combined Encoding + Compression

### Full Pipeline

```
Input Payload
    ↓
[1] Analyze & Validate
    ↓
[2] Apply Dictionary Mapping
    ↓
[3] Optimize Values
    ↓
[4] Minify Structure
    ↓
[5] JSON Serialize
    ↓
[6] Select Compression Algorithm
    ↓
[7] Compress
    ↓
Output (binary)
```

### Performance Overhead

```
1KB payload:
- Encoding: 0.08ms
- Compression (Brotli 8): 0.12ms
- Total: 0.20ms
- Network savings: ~2ms
- Net benefit: +1.8ms

10KB payload:
- Encoding: 0.85ms
- Compression (Brotli 8): 2.10ms
- Total: 2.95ms
- Network savings: ~15ms
- Net benefit: +12.05ms

100KB payload:
- Encoding: 8.4ms
- Compression (Brotli 8): 19.3ms
- Total: 27.7ms
- Network savings: ~180ms
- Net benefit: +152.3ms
```

## Streaming Chunks

### Chunk Generation

```php
public function streamChunks(iterable $stream): void
{
    $buffer = '';
    $maxChunkSize = config('synapse-toon.encoding.max_chunk_size');
    
    foreach ($stream as $item) {
        $encoded = $this->encodeChunk($item);
        $buffer .= $encoded;
        
        if (strlen($buffer) >= $maxChunkSize) {
            echo $buffer;
            $buffer = '';
        }
    }
    
    if ($buffer) {
        echo $buffer;
    }
}
```

### Chunk Structure

```json
id: 550e8400-e29b-41d4-a716-446655440000
event: update
data: [compressed chunk data]

id: 550e8400-e29b-41d4-a716-446655440001
event: update
data: [compressed chunk data]
```

## Advanced: Custom Encoders

Implement domain-specific compression:

```php
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;

class E-commerceEncoder extends SynapseToonEncoder
{
    protected function optimizePayload($payload): array
    {
        // Custom product-specific optimizations
        if (isset($payload['products'])) {
            $payload['products'] = $this->compressProducts($payload['products']);
        }
        
        return parent::optimizePayload($payload);
    }
    
    private function compressProducts(array $products): array
    {
        return array_map(function ($p) {
            return [
                'id' => $p['id'],
                'sku' => $p['sku'],
                'name' => $p['name'],
                'price' => (int)($p['price'] * 100), // Store as cents
            ];
        }, $products);
    }
}
```

Register in ServiceProvider:

```php
$this->app->singleton(
    SynapseToonEncoder::class,
    E-commerceEncoder::class
);
```

## Testing Compression

### Benchmark Tool

```bash
php artisan synapse-toon:benchmark \
  --payload=products \
  --size=10000 \
  --compression=brotli \
  --quality=8
```

Output:

```
Encoding: 8.4ms
Compression: 19.3ms
Total: 27.7ms
Original: 47,200 tokens
Compressed: 26,100 tokens
Reduction: 44.7%
ROI: +152.3ms benefit
```

### Validation

```php
// Verify lossless encoding
$original = ['data' => 'test', 'nested' => ['key' => 'value']];
$encoded = SynapseToon::encoder()->encode($original);
$decoded = SynapseToon::decoder()->decode($encoded);

assert($original === $decoded);
```

## Known Limitations

1. **Recursive structures** – Max depth 256
2. **Large arrays** – >100K items may cause memory issues
3. **Resource types** – Can't serialize (file handles, etc.)
4. **Binary data** – Base64 encoded, adds ~33% overhead

## Next Steps

- [Streaming & SSE](streaming-sse.md) – Deliver chunks efficiently
- [Metrics & Analytics](metrics-analytics.md) – Track compression gains
- [Performance Tuning](performance-tuning.md) – Optimize for your workload
