<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\LocalTenantBackupStorage;
use App\Services\Backups\TenantBackupDumperInterface;
use App\Services\Backups\TenantBackupEncryptor;
use App\Services\Backups\TenantBackupService;
use App\Services\Secrets\SensitiveString;
use App\Test\TestCase\Support\ArraySecretStore;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Cake\Utility\Text;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class TenantBackupServiceTest extends TestCase
{
    private ?array $previousPlatformConfig = null;
    private string $backupRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousPlatformConfig = ConnectionManager::getConfig('platform');
        if (in_array('platform', ConnectionManager::configured(), true)) {
            ConnectionManager::drop('platform');
        }
        ConnectionManager::setConfig('platform', [
            'className' => Connection::class,
            'driver' => Sqlite::class,
            'database' => ':memory:',
            'timezone' => 'UTC',
            'cacheMetadata' => false,
            'quoteIdentifiers' => false,
        ]);
        $this->createPlatformSchema();
        $this->backupRoot = TMP . 'backup-service-test-' . str_replace('.', '-', uniqid('', true));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->backupRoot);
        ConnectionManager::drop('platform');
        if ($this->previousPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
        }
        parent::tearDown();
    }

    public function testBackupCreatesCompletedMetadataAndEncryptedObject(): void
    {
        $tenantId = $this->insertTenant('demo', 'demo_db');
        $plaintext = 'plain backup sample: do not store me';
        $secrets = new ArraySecretStore([
            'tenant.demo.db.password' => 'db-password',
            'tenant.demo.kek' => 'backup-kek',
        ]);
        $encryptor = new TenantBackupEncryptor();
        $service = $this->service($secrets, new FakeTenantBackupDumper($plaintext), $encryptor);

        $result = $service->backupTenant('demo', 7);

        $this->assertSame('completed', $result->status);
        $job = $this->platform()->execute('SELECT * FROM platform_jobs')->fetch('assoc');
        $this->assertSame('completed', $job['status']);
        $this->assertSame($tenantId, $job['tenant_id']);
        $backup = $this->platform()->execute('SELECT * FROM tenant_backups')->fetch('assoc');
        $this->assertSame('completed', $backup['status']);
        $this->assertSame(TenantBackupService::BACKUP_TYPE, $backup['backup_type']);
        $this->assertSame($result->objectUri, $backup['object_uri']);
        $this->assertNotEmpty($backup['object_sha256']);
        $this->assertSame(TenantBackupEncryptor::DATA_ALGORITHM, $backup['encryption_algorithm']);
        $this->assertSame('tenant.demo.kek', $backup['wrapped_dek_key_name']);
        $this->assertSame('unversioned', $backup['wrapped_dek_key_version']);
        $this->assertStringNotContainsString($plaintext, (string)$backup['wrapped_dek']);

        $storedPath = $this->storedPath('demo', $result->backupId);
        $cipherPayload = (string)file_get_contents($storedPath);
        $this->assertStringNotContainsString($plaintext, $cipherPayload);
        $decrypted = $encryptor->decryptFileForTest(
            $storedPath,
            (string)$backup['wrapped_dek'],
            json_decode((string)$backup['wrapped_dek_metadata'], true),
            new SensitiveString('backup-kek'),
        );
        $this->assertSame($plaintext, $decrypted);
    }

    public function testUnsafeTenantSlugFailsBeforeMetadataUsesIt(): void
    {
        $service = $this->service(new ArraySecretStore([]), new FakeTenantBackupDumper('unused'), new TenantBackupEncryptor());

        try {
            $service->backupTenant('demo;bad');
            $this->fail('Expected unsafe slug to fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('Unsafe tenant slug.', $e->getMessage());
        }
    }

    public function testDumperFailureRedactsPasswordInJobAndBackupErrors(): void
    {
        $this->insertTenant('demo', 'demo_db');
        $service = $this->service(
            new ArraySecretStore([
                'tenant.demo.db.password' => 'super-secret-password',
                'tenant.demo.kek' => 'backup-kek',
            ]),
            new FailingTenantBackupDumper('pg_dump failed password=super-secret-password PGPASSWORD=super-secret-password'),
            new TenantBackupEncryptor(),
        );

        try {
            $service->backupTenant('demo');
            $this->fail('Expected dumper failure.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('super-secret-password', $e->getMessage());
        }
        $job = $this->platform()->execute('SELECT status, last_error FROM platform_jobs')->fetch('assoc');
        $backup = $this->platform()->execute('SELECT status, error_summary FROM tenant_backups')->fetch('assoc');
        $this->assertSame('failed', $job['status']);
        $this->assertSame('failed', $backup['status']);
        $this->assertStringNotContainsString('super-secret-password', (string)$job['last_error']);
        $this->assertStringNotContainsString('super-secret-password', (string)$backup['error_summary']);
        $this->assertStringContainsString('[redacted]', (string)$job['last_error']);
    }

    public function testMissingKekSecretFailsCleanlyAndMarksBackupFailed(): void
    {
        $this->insertTenant('demo', 'demo_db');
        $service = $this->service(
            new ArraySecretStore(['tenant.demo.db.password' => 'db-password']),
            new FakeTenantBackupDumper('unused'),
            new TenantBackupEncryptor(),
        );

        try {
            $service->backupTenant('demo');
            $this->fail('Expected missing KEK failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Missing backup KEK secret for tenant "demo".', $e->getMessage());
        }
        $job = $this->platform()->execute('SELECT status, last_error FROM platform_jobs')->fetch('assoc');
        $backup = $this->platform()->execute('SELECT status, error_summary FROM tenant_backups')->fetch('assoc');
        $this->assertSame('failed', $job['status']);
        $this->assertSame('failed', $backup['status']);
        $this->assertSame('Missing backup KEK secret for tenant "demo".', $job['last_error']);
    }

    public function testBackupReusesQueuedPortalJob(): void
    {
        $tenantId = $this->insertTenant('demo', 'demo_db');
        $jobId = Text::uuid();
        $this->platform()->insert('platform_jobs', [
            'id' => $jobId,
            'tenant_id' => $tenantId,
            'requested_by_platform_user_id' => 'platform-admin-1',
            'job_type' => TenantBackupService::JOB_TYPE,
            'status' => 'queued',
            'idempotency_key' => 'portal-backup',
            'parameters' => '{"tenant_slug":"demo"}',
            'log_uri' => null,
            'last_error' => null,
            'created_at' => '2026-07-10 00:00:00',
            'started_at' => null,
            'finished_at' => null,
            'modified_at' => null,
        ]);
        $service = $this->service(
            new ArraySecretStore([
                'tenant.demo.db.password' => 'db-password',
                'tenant.demo.kek' => 'backup-kek',
            ]),
            new FakeTenantBackupDumper('portal backup'),
            new TenantBackupEncryptor(),
        );

        $result = $service->backupTenant('demo', 7, $jobId);

        $this->assertSame($jobId, $result->jobId);
        $this->assertSame(
            1,
            (int)$this->platform()->execute('SELECT COUNT(*) FROM platform_jobs')->fetchColumn(0),
        );
        $backupJobId = $this->platform()->execute(
            'SELECT platform_job_id FROM tenant_backups',
        )->fetchColumn(0);
        $this->assertSame($jobId, $backupJobId);
    }

    private function service(
        ArraySecretStore $secrets,
        TenantBackupDumperInterface $dumper,
        TenantBackupEncryptor $encryptor,
    ): TenantBackupService {
        return new TenantBackupService(
            $this->platform(),
            $secrets,
            $dumper,
            $encryptor,
            new LocalTenantBackupStorage($this->backupRoot, true),
        );
    }

    private function createPlatformSchema(): void
    {
        $this->platform()->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT NOT NULL,
                display_name TEXT NOT NULL,
                status TEXT NOT NULL,
                db_server TEXT NOT NULL,
                db_name TEXT NOT NULL,
                db_role TEXT NOT NULL,
                schema_version TEXT NULL
            )',
        );
        $this->platform()->execute(
            'CREATE TABLE platform_jobs (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NULL,
                requested_by_platform_user_id TEXT NULL,
                job_type TEXT NOT NULL,
                status TEXT NOT NULL,
                idempotency_key TEXT NULL,
                parameters TEXT NULL,
                log_uri TEXT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL,
                started_at TEXT NULL,
                finished_at TEXT NULL,
                modified_at TEXT NULL
            )',
        );
        $this->platform()->execute(
            'CREATE TABLE tenant_backups (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                platform_job_id TEXT NULL,
                backup_type TEXT NOT NULL,
                status TEXT NOT NULL,
                object_uri TEXT NULL,
                object_size_bytes INTEGER NULL,
                object_sha256 TEXT NULL,
                encryption_algorithm TEXT NOT NULL,
                wrapped_dek TEXT NULL,
                wrapped_dek_key_name TEXT NOT NULL,
                wrapped_dek_key_version TEXT NOT NULL,
                wrapped_dek_metadata TEXT NULL,
                error_summary TEXT NULL,
                retention_until TEXT NULL,
                retention_policy TEXT NULL,
                created_at TEXT NOT NULL,
                started_at TEXT NULL,
                completed_at TEXT NULL,
                modified_at TEXT NULL
            )',
        );
    }

    private function insertTenant(string $slug, string $dbName): string
    {
        $id = Text::uuid();
        $this->platform()->insert('tenants', [
            'id' => $id,
            'slug' => $slug,
            'display_name' => ucfirst($slug),
            'status' => 'active',
            'db_server' => 'db.example.test',
            'db_name' => $dbName,
            'db_role' => $dbName . '_role',
            'schema_version' => null,
        ]);

        return $id;
    }

    private function storedPath(string $slug, string $backupId): string
    {
        return $this->backupRoot . DIRECTORY_SEPARATOR . 'objects' . DIRECTORY_SEPARATOR . $slug
            . DIRECTORY_SEPARATOR . $backupId . '.json.gz.enc';
    }

    private function platform(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('platform');

        return $connection;
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
