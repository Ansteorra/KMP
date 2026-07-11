<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformFleetHealthService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;

class PlatformFleetHealthServiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection([
            'driver' => new Sqlite(),
            'database' => ':memory:',
        ]);
        $this->createSchema();
    }

    public function testSnapshotPrioritizesBackupErrorAndPerformanceRisk(): void
    {
        $this->connection->insert('tenants', [
            'id' => 'tenant-1',
            'slug' => 'acme',
            'display_name' => 'Acme Kingdom',
            'status' => 'active',
            'region' => 'us',
            'primary_host' => 'acme.example.test',
            'schema_version' => '20260701000000',
            'created_at' => '2026-01-01 00:00:00',
            'activated_at' => '2026-01-02 00:00:00',
        ]);
        $this->connection->insert('tenant_request_metrics_hourly', [
            'id' => 'metric-1',
            'tenant_id' => 'tenant-1',
            'metric_hour' => '2026-07-10 10:00:00',
            'route_name' => 'Members/index',
            'request_count' => 100,
            'error_count' => 10,
            'server_error_count' => 2,
            'slow_request_count' => 30,
            'duration_total_ms' => 120000,
            'duration_max_ms' => 4000,
        ]);
        $this->connection->insert('tenant_backups', [
            'id' => 'backup-1',
            'tenant_id' => 'tenant-1',
            'status' => 'completed',
            'created_at' => '2026-07-06 01:00:00',
            'completed_at' => '2026-07-06 01:05:00',
            'retention_until' => '2026-08-06 01:05:00',
        ]);

        $snapshot = (new PlatformFleetHealthService($this->connection))->snapshot(
            '2026-07-10 12:00:00',
        );

        $this->assertTrue($snapshot['telemetry_available']);
        $this->assertSame(100, $snapshot['summary']['requests_24h']);
        $this->assertSame(10.0, $snapshot['summary']['error_rate_24h']);
        $this->assertSame(1200, $snapshot['summary']['average_duration_ms_24h']);
        $this->assertSame(1, $snapshot['summary']['critical_tenants']);
        $this->assertSame(0.0, $snapshot['summary']['backup_coverage_percent']);
        $tenant = $snapshot['tenants'][0];
        $this->assertSame('critical', $tenant['risk_level']);
        $this->assertContains('The latest tenant backup is older than 24 hours.', $tenant['attention']);
        $this->assertContains('Request error rate is 10.0%.', $tenant['attention']);
        $this->assertContains('Average response time is 1200 ms.', $tenant['attention']);
    }

    public function testSnapshotTreatsMissingActiveTenantBackupAsCritical(): void
    {
        $this->connection->insert('tenants', [
            'id' => 'tenant-2',
            'slug' => 'bravo',
            'display_name' => 'Bravo Kingdom',
            'status' => 'active',
            'region' => 'us',
            'primary_host' => 'bravo.example.test',
            'schema_version' => null,
            'created_at' => '2026-07-01 00:00:00',
            'activated_at' => '2026-07-01 00:10:00',
        ]);

        $snapshot = (new PlatformFleetHealthService($this->connection))->snapshot(
            '2026-07-10 12:00:00',
        );

        $tenant = $snapshot['tenants'][0];
        $this->assertSame('critical', $tenant['risk_level']);
        $this->assertSame(['No retained tenant backup is available.'], $tenant['attention']);
    }

    private function createSchema(): void
    {
        $this->connection->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT NOT NULL,
                display_name TEXT NOT NULL,
                status TEXT NOT NULL,
                region TEXT NULL,
                primary_host TEXT NULL,
                schema_version TEXT NULL,
                created_at TEXT NOT NULL,
                activated_at TEXT NULL
            )',
        );
        $this->connection->execute(
            'CREATE TABLE tenant_request_metrics_hourly (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                metric_hour TEXT NOT NULL,
                route_name TEXT NOT NULL,
                request_count INTEGER NOT NULL,
                error_count INTEGER NOT NULL,
                server_error_count INTEGER NOT NULL,
                slow_request_count INTEGER NOT NULL,
                duration_total_ms INTEGER NOT NULL,
                duration_max_ms INTEGER NOT NULL
            )',
        );
        $this->connection->execute(
            'CREATE TABLE tenant_backups (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                completed_at TEXT NULL,
                retention_until TEXT NULL
            )',
        );
        $this->connection->execute(
            'CREATE TABLE platform_jobs (
                id TEXT PRIMARY KEY,
                tenant_id TEXT NULL,
                job_type TEXT NOT NULL,
                status TEXT NOT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL,
                started_at TEXT NULL,
                finished_at TEXT NULL
            )',
        );
        $this->connection->execute(
            'CREATE TABLE platform_schedules (
                name TEXT PRIMARY KEY,
                enabled INTEGER NOT NULL,
                status TEXT NOT NULL,
                last_run_at TEXT NULL,
                next_run_at TEXT NULL,
                last_success_at TEXT NULL,
                last_failure_at TEXT NULL,
                last_error TEXT NULL
            )',
        );
        $this->connection->execute(
            'CREATE TABLE platform_database_backups (
                id TEXT PRIMARY KEY,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                completed_at TEXT NULL,
                retention_until TEXT NULL
            )',
        );
    }
}
