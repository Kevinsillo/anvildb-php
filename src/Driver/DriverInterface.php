<?php

declare(strict_types=1);

namespace AnvilDb\Driver;

/**
 * Contract for AnvilDB engine drivers.
 *
 * All data payloads (documents, queries, schemas) are passed as JSON strings
 * to keep both FFI and Process drivers symmetric.
 */
interface DriverInterface
{
    // -----------------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------------

    /**
     * Open a database at the given path.
     *
     * @param string      $dataPath      Filesystem path to the database directory
     * @param string|null $encryptionKey 64-char hex string for AES-256-GCM, or null
     */
    public function open(string $dataPath, ?string $encryptionKey = null): void;

    /**
     * Close the database handle without flushing.
     */
    public function close(): void;

    /**
     * Gracefully shut down: flush pending writes, then close.
     */
    public function shutdown(): void;

    // -----------------------------------------------------------------------
    // Collections
    // -----------------------------------------------------------------------

    /**
     * Create a collection explicitly.
     */
    public function createCollection(string $name): void;

    /**
     * Drop a collection and all its data.
     */
    public function dropCollection(string $name): void;

    /**
     * List all collection names.
     *
     * @return string JSON array of collection names
     */
    public function listCollections(): string;

    // -----------------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------------

    /**
     * Insert a single document.
     *
     * @param string $collection Collection name
     * @param string $jsonDoc    JSON-encoded document
     *
     * @return string JSON-encoded inserted document (with generated id)
     */
    public function insert(string $collection, string $jsonDoc): string;

    /**
     * Find a document by its ID.
     *
     * @return string|null JSON-encoded document, or null if not found
     */
    public function findById(string $collection, string $id): ?string;

    /**
     * Update a document by its ID.
     */
    public function update(string $collection, string $id, string $jsonDoc): void;

    /**
     * Delete a document by its ID.
     */
    public function delete(string $collection, string $id): void;

    /**
     * Insert multiple documents in a single operation.
     *
     * @param string $collection Collection name
     * @param string $jsonDocs   JSON-encoded array of documents
     *
     * @return string JSON-encoded array of inserted documents
     */
    public function bulkInsert(string $collection, string $jsonDocs): string;

    // -----------------------------------------------------------------------
    // Queries
    // -----------------------------------------------------------------------

    /**
     * Execute a query from a JSON spec.
     *
     * @param string $jsonQuerySpec JSON-encoded query specification
     *
     * @return string JSON-encoded array of results
     */
    public function query(string $jsonQuerySpec): string;

    /**
     * Count documents matching an optional filter.
     *
     * @param string      $collection Collection name
     * @param string|null $jsonFilter JSON-encoded filter array, or null for all
     *
     * @return int Number of matching documents
     */
    public function count(string $collection, ?string $jsonFilter = null): int;

    // -----------------------------------------------------------------------
    // Indexes
    // -----------------------------------------------------------------------

    /**
     * Create an index on a field.
     *
     * @param string $indexType 'hash', 'unique', or 'range'
     */
    public function createIndex(string $collection, string $field, string $indexType = 'hash'): void;

    /**
     * Drop an index on a field.
     */
    public function dropIndex(string $collection, string $field): void;

    // -----------------------------------------------------------------------
    // Schema
    // -----------------------------------------------------------------------

    /**
     * Set a validation schema for a collection.
     *
     * @param string $jsonSchema JSON-encoded schema definition
     */
    public function setSchema(string $collection, string $jsonSchema): void;

    // -----------------------------------------------------------------------
    // Buffer control
    // -----------------------------------------------------------------------

    /**
     * Flush all pending buffered writes to disk.
     */
    public function flush(): void;

    /**
     * Flush buffered writes for a single collection.
     */
    public function flushCollection(string $collection): void;

    /**
     * Configure write buffer thresholds.
     */
    public function configureBuffer(int $maxDocs, int $flushIntervalSecs): void;

    // -----------------------------------------------------------------------
    // Encryption
    // -----------------------------------------------------------------------

    /**
     * Encrypt an unencrypted database.
     *
     * @param string $key 64-char hex string
     */
    public function encrypt(string $key): void;

    /**
     * Decrypt an encrypted database.
     *
     * @param string $key 64-char hex string
     */
    public function decrypt(string $key): void;

    // -----------------------------------------------------------------------
    // Cache
    // -----------------------------------------------------------------------

    /**
     * Clear the in-memory query cache.
     */
    public function clearCache(): void;

    // -----------------------------------------------------------------------
    // Errors & Warnings
    // -----------------------------------------------------------------------

    /**
     * Get warnings from the last operation.
     *
     * @return array<string> Warning messages
     */
    public function getWarnings(): array;
}
