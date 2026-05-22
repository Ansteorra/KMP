<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\LocalTenantBackupStorage;
use App\Services\Backups\TenantBackupEncryptor;
use App\Services\Backups\TenantBackupService;
use App\Services\Backups\TenantRestoreService;
use App\Test\TestCase\Support\ArraySecretStore;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Cake\Utility\Text;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class TenantRestoreServiceTest extends TestCase
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
        $this->backupRoot = TMP . 'restore-service-test-' . str_replace('.', '-', uniqid('', true));
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

    public function testSameTenantRestoreRequiresExplicitConfirmation(): void
    {
        $service = $this->restoreService(new ArraySecretStore([]), new RecordingTenantBackupRestorer());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Same-tenant restore requires --confirm-destructive.');
        $service->restoreTenantBackup(Text::uuid(), TenantRestoreService::MODE_SAME_TENANT, null, false);
    }

    public function testCrossTenantRestoreRequiresTargetAndDifferentTenant(): void
    {
        $backupId = $this->createCompletedBackup('demo', 'demo_db');
        $service = $this->restoreService($this->secrets(['demo']), new RecordingTenantBackupRestorer());

        try {
            $service->restoreTenantBackup($backupId, TenantRestoreService::MODE_CROSS_TENANT, null, false);
            $this->fail('Expected missing target tenant failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Cross-tenant restore requires --target-tenant.', $e->getMessage());
        }

        try {
            $service->restoreTenantBackup($backupId, TenantRestoreService::MODE_CROSS_TENANT, 'demo', false);
            $this->fail('Expected same target failure.');
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Cross-tenant restore target must differ from the backup source tenant.',
                $e->getMessage(),
            );
        }
    }

    public function testUnsafeTargetIdentifierFailsBeforeRestoreExecution(): void
    {
        $backupId = $this->createCompletedBackup('demo', 'demo_db');
        $this->insertTenant('target', 'bad;db');
        $restorer = new RecordingTenantBackupRestorer();
        $service = $this->restoreService($this->secrets(['demo', 'target']), $restorer);

        try {
            $service->restoreTenantBackup($backupId, TenantRestoreService::MODE_CROSS_TENANT, 'target', false);
            $this->fail('Expected unsafe target database failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Unsafe target tenant database name.', $e->getMessage());
        }
        $this->assertSame([], $restorer->restoreCalls);
        $this->assertSame([], $restorer->argvCalls);
    }

    public function testRestoreUsesInjectableRestorerArgvAndDoesNotLeakPassword(): void
    {
        $backupId = $this->createCompletedBackup('demo', 'demo_db');
        $this->insertTenant('target', 'target_db');
        $restorer = new RecordingTenantBackupRestorer();
        $service = $this->restoreService($this->secrets(['demo', 'target']), $restorer);

        $result = $service->restoreTenantBackup(
            $backupId,
            TenantRestoreService::MODE_CROSS_TENANT,
            'target',
            false,
        );

        $this->assertSame('completed', $result->status);
        $this->assertSame('target', $result->targetTenantSlug);
        $this->assertCount(1, $restorer->restoreCalls);
        $this->assertSame('target', $restorer->restoreCalls[0]['tenant']);
        $allArgv = implode(' ', array_merge(...$restorer->argvCalls));
        $this->assertStringContainsString('target_db', $allArgv);
        $this->assertStringNotContainsString('target-secret-password', $allArgv);
        $job = $this->platform()->execute('SELECT status, parameters, last_error FROM platform_jobs WHERE id = ?', [
            $result->jobId,
        ])->fetch('assoc');
        $this->assertSame('completed', $job['status']);
        $this->assertStringContainsString('"mode":"cross-tenant"', (string)$job['parameters']);
        $this->assertStringNotContainsString('target-secret-password', (string)$job['parameters']);
    }

    public function testDryRunDecryptsAndPlansWithoutExecutingRestore(): void
    {
        $backupId = $this->createCompletedBackup('demo', 'demo_db');
        $restorer = new RecordingTenantBackupRestorer();
        $service = $this->restoreService($this->secrets(['demo']), $restorer);

        $result = $service->restoreTenantBackup(
            $backupId,
            TenantRestoreService::MODE_SAME_TENANT,
            null,
            true,
            true,
        );

        $this->assertSame('planned', $result->status);
        $this->assertTrue($result->dryRun);
        $this->assertNotEmpty($restorer->argvCalls);
        $this->assertSame([], $restorer->restoreCalls);
    }

    public function testMissingBackupAndMissingKekFailCleanly(): void
    {
        $service = $this->restoreService(new ArraySecretStore([]), new RecordingTenantBackupRestorer());
        try {
            $service->restoreTenantBackup(Text::uuid(), TenantRestoreService::MODE_SAME_TENANT, null, true);
            $this->fail('Expected missing backup failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Tenant backup was not found.', $e->getMessage());
        }

        $backupId = $this->createCompletedBackup('demo', 'demo_db');
        try {
            $service->restoreTenantBackup($backupId, TenantRestoreService::MODE_SAME_TENANT, null, true);
            $this->fail('Expected missing KEK failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('Missing backup KEK secret for tenant restore.', $e->getMessage());
        }
    }

    public function testUnsafeBackupIdFailsBeforeLookup(): void
    {
        $service = $this->restoreService(new ArraySecretStore([]), new RecordingTenantBackupRestorer());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsafe backup id.');
        $service->restoreTenantBackup('bad/backup', TenantRestoreService::MODE_SAME_TENANT, null, true);
    }

    private function createCompletedBackup(string $slug, string $dbName): string
    {
        $this->insertTenant($slug, $dbName);
        $backup = new TenantBackupService(
            $this->platform(),
            $this->secrets([$slug]),
            new FakeTenantBackupDumper('tenant backup plaintext for ' . $slug),
            new TenantBackupEncryptor(),
            new LocalTenantBackupStorage($this->backupRoot, true),
        );

        return $backup->backupTenant($slug)->backupId;
    }

    /**
     * @param list<string> $slugs
     */
    private function secrets(array $slugs): ArraySecretStore
    {
        $values = [];
        foreach ($slugs as $slug) {
            $values['tenant.' . $slug . '.db.password'] = $slug . '-secret-password';
            $values['tenant.' . $slug . '.kek'] = $slug . '-backup-kek';
        }
        if (isset($values['tenant.demo.kek'])) {
            $values['tenant.demo.kek'] = 'demo-backup-kek';
        }

        return new ArraySecretStore($values);
    }

    private function restoreService(ArraySecretStore $secrets, RecordingTenantBackupRestorer $restorer): TenantRestoreService
    {
        return new TenantRestoreService(
            $this->platform(),
            $secrets,
            new LocalTenantBackupStorage($this->backupRoot, true),
            new TenantBackupEncryptor(),
            $restorer,
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
            'db_role' => str_replace('-', '_', $slug) . '_role',
            'schema_version' => null,
        ]);

        return $id;
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
