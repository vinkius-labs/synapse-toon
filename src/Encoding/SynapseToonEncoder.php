<?php

namespace VinkiusLabs\SynapseToon\Encoding;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use JsonSerializable;

class SynapseToonEncoder
{
    public function __construct(protected ConfigRepository $config)
    {
    }

    /**
     * @param mixed $payload
     * @param array<string, mixed> $options
     */
    public function encode(mixed $payload, array $options = []): string
    {
        $dictionary = $options['dictionary'] ?? $this->config->get('synapse-toon.encoding.dictionary', []);
        $preserveZeroFraction = $options['preserve_zero_fraction'] ?? $this->config->get('synapse-toon.encoding.preserve_zero_fraction', false);

        $mapped = $this->mapDictionary($this->normalize($payload), $dictionary, true);

        $json = json_encode($mapped, $this->jsonOptions($preserveZeroFraction));

        if ($json === false) {
            throw new \RuntimeException('Unable to encode payload to Synapse TOON.');
        }

        return $json;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return mixed
     */
    public function decode(string $payload, array $options = []): mixed
    {
        $dictionary = $options['dictionary'] ?? $this->config->get('synapse-toon.encoding.dictionary', []);
        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid Synapse TOON payload: ' . json_last_error_msg());
        }

        return $this->mapDictionary($decoded, $dictionary, false);
    }

    /**
     * Encode a streaming chunk applying TOON heuristics.
     *
     * @param array<string, mixed> $options
     */
    public function encodeChunk(string $chunk, array $options = []): string
    {
        $delimiter = $options['delimiter'] ?? $this->config->get('synapse-toon.encoding.chunk_delimiter', PHP_EOL);
        $maxSize = $options['max_size'] ?? $this->config->get('synapse-toon.encoding.max_chunk_size', 4096);

        $normalized = Str::of($chunk)->squish();

        if ($maxSize > 0 && $normalized->length() > $maxSize) {
            $normalized = $normalized->substr(0, $maxSize - 3)->append('…');
        }

        return $normalized->append($delimiter)->toString();
    }

    /**
     * Approximate complexity score between 0 and 1 based on payload structure.
     */
    public function complexityScore(mixed $payload): float
    {
        $normalized = $this->normalize($payload);
        $json = json_encode($normalized) ?: '';
        $length = max(strlen($json), 1);
        $depthWeight = $this->depth($normalized) / 10;
        $arrayWeight = $this->countNumericArrays($normalized) / 20;

        return min(1.0, ($length / 5000) + $depthWeight + $arrayWeight);
    }

    public function estimatedTokens(mixed $payload): int
    {
        if (is_string($payload)) {
            $content = $payload;
        } else {
            $normalized = $this->normalize($payload);
            $content = json_encode($normalized) ?: '';
        }

        return (int) ceil(strlen($content) / 4); // Approximate 1 token ~ 4 chars
    }

    /**
     * @return array<string, mixed> | array<int, mixed>
     */
    public function normalize(mixed $payload): array
    {
        return match (true) {
            $payload instanceof Arrayable => $payload->toArray(),
            $payload instanceof Jsonable => json_decode($payload->toJson(), true, 512, JSON_THROW_ON_ERROR),
            $payload instanceof JsonSerializable => (array) $payload->jsonSerialize(),
            is_string($payload) => $this->normalizeString($payload),
            is_object($payload) => json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
            is_array($payload) => $payload,
            default => ['value' => $payload],
        };
    }

    /**
     * @param array<string, mixed> | array<int, mixed> $data
     * @param array<string, string> $dictionary
     */
    protected function mapDictionary(array $data, array $dictionary, bool $encode): array
    {
        if (empty($dictionary)) {
            return $data;
        }

        $mapping = Collection::make($encode ? $dictionary : array_flip($dictionary));

        return Collection::make($data)
            ->mapWithKeys(function ($value, $key) use ($dictionary, $encode, $mapping) {
                $translated = $mapping->get($key, $key);

                return [
                    $translated => is_array($value)
                        ? $this->mapDictionary($value, $dictionary, $encode)
                        : $value,
                ];
            })
            ->all();
    }

    private function jsonOptions(bool $preserveZeroFraction): int
    {
        $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        return $preserveZeroFraction
            ? $options | JSON_PRESERVE_ZERO_FRACTION
            : $options;
    }

    private function depth(mixed $data, int $level = 0): int
    {
        if (! is_array($data)) {
            return $level;
        }

        $nextLevel = $level + 1;

        return Collection::make($data)
            ->reduce(fn (int $carry, $value) => max($carry, $this->depth($value, $nextLevel)), $nextLevel);
    }

    private function countNumericArrays(mixed $data): int
    {
        if (! is_array($data)) {
            return 0;
        }

        $initial = Arr::isAssoc($data) ? 0 : 1;

        return Collection::make($data)
            ->reduce(fn (int $carry, $value) => $carry + $this->countNumericArrays($value), $initial);
    }

    private function normalizeString(string $payload): array
    {
        $decoded = json_decode($payload, true);

        return json_last_error() === JSON_ERROR_NONE
            ? $decoded
            : ['value' => $payload];
    }
}
