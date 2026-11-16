<?php
declare(strict_types=1);

namespace App\Queue\Task;

use App\Services\WorkflowEngine\WorkflowDeferredWorkInterface;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use Cake\Log\Log;
use InvalidArgumentException;
use Queue\Queue\ServicesTrait;
use Queue\Queue\Task;
use RuntimeException;

/**
 * Executes persisted timers/action attempts or legacy application resume callbacks.
 *
 * Created via: QueuedJobs->createJob('WorkflowResume', $data)
 */
class WorkflowResumeTask extends Task
{
    use ServicesTrait;

    /**
     * Timeout for run, after which the Task is reassigned to a new worker.
     */
    public ?int $timeout = 120;

    /**
     * Infrastructure retries; action retry counts are persisted by the engine.
     */
    public ?int $retries = 3;

    /**
     * Execute work in the tenant already bound by the queue worker.
     *
     * @param array<string, mixed> $data Instance/node IDs, optional executionLogId or legacy resume data
     * @param int $jobId The id of the QueuedJob entity
     * @return void
     */
    public function run(array $data, int $jobId): void
    {
        $instanceId = $data['instanceId'] ?? null;
        $nodeId = $data['nodeId'] ?? null;
        $outputPort = $data['outputPort'] ?? 'next';
        $additionalData = $data['additionalData'] ?? [];

        if (!$instanceId || !$nodeId) {
            Log::error("WorkflowResumeTask: Missing instanceId or nodeId in job {$jobId}");
            throw new InvalidArgumentException('Missing required instanceId or nodeId');
        }

        Log::info("WorkflowResumeTask: Resuming instance {$instanceId} at node {$nodeId} via port {$outputPort}");

        $engine = $this->getService(WorkflowEngineInterface::class);
        if (isset($data['executionLogId']) || isset($additionalData['asyncAction'])) {
            if (!$engine instanceof WorkflowDeferredWorkInterface) {
                throw new RuntimeException('Workflow engine does not support deferred execution.');
            }
            $result = $engine->processDeferredWork($instanceId, $nodeId, $data['executionLogId'] ?? null);
        } else {
            $result = $engine->resumeWorkflow($instanceId, $nodeId, $outputPort, $additionalData);
        }

        if (!$result->isSuccess()) {
            $error = $result->getError() ?? 'Unknown error';
            Log::error("WorkflowResumeTask: Failed for instance {$instanceId}: {$error}");
            throw new RuntimeException("WorkflowResumeTask failed: {$error}");
        }

        Log::info("WorkflowResumeTask: Successfully resumed instance {$instanceId}");
    }
}
