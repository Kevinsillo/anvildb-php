<?php

declare(strict_types=1);

namespace AnvilDb\Driver;

use AnvilDb\Exception\AnvilDbException;

/**
 * Factory that selects the best available driver for the current environment.
 *
 * Priority:
 * 1. Environment variable `ANVILDB_DRIVER` override (`ffi` or `process`)
 * 2. FFI extension available → FFIDriver
 * 3. Fallback → ProcessDriver
 */
class DriverFactory
{
    /**
     * Create and open a driver instance.
     *
     * @param string      $dataPath      Filesystem path to the database directory
     * @param string|null $encryptionKey 64-char hex string, or null
     *
     * @return DriverInterface
     *
     * @throws AnvilDbException If no suitable driver can be created
     */
    public static function create(string $dataPath, ?string $encryptionKey = null): DriverInterface
    {
        $driver = self::resolveDriver();
        $driver->open($dataPath, $encryptionKey);

        return $driver;
    }

    private static function resolveDriver(): DriverInterface
    {
        $override = getenv('ANVILDB_DRIVER');

        if ($override === 'ffi') {
            return new FFIDriver();
        }

        if ($override === 'process') {
            return new ProcessDriver();
        }

        if (extension_loaded('ffi')) {
            try {
                return new FFIDriver();
            } catch (\Throwable) {
                // FFI loaded but restricted (ffi.enable=preload) — fall through to Process
            }
        }

        return new ProcessDriver();
    }
}
