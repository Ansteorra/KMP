<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\BackupArchiveStorageInterface;
use App\Services\Backups\TenantBackupStoredObject;

final class DownloadArchiveStorage implements BackupArchiveStorageInterface
{
    /**
     * Constructor.
     */
    public function __construct(
        private readonly string $workRoot,
        private readonly string $payload,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function workPath(string $backupId, string $suffix): string
    {
        return $this->workRoot . DIRECTORY_SEPARATOR . $backupId . $suffix;
    }

    /**
     * @inheritDoc
     */
    public function retrieve(string $objectUri, string $destinationPath): TenantBackupStoredObject
    {
        file_put_contents($destinationPath, $this->payload);

        return new TenantBackupStoredObject(
            $objectUri,
            strlen($this->payload),
            hash('sha256', $this->payload),
        );
    }

    /**
     * @inheritDoc
     */
    public function delete(string $objectUri): void
    {
    }
}
