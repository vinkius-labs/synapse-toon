<?php

return [
    'defaults' => [
        'enabled' => true,
        'content_type' => 'application/x-synapse-toon',
        'quality' => 80,
    ],

    'encoding' => [
        'minify' => true,
        'preserve_zero_fraction' => false,
        'dictionary' => [],
        'chunk_delimiter' => PHP_EOL,
        'max_chunk_size' => 4096,
    ],

    'compression' => [
        'enabled' => true,
        'prefer' => 'brotli', // brotli, gzip, deflate, none
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

    'http3' => [
        'enabled' => true,
        'optimize_headers' => true,
        'prefer_compression' => 'brotli',
        'alt_svc_header' => 'h3=":443"',
    ],

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

    'rag' => [
        'enabled' => true,
        'driver' => env('SYNAPSE_TOON_RAG_DRIVER', 'null'),
        'drivers' => [
            'null' => [],
        ],
        'context' => [
            'limit' => 3,
            'max_snippet_length' => 200,
        ],
    ],

    'batch' => [
        'enabled' => true,
        'size' => 50,
        'delimiter' => "\t",
        'llm_connection' => env('SYNAPSE_TOON_LLM_CONNECTION', 'default'),
    ],

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

    'graphql' => [
        'enabled' => true,
        'header' => 'X-Synapse-TOON',
    ],

    'edge_cache' => [
        'enabled' => true,
        'tags' => ['api', 'toon'],
        'ttl' => 3600,
        'store' => null,
    ],

    'octane' => [
        'enabled' => true,
        'preload' => [
            \VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder::class,
            \VinkiusLabs\SynapseToon\Compression\SynapseToonCompressor::class,
            \VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics::class,
        ],
    ],
];
