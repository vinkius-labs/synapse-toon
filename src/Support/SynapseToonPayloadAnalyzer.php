<?php

namespace VinkiusLabs\SynapseToon\Support;

use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;

class SynapseToonPayloadAnalyzer
{
    public function __construct(protected SynapseToonEncoder $encoder)
    {
    }

    /**
     * @param mixed $payload
     *
     * @return array<string, float|int>
     */
    public function analyze(mixed $payload, ?string $encoded = null): array
    {
        $json = json_encode($this->encoder->normalize($payload)) ?: '';
        $encoded ??= $this->encoder->encode($payload);

        $jsonTokens = $this->encoder->estimatedTokens($json);
        $toonTokens = $this->encoder->estimatedTokens($encoded);
        $savings = $jsonTokens > 0 ? (($jsonTokens - $toonTokens) / $jsonTokens) * 100 : 0;

        return [
            'json_tokens' => $jsonTokens,
            'toon_tokens' => $toonTokens,
            'savings_percent' => $savings,
        ];
    }
}
