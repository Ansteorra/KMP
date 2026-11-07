<?php
declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\Services\ServiceResult;

/**
 * Queue execution capability, separate from the application-facing engine API.
 */
interface WorkflowDeferredWorkInterface
{
    /**
     * Process one persisted wait/attempt inside the worker's bound tenant.
     *
     * @param int $instanceId Workflow instance ID
     * @param string $nodeId Workflow node ID
     * @param int|null $executionLogId Exact wait ID; null is for legacy async jobs
     * @return \App\Services\ServiceResult
     */
    public function processDeferredWork(int $instanceId, string $nodeId, ?int $executionLogId = null): ServiceResult;
}
