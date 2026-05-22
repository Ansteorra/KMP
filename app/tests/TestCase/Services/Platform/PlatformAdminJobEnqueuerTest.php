<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformAdminJobEnqueuer;
use App\Services\Platform\PlatformAuditService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;

class PlatformAdminJobEnqueuerTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new Connection([
            'driver' => new Sqlite(),
            'database' => ':memory:',
        ]);
        $this->connection->execute(
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
        $this->connection->execute(
            'CREATE TABLE audit_events (
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

    public function testEnqueuePersistsJobAndAuditEvent(): void
    {
        $service = new PlatformAdminJobEnqueuer(
            $this->connection,
            new PlatformAuditService($this->connection),
        );

        $job = $service->enqueue(
            'tenant_backup',
            'tenant-1',
            'platform-user-1',
            ['scope' => 'tenant', 'tenant_slug' => 'example'],
            'tenant_backup:example:nonce',
            'manual backup request',
            ['ipAddress' => '127.0.0.1', 'userAgent' => 'test'],
        );

        $this->assertSame('tenant_backup', $job['job_type']);
        $this->assertSame('queued', $job['status']);
        $storedJob = $this->connection->execute('SELECT * FROM platform_jobs')->fetch('assoc');
        $this->assertSame($job['id'], $storedJob['id']);
        $this->assertSame('tenant_backup:example:nonce', $storedJob['idempotency_key']);

        $audit = $this->connection->execute('SELECT * FROM audit_events')->fetch('assoc');
        $this->assertSame('platform_job.queued', $audit['action']);
        $this->assertSame($job['id'], $audit['subject_id']);
        $this->assertStringContainsString('tenant_backup:example:nonce', $audit['metadata']);
    }

    public function testEnqueueReturnsExistingJobForDuplicateIdempotencyKey(): void
    {
        $service = new PlatformAdminJobEnqueuer(
            $this->connection,
            new PlatformAuditService($this->connection),
        );

        $first = $service->enqueue(
            'tenant_backup',
            'tenant-1',
            'platform-user-1',
            ['scope' => 'tenant'],
            'duplicate-key',
            'manual backup request',
        );
        $second = $service->enqueue(
            'tenant_backup',
            'tenant-1',
            'platform-user-1',
            ['scope' => 'tenant', 'changed' => true],
            'duplicate-key',
            'manual backup request',
        );

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(1, (int)$this->connection->execute('SELECT COUNT(*) FROM platform_jobs')->fetchColumn(0));
        $this->assertSame(1, (int)$this->connection->execute('SELECT COUNT(*) FROM audit_events')->fetchColumn(0));
    }
}
