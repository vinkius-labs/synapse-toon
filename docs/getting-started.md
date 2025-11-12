# Getting Started

NOTE: This documentation covers Synapse TOON only. It does not reference other packages or projects unrelated to Synapse TOON.

This guide will walk you through installing and configuring Synapse TOON in your Laravel application in under 5 minutes.

## Prerequisites

- Laravel 10.x, 11.x, or 12.x
- PHP 8.2 or 8.3
- Composer 2.x

## Installation

### Step 1: Install via Composer

```bash
composer require vinkius-labs/synapse-toon
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=synapse-toon-config
```

This creates `config/synapse-toon.php` with all available options.

### Step 3: Register Middleware

#### Laravel 11+ (using bootstrap/app.php)

```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(append: [
            \VinkiusLabs\SynapseToon\Http\Middleware\SynapseToonCompressionMiddleware::class,
            \VinkiusLabs\SynapseToon\Http\Middleware\SynapseToonHttp3Middleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

#### Laravel 10 (using app/Http/Kernel.php)

```php
protected $middlewareGroups = [
    'api' => [
        // ... existing middleware
        \VinkiusLabs\SynapseToon\Http\Middleware\SynapseToonCompressionMiddleware::class,
        \VinkiusLabs\SynapseToon\Http\Middleware\SynapseToonHttp3Middleware::class,
    ],
];
```

### Step 4: Configure Environment Variables

Add to your `.env`:

```env
SYNAPSE_TOON_METRICS_DRIVER=log
SYNAPSE_TOON_LOG_CHANNEL=stack
```

## First Response

Create a simple API endpoint to test Synapse TOON:

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/api/test', function (Request $request) {
    return response()->synapseToon([
        'message' => 'Hello from Synapse TOON!',
        'timestamp' => now()->toIso8601String(),
        'data' => [
            'users' => 1247,
            'products' => 8934,
            'orders' => 4521,
        ],
    ]);
});
```

Test it:

```bash
curl -H "Accept-Encoding: br" http://localhost:8000/api/test
```

Expected headers:

```
Content-Type: application/x-synapse-toon
Content-Encoding: br
X-Synapse-TOON-Compressed: brotli
```

## Verify Metrics

Check your logs to see savings metrics:

```bash
tail -f storage/logs/laravel.log
```

You should see entries like:

```
[2025-01-15 10:23:45] local.INFO: {"endpoint":"/api/test","json_tokens":47,"toon_tokens":28,"savings_percent":40.4}
```

## Next Steps

- [Configuration Guide](configuration.md) – Customize Synapse TOON for your needs
- [Encoding & Compression](encoding-compression.md) – Understand the TOON algorithm
- [Metrics & Analytics](metrics-analytics.md) – Set up Prometheus or Datadog
- [Cost Optimization Guide](cost-optimization.md) – Maximize your token savings

## Quick Tips

### Selective Middleware

Apply Synapse TOON only to specific routes:

```php
Route::middleware(['synapsetoon.compression'])->group(function () {
    Route::post('/ai/complete', [AIController::class, 'complete']);
    Route::get('/products', [ProductController::class, 'index']);
});
```

### Disable for Development

In `config/synapse-toon.php`:

```php
'defaults' => [
    'enabled' => env('SYNAPSE_TOON_ENABLED', true),
],
```

Then in `.env.local`:

```env
SYNAPSE_TOON_ENABLED=false
```

### Test Without Middleware

Use the encoder directly:

```php
use VinkiusLabs\SynapseToon\Facades\SynapseToon;

$encoded = SynapseToon::encoder()->encode(['data' => 'test']);
$decoded = SynapseToon::decoder()->decode($encoded);
```

## Troubleshooting

### Brotli Not Available

If you see `X-Synapse-TOON-Compressed: gzip` instead of `brotli`:

```bash
# Install PHP Brotli extension
pecl install brotli

# Add to php.ini
echo "extension=brotli.so" >> /etc/php/8.2/cli/php.ini
```

### No Metrics Appearing

Check driver configuration:

```php
'metrics' => [
    'enabled' => true,
    'driver' => 'log',
],
```

Ensure threshold is met:

```php
'thresholds' => [
    'minimum_savings_percent' => 8, // Responses below 8% won't log
],
```

### Large Payload Performance

For payloads > 100KB, consider:

```php
'compression' => [
    'brotli' => [
        'quality' => 6, // Lower quality = faster encoding
    ],
],
```

## Ready for Production?

Before deploying:

1. ✅ Configure metrics driver (Prometheus/Datadog)
2. ✅ Set appropriate compression quality
3. ✅ Test with production-like payloads
4. ✅ Enable Octane preloading if using Swoole/RoadRunner
5. ✅ Monitor savings metrics for 24-48 hours

Continue to [Configuration Guide](configuration.md) for advanced setup options.
