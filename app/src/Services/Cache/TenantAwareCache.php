<?php
declare(strict_types=1);

namespace App\Services\Cache;

use App\KMP\MissingTenantContextException;
use App\KMP\TenantContext;
use Cake\Cache\Cache;
use Closure;

/**
 * Prefixes cache keys by tenant while preserving single-tenant keys without context.
 *
 * New tenant-scoped cache code should use this service instead of raw Cache:: calls.
 * Use readPlatform()/writePlatform() only for data intentionally shared by all tenants.
 */
class TenantAwareCache implements TenantAwareCacheInterface
{
    /**
     * Constructor.
     */
    public function __construct(private readonly bool $requireTenantContext = false)
    {
    }

    /**
     * Build a tenant-scoped key for the active tenant.
     */
    public function tenantKey(string $key): string
    {
        return self::tenantScopedKey($key, $this->requireTenantContext);
    }

    /**
     * Build a tenant-scoped cache key without requiring service construction.
     */
    public static function tenantScopedKey(string $key, bool $requireTenantContext = false): string
    {
        $tenant = TenantContext::tryCurrent();
        if ($tenant === null) {
            if ($requireTenantContext) {
                throw new MissingTenantContextException('Tenant context is required for tenant cache keys.');
            }

            return $key;
        }

        return sprintf('t:%s:%s:%s', self::clean($tenant->slug), self::clean($tenant->id), $key);
    }

    /**
     * Build a platform-scoped key shared across tenants.
     */
    public function platformKey(string $key): string
    {
        return self::platformScopedKey($key);
    }

    /**
     * Build a platform-scoped cache key shared across tenants.
     */
    public static function platformScopedKey(string $key): string
    {
        return 'platform:' . $key;
    }

    /**
     * Read from the active tenant scope.
     */
    public function read(string $key, string $config = 'default'): mixed
    {
        return Cache::read($this->tenantKey($key), $config);
    }

    /**
     * Write to the active tenant scope.
     */
    public function write(string $key, mixed $value, string $config = 'default'): bool
    {
        return Cache::write($this->tenantKey($key), $value, $config);
    }

    /**
     * Add to the active tenant scope when absent.
     */
    public function add(string $key, mixed $value, string $config = 'default'): bool
    {
        return Cache::add($this->tenantKey($key), $value, $config);
    }

    /**
     * Delete from the active tenant scope.
     */
    public function delete(string $key, string $config = 'default'): bool
    {
        return Cache::delete($this->tenantKey($key), $config);
    }

    /**
     * Read or compute a value in the active tenant scope.
     */
    public function remember(string $key, Closure $default, string $config = 'default'): mixed
    {
        return Cache::remember($this->tenantKey($key), $default, $config);
    }

    /**
     * Read from the shared platform scope.
     */
    public function readPlatform(string $key, string $config = 'default'): mixed
    {
        return Cache::read($this->platformKey($key), $config);
    }

    /**
     * Write to the shared platform scope.
     */
    public function writePlatform(string $key, mixed $value, string $config = 'default'): bool
    {
        return Cache::write($this->platformKey($key), $value, $config);
    }

    /**
     * Delete from the shared platform scope.
     */
    public function deletePlatform(string $key, string $config = 'default'): bool
    {
        return Cache::delete($this->platformKey($key), $config);
    }

    /**
     * Sanitize tenant metadata for cache keys.
     */
    private static function clean(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_.-]+/', '_', $value) ?? $value;
    }
}
