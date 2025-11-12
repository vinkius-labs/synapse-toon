# Technical Reference

This reference is the definitive map for every SynapseToon service, contract, and configuration surface. Use it as a companion when embedding the package into production workloads.

## Container Bindings

| Type-hint | Resolves to | Purpose |
| --- | --- | --- |
| `VinkiusLabs\SynapseToon\SynapseToonManager` | Singleton manager | Entry point for every SynapseToon subsystem and the facade. |
| `VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder` | Entropy-aware codec | Normalises payloads, applies dictionary compression, exposes token heuristics. |
| `VinkiusLabs\SynapseToon\Encoding\SynapseToonDecoder` | Decoder companion | Rehydrates TOON payloads emitted by the encoder. |
| `VinkiusLabs\SynapseToon\Compression\SynapseToonCompressor` | Adaptive compressor | Negotiates Brotli/Gzip/Deflate based on `Accept-Encoding` and package preferences. |
| `VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics` | Metrics orchestrator | Records savings/throughput via pluggable drivers and thresholds. |
| `VinkiusLabs\SynapseToon\Support\SynapseToonPayloadAnalyzer` | Token analyst | Compares JSON vs TOON footprints and returns savings percentages. |
| `VinkiusLabs\SynapseToon\Routing\SynapseToonLLMRouter` | Model router | Chooses the optimal LLM target and forwards encoded payloads. |
| `VinkiusLabs\SynapseToon\Rag\SynapseToonRagService` | Context builder | Fetches vector snippets and compresses them before inference. |
| `VinkiusLabs\SynapseToon\Caching\SynapseToonEdgeCache` | Edge cache helper | Encodes responses once and shares them across cache tiers. |
| `VinkiusLabs\SynapseToon\Streaming\SynapseToonSseStreamer` | SSE engine | Streams TOON chunks with automatic UUID event IDs and flush control. |
| `VinkiusLabs\SynapseToon\GraphQL\SynapseToonGraphQLAdapter` | GraphQL adapter | Wraps GraphQL results in TOON responses with partial-error awareness. |

## Service Provider

`VinkiusLabs\SynapseToon\SynapseToonServiceProvider` is auto-discovered and delivers:

- Published configuration via the `synapse-toon-config` tag.
- Response macros:
	- `Response::synapseToon($payload, int $status = 200, array $headers = [])`
	- `Response::synapseToonStream(iterable $stream, ?callable $transform = null, array $headers = [])`
	- `Response::toon()` (alias of `synapseToon`)
- Request macro: `Request::wantsToon()` detects `application/x-synapse-toon` accept headers or the `synapse_toon` query flag.
- Middleware aliases:
	- `synapsetoon.http3` → `SynapseToonHttp3Middleware`
	- `synapsetoon.compression` → `SynapseToonCompressionMiddleware`

## Facade & Manager

The `SynapseToon` facade is a thin proxy over the manager singleton. Prefer injecting the manager for testability, and fall back to the facade in route closures.

### SynapseToonManager API

| Method | Returns | Description |
| --- | --- | --- |
| `config(string $key, $default = null)` | mixed | Reads `synapse-toon.*` configuration values. |
| `encoder(): SynapseToonEncoder` | Encoder | Primary codec instance (dictionary + heuristics). |
| `decoder(): SynapseToonDecoder` | Decoder | Rehydrates TOON documents. |
| `encode(mixed $payload, array $options = []): string` | string | Convenience wrapper around `SynapseToonEncoder::encode`. |
| `encodeChunk(string $chunk, array $options = []): string` | string | Normalises streaming chunks for SSE. |
| `decode(string $payload, array $options = []): mixed` | mixed | Decodes TOON payloads. |
| `metrics(): SynapseToonMetrics` | Metrics | Records ROI and throughput events. |
| `analyzer(): SynapseToonPayloadAnalyzer` | Analyzer | Computes savings metrics per payload. |
| `rag(): SynapseToonRagService` | RAG service | Builds compressed retrieval contexts. |
| `router(): SynapseToonLLMRouter` | Router | Selects and invokes LLM clients. |
| `edgeCache(): SynapseToonEdgeCache` | Edge cache | Encode-once caching helper. |
| `streamer(): SynapseToonSseStreamer` | Streamer | Server-Sent Events bridge. |
| `graphql(): SynapseToonGraphQLAdapter` | Adapter | TOON wrapper for GraphQL responses. |

## Core Components

### SynapseToonEncoder

- `encode(mixed $payload, array $options = [])`: Accepts arrays, objects, Eloquent models, `JsonSerializable`, or raw JSON strings. Options include `dictionary`, `preserve_zero_fraction`, and `minify`.
- `decode(string $payload, array $options = [])`: Restores original structures using the same dictionary map.
- `encodeChunk(string $chunk, array $options = [])`: Squishes whitespace, applies length guards (`max_size`), and appends a delimiter for SSE.
- `complexityScore(mixed $payload): float`: Returns a 0–1 complexity metric used by the LLM router.
- `estimatedTokens(mixed $payload): int`: Approximates token counts at ~4 characters per token.

### SynapseToonCompressor

- `compress(string $payload, ?string $acceptEncoding = null, array $options = []): array{body: string, encoding: ?string, algorithm: string}` negotiates Brotli/Gzip/Deflate with graceful fallbacks.
- Honours `synapse-toon.compression.prefer`, algorithm-specific quality/level settings, and writes the `X-Synapse-TOON-Compressed` header.
- Returns `algorithm => 'none'` when compression is disabled or unavailable.

### SynapseToonEdgeCache

- `remember(string $key, Closure $callback, ?int $ttl = null): string` encodes the callback result once and stores it in the configured cache store (`synapse-toon.edge_cache.store`, default TTL `synapse-toon.edge_cache.ttl`).

### SynapseToonSseStreamer

- `stream(Request $request, iterable $stream, ?Closure $transform = null, array $headers = []): StreamedResponse` emits UUID-tagged SSE frames with TOON-encoded data.
- Default headers disable buffering (`X-Accel-Buffering: no`) and advertise TOON format.

### SynapseToonPayloadAnalyzer

- `analyze(mixed $payload, ?string $encoded = null): array{json_tokens:int, toon_tokens:int, savings_percent:float}` compares raw vs TOON footprints to quantify ROI.

### SynapseToonProcessLLMBatchJob

Queue-friendly batch coordinator that compresses prompts, routes them, and records metrics.

```php
use VinkiusLabs\SynapseToon\Jobs\SynapseToonProcessLLMBatchJob;

SynapseToonProcessLLMBatchJob::dispatch($requests, [
		'queue' => 'llm-batch',
		'connection' => 'openai.primary',
		'dictionary' => ['prompt' => 'p', 'metadata' => 'm'],
		'delimiter' => "\u{001E}",
		'estimated_json_tokens' => 12000,
		'savings_percent' => 41.8,
]);
```

| Option | Type | Description |
| --- | --- | --- |
| `queue` | string&#124;null | Laravel queue name to hand off to. |
| `connection` | string&#124;null | Container binding for a `SynapseToonLLMClient`; fallbacks use router clients. |
| `delimiter` | string | Separator between prompts before encoding (defaults to `"\t"`). |
| `dictionary` | array | Dictionary overrides applied during encoding. |
| `estimated_json_tokens` | int&#124;null | Baseline token count for ROI logging. |
| `savings_percent` | float&#124;null | Manual savings override when you pre-compute ROI. |

### SynapseToonLLMRouter

- `route(mixed $payload, array $context = []): string` evaluates complexity and token counts against configured strategies.
- `send(mixed $payload, array $context = []): array` routes and forwards to a `SynapseToonLLMClient`.
- Clients can be registered under container keys like `synapse-toon.router.clients.gpt-4o` or passed via the `connection` option.

### SynapseToonRagService

- `buildContext(string $query, array $metadata = []): string` queries the configured `SynapseToonVectorStore`, limits snippets, and returns TOON-compressed context.
- Driver resolution honours `synapse-toon.rag.driver`; unset or disabled falls back to `SynapseToonNullVectorStore`.

