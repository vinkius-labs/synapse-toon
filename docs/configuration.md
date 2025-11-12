# Configuration Guide

NOTE: This documentation covers Synapse TOON only and contains configuration options specific to this package.

Complete reference for all Synapse TOON configuration options in `config/synapse-toon.php`.

## Defaults Section

```php
'defaults' => [
    'enabled' => env('SYNAPSE_TOON_ENABLED', true),
    'content_type' => 'application/x-synapse-toon',
    'quality' => 80,
],
```

### Properties

- **enabled** (bool) – Global feature toggle
- **content_type** (string) – MIME type for TOON responses
- **quality** (int) – Default quality for lossy operations (0-100)

## Encoding Section

```php
'encoding' => [
    'minify' => true,
    'preserve_zero_fraction' => false,
    'dictionary' => [],
    'chunk_delimiter' => PHP_EOL,
    'max_chunk_size' => 4096,
],
```

### Properties

- **minify** (bool) – Remove whitespace and optimize structure
- **preserve_zero_fraction** (bool) – Keep `.0` on floats like `1.0`
- **dictionary** (array) – Custom key mappings: `['user_id' => 'u']`
- **chunk_delimiter** (string) – Separator for streaming chunks
- **max_chunk_size** (int) – Maximum bytes per chunk before splitting

### Dictionary Example

```php
'dictionary' => [
    'user_id' => 'u',
    'product_id' => 'p',
    'created_at' => 'ca',
    'updated_at' => 'ua',
    'description' => 'd',
    'metadata' => 'm',
    'email' => 'e',
    'password' => 'pwd',
],
```

Reduces keys like `user_id` to `u`, saving ~5-8% on token count for key-heavy payloads.

## Compression Section

```php
'compression' => [
    'enabled' => true,
    'prefer' => 'brotli',
    'brotli' => [
        'quality' => 8,
        'mode' => 'generic',
    ],
    'gzip' => [
        'level' => -1,
    ],
    'deflate' => [
        'level' => -1,
    ],
    'header' => 'X-Synapse-TOON-Compressed',
],
```

### Compression Algorithms

#### Brotli (Recommended)

- **quality** (1-11) – Compression level
  - `4`: Fast encoding (~0.5ms/10KB), 28-35% reduction
  - `8`: Balanced (~2.1ms/10KB), 38-43% reduction **(default)**
  - `11`: Maximum (~15ms/10KB), 42-48% reduction
- **mode** – One of `generic`, `text`, `font`

#### Gzip

- **level** (-1 to 9) – Compression level
  - `-1`: Default algorithm choice
  - `6`: Balanced (default for zlib)
  - `9`: Maximum compression

#### Deflate

- **level** (-1 to 9) – Same as Gzip

### Negotiation Flow

1. Client sends `Accept-Encoding: br, gzip`
2. Server parses and extracts quality values
3. Prefers configured `prefer` algorithm if supported
4. Falls back to next supported algorithm
5. Uses uncompressed if nothing matches

## HTTP/3 Section

```php
'http3' => [
    'enabled' => true,
    'optimize_headers' => true,
    'prefer_compression' => 'brotli',
    'alt_svc_header' => 'h3=":443"',
],
```

### Properties

- **enabled** (bool) – HTTP/3 detection and optimization
- **optimize_headers** (bool) – Set Alt-Svc header
- **prefer_compression** (string) – Algorithm preference for HTTP/3 clients
- **alt_svc_header** (string) – Alt-Service header value

### Detection Logic

Middleware checks:
1. `SERVER_PROTOCOL` contains `HTTP/3`
2. `HTTP_ALT_SVC` header contains `h3`
3. `Sec-CH-UA` header contains `h3`

## Metrics Section

```php
'metrics' => [
    'enabled' => true,
    'driver' => env('SYNAPSE_TOON_METRICS_DRIVER', 'log'),
    'drivers' => [
        'log' => [
            'channel' => env('SYNAPSE_TOON_LOG_CHANNEL'),
        ],
        'null' => [],
        'prometheus' => [
            'push_gateway' => env('SYNAPSE_TOON_PROMETHEUS_PUSH'),
            'job' => env('SYNAPSE_TOON_PROMETHEUS_JOB', 'synapse-toon'),
        ],
        'datadog' => [
            'api_key' => env('SYNAPSE_TOON_DATADOG_API_KEY'),
            'endpoint' => env('SYNAPSE_TOON_DATADOG_ENDPOINT', 'https://api.datadoghq.com/api/v1/series'),
        ],
    ],
    'thresholds' => [
        'minimum_savings_percent' => 8,
    ],
],
```

### Drivers

#### Log Driver

Writes to Laravel logs:

```json
{"endpoint":"/api/products","json_tokens":1247,"toon_tokens":683,"savings_percent":45.2}
```

#### Prometheus Driver

Pushes to Prometheus PushGateway:

```
synapse_toon_tokens_saved_total{job="synapse-toon",endpoint="/api/products"} 564
synapse_toon_compression_ratio{job="synapse-toon",algorithm="brotli"} 0.548
```

#### Datadog Driver

Sends to Datadog API:

```bash
curl -X POST "https://api.datadoghq.com/api/v1/series" \
  -H "DD-API-KEY: $DATADOG_API_KEY" \
  -d '{"series": [{"metric": "synapse_toon.tokens_saved", ...}]}'
```

#### Null Driver

Discards all metrics (for development).

### Thresholds

- **minimum_savings_percent** (int) – Only record if savings > threshold

## RAG Section

```php
'rag' => [
    'enabled' => true,
    'driver' => env('SYNAPSE_TOON_RAG_DRIVER', 'null'),
    'drivers' => [
        'null' => [],
        'pinecone' => [
            'api_key' => env('PINECONE_API_KEY'),
            'index' => env('PINECONE_INDEX'),
        ],
    ],
    'context' => [
        'limit' => 3,
        'max_snippet_length' => 200,
    ],
],
```

### Properties

- **limit** (int) – Number of vector results to retrieve
- **max_snippet_length** (int) – Characters per snippet

## Batch Section

```php
'batch' => [
    'enabled' => true,
    'size' => 50,
    'delimiter' => "\t",
    'llm_connection' => env('SYNAPSE_TOON_LLM_CONNECTION', 'default'),
],
```

### Properties

- **size** (int) – Requests per batch (1-100)
- **delimiter** (string) – Separator between batch items
- **llm_connection** (string) – LLM client binding name

### Batch Size Trade-offs

| Size | Latency | Tokens Saved | Ideal For |
|------|---------|--------------|-----------|
| 10 | Low | 15% | Real-time |
| 50 | Medium | 40% | **Recommended** |
| 100 | High | 50% | Background jobs |

## Router Section

```php
'router' => [
    'enabled' => true,
    'strategies' => [
        [
            'name' => 'ultra-light',
            'max_complexity' => 0.3,
            'max_tokens' => 512,
            'target' => 'gpt-4o-mini',
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

### Complexity Scoring

- `0.0-0.3`: Simple lookups, summaries
- `0.3-0.7`: Moderate analysis, code generation
- `0.7-1.0`: Complex reasoning, multi-step tasks

## Best Practices

1. **Development** – Use `log` driver for visibility
2. **Production** – Use `prometheus` or `datadog` with thresholds
3. **Performance** – Enable Octane preloading for high-traffic APIs
4. **Cost** – Tune compression quality based on throughput
5. **Metrics** – Lower threshold during testing, raise in production
