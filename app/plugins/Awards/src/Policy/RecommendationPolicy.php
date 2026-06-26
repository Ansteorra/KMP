<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\Member;
use App\Model\Entity\WorkflowApproval;
use App\Model\Table\WorkflowApprovalsTable;
use App\Policy\BasePolicy;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Services\RecommendationWorkflowUiService;
use BadMethodCallException;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * Authorization policy for Recommendation entities in the Awards plugin.
 *
 * Implements state machine management, workflow authorization, and dynamic approval
 * level validation. Supports dynamic canApproveLevel* methods based on award levels.
 *
 * @method bool canView(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canEdit(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canDelete(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity, mixed ...$optionalArgs)
 * @see \App\Policy\BasePolicy Base authorization functionality
 * @see /docs/5.2.13-awards-recommendation-policy.md Full documentation
 */
class RecommendationPolicy extends BasePolicy
{
    /**
     * Pending approval workflow instance IDs keyed by member ID and scoped workflow instances.
     *
     * @var array<string, array<int>>
     */
    private array $pendingWorkflowInstanceIdsByMemberId = [];

    /**
     * Approval workflow instance IDs keyed by recommendation ID and status filter.
     *
     * @var array<string, array<int>>
     */
    private array $approvalWorkflowInstanceIdsByRecommendation = [];

    /**
     * Whether a member has active Awards recommendation approval work.
     *
     * @var array<int, bool>
     */
    private array $pendingRecommendationApprovalByMemberId = [];

    /**
     * Active Awards recommendation approval workflow instance IDs for this request.
     *
     * @var array<int>|null
     */
    private ?array $activeAwardsApprovalWorkflowInstanceIds = null;

    /**
     * Check if user can view a recommendation.
     *
     * Current workflow approvers and retained prior approvers receive read-only access.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The recommendation entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canView(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        if ($entity instanceof Recommendation) {
            if ($this->canViewViaCurrentApproval($user, $entity)) {
                return true;
            }
            if ($this->canViewViaApprovalWorkflow($user, $entity)) {
                return true;
            }
        }

        return parent::canView($user, $entity, ...$optionalArgs);
    }

    /**
     * Check if user can access the recommendation list.
     *
     * Current workflow approvers need list access so the grid can show only the
     * recommendations awaiting their action through the table policy scope.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The recommendation context
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        if (parent::canIndex($user, $entity, ...$optionalArgs)) {
            return true;
        }

        return $this->hasPendingRecommendationApproval($user);
    }

    /**
     * Check if user can edit a recommendation.
     *
     * Denies edit when the recommendation is locked to an active bestowal workflow.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->isLockedByBestowal($entity)) {
            return false;
        }

        if ($entity instanceof Recommendation && $this->canViewViaCurrentApproval($user, $entity)) {
            return true;
        }

        return parent::canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @inheritDoc
     */
    public function canDelete(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->isLockedByBestowal($entity)) {
            return false;
        }

        return parent::canDelete($user, $entity, ...$optionalArgs);
    }

    /**
     * @inheritDoc
     */
    public function canUpdateStates(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->isLockedByBestowal($entity)) {
            return false;
        }

        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if the user can respond to the active approval from the recommendation screen.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user.
     * @param \App\Model\Entity\BaseEntity $entity Recommendation entity.
     * @param mixed ...$optionalArgs Additional authorization context.
     * @return bool
     */
    public function canDecideApproval(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if (!$entity instanceof Recommendation || $this->isLockedByBestowal($entity)) {
            return false;
        }

        return (new RecommendationWorkflowUiService())->pendingApprovalForRecommendation($entity, $user) !== null;
    }

    /**
     * Check if the user can start a new approval workflow from the recommendation screen.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user.
     * @param \App\Model\Entity\BaseEntity $entity Recommendation entity.
     * @param mixed ...$optionalArgs Additional authorization context.
     * @return bool
     */
    public function canStartApprovalWorkflow(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if (!$entity instanceof Recommendation || !$this->canEdit($user, $entity, ...$optionalArgs)) {
            return false;
        }

        return (new RecommendationWorkflowUiService())->canStartApprovalWorkflow($entity);
    }

    /**
     * Determine whether a recommendation is locked by a linked bestowal.
     *
     * @param \App\Model\Entity\BaseEntity $entity Recommendation entity
     * @return bool
     */
    protected function isLockedByBestowal(BaseEntity $entity): bool
    {
        if (!$entity instanceof Recommendation) {
            return false;
        }

        return $entity->isLockedByBestowal();
    }

    /**
     * Check if user can view recommendations they submitted.
     *
     * Grants direct access if user is the requester, otherwise delegates to permission check.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$args Additional authorization context
     * @return bool True if authorized
     */
    public function canViewSubmittedByMember(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        if ($this->canManageRecommendationMember($user, (int)$entity->requester_id)) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Determine whether the user can manage recommendation actions for a member.
     *
     * Allows self or parent-of-minor access.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param int $memberId
     * @return bool
     */
    protected function canManageRecommendationMember(KmpIdentityInterface $user, int $memberId): bool
    {
        if ($memberId <= 0) {
            return false;
        }

        if ($user instanceof Member) {
            $target = new Member();
            $target->id = $memberId;

            return $user->canManageMember($target);
        }

        return false;
    }

    /**
     * Check if user can view recommendations submitted for a specific member.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$args Additional authorization context
     * @return bool True if authorized
     */
    public function canViewSubmittedForMember(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can view recommendations for a specific event.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$args Additional authorization context
     * @return bool True if authorized
     */
    public function canViewEventRecommendations(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can export recommendation data.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$args Additional authorization context
     * @return bool True if authorized
     */
    public function canExport(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can view hidden recommendations.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canViewHidden(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can view private notes on recommendations.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canViewPrivateNotes(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can add notes to recommendations.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canAddNote(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->isLockedByBestowal($entity)) {
            return false;
        }

        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can request recommendation feedback.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The recommendation context
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canRequestFeedback(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        if ($entity instanceof BaseEntity) {
            return $this->_hasPolicy($user, __FUNCTION__, $entity) || $this->canEdit($user, $entity, ...$optionalArgs);
        }

        return $this->_hasPolicy($user, __FUNCTION__, $entity);
    }

    /**
     * Check if user can retract recommendation feedback requests.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The recommendation context
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canRetractFeedback(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->canRequestFeedback($user, $entity, ...$optionalArgs);
    }

    /**
     * Check if user can administer recommendation feedback requests.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The recommendation context
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canAdministerFeedback(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, __FUNCTION__, $entity) || $user->isSuperUser();
    }

    /**
     * Check if user can add new recommendations.
     *
     * Open authorization - any authenticated user can submit recommendations.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The recommendation context
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool Always true for open submission
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Check if user can group/ungroup recommendations.
     *
     * Delegates to edit permission — same users who can edit can group.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canGroup(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->isLockedByBestowal($entity)) {
            return false;
        }

        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Handle dynamic approval authority methods (canApproveLevel*).
     *
     * Resolves level-specific approval methods dynamically based on award levels.
     *
     * @param string $name The method name (e.g., 'canApproveLevelAoA')
     * @param array $arguments [$user, $entity, ...args]
     * @return bool True if user has approval authority for the level
     * @throws \BadMethodCallException When method is not a recognized dynamic method
     */
    public function __call($name, $arguments): bool
    {
        if (strpos($name, 'canApproveLevel') === 0) {
            $user = $arguments[0] ?? null;
            $entity = $arguments[1] ?? null;

            return $this->_hasPolicy($user, $name, $entity);
        }

        throw new BadMethodCallException("Method {$name} does not exist");
    }

    /**
     * Get names of dynamically generated approval methods.
     *
     * Returns canApproveLevel* method names based on current award levels.
     *
     * @return array List of dynamic method names
     */
    public static function getDynamicMethods(): array
    {
        $dynamicMethods = [];

        $levelsTable = TableRegistry::getTableLocator()->get('Awards.Levels');
        $levelNames = $levelsTable->getAllLevelNames();

        foreach ($levelNames as $levelName) {
            $methodName = 'canApproveLevel' . $levelName;
            $dynamicMethods[] = $methodName;
        }

        return $dynamicMethods;
    }

    /**
     * Check current approver visibility for an active approval-cycle recommendation.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user.
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation entity.
     * @return bool
     */
    private function canViewViaCurrentApproval(
        KmpIdentityInterface $user,
        Recommendation $recommendation,
    ): bool {
        $memberId = (int)$user->getAsMember()->id;
        if ($memberId <= 0) {
            return false;
        }

        $activeWorkflowInstanceIds = $this->activeApprovalWorkflowInstanceIds($recommendation);
        if ($activeWorkflowInstanceIds === []) {
            return false;
        }

        return array_intersect(
            $activeWorkflowInstanceIds,
            $this->pendingWorkflowInstanceIdsForMember($memberId, $activeWorkflowInstanceIds),
        ) !== [];
    }

    /**
     * Determine workflow-backed read access for current and prior approvers.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation entity
     * @return bool
     */
    private function canViewViaApprovalWorkflow(
        KmpIdentityInterface $user,
        Recommendation $recommendation,
    ): bool {
        $memberId = (int)$user->getAsMember()->id;
        if ($memberId <= 0 || empty($recommendation->id)) {
            return false;
        }

        $workflowInstanceIds = $this->approvalWorkflowInstanceIds($recommendation);
        if ($workflowInstanceIds === []) {
            return false;
        }

        $pendingInstanceIds = $this->pendingWorkflowInstanceIdsForMember($memberId, $workflowInstanceIds);
        if (array_intersect($workflowInstanceIds, $pendingInstanceIds) !== []) {
            return true;
        }

        $activeWorkflowInstanceIds = $this->activeApprovalWorkflowInstanceIds($recommendation);
        $responses = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
        $retainedResponses = $responses->find()
            ->select([
                'workflow_approval_id',
                'approval_instance_id' => 'WorkflowApprovals.workflow_instance_id',
                'approval_config' => 'WorkflowApprovals.approver_config',
            ])
            ->innerJoinWith('WorkflowApprovals', function ($q) use ($workflowInstanceIds) {
                return $q->where([
                    'WorkflowApprovals.workflow_instance_id IN' => $workflowInstanceIds,
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
            if (in_array((int)($response['approval_instance_id'] ?? 0), $activeWorkflowInstanceIds, true)) {
                return true;
            }

            $approverConfig = $response['approval_config'] ?? null;
            if (is_string($approverConfig)) {
                $approverConfig = json_decode($approverConfig, true);
            }
            if (empty($approverConfig['retain_read_visibility'])) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Return active approval workflow instance IDs for a recommendation.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation entity.
     * @return array<int>
     */
    private function activeApprovalWorkflowInstanceIds(Recommendation $recommendation): array
    {
        return $this->approvalWorkflowInstanceIds($recommendation, [
            RecommendationApprovalRun::STATUS_IN_PROGRESS,
            RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
        ]);
    }

    /**
     * Return approval workflow instance IDs for a recommendation.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation entity.
     * @param array<string>|null $statuses Optional approval run status filter.
     * @return array<int>
     */
    private function approvalWorkflowInstanceIds(Recommendation $recommendation, ?array $statuses = null): array
    {
        if (empty($recommendation->id)) {
            return [];
        }

        $headRecommendationId = (int)($recommendation->recommendation_group_id ?? $recommendation->id);
        $cacheKey = $headRecommendationId . ':' . implode(',', $statuses ?? ['all']);
        if (array_key_exists($cacheKey, $this->approvalWorkflowInstanceIdsByRecommendation)) {
            return $this->approvalWorkflowInstanceIdsByRecommendation[$cacheKey];
        }

        $runsTable = TableRegistry::getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $query = $runsTable->find()
            ->select(['workflow_instance_id'])
            ->where(['RecommendationApprovalRuns.recommendation_id' => $headRecommendationId]);
        if ($statuses !== null) {
            $query->where(['RecommendationApprovalRuns.status IN' => $statuses]);
        }

        $workflowInstanceIds = $query->all()
            ->extract('workflow_instance_id')
            ->map(static fn($id): int => (int)$id)
            ->toList();

        $workflowInstanceIds = array_values(array_unique(array_filter($workflowInstanceIds)));
        $this->approvalWorkflowInstanceIdsByRecommendation[$cacheKey] = $workflowInstanceIds;

        return $workflowInstanceIds;
    }

    /**
     * Get pending approval workflow instance IDs for a member and workflow scope once per policy instance.
     *
     * @param int $memberId Member ID.
     * @param array<int>|null $workflowInstanceIds Optional workflow instance scope.
     * @return array<int>
     */
    private function pendingWorkflowInstanceIdsForMember(int $memberId, ?array $workflowInstanceIds = null): array
    {
        $workflowInstanceIds ??= $this->activeAwardsApprovalWorkflowInstanceIds();
        $workflowInstanceIds = array_values(array_unique(array_filter(array_map('intval', $workflowInstanceIds))));
        if ($workflowInstanceIds === []) {
            return [];
        }

        sort($workflowInstanceIds);
        $cacheKey = $memberId . ':' . sha1(implode(',', $workflowInstanceIds));
        if (!array_key_exists($cacheKey, $this->pendingWorkflowInstanceIdsByMemberId)) {
            $this->pendingWorkflowInstanceIdsByMemberId[$cacheKey] =
                WorkflowApprovalsTable::getPendingApprovalWorkflowInstanceIdsForMember($memberId, $workflowInstanceIds);
        }

        return $this->pendingWorkflowInstanceIdsByMemberId[$cacheKey];
    }

    /**
     * Return active Awards recommendation approval workflow instance IDs.
     *
     * @return array<int>
     */
    private function activeAwardsApprovalWorkflowInstanceIds(): array
    {
        if ($this->activeAwardsApprovalWorkflowInstanceIds !== null) {
            return $this->activeAwardsApprovalWorkflowInstanceIds;
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

        $this->activeAwardsApprovalWorkflowInstanceIds = array_values(array_unique(array_filter($workflowInstanceIds)));

        return $this->activeAwardsApprovalWorkflowInstanceIds;
    }

    /**
     * Determine whether the member currently has any Awards recommendation approval work.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user.
     * @return bool
     */
    private function hasPendingRecommendationApproval(KmpIdentityInterface $user): bool
    {
        $memberId = (int)$user->getAsMember()->id;
        if ($memberId <= 0) {
            return false;
        }
        if (array_key_exists($memberId, $this->pendingRecommendationApprovalByMemberId)) {
            return $this->pendingRecommendationApprovalByMemberId[$memberId];
        }

        $pendingWorkflowInstanceIds = $this->pendingWorkflowInstanceIdsForMember($memberId);
        if ($pendingWorkflowInstanceIds === []) {
            $this->pendingRecommendationApprovalByMemberId[$memberId] = false;

            return false;
        }

        $runsTable = TableRegistry::getTableLocator()->get('Awards.RecommendationApprovalRuns');

        $hasPendingApproval = $runsTable->find()
            ->where([
                'RecommendationApprovalRuns.workflow_instance_id IN' => $pendingWorkflowInstanceIds,
                'RecommendationApprovalRuns.status IN' => [
                    RecommendationApprovalRun::STATUS_IN_PROGRESS,
                    RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                ],
            ])
            ->limit(1)
            ->first() !== null;
        $this->pendingRecommendationApprovalByMemberId[$memberId] = $hasPendingApproval;

        return $hasPendingApproval;
    }
}
