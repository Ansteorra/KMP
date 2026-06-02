<?php
declare(strict_types=1);

namespace App\Queue\Task;

use App\Model\Entity\WorkflowApproval;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Table;
use Queue\Queue\ServicesTrait;
use Queue\Queue\Task;
use Throwable;

/**
 * Checks for expired workflow approvals and resumes their workflows.
 *
 * Finds all PENDING approvals past their deadline, marks them EXPIRED,
 * and resumes the workflow on the 'expired' output port.
 *
 * Schedule via cron: bin/cake queue add WorkflowApprovalDeadline
 */
class WorkflowApprovalDeadlineTask extends Task
{
    use ServicesTrait;

    /**
     * Timeout for run, after which the Task is reassigned to a new worker.
     */
    public ?int $timeout = 300;

    /**
     * Prevent parallel execution of this task.
     */
    public bool $unique = true;

    /**
     * Scan for expired approvals, process escalation config, and resume workflows.
     *
     * @param array<string, mixed> $data Unused for scheduled runs
     * @param int $jobId The id of the QueuedJob entity
     * @return void
     */
    public function run(array $data, int $jobId): void
    {
        $approvalsTable = $this->getTableLocator()->get('WorkflowApprovals');

        $expiredApprovals = $approvalsTable->find()
            ->where([
                'status' => WorkflowApproval::STATUS_PENDING,
                'deadline IS NOT' => null,
                'deadline <' => DateTime::now(),
            ])
            ->all();

        $count = $expiredApprovals->count();
        if ($count === 0) {
            Log::info('WorkflowApprovalDeadlineTask: No expired approvals found');

            return;
        }

        Log::info("WorkflowApprovalDeadlineTask: Found {$count} expired approval(s)");

        $engine = $this->getService(WorkflowEngineInterface::class);
        $processed = 0;
        $errors = 0;

        foreach ($expiredApprovals as $approval) {
            try {
                $escalationConfig = $approval->escalation_config;

                // Process escalation if configured
                if (!empty($escalationConfig)) {
                    $escalationResult = $this->processEscalation(
                        $approval,
                        $escalationConfig,
                        $approvalsTable,
                        $engine,
                    );
                    if ($escalationResult) {
                        $processed++;

                        continue;
                    }
                    // Escalation returned false — fall through to default expiry
                }

                // Default: mark as expired and resume on 'expired' port
                $approval->status = WorkflowApproval::STATUS_EXPIRED;
                if (!$approvalsTable->save($approval)) {
                    Log::error(
                        "WorkflowApprovalDeadlineTask: Failed to save expired status for approval {$approval->id}",
                    );
                    $errors++;

                    continue;
                }

                $this->dispatchExpiredApprovalEvent($approval);

                $result = $engine->resumeWorkflow(
                    $approval->workflow_instance_id,
                    $approval->node_id,
                    'expired',
                    ['expiredApprovalId' => $approval->id],
                );

                if ($result->isSuccess()) {
                    $processed++;
                    Log::info(
                        "WorkflowApprovalDeadlineTask: Expired approval {$approval->id}, "
                        . "resumed instance {$approval->workflow_instance_id}",
                    );
                } else {
                    $errors++;
                    Log::error(
                        "WorkflowApprovalDeadlineTask: Failed to resume instance {$approval->workflow_instance_id}: "
                        . $result->getError(),
                    );
                }
            } catch (Throwable $e) {
                $errors++;
                Log::error("WorkflowApprovalDeadlineTask: Exception for approval {$approval->id}: " . $e->getMessage());
            }
        }

        Log::info("WorkflowApprovalDeadlineTask: Processed {$processed}, errors {$errors}");
    }

    /**
     * Process escalation actions defined in the approval's escalation_config JSON.
     *
     * Supported actions:
     *   - "auto_approve": Marks approval as approved and resumes on 'approved' port
     *   - "auto_reject": Marks approval as rejected and resumes on 'rejected' port
     *   - "reassign": Updates approver_config, extends deadline, keeps pending
     *   - "notify": Logs notification targets (integration point for mailer)
     *
     * @return bool True if escalation was fully handled, false to fall through to default expiry
     */
    private function processEscalation(
        WorkflowApproval $approval,
        array $escalationConfig,
        Table $approvalsTable,
        WorkflowEngineInterface $engine,
    ): bool {
        $action = $escalationConfig['action'] ?? null;
        $approvalId = $approval->id;

        switch ($action) {
            case 'auto_approve':
                $approval->status = WorkflowApproval::STATUS_APPROVED;
                if (!$approvalsTable->save($approval)) {
                    Log::error("WorkflowApprovalDeadlineTask: Failed to auto-approve approval {$approvalId}");

                    return false;
                }
                $result = $engine->resumeWorkflow(
                    $approval->workflow_instance_id,
                    $approval->node_id,
                    'approved',
                    ['escalatedApprovalId' => $approvalId, 'escalationAction' => 'auto_approve'],
                );
                Log::info("WorkflowApprovalDeadlineTask: Auto-approved approval {$approvalId} (escalation)");

                return $result->isSuccess();

            case 'auto_reject':
                $approval->status = WorkflowApproval::STATUS_REJECTED;
                if (!$approvalsTable->save($approval)) {
                    Log::error("WorkflowApprovalDeadlineTask: Failed to auto-reject approval {$approvalId}");

                    return false;
                }
                $result = $engine->resumeWorkflow(
                    $approval->workflow_instance_id,
                    $approval->node_id,
                    'rejected',
                    ['escalatedApprovalId' => $approvalId, 'escalationAction' => 'auto_reject'],
                );
                Log::info("WorkflowApprovalDeadlineTask: Auto-rejected approval {$approvalId} (escalation)");

                return $result->isSuccess();

            case 'reassign':
                $newConfig = $escalationConfig['reassign_to'] ?? null;
                $extendDeadline = $escalationConfig['extend_deadline'] ?? '7d';
                if (!$newConfig) {
                    Log::warning(
                        "WorkflowApprovalDeadlineTask: Reassign escalation missing 'reassign_to' config "
                        . "for approval {$approvalId}",
                    );

                    return false;
                }
                $approval->approver_type = $newConfig['type'] ?? $approval->approver_type;
                $approval->approver_config = $newConfig;
                $approval->deadline = $this->parseExtendedDeadline($extendDeadline);
                // Clear escalation to prevent re-triggering
                $approval->escalation_config = null;
                if ($approvalsTable->save($approval)) {
                    Log::info("WorkflowApprovalDeadlineTask: Reassigned approval {$approvalId} with extended deadline");

                    return true;
                }
                Log::error("WorkflowApprovalDeadlineTask: Failed to reassign approval {$approvalId}");

                return false;

            case 'notify':
                $notifyTargets = $escalationConfig['notify_members'] ?? [];
                $notifyPermission = $escalationConfig['notify_permission'] ?? null;
                Log::info(
                    "WorkflowApprovalDeadlineTask: Escalation notification for approval {$approvalId} — "
                    . 'members: ' . json_encode($notifyTargets)
                    . ", permission: {$notifyPermission}",
                );
                // Notification dispatched via log; fall through to default expiry
                return false;

            default:
                Log::warning(
                    "WorkflowApprovalDeadlineTask: Unknown escalation action '{$action}' for approval {$approvalId}",
                );

                return false;
        }
    }

    /**
     * Notify domain integrations that an approval deadline expired.
     */
    private function dispatchExpiredApprovalEvent(WorkflowApproval $approval): void
    {
        try {
            EventManager::instance()->dispatch(new Event('Workflow.Approval.Expired', $this, [
                'approval' => $approval,
            ]));
        } catch (Throwable $e) {
            Log::error(
                "WorkflowApprovalDeadlineTask: Expired approval event failed for approval {$approval->id}: "
                . $e->getMessage(),
            );
        }
    }

    /**
     * Parse a deadline extension string into a future DateTime.
     */
    private function parseExtendedDeadline(string $deadline): DateTime
    {
        $now = DateTime::now();

        if (preg_match('/^(\d+)d$/', $deadline, $matches)) {
            return $now->modify("+{$matches[1]} days");
        }
        if (preg_match('/^(\d+)h$/', $deadline, $matches)) {
            return $now->modify("+{$matches[1]} hours");
        }

        return $now->modify('+7 days');
    }
}
