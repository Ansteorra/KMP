<?php

declare(strict_types=1);

namespace Activities\Services;

use App\Model\Entity\WorkflowApproval;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

/**
 * Resolves eligible approver member IDs for activity authorization workflow approvals.
 *
 * Called by DefaultWorkflowApprovalManager for DYNAMIC approver type.
 * Shared by both workflow approval gates and the existing controller UI.
 */
class AuthorizationApproverResolver
{
    /**
     * Get eligible approver member IDs for a workflow approval.
     *
     * @param \App\Model\Entity\WorkflowApproval $approval The workflow approval entity
     * @return int[] Member IDs eligible to approve
     */
    public function getEligibleApproverIds(WorkflowApproval $approval): array
    {
        try {
            $config = $approval->approver_config ?? [];
            $activityId = $config['activity_id'] ?? null;

            // If a specific approver is currently designated (serial pick-next mode)
            if (!empty($config['current_approver_id'])) {
                return [(int)$config['current_approver_id']];
            }

            if (!$activityId) {
                Log::warning('AuthorizationApproverResolver: No activity_id in approver_config for approval ' . $approval->id);
                return [];
            }

            $activitiesTable = TableRegistry::getTableLocator()->get('Activities.Activities');
            $activity = $activitiesTable->find()
                ->where(['id' => (int)$activityId])
                ->first();

            if (!$activity) {
                Log::warning("AuthorizationApproverResolver: Activity {$activityId} not found");
                return [];
            }

            if (!$activity->permission_id) {
                Log::warning("AuthorizationApproverResolver: Activity {$activityId} has no permission_id");
                return [];
            }

            // Use activity's permission-based approver query (all branches)
            $query = $activity->getApproversQuery(-1000000);

            // Exclude already-used approvers and the requesting member
            $excludeIds = $config['exclude_member_ids'] ?? [];
            if (!empty($excludeIds)) {
                $query->where(['Members.id NOT IN' => array_map('intval', (array)$excludeIds)]);
            }

            return $query->select(['Members.id'])
                ->distinct()
                ->all()
                ->extract('id')
                ->toArray();
        } catch (\Throwable $e) {
            Log::error('AuthorizationApproverResolver failed: ' . $e->getMessage());
            return [];
        }
    }
}
