<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\WorkflowApproval;
use App\Model\Table\WorkflowApprovalsTable;
use App\Policy\BasePolicy;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * Table-level authorization policy for Recommendations in the Awards plugin.
 *
 * Implements query scoping based on user approval authority and organizational scope.
 * Supports open recommendation submission and export authorization.
 *
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @see \App\Policy\BasePolicy Base table authorization functionality
 * @see \Awards\Model\Table\RecommendationsTable Recommendation data management
 * @see /docs/5.2.16-awards-recommendations-table-policy.md Full documentation
 */
class RecommendationsTablePolicy extends BasePolicy
{
    /**
     * Apply authorization scoping to recommendation queries.
     *
     * Filters recommendations by branch access and approval authority levels.
     * Discovers approval levels from canApproveLevel* permission methods.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user requesting data access
     * @param \Cake\ORM\Query $query The base query to scope
     * @return \Cake\ORM\Query The scoped query with authorization filtering
     */
    public function scopeIndex(KmpIdentityInterface $user, $query): Query
    {
        $branchIds = $this->_getBranchIdsForPolicy($user, 'canIndex');
        $branchPolicies = $user->getPolicies($branchIds);
        $approvalLevels = [];
        $recommendationPolicies = $branchPolicies['Awards\Policy\RecommendationPolicy'] ?? [];

        // Discover approval levels from dynamic permission methods
        foreach ($recommendationPolicies as $method => $policy) {
            if (strpos($method, 'canApproveLevel') === 0) {
                $level = str_replace('canApproveLevel', '', $method);
                $approvalLevels[] = $level;
            }
        }

        $scopeConditions = [];
        if (!empty($branchIds) && $branchIds[0] !== -10000000) {
            $scopeConditions['Awards.branch_id IN'] = $branchIds;
        }
        if (!empty($approvalLevels)) {
            $scopeConditions['Levels.name IN'] = $approvalLevels;
        }

        $workflowRecommendationIds = $this->workflowVisibleRecommendationIds($user);
        if ($scopeConditions === [] && $workflowRecommendationIds !== []) {
            return $query->where(['Recommendations.id IN' => $workflowRecommendationIds]);
        }

        if ($scopeConditions === []) {
            return $query;
        }

        if ($workflowRecommendationIds !== []) {
            $workflowCondition = ['Recommendations.id IN' => $workflowRecommendationIds];

            return $query->where([
                'OR' => [
                    ['AND' => $scopeConditions],
                    $workflowCondition,
                ],
            ]);
        }

        if ($scopeConditions !== []) {
            return $query->where($scopeConditions);
        }

        return $query;
    }

    /**
     * Return recommendation IDs where this member is a current or retained prior approver.
     *
     * @param \App\KMP\KmpIdentityInterface $user Authenticated member identity.
     * @return array<int>
     */
    private function workflowVisibleRecommendationIds(KmpIdentityInterface $user): array
    {
        $memberId = (int)$user->getAsMember()->id;
        if ($memberId <= 0) {
            return [];
        }

        $instanceIds = WorkflowApprovalsTable::getPendingApprovalWorkflowInstanceIdsForMember($memberId);

        $responses = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
        $retainedResponses = $responses->find()
            ->select([
                'workflow_approval_id',
                'approval_instance_id' => 'WorkflowApprovals.workflow_instance_id',
                'approval_config' => 'WorkflowApprovals.approver_config',
            ])
            ->innerJoinWith('WorkflowApprovals', function ($q) {
                return $q->where([
                    'WorkflowApprovals.status IN' => [
                        WorkflowApproval::STATUS_APPROVED,
                        WorkflowApproval::STATUS_REJECTED,
                        WorkflowApproval::STATUS_EXPIRED,
                        WorkflowApproval::STATUS_CANCELLED,
                    ],
                ]);
            })
            ->where(['WorkflowApprovalResponses.member_id' => $memberId])
            ->enableHydration(false)
            ->all();

        foreach ($retainedResponses as $response) {
            $approverConfig = $response['approval_config'] ?? null;
            if (is_string($approverConfig)) {
                $approverConfig = json_decode($approverConfig, true);
            }
            if (empty($approverConfig['retain_read_visibility'])) {
                continue;
            }
            $instanceIds[] = (int)($response['approval_instance_id'] ?? 0);
        }

        $instanceIds = array_values(array_unique(array_filter($instanceIds)));
        if ($instanceIds === []) {
            return [];
        }

        $runsTable = TableRegistry::getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $recommendationIds = $runsTable->find()
            ->select(['recommendation_id'])
            ->where(['RecommendationApprovalRuns.workflow_instance_id IN' => $instanceIds])
            ->all()
            ->extract('recommendation_id')
            ->toList();

        return array_values(array_unique(array_map('intval', $recommendationIds)));
    }

    /**
     * Authorize recommendation creation (open access).
     *
     * @param \App\KMP\KmpIdentityInterface $user The user requesting creation access
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The target entity or table
     * @param mixed ...$optionalArgs Additional authorization arguments
     * @return bool Always true for open recommendation submission
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Authorize recommendation export to CSV.
     *
     * Delegates to canIndex - users who can list can export.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user requesting export access
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The target entity or table
     * @param mixed ...$optionalArgs Additional authorization arguments
     * @return bool True if user has index permission
     */
    public function canExport(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->canIndex($user, $entity, ...$optionalArgs);
    }
}
