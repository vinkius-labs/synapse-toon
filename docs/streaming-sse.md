# Streaming & SSE

NOTE: This documentation describes Synapse TOON's streaming and SSE features only.

Server-Sent Events implementation for real-time LLM responses with optimal chunking.

## SSE (Server-Sent Events) Protocol

### Wire Format

```
id: 550e8400-e29b-41d4-a716-446655440000
event: update
data: [base64-encoded chunk]

id: 550e8400-e29b-41d4-a716-446655440001
event: update
data: [base64-encoded chunk]
```

### HTTP Headers

```
HTTP/1.1 200 OK
Content-Type: text/event-stream
Cache-Control: no-cache, no-transform
X-Accel-Buffering: no
X-Synapse-TOON-Format: streaming
Connection: keep-alive
```

### Critical Headers Explained

- **Content-Type: text/event-stream** – Tells browser this is SSE
- **Cache-Control: no-cache, no-transform** – Prevent caching/proxying
- **X-Accel-Buffering: no** – Disable nginx buffering
- **Connection: keep-alive** – Keep connection open

## Implementation

### Basic Streaming

```php
use VinkiusLabs\SynapseToon\Facades\SynapseToon;

Route::get('/chat/stream', function (Request $request) {
    $stream = OpenAI::chat()->createStreamed([
        'model' => 'gpt-4o',
        'messages' => $request->input('messages'),
        'stream' => true,
    ]);
    
    return response()->synapseToonStream($stream, function ($chunk) {
        return ['delta' => $chunk['choices'][0]['delta']['content'] ?? ''];
    });
});
```

### Advanced Streaming with Context

```php
public function streamWithContext(Request $request)
{
    $rag = SynapseToon::rag()->buildContext(
        $request->input('query'),
        ['user_id' => auth()->id()]
    );
    
    $stream = OpenAI::chat()->createStreamed([
        'model' => 'gpt-4o',
        'messages' => [
            ['role' => 'system', 'content' => $rag],
            ['role' => 'user', 'content' => $request->input('message')],
        ],
        'stream' => true,
    ]);
    
    return response()->synapseToonStream($stream, function ($chunk) {
        return [
            'delta' => $chunk['choices'][0]['delta']['content'] ?? '',
            'usage' => $chunk['usage'] ?? null,
            'finish_reason' => $chunk['choices'][0]['finish_reason'] ?? null,
        ];
    });
}
```

## Chunking Strategy

### Adaptive Chunk Sizing

```php
class ChunkOptimizer
{
    public function getOptimalChunkSize(int $payloadSize): int
    {
        // Larger payloads benefit from larger chunks
        return match (true) {
            $payloadSize < 1024 => 512,           // 512B
            $payloadSize < 10240 => 2048,         // 2KB
            $payloadSize < 102400 => 4096,        // 4KB (default)
            $payloadSize < 1048576 => 8192,       // 8KB
            default => 16384,                     // 16KB
        };
    }
}
```

### Flush Optimization

```php
private function flushBuffers(): void
{
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    
    if (function_exists('flush')) {
        flush();
    }
}
```

**Critical**: Both `ob_flush()` and `flush()` needed:
- `ob_flush()` flushes output buffer
- `flush()` sends to network socket

## Client-Side Reception

### JavaScript

```javascript
const eventSource = new EventSource('/chat/stream');

eventSource.addEventListener('update', (event) => {
    const chunk = JSON.parse(event.data);
    console.log('Delta:', chunk.delta);
    document.getElementById('response').textContent += chunk.delta;
});

eventSource.onerror = () => {
    eventSource.close();
    console.log('Stream ended');
};
```

### Error Handling

```javascript
eventSource.addEventListener('error', (event) => {
    if (event.readyState === EventSource.CLOSED) {
        console.log('Connection closed');
    } else {
        console.error('Stream error:', event);
        eventSource.close();
    }
});
```

## Compression in Streaming

### Brotli Streaming Compression

```php
function streamWithCompression(iterable $stream): void
{
    $compressor = new SynapseToonCompressor(config());
    
    foreach ($stream as $chunk) {
        $encoded = json_encode($chunk);
        $compressed = $compressor->compress($encoded, 'br');
        
        echo sprintf(
            "id: %s\nevent: update\ndata: %s\n\n",
            Str::uuid(),
            base64_encode($compressed['body'])
        );
        
        ob_flush();
        flush();
    }
}
```

### Network Impact

```
Uncompressed: 100 chunks × 1.2KB = 120KB
Compressed (Brotli): 100 chunks × 0.35KB = 35KB
Savings: 85KB (71%)
```

## Backpressure Handling

### Rate Limiting with Backoff

```php
class BackpressureHandler
{
    private int $maxBufferSize = 65536; // 64KB
    
    public function handleBackpressure(): void
    {
        $current = ob_get_length() ?? 0;
        
        if ($current > $this->maxBufferSize) {
            ob_flush();
            flush();
            
            if (connection_status() !== CONNECTION_NORMAL) {
                throw new StreamInterruptedException('Client disconnected');
            }
            
            sleep(1); // Wait before resuming
        }
    }
}
```

## Connection Monitoring

### Detect Client Disconnection

```php
public function streamWithMonitoring(iterable $stream): void
{
    foreach ($stream as $chunk) {
        if (connection_status() !== CONNECTION_NORMAL) {
            Log::info('Client disconnected, stopping stream');
            break;
        }
        
        echo formatSSE($chunk);
        ob_flush();
        flush();
    }
}
```

### Timeout Handling

```php
set_time_limit(300); // 5 minute timeout
ignore_user_abort(true); // Continue even if user closes browser

try {
    foreach ($stream as $chunk) {
        echo formatSSE($chunk);
        ob_flush();
        flush();
    }
} catch (Throwable $e) {
    Log::error('Stream error', ['error' => $e->getMessage()]);
} finally {
    ignore_user_abort(false);
}
```

## Performance Metrics

### Latency Measurement

```php
private function measureStreamLatency(iterable $stream): void
{
    $startTime = hrtime(true);
    $chunkCount = 0;
    
    foreach ($stream as $chunk) {
        $chunkCount++;
        
        if ($chunkCount % 10 === 0) {
            $elapsed = (hrtime(true) - $startTime) / 1_000_000; // ms
            $rate = ($chunkCount / $elapsed) * 1000; // chunks/sec
            
            Log::info('Stream rate', ['rate' => $rate, 'chunk' => $chunkCount]);
        }
        
        echo formatSSE($chunk);
        ob_flush();
        flush();
    }
}
```

### Expected Performance

```
Throughput:
- Uncompressed: 50-100 MB/sec
- Compressed (Brotli): 20-40 MB/sec
- Network benefit: 3-5x

Time to First Byte (TTFB):
- Uncompressed: 80-150ms
- Compressed: 150-300ms
- Still <400ms acceptable for streaming
```

## Error Handling in Streams

### Graceful Degradation

```php
public function streamWithFallback(Request $request)
{
    try {
        $stream = OpenAI::chat()->createStreamed([...]);
        return response()->synapseToonStream($stream);
    } catch (OpenAI\Exceptions\RateLimitException $e) {
        // Fallback to non-streaming
        return response()->synapseToon(
            ['error' => 'Rate limited, using standard response']
        );
    } catch (Throwable $e) {
        Log::error('Stream failed', ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Stream failed'], 500);
    }
}
```

## Testing Streams

### Test Stream Generator

```php
public function testStreamGenerator(): void
{
    $generator = function () {
        for ($i = 0; $i < 100; $i++) {
            yield [
                'index' => $i,
                'text' => 'Token ' . $i,
                'timestamp' => now()->toDateTimeString(),
            ];
            
            usleep(10000); // 10ms between chunks
        }
    };
    
    $response = SynapseToon::stream($generator());
    
    $this->assertEquals(200, $response->status());
    $this->assertEquals('text/event-stream', $response->headers->get('Content-Type'));
}
```

### Browser Testing

```html
<div id="stream-test"></div>
<script>
const es = new EventSource('/test-stream');
let count = 0;

es.addEventListener('update', (e) => {
    const data = JSON.parse(e.data);
    document.getElementById('stream-test').innerHTML += 
        `Chunk ${count++}: ${data.text}<br>`;
});
</script>
```

## Production Considerations

1. **Connection Pooling** – Limit concurrent streams per user
2. **Timeout Management** – 5-30 min depending on use case
3. **Memory Management** – Stream from iterators, not arrays
4. **Error Logging** – Log all stream interruptions
5. **Rate Limiting** – Prevent stream spam attacks

## Next Steps

- [Metrics & Analytics](metrics-analytics.md) – Track stream performance
- [Performance Tuning](performance-tuning.md) – Optimize latency
- [Architecture](architecture.md) – Understand design patterns
