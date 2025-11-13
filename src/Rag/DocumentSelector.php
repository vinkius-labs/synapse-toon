<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Rag;

use Illuminate\Support\Collection;
use VinkiusLabs\SynapseToon\Rag\ContextConfig;
use Illuminate\Support\Str;
use VinkiusLabs\SynapseToon\Rag\TokenBudget;
use VinkiusLabs\SynapseToon\Rag\Summarizer;
use VinkiusLabs\SynapseToon\Encoding\SynapseToonEncoder;

final class DocumentSelector
{
    public function __construct(
        private SynapseToonEncoder $encoder,
        private Summarizer $summarizer,
    ) {
    }

    /**
     * Select documents respecting token and snippet constraints.
     * Returns a tuple of selected docs and the used tokens count.
     *
     * @return array{0: array, 1:int}
     */
    public function select(Collection $documents, ContextConfig $config, int $initialTokens = 0): array
    {
        $budget = new TokenBudget($config->maxTokens, $initialTokens);
        $limit = $config->limit;
        $maxSnippet = $config->maxSnippet;

        $selected = [];
        $selectedCount = 0;

        $documents
            ->filter(fn ($doc) => ($doc['content'] ?? '') !== '')
            ->each(function ($doc) use (&$selected, &$selectedCount, $limit, $maxSnippet, $config, $budget) {
                if ($this->shouldStopSelection($selectedCount, $budget, $limit)) {
                    return false; // stop iterating
                }

                $result = $this->processDocument($doc, $selected, $selectedCount, $budget, $maxSnippet, $config);
                if ($result === false) {
                    return false; // budget exhausted — break
                }
            });

        return [$selected, $budget->getUsed()];
    }

    private function shouldStopSelection(int $selectedCount, TokenBudget $budget, int $limit): bool
    {
        return $selectedCount >= $limit || $budget->remaining() <= 0;
    }

    /**
     * Attempt to process a single document for selection.
     * Returns false if iteration should stop (budget exhausted), null otherwise.
     */
    private function processDocument(array $doc, array &$selected, int &$selectedCount, TokenBudget $budget, int $maxSnippet, ContextConfig $config): ?bool
    {
        $content = (string) ($doc['content'] ?? '');

        return $this->processNonEmptyDocument($doc, $selected, $selectedCount, $budget, $maxSnippet, $config, $content);
    }

    /**
     * Process a document with non-empty content.
     */
    private function processNonEmptyDocument(array $doc, array &$selected, int &$selectedCount, TokenBudget $budget, int $maxSnippet, ContextConfig $config, string $content): ?bool
    {
        $docTokens = $this->tokens($content);
        $fittingResult = $budget->canFit($docTokens)
            ? ['content' => $content, 'tokens' => $docTokens]
            : $this->handleFitting($content, $budget, $config);

        if ($fittingResult === false || $fittingResult === null) {
            return $fittingResult;
        }

        $finalContent = $this->finalSnippet($fittingResult['content'], $maxSnippet);
        $finalTokens = $this->tokens($finalContent);

        return $budget->canFit($finalTokens) ? $this->finalizeDocument($doc, $selected, $selectedCount, $budget, $finalContent, $finalTokens) : null;
    }

    /**
     * Finalize document processing after fitting.
     */
    private function finalizeDocument(array $doc, array &$selected, int &$selectedCount, TokenBudget $budget, string $finalContent, int $finalTokens): null
    {
        $selected[] = [
            'id' => $doc['id'],
            'content' => $finalContent,
            'score' => $doc['score'],
            'metadata' => $doc['metadata'],
            'tokens' => $finalTokens,
        ];

        $selectedCount++;
        $budget->consume($finalTokens);

        return null;
    }

    /**
     * Handle content fitting when it doesn't fit the budget.
     * Returns adjusted content and tokens, or false if budget exhausted, or null to skip.
     */
    private function handleFitting(string $content, TokenBudget $budget, ContextConfig $config): array|false|null
    {
        $fitted = $this->reduceContentToFitBudget($content, $budget, $config);

        return $fitted === null ? ($budget->remaining() <= 0 ? false : null) : ['content' => $fitted, 'tokens' => $this->tokens($fitted)];
    }

    private function reduceContentToFitBudget(string $content, TokenBudget $budget, ContextConfig $config): ?string
    {
        $remaining = $budget->remaining();
        $summary = $config->summarize ? $this->summarizer->summarize($config->summarizer, $content, $remaining) : null;
        $maxChars = $budget->maxCharsForRemaining();
        $truncated = $maxChars > 0 ? Str::substr($content, 0, $maxChars) : '';

        return match (true) {
            $remaining <= 0 => null,
            $summary && $this->tokens($summary) <= $remaining => $summary,
            $maxChars <= 0 => null,
            $this->tokens($truncated) <= $remaining => $truncated,
            default => null,
        };
    }

    private function finalSnippet(string $content, int $maxSnippet): string
    {
        return Str::substr($content, 0, min($maxSnippet, Str::length($content)));
    }

    private function tokens(string $content): int
    {
        return $this->encoder->estimatedTokens($content);
    }

    // Summarization delegated to Summarizer service
}
