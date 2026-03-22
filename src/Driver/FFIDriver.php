<?php

declare(strict_types=1);

namespace AnvilDb\Driver;

use AnvilDb\Exception\AnvilDbException;
use AnvilDb\Exception\FFIException;
use AnvilDb\FFI\Bridge;

/**
 * Driver that communicates with the Rust engine via PHP's FFI extension.
 */
class FFIDriver implements DriverInterface
{
    private \FFI $ffi;
    private ?\FFI\CData $handle = null;

    public function __construct()
    {
        $this->ffi = Bridge::get();
    }

    // -----------------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------------

    public function open(string $dataPath, ?string $encryptionKey = null): void
    {
        $handle = $this->ffi->anvildb_open($dataPath, $encryptionKey);

        if ($handle === null) {
            throw new FFIException('Failed to open AnvilDb engine');
        }

        $this->handle = $handle;
    }

    public function close(): void
    {
        if ($this->handle !== null) {
            $this->ffi->anvildb_close($this->handle);
            $this->handle = null;
        }
    }

    public function shutdown(): void
    {
        if ($this->handle !== null) {
            $this->ffi->anvildb_shutdown($this->handle);
            $this->handle = null;
        }
    }

    // -----------------------------------------------------------------------
    // Collections
    // -----------------------------------------------------------------------

    public function createCollection(string $name): void
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_create_collection($this->handle, $name);

        if ($result < 0) {
            throw new AnvilDbException('Failed to create collection: ' . $this->getLastError());
        }
    }

    public function dropCollection(string $name): void
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_drop_collection($this->handle, $name);

        if ($result < 0) {
            throw new AnvilDbException('Failed to drop collection: ' . $this->getLastError());
        }
    }

    public function listCollections(): string
    {
        $this->ensureOpen();
        $resultPtr = $this->ffi->anvildb_list_collections($this->handle);

        if ($resultPtr === null) {
            return '[]';
        }

        return $this->ptrToString($resultPtr);
    }

    // -----------------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------------

    public function insert(string $collection, string $jsonDoc): string
    {
        $this->ensureOpen();
        $resultPtr = $this->ffi->anvildb_insert($this->handle, $collection, $jsonDoc);

        if ($resultPtr === null) {
            throw new AnvilDbException($this->getLastError());
        }

        return $this->ptrToString($resultPtr);
    }

    public function findById(string $collection, string $id): ?string
    {
        $this->ensureOpen();
        $resultPtr = $this->ffi->anvildb_find_by_id($this->handle, $collection, $id);

        if ($resultPtr === null) {
            return null;
        }

        return $this->ptrToString($resultPtr);
    }

    public function update(string $collection, string $id, string $jsonDoc): void
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_update($this->handle, $collection, $id, $jsonDoc);

        if ($result < 0) {
            throw new AnvilDbException($this->getLastError());
        }
    }

    public function delete(string $collection, string $id): void
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_delete($this->handle, $collection, $id);

        if ($result < 0) {
            throw new AnvilDbException($this->getLastError());
        }
    }

    public function bulkInsert(string $collection, string $jsonDocs): string
    {
        $this->ensureOpen();
        $resultPtr = $this->ffi->anvildb_bulk_insert($this->handle, $collection, $jsonDocs);

        if ($resultPtr === null) {
            throw new AnvilDbException($this->getLastError());
        }

        return $this->ptrToString($resultPtr);
    }

    // -----------------------------------------------------------------------
    // Queries
    // -----------------------------------------------------------------------

    public function query(string $jsonQuerySpec): string
    {
        $this->ensureOpen();
        $resultPtr = $this->ffi->anvildb_query($this->handle, $jsonQuerySpec);

        if ($resultPtr === null) {
            throw new AnvilDbException($this->getLastError());
        }

        return $this->ptrToString($resultPtr);
    }

    public function count(string $collection, ?string $jsonFilter = null): int
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_count($this->handle, $collection, $jsonFilter);

        if ($result < 0) {
            throw new AnvilDbException($this->getLastError());
        }

        return (int) $result;
    }

    // -----------------------------------------------------------------------
    // Indexes
    // -----------------------------------------------------------------------

    public function createIndex(string $collection, string $field, string $indexType = 'hash'): void
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_create_index($this->handle, $collection, $field, $indexType);

        if ($result < 0) {
            throw new AnvilDbException($this->getLastError());
        }
    }

    public function dropIndex(string $collection, string $field): void
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_drop_index($this->handle, $collection, $field);

        if ($result < 0) {
            throw new AnvilDbException($this->getLastError());
        }
    }

    // -----------------------------------------------------------------------
    // Schema
    // -----------------------------------------------------------------------

    public function setSchema(string $collection, string $jsonSchema): void
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_set_schema($this->handle, $collection, $jsonSchema);

        if ($result < 0) {
            throw new AnvilDbException($this->getLastError());
        }
    }

    // -----------------------------------------------------------------------
    // Buffer control
    // -----------------------------------------------------------------------

    public function flush(): void
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_flush($this->handle);

        if ($result < 0) {
            throw new AnvilDbException('Failed to flush: ' . $this->getLastError());
        }
    }

    public function flushCollection(string $collection): void
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_flush_collection($this->handle, $collection);

        if ($result < 0) {
            throw new AnvilDbException('Failed to flush collection: ' . $this->getLastError());
        }
    }

    public function configureBuffer(int $maxDocs, int $flushIntervalSecs): void
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_configure_buffer($this->handle, $maxDocs, $flushIntervalSecs);

        if ($result < 0) {
            throw new AnvilDbException('Failed to configure buffer: ' . $this->getLastError());
        }
    }

    // -----------------------------------------------------------------------
    // Encryption
    // -----------------------------------------------------------------------

    public function encrypt(string $key): void
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_encrypt($this->handle, $key);

        if ($result < 0) {
            throw new AnvilDbException('Failed to encrypt: ' . $this->getLastError());
        }
    }

    public function decrypt(string $key): void
    {
        $this->ensureOpen();
        $result = $this->ffi->anvildb_decrypt($this->handle, $key);

        if ($result < 0) {
            throw new AnvilDbException('Failed to decrypt: ' . $this->getLastError());
        }
    }

    // -----------------------------------------------------------------------
    // Cache
    // -----------------------------------------------------------------------

    public function clearCache(): void
    {
        $this->ensureOpen();
        $this->ffi->anvildb_clear_cache($this->handle);
    }

    // -----------------------------------------------------------------------
    // Errors & Warnings
    // -----------------------------------------------------------------------

    public function getWarnings(): array
    {
        if ($this->handle === null) {
            return [];
        }

        $ptr = $this->ffi->anvildb_last_warning($this->handle);

        if ($ptr === null) {
            return [];
        }

        $json = $this->ptrToString($ptr);
        $warnings = json_decode($json, true);

        return is_array($warnings) ? $warnings : [];
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    private function ensureOpen(): void
    {
        if ($this->handle === null) {
            throw new AnvilDbException('AnvilDb instance is not open');
        }
    }

    private function getLastError(): string
    {
        $error = $this->ffi->anvildb_last_error($this->handle);

        if ($error === null) {
            return 'Unknown error';
        }

        if (is_string($error)) {
            return $error;
        }

        return \FFI::string($error);
    }

    /**
     * Convert an FFI pointer to a PHP string and free the pointer.
     */
    private function ptrToString(mixed $ptr): string
    {
        if (is_string($ptr)) {
            return $ptr;
        }

        $str = \FFI::string($ptr);
        $this->ffi->anvildb_free_string($ptr);

        return $str;
    }
}
