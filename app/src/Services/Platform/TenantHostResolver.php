<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantMetadata;
use App\Services\Cache\TenantAwareCache;
use Cake\Cache\Exception\CacheWriteException;
use Cake\Core\Exception\CakeException;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;

/**
 * Resolves request hostnames to platform tenant metadata.
 */
class TenantHostResolver
{
    private const CACHE_KEY_PREFIX = 'tenant_host_map:';
    private const CACHE_CONFIG = 'tenant_host_map';

    /**
     * @param string $connectionName Platform datasource name
     */
    public function __construct(
        private readonly string $connectionName = 'platform',
        private readonly TenantAwareCache $cache = new TenantAwareCache(),
    ) {
    }

    /**
     * Resolve a host to tenant metadata using active host mappings.
     *
     * @param string $host Request host
     * @return \App\KMP\TenantMetadata|null
     */
    public function resolve(string $host): ?TenantMetadata
    {
        $normalizedHost = $this->normalizeHost($host);
        $map = $this->hostMap();
        if (isset($map[$normalizedHost]) && is_array($map[$normalizedHost])) {
            return TenantMetadata::fromPlatformRow($map[$normalizedHost]);
        }

        return null;
    }

    /**
     * Clear the shared tenant host map cache after platform tenant/host changes.
     *
     * @param string $connectionName Platform datasource name
     * @return bool
     */
    public static function clearCache(string $connectionName = 'platform'): bool
    {
        try {
            return (new TenantAwareCache())->deletePlatform(self::cacheKey($connectionName), self::CACHE_CONFIG);
        } catch (CakeException $exception) {
            Log::warning('Unable to clear tenant host map cache: ' . $exception->getMessage());

            return false;
        }
    }

    /**
     * Load active host mappings once for all users/processes.
     *
     * @return array<string, array<string, mixed>>
     */
    private function hostMap(): array
    {
        $cacheKey = self::cacheKey($this->connectionName);
        $cached = $this->cache->readPlatform($cacheKey, self::CACHE_CONFIG);
        if (is_array($cached)) {
            return $cached;
        }

        $connection = ConnectionManager::get($this->connectionName);
        $rows = $connection->execute(
            'SELECT h.host_normalized, t.* FROM tenant_hosts h ' .
            'INNER JOIN tenants t ON t.id = h.tenant_id ' .
            'WHERE h.status = :hostStatus',
            [
                'hostStatus' => 'active',
            ],
        )->fetchAll('assoc');

        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['host_normalized'])) {
                continue;
            }
            $map[(string)$row['host_normalized']] = $row;
        }
        $this->writeHostMapCache($cacheKey, $map);

        return $map;
    }

    /**
     * Normalize host names before platform lookup.
     *
     * @param string $host Host name
     * @return string
     */
    public function normalizeHost(string $host): string
    {
        return strtolower(rtrim($host, '.'));
    }

    /**
     * Build the platform cache key for a datasource-specific host map.
     *
     * @param string $connectionName Platform datasource name
     * @return string
     */
    private static function cacheKey(string $connectionName): string
    {
        return self::CACHE_KEY_PREFIX . preg_replace('/[^A-Za-z0-9_.-]+/', '_', $connectionName);
    }

    /**
     * Write the host map without letting cache backend/debug wrapper failures break requests.
     *
     * @param string $cacheKey Logical platform cache key
     * @param array<string, array<string, mixed>> $map Host map
     * @return void
     */
    private function writeHostMapCache(string $cacheKey, array $map): void
    {
        try {
            $this->cache->writePlatform($cacheKey, $map, self::CACHE_CONFIG);
        } catch (CacheWriteException $exception) {
            Log::warning('Unable to write tenant host map cache: ' . $exception->getMessage());
        }
    }
}
