<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\Audit\NullWormAuditSink;
use App\Services\Platform\PlatformAuditService;
use App\Services\Platform\TenantLifecycleService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TenantLifecycleServiceTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection([
            'driver' => Sqlite::class,
            'database' => ':memory:',
        ]);
        $this->connection->execute(
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
        $this->connection->execute(
            'CREATE TABLE platform_jobs (
                id TEXT PRIMARY KEY,
                tenant_id TEXT,
                job_type TEXT NOT NULL,
                status TEXT NOT NULL
            )',
        );
        $this->connection->execute(
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
        $this->connection->insert('tenants', [
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

        $events = $this->connection->execute(
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
        $this->connection->insert('platform_jobs', [
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

    public function testArchivedTenantCannotBeReactivated(): void
    {
        $this->connection->update('tenants', ['status' => 'archived'], ['id' => 'tenant-1']);

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
            $this->connection,
            new PlatformAuditService($this->connection, new NullWormAuditSink(), false),
        );
    }
}
