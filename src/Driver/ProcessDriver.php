<?php

declare(strict_types=1);

namespace AnvilDb\Driver;

use AnvilDb\Exception\AnvilDbException;
use AnvilDb\Exception\ProcessException;

/**
 * Driver that communicates with the anvildb-server binary via stdin/stdout pipes.
 *
 * Used as fallback when the FFI extension is not available.
 */
class ProcessDriver implements DriverInterface
{
    /** @var resource|null */
    private $process = null;

    /** @var resource|null stdin pipe (write) */
    private $stdin = null;

    /** @var resource|null stdout pipe (read) */
    private $stdout = null;

    /** @var resource|null stderr pipe (read) */
    private $stderr = null;

    private array $lastWarnings = [];

    public function __destruct()
    {
        $this->terminateProcess();
    }

    // -----------------------------------------------------------------------
    // Lifecycle
    // -----------------------------------------------------------------------

    public function open(string $dataPath, ?string $encryptionKey = null): void
    {
        $binPath = $this->detectBinaryPath();

        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $this->process = proc_open($binPath, $descriptors, $pipes);

        if (!is_resource($this->process)) {
            throw new ProcessException("Failed to start anvildb-server at: {$binPath}");
        }

        $this->stdin = $pipes[0];
        $this->stdout = $pipes[1];
        $this->stderr = $pipes[2];

        // Set stdout to non-blocking for reads (we'll use fgets which blocks until newline)
        stream_set_blocking($this->stderr, false);

        $params = ['path' => $dataPath];
        if ($encryptionKey !== null) {
            $params['encryption_key'] = $encryptionKey;
        }

        $this->send('open', $params);
    }

    public function close(): void
    {
        if ($this->process !== null) {
            try {
                $this->send('close');
            } catch (\Throwable $e) {
                // Process may already be dead
            }
            $this->terminateProcess();
        }
    }

    public function shutdown(): void
    {
        if ($this->process !== null) {
            try {
                $this->send('shutdown');
            } catch (\Throwable $e) {
                // Process may already be dead
            }
            $this->terminateProcess();
        }
    }

    // -----------------------------------------------------------------------
    // Collections
    // -----------------------------------------------------------------------

    public function createCollection(string $name): void
    {
        $this->send('create_collection', ['name' => $name]);
    }

    public function dropCollection(string $name): void
    {
        $this->send('drop_collection', ['name' => $name]);
    }

    public function listCollections(): string
    {
        $response = $this->send('list_collections');

        return json_encode($response['data'] ?? [], JSON_THROW_ON_ERROR);
    }

    // -----------------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------------

    public function insert(string $collection, string $jsonDoc): string
    {
        $response = $this->send('insert', [
            'collection' => $collection,
            'document' => $jsonDoc,
        ]);

        return json_encode($response['data'], JSON_THROW_ON_ERROR);
    }

    public function findById(string $collection, string $id): ?string
    {
        $response = $this->send('find_by_id', [
            'collection' => $collection,
            'id' => $id,
        ]);

        if ($response['data'] === null) {
            return null;
        }

        return json_encode($response['data'], JSON_THROW_ON_ERROR);
    }

    public function update(string $collection, string $id, string $jsonDoc): void
    {
        $this->send('update', [
            'collection' => $collection,
            'id' => $id,
            'document' => $jsonDoc,
        ]);
    }

    public function delete(string $collection, string $id): void
    {
        $this->send('delete', [
            'collection' => $collection,
            'id' => $id,
        ]);
    }

    public function updateWhere(string $collection, string $jsonFilter, string $jsonDoc): int
    {
        $response = $this->send('update_where', [
            'collection' => $collection,
            'filter' => $jsonFilter,
            'document' => $jsonDoc,
        ]);

        return (int) $response['data'];
    }

    public function deleteWhere(string $collection, string $jsonFilter): int
    {
        $response = $this->send('delete_where', [
            'collection' => $collection,
            'filter' => $jsonFilter,
        ]);

        return (int) $response['data'];
    }

    public function bulkInsert(string $collection, string $jsonDocs): string
    {
        $response = $this->send('bulk_insert', [
            'collection' => $collection,
            'documents' => $jsonDocs,
        ]);

        return json_encode($response['data'], JSON_THROW_ON_ERROR);
    }

    // -----------------------------------------------------------------------
    // Queries
    // -----------------------------------------------------------------------

    public function query(string $jsonQuerySpec): string
    {
        $response = $this->send('query', [
            'spec' => $jsonQuerySpec,
        ]);

        return json_encode($response['data'], JSON_THROW_ON_ERROR);
    }

    public function count(string $collection, ?string $jsonFilter = null): int
    {
        $params = ['collection' => $collection];
        if ($jsonFilter !== null) {
            $params['filter'] = $jsonFilter;
        }

        $response = $this->send('count', $params);

        return (int) $response['data'];
    }

    // -----------------------------------------------------------------------
    // Indexes
    // -----------------------------------------------------------------------

    public function createIndex(string $collection, string $field, string $indexType = 'hash'): void
    {
        $this->send('create_index', [
            'collection' => $collection,
            'field' => $field,
            'index_type' => $indexType,
        ]);
    }

    public function dropIndex(string $collection, string $field): void
    {
        $this->send('drop_index', [
            'collection' => $collection,
            'field' => $field,
        ]);
    }

    // -----------------------------------------------------------------------
    // Schema
    // -----------------------------------------------------------------------

    public function setSchema(string $collection, string $jsonSchema): void
    {
        $this->send('set_schema', [
            'collection' => $collection,
            'schema' => $jsonSchema,
        ]);
    }

    // -----------------------------------------------------------------------
    // Buffer control
    // -----------------------------------------------------------------------

    public function flush(): void
    {
        $this->send('flush');
    }

    public function flushCollection(string $collection): void
    {
        $this->send('flush_collection', ['collection' => $collection]);
    }

    public function configureBuffer(int $maxDocs, int $flushIntervalSecs): void
    {
        $this->send('configure_buffer', [
            'max_docs' => $maxDocs,
            'flush_interval_secs' => $flushIntervalSecs,
        ]);
    }

    // -----------------------------------------------------------------------
    // Encryption
    // -----------------------------------------------------------------------

    public function encrypt(string $key): void
    {
        $this->send('encrypt', ['encryption_key' => $key]);
    }

    public function decrypt(string $key): void
    {
        $this->send('decrypt', ['encryption_key' => $key]);
    }

    // -----------------------------------------------------------------------
    // Cache
    // -----------------------------------------------------------------------

    public function clearCache(): void
    {
        $this->send('clear_cache');
    }

    // -----------------------------------------------------------------------
    // Errors & Warnings
    // -----------------------------------------------------------------------

    public function getWarnings(): array
    {
        return $this->lastWarnings;
    }

    // -----------------------------------------------------------------------
    // Internal: communication
    // -----------------------------------------------------------------------

    /**
     * Send a command to the anvildb-server and return the parsed response.
     *
     * @return array{ok: bool, data: mixed, error?: string, error_code?: int, warnings?: array}
     *
     * @throws AnvilDbException On engine errors
     * @throws ProcessException On communication failures
     */
    private function send(string $cmd, array $params = []): array
    {
        $this->ensureProcess();

        $request = json_encode([
            'cmd' => $cmd,
            'params' => (object) $params,
        ], JSON_THROW_ON_ERROR);

        $written = @fwrite($this->stdin, $request . "\n");
        if ($written === false) {
            throw new ProcessException('Failed to write to anvildb-server stdin');
        }
        @fflush($this->stdin);

        $line = @fgets($this->stdout);
        if ($line === false) {
            // Try to read stderr for error details
            $stderr = $this->readStderr();
            throw new ProcessException('anvildb-server stopped responding' . ($stderr ? ": {$stderr}" : ''));
        }

        $response = json_decode(trim($line), true);
        if (!is_array($response)) {
            throw new ProcessException('Invalid response from anvildb-server');
        }

        $this->lastWarnings = $response['warnings'] ?? [];

        if (!($response['ok'] ?? false)) {
            $errorMsg = $response['error'] ?? 'Unknown engine error';
            throw new AnvilDbException($errorMsg);
        }

        return $response;
    }

    private function ensureProcess(): void
    {
        if ($this->process === null || !is_resource($this->process)) {
            throw new ProcessException('anvildb-server process is not running');
        }

        $status = proc_get_status($this->process);
        if (!$status['running']) {
            throw new ProcessException('anvildb-server process has terminated unexpectedly');
        }
    }

    private function readStderr(): string
    {
        if ($this->stderr === null) {
            return '';
        }

        $output = '';
        while (($line = @fgets($this->stderr)) !== false) {
            $output .= $line;
        }

        return trim($output);
    }

    private function terminateProcess(): void
    {
        if ($this->stdin !== null && is_resource($this->stdin)) {
            @fclose($this->stdin);
            $this->stdin = null;
        }
        if ($this->stdout !== null && is_resource($this->stdout)) {
            @fclose($this->stdout);
            $this->stdout = null;
        }
        if ($this->stderr !== null && is_resource($this->stderr)) {
            @fclose($this->stderr);
            $this->stderr = null;
        }
        if ($this->process !== null && is_resource($this->process)) {
            proc_close($this->process);
            $this->process = null;
        }
    }

    // -----------------------------------------------------------------------
    // Internal: binary detection
    // -----------------------------------------------------------------------

    private function detectBinaryPath(): string
    {
        // 1. Environment variable override
        $envPath = getenv('ANVILDB_BIN_PATH');
        if ($envPath !== false && is_executable($envPath)) {
            return $envPath;
        }

        $wrapperDir = dirname(__DIR__, 2);       // wrappers/php/
        $monorepoRoot = dirname($wrapperDir, 2); // project root

        $ext = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        $binName = "anvildb-server{$ext}";

        // 2. Monorepo dev: target/release or target/debug
        $releasebin = $monorepoRoot . "/target/release/{$binName}";
        if (is_executable($releasebin)) {
            return $releasebin;
        }

        $debugBin = $monorepoRoot . "/target/debug/{$binName}";
        if (is_executable($debugBin)) {
            return $debugBin;
        }

        // 3. Distributed wrapper: lib/<platform>/
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');

        $platform = match (true) {
            $os === 'Linux' && $arch === 'x86_64' => 'x86_64-linux',
            $os === 'Linux' && str_contains($arch, 'aarch64') => 'aarch64-linux',
            $os === 'Darwin' && $arch === 'x86_64' => 'x86_64-darwin',
            $os === 'Darwin' && str_contains($arch, 'arm64') => 'aarch64-darwin',
            $os === 'Windows' && $arch === 'AMD64' => 'x86_64-windows',
            default => throw new ProcessException("Unsupported platform: {$os} {$arch}"),
        };

        $distBin = $wrapperDir . "/lib/{$platform}/{$binName}";
        if (is_executable($distBin)) {
            return $distBin;
        }

        throw new ProcessException(
            "anvildb-server binary not found. Searched:\n" .
            "  - ANVILDB_BIN_PATH env var\n" .
            "  - {$releasebin}\n" .
            "  - {$debugBin}\n" .
            "  - {$distBin}"
        );
    }
}
