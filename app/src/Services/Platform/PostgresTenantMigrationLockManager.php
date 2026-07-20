<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantMetadata;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use RuntimeException;

class PostgresTenantMigrationLockManager implements TenantMigrationLockManagerInterface
{
    /**
     * Try to acquire the tenant migration advisory lock.
     */
    public function acquire(Connection $connection, TenantMetadata $tenant): TenantMigrationLockHandle
    {
        if (!$connection->getDriver() instanceof Postgres) {
            throw new RuntimeException('Tenant migrations require PostgreSQL advisory lock support.');
        }

        [$keyOne, $keyTwo] = $this->lockKeys($tenant);
        $acquired = (bool)$connection
            ->execute('SELECT pg_try_advisory_lock(?, ?)', [$keyOne, $keyTwo])
            ->fetchColumn(0);

        return new TenantMigrationLockHandle($connection, $keyOne, $keyTwo, $acquired);
    }

    /**
     * @return array{int, int}
     */
    private function lockKeys(TenantMetadata $tenant): array
    {
        $hash = hash('sha256', 'kmp:tenant:migrate:' . $tenant->id);

        return [
            (int)hexdec(substr($hash, 0, 7)),
            (int)hexdec(substr($hash, 8, 7)),
        ];
    }
}
