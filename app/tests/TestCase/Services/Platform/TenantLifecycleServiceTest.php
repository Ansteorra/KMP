<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\Audit\NullWormAuditSink;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\TenantLifecycleService;
use App\Test\TestCase\BaseTestCase;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use RuntimeException;

class TenantLifecycleServiceTest extends BaseTestCase
{
    private Connection $tenantConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantConnection = new Connection([
            'driver' => Sqlite::class,
            'database' => ':memory:',
        ]);
        $this->tenantConnection->execute(
            'CREATE TABLE tenants (
                id TEXT PRIMARY KEY,
                slug TEXT NOT NULL,
                status TEXT NOT NULL,
                schema_version TEXT,
                activated_at TEXT,
                suspended_at TEXT,
                archived_at TEXT,
                modified_at TEXT
            )',
        );
        $this->tenantConnection->execute(
            'CREATE TABLE platform_jobs (
                id TEXT PRIMARY KEY,
                tenant_id TEXT,
                job_type TEXT NOT NULL,
                status TEXT NOT NULL
            )',
        );
        $this->tenantConnection->execute(
            'CREATE TABLE audit_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id TEXT,
                platform_user_id TEXT,
                action TEXT,
                subject_type TEXT,
                subject_id TEXT,
                reason TEXT,
                metadata TEXT,
                ip_address TEXT,
                user_agent TEXT,
                previous_hash TEXT,
                event_hash TEXT,
                created_at TEXT
            )',
        );
        $this->tenantConnection->insert('tenants', [
            'id' => 'tenant-1',
            'slug' => 'example',
            'status' => 'active',
            'schema_version' => '20260710000000',
            'activated_at' => '2026-07-10 00:00:00',
            'suspended_at' => null,
            'archived_at' => null,
            'modified_at' => '2026-07-10 00:00:00',
        ]);
    }

    public function testSuspendAndReactivateAreAudited(): void
    {
        $service = $this->service();

        $suspended = $service->transition(
            'tenant-1',
            'suspended',
            'platform-admin-1',
            'Investigating tenant incident.',
        );
        $this->assertSame('suspended', $suspended['status']);
        $this->assertNotEmpty($suspended['suspended_at']);

        $active = $service->transition(
            'tenant-1',
            'active',
            'platform-admin-1',
            'Incident resolved.',
        );
        $this->assertSame('active', $active['status']);
        $this->assertNull($active['suspended_at']);

        $events = $this->tenantConnection->execute(
            'SELECT action, reason, metadata FROM audit_events ORDER BY id',
        )->fetchAll('assoc');
        $this->assertSame(['tenant.suspended', 'tenant.active'], array_column($events, 'action'));
        $this->assertSame('Incident resolved.', $events[1]['reason']);
        $metadata = json_decode((string)$events[1]['metadata'], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('suspended', $metadata['previous_status']);
        $this->assertSame('active', $metadata['new_status']);
    }

    public function testLifecycleTransitionIsBlockedByActiveOperation(): void
    {
        $this->tenantConnection->insert('platform_jobs', [
            'id' => 'job-1',
            'tenant_id' => 'tenant-1',
            'job_type' => 'tenant_backup',
            'status' => 'queued',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('operation is queued or running');

        $this->service()->transition(
            'tenant-1',
            'suspended',
            'platform-admin-1',
            'Investigating tenant incident.',
        );
    }

    public function testReactivationRequiresCurrentReleaseSchema(): void
    {
        $this->tenantConnection->update('tenants', [
            'status' => 'suspended',
            'schema_version' => '20260709000000',
        ], ['id' => 'tenant-1']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('migrations reach schema 20260710000000');

        $this->service()->transition(
            'tenant-1',
            'active',
            'platform-admin-1',
            'Attempting to return to service.',
        );
    }

    public function testReactivationIsBlockedWhileTenantMigrationRuns(): void
    {
        $this->tenantConnection->update('tenants', ['status' => 'suspended'], ['id' => 'tenant-1']);
        $this->tenantConnection->insert('platform_jobs', [
            'id' => 'migration-job-1',
            'tenant_id' => 'tenant-1',
            'job_type' => 'tenant_migration',
            'status' => 'running',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('operation is queued or running');

        $this->service()->transition(
            'tenant-1',
            'active',
            'platform-admin-1',
            'Migration is still running.',
        );
    }

    public function testArchivedTenantCannotBeReactivated(): void
    {
        $this->tenantConnection->update('tenants', ['status' => 'archived'], ['id' => 'tenant-1']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('from archived to active is not allowed');

        $this->service()->transition(
            'tenant-1',
            'active',
            'platform-admin-1',
            'Invalid recovery attempt.',
        );
    }

    private function service(): TenantLifecycleService
    {
        return new TenantLifecycleService(
            $this->tenantConnection,
            new PlatformAuditService($this->tenantConnection, new NullWormAuditSink(), false),
            '20260710000000',
        );
    }
}
