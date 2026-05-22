<?php
declare(strict_types=1);

namespace App\Services\Platform;

use App\KMP\TenantMetadata;
use Cake\Database\Connection;

interface TenantMigrationLockManagerInterface
{
    /**
     * Try to acquire the tenant migration lock on the provided tenant connection.
     */
    public function acquire(Connection $connection, TenantMetadata $tenant): TenantMigrationLockHandle;
}
