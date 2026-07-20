<?php
declare(strict_types=1);

namespace App\Services\Backups;

class PlatformDatabaseBackupResult
{
    /**
     * Constructor.
     *
     * @param string $backupId Backup metadata identifier
     * @param string $jobId Platform job identifier
     * @param string $status Final backup status
     * @param string $objectUri Stored encrypted object URI
     */
    public function __construct(
        public readonly string $backupId,
        public readonly string $jobId,
        public readonly string $status,
        public readonly string $objectUri,
    ) {
    }
}
