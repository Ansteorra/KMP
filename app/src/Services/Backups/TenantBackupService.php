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

class TenantBackupService
{
    private const BACKUP_TYPE = 'pg_dump';
    private const JOB_TYPE = 'tenant_backup';

    public function __construct(
        private readonly Connection $platformConnection,
        private readonly SecretStoreInterface $secretStore,
        private readonly TenantBackupDumperInterface $dumper,
        private readonly TenantBackupEncryptor $encryptor,
        private readonly TenantBackupStorageInterface $storage,
    ) {
    }

    public function backupTenant(string $slug, int $retentionDays = 30): TenantBackupResult
    {
        $slug = strtolower(trim($slug));
        $this->assertSafeSlug($slug);
        $jobId = Text::uuid();
        $backupId = Text::uuid();
        $now = $this->now();
        $this->insertJob($jobId, null, $slug, $now);

        $rawPath = null;
        try {
            $tenant = $this->findTenant($slug);
            $kekName = sprintf('tenant.%s.kek', $tenant->slug);
            $kekVersion = $this->kekVersion($kekName);
            $this->platformConnection->update('platform_jobs', ['tenant_id' => $tenant->id], ['id' => $jobId]);
            $this->insertBackup($backupId, $jobId, $tenant, $kekName, $kekVersion, $retentionDays, $now);

            $dbPassword = $this->secretStore->get(sprintf('tenant.%s.db.password', $tenant->slug));
            if ($dbPassword === null || $dbPassword->isEmpty()) {
                throw new RuntimeException(sprintf('Missing database password secret for tenant "%s".', $tenant->slug));
            }
            $kek = $this->secretStore->get($kekName);
            if ($kek === null || $kek->isEmpty()) {
                throw new RuntimeException(sprintf('Missing backup KEK secret for tenant "%s".', $tenant->slug));
            }

            $startedAt = $this->now();
            $this->platformConnection->update('platform_jobs', [
                'status' => 'running',
                'started_at' => $startedAt,
                'modified_at' => $startedAt,
            ], ['id' => $jobId]);
            $this->platformConnection->update('tenant_backups', [
                'status' => 'running',
                'started_at' => $startedAt,
                'modified_at' => $startedAt,
            ], ['id' => $backupId]);

            $rawPath = $this->storage->workPath($backupId, '.pgdump');
            $encryptedPath = $this->storage->workPath($backupId, '.pgdump.enc.json');
            $this->dumper->dump($tenant, $dbPassword, $rawPath);
            $encryption = $this->encryptor->encryptFile(
                $rawPath,
                $encryptedPath,
                $tenant,
                $backupId,
                $kek,
                $kekName,
                $kekVersion,
            );
            $stored = $this->storage->store($tenant, $backupId, $encryption->encryptedPath);
            $finishedAt = $this->now();
            $this->platformConnection->update('tenant_backups', [
                'status' => 'completed',
                'object_uri' => $stored->uri,
                'object_size_bytes' => $stored->sizeBytes,
                'object_sha256' => $stored->sha256,
                'encryption_algorithm' => $encryption->algorithm,
                'wrapped_dek' => $encryption->wrappedDek,
                'wrapped_dek_metadata' => json_encode($encryption->wrappedDekMetadata, JSON_UNESCAPED_SLASHES),
                'completed_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $backupId]);
            $this->platformConnection->update('platform_jobs', [
                'status' => 'completed',
                'finished_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $jobId]);

            return new TenantBackupResult($backupId, $jobId, 'completed', $stored->uri);
        } catch (Throwable $e) {
            $finishedAt = $this->now();
            $message = $this->scrubError($e->getMessage());
            $this->platformConnection->update('platform_jobs', [
                'status' => 'failed',
                'last_error' => $message,
                'finished_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $jobId]);
            $this->platformConnection->update('tenant_backups', [
                'status' => 'failed',
                'error_summary' => $message,
                'completed_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $backupId]);
            throw new RuntimeException($message, 0, $e);
        } finally {
            if ($rawPath !== null && is_file($rawPath)) {
                @unlink($rawPath);
            }
        }
    }

    private function insertJob(string $jobId, ?string $tenantId, string $slug, DateTime $now): void
    {
        $this->platformConnection->insert('platform_jobs', [
            'id' => $jobId,
            'tenant_id' => $tenantId,
            'requested_by_platform_user_id' => null,
            'job_type' => self::JOB_TYPE,
            'status' => 'queued',
            'idempotency_key' => null,
            'parameters' => json_encode(['tenant_slug' => $slug], JSON_UNESCAPED_SLASHES),
            'log_uri' => null,
            'last_error' => null,
            'created_at' => $now,
            'started_at' => null,
            'finished_at' => null,
            'modified_at' => $now,
        ]);
    }

    private function insertBackup(
        string $backupId,
        string $jobId,
        TenantMetadata $tenant,
        string $kekName,
        string $kekVersion,
        int $retentionDays,
        DateTime $now,
    ): void {
        $this->platformConnection->insert('tenant_backups', [
            'id' => $backupId,
            'tenant_id' => $tenant->id,
            'platform_job_id' => $jobId,
            'backup_type' => self::BACKUP_TYPE,
            'status' => 'queued',
            'object_uri' => null,
            'object_size_bytes' => null,
            'object_sha256' => null,
            'encryption_algorithm' => TenantBackupEncryptor::DATA_ALGORITHM,
            'wrapped_dek' => null,
            'wrapped_dek_key_name' => $kekName,
            'wrapped_dek_key_version' => $kekVersion,
            'wrapped_dek_metadata' => null,
            'error_summary' => null,
            'retention_until' => $now->addDays($retentionDays),
            'retention_policy' => json_encode(['days' => $retentionDays], JSON_UNESCAPED_SLASHES),
            'created_at' => $now,
            'started_at' => null,
            'completed_at' => null,
            'modified_at' => $now,
        ]);
    }

    private function findTenant(string $slug): TenantMetadata
    {
        $row = $this->platformConnection->execute(
            'SELECT * FROM tenants WHERE slug = :slug LIMIT 1',
            ['slug' => $slug],
        )->fetch('assoc');
        if (!is_array($row)) {
            throw new RuntimeException(sprintf('Tenant "%s" was not found.', $slug));
        }

        return TenantMetadata::fromPlatformRow($row);
    }

    private function assertSafeSlug(string $slug): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,78}[a-z0-9]$/', $slug)) {
            throw new RuntimeException('Unsafe tenant slug.');
        }
    }

    private function kekVersion(string $kekName): string
    {
        $rotatedAt = $this->secretStore->rotatedAt($kekName);

        return $rotatedAt?->format('YmdHis') ?? 'unversioned';
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
