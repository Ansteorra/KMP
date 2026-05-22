<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Platform\PlatformScheduleRunner;
use App\Services\Secrets\SecretStoreInterface;
use Cake\Database\Connection;
use Cake\I18n\DateTime;
use Cake\Utility\Text;
use RuntimeException;
use Throwable;

class TenantRestoreService
{
    private const JOB_TYPE = 'tenant_restore';
    public const MODE_SAME_TENANT = 'same-tenant';
    public const MODE_CROSS_TENANT = 'cross-tenant';

    public function __construct(
        private readonly Connection $platformConnection,
        private readonly SecretStoreInterface $secretStore,
        private readonly TenantBackupStorageInterface $storage,
        private readonly TenantBackupEncryptor $encryptor,
        private readonly TenantBackupRestorerInterface $restorer,
    ) {
    }

    public function restoreTenantBackup(
        string $backupId,
        string $mode,
        ?string $targetTenantSlug,
        bool $confirmDestructive,
        bool $dryRun = false,
    ): TenantRestoreResult {
        $backupId = strtolower(trim($backupId));
        $mode = strtolower(trim($mode));
        $targetTenantSlug = $targetTenantSlug === null ? null : strtolower(trim($targetTenantSlug));
        $this->assertSafeUuid($backupId, 'backup id');
        $this->assertMode($mode);
        if ($mode === self::MODE_SAME_TENANT && !$confirmDestructive && !$dryRun) {
            throw new RuntimeException('Same-tenant restore requires --confirm-destructive.');
        }
        if ($mode === self::MODE_CROSS_TENANT && ($targetTenantSlug === null || $targetTenantSlug === '')) {
            throw new RuntimeException('Cross-tenant restore requires --target-tenant.');
        }
        if ($targetTenantSlug !== null && $targetTenantSlug !== '') {
            $this->assertSafeSlug($targetTenantSlug);
        }

        $jobId = Text::uuid();
        $encryptedPath = null;
        $plainPath = null;

        try {
            $backup = $this->findCompletedBackup($backupId);
            $sourceTenant = $this->findTenantById((string)$backup['tenant_id']);
            $targetTenant = $mode === self::MODE_SAME_TENANT
                ? $sourceTenant
                : $this->findTenantBySlug((string)$targetTenantSlug);
            if ($mode === self::MODE_CROSS_TENANT && $targetTenant->id === $sourceTenant->id) {
                throw new RuntimeException('Cross-tenant restore target must differ from the backup source tenant.');
            }
            $this->assertSafeTenantDatabase($sourceTenant, 'source');
            $this->assertSafeTenantDatabase($targetTenant, 'target');
            $this->assertCompleteBackup($backup);
            $this->assertObjectUriMatchesSource((string)$backup['object_uri'], $sourceTenant);
            $this->restorer->buildArgv($targetTenant, $this->storage->workPath($backupId, '.restore-plan.pgdump'));

            $this->insertJob($jobId, $targetTenant, $sourceTenant, $backupId, $mode, $dryRun, 'queued');
            $kek = $this->secretStore->get((string)$backup['wrapped_dek_key_name']);
            if ($kek === null || $kek->isEmpty()) {
                throw new RuntimeException('Missing backup KEK secret for tenant restore.');
            }
            $dbPassword = $this->secretStore->get(sprintf('tenant.%s.db.password', $targetTenant->slug));
            if ($dbPassword === null || $dbPassword->isEmpty()) {
                throw new RuntimeException(sprintf(
                    'Missing database password secret for target tenant "%s".',
                    $targetTenant->slug,
                ));
            }

            $startedAt = $this->now();
            $this->platformConnection->update('platform_jobs', [
                'status' => 'running',
                'started_at' => $startedAt,
                'modified_at' => $startedAt,
            ], ['id' => $jobId]);

            $encryptedPath = $this->storage->workPath($backupId, '.restore.pgdump.enc.json');
            $plainPath = $this->storage->workPath($backupId, '.restore.pgdump');
            $stored = $this->storage->retrieve((string)$backup['object_uri'], $encryptedPath);
            if ((string)$backup['object_sha256'] !== '' && $stored->sha256 !== (string)$backup['object_sha256']) {
                throw new RuntimeException('Stored tenant backup checksum does not match metadata.');
            }
            $metadata = json_decode((string)$backup['wrapped_dek_metadata'], true);
            if (!is_array($metadata)) {
                throw new RuntimeException('Wrapped DEK metadata is invalid.');
            }
            $this->encryptor->decryptFile(
                $encryptedPath,
                $plainPath,
                (string)$backup['wrapped_dek'],
                $metadata,
                $kek,
            );
            if (!$dryRun) {
                $this->restorer->restore($targetTenant, $dbPassword, $plainPath);
            }
            $finishedAt = $this->now();
            $status = $dryRun ? 'planned' : 'completed';
            $this->platformConnection->update('platform_jobs', [
                'status' => $status,
                'finished_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $jobId]);

            return new TenantRestoreResult(
                $jobId,
                $backupId,
                $mode,
                $sourceTenant->slug,
                $targetTenant->slug,
                $status,
                $dryRun,
            );
        } catch (Throwable $e) {
            $this->markJobFailed($jobId, $e);
            throw new RuntimeException($this->scrubError($e->getMessage()), 0, $e);
        } finally {
            foreach ([$plainPath, $encryptedPath] as $path) {
                if ($path !== null && is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function findCompletedBackup(string $backupId): array
    {
        $row = $this->platformConnection->execute(
            'SELECT * FROM tenant_backups WHERE id = :id LIMIT 1',
            ['id' => $backupId],
        )->fetch('assoc');
        if (!is_array($row)) {
            throw new RuntimeException('Tenant backup was not found.');
        }
        if ((string)$row['status'] !== 'completed') {
            throw new RuntimeException('Tenant backup is not completed.');
        }

        return $row;
    }

    private function findTenantById(string $tenantId): TenantMetadata
    {
        $this->assertSafeUuid($tenantId, 'tenant id');
        $row = $this->platformConnection->execute(
            'SELECT * FROM tenants WHERE id = :id LIMIT 1',
            ['id' => $tenantId],
        )->fetch('assoc');
        if (!is_array($row)) {
            throw new RuntimeException('Backup source tenant was not found.');
        }

        return TenantMetadata::fromPlatformRow($row);
    }

    private function findTenantBySlug(string $slug): TenantMetadata
    {
        $this->assertSafeSlug($slug);
        $row = $this->platformConnection->execute(
            'SELECT * FROM tenants WHERE slug = :slug LIMIT 1',
            ['slug' => $slug],
        )->fetch('assoc');
        if (!is_array($row)) {
            throw new RuntimeException(sprintf('Target tenant "%s" was not found.', $slug));
        }

        return TenantMetadata::fromPlatformRow($row);
    }

    /**
     * @param array<string, mixed> $backup Backup metadata row
     */
    private function assertCompleteBackup(array $backup): void
    {
        foreach (['object_uri', 'object_sha256', 'wrapped_dek', 'wrapped_dek_metadata'] as $field) {
            if (!isset($backup[$field]) || (string)$backup[$field] === '') {
                throw new RuntimeException(sprintf('Tenant backup metadata is incomplete: missing %s.', $field));
            }
        }
        if ((string)$backup['backup_type'] !== 'pg_dump') {
            throw new RuntimeException('Unsupported tenant backup type for restore.');
        }
        if ((string)$backup['encryption_algorithm'] !== TenantBackupEncryptor::DATA_ALGORITHM) {
            throw new RuntimeException('Unsupported tenant backup encryption algorithm.');
        }
    }

    private function assertObjectUriMatchesSource(string $objectUri, TenantMetadata $sourceTenant): void
    {
        $expectedLocalPrefix = 'local://' . $sourceTenant->slug . '/';
        if (str_starts_with($objectUri, 'local://') && !str_starts_with($objectUri, $expectedLocalPrefix)) {
            throw new RuntimeException('Tenant backup object URI does not match source tenant.');
        }
    }

    private function insertJob(
        string $jobId,
        TenantMetadata $targetTenant,
        TenantMetadata $sourceTenant,
        string $backupId,
        string $mode,
        bool $dryRun,
        string $status,
    ): void {
        $now = $this->now();
        $parameters = [
            'backup_id' => $backupId,
            'mode' => $mode,
            'dry_run' => $dryRun,
            'source_tenant_id' => $sourceTenant->id,
            'source_tenant_slug' => $sourceTenant->slug,
            'target_tenant_id' => $targetTenant->id,
            'target_tenant_slug' => $targetTenant->slug,
        ];
        $this->platformConnection->insert('platform_jobs', [
            'id' => $jobId,
            'tenant_id' => $targetTenant->id,
            'requested_by_platform_user_id' => null,
            'job_type' => self::JOB_TYPE,
            'status' => $status,
            'idempotency_key' => null,
            'parameters' => json_encode($parameters, JSON_UNESCAPED_SLASHES),
            'log_uri' => null,
            'last_error' => null,
            'created_at' => $now,
            'started_at' => null,
            'finished_at' => null,
            'modified_at' => $now,
        ]);
    }

    private function markJobFailed(string $jobId, Throwable $e): void
    {
        $exists = $this->platformConnection->execute(
            'SELECT COUNT(*) FROM platform_jobs WHERE id = :id',
            ['id' => $jobId],
        )->fetchColumn(0);
        if ((int)$exists !== 1) {
            return;
        }
        $finishedAt = $this->now();
        $this->platformConnection->update('platform_jobs', [
            'status' => 'failed',
            'last_error' => $this->scrubError($e->getMessage()),
            'finished_at' => $finishedAt,
            'modified_at' => $finishedAt,
        ], ['id' => $jobId]);
    }

    private function assertMode(string $mode): void
    {
        if (!in_array($mode, [self::MODE_SAME_TENANT, self::MODE_CROSS_TENANT], true)) {
            throw new RuntimeException('Restore mode must be same-tenant or cross-tenant.');
        }
    }

    private function assertSafeUuid(string $value, string $label): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value)) {
            throw new RuntimeException(sprintf('Unsafe %s.', $label));
        }
    }

    private function assertSafeSlug(string $slug): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,78}[a-z0-9]$/', $slug)) {
            throw new RuntimeException('Unsafe tenant slug.');
        }
    }

    private function assertSafeTenantDatabase(TenantMetadata $tenant, string $label): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/', $tenant->dbName)) {
            throw new RuntimeException(sprintf('Unsafe %s tenant database name.', $label));
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/', $tenant->dbRole)) {
            throw new RuntimeException(sprintf('Unsafe %s tenant database role.', $label));
        }
        if (
            $tenant->dbServer === ''
            || strlen($tenant->dbServer) > 255
            || str_starts_with($tenant->dbServer, '-')
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9.-]*[A-Za-z0-9]$/', $tenant->dbServer)
        ) {
            throw new RuntimeException(sprintf('Unsafe %s tenant database host.', $label));
        }
    }

    private function scrubError(string $message): string
    {
        $message = PlatformScheduleRunner::scrubError($message);
        $message = (string)preg_replace('/password\s*[:=]\s*[^\s]+/i', 'password=[redacted]', $message);
        $message = (string)preg_replace('/PGPASSWORD=[^\s]+/i', 'PGPASSWORD=[redacted]', $message);

        return mb_substr($message, 0, 2000);
    }

    private function now(): DateTime
    {
        return DateTime::now('UTC');
    }
}
