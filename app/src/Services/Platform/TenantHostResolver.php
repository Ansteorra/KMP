<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantMetadata;
use Cake\Datasource\ConnectionManager;

/**
 * Resolves request hostnames to platform tenant metadata.
 */
class TenantHostResolver
{
    /**
     * @param string $connectionName Platform datasource name
     */
    public function __construct(private readonly string $connectionName = 'platform')
    {
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
        $connection = ConnectionManager::get($this->connectionName);
        $row = $connection->execute(
            'SELECT t.* FROM tenant_hosts h ' .
            'INNER JOIN tenants t ON t.id = h.tenant_id ' .
            'WHERE h.host_normalized = :host AND h.status = :hostStatus LIMIT 1',
            [
                'host' => $normalizedHost,
                'hostStatus' => 'active',
            ],
        )->fetch('assoc');

        if (!is_array($row)) {
            return null;
        }

        return TenantMetadata::fromPlatformRow($row);
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
}
