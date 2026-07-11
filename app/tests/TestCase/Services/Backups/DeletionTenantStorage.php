<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Backups\TenantBackupStorageInterface;
use App\Services\Backups\TenantBackupStoredObject;
use RuntimeException;

final class DeletionTenantStorage implements TenantBackupStorageInterface
{
    /**
     * @var list<string>
     */
    public array $deleted = [];

    /**
     * Constructor.
     */
    public function __construct(private readonly bool $fail = false)
    {
    }

    /**
     * @inheritDoc
     */
    public function workPath(string $backupId, string $suffix): string
    {
        throw new RuntimeException('Not used.');
    }

    /**
     * @inheritDoc
     */
    public function store(
        TenantMetadata $tenant,
        string $backupId,
        string $encryptedPath,
    ): TenantBackupStoredObject {
        throw new RuntimeException('Not used.');
    }

    /**
     * @inheritDoc
     */
    public function retrieve(string $objectUri, string $destinationPath): TenantBackupStoredObject
    {
        throw new RuntimeException('Not used.');
    }

    /**
     * @inheritDoc
     */
    public function delete(string $objectUri): void
    {
        if ($this->fail) {
            throw new RuntimeException('storage secret-token failed');
        }
        $this->deleted[] = $objectUri;
    }
}
