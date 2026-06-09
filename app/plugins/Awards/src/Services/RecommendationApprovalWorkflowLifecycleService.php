<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use RuntimeException;

/**
 * Owns workflow-backed lifecycle operations for recommendation approvals.
 */
class RecommendationApprovalWorkflowLifecycleService
{
    use LocatorAwareTrait;

    /**
     * @var array<int, string>
     */
    public const ACTIVE_STATUSES = [
        RecommendationApprovalRun::STATUS_IN_PROGRESS,
        RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
    ];

    private Table $recommendationsTable;
    private Table $approvalRunsTable;
    private Table $workflowInstancesTable;
    private Table $workflowApprovalsTable;

    /**
     * @param \Cake\ORM\Table|null $recommendationsTable Optional recommendations table.
     * @param \Cake\ORM\Table|null $approvalRunsTable Optional recommendation approval runs table.
     * @param \Cake\ORM\Table|null $workflowInstancesTable Optional workflow instances table.
     * @param \Cake\ORM\Table|null $workflowApprovalsTable Optional workflow approvals table.
     */
    public function __construct(
        ?Table $recommendationsTable = null,
        ?Table $approvalRunsTable = null,
        ?Table $workflowInstancesTable = null,
        ?Table $workflowApprovalsTable = null,
    ) {
        $this->recommendationsTable = $recommendationsTable ?? $this->fetchTable('Awards.Recommendations');
        $this->approvalRunsTable = $approvalRunsTable ?? $this->fetchTable('Awards.RecommendationApprovalRuns');
        $this->workflowInstancesTable = $workflowInstancesTable ?? $this->fetchTable('WorkflowInstances');
        $this->workflowApprovalsTable = $workflowApprovalsTable ?? $this->fetchTable('WorkflowApprovals');
    }

    /**
     * Return active approval runs for recommendations, normalized to approval scope.
     *
     * @param array<int> $recommendationIds Recommendation IDs.
     * @param bool $includeChildren Include children for group heads.
     * @return array<int, \Awards\Model\Entity\RecommendationApprovalRun>
     */
    public function findActiveRuns(array $recommendationIds, bool $includeChildren = true): array
    {
        $scopeIds = $this->resolveApprovalScopeRecommendationIds($recommendationIds, $includeChildren);
        if ($scopeIds === []) {
            return [];
        }

        return $this->approvalRunsTable->find()
            ->where([
                'recommendation_id IN' => $scopeIds,
                'status IN' => self::ACTIVE_STATUSES,
                'deleted IS' => null,
            ])
            ->orderBy(['id' => 'ASC'])
            ->all()
            ->toArray();
    }

    /**
     * @param int $recommendationId Recommendation ID.
     * @return bool
     */
    public function hasActiveRun(int $recommendationId): bool
    {
        return $this->findActiveRuns([$recommendationId]) !== [];
    }

    /**
     * Return all recommendation IDs that share the same approval workflow scope.
     *
     * @param int $recommendationId Recommendation ID.
     * @param bool $includeChildren Include grouped children when scoped to a group head.
     * @return array<int>
     */
    public function approvalScopeRecommendationIds(int $recommendationId, bool $includeChildren = true): array
    {
        return $this->resolveApprovalScopeRecommendationIds([$recommendationId], $includeChildren);
    }

    /**
     * Return the latest approval run in the recommendation's approval scope.
     *
     * @param int $recommendationId Recommendation ID.
     * @return \Awards\Model\Entity\RecommendationApprovalRun|null
     */
    public function findLatestRun(int $recommendationId): ?RecommendationApprovalRun
    {
        $scopeIds = $this->resolveApprovalScopeRecommendationIds([$recommendationId], true);
        if ($scopeIds === []) {
            return null;
        }

        $run = $this->approvalRunsTable->find()
            ->where([
                'recommendation_id IN' => $scopeIds,
                'deleted IS' => null,
            ])
            ->orderByDesc('completed')
            ->orderByDesc('started')
            ->orderByDesc('id')
            ->first();

        return $run instanceof RecommendationApprovalRun ? $run : null;
    }

    /**
     * @param array<int> $recommendationIds Recommendation IDs.
     * @param string $operation Human-readable operation.
     * @return void
     */
    public function assertNoActiveRuns(array $recommendationIds, string $operation): void
    {
        if ($this->findActiveRuns($recommendationIds) !== []) {
            throw new RuntimeException(
                sprintf('Cannot %s while one or more recommendations are under active approval review.', $operation),
            );
        }
    }

    /**
     * Cancel active approval workflows because their recommendation was linked to a bestowal.
     *
     * @param array<int> $recommendationIds Recommendation IDs being linked.
     * @param int $bestowalId Bestowal ID.
     * @param int $actorId Actor performing the link.
     * @return array<int> Superseded run IDs.
     */
    public function supersedeActiveRunsForBestowalLink(
        array $recommendationIds,
        int $bestowalId,
        int $actorId,
    ): array {
        $runs = $this->findActiveRuns($recommendationIds);
        $runIds = [];
        foreach ($runs as $run) {
            $this->cancelWorkflowProjection(
                (int)$run->workflow_instance_id,
                RecommendationApprovalRun::TERMINAL_REASON_SUPERSEDED_BY_BESTOWAL_LINK,
            );
            $this->markRunTerminal(
                $run,
                RecommendationApprovalRun::STATUS_CANCELLED,
                RecommendationApprovalRun::TERMINAL_REASON_SUPERSEDED_BY_BESTOWAL_LINK,
                $actorId,
                supersededByBestowalId: $bestowalId,
            );
            $runIds[] = (int)$run->id;
        }

        return $runIds;
    }

    /**
     * Record that an approved run was consumed by a created bestowal.
     *
     * @param int $recommendationId Recommendation ID.
     * @param int $bestowalId Bestowal ID.
     * @param int $actorId Actor ID.
     * @return int|null Consumed run ID.
     */
    public function consumeLatestApprovedRunForBestowal(
        int $recommendationId,
        int $bestowalId,
        int $actorId,
    ): ?int {
        $run = $this->findLatestApprovedRun($recommendationId);
        if ($run === null) {
            return null;
        }

        $this->markRunTerminal(
            $run,
            RecommendationApprovalRun::STATUS_CONSUMED,
            RecommendationApprovalRun::TERMINAL_REASON_CONSUMED_BY_BESTOWAL,
            $actorId,
            consumedByBestowalId: $bestowalId,
        );

        return (int)$run->id;
    }

    /**
     * @param int $recommendationId Recommendation ID.
     * @return int|null
     */
    public function findLatestApprovedRunId(int $recommendationId): ?int
    {
        $run = $this->findLatestApprovedRun($recommendationId);

        return $run !== null ? (int)$run->id : null;
    }

    /**
     * Mark active runs cancelled before deleting a recommendation.
     *
     * @param array<int> $recommendationIds Recommendation IDs.
     * @param int|null $actorId Actor ID.
     * @return array<int> Cancelled run IDs.
     */
    public function cancelActiveRunsForRecommendationDeletion(array $recommendationIds, ?int $actorId): array
    {
        $runs = $this->findActiveRuns($recommendationIds);
        $runIds = [];
        foreach ($runs as $run) {
            $this->cancelWorkflowProjection(
                (int)$run->workflow_instance_id,
                RecommendationApprovalRun::TERMINAL_REASON_RECOMMENDATION_DELETED,
            );
            $this->markRunTerminal(
                $run,
                RecommendationApprovalRun::STATUS_CANCELLED,
                RecommendationApprovalRun::TERMINAL_REASON_RECOMMENDATION_DELETED,
                $actorId,
            );
            $runIds[] = (int)$run->id;
        }

        return $runIds;
    }

    /**
     * Cancel active approval workflows because the recommendation's award now uses another approval process.
     *
     * @param array<int> $recommendationIds Recommendation IDs.
     * @param int|null $actorId Actor ID.
     * @return array<int> Cancelled run IDs.
     */
    public function cancelActiveRunsForAwardChange(array $recommendationIds, ?int $actorId): array
    {
        $runs = $this->findActiveRuns($recommendationIds);
        $runIds = [];
        foreach ($runs as $run) {
            $this->cancelWorkflowProjection(
                (int)$run->workflow_instance_id,
                RecommendationApprovalRun::TERMINAL_REASON_AWARD_CHANGED,
            );
            $this->markRunTerminal(
                $run,
                RecommendationApprovalRun::STATUS_CANCELLED,
                RecommendationApprovalRun::TERMINAL_REASON_AWARD_CHANGED,
                $actorId,
            );
            $runIds[] = (int)$run->id;
        }

        return $runIds;
    }

    /**
     * Cancel active approval workflows that are superseded by grouping under another head.
     *
     * This intentionally queries direct recommendation IDs instead of approval scope so the
     * chosen head's workflow remains active while child workflows are cancelled.
     *
     * @param array<int> $recommendationIds Child recommendation IDs being linked to a group head.
     * @param int $headRecommendationId Group head recommendation ID.
     * @param int|null $actorId Actor ID.
     * @return array<int> Cancelled run IDs.
     */
    public function supersedeActiveRunsForGrouping(
        array $recommendationIds,
        int $headRecommendationId,
        ?int $actorId,
    ): array {
        $recommendationIds = array_values(array_diff(
            array_unique(array_filter(array_map('intval', $recommendationIds))),
            [$headRecommendationId],
        ));
        if ($recommendationIds === []) {
            return [];
        }

        $runs = $this->approvalRunsTable->find()
            ->where([
                'recommendation_id IN' => $recommendationIds,
                'status IN' => self::ACTIVE_STATUSES,
                'deleted IS' => null,
            ])
            ->orderBy(['id' => 'ASC'])
            ->all();

        $runIds = [];
        foreach ($runs as $run) {
            $this->cancelWorkflowProjection(
                (int)$run->workflow_instance_id,
                RecommendationApprovalRun::TERMINAL_REASON_SUPERSEDED_BY_GROUPING,
            );
            $this->markRunTerminal(
                $run,
                RecommendationApprovalRun::STATUS_CANCELLED,
                RecommendationApprovalRun::TERMINAL_REASON_SUPERSEDED_BY_GROUPING,
                $actorId,
            );
            $runIds[] = (int)$run->id;
        }

        return $runIds;
    }

    /**
     * Rehydrate approval workflows for recommendations whose prior approval was consumed or superseded.
     *
     * @param array<int> $recommendationIds Recommendation IDs.
     * @param int $actorId Actor ID.
     * @param string $reason Rehydration reason.
     * @return array<int, array<string, mixed>> Workflow dispatch results by recommendation ID.
     */
    public function rehydrateUnlinkedRecommendations(array $recommendationIds, int $actorId, string $reason): array
    {
        $recommendationIds = array_values(array_unique(array_filter(array_map('intval', $recommendationIds))));
        $results = [];
        foreach ($recommendationIds as $recommendationId) {
            if ($this->hasActiveRun($recommendationId)) {
                continue;
            }

            $previousRun = $this->findLatestRehydratableRun($recommendationId);
            if ($previousRun === null) {
                continue;
            }

            $event = new Event('Workflow.trigger', $this, [
                'eventName' => 'Awards.ExistingRecommendationApprovalRequested',
                'eventData' => [
                    'recommendationId' => $recommendationId,
                    'actorId' => $actorId,
                    'rehydratedFromRunId' => (int)$previousRun->id,
                    'rehydrationReason' => $reason,
                ],
                'triggeredBy' => $actorId,
            ]);
            EventManager::instance()->dispatch($event);
            $results[$recommendationId] = [
                'previousRunId' => (int)$previousRun->id,
                'reason' => $reason,
            ];
        }

        return $results;
    }

    /**
     * Cancel consumed/superseded run projections tied to a cancelled bestowal.
     *
     * @param int $bestowalId Bestowal ID.
     * @param int $actorId Actor ID.
     * @return array<int> Updated run IDs.
     */
    public function markRunsForBestowalCancellation(int $bestowalId, int $actorId): array
    {
        $runs = $this->approvalRunsTable->find()
            ->where([
                'OR' => [
                    'consumed_by_bestowal_id' => $bestowalId,
                    'superseded_by_bestowal_id' => $bestowalId,
                ],
                'deleted IS' => null,
            ])
            ->all();

        $runIds = [];
        foreach ($runs as $run) {
            $this->markRunTerminal(
                $run,
                RecommendationApprovalRun::STATUS_CANCELLED,
                RecommendationApprovalRun::TERMINAL_REASON_BESTOWAL_CANCELLED,
                $actorId,
            );
            $runIds[] = (int)$run->id;
        }

        return $runIds;
    }

    /**
     * @param int $recommendationId Recommendation ID.
     * @return \Awards\Model\Entity\RecommendationApprovalRun|null
     */
    private function findLatestApprovedRun(int $recommendationId): ?RecommendationApprovalRun
    {
        $scopeIds = $this->resolveApprovalScopeRecommendationIds([$recommendationId], true);
        if ($scopeIds === []) {
            return null;
        }

        $run = $this->approvalRunsTable->find()
            ->where([
                'recommendation_id IN' => $scopeIds,
                'status' => RecommendationApprovalRun::STATUS_APPROVED,
                'deleted IS' => null,
            ])
            ->orderByDesc('completed')
            ->orderByDesc('id')
            ->first();

        return $run instanceof RecommendationApprovalRun ? $run : null;
    }

    /**
     * @param int $recommendationId Recommendation ID.
     * @return \Awards\Model\Entity\RecommendationApprovalRun|null
     */
    private function findLatestRehydratableRun(int $recommendationId): ?RecommendationApprovalRun
    {
        $scopeIds = $this->resolveApprovalScopeRecommendationIds([$recommendationId], true);
        if ($scopeIds === []) {
            return null;
        }

        $run = $this->approvalRunsTable->find()
            ->where([
                'recommendation_id IN' => $scopeIds,
                'terminal_reason IN' => [
                    RecommendationApprovalRun::TERMINAL_REASON_CONSUMED_BY_BESTOWAL,
                    RecommendationApprovalRun::TERMINAL_REASON_SUPERSEDED_BY_BESTOWAL_LINK,
                    RecommendationApprovalRun::TERMINAL_REASON_BESTOWAL_CANCELLED,
                    RecommendationApprovalRun::TERMINAL_REASON_SUPERSEDED_BY_GROUPING,
                ],
                'deleted IS' => null,
            ])
            ->orderByDesc('completed')
            ->orderByDesc('id')
            ->first();

        return $run instanceof RecommendationApprovalRun ? $run : null;
    }

    /**
     * @param array<int> $recommendationIds Recommendation IDs.
     * @param bool $includeChildren Include children for group heads.
     * @return array<int>
     */
    private function resolveApprovalScopeRecommendationIds(array $recommendationIds, bool $includeChildren): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $recommendationIds))));
        if ($ids === []) {
            return [];
        }

        $recommendations = $this->recommendationsTable->find()
            ->select(['id', 'recommendation_group_id'])
            ->where(['id IN' => $ids])
            ->all();

        $scopeIds = [];
        foreach ($recommendations as $recommendation) {
            if (!$recommendation instanceof Recommendation) {
                continue;
            }
            $scopeIds[] = $recommendation->recommendation_group_id !== null
                ? (int)$recommendation->recommendation_group_id
                : (int)$recommendation->id;
        }

        if ($includeChildren && $scopeIds !== []) {
            $children = $this->recommendationsTable->find()
                ->select(['id'])
                ->where(['recommendation_group_id IN' => $scopeIds])
                ->all();
            foreach ($children as $child) {
                $scopeIds[] = (int)$child->id;
            }
        }

        return array_values(array_unique(array_filter($scopeIds)));
    }

    /**
     * Keep workflow runtime rows terminal without depending on recommendation state.
     *
     * @param int $workflowInstanceId Workflow instance ID.
     * @param string $reason Cancellation reason.
     * @return void
     */
    private function cancelWorkflowProjection(int $workflowInstanceId, string $reason): void
    {
        $instance = $this->workflowInstancesTable->get($workflowInstanceId);
        if ($instance instanceof WorkflowInstance && !$instance->isTerminal()) {
            $instance->status = WorkflowInstance::STATUS_CANCELLED;
            $instance->completed_at = DateTime::now();
            $errorInfo = $instance->error_info ?? [];
            $errorInfo['cancellation_reason'] = $reason;
            $instance->error_info = $errorInfo;
            $this->workflowInstancesTable->saveOrFail($instance);
        }

        $pendingApprovals = $this->workflowApprovalsTable->find()
            ->where([
                'workflow_instance_id' => $workflowInstanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->all();

        foreach ($pendingApprovals as $approval) {
            $approval->status = WorkflowApproval::STATUS_CANCELLED;
            $this->workflowApprovalsTable->saveOrFail($approval);
        }
    }

    /**
     * @param \Awards\Model\Entity\RecommendationApprovalRun $run Approval run.
     * @param string $status Terminal status.
     * @param string $reason Terminal reason.
     * @param int|null $actorId Actor ID.
     * @param int|null $consumedByBestowalId Consuming bestowal ID.
     * @param int|null $supersededByBestowalId Superseding bestowal ID.
     * @return void
     */
    private function markRunTerminal(
        RecommendationApprovalRun $run,
        string $status,
        string $reason,
        ?int $actorId,
        ?int $consumedByBestowalId = null,
        ?int $supersededByBestowalId = null,
    ): void {
        $run->status = $status;
        $run->current_step_key = null;
        $run->current_step_label = null;
        $run->terminal_reason = $reason;
        if ($run->completed === null) {
            $run->completed = DateTime::now();
        }
        if ($consumedByBestowalId !== null) {
            $run->consumed_by_bestowal_id = $consumedByBestowalId;
        }
        if ($supersededByBestowalId !== null) {
            $run->superseded_by_bestowal_id = $supersededByBestowalId;
        }
        $run->modified_by = $actorId;
        $this->approvalRunsTable->saveOrFail($run);
    }
}
