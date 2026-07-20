<?php
declare(strict_types=1);

namespace App\Services\Platform;

use Cake\Database\Connection;

final class TenantMigrationLockHandle
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly int $keyOne,
        private readonly int $keyTwo,
        public readonly bool $acquired,
    ) {
    }

    /**
     * Release the PostgreSQL advisory lock when it was acquired.
     */
    public function release(): void
    {
        if ($this->acquired) {
            $this->connection->execute('SELECT pg_advisory_unlock(?, ?)', [$this->keyOne, $this->keyTwo]);
        }
    }
}
