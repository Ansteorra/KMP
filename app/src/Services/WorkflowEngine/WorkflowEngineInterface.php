<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\Services\ServiceResult;

/**
 * Contract for the workflow execution engine.
 *
 * Provides methods to start, resume, cancel workflows and dispatch triggers.
 */
interface WorkflowEngineInterface
{
    /**
     * Start a new workflow instance from a definition slug.
     *
     * @param string $workflowSlug The workflow definition slug
     * @param array $triggerData Data passed by the trigger event
     * @param int|null $startedBy Member ID who initiated the workflow
     * @param string|null $entityType Optional entity type this workflow operates on
     * @param int|null $entityId Optional entity ID this workflow operates on
     * @return \App\Services\ServiceResult Contains instanceId on success
     */
    public function startWorkflow(
        string $workflowSlug,
        array $triggerData = [],
        ?int $startedBy = null,
        ?string $entityType = null,
        ?int $entityId = null,
    ): ServiceResult;

    /**
     * Resume a waiting workflow instance from a specific node output.
     *
     * @param int $instanceId The workflow instance ID
     * @param string $nodeId The node to resume from
     * @param string $outputPort The output port to follow
     * @param array $additionalData Extra data to merge into context
     * @return \App\Services\ServiceResult
     */
    public function resumeWorkflow(
        int $instanceId,
        string $nodeId,
        string $outputPort,
        array $additionalData = [],
    ): ServiceResult;

    /**
     * Cancel a running or waiting workflow instance.
     *
     * @param int $instanceId The workflow instance ID
     * @param string|null $reason Optional cancellation reason
     * @return \App\Services\ServiceResult
     */
    public function cancelWorkflow(int $instanceId, ?string $reason = null): ServiceResult;

    /**
     * Get the current state of a workflow instance.
     *
     * @param int $instanceId The workflow instance ID
     * @return array|null Instance state array or null if not found
     */
    public function getInstanceState(int $instanceId): ?array;

    /**
     * Dispatch a trigger event and start any matching workflows.
     *
     * @param string $eventName The event name to match against triggers
     * @param array $eventData Data associated with the event
     * @param int|null $triggeredBy Member ID who triggered the event
     * @return array<\App\Services\ServiceResult> Results for each started workflow
     */
    public function dispatchTrigger(
        string $eventName,
        array $eventData = [],
        ?int $triggeredBy = null,
    ): array;

    /**
     * Fire actions connected to an approval node's on_each_approval port.
     *
     * Called after a non-final approval is recorded (serial pick-next or parallel).
     * Executes intermediate actions without finalizing the approval node.
     * The approval node remains in WAITING state with its execution log unchanged.
     *
     * @param int $instanceId The workflow instance ID
     * @param string $nodeId The approval node ID
     * @param array $approvalData Approval progress data (approverId, decision, comment, nextApproverId)
     * @param string $outputPort Approval node output port to follow
     * @return \App\Services\ServiceResult
     */
    public function fireIntermediateApprovalActions(
        int $instanceId,
        string $nodeId,
        array $approvalData,
        string $outputPort = 'on_each_approval',
    ): ServiceResult;

    /**
     * Complete a pending human task and resume the workflow.
     *
     * @param int $taskId Workflow task ID
     * @param array $formData Submitted form data
     * @param int $completedBy Member ID completing the task
     * @return \App\Services\ServiceResult
     */
    public function completeHumanTask(int $taskId, array $formData, int $completedBy): ServiceResult;

    /**
     * Cancel a pending human task.
     *
     * @param int $taskId Workflow task ID
     * @param string|null $reason Optional cancellation reason
     * @return \App\Services\ServiceResult
     */
    public function cancelHumanTask(int $taskId, ?string $reason = null): ServiceResult;
}
