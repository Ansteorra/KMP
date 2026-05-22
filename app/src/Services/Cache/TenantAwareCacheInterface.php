<?php
declare(strict_types=1);

namespace App\Services\Cache;

use Closure;

/**
 * Tenant-safe facade for application cache access.
 */
interface TenantAwareCacheInterface
{
    /**
     * Return the active tenant's cache key for a logical key.
     */
    public function tenantKey(string $key): string;

    /**
     * Return the shared platform cache key for a logical key.
     */
    public function platformKey(string $key): string;

    /**
     * Read from the active tenant scope.
     */
    public function read(string $key, string $config = 'default'): mixed;

    /**
     * Write to the active tenant scope.
     */
    public function write(string $key, mixed $value, string $config = 'default'): bool;

    /**
     * Add to the active tenant scope when absent.
     */
    public function add(string $key, mixed $value, string $config = 'default'): bool;

    /**
     * Delete from the active tenant scope.
     */
    public function delete(string $key, string $config = 'default'): bool;

    /**
     * Read or compute a value in the active tenant scope.
     */
    public function remember(string $key, Closure $default, string $config = 'default'): mixed;

    /**
     * Read from the shared platform scope.
     */
    public function readPlatform(string $key, string $config = 'default'): mixed;

    /**
     * Write to the shared platform scope.
     */
    public function writePlatform(string $key, mixed $value, string $config = 'default'): bool;

    /**
     * Delete from the shared platform scope.
     */
    public function deletePlatform(string $key, string $config = 'default'): bool;
}
