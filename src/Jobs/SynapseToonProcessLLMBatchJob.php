<?php

namespace VinkiusLabs\SynapseToon\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use VinkiusLabs\SynapseToon\Analytics\SynapseToonMetrics;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;
use VinkiusLabs\SynapseToon\Routing\SynapseToonLLMRouter;

class SynapseToonProcessLLMBatchJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array<int, array<string, mixed>> $requests
     * @param array<string, mixed> $options
     */
    public function __construct(protected array $requests, protected array $options = [])
    {
        if (isset($options['queue'])) {
            $this->onQueue($options['queue']);
        }
    }

    public function handle(SynapseToonEncoder $encoder, SynapseToonMetrics $metrics, SynapseToonLLMRouter $router): void
    {
        $payload = $encoder->encode([
            'requests' => $this->requests,
            'meta' => [
                'count' => count($this->requests),
                'delimiter' => $this->options['delimiter'] ?? "\t",
            ],
        ], ['minify' => true, 'dictionary' => $this->options['dictionary'] ?? []]);

        $context = [
            'connection' => $this->options['connection'] ?? null,
            'batch' => true,
            'batch_size' => count($this->requests),
        ];

        $response = $router->send($payload, $context);

        $metrics->record([
            'endpoint' => 'batch',
            'json_tokens' => $this->options['estimated_json_tokens'] ?? null,
            'toon_tokens' => $encoder->estimatedTokens($payload),
            'savings_percent' => $this->options['savings_percent'] ?? null,
            'batch_size' => count($this->requests),
            'response_status' => $response['status'] ?? 'unknown',
        ]);
    }
}
