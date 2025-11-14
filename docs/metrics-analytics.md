# Metrics & Analytics

NOTE: This documentation covers metrics and analytics for Synapse TOON only.

Production observability with Prometheus, Datadog, and custom drivers.

## Recorded Metrics

### Standard Payload

```json
{
  "endpoint": "/api/products",
  "json_tokens": 1247,
  "toon_tokens": 683,
  "savings_percent": 45.2,
  "compression_algorithm": "brotli",
  "compression_ratio": 0.548,
  "encoding_time_ms": 2.8,
  "compression_time_ms": 4.2,
  "total_time_ms": 7.0,
  "original_size_bytes": 8956,
  "compressed_size_bytes": 4918,
  "response_status": 200,
  "batch": false,
  "batch_size": null,
  "timestamp": 1705330225.4132
}
```

## Prometheus Integration

### Metrics Pushed

```
synapse_toon_tokens_saved_total{endpoint="/api/products",algorithm="brotli"} 564
synapse_toon_compression_ratio{endpoint="/api/products"} 0.548
synapse_toon_encoding_time_ms{endpoint="/api/products"} 2.8
synapse_toon_compression_time_ms{endpoint="/api/products"} 4.2
synapse_toon_response_size_bytes_original{endpoint="/api/products"} 8956
synapse_toon_response_size_bytes_compressed{endpoint="/api/products"} 4918
```

### Configuration

```php
'metrics' => [
    'driver' => 'prometheus',
    'drivers' => [
        'prometheus' => [
            'push_gateway' => 'http://prometheus-pushgateway:9091',
            'job' => 'synapse-toon',
        ],
    ],
],
```

### Prometheus Queries

#### Total Tokens Saved (Last Hour)

```promql
sum(rate(synapse_toon_tokens_saved_total[1h]))
```

#### Average Compression Ratio

```promql
avg(synapse_toon_compression_ratio)
```

#### P95 Encoding Latency

```promql
histogram_quantile(0.95, synapse_toon_encoding_time_ms)
```

#### Cost Savings (Estimate at $5/1M tokens)

```promql
sum(synapse_toon_tokens_saved_total) * 5 / 1000000
```

## Datadog Integration

### Configuration

```php
'metrics' => [
    'driver' => 'datadog',
    'drivers' => [
        'datadog' => [
            'api_key' => env('DATADOG_API_KEY'),
            'endpoint' => 'https://api.datadoghq.com/api/v1/series',
        ],
    ],
],
```

### Custom Metrics

```
synapse_toon.tokens_saved
synapse_toon.compression_ratio
synapse_toon.encoding_time_ms
synapse_toon.compression_time_ms
synapse_toon.response_size_original
synapse_toon.response_size_compressed
```

### Dashboard Example

```
Title: Synapse TOON Optimization Metrics

Widgets:
1. Tokens Saved (Gauge) - Last 24h
2. Avg Compression Ratio (Timeseries) - Last 7d
3. Cost Savings (Big Number) - YTD
4. Top Endpoints by Savings (Table)
5. P95 Latency Impact (Timeseries)
```

## Log Driver

### Log Format

```bash
# storage/logs/laravel.log
[2025-01-15 10:23:45] local.INFO: {"endpoint":"/api/products","json_tokens":1247,"toon_tokens":683,"savings_percent":45.2,"compression_algorithm":"brotli"}
```

### Parsing with ELK

```json
{
  "timestamp": "2025-01-15T10:23:45Z",
  "level": "INFO",
  "message": "Synapse TOON Metrics",
  "endpoint": "/api/products",
  "json_tokens": 1247,
  "toon_tokens": 683,
  "savings_percent": 45.2,
  "compression_algorithm": "brotli"
}
```

## Custom Metrics Collection

### Implementing Custom Driver

```php
namespace App\Synapse;

use VinkiusLabs\SynapseToon\Contracts\SynapseToonMetricsDriver;

class CustomMetricsDriver implements SynapseToonMetricsDriver
{
    public function record(array $payload): void
    {
        // Send to custom backend
        $this->sendToAnalytics([
            'endpoint' => $payload['endpoint'],
            'savings_percent' => $payload['savings_percent'],
            'cost_saved' => $this->calculateCost($payload),
            'timestamp' => microtime(true),
        ]);
    }
    
    private function calculateCost(array $payload): float
    {
        $tokensSaved = $payload['json_tokens'] - $payload['toon_tokens'];
        $costPerMillionTokens = 5.0; // GPT-4o input
        
        return ($tokensSaved / 1_000_000) * $costPerMillionTokens;
    }
}
```

Register in SynapseToonServiceProvider:

```php
$this->app->bind(
    'synapse-toon.drivers.custom',
    CustomMetricsDriver::class
);
```

## Real-Time Dashboarding

### Cost Savings Calculation

```php
$monthlySavings = $this->calculateMonthly([
    'total_tokens_saved' => 15_400_000,
    'cost_per_million' => 5.0,
    'batch_optimization' => 0.89, // 89% savings on batch overhead
]);

// $15.4M tokens × $5 / 1M = $77
// × 89% batch savings = $68.53/month
```

### Endpoint Ranking

```sql
SELECT 
    endpoint,
    COUNT(*) as requests,
    SUM(json_tokens) as total_input_tokens,
    SUM(toon_tokens) as compressed_tokens,
    ROUND(100 * (1 - SUM(toon_tokens) / SUM(json_tokens)), 2) as savings_percent,
    ROUND(SUM(json_tokens - toon_tokens) * 0.000005, 2) as cost_saved
FROM synapse_metrics
WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY endpoint
ORDER BY cost_saved DESC
LIMIT 10;
```

## Alerts & Thresholds

### Prometheus Alert Rules

```yaml
groups:
  - name: synapse_toon
    rules:
      - alert: LowCompressionRatio
        expr: synapse_toon_compression_ratio < 0.6
        for: 5m
        annotations:
          summary: "Compression ratio below 60%"
      
      - alert: HighEncodingLatency
        expr: histogram_quantile(0.95, synapse_toon_encoding_time_ms) > 50
        for: 5m
        annotations:
          summary: "P95 encoding latency > 50ms"
      
      - alert: NoSavingsRecorded
        expr: increase(synapse_toon_tokens_saved_total[1h]) == 0
        for: 1h
        annotations:
          summary: "No token savings in last hour"
```

## Monitoring Checklist

- [ ] Compression ratio > 60%
- [ ] Encoding latency < 50ms (P95)
- [ ] Token savings > 30%
- [ ] No increased error rates
- [ ] Memory usage stable
- [ ] Connection pool healthy

## Troubleshooting Metrics

### Issue: Metrics not appearing in Prometheus

**Check**:
1. PushGateway endpoint accessible
2. Job name configured correctly
3. Metrics driver enabled in config

```bash
curl http://prometheus-pushgateway:9091/metrics | grep synapse_toon
```

### Issue: High latency but low savings

**Cause**: Compression quality too high

**Fix**: Reduce Brotli quality

```php
'compression' => [
    'brotli' => ['quality' => 4],
],
```

### Issue: Inconsistent metrics

**Cause**: Multiple workers pushing simultaneously

**Fix**: Use lock mechanism

```php
Cache::lock('synapse-metrics-push', 10)->get(function () {
    $this->driver->record($payload);
});
```

## Next Steps

- [Performance Tuning](performance-tuning.md) – Optimize based on metrics
- [Cost Optimization Guide](cost-optimization.md) – Maximize ROI
- [Technical Reference](technical-reference.md) – Understand system design
