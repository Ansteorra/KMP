<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Backups;

use App\Services\Backups\TenantRestoreDrillService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\TestSuite\TestCase;
use Cake\Utility\Text;
use RuntimeException;

class TenantRestoreDrillServiceTest extends TestCase
{
    private ?array $previousPlatformConfig = null;

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
    }

    protected function tearDown(): void
    {
        ConnectionManager::drop('platform');
        if ($this->previousPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->previousPlatformConfig);
        }
        parent::tearDown();
    }

    public function testSelectsMostRecentSuccessfulBackupAndRecordsDryRunPlan(): void
    {
        $alphaId = $this->insertTenant('alpha');
        $bravoId = $this->insertTenant('bravo');
        $this->insertBackup($alphaId, 'alpha-old', 'completed', DateTime::now('UTC')->subHours(3));
        $bravoBackup = $this->insertBackup($bravoId, 'bravo-recent', 'completed', DateTime::now('UTC')->subHours(1));
        $this->insertBackup($alphaId, 'alpha-failed', 'failed', DateTime::now('UTC'));
        $verifier = new RecordingRestoreDrillVerifier();
        $service = new TenantRestoreDrillService($this->platform(), $verifier);

        $result = $service->planRecentDrill(null, 36);

        $this->assertSame($bravoBackup, $result->backupId);
        $this->assertSame('bravo', $result->tenantSlug);
        $this->assertSame('planned', $result->status);
        $this->assertTrue($result->dryRun);
        $this->assertCount(1, $verifier->plans);
        $this->assertTrue($verifier->plans[0]->dryRun);
        $this->assertFalse($verifier->plans[0]->destructiveExecution);

        $job = $this->platform()->execute('SELECT * FROM platform_jobs WHERE id = ?', [$result->jobId])->fetch('assoc');
        $this->assertSame('tenant_restore_drill', $job['job_type']);
        $this->assertSame('planned', $job['status']);
        $parameters = json_decode((string)$job['parameters'], true);
        $this->assertSame($bravoBackup, $parameters['backup_id']);
        $this->assertSame('bravo', $parameters['tenant_slug']);
        $this->assertTrue($parameters['dry_run']);
        $this->assertFalse($parameters['destructive_execution']);
        $this->assertStringNotContainsString('wrapped_dek', (string)$job['parameters']);
    }

    public function testTenantFilterSelectsRecentBackupForTenant(): void
    {
        $alphaId = $this->insertTenant('alpha');
        $bravoId = $this->insertTenant('bravo');
        $alphaBackup = $this->insertBackup($alphaId, 'alpha-recent', 'completed', DateTime::now('UTC')->subHours(2));
        $this->insertBackup($bravoId, 'bravo-newer', 'completed', DateTime::now('UTC')->subHours(1));
        $service = new TenantRestoreDrillService($this->platform(), new RecordingRestoreDrillVerifier());

        $result = $service->planRecentDrill('alpha', 36);

        $this->assertSame($alphaBackup, $result->backupId);
        $this->assertSame('alpha', $result->tenantSlug);
    }

    public function testLegacyPgDumpBackupRemainsEligibleForRestoreDrills(): void
    {
        $tenantId = $this->insertTenant('alpha');
        $backupId = $this->insertBackup(
            $tenantId,
            'alpha-legacy',
            'completed',
            DateTime::now('UTC')->subHours(1),
            'pg_dump',
        );
        $service = new TenantRestoreDrillService($this->platform(), new RecordingRestoreDrillVerifier());

        $result = $service->planRecentDrill('alpha', 24);

        $this->assertSame($backupId, $result->backupId);
    }

    public function testNoSuccessfulBackupRecordsClearFailedDrillJob(): void
    {
        $tenantId = $this->insertTenant('alpha');
        $this->insertBackup($tenantId, 'alpha-failed', 'failed', DateTime::now('UTC')->subHours(1));
        $service = new TenantRestoreDrillService($this->platform(), new RecordingRestoreDrillVerifier());

        try {
            $service->planRecentDrill('alpha', 24);
            $this->fail('Expected missing successful backup failure.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No completed tenant backups found within 24 hours for tenant "alpha"', $e->getMessage());
        }

        $job = $this->platform()->execute('SELECT * FROM platform_jobs')->fetch('assoc');
        $this->assertSame('tenant_restore_drill', $job['job_type']);
        $this->assertSame('failed', $job['status']);
        $this->assertSame('No completed tenant backups were found for restore drill planning.', $job['last_error']);
    }

    public function testVerifierErrorsAreScrubbedBeforeOutputAndStorage(): void
    {
        $tenantId = $this->insertTenant('alpha');
        $this->insertBackup($tenantId, 'alpha-recent', 'completed', DateTime::now('UTC')->subHours(1));
        $verifier = new RecordingRestoreDrillVerifier(
            'password=hunter2 PGPASSWORD=tenant-secret token:abc123 admin@example.test',
        );
        $service = new TenantRestoreDrillService($this->platform(), $verifier);

        try {
            $service->planRecentDrill('alpha', 24);
            $this->fail('Expected verifier failure.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('hunter2', $e->getMessage());
            $this->assertStringNotContainsString('tenant-secret', $e->getMessage());
            $this->assertStringNotContainsString('abc123', $e->getMessage());
            $this->assertStringNotContainsString('admin@example.test', $e->getMessage());
        }

        $lastError = (string)$this->platform()->execute('SELECT last_error FROM platform_jobs')->fetchColumn(0);
        $this->assertStringContainsString('password=[redacted]', $lastError);
        $this->assertStringContainsString('PGPASSWORD=[redacted]', $lastError);
        $this->assertStringContainsString('token=[redacted]', $lastError);
        $this->assertStringContainsString('[redacted-email]', $lastError);
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
                recovery_key_exported_at TEXT NULL,
                recovery_key_exported_by TEXT NULL,
                modified_at TEXT NULL
            )',
        );
    }

    private function insertTenant(string $slug): string
    {
        $id = Text::uuid();
        $this->platform()->insert('tenants', [
            'id' => $id,
            'slug' => $slug,
            'display_name' => ucfirst($slug),
            'status' => 'active',
            'db_server' => 'db.example.test',
            'db_name' => $slug . '_db',
            'db_role' => $slug . '_role',
            'schema_version' => null,
        ]);

        return $id;
    }

    private function insertBackup(
        string $tenantId,
        string $label,
        string $status,
        DateTime $completedAt,
        string $backupType = 'json',
    ): string {
        $id = Text::uuid();
        $extension = $backupType === 'json' ? '.json.gz.enc' : '.pgdump.enc';
        $this->platform()->insert('tenant_backups', [
            'id' => $id,
            'tenant_id' => $tenantId,
            'platform_job_id' => null,
            'backup_type' => $backupType,
            'status' => $status,
            'object_uri' => 'local://' . $label . '/' . $id . $extension,
            'object_size_bytes' => 100,
            'object_sha256' => str_repeat('a', 64),
            'encryption_algorithm' => 'aes-256-gcm+x25519-wrap',
            'wrapped_dek' => 'not-used-by-drill-planner',
            'wrapped_dek_key_name' => 'tenant.' . $label . '.kek',
            'wrapped_dek_key_version' => 'test',
            'wrapped_dek_metadata' => '{}',
            'error_summary' => null,
            'retention_until' => null,
            'retention_policy' => null,
            'created_at' => $completedAt->subMinutes(5)->format('Y-m-d H:i:s'),
            'started_at' => $completedAt->subMinutes(4)->format('Y-m-d H:i:s'),
            'completed_at' => $completedAt->format('Y-m-d H:i:s'),
            'modified_at' => $completedAt->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    private function platform(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get('platform');

        return $connection;
    }
}
