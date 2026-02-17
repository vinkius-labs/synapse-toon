<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Analytics\Drivers;

use VinkiusLabs\SynapseToon\Contracts\SynapseToonMetricsDriver;

class SynapseToonNullMetricsDriver implements SynapseToonMetricsDriver
{
    public function record(array $payload): void
    {
        // Intentionally left blank.
    }
}
