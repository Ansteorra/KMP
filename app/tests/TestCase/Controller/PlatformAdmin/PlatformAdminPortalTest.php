<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\PlatformAdmin;

use App\KMP\TenantMetadata;
use App\Model\Entity\Member;
use App\Services\Platform\PlatformTotpVerifier;
use App\Services\Secrets\SecretStoreFactory;
use App\Services\Secrets\SecretStoreInterface;
use App\Services\Secrets\SensitiveString;
use App\Services\Storage\TenantDocumentStorageConfigResolver;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Cake\Core\Configure;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use DateTimeImmutable;

class PlatformAdminPortalTest extends HttpIntegrationTestCase
{
    private mixed $previousPlatformConfig = null;

    /**
     * @var array<string, mixed>
     */
    private array $previousPortalConfig = [];

    /**
     * @var array<string, mixed>
     */
    private array $previousSecretsConfig = [];

    private string $secretFile = '';

    private string $totpSecret = 'JBSWY3DPEHPK3PXP';

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->previousPlatformConfig = ConnectionManager::getConfig('platform');
        $this->previousPortalConfig = (array)Configure::read('Platform.adminPortal', []);
        $this->previousSecretsConfig = (array)Configure::read('Secrets', []);
        $this->configureSecrets();
        $this->resetPlatformConnection();
        $this->createSchema();
        $this->seedPlatformData();
    }

    protected function tearDown(): void
    {
        Configure::write('Platform.adminPortal', $this->previousPortalConfig);
        Configure::write('Secrets', $this->previousSecretsConfig);
        if ($this->secretFile !== '' && file_exists($this->secretFile)) {
            unlink($this->secretFile);
            rmdir(dirname($this->secretFile));
        }
        ConnectionManager::drop('platform');
        if ($this->previousPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
        }
        parent::tearDown();
    }

    public function testDisabledPortalReturnsNotFound(): void
    {
        Configure::write('Platform.adminPortal.enabled', false);

        $this->get('/platform-admin');

        $this->assertResponseCode(404);
    }

    public function testUnauthenticatedRequestIsDeniedWhenPortalEnabled(): void
    {
        $this->enablePortal();

        $this->get('/platform-admin');

        $this->assertRedirectContains('/platform-admin/login');
    }

    public function testTenantUserSessionDoesNotGrantPlatformAdminAccess(): void
    {
        $this->enablePortal();
        $this->session(['Auth' => new Member(['id' => 1, 'email_address' => 'tenant@example.test'])]);

        $this->get('/platform-admin');

        $this->assertRedirectContains('/platform-admin/login');
    }

    public function testLoginRendersForm(): void
    {
        $this->enablePortal();

        $this->get('/platform-admin/login');

        $this->assertResponseOk();
        $this->assertResponseContains('Platform Admin');
        $this->assertResponseContains('MFA code');
    }

    public function testPlatformAdminCanLoginWithPasswordAndTotp(): void
    {
        $this->enablePortal();

        $this->session([]);
        $this->configRequest(['headers' => ['Host' => 'platform.kmp.localhost']]);
        $this->post('/platform-admin/login', [
            'email' => 'admin@example.org',
            'password' => 'TestPassword',
            'totp' => $this->totpCode(),
            'redirect' => '/platform-admin',
        ]);
        $this->assertRedirectContains('/platform-admin');
        $this->assertSession('admin@example.org', 'PlatformAdmin.email');
        $this->assertSession('platform-admin-1', 'PlatformAdmin.id');
        $this->assertSession('platform.kmp.localhost', 'PlatformAdmin.host');

        $this->assertSession('active', 'PlatformAdmin.status');
    }

    public function testPlatformAdminSessionCanViewDashboard(): void
    {
        $this->enablePortal();
        $this->session([
            'PlatformAdmin' => [
                'id' => 'platform-admin-1',
                'email' => 'admin@example.org',
                'status' => 'active',
                'host' => 'platform.kmp.localhost',
            ],
        ]);
        $this->configRequest(['headers' => ['Host' => 'platform.kmp.localhost']]);

        $this->get('/platform-admin');

        $this->assertResponseOk();
        $this->assertResponseContains('Platform Admin Dashboard');
    }

    public function testPendingEnrollmentLoginActivatesUser(): void
    {
        $this->enablePortal();
        $this->platform()->update('platform_users', ['status' => 'pending_enrollment'], ['id' => 'platform-admin-1']);

        $this->configRequest(['headers' => ['Host' => 'platform.kmp.localhost']]);
        $this->post('/platform-admin/login', [
            'email' => 'admin@example.org',
            'password' => 'TestPassword',
            'totp' => $this->totpCode(),
            'redirect' => '/platform-admin',
        ]);

        $this->assertRedirectContains('/platform-admin');
        $row = $this->platform()->execute(
            'SELECT status, totp_enrolled_at FROM platform_users WHERE id = :id',
            ['id' => 'platform-admin-1'],
        )->fetch('assoc');
        $this->assertSame('active', $row['status']);
        $this->assertNotEmpty($row['totp_enrolled_at']);
    }

    public function testInvalidPlatformAdminLoginDoesNotCreateSession(): void
    {
        $this->enablePortal();

        $this->post('/platform-admin/login', [
            'email' => 'admin@example.org',
            'password' => 'wrong',
            'totp' => $this->totpCode(),
            'redirect' => '/platform-admin',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Platform admin login failed.');
        $this->get('/platform-admin');
        $this->assertRedirectContains('/platform-admin/login');
    }

    public function testDetailedPlatformAdminLoginErrorIdentifiesPasswordFailureWhenEnabled(): void
    {
        $this->enablePortal();
        Configure::write('Platform.adminPortal.detailedLoginErrors', true);

        $this->post('/platform-admin/login', [
            'email' => 'admin@example.org',
            'password' => 'wrong',
            'totp' => $this->totpCode(),
            'redirect' => '/platform-admin',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Platform admin password verification failed.');
        $this->assertResponseNotContains('MFA code verification failed.');
    }

    public function testDetailedPlatformAdminLoginErrorShowsSubmittedUnknownEmail(): void
    {
        $this->enablePortal();
        Configure::write('Platform.adminPortal.detailedLoginErrors', true);

        $this->post('/platform-admin/login', [
            'email' => 'wrong@example.org',
            'password' => 'TestPassword',
            'totp' => $this->totpCode(),
            'redirect' => '/platform-admin',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('No platform admin account matched &quot;wrong@example.org&quot;.');
        $this->assertResponseNotContains('admin@example.org');
    }

    public function testGenericPlatformAdminLoginErrorDoesNotShowSubmittedUnknownEmail(): void
    {
        $this->enablePortal();

        $this->post('/platform-admin/login', [
            'email' => 'wrong@example.org',
            'password' => 'TestPassword',
            'totp' => $this->totpCode(),
            'redirect' => '/platform-admin',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Platform admin login failed.');
        $this->assertResponseNotContains('No platform admin account matched');
    }

    public function testDetailedPlatformAdminLoginErrorIdentifiesTotpFailureWhenEnabled(): void
    {
        $this->enablePortal();
        Configure::write('Platform.adminPortal.detailedLoginErrors', true);

        $this->post('/platform-admin/login', [
            'email' => 'admin@example.org',
            'password' => 'TestPassword',
            'totp' => '000000',
            'redirect' => '/platform-admin',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Platform admin password verified, but MFA code verification failed.');
    }

    public function testConfiguredPlatformAdminCanViewDashboardWithScrubbedData(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->get('/platform-admin');

        $this->assertResponseOk();
        $this->assertResponseContains('Platform Admin Dashboard');
        $this->assertResponseContains('Example Tenant');
        $this->assertResponseContains('tenant_backup');
        $this->assertResponseNotContains('>Jobs<');
        $this->assertResponseNotContains('>Schedules<');
        $this->assertResponseNotContains('>Data Console<');
        $this->assertResponseNotContains('db-secret-host');
        $this->assertResponseNotContains('platform-db-password');
        $this->assertResponseNotContains('wrapped-dek-secret');
        $this->assertResponseNotContains('object://secret-path');
        $this->assertResponseNotContains('super-secret-password');
    }

    public function testTenantDetailOmitsDatabaseAndSecretFields(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->get('/platform-admin/tenants/example');

        $this->assertResponseOk();
        $this->assertResponseContains('Example Tenant');
        $this->assertResponseContains('example.test');
        $this->assertResponseNotContains('db-secret-host');
        $this->assertResponseNotContains('platform-db-password');
        $this->assertResponseNotContains('wrapped-dek-secret');
        $this->assertResponseNotContains('object://secret-path');
    }

    public function testPlatformAdminCanCreateTenantWithSafeConfiguration(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->get('/platform-admin/tenants');
        $this->assertResponseOk();
        $this->assertResponseContains('Create tenant');
        $this->get('/platform-admin/tenants/add');
        $this->assertResponseOk();
        $this->assertResponseContains('Tenant super user email');
        $this->assertResponseContains('The user sets their password through Forgot Password.');

        $this->post('/platform-admin/tenants/add', [
            'slug' => 'newkingdom',
            'display_name' => 'New Kingdom',
            'status' => 'provisioning',
            'region' => 'us',
            'primary_host' => 'newkingdom.test',
            'initial_super_user_email' => 'superuser@newkingdom.test',
            'db_server' => 'db.internal',
            'db_name' => '',
            'db_role' => '',
            'queue_concurrency_limit' => '7',
            'documents_blob_container' => 'documents-newkingdom',
            'documents_blob_prefix' => 'tenants/newkingdom',
            'email_mode' => 'resend',
            'email_from_address' => 'no-reply@newkingdom.test',
            'email_from_name' => 'New Kingdom',
            'email_endpoint_url' => 'https://api.resend.com/emails',
            'email_api_secret_ref' => 'tenant.newkingdom.email-api-key',
            'features_json' => '',
            'integration_endpoints_json' => '{"roster":"https://integrations.newkingdom.test/roster"}',
            'integration_secret_refs_json' => '{"roster":"tenant.newkingdom.roster-token"}',
            'nonce' => 'newkingdom-provision',
        ]);

        $this->assertRedirectContains('/platform-admin/tenants/newkingdom');
        $row = $this->platform()->execute('SELECT * FROM tenants WHERE slug = ?', ['newkingdom'])->fetch('assoc');
        $this->assertSame('New Kingdom', $row['display_name']);
        $this->assertSame('provisioning', $row['status']);
        $this->assertSame('kmp_tenant_newkingdom', $row['db_name']);
        $this->assertSame('kmp_tenant_newkingdom_role', $row['db_role']);
        $this->assertSame(7, (int)$row['queue_concurrency_limit']);
        $config = json_decode((string)$row['tenant_config'], true);
        $this->assertSame('resend', $config['email']['mode']);
        $this->assertSame('tenant.newkingdom.email-api-key', $config['email']['api_secret_ref']);
        $this->assertTrue(SecretStoreFactory::fromConfig()->exists('tenant.newkingdom.db.password'));

        $host = $this->platform()->execute(
            'SELECT host FROM tenant_hosts WHERE tenant_id = ? AND is_primary = 1',
            [$row['id']],
        )->fetchColumn(0);
        $this->assertSame('newkingdom.test', $host);

        $auditAction = $this->platform()->execute(
            'SELECT action FROM audit_events WHERE subject_id = ? ORDER BY id DESC LIMIT 1',
            [$row['id']],
        )->fetchColumn(0);
        $this->assertSame('tenant.created', $auditAction);

        $job = $this->platform()->execute(
            'SELECT * FROM platform_jobs WHERE job_type = ? ORDER BY created_at DESC LIMIT 1',
            ['tenant_provision'],
        )->fetch('assoc');
        $this->assertSame('queued', $job['status']);
        $this->assertSame($row['id'], $job['tenant_id']);
        $this->assertSame('tenant_provision:newkingdom:newkingdom-provision', $job['idempotency_key']);
        $this->assertStringContainsString('"run_migrations":true', $job['parameters']);
        $this->assertStringContainsString('"create_database":true', $job['parameters']);
        $this->assertStringContainsString('"initial_super_user_email":"superuser@newkingdom.test"', $job['parameters']);
        $this->assertStringNotContainsString('super-secret-password', $job['parameters']);
        $this->assertStringNotContainsString('password_token', $job['parameters']);
    }

    public function testTenantCreationRequiresValidInitialSuperUserEmail(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->post('/platform-admin/tenants/add', [
            'slug' => 'newkingdom',
            'display_name' => 'New Kingdom',
            'status' => 'provisioning',
            'region' => 'us',
            'primary_host' => 'newkingdom.test',
            'initial_super_user_email' => 'not-an-email',
            'db_server' => 'db.internal',
            'db_name' => '',
            'db_role' => '',
            'queue_concurrency_limit' => '7',
            'documents_blob_container' => 'documents-newkingdom',
            'documents_blob_prefix' => 'tenants/newkingdom',
            'email_mode' => 'disabled',
            'features_json' => '',
            'integration_endpoints_json' => '',
            'integration_secret_refs_json' => '',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Initial tenant super user email is required and must be a valid email address.');
        $tenantCount = (int)$this->platform()
            ->execute('SELECT COUNT(*) FROM tenants WHERE slug = ?', ['newkingdom'])
            ->fetchColumn(0);
        $this->assertSame(0, $tenantCount);
    }

    public function testTenantEditCannotManuallyActivateProvisioningTenant(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();
        $this->platform()->update('tenants', ['status' => 'provisioning'], ['id' => 'tenant-1']);

        $this->post('/platform-admin/tenants/example/edit', [
            'slug' => 'example',
            'display_name' => 'Example Tenant',
            'status' => 'active',
            'region' => 'us',
            'primary_host' => 'example.test',
            'db_server' => 'db-secret-host',
            'db_name' => 'tenant_secret_database',
            'db_role' => 'tenant_secret_role',
            'queue_concurrency_limit' => '5',
            'documents_blob_container' => 'documents-old',
            'documents_blob_prefix' => 'tenants/old',
            'email_mode' => 'default',
            'features_json' => '',
            'integration_endpoints_json' => '',
            'integration_secret_refs_json' => '',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Tenant activation is completed by the provisioning worker');
        $status = $this->platform()
            ->execute('SELECT status FROM tenants WHERE id = ?', ['tenant-1'])
            ->fetchColumn(0);
        $this->assertSame('provisioning', $status);
    }

    public function testPlatformAdminCanEditTenantRegistryAndConfiguration(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->get('/platform-admin/tenants/example/edit');
        $this->assertResponseOk();
        $this->assertResponseContains('Edit Tenant');

        $this->post('/platform-admin/tenants/example/edit', [
            'slug' => 'ignored-change',
            'display_name' => 'Example Updated',
            'status' => 'suspended',
            'region' => 'us-central',
            'primary_host' => 'example-updated.test',
            'db_server' => 'other-db.internal',
            'db_name' => 'other_tenant_database',
            'db_role' => 'other_tenant_role',
            'queue_concurrency_limit' => '3',
            'documents_blob_container' => 'documents-updated',
            'documents_blob_prefix' => 'tenants/example-updated',
            'email_mode' => 'smtp',
            'email_from_address' => 'no-reply@example.test',
            'email_from_name' => 'Example Updated',
            'email_smtp_host' => 'smtp.example.test',
            'email_smtp_port' => '587',
            'email_smtp_username' => 'mailer',
            'email_smtp_password_secret_ref' => 'tenant.example.smtp-password',
            'email_smtp_tls' => '1',
            'features_json' => '',
            'integration_endpoints_json' => '',
            'integration_secret_refs_json' => '',
        ]);

        $this->assertRedirectContains('/platform-admin/tenants/example');
        $row = $this->platform()->execute('SELECT * FROM tenants WHERE slug = ?', ['example'])->fetch('assoc');
        $this->assertSame('Example Updated', $row['display_name']);
        $this->assertSame('suspended', $row['status']);
        $this->assertSame('db-secret-host', $row['db_server']);
        $this->assertSame('tenant_secret_database', $row['db_name']);
        $this->assertSame('tenant_secret_role', $row['db_role']);
        $this->assertSame(3, (int)$row['queue_concurrency_limit']);
        $config = json_decode((string)$row['tenant_config'], true);
        $this->assertSame('smtp', $config['email']['mode']);
        $this->assertSame('smtp.example.test', $config['email']['host']);
        $this->assertSame('tenant.example.smtp-password', $config['email']['smtp_password_secret_ref']);
        $this->assertArrayNotHasKey('password', $config);
    }

    public function testPlatformAdminCanQueueTenantBackup(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->get('/platform-admin/tenants/example/backups');
        $this->assertResponseOk();
        $this->assertResponseContains('Tenant Backups');
        $this->assertResponseContains('Queue backup');

        $this->post('/platform-admin/tenants/example/backups/create', [
            'retention_days' => '45',
            'nonce' => 'tenant-backup-test',
        ]);

        $this->assertRedirectContains('/platform-admin/tenants/example/backups');
        $job = $this->platform()->execute(
            'SELECT * FROM platform_jobs WHERE job_type = ? ORDER BY created_at DESC LIMIT 1',
            ['tenant_backup_json'],
        )->fetch('assoc');
        $this->assertSame('queued', $job['status']);
        $this->assertSame('tenant_backup_json:example:tenant-backup-test', $job['idempotency_key']);
        $this->assertStringContainsString('"backup_format":"kmpbackup_json"', $job['parameters']);
        $this->assertStringContainsString('"retention_days":45', $job['parameters']);

        $audit = $this->platform()->execute(
            'SELECT action, subject_id FROM audit_events WHERE action = ? ORDER BY id DESC LIMIT 1',
            ['platform_job.queued'],
        )->fetch('assoc');
        $this->assertSame('platform_job.queued', $audit['action']);
        $this->assertSame($job['id'], $audit['subject_id']);
    }

    public function testPlatformAdminCanQueuePlatformBackup(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->get('/platform-admin/backups');
        $this->assertResponseOk();
        $this->assertResponseContains('Platform Database Backup');
        $this->assertResponseContains('Tenant Backup Administration');

        $this->post('/platform-admin/backups/platform/create', [
            'retention_days' => '60',
            'nonce' => 'platform-backup-test',
        ]);

        $this->assertRedirectContains('/platform-admin/backups');
        $job = $this->platform()->execute(
            'SELECT * FROM platform_jobs WHERE job_type = ? ORDER BY created_at DESC LIMIT 1',
            ['platform_backup_json'],
        )->fetch('assoc');
        $this->assertSame('queued', $job['status']);
        $this->assertSame('platform_backup_json:platform-backup-test', $job['idempotency_key']);
        $this->assertStringContainsString('"scope":"platform"', $job['parameters']);
        $this->assertStringContainsString('"backup_format":"kmpbackup_json"', $job['parameters']);
    }

    public function testTenantRestoreRequiresSuspendedTenantAndStepUp(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->post('/platform-admin/tenants/example/backups/tenant-kmpbackup-1/restore', [
            'confirmation' => 'RESTORE example',
            'reason' => 'Testing tenant restore guardrails',
            'totp' => $this->totpCode(),
            'nonce' => 'tenant-restore-active',
        ]);
        $this->assertRedirectContains('/platform-admin/tenants/example/backups');
        $count = $this->platform()->execute(
            'SELECT COUNT(*) FROM platform_jobs WHERE job_type = ?',
            ['tenant_restore_json'],
        )->fetchColumn(0);
        $this->assertSame(0, (int)$count);

        $this->platform()->update('tenants', ['status' => 'suspended'], ['id' => 'tenant-1']);
        $this->post('/platform-admin/tenants/example/backups/tenant-kmpbackup-1/restore', [
            'confirmation' => 'RESTORE example',
            'reason' => 'Testing tenant restore guardrails',
            'totp' => $this->totpCode(),
            'nonce' => 'tenant-restore-suspended',
        ]);
        $this->assertRedirectContains('/platform-admin/tenants/example/backups');

        $job = $this->platform()->execute(
            'SELECT * FROM platform_jobs WHERE job_type = ? ORDER BY created_at DESC LIMIT 1',
            ['tenant_restore_json'],
        )->fetch('assoc');
        $this->assertSame('queued', $job['status']);
        $this->assertSame('tenant_restore_json:example:tenant-kmpbackup-1:tenant-restore-suspended', $job['idempotency_key']);
        $this->assertStringContainsString('"object_name":"tenant-example-20260516.kmpbackup"', $job['parameters']);
        $this->assertStringNotContainsString($this->totpCode(), $job['parameters']);
    }

    public function testTenantDownloadRejectsNonKmpbackupArchive(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->post('/platform-admin/tenants/example/backups/backup-1/download', [
            'confirmation' => 'DOWNLOAD example',
            'reason' => 'Testing tenant download guardrails',
            'totp' => $this->totpCode(),
        ]);

        $this->assertRedirectContains('/platform-admin/tenants/example/backups');
        $count = $this->platform()->execute(
            'SELECT COUNT(*) FROM audit_events WHERE action = ?',
            ['tenant_backup.download'],
        )->fetchColumn(0);
        $this->assertSame(0, (int)$count);
    }

    public function testPlatformRestoreRequiresStepUpAndQueuesJob(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->post('/platform-admin/backups/platform/platform-kmpbackup-1/restore', [
            'confirmation' => 'RESTORE platform',
            'reason' => 'Testing platform restore guardrails',
            'totp' => $this->totpCode(),
            'nonce' => 'platform-restore',
        ]);

        $this->assertRedirectContains('/platform-admin/backups');
        $job = $this->platform()->execute(
            'SELECT * FROM platform_jobs WHERE job_type = ? ORDER BY created_at DESC LIMIT 1',
            ['platform_restore_json'],
        )->fetch('assoc');
        $this->assertSame('queued', $job['status']);
        $this->assertSame('platform_restore_json:platform-kmpbackup-1:platform-restore', $job['idempotency_key']);
        $this->assertStringContainsString('"object_name":"platform-20260516.kmpbackup"', $job['parameters']);
    }

    public function testPlatformAdminCanViewAndEditAllowedTenantConfig(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->get('/platform-admin/tenants/example/config');

        $this->assertResponseOk();
        $this->assertResponseContains('Tenant Configuration');
        $this->assertResponseContains('Azure Communication Services');
        $this->assertResponseContains('SendGrid');
        $this->assertResponseContains('Resend');
        $this->assertResponseNotContains('Webhook');
        $this->assertResponseContains('documents-old');
        $this->assertResponseContains('tenant.example.email-api-key');
        $this->assertResponseNotContains('super-secret-password');

        $this->loginAsPlatformAdmin();
        $this->post('/platform-admin/tenants/example/config', [
            'documents_blob_container' => 'documents-example',
            'documents_blob_prefix' => 'tenants/example',
            'email_mode' => 'sendgrid',
            'email_from_address' => 'no-reply@example.test',
            'email_from_name' => 'Example Herald',
            'email_endpoint_url' => 'https://mail.example.test/send',
            'email_api_secret_ref' => 'tenant.example.email-api-key',
            'email_smtp_password_secret_ref' => 'tenant.example.smtp-password',
            'features_json' => '{"awards":true,"waivers":false}',
            'integration_endpoints_json' => '{"roster":"https://integrations.example.test/roster"}',
            'integration_secret_refs_json' => '{"roster":"tenant.example.roster-token"}',
        ]);

        $this->assertRedirectContains('/platform-admin/tenants/example');

        $row = $this->platform()->execute('SELECT * FROM tenants WHERE slug = ?', ['example'])->fetch('assoc');
        $config = json_decode((string)$row['tenant_config'], true);
        $this->assertSame('documents-example', $config['documents']['blob_container']);
        $this->assertSame('tenants/example', $config['documents']['blob_prefix']);
        $this->assertSame('sendgrid', $config['email']['mode']);
        $this->assertSame('tenant.example.email-api-key', $config['email']['api_secret_ref']);
        $this->assertArrayNotHasKey('password', $config);

        $tenant = TenantMetadata::fromPlatformRow($row);
        $resolved = (new TenantDocumentStorageConfigResolver())->resolveAzureConfig($tenant);
        $this->assertSame('documents-example', $resolved['container']);
        $this->assertSame('tenants/example', $resolved['prefix']);

        $audit = $this->platform()->execute('SELECT * FROM audit_events')->fetch('assoc');
        $this->assertSame('tenant.config.updated', $audit['action']);
        $this->assertSame('tenant-1', $audit['tenant_id']);
    }

    public function testTenantConfigEditRequiresPlatformAdminSession(): void
    {
        $this->enablePortal();
        $this->session(['Auth' => new Member(['id' => 1, 'email_address' => 'tenant@example.test'])]);

        $this->get('/platform-admin/tenants/example/config');

        $this->assertRedirectContains('/platform-admin/login');
    }

    public function testTenantConfigRejectsPlaintextSecretValues(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->post('/platform-admin/tenants/example/config', [
            'documents_blob_container' => 'documents-example',
            'documents_blob_prefix' => '',
            'email_mode' => 'sendgrid',
            'email_from_address' => '',
            'email_from_name' => '',
            'email_endpoint_url' => '',
            'email_api_secret_ref' => 'plaintext:new-secret-value',
            'email_smtp_password_secret_ref' => '',
            'features_json' => '',
            'integration_endpoints_json' => '',
            'integration_secret_refs_json' => '',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Secret fields must contain a secret reference name');
        $stored = (string)$this->platform()
            ->execute('SELECT tenant_config FROM tenants WHERE slug = ?', ['example'])
            ->fetchColumn(0);
        $this->assertStringNotContainsString('new-secret-value', $stored);
    }

    public function testTenantConfigRejectsUnsupportedEmailModes(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->post('/platform-admin/tenants/example/config', [
            'documents_blob_container' => 'documents-example',
            'documents_blob_prefix' => '',
            'email_mode' => 'webhook',
            'email_from_address' => '',
            'email_from_name' => '',
            'email_endpoint_url' => '',
            'email_api_secret_ref' => '',
            'email_smtp_password_secret_ref' => '',
            'features_json' => '',
            'integration_endpoints_json' => '',
            'integration_secret_refs_json' => '',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Email mode must be default, disabled, azure, smtp, resend, or sendgrid.');
    }

    public function testTenantConfigRejectsUnknownFields(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->post('/platform-admin/tenants/example/config', [
            'documents_blob_container' => 'documents-example',
            'documents_blob_prefix' => '',
            'email_mode' => 'default',
            'email_from_address' => '',
            'email_from_name' => '',
            'email_endpoint_url' => '',
            'email_api_secret_ref' => '',
            'email_smtp_password_secret_ref' => '',
            'features_json' => '',
            'integration_endpoints_json' => '',
            'integration_secret_refs_json' => '',
            'rogue_setting' => 'unsafe',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Unknown tenant configuration field');
        $stored = (string)$this->platform()
            ->execute('SELECT tenant_config FROM tenants WHERE slug = ?', ['example'])
            ->fetchColumn(0);
        $this->assertStringNotContainsString('rogue_setting', $stored);
    }

    public function testUnauthenticatedDataConsoleRequestIsDenied(): void
    {
        $this->enableDataConsole();

        $this->get('/platform-admin/data-console');

        $this->assertRedirectContains('/platform-admin/login');
    }

    public function testTenantUserSessionDoesNotGrantDataConsoleAccess(): void
    {
        $this->enableDataConsole();
        $this->authenticateAsSuperUser();

        $this->get('/platform-admin/data-console');

        $this->assertRedirectContains('/platform-admin/login');
    }

    public function testAllowlistedDataConsoleQueryReturnsPaginatedScrubbedRowsAndAudits(): void
    {
        $this->enableDataConsole();
        $this->loginAsPlatformAdmin();

        $this->get('/platform-admin/data-console?query=backups&limit=1');

        $this->assertResponseOk();
        $this->assertResponseContains('Platform Data Console');
        $this->assertResponseContains('Backups');
        $this->assertResponseContains('Page 1; limit 1');
        $this->assertResponseContains('[redacted]');
        $this->assertResponseNotContains('platform-secret-database');
        $this->assertResponseNotContains('object://secret-path');
        $this->assertResponseNotContains('wrapped-dek-secret');

        $audit = $this->platform()->execute(
            'SELECT action, subject_id, metadata FROM audit_events WHERE action = :action',
            ['action' => 'data_console.query'],
        )->fetch('assoc');
        $this->assertIsArray($audit);
        $this->assertSame('backups', $audit['subject_id']);
        $this->assertStringContainsString('"row_count":1', (string)$audit['metadata']);
    }

    public function testNonAllowlistedDataConsoleQueryIsRejected(): void
    {
        $this->enableDataConsole();
        $this->loginAsPlatformAdmin();

        $this->get('/platform-admin/data-console?query=SELECT%20*%20FROM%20platform_users');

        $this->assertResponseCode(400);
        $auditCount = $this->platform()->execute(
            'SELECT COUNT(*) FROM audit_events WHERE action = :action',
            ['action' => 'data_console.query'],
        )->fetchColumn(0);
        $this->assertSame(0, (int)$auditCount);
    }

    private function enablePortal(): void
    {
        Configure::write('Platform.adminPortal.enabled', true);
        Configure::write('Platform.adminPortal.hosts', ['platform.kmp.localhost']);
        Configure::write('Platform.adminPortal.allowedStatuses', ['active']);
        Configure::write('Platform.adminPortal.detailedLoginErrors', false);
    }

    private function loginAsPlatformAdmin(): void
    {
        $this->session([
            'PlatformAdmin' => [
                'id' => 'platform-admin-1',
                'email' => 'admin@example.org',
                'status' => 'active',
                'host' => 'localhost',
            ],
        ]);
    }

    private function configureSecrets(): void
    {
        $directory = sys_get_temp_dir() . DS . 'platform-admin-secrets-' . uniqid();
        mkdir($directory, 0700, true);
        $this->secretFile = $directory . DS . 'secrets.json';
        file_put_contents($this->secretFile, json_encode([
            'secrets' => [
                'platform.admin.platform-admin-1.totp' => [
                    'value' => $this->totpSecret,
                    'rotated_at' => '2026-05-16T12:00:00+00:00',
                ],
            ],
        ], JSON_PRETTY_PRINT));
        chmod($this->secretFile, 0600);
        Configure::write('Secrets', [
            'driver' => 'file',
            'drivers' => [
                'file' => [
                    'path' => $this->secretFile,
                    'environment' => 'test',
                    'allowInEnvironments' => ['test'],
                ],
            ],
        ]);
    }

    private function enableDataConsole(): void
    {
        $this->enablePortal();
        Configure::write('Platform.adminPortal.dataConsole.enabled', true);
    }

    private function resetPlatformConnection(): void
    {
        ConnectionManager::drop('platform');
        ConnectionManager::setConfig('platform', [
            'className' => Connection::class,
            'driver' => Sqlite::class,
            'database' => ':memory:',
            'password' => 'platform-db-password',
            'cacheMetadata' => false,
            'quoteIdentifiers' => false,
        ]);
    }

    private function platform(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('platform');

        return $connection;
    }

    private function createSchema(): void
    {
        $connection = $this->platform();
        $connection->execute(
            'CREATE TABLE platform_users (
                id TEXT PRIMARY KEY,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                status TEXT NOT NULL,
                totp_secret_ref TEXT NULL,
                totp_enrolled_at TEXT NULL,
                failed_login_count INTEGER NOT NULL DEFAULT 0,
                locked_until TEXT NULL,
                last_login_at TEXT NULL,
                created_at TEXT NOT NULL,
                modified_at TEXT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                display_name TEXT NOT NULL,
                status TEXT NOT NULL,
                region TEXT NOT NULL,
                primary_host TEXT NULL,
                db_server TEXT NOT NULL,
                db_name TEXT NOT NULL,
                db_role TEXT NOT NULL,
                key_vault_prefix TEXT NULL,
                schema_version TEXT NULL,
                feature_flags TEXT NULL,
                tenant_config TEXT NULL,
                queue_concurrency_limit INTEGER NOT NULL,
                created_at TEXT NOT NULL,
                activated_at TEXT NULL,
                suspended_at TEXT NULL,
                archived_at TEXT NULL,
                modified_at TEXT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE tenant_hosts (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                host TEXT NOT NULL,
                host_normalized TEXT NOT NULL,
                is_primary INTEGER NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE platform_jobs (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NULL,
                requested_by_platform_user_id TEXT NULL,
                job_type TEXT NOT NULL,
                status TEXT NOT NULL,
                idempotency_key TEXT NULL UNIQUE,
                parameters TEXT NULL,
                log_uri TEXT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL,
                started_at TEXT NULL,
                finished_at TEXT NULL,
                modified_at TEXT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE tenant_backups (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                backup_type TEXT NOT NULL,
                status TEXT NOT NULL,
                object_uri TEXT NULL,
                object_size_bytes INTEGER NULL,
                wrapped_dek TEXT NULL,
                wrapped_dek_key_name TEXT NULL,
                wrapped_dek_key_version TEXT NULL,
                created_at TEXT NOT NULL,
                completed_at TEXT NULL,
                retention_until TEXT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE platform_database_backups (
                id TEXT PRIMARY KEY,
                backup_type TEXT NOT NULL,
                status TEXT NOT NULL,
                connection_name TEXT NOT NULL,
                database_name TEXT NOT NULL,
                object_uri TEXT NULL,
                object_size_bytes INTEGER NULL,
                wrapped_dek TEXT NULL,
                created_at TEXT NOT NULL,
                completed_at TEXT NULL,
                retention_until TEXT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE platform_schedules (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                cron_expression TEXT NOT NULL,
                command TEXT NOT NULL,
                enabled INTEGER NOT NULL,
                tenant_scope TEXT NOT NULL,
                tenant_id TEXT NULL,
                status TEXT NOT NULL,
                last_run_at TEXT NULL,
                next_run_at TEXT NULL,
                last_success_at TEXT NULL,
                last_failure_at TEXT NULL,
                last_error TEXT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE releases (
                id TEXT PRIMARY KEY,
                image_tag TEXT NOT NULL,
                git_sha TEXT NULL,
                min_schema TEXT NOT NULL,
                max_schema TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                modified_at TEXT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE IF NOT EXISTS audit_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id TEXT NULL,
                platform_user_id TEXT NULL,
                action TEXT NOT NULL,
                subject_type TEXT NULL,
                subject_id TEXT NULL,
                reason TEXT NULL,
                metadata TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                user_agent TEXT NOT NULL,
                previous_hash TEXT NULL,
                event_hash TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
        );
    }

    private function seedPlatformData(): void
    {
        $connection = $this->platform();
        $now = '2026-05-16 12:00:00';
        $connection->insert('platform_users', [
            'id' => 'platform-admin-1',
            'email' => 'admin@example.org',
            'password_hash' => password_hash('TestPassword', PASSWORD_DEFAULT),
            'status' => 'active',
            'totp_secret_ref' => 'platform.admin.platform-admin-1.totp',
            'totp_enrolled_at' => $now,
            'failed_login_count' => 0,
            'locked_until' => null,
            'last_login_at' => null,
            'created_at' => $now,
            'modified_at' => $now,
        ]);
        $connection->insert('tenants', [
            'id' => 'tenant-1',
            'slug' => 'example',
            'display_name' => 'Example Tenant',
            'status' => 'active',
            'region' => 'us',
            'primary_host' => 'example.test',
            'db_server' => 'db-secret-host',
            'db_name' => 'tenant_secret_database',
            'db_role' => 'tenant_secret_role',
            'key_vault_prefix' => 'secret/key/prefix',
            'schema_version' => '20260516000000',
            'feature_flags' => '{"secret":"do-not-render"}',
            'tenant_config' => json_encode([
                'documents' => [
                    'blob_container' => 'documents-old',
                    'blob_prefix' => 'tenants/old',
                ],
                'email' => [
                    'mode' => 'api',
                    'api_secret_ref' => 'tenant.example.email-api-key',
                ],
                'password' => 'super-secret-password',
            ], JSON_UNESCAPED_SLASHES),
            'queue_concurrency_limit' => 5,
            'created_at' => $now,
            'activated_at' => $now,
            'modified_at' => $now,
        ]);
        $connection->insert('tenant_hosts', [
            'id' => 'host-1',
            'tenant_id' => 'tenant-1',
            'host' => 'example.test',
            'host_normalized' => 'example.test',
            'is_primary' => 1,
            'status' => 'active',
            'created_at' => $now,
        ]);
        $connection->insert('platform_jobs', [
            'id' => 'job-1',
            'tenant_id' => 'tenant-1',
            'job_type' => 'tenant_backup',
            'status' => 'failed',
            'parameters' => '{"password":"super-secret-password"}',
            'log_uri' => 'object://secret-path/job.log',
            'last_error' => 'super-secret-password failed',
            'created_at' => $now,
        ]);
        $connection->insert('tenant_backups', [
            'id' => 'backup-1',
            'tenant_id' => 'tenant-1',
            'backup_type' => 'pg_dump',
            'status' => 'completed',
            'object_uri' => 'object://secret-path/backup.dump',
            'object_size_bytes' => 123,
            'wrapped_dek' => 'wrapped-dek-secret',
            'wrapped_dek_key_name' => 'secret-key-name',
            'wrapped_dek_key_version' => 'secret-key-version',
            'created_at' => $now,
            'completed_at' => $now,
            'retention_until' => '2026-06-16 12:00:00',
        ]);
        $connection->insert('tenant_backups', [
            'id' => 'tenant-kmpbackup-1',
            'tenant_id' => 'tenant-1',
            'backup_type' => 'kmpbackup_json',
            'status' => 'completed',
            'object_uri' => 'tenant-example-20260516.kmpbackup',
            'object_size_bytes' => 789,
            'created_at' => $now,
            'completed_at' => $now,
            'retention_until' => '2026-06-16 12:00:00',
        ]);
        $connection->insert('platform_database_backups', [
            'id' => 'platform-backup-1',
            'backup_type' => 'pg_dump',
            'status' => 'completed',
            'connection_name' => 'platform',
            'database_name' => 'platform-secret-database',
            'object_uri' => 'object://secret-path/platform.dump',
            'object_size_bytes' => 456,
            'wrapped_dek' => 'wrapped-dek-secret',
            'created_at' => '2026-05-16 12:05:00',
            'completed_at' => '2026-05-16 12:06:00',
            'retention_until' => '2026-06-16 12:00:00',
        ]);
        $connection->insert('platform_database_backups', [
            'id' => 'platform-kmpbackup-1',
            'backup_type' => 'kmpbackup_json',
            'status' => 'completed',
            'connection_name' => 'platform',
            'database_name' => 'platform',
            'object_uri' => 'platform-20260516.kmpbackup',
            'object_size_bytes' => 987,
            'created_at' => '2026-05-16 12:05:00',
            'completed_at' => '2026-05-16 12:06:00',
            'retention_until' => '2026-06-16 12:00:00',
        ]);
        $connection->insert('platform_schedules', [
            'id' => 'schedule-1',
            'name' => 'Nightly backup',
            'cron_expression' => '0 2 * * *',
            'command' => 'tenant backup --all',
            'enabled' => 1,
            'tenant_scope' => 'all_active',
            'status' => 'idle',
            'next_run_at' => '2026-05-17 02:00:00',
            'last_error' => 'super-secret-password',
        ]);
    }

    private function totpCode(): string
    {
        $verifier = new PlatformTotpVerifier($this->secretStore());

        return $verifier->codeForTimestamp($this->totpSecret, time());
    }

    private function secretStore(): SecretStoreInterface
    {
        return new class ($this->totpSecret) implements SecretStoreInterface {
            public function __construct(private readonly string $totpSecret)
            {
            }

            public function get(string $name): ?SensitiveString
            {
                return new SensitiveString($this->totpSecret);
            }

            public function exists(string $name): bool
            {
                return true;
            }

            public function list(string $prefix = ''): array
            {
                return [];
            }

            public function rotatedAt(string $name): ?DateTimeImmutable
            {
                return null;
            }
        };
    }
}
