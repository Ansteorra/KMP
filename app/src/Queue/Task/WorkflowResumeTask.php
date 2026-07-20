<?php
declare(strict_types=1);

namespace App\Queue\Task;

use App\Services\WorkflowEngine\WorkflowEngineInterface;
use Cake\Log\Log;
use Queue\Queue\Task;
use Queue\Queue\ServicesTrait;

/**
 * Asynchronously resumes a waiting workflow instance at a specific node.
 *
 * Created via: QueuedJobs->createJob('WorkflowResume', $data)
 */
class WorkflowResumeTask extends Task {

	use ServicesTrait;

	/**
	 * Timeout for run, after which the Task is reassigned to a new worker.
	 */
	public ?int $timeout = 120;

	/**
	 * Number of retries before giving up.
	 */
	public ?int $retries = 3;

	/**
	 * Resume a waiting workflow instance.
	 *
	 * @param array<string, mixed> $data Must contain 'instanceId' and 'nodeId'; optionally 'outputPort' and 'additionalData'.
	 * @param int $jobId The id of the QueuedJob entity
	 *
	 * @return void
	 */
	public function run(array $data, int $jobId): void {
		$instanceId = $data['instanceId'] ?? null;
		$nodeId = $data['nodeId'] ?? null;
		$outputPort = $data['outputPort'] ?? 'next';
		$additionalData = $data['additionalData'] ?? [];

		if (!$instanceId || !$nodeId) {
			Log::error("WorkflowResumeTask: Missing instanceId or nodeId in job {$jobId}");
			throw new \InvalidArgumentException('Missing required instanceId or nodeId');
		}

		Log::info("WorkflowResumeTask: Resuming instance {$instanceId} at node {$nodeId} via port {$outputPort}");

		$engine = $this->getService(WorkflowEngineInterface::class);
		$result = $engine->resumeWorkflow($instanceId, $nodeId, $outputPort, $additionalData);

		if (!$result->isSuccess()) {
			$error = $result->getError() ?? 'Unknown error';
			Log::error("WorkflowResumeTask: Failed for instance {$instanceId}: {$error}");
			throw new \RuntimeException("WorkflowResumeTask failed: {$error}");
		}

		Log::info("WorkflowResumeTask: Successfully resumed instance {$instanceId}");
	}

}
