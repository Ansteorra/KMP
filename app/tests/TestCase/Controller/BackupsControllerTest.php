<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Controller\BackupsController;
use App\Queue\Task\BackupRestoreTask;
use Cake\TestSuite\TestCase;
use ReflectionClass;

/**
 * @covers \App\Controller\BackupsController
 */
class BackupsControllerTest extends TestCase
{
    public function testRestoreRunnerIsQueuedForWorker(): void
    {
        $reflection = new ReflectionClass(BackupsController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('enqueueRestoreRunner');
        $method->setAccessible(true);

        $job = $method->invoke($controller, 'abc123', 'restore-123');

        $this->assertSame(BackupRestoreTask::taskName(), $job->job_task);
        $this->assertSame('backup_restore', $job->job_group);
        $this->assertSame('restore-restore-123', $job->reference);
        $this->assertSame('Restore queued.', $job->status);
        $this->assertSame([
            'token' => 'abc123',
            'restore_id' => 'restore-123',
        ], $job->data);
    }
}
