<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Backups\TenantBackupStorageInterface;
use App\Services\Backups\TenantBackupStoredObject;
use Closure;
use RuntimeException;

final class RetentionTenantStorage implements TenantBackupStorageInterface
{
    /**
     * @var list<string>
     */
    public array $deleted = [];

    /**
     * Constructor.
     */
    public function __construct(
        private readonly bool $fail = false,
        private readonly ?Closure $onDelete = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function workPath(string $backupId, string $suffix): string
    {
        return TMP . $backupId . $suffix;
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
        if ($this->onDelete !== null) {
            ($this->onDelete)($objectUri);
        }
        if ($this->fail) {
            throw new RuntimeException('token=secret-token storage unavailable');
        }
        $this->deleted[] = $objectUri;
    }
}
