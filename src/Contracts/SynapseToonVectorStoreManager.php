<?php

namespace VinkiusLabs\SynapseToon\Contracts;

interface SynapseToonVectorStoreManager
{
    /**
     * Store a document in the vector store. Drivers that do not support
     * write operations may ignore this method.
     */
    public function store(string $id, string $content, array $metadata = []): void;

    /**
     * Remove a document from the vector store. Drivers that do not support
     * write operations may ignore this method.
     */
    public function delete(string $id): void;
}
