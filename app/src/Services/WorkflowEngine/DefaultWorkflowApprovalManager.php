<?php
declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\KMP\StaticHelpers;
use App\KMP\WorkflowApprovalDecisionOptions;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Model\Table\WorkflowApprovalsTable;
use App\Services\ServiceResult;
use App\Services\WorkflowRegistry\WorkflowApproverResolverRegistry;
use Cake\Core\ContainerInterface;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Exception;
use RuntimeException;

/**
 * Default implementation of WorkflowApprovalManagerInterface.
 *
 * Manages approval gate lifecycle: creation, transactional response recording,
 * candidate discovery for picker UIs, and resolution detection.
 *
 * Current-user pending eligibility is owned by WorkflowApprovalsTable so queues,
 * badges, and response recording use one canonical decision path.
 */
class DefaultWorkflowApprovalManager implements WorkflowApprovalManagerInterface
{
    /**
     * Maximum retry attempts for optimistic lock conflicts.
     */
    private const MAX_OPTIMISTIC_RETRIES = 3;

    /**
     * Base delay in microseconds between optimistic lock retries (50ms).
     */
    private const RETRY_DELAY_US = 50000;

    /**
     * @param \Cake\Core\ContainerInterface|null $container Optional DI container for dynamic resolvers
     */
    public function __construct(private ?ContainerInterface $container = null)
    {
    }

    /**
     * Record a member's approval decision with optimistic-locking retries.
     *
     * Uses a three-layer concurrency defense:
     *  1. FOR UPDATE row lock — serialises concurrent transactions
     *  2. Atomic SQL increment — prevents lost count updates
     *  3. Optimistic version check — detects any out-of-band modification
     */
    public function recordResponse(
        int $approvalId,
        int $memberId,
        string $decision,
        ?string $comment = null,
        ?int $nextApproverId = null,
    ): ServiceResult {
        for ($attempt = 1; $attempt <= self::MAX_OPTIMISTIC_RETRIES; $attempt++) {
            try {
                $result = $this->attemptRecordResponse($approvalId, $memberId, $decision, $comment, $nextApproverId);

                return $result;
            } catch (OptimisticLockException $e) {
                Log::warning(
                    "Optimistic lock conflict on approval {$approvalId}, attempt {$attempt}/"
                    . self::MAX_OPTIMISTIC_RETRIES . ": {$e->getMessage()}",
                );

                if ($attempt < self::MAX_OPTIMISTIC_RETRIES) {
                    usleep(self::RETRY_DELAY_US * $attempt);
                }
            } catch (Exception $e) {
                Log::error("Error recording approval response: {$e->getMessage()}");

                return new ServiceResult(false, 'An unexpected error occurred.');
            }
        }

        Log::error(
            "Optimistic lock retries exhausted for approval {$approvalId} after "
            . self::MAX_OPTIMISTIC_RETRIES . ' attempts',
        );

        return new ServiceResult(false, 'Approval was modified concurrently. Please retry.');
    }

    /**
     * Single transactional attempt to record an approval response.
     *
     * @throws \App\Services\WorkflowEngine\OptimisticLockException When the version check fails.
     */
    private function attemptRecordResponse(
        int $approvalId,
        int $memberId,
        string $decision,
        ?string $comment,
        ?int $nextApproverId,
    ): ServiceResult {
        $connection = ConnectionManager::get('default');

        /** @var \App\Services\ServiceResult $result */
        $result = $connection->transactional(function () use (
            $approvalId,
            $memberId,
            $decision,
            $comment,
            $nextApproverId,
        ) {
            $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
            $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');

            // Layer 1: Lock the approval row to prevent concurrent modifications
            /** @var \App\Model\Entity\WorkflowApproval|null $approval */
            $approval = $approvalsTable->find()
                ->where(['WorkflowApprovals.id' => $approvalId])
                ->epilog('FOR UPDATE')
                ->first();

            if (!$approval) {
                return new ServiceResult(false, 'Approval not found.');
            }

            if ($approval->status !== WorkflowApproval::STATUS_PENDING) {
                return new ServiceResult(false, 'Approval is no longer pending.');
            }

            // Capture version at read time for optimistic lock check
            $currentVersion = (int)($approval->version ?? 1);

            $approverConfig = $approval->approver_config ?? [];

            // Check for duplicate response
            $existing = $responsesTable->find()
                ->where([
                    'workflow_approval_id' => $approvalId,
                    'member_id' => $memberId,
                ])
                ->first();

            if ($existing) {
                return new ServiceResult(false, 'Member has already responded to this approval.');
            }

            $isEligible = WorkflowApprovalsTable::isPendingApprovalForMember($approvalId, $memberId);
            if ($approval->approver_type === WorkflowApproval::APPROVER_TYPE_DYNAMIC) {
                $serviceRef = $approverConfig['service'] ?? null;
                $method = $approverConfig['method'] ?? null;
                if ($serviceRef !== null || $method !== null) {
                    $dynamicApproverIds = $this->resolveDynamicApproverIds($approval);
                    $currentApproverId = (int)(
                        $approval->current_approver_id
                        ?? $approverConfig['current_approver_id']
                        ?? 0
                    );
                    $memberResolvedEligible = in_array($memberId, $dynamicApproverIds, true);
                    $resolverEligible = $currentApproverId > 0
                        ? $memberId === $currentApproverId && $memberResolvedEligible
                        : $memberResolvedEligible;

                    if (!$resolverEligible) {
                        return new ServiceResult(false, 'You are not eligible to respond to this approval.');
                    }
                    if (!$isEligible && !$this->hasPriorDynamicWorkflowResponse($approval, $memberId)) {
                        $isEligible = true;
                    }
                }
            }

            // Check eligibility before accepting the response
            if (!$isEligible) {
                return new ServiceResult(false, 'You are not eligible to respond to this approval.');
            }

            if (!in_array($decision, WorkflowApprovalDecisionOptions::allowedValues($approverConfig), true)) {
                return new ServiceResult(false, 'Invalid approval decision.');
            }

            // Create response
            $response = $responsesTable->newEntity([
                'workflow_approval_id' => $approvalId,
                'member_id' => $memberId,
                'decision' => $decision,
                'comment' => $comment,
                'responded_at' => DateTime::now(),
            ]);

            if (!$responsesTable->save($response)) {
                Log::error("Failed to save approval response for approval {$approvalId}");

                return new ServiceResult(false, 'Failed to save response.');
            }

            // Layer 2: Atomic increment of counts to prevent lost updates
            // Layer 3: Optimistic version check — ensures no concurrent modification
            if ($decision === WorkflowApprovalResponse::DECISION_APPROVE) {
                $affectedRows = $approvalsTable->updateAll(
                    ['approved_count = approved_count + 1', 'version' => $currentVersion + 1],
                    ['id' => $approval->id, 'version' => $currentVersion],
                );
            } elseif ($decision === WorkflowApprovalResponse::DECISION_REJECT) {
                $affectedRows = $approvalsTable->updateAll(
                    ['rejected_count = rejected_count + 1', 'version' => $currentVersion + 1],
                    ['id' => $approval->id, 'version' => $currentVersion],
                );
            } else {
                // Non-counted decision (e.g. abstain) — still bump version
                $affectedRows = $approvalsTable->updateAll(
                    ['version' => $currentVersion + 1],
                    ['id' => $approval->id, 'version' => $currentVersion],
                );
            }

            if ($affectedRows === 0) {
                throw new OptimisticLockException(
                    "Approval {$approvalId} was modified concurrently (expected version {$currentVersion}).",
                );
            }

            // Reload approval to get accurate counts after atomic increment
            $approval = $approvalsTable->get($approval->id);

            // Check for serial pick-next mode
            $isSerialPickNext = !empty($approverConfig['serial_pick_next']);
            $isFeedbackResponse = !empty($approverConfig['feedback_response']);

            // Check resolution: threshold met or any rejection
            if ($isFeedbackResponse) {
                $approval->status = WorkflowApproval::STATUS_APPROVED;
            } elseif ($approval->approved_count >= $approval->required_count) {
                $approval->status = WorkflowApproval::STATUS_APPROVED;
            } elseif ($approval->rejected_count > 0) {
                $approval->status = WorkflowApproval::STATUS_REJECTED;
            } elseif ($isSerialPickNext && $decision === WorkflowApprovalResponse::DECISION_APPROVE) {
                // Serial pick-next: more approvals needed, update approver_config
                if ($nextApproverId) {
                    $approverConfig['current_approver_id'] = $nextApproverId;
                } else {
                    // No next approver specified — clear current to allow any eligible
                    unset($approverConfig['current_approver_id']);
                }
                $approval->current_approver_id = $nextApproverId ?: null;

                // Append to approval chain for audit trail
                $chain = $approverConfig['approval_chain'] ?? [];
                $chain[] = [
                    'approver_id' => $memberId,
                    'responded_at' => DateTime::now()->toIso8601String(),
                    'next_picked' => $nextApproverId,
                ];
                $approverConfig['approval_chain'] = $chain;

                // Track already-used approvers in exclude list
                $excludeIds = $approverConfig['exclude_member_ids'] ?? [];
                $excludeIds[] = $memberId;
                $approverConfig['exclude_member_ids'] = array_unique(array_map('intval', $excludeIds));

                $approval->approver_config = $approverConfig;
                // Status stays PENDING — don't resolve yet

                // Version-gated save for serial pick-next config update
                $this->saveWithVersionCheck($approvalsTable, $approval);

                return new ServiceResult(true, null, [
                    'approvalStatus' => 'pending',
                    'needsMore' => true,
                    'instanceId' => $approval->workflow_instance_id,
                    'nodeId' => $approval->node_id,
                    'nextApproverId' => $nextApproverId,
                ]);
            }

            if ($approval->status !== WorkflowApproval::STATUS_PENDING) {
                // Version-gated save for status resolution
                $this->saveWithVersionCheck($approvalsTable, $approval);
            }

            $returnData = [
                'approvalStatus' => $approval->status,
                'instanceId' => $approval->workflow_instance_id,
                'nodeId' => $approval->node_id,
            ];

            // Flag parallel non-final approvals so the controller can fire intermediate actions
            if ($approval->status === WorkflowApproval::STATUS_PENDING) {
                $returnData['needsMore'] = true;
            }

            return new ServiceResult(true, null, $returnData);
        });

        if ($result === false) {
            return new ServiceResult(false, 'Transaction failed.');
        }

        return $result;
    }

    /**
     * Save an approval entity with optimistic version bump.
     *
     * Increments the version on save and verifies the row was updated.
     * Must be called within a transaction that already holds the FOR UPDATE lock.
     *
     * @throws \App\Services\WorkflowEngine\OptimisticLockException When the version has been changed by another process.
     */
    private function saveWithVersionCheck($approvalsTable, WorkflowApproval $approval): void
    {
        $approval->version = ($approval->version ?? 1) + 1;

        if (!$approvalsTable->save($approval)) {
            Log::error("Failed to save approval {$approval->id} with version check");
            throw new OptimisticLockException(
                "Failed to save approval {$approval->id} — possible concurrent modification.",
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function createApproval(int $instanceId, string $nodeId, int $executionLogId, array $config): ServiceResult
    {
        try {
            $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');

            $approverType = $config['approver_type']
                ?? $config['approverType']
                ?? WorkflowApproval::APPROVER_TYPE_PERMISSION;
            $approverConfig = $config['approver_config'] ?? $config['approverConfig'] ?? [];
            $requiredCount = (int)($config['required_count'] ?? $config['requiredCount'] ?? 1);
            $allowParallel = (bool)($config['allow_parallel']
                ?? $config['allowParallel']
                ?? $config['parallel']
                ?? true);
            $currentApproverId = $config['current_approver_id'] ?? $approverConfig['current_approver_id'] ?? null;
            $deadline = null;

            if (!empty($config['deadline'])) {
                $deadline = $this->parseDeadline($config['deadline']);
            }

            $approval = $approvalsTable->newEntity([
                'workflow_instance_id' => $instanceId,
                'node_id' => $nodeId,
                'execution_log_id' => $executionLogId,
                'approver_type' => $approverType,
                'approver_config' => $approverConfig,
                'current_approver_id' => $currentApproverId,
                'request_title' => $approvalsTable->resolveRequestTitleForInstanceId($instanceId),
                'required_count' => $requiredCount,
                'approved_count' => 0,
                'rejected_count' => 0,
                'status' => WorkflowApproval::STATUS_PENDING,
                'allow_parallel' => $allowParallel,
                'deadline' => $deadline,
                'escalation_config' => $config['escalation_config'] ?? $config['escalationConfig'] ?? null,
                'version' => 1,
                'approval_token' => StaticHelpers::generateToken(32),
            ]);
            if ($approval->approver_type === WorkflowApproval::APPROVER_TYPE_DYNAMIC) {
                $eligibleMemberIds = $this->resolveDynamicApproverIds($approval);
                $storedConfig = $approval->approver_config ?? [];
                $storedConfig['eligible_member_ids'] = $eligibleMemberIds;
                $approval->approver_config = $storedConfig;
                if ($approval->current_approver_id === null && count($eligibleMemberIds) === 1) {
                    $approval->current_approver_id = $eligibleMemberIds[0];
                }
            }

            if (!$approvalsTable->save($approval)) {
                Log::error("Failed to create approval for instance {$instanceId}, node {$nodeId}");

                return new ServiceResult(false, 'Failed to create approval.');
            }

            return new ServiceResult(true, null, ['approvalId' => $approval->id, 'approval' => $approval]);
        } catch (Exception $e) {
            Log::error("Error creating approval: {$e->getMessage()}");

            return new ServiceResult(false, 'An unexpected error occurred.');
        }
    }

    /**
     * List the approvals currently pending the given member's action.
     *
     * Uses the same canonical eligibility logic as badges, mobile queues, and
     * controller response guards so listed approvals remain actionable.
     */
    public function getPendingApprovalsForMember(int $memberId): array
    {
        try {
            $approvals = WorkflowApprovalsTable::getPendingApprovalsForMember($memberId, [
                'WorkflowInstances' => ['WorkflowDefinitions'],
            ]);
            $approvalsById = [];
            foreach ($approvals as $approval) {
                $approvalsById[(int)$approval->id] = $approval;
            }

            $fallbackApprovals = $this->getResolverBackedPendingDynamicApprovals($memberId);
            foreach ($fallbackApprovals as $approval) {
                $approvalsById[(int)$approval->id] = $approval;
            }

            return array_values($approvalsById);
        } catch (Exception $e) {
            Log::error("Error fetching pending approvals for member {$memberId}: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Load callback-only dynamic approvals whose eligibility cannot be represented by lookup fields.
     *
     * @param int $memberId Member ID.
     * @return array<\App\Model\Entity\WorkflowApproval>
     */
    private function getResolverBackedPendingDynamicApprovals(int $memberId): array
    {
        $approvals = TableRegistry::getTableLocator()->get('WorkflowApprovals')->find()
            ->contain(['WorkflowInstances' => ['WorkflowDefinitions']])
            ->where([
                'WorkflowApprovals.status' => WorkflowApproval::STATUS_PENDING,
                'WorkflowApprovals.approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            ])
            ->orderBy(['WorkflowApprovals.modified' => 'DESC', 'WorkflowApprovals.id' => 'DESC'])
            ->all()
            ->toArray();

        $eligibleApprovals = [];
        foreach ($approvals as $approval) {
            if ($this->hasPriorDynamicWorkflowResponse($approval, $memberId)) {
                continue;
            }

            $config = $approval->approver_config ?? [];
            if (($config['service'] ?? null) === null && ($config['method'] ?? null) === null) {
                continue;
            }

            try {
                $dynamicApproverIds = $this->resolveDynamicApproverIds($approval);
            } catch (RuntimeException $e) {
                Log::error("Dynamic approver resolution failed: {$e->getMessage()}");

                continue;
            }

            $currentApproverId = (int)($approval->current_approver_id ?? $config['current_approver_id'] ?? 0);
            $memberResolvedEligible = in_array($memberId, $dynamicApproverIds, true);
            $isEligible = $currentApproverId > 0
                ? $memberId === $currentApproverId && $memberResolvedEligible
                : $memberResolvedEligible;
            if ($isEligible) {
                $eligibleApprovals[] = $approval;
            }
        }

        return $eligibleApprovals;
    }

    /**
     * @inheritDoc
     */
    public function getApprovalsForInstance(int $instanceId): array
    {
        try {
            $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');

            return $approvalsTable->find()
                ->contain(['WorkflowApprovalResponses.Members'])
                ->where(['WorkflowApprovals.workflow_instance_id' => $instanceId])
                ->all()
                ->toArray();
        } catch (Exception $e) {
            Log::error("Error fetching approvals for instance {$instanceId}: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function isResolved(int $approvalId): bool
    {
        try {
            $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');

            /** @var \App\Model\Entity\WorkflowApproval|null $approval */
            $approval = $approvalsTable->find()
                ->where(['WorkflowApprovals.id' => $approvalId])
                ->first();

            if (!$approval) {
                return false;
            }

            return $approval->isResolved();
        } catch (Exception $e) {
            Log::error("Error checking approval resolution for {$approvalId}: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function cancelApprovalsForInstance(int $instanceId): ServiceResult
    {
        try {
            $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');

            $pendingApprovals = $approvalsTable->find()
                ->where([
                    'workflow_instance_id' => $instanceId,
                    'status' => WorkflowApproval::STATUS_PENDING,
                ])
                ->all();

            foreach ($pendingApprovals as $approval) {
                $approval->status = WorkflowApproval::STATUS_CANCELLED;
                if (!$approvalsTable->save($approval)) {
                    Log::error("Failed to cancel approval {$approval->id} for instance {$instanceId}");
                }
            }

            return new ServiceResult(true);
        } catch (Exception $e) {
            Log::error("Error cancelling approvals for instance {$instanceId}: {$e->getMessage()}");

            return new ServiceResult(false, 'Failed to cancel approvals.');
        }
    }

    /**
     * Reassign a pending approval to a different eligible member.
     *
     * @param int $approvalId Workflow approval ID
     * @param int $newApproverId Member ID of the new approver
     * @param int $adminMemberId Member ID of the admin performing the reassignment
     * @param string|null $reason Optional reason for reassignment
     * @return \App\Services\ServiceResult Contains approvalId, instanceId, nodeId, previousApproverId, newApproverId on success
     */
    public function reassignApproval(
        int $approvalId,
        int $newApproverId,
        int $adminMemberId,
        ?string $reason = null,
    ): ServiceResult {
        try {
            $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');

            /** @var \App\Model\Entity\WorkflowApproval|null $approval */
            $approval = $approvalsTable->find()
                ->where(['WorkflowApprovals.id' => $approvalId])
                ->first();

            if (!$approval) {
                return new ServiceResult(false, 'Approval not found.');
            }

            if ($approval->status !== WorkflowApproval::STATUS_PENDING) {
                return new ServiceResult(false, 'Only pending approvals can be reassigned.');
            }

            $previousApproverId = $approval->current_approver_id;

            // Update the current approver
            $approval->current_approver_id = $newApproverId;
            $config = $approval->approver_config ?? [];
            $config['current_approver_id'] = $newApproverId;
            $config['reassigned_by'] = $adminMemberId;
            $config['reassigned_at'] = DateTime::now()->toIso8601String();
            if ($reason) {
                $config['reassignment_reason'] = $reason;
            }
            $approval->approver_config = $config;

            if (!$approvalsTable->save($approval)) {
                return new ServiceResult(false, 'Failed to save reassignment.');
            }

            return new ServiceResult(true, null, [
                'approvalId' => $approval->id,
                'instanceId' => $approval->workflow_instance_id,
                'nodeId' => $approval->node_id,
                'previousApproverId' => $previousApproverId,
                'newApproverId' => $newApproverId,
            ]);
        } catch (Exception $e) {
            Log::error("Error reassigning approval {$approvalId}: {$e->getMessage()}");

            return new ServiceResult(false, 'An unexpected error occurred.');
        }
    }

    /**
     * @inheritDoc
     */
    public function getEligibleApprovers(int $approvalId): array
    {
        try {
            $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');

            /** @var \App\Model\Entity\WorkflowApproval|null $approval */
            $approval = $approvalsTable->find()
                ->where(['WorkflowApprovals.id' => $approvalId])
                ->first();

            if (!$approval) {
                return [];
            }

            return $this->findEligibleMembers($approval);
        } catch (Exception $e) {
            Log::error("Error fetching eligible approvers for {$approvalId}: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Get candidate member IDs for the "next approver" picker in serial pick-next mode.
     *
     * Returns the full pool of eligible members with the right permission,
     * minus those who have already responded and the requesting member.
     *
     * @param int $approvalId Workflow approval ID
     * @param int|null $currentMemberId Current user ID to exclude (they're approving now)
     * @return array<int> Member IDs eligible to be picked as next approver
     */
    public function getNextApproverCandidates(int $approvalId, ?int $currentMemberId = null): array
    {
        try {
            $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');

            /** @var \App\Model\Entity\WorkflowApproval|null $approval */
            $approval = $approvalsTable->find()
                ->contain(['WorkflowInstances'])
                ->where(['WorkflowApprovals.id' => $approvalId])
                ->first();

            if (!$approval) {
                return [];
            }

            // Build exclusion list: previous responders + current user + requester
            $excludeIds = [];

            // Get IDs of members who already responded to this approval
            $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
            $respondedIds = $responsesTable->find()
                ->select(['member_id'])
                ->where(['workflow_approval_id' => $approvalId])
                ->all()
                ->extract('member_id')
                ->toArray();
            $excludeIds = array_merge($excludeIds, array_map('intval', $respondedIds));

            // Exclude current user
            if ($currentMemberId) {
                $excludeIds[] = $currentMemberId;
            }

            // Exclude the requesting member (from workflow instance context)
            $instanceContext = $approval->workflow_instance->context ?? [];
            $triggerData = $instanceContext['triggerData'] ?? ($instanceContext['trigger'] ?? []);
            $requesterId = (int)($triggerData['memberId'] ?? 0);
            if ($requesterId > 0) {
                $excludeIds[] = $requesterId;
            }

            // Also include any exclude_member_ids from approver_config
            $config = $approval->approver_config ?? [];
            $configExcludes = $config['exclude_member_ids'] ?? [];
            $excludeIds = array_merge($excludeIds, array_map('intval', $configExcludes));

            $excludeIds = array_unique(array_filter($excludeIds));

            // Temporarily clear current_approver_id to get the full pool from the resolver
            $clonedApproval = clone $approval;
            $clonedConfig = $clonedApproval->approver_config ?? [];
            unset($clonedConfig['current_approver_id']);
            $clonedApproval->approver_config = $clonedConfig;

            // Get full candidate pool
            $candidates = match ($approval->approver_type) {
                WorkflowApproval::APPROVER_TYPE_DYNAMIC => $this->resolveDynamicApproverIds($clonedApproval),
                WorkflowApproval::APPROVER_TYPE_PERMISSION => array_map(
                    fn($m) => $m->id,
                    $this->findMembersByPermission($config['permission'] ?? ''),
                ),
                WorkflowApproval::APPROVER_TYPE_ROLE => array_map(
                    fn($m) => $m->id,
                    $this->findMembersByRole($config['role'] ?? ''),
                ),
                default => [],
            };
            $candidateIds = array_map('intval', $candidates);
            if ($approval->approver_type === WorkflowApproval::APPROVER_TYPE_DYNAMIC) {
                $excludeIds = array_merge(
                    $excludeIds,
                    $this->getWorkflowInstanceResponderIds((int)$approval->workflow_instance_id),
                );
            }

            // Remove excluded IDs
            return array_values(array_unique(array_diff($candidateIds, $excludeIds)));
        } catch (Exception $e) {
            Log::error("Error fetching next approver candidates for {$approvalId}: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Find members who are potential candidates for an approval picker.
     *
     * This is not the final current-user response eligibility check. Response
     * recording delegates to WorkflowApprovalsTable::isPendingApprovalForMember().
     *
     * @return array<\App\Model\Entity\Member>
     */
    private function findEligibleMembers(WorkflowApproval $approval): array
    {
        $config = $approval->approver_config ?? [];

        switch ($approval->approver_type) {
            case WorkflowApproval::APPROVER_TYPE_PERMISSION:
                $permissionName = $config['permission'] ?? null;
                if (!$permissionName) {
                    return [];
                }

                return $this->findMembersByPermission($permissionName);

            case WorkflowApproval::APPROVER_TYPE_ROLE:
                $roleName = $config['role'] ?? null;
                if (!$roleName) {
                    return [];
                }

                return $this->findMembersByRole($roleName);

            case WorkflowApproval::APPROVER_TYPE_MEMBER:
                $targetMemberId = (int)($config['member_id'] ?? 0);
                if ($targetMemberId <= 0) {
                    return [];
                }
                $membersTable = TableRegistry::getTableLocator()->get('Members');
                $member = $membersTable->find()
                    ->where(['Members.id' => $targetMemberId])
                    ->first();

                return $member ? [$member] : [];

            case WorkflowApproval::APPROVER_TYPE_DYNAMIC:
                return array_values(array_filter(
                    $this->findDynamicApprovers($approval),
                    fn($member): bool => !$this->hasPriorDynamicWorkflowResponse($approval, (int)$member->id),
                ));

            case WorkflowApproval::APPROVER_TYPE_POLICY:
                // Fall back to permission-based list, then filter by policy
                $permissionName = $config['permission'] ?? null;
                if ($permissionName) {
                    return $this->findMembersByPermission($permissionName);
                }

                return [];

            default:
                return [];
        }
    }

    /**
     * Find all members who have an active (unexpired) role granting the specified permission.
     *
     * @return array<\App\Model\Entity\Member>
     */
    private function findMembersByPermission(string $permissionName): array
    {
        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $now = DateTime::now();

        return $membersTable->find()
            ->innerJoinWith('MemberRoles.Roles.Permissions')
            ->where([
                'Permissions.name' => $permissionName,
                'OR' => [
                    'MemberRoles.start_on IS' => null,
                    'MemberRoles.start_on <=' => $now,
                ],
            ])
            ->where([
                'OR' => [
                    'MemberRoles.expires_on IS' => null,
                    'MemberRoles.expires_on >=' => $now,
                ],
            ])
            ->group(['Members.id'])
            ->all()
            ->toArray();
    }

    /**
     * Find all members who have the specified active (unexpired) role.
     *
     * @return array<\App\Model\Entity\Member>
     */
    private function findMembersByRole(string $roleName): array
    {
        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $now = DateTime::now();

        return $membersTable->find()
            ->innerJoinWith('MemberRoles.Roles')
            ->where([
                'Roles.name' => $roleName,
                'OR' => [
                    'MemberRoles.start_on IS' => null,
                    'MemberRoles.start_on <=' => $now,
                ],
            ])
            ->where([
                'OR' => [
                    'MemberRoles.expires_on IS' => null,
                    'MemberRoles.expires_on >=' => $now,
                ],
            ])
            ->group(['Members.id'])
            ->all()
            ->toArray();
    }

    /**
     * Resolve eligible member IDs via configured callback service.
     *
     * Expects approver_config: {"service": "App\\Services\\MyService", "method": "getEligibleApprovers"}
     * The callback receives the WorkflowApproval and must return int[] of member IDs.
     *
     * @return array<int>
     * @throws \RuntimeException If config is missing or callback is invalid
     */
    private function resolveDynamicApproverIds(WorkflowApproval $approval): array
    {
        $config = $approval->approver_config ?? [];
        $serviceRef = $config['service'] ?? null;
        $method = $config['method'] ?? null;
        if (
            $serviceRef === null
            && $method === null
            && isset($config['eligible_member_ids'])
            && is_array($config['eligible_member_ids'])
        ) {
            return array_values(array_unique(array_map('intval', $config['eligible_member_ids'])));
        }

        // Try registry lookup first
        $registryEntry = $serviceRef ? WorkflowApproverResolverRegistry::getResolver($serviceRef) : null;
        if ($registryEntry) {
            $serviceClass = $registryEntry['serviceClass'];
            $method = $method ?? $registryEntry['serviceMethod'];
        } else {
            // Backward compat: treat as direct class name
            $serviceClass = $serviceRef;
        }

        if (!$serviceClass || !$method) {
            throw new RuntimeException(
                "Dynamic approver type requires 'service' and 'method' in approver_config. "
                . "Approval ID: {$approval->id}",
            );
        }

        if (!class_exists($serviceClass)) {
            throw new RuntimeException("Dynamic approver service class '{$serviceClass}' not found.");
        }

        $service = $this->container !== null && $this->container->has($serviceClass)
            ? $this->container->get($serviceClass)
            : new $serviceClass();
        if (!method_exists($service, $method)) {
            throw new RuntimeException("Method '{$method}' not found on '{$serviceClass}'.");
        }

        $result = $service->$method($approval);
        if (!is_array($result)) {
            Log::warning("Dynamic approver {$serviceClass}::{$method} did not return an array");

            return [];
        }

        return array_values(array_unique(array_map('intval', $result)));
    }

    /**
     * Dynamic approvers may qualify through multiple routes, but each member can only respond once per workflow.
     */
    private function hasPriorDynamicWorkflowResponse(WorkflowApproval $approval, int $memberId): bool
    {
        if ($approval->approver_type !== WorkflowApproval::APPROVER_TYPE_DYNAMIC) {
            return false;
        }

        $workflowInstanceId = (int)$approval->workflow_instance_id;
        if ($workflowInstanceId <= 0 || $memberId <= 0) {
            return false;
        }

        return in_array($memberId, $this->getWorkflowInstanceResponderIds($workflowInstanceId), true);
    }

    /**
     * @return array<int>
     */
    private function getWorkflowInstanceResponderIds(int $workflowInstanceId): array
    {
        if ($workflowInstanceId <= 0) {
            return [];
        }

        $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');

        return $responsesTable->find()
            ->select(['member_id'])
            ->innerJoinWith('WorkflowApprovals', function ($q) use ($workflowInstanceId) {
                return $q->where(['WorkflowApprovals.workflow_instance_id' => $workflowInstanceId]);
            })
            ->all()
            ->extract('member_id')
            ->map(static fn($memberId): int => (int)$memberId)
            ->toList();
    }

    /**
     * Find Member entities eligible via the dynamic callback approver.
     *
     * @return array<\App\Model\Entity\Member>
     */
    private function findDynamicApprovers(WorkflowApproval $approval): array
    {
        try {
            $memberIds = $this->resolveDynamicApproverIds($approval);
            if (empty($memberIds)) {
                return [];
            }

            $membersTable = TableRegistry::getTableLocator()->get('Members');

            return $membersTable->find()
                ->where(['Members.id IN' => $memberIds])
                ->all()
                ->toArray();
        } catch (RuntimeException $e) {
            Log::error("Dynamic approver resolution failed: {$e->getMessage()}");

            return [];
        }
    }

    /**
     * Parse a deadline string (e.g., "7d", "24h") into a DateTime.
     */
    private function parseDeadline(string $deadline): DateTime
    {
        $now = DateTime::now();

        if (preg_match('/^(\d+)d$/', $deadline, $matches)) {
            return $now->modify("+{$matches[1]} days");
        }

        if (preg_match('/^(\d+)h$/', $deadline, $matches)) {
            return $now->modify("+{$matches[1]} hours");
        }

        if (preg_match('/^(\d+)m$/', $deadline, $matches)) {
            return $now->modify("+{$matches[1]} minutes");
        }

        // Fallback: try parsing as a date string
        return new DateTime($deadline);
    }
}
