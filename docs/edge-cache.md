# Edge Cache

Multi-tier caching strategy.

## Cache Architecture

### L1: Request Cache

```php
namespace VinkiusLabs\SynapseToon\Cache;

class RequestCache
{
    private array $store = [];
    
    public function get(string $key): mixed
    {
        return $this->store[$key] ?? null;
    }
    
    public function put(string $key, mixed $value, int $ttl = null): void
    {
        $this->store[$key] = [
            'value' => $value,
            'expires_at' => $ttl ? time() + $ttl : null,
        ];
    }
    
    public function has(string $key): bool
    {
        $entry = $this->store[$key] ?? null;
        
        if ($entry === null) {
            return false;
        }
        
        if ($entry['expires_at'] && time() > $entry['expires_at']) {
            unset($this->store[$key]);
            return false;
        }
        
        return true;
    }
}
```

### L2: Redis Cache

```php
class SynapseToonRedisEdgeCache
{
    public function remember(string $key, int $ttl, callable $callback): string
    {
        $cacheKey = 'synapse-edge:' . $key;
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        $response = $callback();
        
        $encoded = SynapseToon::encoder()->encode($response);
        $compressed = SynapseToon::compressor()->compress($encoded);
        
        Cache::put($cacheKey, $compressed, $ttl);
        
        return $compressed;
    }
}
```

## Cache Backends

### Redis Backend

```php
// config/cache.php

return [
    'default' => env('CACHE_DRIVER', 'redis'),
    'stores' => [
        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'ttl' => 3600,
        ],
    ],
];
```

### Memcached Backend

```php
return [
    'stores' => [
        'memcached' => [
            'driver' => 'memcached',
            'servers' => [
                [
                    'host' => '127.0.0.1',
                    'port' => 11211,
                    'weight' => 100,
                ],
            ],
        ],
    ],
];
```

### DynamoDB Backend

```php
return [
    'stores' => [
        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => 'synapse-cache',
        ],
    ],
];
```

## Cache Strategies

### Time-Based TTL

```php
class CacheStrategy
{
    public function getTTL(string $type): int
    {
        return match($type) {
            'user_profile' => 3600, // 1 hour
            'api_response' => 300, // 5 minutes
            'ml_prediction' => 86400, // 1 day
            default => 600, // 10 minutes
        };
    }
}
```

### Tag-Based Invalidation

```php
class TaggedCache
{
    public function put(string $key, mixed $value, array $tags, int $ttl): void
    {
        Cache::tags($tags)->put($key, $value, $ttl);
    }
    
    public function flush(string $tag): void
    {
        Cache::tags($tag)->flush();
    }
}

// Usage
$cache = app(TaggedCache::class);
$cache->put('user:123', $userData, ['user', 'user:123'], 3600);
$cache->flush('user'); // Flush all user caches
```

### LRU Eviction

```php
class LRUCache
{
    private int $maxSize = 1000;
    private array $store = [];
    private array $accessOrder = [];
    
    public function get(string $key): mixed
    {
        if (!isset($this->store[$key])) {
            return null;
        }
        
        $this->updateAccessOrder($key);
        return $this->store[$key];
    }
    
    public function put(string $key, mixed $value): void
    {
        if (count($this->store) >= $this->maxSize) {
            $evicted = array_shift($this->accessOrder);
            unset($this->store[$evicted]);
        }
        
        $this->store[$key] = $value;
        $this->updateAccessOrder($key);
    }
    
    private function updateAccessOrder(string $key): void
    {
        if (in_array($key, $this->accessOrder)) {
            unset($this->accessOrder[array_search($key, $this->accessOrder)]);
        }
        $this->accessOrder[] = $key;
    }
}
```

## Cache Invalidation

### Event-Based Invalidation

```php
class UserUpdated
{
    public function __construct(
        public readonly User $user,
    ) {}
    
    public function invalidateCache(): void
    {
        Cache::tags('user', 'user:' . $this->user->id)->flush();
    }
}
```

### Scheduled Invalidation

```php
class ScheduledCacheInvalidation
{
    public function handle(): void
    {
        Cache::forget('stale_key');
        Cache::tags('temporary')->flush();
    }
}

// Schedule in Kernel.php
$schedule->call(ScheduledCacheInvalidation::class)
    ->hourly();
```

## Distributed Caching

### Cache Stampede Prevention

```php
class CacheWithLock
{
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        if (Cache::has($key)) {
            return Cache::get($key);
        }
        
        return Cache::lock($key, 10)->get(function() use ($key, $ttl, $callback) {
            $value = $callback();
            Cache::put($key, $value, $ttl);
            return $value;
        });
    }
}
```

## Monitoring

### Cache Metrics

```php
class CacheMetrics
{
    public function recordHit(string $key): void
    {
        SynapseToon::metrics()->increment('cache_hit', tags: [
            'key' => $key,
        ]);
    }
    
    public function recordMiss(string $key): void
    {
        SynapseToon::metrics()->increment('cache_miss', tags: [
            'key' => $key,
        ]);
    }
    
    public function hitRatio(): float
    {
        $hits = SynapseToon::metrics()->get('cache_hit', 0);
        $misses = SynapseToon::metrics()->get('cache_miss', 0);
        
        return $hits / ($hits + $misses);
    }
}
```

## Testing

```php
class SynapseToonEdgeCacheTest extends TestCase
{
    public function testCacheHit(): void
    {
        Cache::put('test_key', 'test_value', 3600);
        
        $this->assertTrue(Cache::has('test_key'));
        $this->assertEquals('test_value', Cache::get('test_key'));
    }
    
    public function testCacheExpiration(): void
    {
        Cache::put('test_key', 'test_value', 1);
        
        sleep(2);
        
        $this->assertNull(Cache::get('test_key'));
    }
}
```

## Performance Optimization

### Cache Layer Comparison

```
Request Cache:    0.1ms, 1MB max
Redis Cache:      1ms,   Unlimited
Memcached:        1.5ms, Unlimited
DynamoDB:         50ms,  Unlimited

Recommendation: Multi-tier (Request + Redis)
```

## Real-World Example

### Synapse TOON Use Case — User Profile Caching

```php
class UserProfileService
{
    public function getProfile(int $userId): array
    {
        return Cache::remember(
            "user:{$userId}:profile",
            3600,
            fn() => User::find($userId)->load('preferences')->toArray()
        );
    }
    
    public function invalidateProfile(int $userId): void
    {
        Cache::forget("user:{$userId}:profile");
    }
}
```

## Next Steps

- [Performance Tuning](performance-tuning.md) – Optimize strategies
- [Cost Optimization](cost-optimization.md) – Reduce costs
- [Architecture](architecture.md) – System design
