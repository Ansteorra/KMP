<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing, Generic.PHP.NoSilencedErrors.Discouraged

namespace App\Services\Backups;

use App\Services\Platform\PlatformScheduleRunner;
use App\Services\Secrets\SecretStoreInterface;
use App\Services\Secrets\SensitiveString;
use Cake\Database\Connection;
use Cake\I18n\DateTime;
use Cake\Utility\Text;
use RuntimeException;
use Throwable;

class PlatformDatabaseBackupService
{
    private const BACKUP_TYPE = 'pg_dump';
    private const JOB_TYPE = 'platform_database_backup';

    /**
     * @param array<string, mixed> $platformConfig Platform datasource configuration
     */
    public function __construct(
        private readonly Connection $platformConnection,
        private readonly array $platformConfig,
        private readonly SecretStoreInterface $secretStore,
        private readonly PlatformDatabaseBackupDumperInterface $dumper,
        private readonly PlatformDatabaseBackupEncryptor $encryptor,
        private readonly LocalPlatformDatabaseBackupStorage $storage,
        private readonly string $kekName = 'platform.backup.kek',
    ) {
    }

    public function backupPlatformDatabase(int $retentionDays = 30): PlatformDatabaseBackupResult
    {
        $jobId = Text::uuid();
        $backupId = Text::uuid();
        $now = $this->now();
        $databaseName = $this->databaseName();
        $this->insertJob($jobId, $databaseName, $now);
        $this->insertBackup($backupId, $jobId, $databaseName, $retentionDays, $now);

        $rawPath = null;
        try {
            $databasePassword = $this->databasePassword();
            $kekVersion = $this->kekVersion($this->kekName);
            $kek = $this->secretStore->get($this->kekName);
            if ($kek === null || $kek->isEmpty()) {
                throw new RuntimeException('Missing platform backup KEK secret.');
            }

            $startedAt = $this->now();
            $this->platformConnection->update('platform_jobs', [
                'status' => 'running',
                'started_at' => $startedAt,
                'modified_at' => $startedAt,
            ], ['id' => $jobId]);
            $this->platformConnection->update('platform_database_backups', [
                'status' => 'running',
                'started_at' => $startedAt,
                'modified_at' => $startedAt,
            ], ['id' => $backupId]);

            $rawPath = $this->storage->workPath($backupId, '.pgdump');
            $encryptedPath = $this->storage->workPath($backupId, '.pgdump.enc.json');
            $this->dumper->dump($this->platformConfig, $databasePassword, $rawPath);
            $encryption = $this->encryptor->encryptFile(
                $rawPath,
                $encryptedPath,
                $backupId,
                $kek,
                $this->kekName,
                $kekVersion,
            );
            $stored = $this->storage->store($backupId, $encryption->encryptedPath);
            $finishedAt = $this->now();
            $this->platformConnection->update('platform_database_backups', [
                'status' => 'completed',
                'object_uri' => $stored->uri,
                'object_size_bytes' => $stored->sizeBytes,
                'object_sha256' => $stored->sha256,
                'encryption_algorithm' => $encryption->algorithm,
                'wrapped_dek' => $encryption->wrappedDek,
                'wrapped_dek_key_name' => $this->kekName,
                'wrapped_dek_key_version' => $kekVersion,
                'wrapped_dek_metadata' => json_encode($encryption->wrappedDekMetadata, JSON_UNESCAPED_SLASHES),
                'completed_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $backupId]);
            $this->platformConnection->update('platform_jobs', [
                'status' => 'completed',
                'finished_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $jobId]);

            return new PlatformDatabaseBackupResult($backupId, $jobId, 'completed', $stored->uri);
        } catch (Throwable $e) {
            $finishedAt = $this->now();
            $message = $this->scrubError($e->getMessage());
            $this->platformConnection->update('platform_jobs', [
                'status' => 'failed',
                'last_error' => $message,
                'finished_at' => $finishedAt,
                'modified_at' => $finishedAt,
            ], ['id' => $jobId]);
            $this->platformConnection->update('platform_database_backups', [
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

    private function insertJob(string $jobId, string $databaseName, DateTime $now): void
    {
        $this->platformConnection->insert('platform_jobs', [
            'id' => $jobId,
            'tenant_id' => null,
            'requested_by_platform_user_id' => null,
            'job_type' => self::JOB_TYPE,
            'status' => 'queued',
            'idempotency_key' => null,
            'parameters' => json_encode(
                ['connection' => 'platform', 'database' => $databaseName],
                JSON_UNESCAPED_SLASHES,
            ),
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
        string $databaseName,
        int $retentionDays,
        DateTime $now,
    ): void {
        $this->platformConnection->insert('platform_database_backups', [
            'id' => $backupId,
            'platform_job_id' => $jobId,
            'backup_type' => self::BACKUP_TYPE,
            'status' => 'queued',
            'connection_name' => 'platform',
            'database_name' => $databaseName,
            'object_uri' => null,
            'object_size_bytes' => null,
            'object_sha256' => null,
            'encryption_algorithm' => PlatformDatabaseBackupEncryptor::DATA_ALGORITHM,
            'wrapped_dek' => null,
            'wrapped_dek_key_name' => $this->kekName,
            'wrapped_dek_key_version' => $this->kekVersion($this->kekName),
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

    private function databasePassword(): SensitiveString
    {
        $password = (string)($this->platformConfig['password'] ?? '');
        if ($password === '') {
            throw new RuntimeException('Missing platform database password configuration.');
        }

        return new SensitiveString($password);
    }

    private function databaseName(): string
    {
        $databaseName = (string)($this->platformConfig['database'] ?? '');
        if ($databaseName === '') {
            throw new RuntimeException('Missing platform database name configuration.');
        }

        return $databaseName;
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
