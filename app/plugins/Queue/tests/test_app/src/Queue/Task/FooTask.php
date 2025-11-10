<?php
declare(strict_types=1);

/**
 * Test fixture Task for Queue plugin tests
 */

namespace TestApp\Queue\Task;

use Queue\Queue\Task;

/**
 * Simple Foo task for testing task name resolution and task finding
 */
class FooTask extends Task
{
    /**
     * Timeout for run, after which the Task is reassigned to a new worker.
     */
    public ?int $timeout = 10;

    /**
     * Run the task
     *
     * @param array<string, mixed> $data The array passed to QueuedJobsTable::createJob()
     * @param int $jobId The id of the QueuedJob entity
     * @return void
     */
    public function run(array $data, int $jobId): void
    {
        // Test task - does nothing
    }
}
