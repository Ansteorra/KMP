<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use InvalidArgumentException;

/**
 * Encapsulates recommendation grouping mechanics and origin-state restoration.
 */
class RecommendationGroupingService
{
    use LocatorAwareTrait;

    /**
     * @var array<int, string>
     */
    private const LINKED_STATES = ['Linked', 'Linked - Closed'];

    private Table $recommendationsTable;
    private Table $approvalRunsTable;
    private Table $workflowApprovalsTable;
    private RecommendationStateLogService $stateLogService;
    private RecommendationApprovalWorkflowLifecycleService $approvalLifecycleService;

    /**
     * @param \Cake\ORM\Table|null $recommendationsTable Optional injected recommendations table.
     * @param \Awards\Services\RecommendationStateLogService|null $stateLogService Optional injected state-log service.
     * @param \Cake\ORM\Table|null $approvalRunsTable Optional injected approval runs table.
     * @param \Cake\ORM\Table|null $workflowApprovalsTable Optional injected workflow approvals table.
     * @param \Awards\Services\RecommendationApprovalWorkflowLifecycleService|null $approvalLifecycleService Optional lifecycle service.
     */
    public function __construct(
        ?Table $recommendationsTable = null,
        ?RecommendationStateLogService $stateLogService = null,
        ?Table $approvalRunsTable = null,
        ?Table $workflowApprovalsTable = null,
        ?RecommendationApprovalWorkflowLifecycleService $approvalLifecycleService = null,
    ) {
        $this->recommendationsTable = $recommendationsTable ?? $this->fetchTable('Awards.Recommendations');
        $this->stateLogService = $stateLogService ?? new RecommendationStateLogService();
        $this->approvalRunsTable = $approvalRunsTable ?? $this->fetchTable('Awards.RecommendationApprovalRuns');
        $this->workflowApprovalsTable = $workflowApprovalsTable ?? $this->fetchTable('WorkflowApprovals');
        $this->approvalLifecycleService = $approvalLifecycleService
            ?? new RecommendationApprovalWorkflowLifecycleService(
                recommendationsTable: $this->recommendationsTable,
                approvalRunsTable: $this->approvalRunsTable,
                workflowApprovalsTable: $this->workflowApprovalsTable,
            );
    }

    /**
     * Group recommendations under a shared head and snapshot each child origin state.
     *
     * @param array<int, int|string> $recommendationIds Recommendation IDs to group.
     * @param int|null $actorId Current user ID.
     * @return \Awards\Model\Entity\Recommendation
     */
    public function groupRecommendations(array $recommendationIds, ?int $actorId = null): Recommendation
    {
        $ids = array_values(array_unique(array_map('intval', $recommendationIds)));
        if (count($ids) < 2) {
            throw new InvalidArgumentException('At least 2 recommendations are required to group.');
        }

        $this->assertGroupingPermitted($ids);

        return $this->withTransaction(function () use ($ids, $actorId): Recommendation {
            $recommendations = $this->recommendationsTable->find()
                ->where(['Recommendations.id IN' => $ids])
                ->contain(['GroupChildren'])
                ->toArray();

            if (count($recommendations) !== count($ids)) {
                throw new InvalidArgumentException('One or more recommendations could not be found.');
            }

            $this->assertCompatibleMembers($recommendations);

            $head = $this->chooseHead($recommendations);
            $targetLinkedState = $this->determineLinkedStateForHead($head);
            $childIds = $this->collectChildIdsForGrouping($recommendations, (int)$head->id);
            $this->approvalLifecycleService->supersedeActiveRunsForGrouping(
                $childIds,
                (int)$head->id,
                $actorId,
            );

            foreach ($recommendations as $recommendation) {
                if ((int)$recommendation->id === (int)$head->id) {
                    if ($recommendation->recommendation_group_id !== null) {
                        $recommendation->recommendation_group_id = null;
                        $this->clearOriginSnapshot($recommendation);
                        $this->applyActor($recommendation, $actorId);
                        $this->recommendationsTable->saveOrFail($recommendation);
                    }
                    continue;
                }

                if (!empty($recommendation->group_children)) {
                    foreach ($recommendation->group_children as $groupChild) {
                        if ((int)$groupChild->id === (int)$head->id) {
                            continue;
                        }
                        $this->linkRecommendationToHead(
                            $groupChild,
                            (int)$head->id,
                            $targetLinkedState,
                            $actorId,
                            preserveExistingOrigin: true,
                        );
                    }
                }

                $this->linkRecommendationToHead(
                    $recommendation,
                    (int)$head->id,
                    $targetLinkedState,
                    $actorId,
                );
            }

            return $this->recommendationsTable->get((int)$head->id, contain: ['GroupChildren']);
        });
    }

    /**
     * Ungroup all children from the supplied head.
     *
     * @param int $headId Group head recommendation ID.
     * @param int|null $actorId Current user ID.
     * @return array<int, \Awards\Model\Entity\Recommendation>
     */
    public function ungroupRecommendations(int $headId, ?int $actorId = null): array
    {
        $this->assertNoActiveApprovalRuns(
            [$headId],
            includeChildren: true,
            operation: 'ungroup these recommendations',
        );

        return $this->withTransaction(function () use ($headId, $actorId): array {
            $head = $this->recommendationsTable->get($headId, contain: ['GroupChildren']);
            if (empty($head->group_children)) {
                throw new InvalidArgumentException('This recommendation has no grouped children.');
            }

            $restored = [];
            foreach ($head->group_children as $child) {
                $restored[] = $this->restoreRecommendationToOrigin($child, $actorId);
            }

            return $restored;
        });
    }

    /**
     * Remove a single child from its group and auto-restore the final child.
     *
     * @param int $childId Child recommendation ID.
     * @param int|null $actorId Current user ID.
     * @return int Former head recommendation ID.
     */
    public function removeFromGroup(int $childId, ?int $actorId = null): int
    {
        /** @var \Awards\Model\Entity\Recommendation $child */
        $child = $this->recommendationsTable->get($childId, contain: []);
        if ($child->recommendation_group_id === null) {
            throw new InvalidArgumentException('This recommendation is not part of a group.');
        }

        $this->assertNoActiveApprovalRuns(
            [(int)$child->recommendation_group_id],
            includeChildren: true,
            operation: 'remove a recommendation from a group',
        );

        return $this->withTransaction(function () use ($childId, $actorId): int {
            /** @var \Awards\Model\Entity\Recommendation $child */
            $child = $this->recommendationsTable->get($childId);
            if ($child->recommendation_group_id === null) {
                throw new InvalidArgumentException('This recommendation is not part of a group.');
            }

            $headId = (int)$child->recommendation_group_id;
            $this->restoreRecommendationToOrigin($child, $actorId);

            $remainingCount = $this->recommendationsTable->find()
                ->where(['recommendation_group_id' => $headId])
                ->count();

            if ($remainingCount === 1) {
                /** @var \Awards\Model\Entity\Recommendation $lastChild */
                $lastChild = $this->recommendationsTable->find()
                    ->where(['recommendation_group_id' => $headId])
                    ->firstOrFail();
                $this->restoreRecommendationToOrigin($lastChild, $actorId);
            }

            return $headId;
        });
    }

    /**
     * Restore children when a group head is deleted.
     *
     * @param \Awards\Model\Entity\Recommendation|int $head Group head entity or ID.
     * @param int|null $actorId Current user ID.
     * @return array<int, \Awards\Model\Entity\Recommendation>
     */
    public function restoreChildrenForDeletedHead(Recommendation|int $head, ?int $actorId = null): array
    {
        $headId = $head instanceof Recommendation ? (int)$head->id : $head;

        return $this->withTransaction(function () use ($headId, $actorId): array {
            $children = $this->recommendationsTable->find()
                ->where(['recommendation_group_id' => $headId])
                ->all();

            $restored = [];
            foreach ($children as $child) {
                $restored[] = $this->restoreRecommendationToOrigin($child, $actorId);
            }

            return $restored;
        });
    }

    /**
     * Keep linked children aligned with the current head open/closed state.
     *
     * @param \Awards\Model\Entity\Recommendation|int $head Group head entity or ID.
     * @param int|null $actorId Current user ID.
     * @return int Number of children updated.
     */
    public function syncLinkedChildrenState(Recommendation|int $head, ?int $actorId = null): int
    {
        $headEntity = $head instanceof Recommendation
            ? $head
            : $this->recommendationsTable->get($head);

        return $this->withTransaction(function () use ($headEntity, $actorId): int {
            $children = $this->recommendationsTable->find()
                ->where([
                    'recommendation_group_id' => $headEntity->id,
                    'Recommendations.state IN' => self::LINKED_STATES,
                ])
                ->all();

            $targetState = $this->determineLinkedStateForHead($headEntity);
            $updated = 0;
            foreach ($children as $child) {
                if ($child->state === $targetState) {
                    continue;
                }

                $beforeState = (string)$child->state;
                $beforeStatus = (string)$child->status;
                $child->state = $targetState;
                $this->applyActor($child, $actorId);
                $saved = $this->recommendationsTable->saveOrFail($child);
                $this->stateLogService->logStateTransition(
                    (int)$saved->id,
                    $beforeState,
                    (string)$saved->state,
                    $beforeStatus,
                    $saved->status !== null ? (string)$saved->status : null,
                    $actorId,
                );
                $updated++;
            }

            return $updated;
        });
    }

    /**
     * Restore one recommendation to the origin snapshot captured at grouping time.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Child recommendation.
     * @param int|null $actorId Current user ID.
     * @return \Awards\Model\Entity\Recommendation
     */
    private function restoreRecommendationToOrigin(Recommendation $recommendation, ?int $actorId = null): Recommendation
    {
        $origin = $this->resolveOriginSnapshot($recommendation);
        $beforeState = (string)$recommendation->state;
        $beforeStatus = (string)$recommendation->status;

        $recommendation->recommendation_group_id = null;
        $recommendation->state = $origin['state'];
        $this->clearOriginSnapshot($recommendation);
        $this->applyActor($recommendation, $actorId);

        $saved = $this->recommendationsTable->saveOrFail($recommendation);
        $this->stateLogService->logStateTransition(
            (int)$saved->id,
            $beforeState,
            (string)$saved->state,
            $beforeStatus,
            $saved->status !== null ? (string)$saved->status : ($origin['status'] ?? null),
            $actorId,
        );
        if ($actorId !== null) {
            $this->approvalLifecycleService->rehydrateUnlinkedRecommendations(
                [(int)$saved->id],
                $actorId,
                RecommendationApprovalRun::TERMINAL_REASON_SUPERSEDED_BY_GROUPING,
            );
        }

        return $saved;
    }

    /**
     * Link a recommendation to the supplied head and capture its pre-group state.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation to link.
     * @param int $headId Group head ID.
     * @param string $targetState Linked state to apply.
     * @param int|null $actorId Current user ID.
     * @param bool $preserveExistingOrigin Keep a previously captured origin snapshot.
     * @return \Awards\Model\Entity\Recommendation
     */
    private function linkRecommendationToHead(
        Recommendation $recommendation,
        int $headId,
        string $targetState,
        ?int $actorId = null,
        bool $preserveExistingOrigin = false,
    ): Recommendation {
        if (!$preserveExistingOrigin || $recommendation->group_origin_state === null) {
            $this->captureOriginSnapshot($recommendation);
        }

        $beforeState = (string)$recommendation->state;
        $beforeStatus = (string)$recommendation->status;
        $recommendation->recommendation_group_id = $headId;
        $recommendation->state = $targetState;
        $this->applyActor($recommendation, $actorId);

        $saved = $this->recommendationsTable->saveOrFail($recommendation);
        $this->stateLogService->logStateTransition(
            (int)$saved->id,
            $beforeState,
            (string)$saved->state,
            $beforeStatus,
            $saved->status !== null ? (string)$saved->status : null,
            $actorId,
        );

        return $saved;
    }

    /**
     * Snapshot the child's current non-linked state for future restoration.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation being grouped.
     * @return void
     */
    private function captureOriginSnapshot(Recommendation $recommendation): void
    {
        if ($recommendation->group_origin_state !== null) {
            return;
        }

        $originState = (string)$recommendation->state;
        $originStatus = (string)$recommendation->status;

        if ($this->isLinkedState($originState)) {
            $legacyOrigin = $this->findLegacyOriginSnapshot((int)$recommendation->id);
            if ($legacyOrigin !== null) {
                $originState = $legacyOrigin['state'];
                $originStatus = $legacyOrigin['status'];
            }
        }

        $recommendation->group_origin_state = $originState;
        $recommendation->group_origin_status = $originStatus;
    }

    /**
     * Resolve the best available origin snapshot for a grouped child.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Grouped child.
     * @return array{state: string, status: string|null}
     */
    private function resolveOriginSnapshot(Recommendation $recommendation): array
    {
        if (!empty($recommendation->group_origin_state)) {
            return [
                'state' => (string)$recommendation->group_origin_state,
                'status' => $recommendation->group_origin_status !== null
                    ? (string)$recommendation->group_origin_status
                    : null,
            ];
        }

        $legacyOrigin = $this->findLegacyOriginSnapshot((int)$recommendation->id);
        if ($legacyOrigin !== null) {
            return $legacyOrigin;
        }

        return ['state' => 'Submitted', 'status' => null];
    }

    /**
     * Find the most recent non-linked origin for a legacy grouped child.
     *
     * @param int $recommendationId Recommendation ID.
     * @return array{state: string, status: string|null}|null
     */
    private function findLegacyOriginSnapshot(int $recommendationId): ?array
    {
        if ($recommendationId <= 0) {
            return null;
        }

        $stateLogsTable = $this->fetchTable('Awards.RecommendationsStatesLogs');
        $log = $stateLogsTable->find()
            ->select(['from_state', 'from_status'])
            ->where([
                'recommendation_id' => $recommendationId,
                'to_state IN' => self::LINKED_STATES,
                'from_state NOT IN' => self::LINKED_STATES,
            ])
            ->orderBy(['created' => 'DESC', 'id' => 'DESC'])
            ->first();

        if ($log === null || empty($log->from_state) || $log->from_state === 'New') {
            return null;
        }

        return [
            'state' => (string)$log->from_state,
            'status' => !empty($log->from_status) ? (string)$log->from_status : null,
        ];
    }

    /**
     * Choose the grouping head using the existing controller preference rules.
     *
     * @param array<int, \Awards\Model\Entity\Recommendation> $recommendations Selected recommendations.
     * @return \Awards\Model\Entity\Recommendation
     */
    private function chooseHead(array $recommendations): Recommendation
    {
        foreach ($recommendations as $recommendation) {
            if ($recommendation->recommendation_group_id === null && !empty($recommendation->group_children)) {
                return $recommendation;
            }
        }

        usort(
            $recommendations,
            fn(Recommendation $left, Recommendation $right): int => (int)$left->id <=> (int)$right->id,
        );

        return $recommendations[0];
    }

    /**
     * @param array<int, \Awards\Model\Entity\Recommendation> $recommendations Selected recommendations.
     * @param int $headId Chosen group head ID.
     * @return array<int>
     */
    private function collectChildIdsForGrouping(array $recommendations, int $headId): array
    {
        $childIds = [];
        foreach ($recommendations as $recommendation) {
            if ((int)$recommendation->id !== $headId) {
                $childIds[] = (int)$recommendation->id;
            }
            foreach ($recommendation->group_children ?? [] as $groupChild) {
                if ((int)$groupChild->id !== $headId) {
                    $childIds[] = (int)$groupChild->id;
                }
            }
        }

        return array_values(array_unique($childIds));
    }

    /**
     * Assert that the selected recommendations are still groupable.
     *
     * @param array<int> $ids Recommendation IDs to check.
     * @throws \InvalidArgumentException when grouping is blocked by archived records.
     */
    private function assertGroupingPermitted(array $ids): void
    {
        $archivedStates = Recommendation::getStatuses()['Closed'] ?? [];
        $archiveConditions = [
            'Bestowals.lifecycle_status' => Bestowal::LIFECYCLE_GIVEN,
        ];
        if ($archivedStates !== []) {
            $archiveConditions[] = ['Recommendations.state IN' => $archivedStates];
        }

        $archivedCount = $this->recommendationsTable->find()
            ->leftJoinWith('Bestowals')
            ->where([
                'Recommendations.id IN' => $ids,
                'OR' => $archiveConditions,
            ])
            ->count();

        if ($archivedCount === 0) {
            return;
        }

        throw new InvalidArgumentException('Archived recommendations cannot be grouped.');
    }

    /**
     * Assert that the supplied recommendations (and optionally children) have no active approval runs.
     *
     * @param array<int> $ids Recommendation IDs to check.
     * @param bool $includeChildren Also check group children of each supplied ID.
     * @param string $operation Human-readable operation description.
     * @return void
     */
    private function assertNoActiveApprovalRuns(
        array $ids,
        bool $includeChildren,
        string $operation,
    ): void {
        $activeRuns = $this->findActiveApprovalRuns($ids, $includeChildren);
        if ($activeRuns !== []) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot %s while one or more recommendations in the group are under active approval review.',
                    $operation,
                ),
            );
        }
    }

    /**
     * Find active approval runs for supplied recommendation IDs.
     *
     * @param array<int> $ids Recommendation IDs to check.
     * @param bool $includeChildren Also check group children of each supplied ID.
     * @return array<\Awards\Model\Entity\RecommendationApprovalRun>
     */
    private function findActiveApprovalRuns(array $ids, bool $includeChildren = false): array
    {
        $checkIds = $ids;

        if ($includeChildren) {
            $children = $this->recommendationsTable->find()
                ->select(['id'])
                ->where(['recommendation_group_id IN' => $ids])
                ->all();
            foreach ($children as $child) {
                $checkIds[] = (int)$child->id;
            }
            $checkIds = array_values(array_unique($checkIds));
        }

        if ($checkIds === []) {
            return [];
        }

        /** @var array<\Awards\Model\Entity\RecommendationApprovalRun> $activeRuns */
        return $this->approvalLifecycleService->findActiveRuns($checkIds, $includeChildren);
    }

    /**
     * Validate that grouped recommendations point at the same member or no member.
     *
     * @param array<int, \Awards\Model\Entity\Recommendation> $recommendations Selected recommendations.
     * @return void
     */
    private function assertCompatibleMembers(array $recommendations): void
    {
        $memberIds = array_unique(
            array_filter(
                array_map(
                    static fn(Recommendation $recommendation): ?int => $recommendation->member_id,
                    $recommendations,
                ),
                static fn(?int $memberId): bool => $memberId !== null,
            ),
        );

        if (count($memberIds) > 1) {
            throw new InvalidArgumentException(
                'Recommendations with different members cannot be grouped together.',
            );
        }
    }

    /**
     * Convert a head recommendation into the appropriate linked child state.
     *
     * @param \Awards\Model\Entity\Recommendation $head Group head.
     * @return string
     */
    private function determineLinkedStateForHead(Recommendation $head): string
    {
        $closedStates = Recommendation::getStatuses()['Closed'] ?? [];
        $headIsClosed = $head->status === 'Closed' || in_array((string)$head->state, $closedStates, true);

        return $headIsClosed ? 'Linked - Closed' : 'Linked';
    }

    /**
     * Clear any persisted origin snapshot after a child leaves a group.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation being cleared.
     * @return void
     */
    private function clearOriginSnapshot(Recommendation $recommendation): void
    {
        $recommendation->group_origin_state = null;
        $recommendation->group_origin_status = null;
    }

    /**
     * Apply the actor ID to a recommendation so downstream logs are attributed.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation being saved.
     * @param int|null $actorId Current user ID.
     * @return void
     */
    private function applyActor(Recommendation $recommendation, ?int $actorId): void
    {
        if ($actorId !== null) {
            $recommendation->modified_by = $actorId;
        }
    }

    /**
     * Determine whether a recommendation is already in a synthetic linked state.
     *
     * @param string $state State name.
     * @return bool
     */
    private function isLinkedState(string $state): bool
    {
        return in_array($state, self::LINKED_STATES, true);
    }

    /**
     * Run a callback inside a transaction unless one is already active.
     *
     * @param callable():mixed $callback Work to execute.
     * @return mixed
     */
    private function withTransaction(callable $callback): mixed
    {
        $connection = $this->recommendationsTable->getConnection();
        if (method_exists($connection, 'inTransaction') && $connection->inTransaction()) {
            return $callback();
        }

        return $connection->transactional($callback);
    }
}
