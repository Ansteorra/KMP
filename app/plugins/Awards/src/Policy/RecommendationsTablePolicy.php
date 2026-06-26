<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\WorkflowApproval;
use App\Model\Table\WorkflowApprovalsTable;
use App\Policy\BasePolicy;
use Awards\Model\Entity\RecommendationApprovalRun;
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
     * Current approval recommendation IDs keyed by member ID.
     *
     * @var array<int, array<int>>
     */
    private array $pendingApprovalRecommendationIdsByMemberId = [];

    /**
     * Current/prior workflow-visible recommendation IDs keyed by member ID and pending IDs.
     *
     * @var array<string, array<int>>
     */
    private array $workflowVisibleRecommendationIdsByMemberAndPending = [];

    /**
     * Active Awards recommendation approval workflow instance IDs for this request.
     *
     * @var array<int>|null
     */
    private ?array $activeApprovalWorkflowInstanceIds = null;

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
        if ($this->_isSuperUser($user)) {
            return $query;
        }

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
        $hasBranchScope = !empty($branchIds) && $branchIds[0] !== -10000000;
        if ($hasBranchScope || !empty($approvalLevels)) {
            // Express the branch/level scope as an award_id subquery so the
            // condition is self-sufficient and does not depend on the caller
            // having joined Awards/Levels onto the recommendation query.
            $awardsTable = TableRegistry::getTableLocator()->get('Awards.Awards');
            $awardIdQuery = $awardsTable->find()->select(['Awards.id']);
            if ($hasBranchScope) {
                $awardIdQuery->where(['Awards.branch_id IN' => $branchIds]);
            }
            if (!empty($approvalLevels)) {
                $awardIdQuery
                    ->innerJoinWith('Levels')
                    ->where(['Levels.name IN' => $approvalLevels]);
            }
            $scopeConditions['Recommendations.award_id IN'] = $awardIdQuery;
        }

        $pendingRecommendationIds = $this->pendingApprovalRecommendationIds($user);
        $workflowRecommendationIds = $this->workflowVisibleRecommendationIds($user, $pendingRecommendationIds);
        if ($scopeConditions === [] && $workflowRecommendationIds !== []) {
            return $this->applyActiveApprovalVisibility(
                $query->where(['Recommendations.id IN' => $workflowRecommendationIds]),
                $workflowRecommendationIds,
            );
        }

        if ($scopeConditions === []) {
            return $this->applyActiveApprovalVisibility($query, $workflowRecommendationIds);
        }

        if ($workflowRecommendationIds !== []) {
            $workflowCondition = ['Recommendations.id IN' => $workflowRecommendationIds];

            return $this->applyActiveApprovalVisibility(
                $query->where([
                    'OR' => [
                        ['AND' => $scopeConditions],
                        $workflowCondition,
                    ],
                ]),
                $workflowRecommendationIds,
            );
        }

        if ($scopeConditions !== []) {
            return $this->applyActiveApprovalVisibility($query->where($scopeConditions), $workflowRecommendationIds);
        }

        return $this->applyActiveApprovalVisibility($query, $workflowRecommendationIds);
    }

    /**
     * Return recommendation IDs where this member is a current approver.
     *
     * @param \App\KMP\KmpIdentityInterface $user Authenticated member identity.
     * @return array<int>
     */
    private function pendingApprovalRecommendationIds(KmpIdentityInterface $user): array
    {
        $memberId = (int)$user->getAsMember()->id;
        if ($memberId <= 0) {
            return [];
        }
        if (array_key_exists($memberId, $this->pendingApprovalRecommendationIdsByMemberId)) {
            return $this->pendingApprovalRecommendationIdsByMemberId[$memberId];
        }

        $activeWorkflowInstanceIds = $this->activeApprovalWorkflowInstanceIds();
        $instanceIds = WorkflowApprovalsTable::getPendingApprovalWorkflowInstanceIdsForMember(
            $memberId,
            $activeWorkflowInstanceIds,
        );
        if ($instanceIds === []) {
            $this->pendingApprovalRecommendationIdsByMemberId[$memberId] = [];

            return [];
        }

        $recommendationIds = $this->recommendationIdsForWorkflowInstances($instanceIds);
        $this->pendingApprovalRecommendationIdsByMemberId[$memberId] = $recommendationIds;

        return $recommendationIds;
    }

    /**
     * Return recommendation IDs where this member is a current or prior workflow approver.
     *
     * @param \App\KMP\KmpIdentityInterface $user Authenticated member identity.
     * @param array<int> $pendingRecommendationIds Recommendation IDs currently pending for the member.
     * @return array<int>
     */
    private function workflowVisibleRecommendationIds(
        KmpIdentityInterface $user,
        array $pendingRecommendationIds,
    ): array {
        $memberId = (int)$user->getAsMember()->id;
        if ($memberId <= 0) {
            return [];
        }
        $cacheKey = $memberId . ':' . implode(',', $pendingRecommendationIds);
        if (array_key_exists($cacheKey, $this->workflowVisibleRecommendationIdsByMemberAndPending)) {
            return $this->workflowVisibleRecommendationIdsByMemberAndPending[$cacheKey];
        }

        $recommendationIds = $pendingRecommendationIds;
        $activeWorkflowInstanceIds = $this->activeApprovalWorkflowInstanceIds();
        $responses = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
        $activeResponses = [];
        if ($activeWorkflowInstanceIds !== []) {
            $activeResponses = $responses->find()
                ->select([
                    'approval_instance_id' => 'WorkflowApprovals.workflow_instance_id',
                ])
                ->innerJoinWith('WorkflowApprovals', function ($q) use ($activeWorkflowInstanceIds) {
                    return $q->where(['WorkflowApprovals.workflow_instance_id IN' => $activeWorkflowInstanceIds]);
                })
                ->where(['WorkflowApprovalResponses.member_id' => $memberId])
                ->enableHydration(false)
                ->all();
        }

        $activeResponseWorkflowInstanceIds = [];
        foreach ($activeResponses as $response) {
            $activeResponseWorkflowInstanceIds[] = (int)($response['approval_instance_id'] ?? 0);
        }
        if ($activeResponseWorkflowInstanceIds !== []) {
            $activeResponseRecommendationIds = $this->recommendationIdsForWorkflowInstances(
                $activeResponseWorkflowInstanceIds,
            );
            array_push($recommendationIds, ...$activeResponseRecommendationIds);
        }

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

        $retainedWorkflowInstanceIds = [];
        foreach ($retainedResponses as $response) {
            $approverConfig = $response['approval_config'] ?? null;
            if (is_string($approverConfig)) {
                $approverConfig = json_decode($approverConfig, true);
            }
            if (empty($approverConfig['retain_read_visibility'])) {
                continue;
            }
            $retainedWorkflowInstanceIds[] = (int)($response['approval_instance_id'] ?? 0);
        }

        if ($retainedWorkflowInstanceIds !== []) {
            $retainedRecommendationIds = $this->recommendationIdsForWorkflowInstances($retainedWorkflowInstanceIds);
            array_push($recommendationIds, ...$retainedRecommendationIds);
        }

        $recommendationIds = array_values(array_unique(array_filter(array_map('intval', $recommendationIds))));
        $this->workflowVisibleRecommendationIdsByMemberAndPending[$cacheKey] = $recommendationIds;

        return $recommendationIds;
    }

    /**
     * Map workflow instance IDs to recommendation IDs.
     *
     * @param array<int> $instanceIds Workflow instance IDs.
     * @return array<int>
     */
    private function recommendationIdsForWorkflowInstances(array $instanceIds): array
    {
        $instanceIds = array_values(array_unique(array_filter(array_map('intval', $instanceIds))));
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
     * Return active Awards recommendation approval workflow instance IDs.
     *
     * @return array<int>
     */
    private function activeApprovalWorkflowInstanceIds(): array
    {
        if ($this->activeApprovalWorkflowInstanceIds !== null) {
            return $this->activeApprovalWorkflowInstanceIds;
        }

        $runsTable = TableRegistry::getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $workflowInstanceIds = $runsTable->find()
            ->select(['workflow_instance_id'])
            ->where([
                'RecommendationApprovalRuns.status IN' => [
                    RecommendationApprovalRun::STATUS_IN_PROGRESS,
                    RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                ],
            ])
            ->all()
            ->extract('workflow_instance_id')
            ->map(static fn($id): int => (int)$id)
            ->toList();

        $this->activeApprovalWorkflowInstanceIds = array_values(array_unique(array_filter($workflowInstanceIds)));

        return $this->activeApprovalWorkflowInstanceIds;
    }

    /**
     * Hide active approval-cycle recommendations unless this user has workflow visibility.
     *
     * @param \Cake\ORM\Query $query Query being scoped.
     * @param array<int> $workflowVisibleRecommendationIds Recommendation IDs visible through current/prior workflow approvals.
     * @return \Cake\ORM\Query
     */
    private function applyActiveApprovalVisibility(Query $query, array $workflowVisibleRecommendationIds): Query
    {
        $activeRecommendationIds = $this->activeApprovalRecommendationIdsQuery();
        if ($workflowVisibleRecommendationIds === []) {
            return $query->where(['Recommendations.id NOT IN' => $activeRecommendationIds]);
        }

        return $query->where([
            'OR' => [
                ['Recommendations.id NOT IN' => $activeRecommendationIds],
                ['Recommendations.id IN' => $workflowVisibleRecommendationIds],
            ],
        ]);
    }

    /**
     * Build the active approval-cycle recommendation ID subquery.
     *
     * @return \Cake\ORM\Query
     */
    private function activeApprovalRecommendationIdsQuery(): Query
    {
        $runsTable = TableRegistry::getTableLocator()->get('Awards.RecommendationApprovalRuns');

        return $runsTable->find()
            ->select(['recommendation_id'])
            ->where([
                'RecommendationApprovalRuns.status IN' => [
                    RecommendationApprovalRun::STATUS_IN_PROGRESS,
                    RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                ],
            ]);
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
