<?php
declare(strict_types=1);

namespace App\Controller\PlatformAdmin;

use App\Services\Backups\BackupDeletionService;
use App\Services\Backups\BackupStorageFactory;
use App\Services\Backups\PlatformDatabaseBackupEncryptor;
use App\Services\Backups\TenantBackupService;
use App\Services\Platform\PlatformAdminJobEnqueuer;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformHealthService;
use App\Services\Platform\PlatformJobRunner;
use App\Services\Platform\ReleaseCompatibilityChecker;
use App\Services\Platform\ReleaseManifest;
use Cake\Core\Configure;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Utility\Text;
use RuntimeException;
use Throwable;

class OperationsController extends PlatformAdminAppController
{
    /**
     * Show platform metadata datasource health.
     *
     * @return void
     */
    public function health(): void
    {
        $health = (new PlatformHealthService(
            'platform',
            (int)Configure::read('Platform.health.retryAttempts', 0),
            (int)Configure::read('Platform.health.retryDelayMs', 0),
        ))->check()->toSafeArray();
        $this->set(compact('health'));
    }

    /**
     * List recent platform jobs with only a redacted error indicator.
     *
     * @return void
     */
    public function jobs(): void
    {
        $jobs = $this->safeRows(
            'SELECT j.id, j.job_type, j.status, j.created_at, j.started_at, j.finished_at,
                    CASE WHEN j.last_error IS NULL OR j.last_error = :empty THEN 0 ELSE 1 END AS has_error,
                    t.slug AS tenant_slug
               FROM platform_jobs j
          LEFT JOIN tenants t ON t.id = j.tenant_id
           ORDER BY j.created_at DESC
              LIMIT 100',
            ['empty' => ''],
        );
        $this->set(compact('jobs'));
    }

    /**
     * Show safe execution detail and progress events for one platform job.
     */
    public function job(string $jobId): void
    {
        $job = $this->platform()->execute(
            'SELECT j.id, j.tenant_id, j.job_type, j.status, j.created_at, j.started_at,
                    j.finished_at, j.modified_at,
                    CASE WHEN j.last_error IS NULL OR j.last_error = :empty THEN 0 ELSE 1 END AS has_error,
                    t.slug AS tenant_slug
               FROM platform_jobs j
          LEFT JOIN tenants t ON t.id = j.tenant_id
              WHERE j.id = :jobId
              LIMIT 1',
            ['jobId' => $jobId, 'empty' => ''],
        )->fetch('assoc');
        if (!is_array($job)) {
            throw new NotFoundException('Platform job was not found.');
        }
        $events = $this->safeRows(
            'SELECT sequence_number, event_level, event_code, message, created_at
               FROM platform_job_events
              WHERE platform_job_id = :jobId
           ORDER BY sequence_number ASC
              LIMIT 200',
            ['jobId' => $jobId],
        );
        $retryNonce = Text::uuid();
        $canRetry = (string)$job['status'] === 'failed'
            && PlatformJobRunner::supports((string)$job['job_type']);
        $this->set(compact('job', 'events', 'retryNonce', 'canRetry'));
    }

    /**
     * Queue a fresh attempt of a failed executable platform job.
     *
     * @return \Cake\Http\Response|null
     */
    public function retryJob(string $jobId)
    {
        $this->request->allowMethod(['post']);
        try {
            $source = $this->platform()->execute(
                'SELECT id, tenant_id, job_type, status, parameters
                   FROM platform_jobs
                  WHERE id = :jobId
                  LIMIT 1',
                ['jobId' => $jobId],
            )->fetch('assoc');
            if (!is_array($source)) {
                throw new NotFoundException('Platform job was not found.');
            }
            if ((string)$source['status'] !== 'failed') {
                throw new BadRequestException('Only failed jobs can be retried.');
            }
            if (!PlatformJobRunner::supports((string)$source['job_type'])) {
                throw new BadRequestException('This platform job type is not retryable.');
            }
            $parameters = json_decode((string)$source['parameters'], true);
            if (!is_array($parameters)) {
                throw new BadRequestException('The source job parameters are invalid.');
            }
            $reason = $this->validateStepUpAction('RETRY job');
            $nonce = (string)$this->request->getData('nonce', Text::uuid());
            $job = $this->jobEnqueuer()->enqueue(
                (string)$source['job_type'],
                $source['tenant_id'] === null ? null : (string)$source['tenant_id'],
                $this->platformAdmin['id'] ?? null,
                $parameters,
                sprintf('platform_job_retry:%s:%s', $jobId, $nonce),
                $reason,
                $this->auditOptions($source['tenant_id'] === null ? null : (string)$source['tenant_id']),
            );
            $this->Flash->success(__('A new job attempt has been queued: {0}', $job['id']));
        } catch (BadRequestException | RuntimeException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect([
            'prefix' => 'PlatformAdmin',
            'controller' => 'Operations',
            'action' => 'job',
            $jobId,
        ]);
    }

    /**
     * List configured platform schedules.
     *
     * @return void
     */
    public function schedules(): void
    {
        $schedules = $this->safeRows(
            'SELECT s.name, s.cron_expression, s.command, s.enabled, s.tenant_scope,
                    s.status, s.last_run_at, s.next_run_at, s.last_success_at, s.last_failure_at,
                    CASE WHEN s.last_error IS NULL OR s.last_error = :empty THEN 0 ELSE 1 END AS has_error,
                    t.slug AS tenant_slug
               FROM platform_schedules s
          LEFT JOIN tenants t ON t.id = s.tenant_id
           ORDER BY s.enabled DESC, s.next_run_at ASC, s.name ASC
              LIMIT 100',
            ['empty' => ''],
        );
        $this->set(compact('schedules'));
    }

    /**
     * List tenant and platform database backup status.
     *
     * @return void
     */
    public function backups(): void
    {
        $tenantBackups = $this->safeRows(
            'SELECT b.id, b.backup_type, b.status, b.object_size_bytes, b.created_at,
                    b.completed_at, b.retention_until, b.error_summary, b.encryption_algorithm,
                    t.slug AS tenant_slug
               FROM tenant_backups b
               JOIN tenants t ON t.id = b.tenant_id
           ORDER BY b.created_at DESC
              LIMIT 100',
        );
        $platformBackups = $this->safeRows(
            'SELECT id, backup_type, status, connection_name, database_name, object_size_bytes,
                   object_uri, created_at, completed_at, retention_until, error_summary, encryption_algorithm
               FROM platform_database_backups
           ORDER BY created_at DESC
              LIMIT 50',
        );
        $tenants = $this->safeRows(
            'SELECT slug, display_name, status FROM tenants ORDER BY display_name ASC, slug ASC LIMIT 200',
        );
        $nonce = Text::uuid();
        $this->set(compact('tenantBackups', 'platformBackups', 'tenants', 'nonce'));
    }

    /**
     * Queue an encrypted platform PostgreSQL dump backup.
     *
     * @return \Cake\Http\Response|null
     */
    public function createPlatformBackup()
    {
        $this->request->allowMethod(['post']);
        $retentionDays = max(1, min(365, (int)$this->request->getData('retention_days', 30)));
        $nonce = (string)$this->request->getData('nonce', Text::uuid());
        try {
            $job = (new PlatformAdminJobEnqueuer(
                $this->platform(),
                new PlatformAuditService($this->platform()),
            ))->enqueue(
                PlatformJobRunner::JOB_PLATFORM_BACKUP,
                null,
                $this->platformAdmin['id'] ?? null,
                [
                    'retention_days' => $retentionDays,
                ],
                sprintf('platform_database_backup:%s', $nonce),
                'platform admin platform database backup request',
                [
                    'ipAddress' => $this->request->clientIp(),
                    'userAgent' => $this->request->getHeaderLine('User-Agent') ?: 'platform-admin',
                ],
            );
            $this->Flash->success(__('Platform database backup has been queued: {0}', $job['id']));
        } catch (RuntimeException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect(['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'backups']);
    }

    /**
     * Download an encrypted platform database dump after audit and step-up.
     *
     * @param string $backupId Platform backup row id
     * @return \Cake\Http\Response|null
     */
    public function downloadPlatformBackup(string $backupId)
    {
        $this->request->allowMethod(['post']);

        try {
            $backup = $this->platformBackupById($backupId);
            $this->assertUsableBackup($backup, ['pg_dump', TenantBackupService::LEGACY_BACKUP_TYPE]);
            $reason = $this->validateStepUpAction('DOWNLOAD platform');
            $download = $this->stageBackupDownload(
                $backup,
                BackupStorageFactory::platformArchive((string)$backup['backup_type']),
                'platform',
            );
            register_shutdown_function(static function () use ($download): void {
                if (is_file($download['path'])) {
                    unlink($download['path']);
                }
            });
            $this->auditService()->record(
                'platform_backup.download',
                $this->platformAdmin['id'] ?? null,
                'platform_database_backup',
                $backupId,
                $reason,
                ['backup_format' => 'encrypted_' . (string)$backup['backup_type']],
                false,
                $this->auditOptions(),
            );

            return $this->response
                ->withType('application/octet-stream')
                ->withFile($download['path'], [
                    'download' => true,
                    'name' => $download['filename'],
                ]);
        } catch (BadRequestException | RuntimeException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect(['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'backups']);
    }

    /**
     * Download a platform backup-scoped recovery key after audit and operator step-up.
     *
     * @param string $backupId Platform backup row id
     * @return \Cake\Http\Response|null
     */
    public function downloadPlatformBackupRecoveryKey(string $backupId)
    {
        $this->request->allowMethod(['post']);

        try {
            $backup = $this->platformBackupById($backupId);
            $this->assertUsableBackup($backup, ['pg_dump']);
            if ((string)$backup['encryption_algorithm'] !== PlatformDatabaseBackupEncryptor::DATA_ALGORITHM) {
                throw new BadRequestException('This backup does not support portable recovery-key export.');
            }
            $reason = $this->validateStepUpAction('DOWNLOAD KEY platform');
            $export = $this->exportPlatformBackupRecoveryKey($backup);
            $this->auditService()->record(
                'platform_backup.recovery_key_exported',
                $this->platformAdmin['id'] ?? null,
                'platform_database_backup',
                $backupId,
                $reason,
                ['backup_format' => 'encrypted_pg_dump'],
                false,
                $this->auditOptions(),
            );

            return $this->recoveryKeyDownloadResponse($export);
        } catch (BadRequestException | RuntimeException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect(['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'backups']);
    }

    /**
     * Delete an encrypted platform backup object after audit and operator step-up.
     *
     * @param string $backupId Platform backup row id
     * @return \Cake\Http\Response|null
     */
    public function deletePlatformBackup(string $backupId)
    {
        $this->request->allowMethod(['post']);

        try {
            $backup = $this->platformBackupById($backupId);
            $this->assertDeletableBackup($backup, ['pg_dump', TenantBackupService::LEGACY_BACKUP_TYPE]);
            $reason = $this->validateStepUpAction('DELETE BACKUP platform');
            (new BackupDeletionService($this->platform(), $this->auditService()))->deletePlatform(
                $backup,
                BackupStorageFactory::platformArchive((string)$backup['backup_type']),
                $this->platformAdmin['id'] ?? null,
                $reason,
                ['backup_format' => 'encrypted_' . (string)$backup['backup_type']],
                $this->auditOptions(),
            );
            $this->Flash->success(__('Platform backup archive deleted. Audit metadata has been retained.'));
        } catch (BadRequestException | RuntimeException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect(['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'backups']);
    }

    /**
     * Show release records and manifest compatibility status.
     *
     * @return void
     */
    public function release(): void
    {
        $releases = $this->safeRows(
            'SELECT image_tag, git_sha, min_schema, max_schema, status, created_at, modified_at
               FROM releases
           ORDER BY created_at DESC
              LIMIT 25',
        );
        $compatibility = $this->compatibility();
        $this->set(compact('releases', 'compatibility'));
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function safeRows(string $sql, array $params = []): array
    {
        try {
            return $this->platform()->execute($sql, $params)->fetchAll('assoc');
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Create the audited Platform Admin job enqueue service.
     *
     * @return \App\Services\Platform\PlatformAdminJobEnqueuer
     */
    private function jobEnqueuer(): PlatformAdminJobEnqueuer
    {
        return new PlatformAdminJobEnqueuer($this->platform(), $this->auditService());
    }

    /**
     * Create the platform audit service.
     *
     * @return \App\Services\Platform\PlatformAuditService
     */
    private function auditService(): PlatformAuditService
    {
        return new PlatformAuditService($this->platform());
    }

    /**
     * @return array<string, mixed>
     */
    private function auditOptions(?string $tenantId = null): array
    {
        return [
            'tenantId' => $tenantId,
            'ipAddress' => $this->request->clientIp(),
            'userAgent' => $this->request->getHeaderLine('User-Agent') ?: 'platform-admin',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function platformBackupById(string $backupId): array
    {
        $row = $this->platform()->execute(
            'SELECT id, backup_type, status, object_uri, object_size_bytes, object_sha256,
                    encryption_algorithm, wrapped_dek, wrapped_dek_key_name,
                    wrapped_dek_key_version, wrapped_dek_metadata, retention_until
               FROM platform_database_backups
              WHERE id = :backupId
              LIMIT 1',
            ['backupId' => $backupId],
        )->fetch('assoc');
        if (!is_array($row)) {
            throw new NotFoundException('Backup was not found.');
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function compatibility(): array
    {
        try {
            $manifest = ReleaseManifest::fromFile(ROOT . DS . 'config' . DS . 'release_manifest.json');
            $tenantRows = $this->platform()->execute(
                'SELECT slug, schema_version FROM tenants WHERE status = :status ORDER BY slug',
                ['status' => 'active'],
            )->fetchAll('assoc');
            $errors = (new ReleaseCompatibilityChecker())->incompatibleTenants($tenantRows, $manifest);

            return [
                'available' => true,
                'appVersion' => $manifest->appVersion,
                'minTenantSchema' => $manifest->minTenantSchema,
                'maxTenantSchema' => $manifest->maxTenantSchema,
                'activeTenantCount' => count($tenantRows),
                'incompatibleCount' => count($errors),
                'errors' => $errors,
            ];
        } catch (Throwable) {
            return ['available' => false, 'message' => 'Release manifest compatibility is unavailable.'];
        }
    }
}
