<?php
declare(strict_types=1);

namespace App\Controller\PlatformAdmin;

use App\Services\Backups\BackupDeletionService;
use App\Services\Backups\BackupStorageFactory;
use App\Services\Backups\JsonTenantBackupDumper;
use App\Services\Backups\TenantBackupEncryptor;
use App\Services\Backups\TenantBackupService;
use App\Services\Backups\TenantRestoreService;
use App\Services\BackupService;
use App\Services\Platform\PlatformAdminJobEnqueuer;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\PlatformFleetHealthService;
use App\Services\Platform\PlatformJobRunner;
use App\Services\Platform\TenantConfigSchema;
use App\Services\Platform\TenantHostResolver;
use App\Services\Platform\TenantLifecycleService;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SensitiveString;
use App\Services\Secrets\WritableSecretStoreInterface;
use App\Services\TenantConnectionManager;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\Utility\Text;
use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;

class TenantsController extends PlatformAdminAppController
{
    /**
     * Upper bound for synchronous legacy .kmpbackup imports.
     */
    private const MAX_LEGACY_IMPORT_BYTES = 512 * 1024 * 1024;

    /**
     * List platform tenants without database or secret metadata.
     *
     * @return void
     */
    public function index(): void
    {
        try {
            $fleet = (new PlatformFleetHealthService($this->platform()))->snapshot(null, 500);
            $tenants = $fleet['tenants'];
        } catch (Throwable $exception) {
            Log::warning(sprintf('Platform tenant fleet list failed: %s', $exception::class));
            $tenants = [];
        }

        $this->set(compact('tenants'));
    }

    /**
     * Create a tenant registry row and safe platform-managed configuration.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function add()
    {
        $schema = new TenantConfigSchema();
        $formData = $schema->toFormData([]);
        $tenantForm = $this->defaultTenantForm();
        $errors = [];

        if ($this->request->is('post')) {
            try {
                $tenantData = $this->tenantDataFromRequest($this->request->getData());
                $initialSuperUserEmail = $this->initialSuperUserEmailFromRequest($this->request->getData(), true);
                $newConfig = $schema->buildFromFormData($this->configFormData($this->request->getData()));
                if (($newConfig['email']['mode'] ?? 'default') === 'disabled') {
                    throw new InvalidArgumentException(
                        'Email delivery cannot be disabled while onboarding the initial tenant super user.',
                    );
                }
                $tenant = $this->createTenant($tenantData, $newConfig);
                $job = $this->enqueueTenantProvisioningJob(
                    $tenant,
                    $tenantData,
                    $newConfig,
                    $initialSuperUserEmail,
                );
                $this->Flash->success(__('Tenant provisioning has been queued: {0}', $job['id']));

                return $this->redirect([
                    'prefix' => 'PlatformAdmin',
                    'controller' => 'Tenants',
                    'action' => 'view',
                    $tenant['slug'],
                ]);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
                $this->Flash->error(__($exception->getMessage()));
            } catch (Throwable $exception) {
                Log::error('Unable to create tenant: ' . $exception->getMessage());
                $errors[] = 'Tenant could not be created.';
                $this->Flash->error(__('Tenant could not be created.'));
            }
            $tenantForm = array_merge($tenantForm, $this->request->getData());
            $formData = array_merge($formData, $this->request->getData());
        }

        $this->set(compact('tenantForm', 'formData', 'errors'));
    }

    /**
     * Edit safe tenant registry fields and platform-managed configuration.
     *
     * @param string $slug Tenant slug
     * @return \Cake\Http\Response|null|void
     */
    public function edit(string $slug)
    {
        $tenant = $this->tenantBySlug($slug);
        if ($tenant === null) {
            throw new NotFoundException('Tenant was not found.');
        }

        $schema = new TenantConfigSchema();
        $tenantConfig = isset($tenant['tenant_config']) ? (string)$tenant['tenant_config'] : null;
        $safeConfig = $schema->safeConfigFromJson($tenantConfig);
        $formData = $schema->toFormData($safeConfig);
        $tenantForm = $this->tenantFormFromRow($tenant);
        $errors = [];

        if ($this->request->is(['post', 'put', 'patch'])) {
            try {
                $tenantData = $this->tenantDataFromRequest(
                    $this->request->getData(),
                    (string)$tenant['slug'],
                    (string)$tenant['status'],
                );
                $tenantData['db_server'] = (string)$tenant['db_server'];
                $tenantData['db_name'] = (string)$tenant['db_name'];
                $tenantData['db_role'] = (string)$tenant['db_role'];
                $newConfig = $schema->buildFromFormData($this->configFormData($this->request->getData()));
                $this->updateTenant($tenant, $tenantData, $safeConfig, $newConfig);
                $this->Flash->success(__('Tenant has been updated.'));

                return $this->redirect([
                    'prefix' => 'PlatformAdmin',
                    'controller' => 'Tenants',
                    'action' => 'view',
                    $tenant['slug'],
                ]);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
                $this->Flash->error(__($exception->getMessage()));
            } catch (Throwable $exception) {
                Log::error('Unable to update tenant: ' . $exception->getMessage());
                $errors[] = 'Tenant could not be updated.';
                $this->Flash->error(__('Tenant could not be updated.'));
            }
            $tenantForm = array_merge($tenantForm, $this->request->getData());
            $formData = array_merge($formData, $this->request->getData());
        }

        $this->set(compact('tenant', 'tenantForm', 'formData', 'errors'));
    }

    /**
     * Show safe tenant registry details and recent operational status.
     *
     * @param string $slug Tenant slug
     * @return void
     */
    public function view(string $slug): void
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/', $slug)) {
            throw new NotFoundException('Tenant was not found.');
        }

        $tenant = $this->tenantBySlug($slug);
        if ($tenant === null) {
            throw new NotFoundException('Tenant was not found.');
        }

        $provisioningJob = $this->latestTenantJob(
            (string)$tenant['id'],
            PlatformJobRunner::JOB_TENANT_PROVISION,
        );
        $this->set([
            'tenant' => $tenant,
            'hosts' => $this->tenantHosts((string)$tenant['id']),
            'jobs' => $this->tenantJobs((string)$tenant['id']),
            'backups' => $this->tenantBackups((string)$tenant['id']),
            'provisioningJob' => $provisioningJob,
            'provisioningEvents' => $provisioningJob === null
                ? []
                : $this->jobEvents((string)$provisioningJob['id']),
            'metrics' => $this->tenantMetrics((string)$tenant['id']),
            'metricRoutes' => $this->tenantMetricRoutes((string)$tenant['id']),
            'metricHours' => $this->tenantMetricHours((string)$tenant['id']),
            'lifecycleNonce' => Text::uuid(),
        ]);
    }

    /**
     * Suspend an active tenant after operator step-up verification.
     */
    public function suspend(string $slug)
    {
        return $this->transitionLifecycle($slug, 'suspended', 'SUSPEND');
    }

    /**
     * Reactivate a suspended tenant after operator step-up verification.
     */
    public function reactivate(string $slug)
    {
        return $this->transitionLifecycle($slug, 'active', 'REACTIVATE');
    }

    /**
     * Archive a suspended or incomplete tenant after operator step-up verification.
     */
    public function archive(string $slug)
    {
        return $this->transitionLifecycle($slug, 'archived', 'ARCHIVE');
    }

    /**
     * Show tenant-scoped platform-admin backup controls.
     *
     * @param string $slug Tenant slug
     * @return void
     */
    public function backups(string $slug): void
    {
        $tenant = $this->tenantBySlug($slug);
        if ($tenant === null) {
            throw new NotFoundException('Tenant was not found.');
        }

        $jobs = $this->platform()->execute(
            'SELECT id, job_type, status, parameters, created_at, started_at, finished_at,
                    CASE WHEN last_error IS NULL OR last_error = :empty THEN 0 ELSE 1 END AS has_error
               FROM platform_jobs
              WHERE tenant_id = :tenantId
                AND job_type IN (:backupJob, :restoreJob)
           ORDER BY created_at DESC
              LIMIT 25',
            [
                'tenantId' => $tenant['id'],
                'backupJob' => PlatformJobRunner::JOB_TENANT_BACKUP,
                'restoreJob' => PlatformJobRunner::JOB_TENANT_RESTORE,
                'empty' => '',
            ],
        )->fetchAll('assoc');
        $backups = $this->tenantBackups((string)$tenant['id']);
        $restoreTargets = $this->platform()->execute(
            "SELECT slug, display_name, status
               FROM tenants
              WHERE status = 'suspended' OR slug = :slug
           ORDER BY slug ASC
              LIMIT 200",
            ['slug' => (string)$tenant['slug']],
        )->fetchAll('assoc');
        $defaultRetentionDays = $this->backupPolicyRetentionDays();
        $nonce = Text::uuid();

        $this->set(compact('tenant', 'jobs', 'backups', 'restoreTargets', 'defaultRetentionDays', 'nonce'));
    }

    /**
     * Queue an encrypted tenant JSON logical archive from Platform Admin.
     *
     * @param string $slug Tenant slug
     * @return \Cake\Http\Response|null
     */
    public function createBackup(string $slug)
    {
        $this->request->allowMethod(['post']);
        $tenant = $this->tenantBySlug($slug);
        if ($tenant === null) {
            throw new NotFoundException('Tenant was not found.');
        }

        $retentionDays = max(1, min(365, (int)$this->request->getData(
            'retention_days',
            $this->backupPolicyRetentionDays(),
        )));
        $nonce = (string)$this->request->getData('nonce', Text::uuid());
        try {
            $job = $this->jobEnqueuer()->enqueue(
                PlatformJobRunner::JOB_TENANT_BACKUP,
                (string)$tenant['id'],
                $this->platformAdmin['id'] ?? null,
                [
                    'tenant_slug' => (string)$tenant['slug'],
                    'retention_days' => $retentionDays,
                ],
                sprintf('tenant_backup:%s:%s', $tenant['slug'], $nonce),
                'platform admin tenant backup request',
                $this->auditOptions((string)$tenant['id']),
            );
            $this->Flash->success(__('Tenant backup has been queued: {0}', $job['id']));
        } catch (RuntimeException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect([
            'prefix' => 'PlatformAdmin',
            'controller' => 'Tenants',
            'action' => 'backups',
            $tenant['slug'],
        ]);
    }

    /**
     * Queue a guarded tenant restore request.
     *
     * @param string $slug Tenant slug
     * @param string $backupId Backup row id
     * @return \Cake\Http\Response|null
     */
    public function restoreBackup(string $slug, string $backupId)
    {
        $this->request->allowMethod(['post']);
        $tenant = $this->tenantBySlug($slug);
        if ($tenant === null) {
            throw new NotFoundException('Tenant was not found.');
        }

        try {
            $targetSlug = strtolower(trim((string)$this->request->getData('target_tenant_slug', $tenant['slug'])));
            $crossTenant = $targetSlug !== (string)$tenant['slug'];
            $target = $crossTenant ? $this->tenantBySlug($targetSlug) : $tenant;
            if ($target === null) {
                throw new BadRequestException('Restore target tenant was not found.');
            }
            if ((string)$target['status'] !== 'suspended') {
                throw new BadRequestException(
                    'The restore target tenant must be suspended before a platform-admin restore is queued.',
                );
            }
            $backup = $this->tenantBackupById((string)$tenant['id'], $backupId);
            $this->assertUsableBackup($backup);
            $reason = $this->validateStepUpAction(sprintf('RESTORE %s', $target['slug']));
            $nonce = (string)$this->request->getData('nonce', Text::uuid());
            $parameters = [
                'tenant_slug' => (string)$target['slug'],
                'backup_id' => $backupId,
                'mode' => $crossTenant
                    ? TenantRestoreService::MODE_CROSS_TENANT
                    : TenantRestoreService::MODE_SAME_TENANT,
                'source_tenant_id' => (string)$tenant['id'],
                'source_tenant_slug' => (string)$tenant['slug'],
            ];
            if ($crossTenant) {
                $parameters['target_tenant_slug'] = (string)$target['slug'];
            }
            $job = $this->jobEnqueuer()->enqueue(
                PlatformJobRunner::JOB_TENANT_RESTORE,
                (string)$target['id'],
                $this->platformAdmin['id'] ?? null,
                $parameters,
                sprintf('tenant_restore:%s:%s:%s', $target['slug'], $backupId, $nonce),
                $reason,
                $this->auditOptions((string)$target['id']),
            );
            $this->Flash->success($crossTenant
                ? __('Cross-tenant restore into "{0}" has been queued: {1}', $target['slug'], $job['id'])
                : __('Tenant restore has been queued: {0}', $job['id']));
        } catch (BadRequestException | RuntimeException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect([
            'prefix' => 'PlatformAdmin',
            'controller' => 'Tenants',
            'action' => 'backups',
            $tenant['slug'],
        ]);
    }

    /**
     * Import a legacy passphrase-encrypted .kmpbackup archive as a managed backup.
     *
     * Accepts archives produced by the retired tenant self-service system and
     * by upstream (ansteorra/KMP) installs. The archive is decrypted with the
     * supplied passphrase, re-encrypted with the tenant's envelope keys, and
     * recorded as a normal managed backup — restorable through the standard
     * guarded restore flow.
     *
     * @param string $slug Tenant slug
     * @return \Cake\Http\Response|null
     */
    public function importLegacyBackup(string $slug)
    {
        $this->request->allowMethod(['post']);
        $tenant = $this->tenantBySlug($slug);
        if ($tenant === null) {
            throw new NotFoundException('Tenant was not found.');
        }

        try {
            $upload = $this->request->getData('backup_file');
            if (!$upload instanceof UploadedFileInterface || $upload->getError() !== UPLOAD_ERR_OK) {
                throw new BadRequestException('Choose a valid legacy backup file to import.');
            }
            $size = $upload->getSize();
            if ($size !== null && $size > self::MAX_LEGACY_IMPORT_BYTES) {
                throw new BadRequestException('The legacy backup file exceeds the import size limit.');
            }
            $passphrase = (string)$this->request->getData('passphrase', '');
            if ($passphrase === '') {
                throw new BadRequestException('Enter the encryption key the backup was created with.');
            }
            $reason = $this->validateStepUpAction(sprintf('IMPORT %s', $tenant['slug']));

            $stream = $upload->getStream();
            $stream->rewind();
            $data = $stream->getContents();
            if ($data === '' || strlen($data) > self::MAX_LEGACY_IMPORT_BYTES) {
                throw new BadRequestException('The legacy backup file was empty or exceeds the import size limit.');
            }

            $backupService = new BackupService();
            $backupService->validateImportHeader($data, $passphrase);
            $logicalArchive = $backupService->decryptToLogicalArchive($data, $passphrase);
            try {
                $result = $this->tenantBackupService()->importArchiveAsBackup(
                    (string)$tenant['slug'],
                    $logicalArchive,
                    $this->backupPolicyRetentionDays(),
                    ['original_filename' => mb_substr((string)$upload->getClientFilename(), 0, 160)],
                );
            } finally {
                sodium_memzero($logicalArchive);
            }
            $this->auditService()->record(
                'tenant_backup.legacy_imported',
                $this->platformAdmin['id'] ?? null,
                'tenant_backup',
                $result->backupId,
                $reason,
                [
                    'tenant_slug' => (string)$tenant['slug'],
                    'original_filename' => (string)$upload->getClientFilename(),
                ],
                false,
                $this->auditOptions((string)$tenant['id']),
            );
            $this->Flash->success(__(
                'Legacy backup imported as managed backup {0}. Restore it through the guarded restore flow.',
                $result->backupId,
            ));
        } catch (BadRequestException | RuntimeException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect([
            'prefix' => 'PlatformAdmin',
            'controller' => 'Tenants',
            'action' => 'backups',
            $tenant['slug'],
        ]);
    }

    /**
     * Download an encrypted tenant .kmpbackup archive after audit and step-up.
     *
     * @param string $slug Tenant slug
     * @param string $backupId Backup row id
     * @return \Cake\Http\Response|null
     */
    public function downloadBackup(string $slug, string $backupId)
    {
        $this->request->allowMethod(['post']);
        $tenant = $this->tenantBySlug($slug);
        if ($tenant === null) {
            throw new NotFoundException('Tenant was not found.');
        }

        try {
            $backup = $this->tenantBackupById((string)$tenant['id'], $backupId);
            $this->assertUsableBackup($backup, [
                TenantBackupService::BACKUP_TYPE,
                TenantBackupService::LEGACY_BACKUP_TYPE,
                'pg_dump',
            ]);
            $reason = $this->validateStepUpAction(sprintf('DOWNLOAD %s', $tenant['slug']));
            $download = $this->stageBackupDownload(
                $backup,
                BackupStorageFactory::tenantArchive((string)$backup['backup_type']),
                'tenant-' . (string)$tenant['slug'],
            );
            register_shutdown_function(static function () use ($download): void {
                if (is_file($download['path'])) {
                    unlink($download['path']);
                }
            });
            $this->auditService()->record(
                'tenant_backup.download',
                $this->platformAdmin['id'] ?? null,
                'tenant_backup',
                $backupId,
                $reason,
                [
                    'backup_format' => 'encrypted_' . (string)$backup['backup_type'],
                    'tenant_slug' => (string)$tenant['slug'],
                ],
                false,
                $this->auditOptions((string)$tenant['id']),
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

        return $this->redirect([
            'prefix' => 'PlatformAdmin',
            'controller' => 'Tenants',
            'action' => 'backups',
            $tenant['slug'],
        ]);
    }

    /**
     * Download a backup-scoped recovery key after audit and operator step-up.
     *
     * @param string $slug Tenant slug
     * @param string $backupId Backup row id
     * @return \Cake\Http\Response|null
     */
    public function downloadBackupRecoveryKey(string $slug, string $backupId)
    {
        $this->request->allowMethod(['post']);
        $tenant = $this->tenantBySlug($slug);
        if ($tenant === null) {
            throw new NotFoundException('Tenant was not found.');
        }

        try {
            $backup = $this->tenantBackupById((string)$tenant['id'], $backupId);
            $this->assertUsableBackup($backup, ['json']);
            $reason = $this->validateStepUpAction(sprintf('DOWNLOAD KEY %s', $tenant['slug']));
            $export = $this->exportTenantBackupRecoveryKey($backup, $tenant);
            $this->platform()->execute(
                'UPDATE tenant_backups
                    SET recovery_key_exported_at = COALESCE(recovery_key_exported_at, :now),
                        recovery_key_exported_by = COALESCE(recovery_key_exported_by, :by),
                        modified_at = :now
                  WHERE id = :backupId',
                [
                    'now' => DateTime::now('UTC'),
                    'by' => 'platform:' . (string)($this->platformAdmin['id'] ?? 'unknown'),
                    'backupId' => $backupId,
                ],
                ['now' => 'datetime'],
            );
            $this->auditService()->record(
                'tenant_backup.recovery_key_exported',
                $this->platformAdmin['id'] ?? null,
                'tenant_backup',
                $backupId,
                $reason,
                [
                    'backup_format' => 'encrypted_json',
                    'tenant_slug' => (string)$tenant['slug'],
                ],
                false,
                $this->auditOptions((string)$tenant['id']),
            );

            return $this->recoveryKeyDownloadResponse($export);
        } catch (BadRequestException | RuntimeException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect([
            'prefix' => 'PlatformAdmin',
            'controller' => 'Tenants',
            'action' => 'backups',
            $tenant['slug'],
        ]);
    }

    /**
     * Delete an encrypted tenant backup object after audit and operator step-up.
     *
     * @param string $slug Tenant slug
     * @param string $backupId Backup row id
     * @return \Cake\Http\Response|null
     */
    public function deleteBackup(string $slug, string $backupId)
    {
        $this->request->allowMethod(['post']);
        $tenant = $this->tenantBySlug($slug);
        if ($tenant === null) {
            throw new NotFoundException('Tenant was not found.');
        }

        try {
            $backup = $this->tenantBackupById((string)$tenant['id'], $backupId);
            $this->assertDeletableBackup($backup);
            $reason = $this->validateStepUpAction(sprintf('DELETE BACKUP %s', $tenant['slug']));
            (new BackupDeletionService($this->platform(), $this->auditService()))->deleteTenant(
                $backup,
                BackupStorageFactory::tenantArchive((string)$backup['backup_type']),
                $this->platformAdmin['id'] ?? null,
                $reason,
                [
                    'backup_format' => 'encrypted_' . (string)$backup['backup_type'],
                    'tenant_slug' => (string)$tenant['slug'],
                ],
                $this->auditOptions((string)$tenant['id']),
            );
            $this->Flash->success(__('Tenant backup archive deleted. Audit metadata has been retained.'));
        } catch (BadRequestException | RuntimeException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect([
            'prefix' => 'PlatformAdmin',
            'controller' => 'Tenants',
            'action' => 'backups',
            $tenant['slug'],
        ]);
    }

    /**
     * View and edit safe tenant-scoped platform configuration.
     *
     * @param string $slug Tenant slug
     * @return \Cake\Http\Response|null|void
     */
    public function config(string $slug)
    {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,78}[a-z0-9])?$/', $slug)) {
            throw new NotFoundException('Tenant was not found.');
        }

        $tenant = $this->tenantBySlug($slug);
        if ($tenant === null) {
            throw new NotFoundException('Tenant was not found.');
        }

        $schema = new TenantConfigSchema();
        $tenantConfig = isset($tenant['tenant_config']) ? (string)$tenant['tenant_config'] : null;
        $safeConfig = $schema->safeConfigFromJson($tenantConfig);
        $formData = $schema->toFormData($safeConfig);
        $errors = [];

        if ($this->request->is(['post', 'put', 'patch'])) {
            try {
                $newConfig = $schema->buildFromFormData($this->request->getData());
                $this->saveTenantConfig($tenant, $safeConfig, $newConfig);
                $this->Flash->success(__('Tenant configuration has been saved.'));

                return $this->redirect([
                    'prefix' => 'PlatformAdmin',
                    'controller' => 'Tenants',
                    'action' => 'view',
                    $slug,
                ]);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
                $formData = array_merge($formData, $this->request->getData());
                $this->Flash->error(__($exception->getMessage()));
            }
        }

        $this->set(compact('tenant', 'safeConfig', 'formData', 'errors'));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tenantBySlug(string $slug): ?array
    {
        $row = $this->platform()->execute(
            'SELECT id, slug, display_name, status, region, primary_host, db_server, db_name, db_role,
                    schema_version, tenant_config, queue_concurrency_limit, created_at, activated_at, suspended_at,
                    archived_at, modified_at
               FROM tenants
              WHERE slug = :slug
              LIMIT 1',
            ['slug' => $slug],
        )->fetch('assoc');

        return is_array($row) ? $row : null;
    }

    /**
     * Build the managed tenant backup service (same wiring as TenantBackupCommand).
     *
     * @return \App\Services\Backups\TenantBackupService
     */
    private function tenantBackupService(): TenantBackupService
    {
        $secretStore = SecretStoreFactory::fromConfig();

        return new TenantBackupService(
            $this->platform(),
            $secretStore,
            new JsonTenantBackupDumper(new TenantConnectionManager($secretStore)),
            new TenantBackupEncryptor(),
            BackupStorageFactory::tenant(),
        );
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
    private function tenantBackupById(string $tenantId, string $backupId): array
    {
        $row = $this->platform()->execute(
            'SELECT id, tenant_id, backup_type, status, object_uri, object_size_bytes,
                    object_sha256, encryption_algorithm, wrapped_dek, wrapped_dek_key_name,
                    wrapped_dek_key_version, wrapped_dek_metadata, retention_until
               FROM tenant_backups
              WHERE tenant_id = :tenantId
                AND id = :backupId
              LIMIT 1',
            ['tenantId' => $tenantId, 'backupId' => $backupId],
        )->fetch('assoc');
        if (!is_array($row)) {
            throw new NotFoundException('Backup was not found.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function configFormData(array $data): array
    {
        $fields = [
            'documents_blob_container',
            'documents_blob_prefix',
            'email_mode',
            'email_from_address',
            'email_from_name',
            'email_endpoint_url',
            'email_azure_connection_string_secret_ref',
            'email_azure_api_version',
            'email_api_secret_ref',
            'email_smtp_host',
            'email_smtp_port',
            'email_smtp_username',
            'email_smtp_password_secret_ref',
            'email_smtp_tls',
            'features_json',
            'integration_endpoints_json',
            'integration_secret_refs_json',
        ];
        $config = [];
        foreach ($fields as $field) {
            $config[$field] = $data[$field] ?? '';
        }

        return $config;
    }

    /**
     * @return array<string, string>
     */
    private function defaultTenantForm(): array
    {
        return [
            'slug' => '',
            'display_name' => '',
            'status' => 'provisioning',
            'region' => 'us',
            'primary_host' => '',
            'initial_super_user_email' => '',
            'db_server' => (string)($this->platform()->config()['host'] ?? 'localhost'),
            'db_name' => '',
            'db_role' => '',
            'queue_concurrency_limit' => '5',
        ];
    }

    /**
     * @param array<string, mixed> $tenant
     * @return array<string, string>
     */
    private function tenantFormFromRow(array $tenant): array
    {
        return [
            'slug' => (string)($tenant['slug'] ?? ''),
            'display_name' => (string)($tenant['display_name'] ?? ''),
            'status' => (string)($tenant['status'] ?? 'provisioning'),
            'region' => (string)($tenant['region'] ?? 'us'),
            'primary_host' => (string)($tenant['primary_host'] ?? ''),
            'initial_super_user_email' => '',
            'db_server' => (string)($tenant['db_server'] ?? ''),
            'db_name' => (string)($tenant['db_name'] ?? ''),
            'db_role' => (string)($tenant['db_role'] ?? ''),
            'queue_concurrency_limit' => (string)($tenant['queue_concurrency_limit'] ?? '5'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function tenantDataFromRequest(
        array $data,
        ?string $existingSlug = null,
        ?string $currentStatus = null,
    ): array {
        $slug = $existingSlug ?? strtolower(trim((string)($data['slug'] ?? '')));
        $this->assertValidSlug($slug);
        if (in_array($slug, ['admin', 'api', 'assets', 'backups', 'objects', 'platform', 'work'], true)) {
            throw new InvalidArgumentException('Tenant slug is reserved.');
        }

        $displayName = trim((string)($data['display_name'] ?? ''));
        if ($displayName === '' || mb_strlen($displayName) > 120) {
            throw new InvalidArgumentException('Tenant display name is required and must be 120 characters or fewer.');
        }
        $status = $existingSlug === null ? 'provisioning' : (string)$currentStatus;
        if (!in_array($status, ['provisioning', 'active', 'suspended', 'archived'], true)) {
            throw new InvalidArgumentException('Tenant status must be provisioning, active, suspended, or archived.');
        }
        $region = trim((string)($data['region'] ?? 'us'));
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/', $region)) {
            throw new InvalidArgumentException('Tenant region must be a safe region identifier.');
        }
        $host = strtolower(trim((string)($data['primary_host'] ?? '')));
        if ($host === '' || !preg_match('/^[a-z0-9.-]{3,255}$/', $host) || str_contains($host, '..')) {
            throw new InvalidArgumentException('Tenant primary host must be a valid hostname.');
        }
        $this->initialSuperUserEmailFromRequest($data, $existingSlug === null);
        $dbServer = trim((string)($data['db_server'] ?? ''));
        if ($dbServer === '' || mb_strlen($dbServer) > 255) {
            throw new InvalidArgumentException('Tenant database server is required.');
        }

        $defaultDbName = 'kmp_tenant_' . str_replace('-', '_', $slug);
        $dbName = trim((string)($data['db_name'] ?? '')) ?: $defaultDbName;
        $dbRole = trim((string)($data['db_role'] ?? '')) ?: $dbName . '_role';
        $this->assertValidIdentifier($dbName, 'database name');
        $this->assertValidIdentifier($dbRole, 'database role');

        $queueLimit = (int)($data['queue_concurrency_limit'] ?? 5);
        if ($queueLimit < 1 || $queueLimit > 100) {
            throw new InvalidArgumentException('Queue concurrency limit must be between 1 and 100.');
        }

        return [
            'slug' => $slug,
            'display_name' => $displayName,
            'status' => $status,
            'region' => strtolower($region),
            'primary_host' => $host,
            'db_server' => $dbServer,
            'db_name' => $dbName,
            'db_role' => $dbRole,
            'queue_concurrency_limit' => $queueLimit,
        ];
    }

    /**
     * Validate and normalize the initial tenant super-user email.
     *
     * @param array<string, mixed> $data Request data
     */
    private function initialSuperUserEmailFromRequest(array $data, bool $required): ?string
    {
        $email = strtolower(trim((string)($data['initial_super_user_email'] ?? '')));
        if ($email === '' && !$required) {
            return null;
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 50) {
            throw new InvalidArgumentException(
                'Initial tenant super user email is required and must be a valid email address.',
            );
        }

        return $email;
    }

    /**
     * Validate platform tenant slug format.
     */
    private function assertValidSlug(string $slug): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,78}[a-z0-9]$/', $slug)) {
            throw new InvalidArgumentException(
                'Tenant slug must be 3-80 lowercase letters, numbers, or hyphens.',
            );
        }
    }

    /**
     * Validate a PostgreSQL identifier used for tenant database resources.
     */
    private function assertValidIdentifier(string $identifier, string $label): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{2,62}$/', $identifier)) {
            throw new InvalidArgumentException(sprintf('Tenant %s must be a safe PostgreSQL identifier.', $label));
        }
    }

    /**
     * @param array<string, mixed> $tenantData
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function createTenant(array $tenantData, array $config): array
    {
        $now = (new DateTime('now'))->format('Y-m-d H:i:s');
        $this->ensureTenantSecrets((string)$tenantData['slug']);
        $tenant = $tenantData + [
            'id' => Text::uuid(),
            'key_vault_prefix' => sprintf('tenant.%s', $tenantData['slug']),
            'schema_version' => null,
            'feature_flags' => '{}',
            'tenant_config' => $this->encodeConfig($config),
            'created_at' => $now,
            'activated_at' => $tenantData['status'] === 'active' ? $now : null,
            'suspended_at' => $tenantData['status'] === 'suspended' ? $now : null,
            'archived_at' => $tenantData['status'] === 'archived' ? $now : null,
            'modified_at' => $now,
        ];

        $this->platform()->transactional(function () use ($tenant, $tenantData, $config, $now): void {
            $this->platform()->insert('tenants', $tenant);
            $this->upsertPrimaryHost((string)$tenant['id'], (string)$tenantData['primary_host'], $now);
            $this->auditTenantRegistryChange('tenant.created', $tenant, [], $tenantData, $config);
        });
        TenantHostResolver::clearCache();

        return $tenant;
    }

    /**
     * Queue out-of-band full tenant provisioning.
     *
     * @param array<string, mixed> $tenant
     * @param array<string, mixed> $tenantData
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function enqueueTenantProvisioningJob(
        array $tenant,
        array $tenantData,
        array $config,
        string $initialSuperUserEmail,
    ): array {
        $nonce = (string)$this->request->getData('nonce', Text::uuid());

        return $this->jobEnqueuer()->enqueue(
            PlatformJobRunner::JOB_TENANT_PROVISION,
            (string)$tenant['id'],
            $this->platformAdmin['id'] ?? null,
            [
                'slug' => (string)$tenantData['slug'],
                'display_name' => (string)$tenantData['display_name'],
                'primary_host' => (string)$tenantData['primary_host'],
                'initial_super_user_email' => $initialSuperUserEmail,
                'db_server' => (string)$tenantData['db_server'],
                'db_name' => (string)$tenantData['db_name'],
                'db_role' => (string)$tenantData['db_role'],
                'blob_container' => (string)($config['documents']['blob_container'] ?? 'tenant-' . $tenantData['slug']),
                'region' => (string)$tenantData['region'],
                'queue_concurrency_limit' => (int)$tenantData['queue_concurrency_limit'],
                'tenantConfig' => $config,
                'status' => 'active',
                'create_database' => true,
                'skip_create_database' => false,
                'run_migrations' => true,
                'smoke_table' => 'members',
            ],
            sprintf('tenant_provision:%s:%s', $tenantData['slug'], $nonce),
            'platform admin tenant provisioning request',
            $this->auditOptions((string)$tenant['id']),
        );
    }

    /**
     * Ensure runtime tenant routing can resolve the DB password secret after create.
     */
    private function ensureTenantSecrets(string $slug): void
    {
        $store = SecretStoreFactory::fromConfig();
        if (!$store instanceof WritableSecretStoreInterface) {
            throw new RuntimeException(
                'Configured secret store is not writable; tenant secrets cannot be created.',
            );
        }

        foreach ([sprintf('tenant.%s.db.password', $slug), sprintf('tenant.%s.kek', $slug)] as $secretName) {
            $existing = $store->get($secretName);
            if ($existing !== null && !$existing->isEmpty()) {
                continue;
            }
            $store->put($secretName, new SensitiveString($this->generateDatabasePassword()));
        }
    }

    /**
     * Generate a random URL-safe tenant database password.
     */
    private function generateDatabasePassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * @param array<string, mixed> $tenant
     * @param array<string, mixed> $tenantData
     * @param array<string, mixed> $oldConfig
     * @param array<string, mixed> $newConfig
     */
    private function updateTenant(array $tenant, array $tenantData, array $oldConfig, array $newConfig): void
    {
        $now = (new DateTime('now'))->format('Y-m-d H:i:s');
        $activatedAt = $tenantData['status'] === 'active' ? ($tenant['activated_at'] ?? $now) : $tenant['activated_at'];
        $suspendedAt = $tenantData['status'] === 'suspended' ? ($tenant['suspended_at'] ?? $now) : null;
        $archivedAt = $tenantData['status'] === 'archived' ? ($tenant['archived_at'] ?? $now) : null;
        $update = $tenantData + [
            'tenant_config' => $this->encodeConfig($newConfig),
            'activated_at' => $activatedAt,
            'suspended_at' => $suspendedAt,
            'archived_at' => $archivedAt,
            'modified_at' => $now,
        ];
        unset($update['slug']);

        $this->platform()->transactional(function () use (
            $tenant,
            $tenantData,
            $oldConfig,
            $newConfig,
            $update,
            $now,
        ): void {
            $this->platform()->update('tenants', $update, ['id' => $tenant['id']]);
            $this->upsertPrimaryHost((string)$tenant['id'], (string)$tenantData['primary_host'], $now);
            $this->auditTenantRegistryChange('tenant.updated', $tenant, $oldConfig, $tenantData, $newConfig);
        });
        TenantHostResolver::clearCache();
    }

    /**
     * Apply a guarded tenant lifecycle transition.
     *
     * @return \Cake\Http\Response|null
     */
    private function transitionLifecycle(string $slug, string $targetStatus, string $confirmationVerb)
    {
        $this->request->allowMethod(['post']);
        $tenant = $this->tenantBySlug($slug);
        if ($tenant === null) {
            throw new NotFoundException('Tenant was not found.');
        }

        try {
            $reason = $this->validateStepUpAction(sprintf('%s %s', $confirmationVerb, $slug));
            (new TenantLifecycleService($this->platform()))->transition(
                (string)$tenant['id'],
                $targetStatus,
                isset($this->platformAdmin['id']) ? (string)$this->platformAdmin['id'] : null,
                $reason,
                $this->auditOptions((string)$tenant['id']),
            );
            $this->Flash->success(__('Tenant status changed to {0}.', $targetStatus));
        } catch (BadRequestException | RuntimeException $exception) {
            $this->Flash->error(__($exception->getMessage()));
        }

        return $this->redirect([
            'prefix' => 'PlatformAdmin',
            'controller' => 'Tenants',
            'action' => 'view',
            $slug,
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function encodeConfig(array $config): string
    {
        $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidArgumentException('Unable to encode tenant configuration JSON.');
        }

        return $json;
    }

    /**
     * Create or update the active primary host row for a tenant.
     */
    private function upsertPrimaryHost(string $tenantId, string $host, string $now): void
    {
        $existing = $this->platform()->execute(
            'SELECT id FROM tenant_hosts WHERE tenant_id = :tenantId AND is_primary = :primary LIMIT 1',
            ['tenantId' => $tenantId, 'primary' => 1],
        )->fetch('assoc');

        if (is_array($existing)) {
            $this->platform()->update('tenant_hosts', [
                'host' => $host,
                'host_normalized' => $host,
                'status' => 'active',
            ], ['id' => $existing['id']]);

            return;
        }

        $this->platform()->insert('tenant_hosts', [
            'id' => Text::uuid(),
            'tenant_id' => $tenantId,
            'host' => $host,
            'host_normalized' => $host,
            'is_primary' => 1,
            'status' => 'active',
            'created_at' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $tenant
     * @param array<string, mixed> $oldConfig
     * @param array<string, mixed> $tenantData
     * @param array<string, mixed> $newConfig
     */
    private function auditTenantRegistryChange(
        string $action,
        array $tenant,
        array $oldConfig,
        array $tenantData,
        array $newConfig,
    ): void {
        (new PlatformAuditService($this->platform()))->record(
            $action,
            isset($this->platformAdmin['id']) ? (string)$this->platformAdmin['id'] : null,
            'tenant',
            (string)$tenant['id'],
            'platform admin tenant registry update',
            [
                'slug' => (string)($tenant['slug'] ?? $tenantData['slug']),
                'registry' => $tenantData,
                'before_config' => $oldConfig,
                'after_config' => $newConfig,
            ],
            false,
            [
                'tenantId' => (string)$tenant['id'],
                'ipAddress' => $this->request->clientIp(),
                'userAgent' => $this->request->getHeaderLine('User-Agent') ?: 'platform-admin',
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tenantHosts(string $tenantId): array
    {
        return $this->platform()->execute(
            'SELECT host, is_primary, status, created_at
               FROM tenant_hosts
              WHERE tenant_id = :tenantId
           ORDER BY is_primary DESC, host ASC',
            ['tenantId' => $tenantId],
        )->fetchAll('assoc');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tenantJobs(string $tenantId): array
    {
        return $this->platform()->execute(
            'SELECT id, job_type, status, created_at, started_at, finished_at,
                    CASE WHEN last_error IS NULL OR last_error = :empty THEN 0 ELSE 1 END AS has_error
               FROM platform_jobs
              WHERE tenant_id = :tenantId
           ORDER BY created_at DESC
              LIMIT 20',
            ['tenantId' => $tenantId, 'empty' => ''],
        )->fetchAll('assoc');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestTenantJob(string $tenantId, string $jobType): ?array
    {
        $row = $this->platform()->execute(
            'SELECT id, job_type, status, created_at, started_at, finished_at,
                    CASE WHEN last_error IS NULL OR last_error = :empty THEN 0 ELSE 1 END AS has_error
               FROM platform_jobs
              WHERE tenant_id = :tenantId
                AND job_type = :jobType
           ORDER BY created_at DESC
              LIMIT 1',
            ['tenantId' => $tenantId, 'jobType' => $jobType, 'empty' => ''],
        )->fetch('assoc');

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tenantBackups(string $tenantId): array
    {
        return $this->platform()->execute(
            'SELECT id, backup_type, status, object_uri, object_size_bytes, encryption_algorithm,
                    created_at, completed_at, retention_until
               FROM tenant_backups
              WHERE tenant_id = :tenantId
           ORDER BY created_at DESC
              LIMIT 20',
            ['tenantId' => $tenantId],
        )->fetchAll('assoc');
    }

    /**
     * @return array<string, int|float|bool>
     */
    private function tenantMetrics(string $tenantId): array
    {
        $defaults = [
            'available' => false,
            'request_count' => 0,
            'error_count' => 0,
            'server_error_count' => 0,
            'slow_request_count' => 0,
            'average_duration_ms' => 0,
            'duration_max_ms' => 0,
            'error_rate' => 0.0,
        ];
        try {
            $row = $this->platform()->execute(
                'SELECT COALESCE(SUM(request_count), 0) AS request_count,
                        COALESCE(SUM(error_count), 0) AS error_count,
                        COALESCE(SUM(server_error_count), 0) AS server_error_count,
                        COALESCE(SUM(slow_request_count), 0) AS slow_request_count,
                        COALESCE(SUM(duration_total_ms), 0) AS duration_total_ms,
                        COALESCE(MAX(duration_max_ms), 0) AS duration_max_ms
                   FROM tenant_request_metrics_hourly
                  WHERE tenant_id = :tenantId
                    AND metric_hour >= :since',
                [
                    'tenantId' => $tenantId,
                    'since' => gmdate('Y-m-d H:i:s', time() - 24 * 60 * 60),
                ],
            )->fetch('assoc');
        } catch (Throwable $exception) {
            Log::warning(sprintf('Tenant metric summary unavailable: %s', $exception::class));

            return $defaults;
        }
        if (!is_array($row)) {
            return $defaults;
        }

        $requests = (int)$row['request_count'];
        $errors = (int)$row['error_count'];

        return [
            'available' => true,
            'request_count' => $requests,
            'error_count' => $errors,
            'server_error_count' => (int)$row['server_error_count'],
            'slow_request_count' => (int)$row['slow_request_count'],
            'average_duration_ms' => $requests > 0
                ? (int)round((int)$row['duration_total_ms'] / $requests)
                : 0,
            'duration_max_ms' => (int)$row['duration_max_ms'],
            'error_rate' => $requests > 0 ? round($errors / $requests * 100, 2) : 0.0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tenantMetricRoutes(string $tenantId): array
    {
        try {
            return $this->platform()->execute(
                'SELECT route_name,
                        SUM(request_count) AS request_count,
                        SUM(error_count) AS error_count,
                        SUM(server_error_count) AS server_error_count,
                        SUM(duration_total_ms) AS duration_total_ms,
                        MAX(duration_max_ms) AS duration_max_ms
                   FROM tenant_request_metrics_hourly
                  WHERE tenant_id = :tenantId
                    AND metric_hour >= :since
               GROUP BY route_name
               ORDER BY SUM(error_count) DESC, SUM(request_count) DESC
                  LIMIT 12',
                [
                    'tenantId' => $tenantId,
                    'since' => gmdate('Y-m-d H:i:s', time() - 24 * 60 * 60),
                ],
            )->fetchAll('assoc');
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tenantMetricHours(string $tenantId): array
    {
        try {
            return $this->platform()->execute(
                'SELECT metric_hour,
                        SUM(request_count) AS request_count,
                        SUM(error_count) AS error_count,
                        SUM(server_error_count) AS server_error_count,
                        SUM(duration_total_ms) AS duration_total_ms
                   FROM tenant_request_metrics_hourly
                  WHERE tenant_id = :tenantId
                    AND metric_hour >= :since
               GROUP BY metric_hour
               ORDER BY metric_hour ASC',
                [
                    'tenantId' => $tenantId,
                    'since' => gmdate('Y-m-d H:i:s', time() - 24 * 60 * 60),
                ],
            )->fetchAll('assoc');
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jobEvents(string $jobId): array
    {
        try {
            return $this->platform()->execute(
                'SELECT sequence_number, event_level, event_code, message, created_at
                   FROM platform_job_events
                  WHERE platform_job_id = :jobId
               ORDER BY sequence_number ASC
                  LIMIT 100',
                ['jobId' => $jobId],
            )->fetchAll('assoc');
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $tenant Tenant row
     * @param array<string, mixed> $oldConfig Previous safe config
     * @param array<string, mixed> $newConfig New safe config
     * @return void
     */
    private function saveTenantConfig(array $tenant, array $oldConfig, array $newConfig): void
    {
        $json = json_encode($newConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidArgumentException('Unable to encode tenant configuration JSON.');
        }

        $connection = $this->platform();
        $connection->transactional(function () use ($connection, $tenant, $oldConfig, $newConfig, $json): void {
            $connection->update('tenants', [
                'tenant_config' => $json,
                'modified_at' => (new DateTime('now'))->format('Y-m-d H:i:s'),
            ], ['id' => $tenant['id']]);

            $this->auditTenantConfigChange($tenant, $oldConfig, $newConfig);
        });
    }

    /**
     * @param array<string, mixed> $tenant Tenant row
     * @param array<string, mixed> $oldConfig Previous safe config
     * @param array<string, mixed> $newConfig New safe config
     * @return void
     */
    private function auditTenantConfigChange(array $tenant, array $oldConfig, array $newConfig): void
    {
        try {
            (new PlatformAuditService($this->platform()))->record(
                'tenant.config.updated',
                isset($this->platformAdmin['id']) ? (string)$this->platformAdmin['id'] : null,
                'tenant',
                (string)$tenant['id'],
                'platform admin tenant config update',
                [
                    'slug' => (string)$tenant['slug'],
                    'before' => $oldConfig,
                    'after' => $newConfig,
                ],
                false,
                [
                    'tenantId' => (string)$tenant['id'],
                    'ipAddress' => $this->request->clientIp(),
                    'userAgent' => $this->request->getHeaderLine('User-Agent') ?: 'platform-admin',
                ],
            );
        } catch (Throwable $exception) {
            Log::warning('Unable to record tenant config audit event: ' . $exception->getMessage());
        }
    }
}
