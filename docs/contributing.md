# Contributing

NOTE: This guide is for contributing to the Synapse TOON project only. It does not apply to other projects in the repository.

Guidelines for contributing to Synapse TOON.

## Development Setup

### Prerequisites

```bash
PHP 8.2+
Laravel 10+
Composer
PostgreSQL 14+
Redis 7+
```

### Installation

```bash
git clone https://github.com/VinkiusLabs/synapse-toon.git
cd synapse-toon
composer install
cp .env.example .env
php artisan key:generate
```

### Docker Setup

```bash
docker-compose up -d
docker-compose exec app php artisan migrate
```

## Code Standards

### PHP 8.2+ Features Required

```php
declare(strict_types=1);

namespace VinkiusLabs\SynapseToon;

final readonly class Example
{
    private string $value = '';

    public function __construct(
        private string $config,
    ) {}

    public function handle(): string
    {
        return $this->config;
    }

    private function helper(): void
    {
        // Private helper
    }
}
```

Rules:
- `declare(strict_types=1)` required
- PSR-12 formatting
- Type hints on all properties and parameters
- `private` by default (encapsulation)
- `readonly` where possible
- No `public` properties

#### Naming Conventions

```php
// Classes: PascalCase
class SynapseToonEncoder {}

// Methods: camelCase
public function encodePayload() {}

// Properties: camelCase
private string $payloadData = '';

// Constants: UPPER_SNAKE_CASE
const DEFAULT_QUALITY = 11;

// Config keys: snake_case
'encoding' => [
    'compression_level' => 6,
]
```

### Testing Requirements

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test tests/Unit/EncoderTest.php

# Coverage report
php artisan test --coverage
```

All code must have 80%+ test coverage.

## Pull Request Process

1. **Fork the repository**

```bash
git clone https://github.com/YOUR_USERNAME/synapse-toon.git
```

2. **Create feature branch**

```bash
git checkout -b feature/your-feature-name
```

3. **Make changes and test**

```bash
php artisan test
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
```

4. **Commit with clear messages**

```bash
git commit -m "feat: add token optimization for batch processing"
```

Follow [Conventional Commits](https://www.conventionalcommits.org/)

5. **Push to your fork**

```bash
git push origin feature/your-feature-name
```

6. **Create Pull Request**

Include:
- What you changed
- Why you changed it
- Testing performed
- Before/after metrics if applicable

## Commit Message Format

```
<type>(<scope>): <subject>
<BLANK LINE>
<body>
<BLANK LINE>
<footer>
```

Types:
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation
- `test:` Adding tests
- `perf:` Performance improvement
- `refactor:` Code restructure

Example:

```
feat(encoder): add SIMD optimizations for token compression

Implement SIMD operations for 50% faster encoding on AVX2 systems.
Adds fallback for non-SIMD architectures.

Closes #123
Performance: 2.5ms → 1.2ms per 100K tokens
```

## Architecture Guidelines

### Dependency Injection

```php
class UserService
{
    public function __construct(
        private readonly EncoderContract $encoder,
        private readonly CacheRepository $cache,
    ) {}
    
    public function getUser(int $id): User
    {
        $cached = $this->cache->get("user:{$id}");
        return $cached ?: $this->encoder->encode($this->fetchUser($id));
    }
}
```

### Service Providers

```php
namespace VinkiusLabs\SynapseToon;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Encoder::class, function ($app) {
            return new BrotliEncoder();
        });
    }
    
    public function boot(): void
    {
        // Configuration publishing
    }
}
```

## Performance Considerations

### Benchmarking

Before submitting a PR with performance changes:

```bash
# Run benchmarks
php artisan bench --suite=encoding

# Compare against main
git benchmark main..HEAD
```

Acceptable improvements:
- Encoding: 5%+ faster
- Decoding: 3%+ faster
- Memory: 10%+ reduction

### Profiling

```php
class ProfiledEncoder
{
    public function encode(array $data): string
    {
        $start = microtime(true);
        
        $result = $this->doEncode($data);
        
        $duration = (microtime(true) - $start) * 1000;
        logger()->debug("Encoding took {$duration}ms");
        
        return $result;
    }
}
```

## Documentation

### Adding New Feature

1. Update README.md with example usage
2. Create docs/{feature-name}.md
3. Add to docs/architecture.md
4. Include code examples with input/output

Example:

```markdown
## Feature Name

Brief description.

### Usage

```php
SynapseToon::feature()->execute($data);
```

### Performance

- Throughput: X requests/sec
- Latency: Xms avg
- Token savings: X%
```

### API Documentation

```php
/**
 * Encode data using TOON format
 * 
 * @param array<string, mixed> $data Input data
 * @param int $quality Compression quality 0-11
 * @return string Encoded binary data
 * @throws EncodingException
 */
public function encode(array $data, int $quality = 6): string
```

## Security

### Reporting Issues

Do NOT create public GitHub issues for security vulnerabilities.

Email: security@vinkius.dev

Include:
- Description of vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (optional)

### Security Guidelines

- Never log sensitive data (API keys, tokens)
- Validate all user input
- Use prepared statements for queries
- Keep dependencies updated

```bash
composer audit
composer update
```

## Community

### Getting Help

- GitHub Issues for bugs/feature requests
- Discussions for questions
- Discord for real-time chat

### Code Review

All contributions reviewed for:
- Code quality
- Test coverage
- Performance impact
- Security
- Documentation

### Recognition

Contributors will be added to:
- CONTRIBUTORS.md
- GitHub Contributors page
- Release notes

## License

By contributing, you agree your code is licensed under MIT.

## Questions?

Open an issue or ask in Discussions.

Thanks for contributing to Synapse TOON!
