<?php

declare(strict_types=1);

namespace VinkiusLabs\SynapseToon\Test\Unit;

use VinkiusLabs\SynapseToon\Rag\Drivers\InMemoryVectorStore;
use VinkiusLabs\SynapseToon\Test\TestCase;

class InMemoryVectorStoreTest extends TestCase
{
    public function test_search_returns_seeded_documents(): void
    {
        $store = new InMemoryVectorStore([
            ['id' => 'doc-1', 'content' => 'Laravel authentication guide', 'score' => 0.9],
            ['id' => 'doc-2', 'content' => 'PHP testing best practices', 'score' => 0.7],
            ['id' => 'doc-3', 'content' => 'API design patterns', 'score' => 0.5],
        ]);

        $results = $store->search('laravel', 2);

        $this->assertCount(2, $results);
        // 'laravel' appears in doc-1, so doc-1 should have boosted score
        $this->assertSame('doc-1', $results->first()['id']);
    }

    public function test_search_limits_results(): void
    {
        $store = new InMemoryVectorStore([
            ['id' => 'a', 'content' => 'one', 'score' => 1.0],
            ['id' => 'b', 'content' => 'two', 'score' => 0.9],
            ['id' => 'c', 'content' => 'three', 'score' => 0.8],
        ]);

        $results = $store->search('', 1);
        $this->assertCount(1, $results);
    }

    public function test_store_adds_document(): void
    {
        $store = new InMemoryVectorStore();

        $store->store('new-doc', 'New document content', ['category' => 'test']);

        $results = $store->search('new document');
        $this->assertCount(1, $results);
        $this->assertSame('new-doc', $results->first()['id']);
        $this->assertSame('New document content', $results->first()['content']);
    }

    public function test_store_overwrites_existing_document(): void
    {
        $store = new InMemoryVectorStore([
            ['id' => 'doc-1', 'content' => 'Original content', 'score' => 0.5],
        ]);

        $store->store('doc-1', 'Updated content', ['version' => 2]);

        $results = $store->search('updated');
        $this->assertSame('doc-1', $results->first()['id']);
        $this->assertSame('Updated content', $results->first()['content']);
    }

    public function test_delete_removes_document(): void
    {
        $store = new InMemoryVectorStore([
            ['id' => 'doc-1', 'content' => 'First', 'score' => 0.9],
            ['id' => 'doc-2', 'content' => 'Second', 'score' => 0.8],
        ]);

        $store->delete('doc-1');

        $results = $store->search('', 10);
        $this->assertCount(1, $results);
        $this->assertSame('doc-2', $results->first()['id']);
    }

    public function test_delete_nonexistent_is_noop(): void
    {
        $store = new InMemoryVectorStore([
            ['id' => 'doc-1', 'content' => 'Only doc', 'score' => 0.9],
        ]);

        $store->delete('nonexistent');

        $results = $store->search('', 10);
        $this->assertCount(1, $results);
    }

    public function test_search_boosts_matching_documents(): void
    {
        $store = new InMemoryVectorStore([
            ['id' => 'low-match', 'content' => 'Something about PHP', 'score' => 0.9],
            ['id' => 'high-match', 'content' => 'PHP testing framework', 'score' => 0.3],
        ]);

        $results = $store->search('php testing', 2);

        // 'high-match' contains 'php testing', gets +0.5 boost → 0.8
        // 'low-match' contains 'php', gets +0.5 boost → 1.4
        // Both should be returned, sorted by score desc
        $this->assertCount(2, $results);
    }

    public function test_empty_store_returns_empty_collection(): void
    {
        $store = new InMemoryVectorStore();

        $results = $store->search('anything');
        $this->assertCount(0, $results);
    }

    public function test_seed_without_id_generates_uuid(): void
    {
        $store = new InMemoryVectorStore([
            ['content' => 'Document without explicit ID', 'score' => 0.5],
        ]);

        $results = $store->search('document');
        $this->assertCount(1, $results);
        $this->assertNotEmpty($results->first()['id']);
    }

    public function test_metadata_is_preserved(): void
    {
        $store = new InMemoryVectorStore();
        $store->store('doc-1', 'Test content', ['category' => 'tutorial', 'lang' => 'en']);

        $results = $store->search('test');
        $this->assertSame(['category' => 'tutorial', 'lang' => 'en'], $results->first()['metadata']);
    }
}
