<?php
declare(strict_types=1);

namespace App\Controller\PlatformAdmin;

use App\Services\BackupStorageService;
use App\Services\Platform\PlatformAdminJobEnqueuer;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformHealthService;
use App\Services\Platform\ReleaseCompatibilityChecker;
use App\Services\Platform\ReleaseManifest;
use Cake\Core\Configure;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\Utility\Text;
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
                    b.completed_at, b.retention_until, t.slug AS tenant_slug
               FROM tenant_backups b
               JOIN tenants t ON t.id = b.tenant_id
           ORDER BY b.created_at DESC
              LIMIT 100',
        );
        $platformBackups = $this->safeRows(
            'SELECT id, backup_type, status, connection_name, database_name, object_size_bytes,
                    created_at, completed_at, retention_until
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
     * Queue a platform database JSON backup.
     *
     * @return \Cake\Http\Response|null
     */
    public function createPlatformBackup()
    {
        $this->request->allowMethod(['post']);
        $retentionDays = max(1, min(365, (int)$this->request->getData('retention_days', 30)));
        $nonce = (string)$this->request->getData('nonce', Text::uuid());
        $job = (new PlatformAdminJobEnqueuer(
            $this->platform(),
            new PlatformAuditService($this->platform()),
        ))->enqueue(
            'platform_backup_json',
            null,
            $this->platformAdmin['id'] ?? null,
            [
                'scope' => 'platform',
                'connection' => 'platform',
                'backup_format' => 'kmpbackup_json',
                'retention_days' => $retentionDays,
            ],
            sprintf('platform_backup_json:%s', $nonce),
            'platform admin platform database backup request',
            [
                'ipAddress' => $this->request->clientIp(),
                'userAgent' => $this->request->getHeaderLine('User-Agent') ?: 'platform-admin',
            ],
        );
        $this->Flash->success(__('Platform database backup has been queued: {0}', $job['id']));

        return $this->redirect(['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'backups']);
    }

    /**
     * Queue a guarded platform database restore request.
     *
     * @param string $backupId Platform backup row id
     * @return \Cake\Http\Response|null
     */
    public function restorePlatformBackup(string $backupId)
    {
        $this->request->allowMethod(['post']);

        try {
            $backup = $this->platformBackupById($backupId);
            $filename = $this->validatedCompletedKmpBackup($backup);
            $reason = $this->validateStepUpAction('RESTORE platform');
            $nonce = (string)$this->request->getData('nonce', Text::uuid());
            $job = $this->jobEnqueuer()->enqueue(
                'platform_restore_json',
                null,
                $this->platformAdmin['id'] ?? null,
                [
                    'scope' => 'platform',
                    'connection' => 'platform',
                    'backup_id' => $backupId,
                    'object_name' => $filename,
                    'backup_format' => 'kmpbackup_json',
                    'reason' => $reason,
                ],
                sprintf('platform_restore_json:%s:%s', $backupId, $nonce),
                $reason,
                $this->auditOptions(),
            );
            $this->Flash->success(__('Platform database restore has been queued: {0}', $job['id']));
        } catch (BadRequestException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect(['prefix' => 'PlatformAdmin', 'controller' => 'Operations', 'action' => 'backups']);
    }

    /**
     * Download an encrypted platform database .kmpbackup archive after audit and step-up.
     *
     * @param string $backupId Platform backup row id
     * @return \Cake\Http\Response|null
     */
    public function downloadPlatformBackup(string $backupId)
    {
        $this->request->allowMethod(['post']);

        try {
            $backup = $this->platformBackupById($backupId);
            $filename = $this->validatedCompletedKmpBackup($backup);
            $reason = $this->validateStepUpAction('DOWNLOAD platform');
            $this->auditService()->record(
                'platform_backup.download',
                $this->platformAdmin['id'] ?? null,
                'platform_database_backup',
                $backupId,
                $reason,
                ['backup_format' => 'kmpbackup_json'],
                false,
                $this->auditOptions(),
            );
            $data = (new BackupStorageService())->read($filename);

            return $this->response
                ->withType('application/octet-stream')
                ->withDownload(basename($filename))
                ->withStringBody($data);
        } catch (BadRequestException $exception) {
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
    private function auditOptions(): array
    {
        return [
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
            'SELECT id, backup_type, status, object_uri
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
     * @param array<string, mixed> $backup
     */
    private function validatedCompletedKmpBackup(array $backup): string
    {
        if ((string)($backup['status'] ?? '') !== 'completed') {
            throw new BadRequestException('Only completed backups can be used for this action.');
        }
        if ((string)($backup['backup_type'] ?? '') !== 'kmpbackup_json') {
            throw new BadRequestException('Only encrypted JSON .kmpbackup archives can be used for this action.');
        }

        return $this->safeKmpBackupObjectName($backup['object_uri'] ?? null);
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
