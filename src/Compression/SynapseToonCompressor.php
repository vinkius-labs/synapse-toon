<?php

namespace VinkiusLabs\SynapseToon\Compression;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SynapseToonCompressor
{
    public function __construct(protected ConfigRepository $config)
    {
    }

    /**
     * Compress data using the best available algorithm based on request headers and configuration.
     *
     * @param array<string, mixed> $options
     *
     * @return array{body: string, encoding: string|null, algorithm: string|null}
     */
    public function compress(string $payload, ?string $acceptEncoding = null, array $options = []): array
    {
        if (! $this->config->get('synapse-toon.compression.enabled', true)) {
            return ['body' => $payload, 'encoding' => null, 'algorithm' => 'none'];
        }

        $resolved = collect($this->resolveAlgorithms($acceptEncoding));
        $preferredAlgorithm = $this->config->get('synapse-toon.compression.prefer', 'brotli');

        if ($resolved->isEmpty()) {
            if ($acceptEncoding === null || trim($acceptEncoding) === '') {
                $resolved = $resolved->prepend($preferredAlgorithm);
            }
        } else {
            if ($preferredAlgorithm) {
                $resolved = $resolved->prepend($preferredAlgorithm);
            }
        }

        $preferred = $resolved
            ->unique()
            ->map(function (string $algorithm) use ($payload, $options) {
                return $this->attemptCompression($algorithm, $payload, $options);
            })
            ->first(function (?array $result) {
                return $result !== null;
            });

        return $preferred ?? ['body' => $payload, 'encoding' => null, 'algorithm' => 'none'];
    }

    protected function compressBrotli(string $payload, array $options): ?string
    {
        $quality = $options['quality'] ?? $this->config->get('synapse-toon.compression.brotli.quality', 8);
        $mode = $options['mode'] ?? $this->config->get('synapse-toon.compression.brotli.mode', 'generic');

        if (! \function_exists('brotli_compress')) {
            return null;
        }

        $modeValue = match (is_string($mode) ? strtolower($mode) : $mode) {
            'text', 'html' => defined('BROTLI_MODE_TEXT') ? constant('BROTLI_MODE_TEXT') : 1,
            'font' => defined('BROTLI_MODE_FONT') ? constant('BROTLI_MODE_FONT') : 2,
            default => defined('BROTLI_MODE_GENERIC') ? constant('BROTLI_MODE_GENERIC') : 0,
        };

        $result = \call_user_func('brotli_compress', $payload, (int) $quality, $modeValue);

        return $result === false ? null : $result;
    }

    protected function compressGzip(string $payload, array $options): ?string
    {
        $level = $options['level'] ?? $this->config->get('synapse-toon.compression.gzip.level', -1);

        if (! \function_exists('gzencode')) {
            return null;
        }

        $compressed = \gzencode($payload, (int) $level);

        return $compressed === false ? null : $compressed;
    }

    protected function compressDeflate(string $payload, array $options): ?string
    {
        $level = $options['level'] ?? $this->config->get('synapse-toon.compression.deflate.level', -1);

        if (! \function_exists('deflate_init')) {
            return null;
        }

        $resource = \deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => (int) $level]);

        if ($resource === false) {
            return null;
        }

        $compressed = \deflate_add($resource, $payload, ZLIB_FINISH);

        return $compressed === false ? null : $compressed;
    }

    protected function resolveAlgorithms(?string $acceptEncoding): array
    {
        return collect($acceptEncoding ? explode(',', $acceptEncoding) : [])
            ->map(function (string $encoding) {
                return $this->parseEncodingPreference($encoding);
            })
            ->filter(function (array $preference) {
                return $preference['quality'] > 0;
            })
            ->flatMap(function (array $preference) {
                return $this->mapEncodingToAlgorithms($preference['encoding']);
            })
            ->unique()
            ->values()
            ->all();
    }

    protected function encodingForAlgorithm(string $algorithm): ?string
    {
        return match ($algorithm) {
            'brotli' => 'br',
            'gzip' => 'gzip',
            'deflate' => 'deflate',
            default => null,
        };
    }

    protected function attemptCompression(string $algorithm, string $payload, array $options): ?array
    {
        $method = 'compress' . ucfirst($algorithm);

        if (! method_exists($this, $method)) {
            return null;
        }

        try {
            $body = $this->{$method}($payload, $options);
        } catch (\Throwable) {
            return null;
        }

        if ($body === null) {
            return null;
        }

        return [
            'body' => $body,
            'encoding' => $this->encodingForAlgorithm($algorithm),
            'algorithm' => $algorithm,
        ];
    }

    protected function parseEncodingPreference(string $encoding): array
    {
        $segments = collect(explode(';', trim($encoding)))->map(function (string $segment) {
            return trim($segment);
        });

        $value = Str::lower($segments->shift() ?? '');
        $quality = $segments->first(fn (string $segment) => Str::startsWith($segment, 'q='));

        $qualityValue = $quality ? (float) substr($quality, 2) : 1.0;

        return [
            'encoding' => $value,
            'quality' => $qualityValue,
        ];
    }

    protected function mapEncodingToAlgorithms(string $encoding): array
    {
        return match ($encoding) {
            'br', 'brotli' => ['brotli'],
            'gzip', 'x-gzip' => ['gzip'],
            'deflate' => ['deflate'],
            '*' => ['brotli', 'gzip', 'deflate'],
            'identity' => [],
            default => [],
        };
    }
}
