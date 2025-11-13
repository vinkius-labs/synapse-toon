<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Compression;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class AcceptEncodingResolver
{
    public function resolve(?string $acceptEncoding): array
    {
        return collect($acceptEncoding ? explode(',', $acceptEncoding) : [])
            ->map(fn (string $encoding) => $this->parseEncodingPreference($encoding))
            ->filter(fn (array $preference) => $preference['quality'] > 0)
            ->flatMap(fn (array $preference) => $this->mapEncodingToAlgorithms($preference['encoding']))
            ->unique()
            ->values()
            ->all();
    }

    protected function parseEncodingPreference(string $encoding): array
    {
        $segments = collect(explode(';', trim($encoding)))->map(fn (string $segment) => trim($segment));

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
