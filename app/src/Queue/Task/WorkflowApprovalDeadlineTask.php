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
use RuntimeException;
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
     * Retry transient workflow resume failures.
     */
    public ?int $retries = 3;

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

                $expiredApproval = $this->transitionAndResume(
                    $approval,
                    WorkflowApproval::STATUS_EXPIRED,
                    'expired',
                    ['expiredApprovalId' => $approval->id],
                    $approvalsTable,
                    $engine,
                );
                if ($expiredApproval === null) {
                    continue;
                }

                $this->dispatchExpiredApprovalEvent($expiredApproval);
                $processed++;
                Log::info(
                    "WorkflowApprovalDeadlineTask: Expired approval {$approval->id}, "
                    . "resumed instance {$approval->workflow_instance_id}",
                );
            } catch (Throwable $e) {
                $errors++;
                Log::error("WorkflowApprovalDeadlineTask: Exception for approval {$approval->id}: " . $e->getMessage());
            }
        }

        Log::info("WorkflowApprovalDeadlineTask: Processed {$processed}, errors {$errors}");
        if ($errors > 0) {
            throw new RuntimeException(
                "WorkflowApprovalDeadlineTask failed to process {$errors} approval(s); retrying pending work.",
            );
        }
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
                $this->transitionAndResume(
                    $approval,
                    WorkflowApproval::STATUS_APPROVED,
                    'approved',
                    ['escalatedApprovalId' => $approvalId, 'escalationAction' => 'auto_approve'],
                    $approvalsTable,
                    $engine,
                );
                Log::info("WorkflowApprovalDeadlineTask: Auto-approved approval {$approvalId} (escalation)");

                return true;

            case 'auto_reject':
                $this->transitionAndResume(
                    $approval,
                    WorkflowApproval::STATUS_REJECTED,
                    'rejected',
                    ['escalatedApprovalId' => $approvalId, 'escalationAction' => 'auto_reject'],
                    $approvalsTable,
                    $engine,
                );
                Log::info("WorkflowApprovalDeadlineTask: Auto-rejected approval {$approvalId} (escalation)");

                return true;

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
                $this->reassignPendingApproval(
                    $approval,
                    $newConfig,
                    $this->parseExtendedDeadline($extendDeadline),
                    $approvalsTable,
                );
                Log::info("WorkflowApprovalDeadlineTask: Reassigned approval {$approvalId} with extended deadline");

                return true;

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
     * Atomically transition an approval and resume its workflow.
     *
     * @return \App\Model\Entity\WorkflowApproval|null Updated approval, or null when already handled
     */
    private function transitionAndResume(
        WorkflowApproval $approval,
        string $status,
        string $outputPort,
        array $resumeData,
        Table $approvalsTable,
        WorkflowEngineInterface $engine,
    ): ?WorkflowApproval {
        $connection = $approvalsTable->getConnection();
        $connection->enableSavePoints();

        return $connection->transactional(
            function () use (
                $approval,
                $status,
                $outputPort,
                $resumeData,
                $approvalsTable,
                $engine,
            ): ?WorkflowApproval {
                // Cancellation uses the same instance-then-approval lock order.
                $instancesTable = $this->getTableLocator()->get('WorkflowInstances');
                $lockedInstance = $instancesTable->find()
                    ->where(['id' => $approval->workflow_instance_id])
                    ->epilog('FOR UPDATE')
                    ->first();
                if ($lockedInstance === null || $lockedInstance->isTerminal()) {
                    return null;
                }

                $lockedApproval = $approvalsTable->find()
                    ->where(['id' => $approval->id])
                    ->epilog('FOR UPDATE')
                    ->first();
                if (
                    !$lockedApproval
                    || $lockedApproval->status !== WorkflowApproval::STATUS_PENDING
                ) {
                    return null;
                }

                $lockedApproval->status = $status;
                $approvalsTable->saveOrFail($lockedApproval);

                $result = $engine->resumeWorkflow(
                    (int)$lockedApproval->workflow_instance_id,
                    (string)$lockedApproval->node_id,
                    $outputPort,
                    $resumeData,
                );
                if (!$result->isSuccess()) {
                    throw new RuntimeException(
                        "Failed to resume instance {$lockedApproval->workflow_instance_id}: "
                        . ($result->getError() ?? 'Unknown error'),
                    );
                }

                return $lockedApproval;
            },
        );
    }

    /**
     * Reassign an approval while holding the same row lock used by responses.
     */
    private function reassignPendingApproval(
        WorkflowApproval $approval,
        array $newConfig,
        DateTime $deadline,
        Table $approvalsTable,
    ): void {
        $connection = $approvalsTable->getConnection();
        $connection->enableSavePoints();
        $connection->transactional(
            function () use ($approval, $newConfig, $deadline, $approvalsTable): void {
                $lockedApproval = $approvalsTable->find()
                    ->where(['id' => $approval->id])
                    ->epilog('FOR UPDATE')
                    ->first();
                if (
                    !$lockedApproval
                    || $lockedApproval->status !== WorkflowApproval::STATUS_PENDING
                ) {
                    return;
                }

                $lockedApproval->approver_type = $newConfig['type'] ?? $lockedApproval->approver_type;
                $lockedApproval->approver_config = $newConfig;
                $lockedApproval->deadline = $deadline;
                $lockedApproval->escalation_config = null;
                $approvalsTable->saveOrFail($lockedApproval);
            },
        );
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
