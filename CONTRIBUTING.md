# Contributing to Synapse TOON

Thank you for your interest in contributing to Synapse TOON! This guide will help you get started.

## Code of Conduct

This project adheres to a [Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code.

## How to Contribute

### Reporting Bugs

Before creating a bug report, please search [existing issues](https://github.com/vinkius-labs/synapse-toon/issues) to avoid duplicates.

When filing a bug, include:

- **PHP version** (`php -v`)
- **Laravel version** (`composer show laravel/framework | grep versions`)
- **Synapse TOON version** (`composer show vinkius-labs/synapse-toon | grep versions`)
- **Minimal reproduction** — the smallest code sample that triggers the bug
- **Expected vs. actual behavior**
- **Stack trace** (if applicable)

### Suggesting Features

Open a [feature request](https://github.com/vinkius-labs/synapse-toon/issues/new?template=feature_request.md) with:

- A clear description of the problem you are trying to solve
- Your proposed solution (if any)
- Alternatives you have considered

### Submitting Pull Requests

1. Fork the repository and create your branch from `main`.
2. Install dependencies and run the test suite (see below).
3. Write tests that cover your changes.
4. Ensure all tests pass and code style checks succeed.
5. Write a clear commit message (see [Commit Messages](#commit-messages)).
6. Open a pull request against `main`.

## Development Setup

### Prerequisites

- PHP 8.2 or 8.3
- Composer 2.x
- Docker (recommended for consistent environments)

### Local Setup

```bash
git clone https://github.com/vinkius-labs/synapse-toon.git
cd synapse-toon
composer install
```

### Running Tests

**With Docker (recommended):**

```bash
docker compose build
docker compose run --rm app bash -c "composer install --no-interaction && vendor/bin/phpunit"
```

**Without Docker:**

```bash
vendor/bin/phpunit
```

### Code Style

This project follows PSR-12 coding standards. Check and fix style issues with:

```bash
# Check style
vendor/bin/phpcs -p --standard=PSR2 src tests

# Auto-fix style
vendor/bin/phpcbf -p --standard=PSR2 src tests

# Or use Laravel Pint
vendor/bin/pint
```

## Coding Standards

### PHP

- **Minimum PHP 8.2** — use modern PHP features (named arguments, match expressions, enums, fibers where appropriate).
- **`declare(strict_types=1)`** — required in every PHP file.
- **Type declarations** — all parameters, return types, and properties must be typed.
- **Readonly properties** — prefer `readonly` for immutable state.

### Naming Conventions

All public classes, interfaces, and traits **must** use the `SynapseToon` prefix:

```php
// Correct
class SynapseToonEncoder { }
interface SynapseToonCompressorContract { }

// Incorrect
class Encoder { }
interface CompressorContract { }
```

This convention ensures zero namespace collisions in application code and makes grep/search trivial.

### Architecture Guidelines

- **Contracts first** — define an interface in `src/Contracts/` before implementing.
- **Driver pattern** — use the Manager + Driver pattern for swappable implementations.
- **Configuration** — all tunables must live in `config/synapse-toon.php` with `env()` fallbacks.
- **No side effects in constructors** — constructors should only assign dependencies.

### Testing

- Use [Orchestra Testbench](https://github.com/orchestral/testbench) for feature tests.
- Place feature tests in `tests/Feature/`, unit tests in `tests/Unit/`.
- Test names should read as sentences: `test_middleware_encodes_and_compresses_response`.
- Avoid mocking the class under test.

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <short summary>

<body>

<footer>
```

### Types

| Type | Purpose |
|:-----|:--------|
| `feat` | New feature |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `style` | Code style (no logic change) |
| `refactor` | Code change that neither fixes a bug nor adds a feature |
| `perf` | Performance improvement |
| `test` | Adding or correcting tests |
| `chore` | Build process, CI, or tooling changes |

### Examples

```
feat(compression): add minimum size threshold for compression

Skip compression for payloads smaller than the configured minimum_size
(default: 128 bytes). This avoids the overhead of compressing tiny
payloads where the compression ratio would be negligible.

Closes #42
```

```
fix(middleware): prevent double-encoding of pre-encoded responses

Responses already carrying the TOON content-type are no longer
re-encoded by the compression middleware.
```

## Pull Request Guidelines

- **One concern per PR** — avoid mixing unrelated changes.
- **Include tests** — PRs without test coverage will not be merged.
- **Update documentation** — if your change affects public API or behavior, update the relevant file in `docs/`.
- **Keep the diff small** — large PRs are harder to review. If a change is big, consider splitting it into smaller PRs.
- **CI must pass** — all status checks must be green before merging.

## Release Process

Releases follow [Semantic Versioning](https://semver.org/):

- **MAJOR** — incompatible API changes
- **MINOR** — new features, backward-compatible
- **PATCH** — backward-compatible bug fixes

Releases are published to [Packagist](https://packagist.org/packages/vinkius-labs/synapse-toon) via GitHub tags.

## Getting Help

- **Questions** — open a [Discussion](https://github.com/vinkius-labs/synapse-toon/discussions)
- **Bugs** — open an [Issue](https://github.com/vinkius-labs/synapse-toon/issues)
- **Security** — see [SECURITY.md](SECURITY.md)

---

Thank you for helping make Synapse TOON better!
