<?php
declare(strict_types=1);

// phpcs:disable CakePHP.Commenting.FunctionComment.Missing

namespace App\Services\Backups;

use App\Services\Platform\PlatformScheduleRunner;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\Utility\Text;
use RuntimeException;
use Throwable;

class TenantRestoreDrillService
{
    private const JOB_TYPE = 'tenant_restore_drill';

    public function __construct(
        private readonly Connection $platformConnection,
        private readonly TenantRestoreDrillVerifierInterface $verifier,
    ) {
    }

    public function planRecentDrill(
        ?string $tenantSlug = null,
        int $lookbackHours = 36,
        bool $executeRestore = false,
        bool $confirmDestructive = false,
    ): TenantRestoreDrillResult {
        $tenantSlug = $tenantSlug === null ? null : strtolower(trim($tenantSlug));
        if ($tenantSlug !== null && $tenantSlug !== '') {
            $this->assertSafeSlug($tenantSlug);
        }
        if ($lookbackHours < 1 || $lookbackHours > 744) {
            throw new RuntimeException('Restore drill lookback hours must be between 1 and 744.');
        }
        if ($executeRestore && !$confirmDestructive) {
            throw new RuntimeException('Destructive restore drill execution requires explicit confirmation.');
        }

        $backup = $this->findRecentCompletedBackup($tenantSlug, $lookbackHours);
        if ($backup === null) {
            $jobId = $this->insertJob(null, 'failed', [
                'tenant_slug' => $tenantSlug,
                'lookback_hours' => $lookbackHours,
                'dry_run' => !$executeRestore,
            ], 'No completed tenant backups were found for restore drill planning.');

            throw new RuntimeException(sprintf(
                'No completed tenant backups found within %d hours%s. Recorded failed drill job %s.',
                $lookbackHours,
                $tenantSlug !== null && $tenantSlug !== '' ? ' for tenant "' . $tenantSlug . '"' : '',
                $jobId,
            ));
        }

        $backupId = (string)$backup['id'];
        $archiveLocked = false;
        try {
            $archiveLocked = $this->lockBackupArchive($backupId);
            $this->assertBackupStillCompleted($backupId);
            $dryRun = !$executeRestore;
            $jobId = $this->insertJob((string)$backup['tenant_id'], 'running', [
                'tenant_slug' => (string)$backup['tenant_slug'],
                'backup_id' => $backupId,
                'backup_completed_at' => $this->nullableString($backup['completed_at'] ?? null),
                'lookback_hours' => $lookbackHours,
                'dry_run' => $dryRun,
                'destructive_execution' => $executeRestore,
            ]);
            $plan = new TenantRestoreDrillPlan(
                $jobId,
                $backupId,
                (string)$backup['tenant_id'],
                (string)$backup['tenant_slug'],
                $this->nullableString($backup['completed_at'] ?? null),
                $dryRun,
                $executeRestore,
            );

            try {
                $this->verifier->verify($plan);
                $status = $dryRun ? 'planned' : 'completed';
                $message = $dryRun
                    ? 'Restore drill plan verified without executing restore.'
                    : 'Restore drill executed.';
                $this->finishJob($jobId, $status, null);

                return new TenantRestoreDrillResult(
                    $jobId,
                    $plan->backupId,
                    $plan->tenantSlug,
                    $status,
                    $dryRun,
                    $message,
                );
            } catch (Throwable $e) {
                $message = $this->scrubError($e->getMessage());
                $this->finishJob($jobId, 'failed', $message);

                throw new RuntimeException($message, 0, $e);
            }
        } finally {
            if ($archiveLocked) {
                $this->unlockBackupArchive($backupId);
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findRecentCompletedBackup(?string $tenantSlug, int $lookbackHours): ?array
    {
        $cutoff = DateTime::now('UTC')->subHours($lookbackHours)->format('Y-m-d H:i:s');
        $params = ['cutoff' => $cutoff];
        $tenantFilter = '';
        if ($tenantSlug !== null && $tenantSlug !== '') {
            $tenantFilter = ' AND t.slug = :tenantSlug';
            $params['tenantSlug'] = $tenantSlug;
        }
        $row = $this->platformConnection->execute(
            'SELECT b.id, b.tenant_id, b.completed_at, b.created_at, t.slug AS tenant_slug
               FROM tenant_backups b
               INNER JOIN tenants t ON t.id = b.tenant_id
              WHERE b.status = :status
                AND b.backup_type IN (:jsonBackupType, :legacyBackupType)
                AND (b.completed_at IS NOT NULL AND b.completed_at >= :cutoff)'
                . $tenantFilter .
            ' ORDER BY b.completed_at DESC, b.created_at DESC
              LIMIT 1',
            $params + [
                'status' => 'completed',
                'jsonBackupType' => TenantBackupService::BACKUP_TYPE,
                'legacyBackupType' => 'pg_dump',
            ],
        )->fetch('assoc');

        return is_array($row) ? $row : null;
    }

    private function assertBackupStillCompleted(string $backupId): void
    {
        $status = $this->platformConnection->execute(
            'SELECT status FROM tenant_backups WHERE id = :backupId LIMIT 1',
            ['backupId' => $backupId],
        )->fetchColumn(0);
        if ($status !== 'completed') {
            throw new RuntimeException('Tenant backup is no longer available for a restore drill.');
        }
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
            Log::error(sprintf('Restore drill archive unlock failed for %s: %s', $backupId, $exception::class));
        }
    }

    /**
     * @param array<string, mixed> $parameters Job parameters
     */
    private function insertJob(?string $tenantId, string $status, array $parameters, ?string $lastError = null): string
    {
        $jobId = Text::uuid();
        $now = DateTime::now('UTC');
        $this->platformConnection->insert('platform_jobs', [
            'id' => $jobId,
            'tenant_id' => $tenantId,
            'requested_by_platform_user_id' => null,
            'job_type' => self::JOB_TYPE,
            'status' => $status,
            'idempotency_key' => null,
            'parameters' => json_encode($parameters, JSON_UNESCAPED_SLASHES),
            'log_uri' => null,
            'last_error' => $lastError === null ? null : $this->scrubError($lastError),
            'created_at' => $now,
            'started_at' => $status === 'running' ? $now : null,
            'finished_at' => $status === 'failed' ? $now : null,
            'modified_at' => $now,
        ]);

        return $jobId;
    }

    private function finishJob(string $jobId, string $status, ?string $lastError): void
    {
        $now = DateTime::now('UTC');
        $this->platformConnection->update('platform_jobs', [
            'status' => $status,
            'last_error' => $lastError,
            'finished_at' => $now,
            'modified_at' => $now,
        ], ['id' => $jobId]);
    }

    private function assertSafeSlug(string $slug): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,78}[a-z0-9]$/', $slug)) {
            throw new RuntimeException('Unsafe tenant slug.');
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string)$value;
    }

    private function scrubError(string $message): string
    {
        $message = PlatformScheduleRunner::scrubError($message);
        $message = (string)preg_replace('/PGPASSWORD=[^\s]+/i', 'PGPASSWORD=[redacted]', $message);

        return mb_substr($message, 0, 2000);
    }
}
