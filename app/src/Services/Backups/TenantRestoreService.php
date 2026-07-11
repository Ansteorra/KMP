<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

use App\KMP\TenantMetadata;
use App\Services\Platform\PlatformScheduleRunner;
use App\Services\Secrets\SecretStoreInterface;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\Utility\Text;
use RuntimeException;
use Throwable;

class TenantRestoreService
{
    public const JOB_TYPE = 'tenant_restore';
    public const MODE_SAME_TENANT = 'same-tenant';
    public const MODE_CROSS_TENANT = 'cross-tenant';

    public function __construct(
        private readonly Connection $platformConnection,
        private readonly SecretStoreInterface $secretStore,
        private readonly TenantBackupStorageInterface $storage,
        private readonly TenantBackupEncryptor $encryptor,
        private readonly TenantBackupRestorerInterface $jsonRestorer,
        private readonly ?TenantBackupRestorerInterface $legacyPgDumpRestorer = null,
    ) {
    }

    public function restoreTenantBackup(
        string $backupId,
        string $mode,
        ?string $targetTenantSlug,
        bool $confirmDestructive,
        bool $dryRun = false,
        ?string $platformJobId = null,
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
        if (!$confirmDestructive && !$dryRun) {
            throw new RuntimeException('Restore requires --confirm-destructive.');
        }

        $jobId = $platformJobId === null ? Text::uuid() : null;
        $encryptedPath = null;
        $plainPath = null;
        $archiveLocked = false;

        try {
            if ($platformJobId !== null) {
                $this->assertExistingJob($platformJobId);
                $jobId = $platformJobId;
            }
            if ($jobId === null) {
                throw new RuntimeException('Restore job identifier was not initialized.');
            }
            $archiveLocked = $this->lockBackupArchive($backupId);
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
            $restorer = $this->restorerFor($backup);

            if ($platformJobId === null) {
                $this->insertJob($jobId, $targetTenant, $sourceTenant, $backupId, $mode, $dryRun, 'queued');
            } else {
                $this->platformConnection->update(
                    'platform_jobs',
                    ['tenant_id' => $targetTenant->id, 'modified_at' => $this->now()],
                    ['id' => $jobId],
                );
            }
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

            $jsonBackup = (string)$backup['backup_type'] === TenantBackupService::BACKUP_TYPE;
            $encryptedPath = $this->storage->workPath(
                $backupId,
                $jsonBackup ? '.restore.json.gz.enc' : '.restore.pgdump.enc',
            );
            $plainPath = $this->storage->workPath(
                $backupId,
                $jsonBackup ? '.restore.json.gz' : '.restore.pgdump',
            );
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
            $restorer->validate($targetTenant, $plainPath);
            if (!$dryRun) {
                $this->assertTenantStillSuspended($targetTenant->id);
                $restorer->restore(
                    $targetTenant,
                    $dbPassword,
                    $plainPath,
                    function (array $progress) use ($jobId): void {
                        $this->heartbeat($jobId, $progress);
                    },
                );
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
            if ($jobId !== null) {
                $this->markJobFailed($jobId, $e);
            }
            throw new RuntimeException($this->scrubError($e->getMessage()), 0, $e);
        } finally {
            foreach ([$plainPath, $encryptedPath] as $path) {
                if ($path !== null && is_file($path)) {
                    unlink($path);
                }
            }
            if ($archiveLocked) {
                $this->unlockBackupArchive($backupId);
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

    private function lockBackupArchive(string $backupId): bool
    {
        if (!$this->platformConnection->getDriver() instanceof Postgres) {
            return false;
        }
        $this->platformConnection->execute(
            'SELECT pg_advisory_lock(hashtext(:scope))',
            ['scope' => sprintf('backup-archive:%s', $backupId)],
        );

        return true;
    }

    private function unlockBackupArchive(string $backupId): void
    {
        try {
            $this->platformConnection->execute(
                'SELECT pg_advisory_unlock(hashtext(:scope))',
                ['scope' => sprintf('backup-archive:%s', $backupId)],
            );
        } catch (Throwable $exception) {
            Log::error(sprintf('Tenant restore archive unlock failed for %s: %s', $backupId, $exception::class));
        }
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

    private function assertTenantStillSuspended(string $tenantId): void
    {
        $status = $this->platformConnection->execute(
            'SELECT status FROM tenants WHERE id = :tenantId LIMIT 1',
            ['tenantId' => $tenantId],
        )->fetchColumn(0);
        if ($status !== 'suspended') {
            throw new RuntimeException('Target tenant must remain suspended during a destructive restore.');
        }
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
        if (!in_array((string)$backup['backup_type'], [TenantBackupService::BACKUP_TYPE, 'pg_dump'], true)) {
            throw new RuntimeException('Unsupported tenant backup type for restore.');
        }
        if (
            !in_array(
                (string)$backup['encryption_algorithm'],
                [TenantBackupEncryptor::DATA_ALGORITHM, TenantBackupEncryptor::LEGACY_DATA_ALGORITHM],
                true,
            )
        ) {
            throw new RuntimeException('Unsupported tenant backup encryption algorithm.');
        }
    }

    /**
     * Select the logical JSON restorer while retaining compatibility with
     * encrypted pg_dump archives created before the managed format changed.
     *
     * @param array<string, mixed> $backup
     */
    private function restorerFor(array $backup): TenantBackupRestorerInterface
    {
        if ((string)$backup['backup_type'] === TenantBackupService::BACKUP_TYPE) {
            return $this->jsonRestorer;
        }
        if ((string)$backup['backup_type'] === 'pg_dump' && $this->legacyPgDumpRestorer !== null) {
            return $this->legacyPgDumpRestorer;
        }

        throw new RuntimeException('No restorer is available for this tenant backup type.');
    }

    /**
     * Keep long logical restores fresh in fleet-health monitoring.
     *
     * @param array<string, mixed> $progress
     */
    private function heartbeat(string $jobId, array $progress): void
    {
        $this->platformConnection->update('platform_jobs', [
            'modified_at' => $this->now(),
        ], ['id' => $jobId]);
    }

    private function assertObjectUriMatchesSource(string $objectUri, TenantMetadata $sourceTenant): void
    {
        $expectedLocalPrefix = 'local://' . $sourceTenant->slug . '/';
        if (str_starts_with($objectUri, 'local://') && !str_starts_with($objectUri, $expectedLocalPrefix)) {
            throw new RuntimeException('Tenant backup object URI does not match source tenant.');
        }
        $expectedConfiguredPrefix = 'backup://tenants/' . $sourceTenant->slug . '/';
        if (str_starts_with($objectUri, 'backup://') && !str_starts_with($objectUri, $expectedConfiguredPrefix)) {
            throw new RuntimeException('Tenant backup object URI does not match source tenant.');
        }
    }

    private function assertExistingJob(string $jobId): void
    {
        $row = $this->platformConnection->execute(
            'SELECT job_type, status FROM platform_jobs WHERE id = :id LIMIT 1',
            ['id' => $jobId],
        )->fetch('assoc');
        if (!is_array($row) || (string)$row['job_type'] !== self::JOB_TYPE) {
            throw new RuntimeException('Tenant restore job context is invalid.');
        }
        if (!in_array((string)$row['status'], ['queued', 'running'], true)) {
            throw new RuntimeException('Tenant restore job is not executable.');
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
            'tenant_slug' => $targetTenant->slug,
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
