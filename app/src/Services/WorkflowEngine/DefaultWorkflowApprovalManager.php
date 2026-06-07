<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine;

use App\KMP\WorkflowApprovalDecisionOptions;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Services\ServiceResult;
use App\Services\WorkflowRegistry\WorkflowApproverResolverRegistry;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Default implementation of WorkflowApprovalManagerInterface.
 *
 * Manages approval gate lifecycle: creation, response recording,
 * eligibility checks, and resolution detection.
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
     * @inheritDoc
     *
     * Uses a three-layer concurrency defense:
     *  1. FOR UPDATE row lock — serialises concurrent transactions
     *  2. Atomic SQL increment — prevents lost count updates
     *  3. Optimistic version check — detects any out-of-band modification
     */
    public function recordResponse(int $approvalId, int $memberId, string $decision, ?string $comment = null, ?int $nextApproverId = null): ServiceResult
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_OPTIMISTIC_RETRIES; $attempt++) {
            try {
                $result = $this->attemptRecordResponse($approvalId, $memberId, $decision, $comment, $nextApproverId);

                return $result;
            } catch (OptimisticLockException $e) {
                $lastException = $e;
                Log::warning(
                    "Optimistic lock conflict on approval {$approvalId}, attempt {$attempt}/"
                    . self::MAX_OPTIMISTIC_RETRIES . ": {$e->getMessage()}"
                );

                if ($attempt < self::MAX_OPTIMISTIC_RETRIES) {
                    usleep(self::RETRY_DELAY_US * $attempt);
                }
            } catch (\Exception $e) {
                Log::error("Error recording approval response: {$e->getMessage()}");

                return new ServiceResult(false, 'An unexpected error occurred.');
            }
        }

        Log::error(
            "Optimistic lock retries exhausted for approval {$approvalId} after "
            . self::MAX_OPTIMISTIC_RETRIES . " attempts"
        );

        return new ServiceResult(false, 'Approval was modified concurrently. Please retry.');
    }

    /**
     * Single transactional attempt to record an approval response.
     *
     * @throws OptimisticLockException When the version check fails.
     */
    private function attemptRecordResponse(int $approvalId, int $memberId, string $decision, ?string $comment, ?int $nextApproverId): ServiceResult
    {
        $connection = ConnectionManager::get('default');

        /** @var ServiceResult $result */
        $result = $connection->transactional(function () use ($approvalId, $memberId, $decision, $comment, $nextApproverId) {
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

            // Check eligibility before accepting the response
            if (!$this->isMemberEligible($approval, $memberId)) {
                return new ServiceResult(false, 'You are not eligible to respond to this approval.');
            }

            $approverConfig = $approval->approver_config ?? [];
            if (!in_array($decision, WorkflowApprovalDecisionOptions::allowedValues($approverConfig), true)) {
                return new ServiceResult(false, 'Invalid approval decision.');
            }

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
                    ['id' => $approval->id, 'version' => $currentVersion]
                );
            } elseif ($decision === WorkflowApprovalResponse::DECISION_REJECT) {
                $affectedRows = $approvalsTable->updateAll(
                    ['rejected_count = rejected_count + 1', 'version' => $currentVersion + 1],
                    ['id' => $approval->id, 'version' => $currentVersion]
                );
            } else {
                // Non-counted decision (e.g. abstain) — still bump version
                $affectedRows = $approvalsTable->updateAll(
                    ['version' => $currentVersion + 1],
                    ['id' => $approval->id, 'version' => $currentVersion]
                );
            }

            if ($affectedRows === 0) {
                throw new OptimisticLockException(
                    "Approval {$approvalId} was modified concurrently (expected version {$currentVersion})."
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
     * @throws OptimisticLockException When the version has been changed by another process.
     */
    private function saveWithVersionCheck($approvalsTable, WorkflowApproval $approval): void
    {
        $approval->version = ($approval->version ?? 1) + 1;

        if (!$approvalsTable->save($approval)) {
            Log::error("Failed to save approval {$approval->id} with version check");
            throw new OptimisticLockException(
                "Failed to save approval {$approval->id} — possible concurrent modification."
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

            $approverType = $config['approverType'] ?? WorkflowApproval::APPROVER_TYPE_PERMISSION;
            $approverConfig = $config['approverConfig'] ?? null;
            $requiredCount = (int)($config['requiredCount'] ?? 1);
            $allowParallel = (bool)($config['allowParallel'] ?? true);
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
                'current_approver_id' => $approverConfig['current_approver_id'] ?? null,
                'required_count' => $requiredCount,
                'approved_count' => 0,
                'rejected_count' => 0,
                'status' => WorkflowApproval::STATUS_PENDING,
                'allow_parallel' => $allowParallel,
                'deadline' => $deadline,
                'version' => 1,
                'approval_token' => \App\KMP\StaticHelpers::generateToken(32),
            ]);

            if (!$approvalsTable->save($approval)) {
                Log::error("Failed to create approval for instance {$instanceId}, node {$nodeId}");
                return new ServiceResult(false, 'Failed to create approval.');
            }

            return new ServiceResult(true, null, ['approvalId' => $approval->id]);
        } catch (\Exception $e) {
            Log::error("Error creating approval: {$e->getMessage()}");
            return new ServiceResult(false, 'An unexpected error occurred.');
        }
    }

    /**
     * @inheritDoc
     *
     * Optimized to pre-fetch member permissions/roles in two queries,
     * then filter approvals in-memory instead of N+1 per-approval DB lookups.
     */
    public function getPendingApprovalsForMember(int $memberId): array
    {
        try {
            $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
            $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');

            // Get IDs of approvals this member already responded to
            $respondedIds = $responsesTable->find()
                ->select(['workflow_approval_id'])
                ->where(['member_id' => $memberId])
                ->all()
                ->extract('workflow_approval_id')
                ->toArray();

            $query = $approvalsTable->find()
                ->contain(['WorkflowInstances.WorkflowDefinitions'])
                ->where(['WorkflowApprovals.status' => WorkflowApproval::STATUS_PENDING]);

            if (!empty($respondedIds)) {
                $query->where(['WorkflowApprovals.id NOT IN' => $respondedIds]);
            }

            // Pre-fetch member's active permissions and roles (2 queries total)
            $memberPermissions = $this->getMemberActivePermissions($memberId);
            $memberRoles = $this->getMemberActiveRoles($memberId);

            $pendingApprovals = $query->all()->toArray();

            // Filter using cached permission/role sets — no per-approval DB queries
            $eligible = [];
            foreach ($pendingApprovals as $approval) {
                if (
                    !$this->hasPriorDynamicWorkflowResponse($approval, $memberId)
                    && $this->isMemberEligibleCached($approval, $memberId, $memberPermissions, $memberRoles)
                ) {
                    $eligible[] = $approval;
                }
            }

            return $eligible;
        } catch (\Exception $e) {
            Log::error("Error fetching pending approvals for member {$memberId}: {$e->getMessage()}");
            return [];
        }
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
     * @return ServiceResult Contains approvalId, instanceId, nodeId, previousApproverId, newApproverId on success
     */
    public function reassignApproval(int $approvalId, int $newApproverId, int $adminMemberId, ?string $reason = null): ServiceResult
    {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
     * @return int[] Member IDs eligible to be picked as next approver
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
                    $this->findMembersByPermission($config['permission'] ?? '')
                ),
                WorkflowApproval::APPROVER_TYPE_ROLE => array_map(
                    fn($m) => $m->id,
                    $this->findMembersByRole($config['role'] ?? '')
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
        } catch (\Exception $e) {
            Log::error("Error fetching next approver candidates for {$approvalId}: {$e->getMessage()}");
            return [];
        }
    }

    /**
     * Check if a member is eligible to respond to an approval based on approver_type.
     */
    private function isMemberEligible(WorkflowApproval $approval, int $memberId): bool
    {
        $config = $approval->approver_config ?? [];

        // Serial pick-next: when current_approver_id is set, only that member is eligible.
        // For permission/role-based approvals, also verify the member still holds the
        // underlying permission/role (e.g. warrant not expired).
        if (!empty($config['serial_pick_next']) && !empty($config['current_approver_id'])) {
            if ($memberId !== (int)$config['current_approver_id']) {
                return false;
            }
            // Member-type approvals are person-to-person — no permission check needed
            if ($approval->approver_type === WorkflowApproval::APPROVER_TYPE_MEMBER) {
                return true;
            }
            // Fall through to the approver_type switch to validate active permission/role
        }

        switch ($approval->approver_type) {
            case WorkflowApproval::APPROVER_TYPE_PERMISSION:
                $permissionName = $config['permission'] ?? null;
                if (!$permissionName) {
                    return false;
                }
                return $this->memberHasPermission($memberId, $permissionName);

            case WorkflowApproval::APPROVER_TYPE_ROLE:
                $roleName = $config['role'] ?? null;
                if (!$roleName) {
                    return false;
                }
                return $this->memberHasRole($memberId, $roleName);

            case WorkflowApproval::APPROVER_TYPE_MEMBER:
                // Person-to-person: ID match only, no permission required
                $targetMemberId = (int)($config['member_id'] ?? 0);
                return $memberId === $targetMemberId;

            case WorkflowApproval::APPROVER_TYPE_DYNAMIC:
                if ($this->hasPriorDynamicWorkflowResponse($approval, $memberId)) {
                    return false;
                }
                return $this->resolveDynamicEligibility($approval, $memberId);

            case WorkflowApproval::APPROVER_TYPE_POLICY:
                return $this->memberPassesPolicy($approval, $memberId);

            default:
                return false;
        }
    }

    /**
     * Find all members eligible to respond to an approval.
     *
     * @return \App\Model\Entity\Member[]
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
     * Check if a member has a specific permission via their active (unexpired) roles.
     */
    private function memberHasPermission(int $memberId, string $permissionName): bool
    {
        $memberRolesTable = TableRegistry::getTableLocator()->get('MemberRoles');
        $now = DateTime::now();

        $count = $memberRolesTable->find()
            ->innerJoinWith('Roles.Permissions')
            ->where([
                'MemberRoles.member_id' => $memberId,
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
            ->count();

        return $count > 0;
    }

    /**
     * Check if a member has a specific active (unexpired) role.
     */
    private function memberHasRole(int $memberId, string $roleName): bool
    {
        $memberRolesTable = TableRegistry::getTableLocator()->get('MemberRoles');
        $now = DateTime::now();

        $count = $memberRolesTable->find()
            ->innerJoinWith('Roles')
            ->where([
                'MemberRoles.member_id' => $memberId,
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
            ->count();

        return $count > 0;
    }

    /**
     * Find all members who have an active (unexpired) role granting the specified permission.
     *
     * @return \App\Model\Entity\Member[]
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
     * @return \App\Model\Entity\Member[]
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
     * Get all active permission names for a member in a single query.
     *
     * @return string[]
     */
    private function getMemberActivePermissions(int $memberId): array
    {
        $memberRolesTable = TableRegistry::getTableLocator()->get('MemberRoles');
        $now = DateTime::now();

        return $memberRolesTable->find()
            ->innerJoinWith('Roles.Permissions')
            ->select(['permission_name' => 'Permissions.name'])
            ->where([
                'MemberRoles.member_id' => $memberId,
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
            ->distinct()
            ->all()
            ->extract('permission_name')
            ->toArray();
    }

    /**
     * Get all active role names for a member in a single query.
     *
     * @return string[]
     */
    private function getMemberActiveRoles(int $memberId): array
    {
        $memberRolesTable = TableRegistry::getTableLocator()->get('MemberRoles');
        $now = DateTime::now();

        return $memberRolesTable->find()
            ->innerJoinWith('Roles')
            ->select(['role_name' => 'Roles.name'])
            ->where([
                'MemberRoles.member_id' => $memberId,
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
            ->distinct()
            ->all()
            ->extract('role_name')
            ->toArray();
    }

    /**
     * Check eligibility using pre-fetched permission/role sets to avoid N+1 queries.
     */
    private function isMemberEligibleCached(
        WorkflowApproval $approval,
        int $memberId,
        array $memberPermissions,
        array $memberRoles,
    ): bool {
        $config = $approval->approver_config ?? [];

        // If a specific approver is assigned, only they are eligible.
        // For permission/role-based approvals, also verify the member still holds
        // the underlying permission/role (e.g. warrant not expired).
        $currentApproverId = $approval->current_approver_id
            ?? (!empty($config['current_approver_id']) ? (int)$config['current_approver_id'] : null);

        if ($currentApproverId !== null) {
            if ($memberId !== $currentApproverId) {
                return false;
            }
            // Member-type approvals are person-to-person — no permission check needed
            if ($approval->approver_type === WorkflowApproval::APPROVER_TYPE_MEMBER) {
                return true;
            }
            // Fall through to the approver_type switch to validate active permission/role
        }

        // No specific assignee or assigned + needs permission/role validation
        switch ($approval->approver_type) {
            case WorkflowApproval::APPROVER_TYPE_PERMISSION:
                $permissionName = $config['permission'] ?? null;
                return $permissionName && in_array($permissionName, $memberPermissions, true);

            case WorkflowApproval::APPROVER_TYPE_ROLE:
                $roleName = $config['role'] ?? null;
                return $roleName && in_array($roleName, $memberRoles, true);

            case WorkflowApproval::APPROVER_TYPE_MEMBER:
                // Person-to-person: ID match only, no permission required
                $targetMemberId = (int)($config['member_id'] ?? 0);
                return $memberId === $targetMemberId;

            case WorkflowApproval::APPROVER_TYPE_DYNAMIC:
                if ($this->hasPriorDynamicWorkflowResponse($approval, $memberId)) {
                    return false;
                }
                return $this->resolveDynamicEligibility($approval, $memberId);

            case WorkflowApproval::APPROVER_TYPE_POLICY:
                return $this->memberPassesPolicy($approval, $memberId);

            default:
                return false;
        }
    }

    /**
     * Check if a member is eligible via the dynamic callback approver.
     */
    private function resolveDynamicEligibility(WorkflowApproval $approval, int $memberId): bool
    {
        try {
            $eligibleIds = $this->resolveDynamicApproverIds($approval);
            return in_array($memberId, $eligibleIds, true);
        } catch (\RuntimeException $e) {
            Log::error("Dynamic approver resolution failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Resolve eligible member IDs via configured callback service.
     *
     * Expects approver_config: {"service": "App\\Services\\MyService", "method": "getEligibleApprovers"}
     * The callback receives the WorkflowApproval and must return int[] of member IDs.
     *
     * @return int[]
     * @throws \RuntimeException If config is missing or callback is invalid
     */
    private function resolveDynamicApproverIds(WorkflowApproval $approval): array
    {
        $config = $approval->approver_config ?? [];
        $serviceRef = $config['service'] ?? null;
        $method = $config['method'] ?? null;

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
            throw new \RuntimeException(
                "Dynamic approver type requires 'service' and 'method' in approver_config. "
                . "Approval ID: {$approval->id}"
            );
        }

        if (!class_exists($serviceClass)) {
            throw new \RuntimeException("Dynamic approver service class '{$serviceClass}' not found.");
        }

        $service = new $serviceClass();
        if (!method_exists($service, $method)) {
            throw new \RuntimeException("Method '{$method}' not found on '{$serviceClass}'.");
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
     * @return \App\Model\Entity\Member[]
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
        } catch (\RuntimeException $e) {
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

    /**
     * Check if a member passes a CakePHP policy check for the approval's entity.
     *
     * Resolves the entity from the workflow instance context, loads the member
     * as an identity, instantiates the policy class, and calls the action method.
     */
    private function memberPassesPolicy(WorkflowApproval $approval, int $memberId): bool
    {
        $config = $approval->approver_config ?? [];
        $policyClass = $config['policyClass'] ?? null;
        $policyAction = $config['policyAction'] ?? null;
        $entityTable = $config['entityTable'] ?? null;
        $entityIdKey = $config['entityIdKey'] ?? null;

        if (!$policyClass || !$policyAction || !$entityTable || !$entityIdKey) {
            Log::warning("Policy approver type missing config: policyClass={$policyClass}, policyAction={$policyAction}, entityTable={$entityTable}, entityIdKey={$entityIdKey}");
            return false;
        }

        // Load the workflow instance to get context
        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $instance = $instancesTable->get($approval->workflow_instance_id);
        $context = $instance->context ?? [];

        // Resolve entity ID from context using dot-path key
        $entityId = $this->resolveContextValue($context, $entityIdKey);
        if (!$entityId) {
            Log::warning("Policy check: could not resolve entity ID from context key '{$entityIdKey}'");
            return false;
        }

        // Load the target entity
        $table = TableRegistry::getTableLocator()->get($entityTable);
        $entity = $table->find()->where([$table->getAlias() . '.id' => $entityId])->first();
        if (!$entity) {
            Log::warning("Policy check: entity {$entityTable}#{$entityId} not found");
            return false;
        }

        // Load the member as an identity (has getPolicies() via KmpIdentityInterface)
        $membersTable = TableRegistry::getTableLocator()->get('Members');
        $member = $membersTable->get($memberId);

        // Instantiate the policy and call the action method
        if (!class_exists($policyClass)) {
            Log::warning("Policy check: class {$policyClass} not found");
            return false;
        }

        $policy = new $policyClass();
        $methodName = $policyAction;
        if (!method_exists($policy, $methodName)) {
            Log::warning("Policy check: method {$methodName} not found on {$policyClass}");
            return false;
        }

        try {
            return $policy->$methodName($member, $entity);
        } catch (\Exception $e) {
            Log::error("Policy check failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Resolve a dot-path value from a nested context array.
     * E.g., "trigger.rosterId" resolves $context['trigger']['rosterId'].
     */
    private function resolveContextValue(array $context, string $key): mixed
    {
        $parts = explode('.', $key);
        $current = $context;
        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }
        return $current;
    }
}
