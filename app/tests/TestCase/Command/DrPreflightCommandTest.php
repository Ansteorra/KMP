<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * Tests non-destructive DR preflight reporting.
 */
class DrPreflightCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    private mixed $originalPlatformConfig = null;
    private array $originalWormConfig = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalPlatformConfig = ConnectionManager::getConfig('platform');
        $this->originalWormConfig = (array)Configure::read('PlatformAudit.worm', []);
        $this->configurePlatform();
    }

    protected function tearDown(): void
    {
        if (in_array('platform', ConnectionManager::configured(), true)) {
            ConnectionManager::drop('platform');
        }
        if ($this->originalPlatformConfig !== null) {
            ConnectionManager::setConfig('platform', $this->originalPlatformConfig);
        }
        Configure::write('PlatformAudit.worm', $this->originalWormConfig);

        parent::tearDown();
    }

    public function testPreflightPassesWhenPlatformAndTenantBackupsAreFresh(): void
    {
        $connection = ConnectionManager::get('platform');
        $now = date('Y-m-d H:i:s');
        $connection->insert('tenants', [
            'id' => 'tenant-1',
            'slug' => 'alpha',
            'status' => 'active',
            'primary_host' => 'alpha.example.test',
        ]);
        $connection->insert('platform_database_backups', [
            'id' => 'platform-backup-1',
            'status' => 'completed',
            'object_uri' => 'local://platform-backup-1',
            'completed_at' => $now,
            'created_at' => $now,
        ]);
        $connection->insert('tenant_backups', [
            'id' => 'tenant-backup-1',
            'tenant_id' => 'tenant-1',
            'status' => 'completed',
            'object_uri' => 'local://tenant-backup-1',
            'completed_at' => $now,
            'created_at' => $now,
        ]);
        Configure::write('PlatformAudit.worm', ['sink' => 'file', 'failClosed' => true]);

        $this->exec('dr_preflight --tenant alpha --freshness-hours 24');

        $this->assertExitSuccess();
        $this->assertOutputContains('DR preflight: PASS');
        $this->assertOutputContains('Platform backup: fresh');
        $this->assertOutputContains('Tenant alpha: fresh');
        $this->assertOutputContains('WORM audit: sink=file fail_closed=true');
    }

    public function testPreflightFailsWhenTenantBackupIsStale(): void
    {
        $connection = ConnectionManager::get('platform');
        $now = date('Y-m-d H:i:s');
        $stale = date('Y-m-d H:i:s', strtotime('-2 days'));
        $connection->insert('tenants', [
            'id' => 'tenant-1',
            'slug' => 'alpha',
            'status' => 'active',
            'primary_host' => 'alpha.example.test',
        ]);
        $connection->insert('platform_database_backups', [
            'id' => 'platform-backup-1',
            'status' => 'completed',
            'object_uri' => 'local://platform-backup-1',
            'completed_at' => $now,
            'created_at' => $now,
        ]);
        $connection->insert('tenant_backups', [
            'id' => 'tenant-backup-1',
            'tenant_id' => 'tenant-1',
            'status' => 'completed',
            'object_uri' => 'local://tenant-backup-1',
            'completed_at' => $stale,
            'created_at' => $stale,
        ]);

        $this->exec('dr_preflight --tenant alpha --freshness-hours 24 --json');

        $this->assertExitError();
        $this->assertOutputContains('"pass": false');
        $this->assertOutputContains('"latest_backup_id": "tenant-backup-1"');
    }

    private function configurePlatform(): void
    {
        if (in_array('platform', ConnectionManager::configured(), true)) {
            ConnectionManager::drop('platform');
        }
        ConnectionManager::setConfig('platform', [
            'className' => 'Cake\Database\Connection',
            'driver' => Sqlite::class,
            'database' => ':memory:',
            'cacheMetadata' => false,
            'quoteIdentifiers' => false,
        ]);
        $this->createPlatformTables();
    }

    private function createPlatformTables(): void
    {
        $connection = ConnectionManager::get('platform');
        $connection->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL,
                primary_host TEXT
            )',
        );
        $connection->execute(
            'CREATE TABLE platform_database_backups (
                id TEXT PRIMARY KEY,
                status TEXT NOT NULL,
                object_uri TEXT,
                completed_at TEXT,
                created_at TEXT
            )',
        );
        $connection->execute(
            'CREATE TABLE tenant_backups (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                status TEXT NOT NULL,
                object_uri TEXT,
                completed_at TEXT,
                created_at TEXT
            )',
        );
        $connection->execute(
            'CREATE TABLE platform_jobs (
                id TEXT PRIMARY KEY,
                status TEXT NOT NULL
            )',
        );
    }
}
