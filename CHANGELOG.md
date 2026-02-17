# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-02-17

### Added

- **SynapseToonEncoder / SynapseToonDecoder** — Lossless TOON codec with dictionary support and entropy-aware heuristics for 25–45% token reduction
- **SynapseToonCompressor** — Adaptive Brotli, Gzip, and Deflate compression with automatic `Accept-Encoding` negotiation
- **SynapseToonSseStreamer** — Server-Sent Events streaming with zero-copy chunking and buffer flush guardrails
- **SynapseToonEdgeCache** — Encode-once edge caching helper optimized for Redis and Laravel Octane workloads
- **SynapseToonMetrics** — Driver-agnostic metrics system with Log, Prometheus, and Datadog drivers
- **SynapseToonProcessLLMBatchJob** — Queue-friendly batch encoder supporting up to 100 prompts per dispatch
- **SynapseToonLLMRouter** — Complexity-aware model router with pluggable LLM client implementations
- **SynapseToonRagService** — Vector-store abstraction with snippet thresholds and metadata braiding
- **SynapseToonGraphQLAdapter** — Lighthouse / Rebing GraphQL pipeline integration with TOON encoding
- **SynapseToonPayloadAnalyzer** — Token analytics and savings calculator for middleware and dashboards
- **SynapseToonCompressionMiddleware** — HTTP middleware for automatic response compression
- **SynapseToonHttp3Middleware** — HTTP/3 detection and header optimization middleware
- Response macros: `response()->synapseToon()` and `response()->synapseToonStream()`
- Laravel Octane preloading support
- Comprehensive documentation in `/docs`
- Full test suite with 25+ tests covering all components

### Security

- All user inputs are validated before processing
- No sensitive data is logged by default

[Unreleased]: https://github.com/vinkius-labs/synapse-toon/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/vinkius-labs/synapse-toon/releases/tag/v1.0.0
