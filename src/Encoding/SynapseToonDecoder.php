<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Encoding;

class SynapseToonDecoder
{
    public function __construct(protected SynapseToonEncoder $encoder)
    {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function decode(string $payload, array $options = []): mixed
    {
        return $this->encoder->decode($payload, $options);
    }
}
