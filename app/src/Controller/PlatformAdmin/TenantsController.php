<?php
declare(strict_types=1);

namespace App\Controller\PlatformAdmin;

use App\Services\BackupStorageService;
use App\Services\Platform\PlatformAdminJobEnqueuer;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\TenantConfigSchema;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SensitiveString;
use App\Services\Secrets\WritableSecretStoreInterface;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\Utility\Text;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class TenantsController extends PlatformAdminAppController
{
    /**
     * List platform tenants without database or secret metadata.
     *
     * @return void
     */
    public function index(): void
    {
        try {
            $tenants = $this->platform()->execute(
                'SELECT id, slug, display_name, status, region, primary_host, schema_version,
                        queue_concurrency_limit, created_at, activated_at, suspended_at, archived_at
                   FROM tenants
               ORDER BY display_name ASC, slug ASC
                  LIMIT 200',
            )->fetchAll('assoc');
        } catch (Throwable) {
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
                $newConfig = $schema->buildFromFormData($this->configFormData($this->request->getData()));
                $tenant = $this->createTenant($tenantData, $newConfig);
                $this->Flash->success(__('Tenant has been created.'));

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
                $tenantData = $this->tenantDataFromRequest($this->request->getData(), (string)$tenant['slug']);
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

        $this->set([
            'tenant' => $tenant,
            'hosts' => $this->tenantHosts((string)$tenant['id']),
            'jobs' => $this->tenantJobs((string)$tenant['id']),
            'backups' => $this->tenantBackups((string)$tenant['id']),
        ]);
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
                'backupJob' => 'tenant_backup_json',
                'restoreJob' => 'tenant_restore_json',
                'empty' => '',
            ],
        )->fetchAll('assoc');
        $backups = $this->tenantBackups((string)$tenant['id']);
        $nonce = Text::uuid();

        $this->set(compact('tenant', 'jobs', 'backups', 'nonce'));
    }

    /**
     * Queue a tenant JSON backup from Platform Admin.
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

        $retentionDays = max(1, min(365, (int)$this->request->getData('retention_days', 30)));
        $nonce = (string)$this->request->getData('nonce', Text::uuid());
        $job = $this->jobEnqueuer()->enqueue(
            'tenant_backup_json',
            (string)$tenant['id'],
            $this->platformAdmin['id'] ?? null,
            [
                'scope' => 'tenant',
                'tenant_slug' => (string)$tenant['slug'],
                'backup_format' => 'kmpbackup_json',
                'retention_days' => $retentionDays,
            ],
            sprintf('tenant_backup_json:%s:%s', $tenant['slug'], $nonce),
            'platform admin tenant backup request',
            $this->auditOptions((string)$tenant['id']),
        );
        $this->Flash->success(__('Tenant backup has been queued: {0}', $job['id']));

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
            if ((string)$tenant['status'] !== 'suspended') {
                throw new BadRequestException('Tenant must be suspended before a platform-admin restore is queued.');
            }
            $backup = $this->tenantBackupById((string)$tenant['id'], $backupId);
            $filename = $this->validatedCompletedKmpBackup($backup);
            $reason = $this->validateStepUpAction(sprintf('RESTORE %s', $tenant['slug']));
            $nonce = (string)$this->request->getData('nonce', Text::uuid());
            $job = $this->jobEnqueuer()->enqueue(
                'tenant_restore_json',
                (string)$tenant['id'],
                $this->platformAdmin['id'] ?? null,
                [
                    'scope' => 'tenant',
                    'tenant_slug' => (string)$tenant['slug'],
                    'backup_id' => $backupId,
                    'object_name' => $filename,
                    'backup_format' => 'kmpbackup_json',
                    'reason' => $reason,
                ],
                sprintf('tenant_restore_json:%s:%s:%s', $tenant['slug'], $backupId, $nonce),
                $reason,
                $this->auditOptions((string)$tenant['id']),
            );
            $this->Flash->success(__('Tenant restore has been queued: {0}', $job['id']));
        } catch (BadRequestException $exception) {
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
            $filename = $this->validatedCompletedKmpBackup($backup);
            $reason = $this->validateStepUpAction(sprintf('DOWNLOAD %s', $tenant['slug']));
            $this->auditService()->record(
                'tenant_backup.download',
                $this->platformAdmin['id'] ?? null,
                'tenant_backup',
                $backupId,
                $reason,
                [
                    'backup_format' => 'kmpbackup_json',
                    'tenant_slug' => (string)$tenant['slug'],
                ],
                false,
                $this->auditOptions((string)$tenant['id']),
            );
            $data = (new BackupStorageService())->read($filename);

            return $this->response
                ->withType('application/octet-stream')
                ->withDownload(basename($filename))
                ->withStringBody($data);
        } catch (BadRequestException $exception) {
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
            'SELECT id, tenant_id, backup_type, status, object_uri
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
    private function tenantDataFromRequest(array $data, ?string $existingSlug = null): array
    {
        $slug = $existingSlug ?? strtolower(trim((string)($data['slug'] ?? '')));
        $this->assertValidSlug($slug);
        if (in_array($slug, ['admin', 'api', 'assets', 'backups', 'objects', 'platform', 'work'], true)) {
            throw new InvalidArgumentException('Tenant slug is reserved.');
        }

        $displayName = trim((string)($data['display_name'] ?? ''));
        if ($displayName === '' || mb_strlen($displayName) > 120) {
            throw new InvalidArgumentException('Tenant display name is required and must be 120 characters or fewer.');
        }
        $status = trim((string)($data['status'] ?? 'provisioning'));
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
        $this->ensureTenantDatabasePasswordSecret((string)$tenantData['slug']);
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

        return $tenant;
    }

    /**
     * Ensure runtime tenant routing can resolve the DB password secret after create.
     */
    private function ensureTenantDatabasePasswordSecret(string $slug): void
    {
        $secretName = sprintf('tenant.%s.db.password', $slug);
        $store = SecretStoreFactory::fromConfig();
        if (!$store instanceof WritableSecretStoreInterface) {
            throw new RuntimeException(
                'Configured secret store is not writable; tenant database password secret cannot be created.',
            );
        }

        $existing = $store->get($secretName);
        if ($existing !== null && !$existing->isEmpty()) {
            return;
        }

        $store->put($secretName, new SensitiveString($this->generateDatabasePassword()));
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
     * @return list<array<string, mixed>>
     */
    private function tenantBackups(string $tenantId): array
    {
        return $this->platform()->execute(
            'SELECT id, backup_type, status, object_size_bytes, created_at, completed_at, retention_until
               FROM tenant_backups
              WHERE tenant_id = :tenantId
           ORDER BY created_at DESC
              LIMIT 20',
            ['tenantId' => $tenantId],
        )->fetchAll('assoc');
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
