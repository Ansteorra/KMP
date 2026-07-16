<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\PlatformAdmin;

use App\KMP\TenantMetadata;
use App\Model\Entity\Member;
use App\Services\Backups\BackupRecoveryKeyService;
use App\Services\Backups\PlatformDatabaseBackupEncryptor;
use App\Services\Backups\TenantBackupEncryptor;
use App\Services\BackupStorageService;
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

    private string $tenantBackupKek = 'tenant-backup-kek-for-platform-portal-tests';

    private string $platformBackupKek = 'platform-backup-kek-for-platform-portal-tests';

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
        $this->platform()->insert('tenant_request_metrics_hourly', [
            'id' => 'metric-1',
            'tenant_id' => 'tenant-1',
            'metric_hour' => gmdate('Y-m-d H:00:00'),
            'route_name' => 'Members::index',
            'request_count' => 100,
            'error_count' => 8,
            'server_error_count' => 3,
            'slow_request_count' => 7,
            'duration_total_ms' => 120000,
            'duration_max_ms' => 4100,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'modified_at' => null,
        ]);
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
        $this->assertResponseContains('Platform operations');
        $this->assertResponseContains('requests / 8.00% errors / 1200 ms average');
        $this->assertResponseContains('7 slow requests / 3 server errors');
        $this->assertResponseContains('Request error rate is 8.0%.');
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
        $this->assertResponseContains('Platform operations');
        $this->assertResponseContains('Example Tenant');
        $this->assertResponseContains('tenant backup');
        $this->assertResponseContains('>Jobs<');
        $this->assertResponseContains('>Schedules<');
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
        $this->assertResponseContains('Onboard a kingdom');
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
            'documents_blob_container' => 'documents-newkingdom',
            'documents_blob_prefix' => 'tenants/newkingdom',
            'email_mode' => 'resend',
            'email_from_address' => 'no-reply@newkingdom.test',
            'email_from_name' => 'New Kingdom',
            'email_endpoint_url' => 'https://api.resend.com/emails',
            'email_api_secret_ref' => 'tenant.newkingdom.email-api-key',
            'nonce' => 'newkingdom-provision',
        ]);

        $this->assertRedirectContains('/platform-admin/tenants/newkingdom');
        $row = $this->platform()->execute('SELECT * FROM tenants WHERE slug = ?', ['newkingdom'])->fetch('assoc');
        $this->assertSame('New Kingdom', $row['display_name']);
        $this->assertSame('provisioning', $row['status']);
        $this->assertSame('kmp_tenant_newkingdom', $row['db_name']);
        $this->assertSame('kmp_tenant_newkingdom_role', $row['db_role']);
        $config = json_decode((string)$row['tenant_config'], true);
        $this->assertSame('resend', $config['email']['mode']);
        $this->assertSame('tenant.newkingdom.email-api-key', $config['email']['api_secret_ref']);
        $this->assertTrue(SecretStoreFactory::fromConfig()->exists('tenant.newkingdom.db.password'));
        $this->assertTrue(SecretStoreFactory::fromConfig()->exists('tenant.newkingdom.kek'));

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
            'documents_blob_container' => 'documents-newkingdom',
            'documents_blob_prefix' => 'tenants/newkingdom',
            'email_mode' => 'disabled',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains('Initial tenant super user email is required and must be a valid email address.');
        $tenantCount = (int)$this->platform()
            ->execute('SELECT COUNT(*) FROM tenants WHERE slug = ?', ['newkingdom'])
            ->fetchColumn(0);
        $this->assertSame(0, $tenantCount);
    }

    public function testTenantCreationRejectsDisabledEmailDelivery(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

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
            'documents_blob_container' => 'documents-newkingdom',
            'documents_blob_prefix' => 'tenants/newkingdom',
            'email_mode' => 'disabled',
        ]);

        $this->assertResponseOk();
        $this->assertResponseContains(
            'Email delivery cannot be disabled while onboarding the initial tenant super user.',
        );
        $tenantCount = (int)$this->platform()
            ->execute('SELECT COUNT(*) FROM tenants WHERE slug = ?', ['newkingdom'])
            ->fetchColumn(0);
        $this->assertSame(0, $tenantCount);
    }

    public function testTenantEditDoesNotChangeLifecycleStatus(): void
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
            'documents_blob_container' => 'documents-old',
            'documents_blob_prefix' => 'tenants/old',
            'email_mode' => 'default',
        ]);

        $this->assertRedirectContains('/platform-admin/tenants/example');
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
        ]);

        $this->assertRedirectContains('/platform-admin/tenants/example');
        $row = $this->platform()->execute('SELECT * FROM tenants WHERE slug = ?', ['example'])->fetch('assoc');
        $this->assertSame('Example Updated', $row['display_name']);
        $this->assertSame('active', $row['status']);
        $this->assertSame('db-secret-host', $row['db_server']);
        $this->assertSame('tenant_secret_database', $row['db_name']);
        $this->assertSame('tenant_secret_role', $row['db_role']);
        $config = json_decode((string)$row['tenant_config'], true);
        $this->assertSame('smtp', $config['email']['mode']);
        $this->assertSame('smtp.example.test', $config['email']['host']);
        $this->assertSame('tenant.example.smtp-password', $config['email']['smtp_password_secret_ref']);
        $this->assertArrayNotHasKey('password', $config);
    }

    public function testPlatformAdminCanSuspendAndReactivateTenantWithStepUp(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->post('/platform-admin/tenants/example/suspend', [
            'confirmation' => 'SUSPEND example',
            'reason' => 'Investigating elevated tenant errors.',
            'totp' => $this->totpCode(),
        ]);

        $this->assertRedirectContains('/platform-admin/tenants/example');
        $tenant = $this->platform()->execute(
            'SELECT status, suspended_at FROM tenants WHERE id = ?',
            ['tenant-1'],
        )->fetch('assoc');
        $this->assertSame('suspended', $tenant['status']);
        $this->assertNotEmpty($tenant['suspended_at']);

        $this->post('/platform-admin/tenants/example/reactivate', [
            'confirmation' => 'REACTIVATE example',
            'reason' => 'Tenant health has recovered.',
            'totp' => $this->totpCode(),
        ]);

        $this->assertRedirectContains('/platform-admin/tenants/example');
        $tenant = $this->platform()->execute(
            'SELECT status, suspended_at FROM tenants WHERE id = ?',
            ['tenant-1'],
        )->fetch('assoc');
        $this->assertSame('active', $tenant['status']);
        $this->assertNull($tenant['suspended_at']);
        $actions = $this->platform()->execute(
            "SELECT action FROM audit_events WHERE action LIKE 'tenant.%' ORDER BY id",
        )->fetchAll('assoc');
        $this->assertSame(['tenant.suspended', 'tenant.active'], array_column($actions, 'action'));
    }

    public function testPlatformAdminCanQueueTenantBackup(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->get('/platform-admin/tenants/example/backups');
        $this->assertResponseOk();
        $this->assertResponseContains('Tenant Backups');
        $this->assertResponseContains('Queue backup');
        $this->assertResponseContains('id="tenant-backup-tenant-kmpbackup-1-download-confirmation"');
        $this->assertResponseContains('id="tenant-backup-tenant-kmpbackup-1-recovery-key-confirmation"');
        $this->assertResponseContains('id="tenant-backup-tenant-kmpbackup-1-restore-confirmation"');
        $this->assertResponseContains('id="tenant-backup-tenant-kmpbackup-1-delete-confirmation"');
        $this->assertResponseNotContains('id="confirmation"');

        $this->post('/platform-admin/tenants/example/backups/create', [
            'retention_days' => '45',
            'nonce' => 'tenant-backup-test',
        ]);

        $this->assertRedirectContains('/platform-admin/tenants/example/backups');
        $job = $this->platform()->execute(
            'SELECT * FROM platform_jobs WHERE job_type = ? ORDER BY created_at DESC LIMIT 1',
            ['tenant_backup'],
        )->fetch('assoc');
        $this->assertSame('queued', $job['status']);
        $this->assertSame('tenant_backup:example:tenant-backup-test', $job['idempotency_key']);
        $this->assertStringContainsString('"tenant_slug":"example"', $job['parameters']);
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
            ['platform_database_backup'],
        )->fetch('assoc');
        $this->assertSame('queued', $job['status']);
        $this->assertSame('platform_database_backup:platform-backup-test', $job['idempotency_key']);
        $this->assertStringContainsString('"retention_days":60', $job['parameters']);
    }

    public function testPlatformAdminCanInspectAndRetryFailedExecutableJob(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();
        $jobId = '33333333-3333-4333-8333-333333333333';
        $this->platform()->insert('platform_jobs', [
            'id' => $jobId,
            'tenant_id' => 'tenant-1',
            'requested_by_platform_user_id' => 'platform-admin-1',
            'job_type' => 'tenant_backup',
            'status' => 'failed',
            'idempotency_key' => 'failed-portal-backup',
            'parameters' => '{"tenant_slug":"example","retention_days":30}',
            'last_error' => 'token=[redacted] failed',
            'created_at' => '2026-07-10 00:00:00',
            'finished_at' => '2026-07-10 00:01:00',
        ]);
        $this->platform()->insert('platform_job_events', [
            'id' => '44444444-4444-4444-8444-444444444444',
            'platform_job_id' => $jobId,
            'sequence_number' => 1,
            'event_level' => 'error',
            'event_code' => 'job.failed',
            'message' => 'Backup worker exited unsuccessfully.',
            'created_at' => '2026-07-10 00:01:00',
        ]);

        $this->get('/platform-admin/jobs/' . $jobId);

        $this->assertResponseOk();
        $this->assertResponseContains('Progress timeline');
        $this->assertResponseContains('Backup worker exited unsuccessfully.');
        $this->assertResponseNotContains('token=[redacted] failed');

        $this->post('/platform-admin/jobs/' . $jobId . '/retry', [
            'confirmation' => 'RETRY job',
            'reason' => 'Retry after correcting worker storage.',
            'totp' => $this->totpCode(),
            'nonce' => 'retry-test',
        ]);

        $this->assertRedirectContains('/platform-admin/jobs/' . $jobId);
        $retry = $this->platform()->execute(
            'SELECT * FROM platform_jobs WHERE id != :sourceId AND job_type = :jobType ORDER BY created_at DESC LIMIT 1',
            ['sourceId' => $jobId, 'jobType' => 'tenant_backup'],
        )->fetch('assoc');
        $this->assertSame('queued', $retry['status']);
        $this->assertSame('platform_job_retry:' . $jobId . ':retry-test', $retry['idempotency_key']);
        $this->assertStringNotContainsString($this->totpCode(), (string)$retry['parameters']);
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
            ['tenant_restore'],
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
            ['tenant_restore'],
        )->fetch('assoc');
        $this->assertSame('queued', $job['status']);
        $this->assertSame('tenant_restore:example:tenant-kmpbackup-1:tenant-restore-suspended', $job['idempotency_key']);
        $this->assertStringContainsString('"backup_id":"tenant-kmpbackup-1"', $job['parameters']);
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

    public function testTenantRecoveryKeyDownloadIsBoundedAuditedAndNeverCached(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();
        $backupId = '11111111-1111-4111-8111-111111111111';
        $inputPath = tempnam(TMP, 'portal-tenant-plaintext-');
        $encryptedPath = tempnam(TMP, 'portal-tenant-encrypted-');
        $this->assertIsString($inputPath);
        $this->assertIsString($encryptedPath);
        try {
            file_put_contents($inputPath, 'tenant logical backup');
            $tenant = TenantMetadata::fromPlatformRow(
                $this->platform()->execute('SELECT * FROM tenants WHERE id = ?', ['tenant-1'])->fetch('assoc'),
            );
            $encryption = (new TenantBackupEncryptor())->encryptFile(
                $inputPath,
                $encryptedPath,
                $tenant,
                $backupId,
                new SensitiveString($this->tenantBackupKek),
                'tenant.example.kek',
                'v1',
            );
            $this->platform()->update('tenant_backups', [
                'id' => $backupId,
                'object_size_bytes' => filesize($encryptedPath),
                'object_sha256' => hash_file('sha256', $encryptedPath),
                'encryption_algorithm' => $encryption->algorithm,
                'wrapped_dek' => $encryption->wrappedDek,
                'wrapped_dek_key_name' => 'tenant.example.kek',
                'wrapped_dek_metadata' => json_encode($encryption->wrappedDekMetadata, JSON_THROW_ON_ERROR),
            ], ['id' => 'tenant-kmpbackup-1']);

            $this->post('/platform-admin/tenants/example/backups/' . $backupId . '/recovery-key', [
                'confirmation' => 'DOWNLOAD KEY example',
                'reason' => 'Testing portable tenant recovery key export.',
                'totp' => $this->totpCode(),
            ]);

            $this->assertResponseOk();
            $this->assertHeaderContains('Cache-Control', 'no-store');
            $this->assertHeaderContains('Content-Disposition', BackupRecoveryKeyService::FILE_EXTENSION);
            $payload = json_decode((string)$this->_response->getBody(), true, 16, JSON_THROW_ON_ERROR);
            $this->assertSame('tenant', $payload['scope']);
            $this->assertSame($backupId, $payload['backup_id']);
            $this->assertStringNotContainsString(
                $this->tenantBackupKek,
                (string)$this->_response->getBody(),
            );
            $action = $this->platform()->execute(
                'SELECT action FROM audit_events WHERE subject_id = ? ORDER BY id DESC LIMIT 1',
                [$backupId],
            )->fetchColumn(0);
            $this->assertSame('tenant_backup.recovery_key_exported', $action);
        } finally {
            unlink($inputPath);
            unlink($encryptedPath);
        }
    }

    public function testPlatformRecoveryKeyDownloadIsBoundedAuditedAndNeverCached(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();
        $backupId = '22222222-2222-4222-8222-222222222222';
        $inputPath = tempnam(TMP, 'portal-platform-plaintext-');
        $encryptedPath = tempnam(TMP, 'portal-platform-encrypted-');
        $this->assertIsString($inputPath);
        $this->assertIsString($encryptedPath);
        try {
            file_put_contents($inputPath, 'platform database dump');
            $encryption = (new PlatformDatabaseBackupEncryptor())->encryptFile(
                $inputPath,
                $encryptedPath,
                $backupId,
                new SensitiveString($this->platformBackupKek),
                'platform.backup.kek',
                'v1',
            );
            $this->platform()->update('platform_database_backups', [
                'id' => $backupId,
                'object_size_bytes' => filesize($encryptedPath),
                'object_sha256' => hash_file('sha256', $encryptedPath),
                'encryption_algorithm' => $encryption->algorithm,
                'wrapped_dek' => $encryption->wrappedDek,
                'wrapped_dek_key_name' => 'platform.backup.kek',
                'wrapped_dek_metadata' => json_encode($encryption->wrappedDekMetadata, JSON_THROW_ON_ERROR),
            ], ['id' => 'platform-kmpbackup-1']);

            $this->post('/platform-admin/backups/platform/' . $backupId . '/recovery-key', [
                'confirmation' => 'DOWNLOAD KEY platform',
                'reason' => 'Testing portable platform recovery key export.',
                'totp' => $this->totpCode(),
            ]);

            $this->assertResponseOk();
            $this->assertHeaderContains('Cache-Control', 'no-store');
            $this->assertHeaderContains('Content-Disposition', BackupRecoveryKeyService::FILE_EXTENSION);
            $payload = json_decode((string)$this->_response->getBody(), true, 16, JSON_THROW_ON_ERROR);
            $this->assertSame('platform', $payload['scope']);
            $this->assertSame($backupId, $payload['backup_id']);
            $this->assertStringNotContainsString(
                $this->platformBackupKek,
                (string)$this->_response->getBody(),
            );
            $action = $this->platform()->execute(
                'SELECT action FROM audit_events WHERE subject_id = ? ORDER BY id DESC LIMIT 1',
                [$backupId],
            )->fetchColumn(0);
            $this->assertSame('platform_backup.recovery_key_exported', $action);
        } finally {
            unlink($inputPath);
            unlink($encryptedPath);
        }
    }

    public function testPlatformBackupUiDoesNotOfferInProcessRestore(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();

        $this->get('/platform-admin/backups');

        $this->assertResponseOk();
        $this->assertResponseContains('disaster-recovery runbook');
        $this->assertResponseNotContains('Queue destructive restore');
        $count = $this->platform()->execute(
            'SELECT COUNT(*) FROM platform_jobs WHERE job_type = ?',
            ['platform_restore_json'],
        )->fetchColumn(0);
        $this->assertSame(0, (int)$count);
    }

    public function testBackupScreensRenderCompactGuardedActionModals(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();
        $legacyBackup = [
            'backup_type' => 'kmpbackup_json',
            'status' => 'completed',
            'object_uri' => 'legacy-example.kmpbackup',
            'object_size_bytes' => 456,
            'object_sha256' => null,
            'encryption_algorithm' => 'AES-256-GCM',
            'wrapped_dek' => null,
            'wrapped_dek_key_name' => 'legacy.application.key',
            'wrapped_dek_key_version' => 'legacy',
            'wrapped_dek_metadata' => null,
            'created_at' => '2026-05-16 12:00:00',
            'completed_at' => '2026-05-16 12:01:00',
            'retention_until' => '2099-06-16 12:00:00',
        ];
        $this->platform()->insert('tenant_backups', $legacyBackup + [
            'id' => 'legacy-tenant-backup',
            'tenant_id' => 'tenant-1',
        ]);
        $this->platform()->insert('platform_database_backups', $legacyBackup + [
            'id' => 'legacy-platform-backup',
            'connection_name' => 'platform',
            'database_name' => 'platform',
        ]);

        $this->get('/platform-admin/backups');
        $this->assertResponseOk();
        $platformBody = (string)$this->_response->getBody();
        $this->assertStringContainsString('data-controller="guarded-action-modal"', $platformBody);
        $this->assertStringContainsString('/js/controllers-', $platformBody);
        $this->assertStringContainsString('/js/index-', $platformBody);
        $this->assertStringContainsString('table table-sm align-middle text-nowrap', $platformBody);
        $this->assertStringContainsString('d-inline-flex flex-nowrap gap-1', $platformBody);
        $this->assertStringContainsString('id="platform-backup-platform-kmpbackup-1-download"', $platformBody);
        $this->assertStringContainsString('id="platform-backup-platform-kmpbackup-1-delete"', $platformBody);
        $this->assertStringContainsString('id="platform-backup-legacy-platform-backup-download"', $platformBody);
        $this->assertStringContainsString('id="platform-backup-legacy-platform-backup-delete"', $platformBody);
        $this->assertStringNotContainsString(
            'id="platform-backup-legacy-platform-backup-recovery-key"',
            $platformBody,
        );
        $this->assertSame(1, substr_count($platformBody, 'id="platform-backup-action-modal"'));

        $this->get('/platform-admin/tenants/example/backups');
        $this->assertResponseOk();
        $tenantBody = (string)$this->_response->getBody();
        $this->assertStringContainsString('data-controller="guarded-action-modal"', $tenantBody);
        $this->assertStringContainsString('table table-sm align-middle text-nowrap', $tenantBody);
        $this->assertStringContainsString('d-inline-flex flex-nowrap gap-1', $tenantBody);
        $this->assertStringContainsString('id="tenant-backup-tenant-kmpbackup-1-download"', $tenantBody);
        $this->assertStringContainsString('id="tenant-backup-tenant-kmpbackup-1-recovery-key"', $tenantBody);
        $this->assertStringContainsString('id="tenant-backup-tenant-kmpbackup-1-delete"', $tenantBody);
        $this->assertStringContainsString('id="tenant-backup-legacy-tenant-backup-download"', $tenantBody);
        $this->assertStringContainsString('id="tenant-backup-legacy-tenant-backup-delete"', $tenantBody);
        $this->assertStringNotContainsString('id="tenant-backup-legacy-tenant-backup-restore"', $tenantBody);
        $this->assertStringNotContainsString('id="tenant-backup-legacy-tenant-backup-recovery-key"', $tenantBody);
        $this->assertSame(1, substr_count($tenantBody, 'id="tenant-backup-action-modal"'));
    }

    public function testPlatformAdminCanDeleteTenantBackupAndRetainAuditMetadata(): void
    {
        $this->enablePortal();
        $this->loginAsPlatformAdmin();
        $previousStorage = Configure::read('Documents.storage');
        $previousBackupPath = Configure::read('Backups.local.path');
        $backupPath = TMP . 'platform-admin-delete-' . uniqid('', true);
        $objectPath = 'tenants/example/11111111-1111-4111-8111-111111111111.json.gz.enc';
        Configure::write('Documents.storage', ['adapter' => 'local']);
        Configure::write('Backups.local.path', $backupPath);

        try {
            (new BackupStorageService())->write($objectPath, 'encrypted tenant backup');

            $this->post('/platform-admin/tenants/example/backups/tenant-kmpbackup-1/delete', [
                'confirmation' => 'DELETE BACKUP example',
                'reason' => 'Remove the superseded tenant recovery point.',
                'totp' => $this->totpCode(),
            ]);

            $this->assertRedirectContains('/platform-admin/tenants/example/backups');
            $backup = $this->platform()->execute(
                'SELECT status, object_uri FROM tenant_backups WHERE id = ?',
                ['tenant-kmpbackup-1'],
            )->fetch('assoc');
            $this->assertSame('deleted', $backup['status']);
            $this->assertNull($backup['object_uri']);
            $this->assertFalse((new BackupStorageService())->exists($objectPath));
            $audit = $this->platform()->execute(
                'SELECT action, reason FROM audit_events WHERE subject_id = ? ORDER BY id DESC LIMIT 1',
                ['tenant-kmpbackup-1'],
            )->fetch('assoc');
            $this->assertSame('tenant_backup.deleted', $audit['action']);
            $this->assertSame('Remove the superseded tenant recovery point.', $audit['reason']);
        } finally {
            Configure::write('Documents.storage', $previousStorage);
            if ($previousBackupPath === null) {
                Configure::delete('Backups.local.path');
            } else {
                Configure::write('Backups.local.path', $previousBackupPath);
            }
            $storedPath = $backupPath . DS . $objectPath;
            if (is_file($storedPath)) {
                unlink($storedPath);
            }
            $directories = [
                $backupPath . DS . 'tenants' . DS . 'example',
                $backupPath . DS . 'tenants',
                $backupPath,
            ];
            foreach ($directories as $directory) {
                if (is_dir($directory)) {
                    rmdir($directory);
                }
            }
        }
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
                'tenant.example.kek' => [
                    'value' => $this->tenantBackupKek,
                    'rotated_at' => '2026-05-16T12:00:00+00:00',
                ],
                'platform.backup.kek' => [
                    'value' => $this->platformBackupKek,
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
                schema_version TEXT NULL,
                tenant_config TEXT NULL,
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
            'CREATE TABLE platform_job_events (
                id TEXT PRIMARY KEY,
                platform_job_id TEXT NOT NULL,
                sequence_number INTEGER NOT NULL,
                event_level TEXT NOT NULL,
                event_code TEXT NOT NULL,
                message TEXT NOT NULL,
                created_at TEXT NOT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE tenant_request_metrics_hourly (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                metric_hour TEXT NOT NULL,
                route_name TEXT NOT NULL,
                request_count INTEGER NOT NULL DEFAULT 0,
                error_count INTEGER NOT NULL DEFAULT 0,
                server_error_count INTEGER NOT NULL DEFAULT 0,
                slow_request_count INTEGER NOT NULL DEFAULT 0,
                duration_total_ms INTEGER NOT NULL DEFAULT 0,
                duration_max_ms INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
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
                object_sha256 TEXT NULL,
                encryption_algorithm TEXT NULL,
                wrapped_dek TEXT NULL,
                wrapped_dek_key_name TEXT NULL,
                wrapped_dek_key_version TEXT NULL,
                wrapped_dek_metadata TEXT NULL,
                error_summary TEXT NULL,
                created_at TEXT NOT NULL,
                completed_at TEXT NULL,
                retention_until TEXT NULL,
                recovery_key_exported_at TEXT NULL,
                recovery_key_exported_by TEXT NULL,
                modified_at TEXT NULL
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
                object_sha256 TEXT NULL,
                encryption_algorithm TEXT NULL,
                wrapped_dek TEXT NULL,
                wrapped_dek_key_name TEXT NULL,
                wrapped_dek_key_version TEXT NULL,
                wrapped_dek_metadata TEXT NULL,
                error_summary TEXT NULL,
                created_at TEXT NOT NULL,
                completed_at TEXT NULL,
                retention_until TEXT NULL,
                modified_at TEXT NULL
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
            'schema_version' => '20260516000000',
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
            'object_sha256' => str_repeat('a', 64),
            'encryption_algorithm' => 'aes-256-gcm-envelope-v1',
            'wrapped_dek' => 'wrapped-dek-secret',
            'wrapped_dek_key_name' => 'secret-key-name',
            'wrapped_dek_key_version' => 'secret-key-version',
            'wrapped_dek_metadata' => '{}',
            'created_at' => $now,
            'completed_at' => $now,
            'retention_until' => '2099-06-16 12:00:00',
        ]);
        $connection->insert('tenant_backups', [
            'id' => 'tenant-kmpbackup-1',
            'tenant_id' => 'tenant-1',
            'backup_type' => 'json',
            'status' => 'completed',
            'object_uri' => 'backup://tenants/example/11111111-1111-4111-8111-111111111111.json.gz.enc',
            'object_size_bytes' => 789,
            'object_sha256' => str_repeat('b', 64),
            'encryption_algorithm' => 'XCHACHA20-POLY1305-SECRETSTREAM',
            'wrapped_dek' => 'wrapped-dek-secret',
            'wrapped_dek_key_name' => 'tenant.example.kek',
            'wrapped_dek_key_version' => 'v1',
            'wrapped_dek_metadata' => '{}',
            'created_at' => $now,
            'completed_at' => $now,
            'retention_until' => '2099-06-16 12:00:00',
        ]);
        $connection->insert('platform_database_backups', [
            'id' => 'platform-backup-1',
            'backup_type' => 'pg_dump',
            'status' => 'completed',
            'connection_name' => 'platform',
            'database_name' => 'platform-secret-database',
            'object_uri' => 'object://secret-path/platform.dump',
            'object_size_bytes' => 456,
            'object_sha256' => str_repeat('c', 64),
            'encryption_algorithm' => 'aes-256-gcm-envelope-v1',
            'wrapped_dek' => 'wrapped-dek-secret',
            'wrapped_dek_key_name' => 'platform.backup.kek',
            'wrapped_dek_key_version' => 'v1',
            'wrapped_dek_metadata' => '{}',
            'created_at' => '2026-05-16 12:05:00',
            'completed_at' => '2026-05-16 12:06:00',
            'retention_until' => '2099-06-16 12:00:00',
        ]);
        $connection->insert('platform_database_backups', [
            'id' => 'platform-kmpbackup-1',
            'backup_type' => 'pg_dump',
            'status' => 'completed',
            'connection_name' => 'platform',
            'database_name' => 'platform',
            'object_uri' => 'backup://platform/22222222-2222-4222-8222-222222222222.pgdump.enc.json',
            'object_size_bytes' => 987,
            'object_sha256' => str_repeat('d', 64),
            'encryption_algorithm' => 'aes-256-gcm-envelope-v1',
            'wrapped_dek' => 'wrapped-dek-secret',
            'wrapped_dek_key_name' => 'platform.backup.kek',
            'wrapped_dek_key_version' => 'v1',
            'wrapped_dek_metadata' => '{}',
            'created_at' => '2026-05-16 12:05:00',
            'completed_at' => '2026-05-16 12:06:00',
            'retention_until' => '2099-06-16 12:00:00',
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
