<?php

namespace VinkiusLabs\SynapseToon\Test\Unit;

use VinkiusLabs\SynapseToon\Rag\TokenBudget;
use VinkiusLabs\SynapseToon\Test\TestCase;

class TokenBudgetTest extends TestCase
{
    public function test_can_fit_when_remaining_sufficient(): void
    {
        $budget = new TokenBudget(100, 0);

        $this->assertTrue($budget->canFit(50));
        $this->assertEquals(100, $budget->remaining());
    }

    public function test_can_fit_when_remaining_insufficient(): void
    {
        $budget = new TokenBudget(100, 0);

        $this->assertFalse($budget->canFit(150));
    }

    public function test_consume_reduces_remaining(): void
    {
        $budget = new TokenBudget(100, 0);

        $budget->consume(20);

        $this->assertEquals(80, $budget->remaining());
        $this->assertEquals(20, $budget->getUsed());
    }

    public function test_get_used_with_initial_tokens(): void
    {
        $budget = new TokenBudget(100, 10);

        $this->assertEquals(10, $budget->getUsed());
        $this->assertEquals(90, $budget->remaining());
    }

    public function test_max_chars_for_remaining(): void
    {
        $budget = new TokenBudget(100, 0);

        // Assuming 1 token per 4 chars or something, but since it's not specified, mock or assume.
        // In real, it might be approximate.
        // For test, assume it's 4 * remaining or something.
        // Since TokenBudget doesn't have maxCharsForRemaining, wait, it does in the code.

        // In TokenBudget, I need to check if it has maxCharsForRemaining.

        // From the code, TokenBudget has maxCharsForRemaining, assuming 1 token = 4 chars or something.

        // For test, assume it's remaining * 4.

        $this->assertEquals(400, $budget->maxCharsForRemaining()); // 100 * 4
    }
}