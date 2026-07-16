<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\PlatformJobRetentionService;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\TestSuite\TestCase;
use DateTimeImmutable;
use InvalidArgumentException;

class PlatformJobRetentionServiceTest extends TestCase
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
                job_type TEXT NOT NULL,
                status TEXT NOT NULL,
                created_at TEXT NOT NULL,
                finished_at TEXT NULL,
                modified_at TEXT NULL
            )',
        );
    }

    public function testPruneUsesTieredRetentionAndPreservesActiveJobs(): void
    {
        $this->insertJob('old-schedule', 'platform_schedule', 'completed', '2026-06-01 00:00:00');
        $this->insertJob('recent-schedule', 'platform_schedule', 'completed', '2026-07-10 00:00:00');
        $this->insertJob('old-completed', 'tenant_backup_json', 'completed', '2026-03-01 00:00:00');
        $this->insertJob('recent-completed', 'tenant_backup_json', 'completed', '2026-06-01 00:00:00');
        $this->insertJob('old-failed', 'tenant_restore_json', 'failed', '2025-12-01 00:00:00');
        $this->insertJob('recent-failed', 'tenant_restore_json', 'failed', '2026-06-01 00:00:00');
        $this->insertJob('old-queued', 'tenant_backup_json', 'queued', '2025-01-01 00:00:00');
        $this->insertJob('old-running', 'tenant_backup_json', 'running', '2025-01-01 00:00:00');

        $result = (new PlatformJobRetentionService($this->connection))->prune(
            14,
            90,
            180,
            5000,
            new DateTimeImmutable('2026-07-15 12:00:00'),
        );

        $this->assertSame(['schedule_completed' => 1, 'completed' => 1, 'failed' => 1], $result);
        $remaining = $this->connection->execute(
            'SELECT id FROM platform_jobs ORDER BY id',
        )->fetchAll('assoc');
        $this->assertSame([
            ['id' => 'old-queued'],
            ['id' => 'old-running'],
            ['id' => 'recent-completed'],
            ['id' => 'recent-failed'],
            ['id' => 'recent-schedule'],
        ], $remaining);
    }

    public function testPruneRejectsUnsafeRetention(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Schedule job retention');

        (new PlatformJobRetentionService($this->connection))->prune(0);
    }

    private function insertJob(string $id, string $jobType, string $status, string $finishedAt): void
    {
        $this->connection->insert('platform_jobs', [
            'id' => $id,
            'job_type' => $jobType,
            'status' => $status,
            'created_at' => $finishedAt,
            'finished_at' => $finishedAt,
            'modified_at' => $finishedAt,
        ]);
    }
}
