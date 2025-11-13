<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Rag;

class TokenBudget
{
    private int $maxTokens;
    private int $usedTokens;

    public function __construct(int $maxTokens, int $initialUsed = 0)
    {
        $this->maxTokens = max(0, $maxTokens);
        $this->usedTokens = max(0, $initialUsed);
    }

    public function getUsed(): int
    {
        return $this->usedTokens;
    }

    public function remaining(): int
    {
        return max(0, $this->maxTokens - $this->usedTokens);
    }

    public function canFit(int $tokens): bool
    {
        return $this->usedTokens + $tokens <= $this->maxTokens;
    }

    public function consume(int $tokens): void
    {
        $this->usedTokens += max(0, $tokens);
    }

    public function maxCharsForRemaining(): int
    {
        return (int) floor($this->remaining() * 4);
    }
}
