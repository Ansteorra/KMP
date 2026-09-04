<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\ActionItem;
use App\Services\ActionItems\ActionItemService;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use RuntimeException;
use Throwable;

/**
 * Cancels an in-flight bestowal and unwinds linked recommendations.
 */
class BestowalCancellationService
{
    use LocatorAwareTrait;

    public const EVENT_NAME = 'Awards.BestowalCancelled';
    public const RECONSIDERATION_STATE = 'Submitted';
    public const TODO_CANCELLATION_NOTE = 'Bestowal cancelled for complete reconsideration.';

    private Table $bestowalsTable;
    private Table $recommendationsTable;
    private Table $bestowalRecommendationsTable;
    private BestowalRecommendationSyncService $syncService;
    private RecommendationApprovalWorkflowLifecycleService $approvalLifecycleService;
    private ActionItemService $actionItemService;
    private RecommendationStateLogService $stateLogService;

    /**
     * @param \Cake\ORM\Table|null $bestowalsTable Optional injected bestowals table.
     * @param \Cake\ORM\Table|null $recommendationsTable Optional injected recommendations table.
     * @param \Cake\ORM\Table|null $bestowalRecommendationsTable Optional injected join table.
     * @param \Awards\Services\BestowalRecommendationSyncService|null $syncService Optional injected sync service.
     * @param \Awards\Services\RecommendationApprovalWorkflowLifecycleService|null $approvalLifecycleService Optional lifecycle service.
     * @param \App\Services\ActionItems\ActionItemService|null $actionItemService Optional action-item service.
     * @param \Awards\Services\RecommendationStateLogService|null $stateLogService Optional state-log service.
     */
    public function __construct(
        ?Table $bestowalsTable = null,
        ?Table $recommendationsTable = null,
        ?Table $bestowalRecommendationsTable = null,
        ?BestowalRecommendationSyncService $syncService = null,
        ?RecommendationApprovalWorkflowLifecycleService $approvalLifecycleService = null,
        ?ActionItemService $actionItemService = null,
        ?RecommendationStateLogService $stateLogService = null,
    ) {
        $this->bestowalsTable = $bestowalsTable ?? $this->fetchTable('Awards.Bestowals');
        $this->recommendationsTable = $recommendationsTable ?? $this->fetchTable('Awards.Recommendations');
        $this->bestowalRecommendationsTable = $bestowalRecommendationsTable
            ?? $this->fetchTable('Awards.BestowalRecommendations');
        $this->syncService = $syncService ?? new BestowalRecommendationSyncService();
        $this->approvalLifecycleService = $approvalLifecycleService
            ?? new RecommendationApprovalWorkflowLifecycleService(
                recommendationsTable: $this->recommendationsTable,
            );
        $this->actionItemService = $actionItemService ?? new ActionItemService();
        $this->stateLogService = $stateLogService ?? new RecommendationStateLogService();
    }

    /**
     * Cancel a bestowal, unwind linked recommendations, and clear bestowal links.
     *
     * @param int $bestowalId Bestowal ID.
     * @param int $actorId Actor performing the cancellation.
     * @param string $closeReason Required cancellation reason.
     * @return array<string, mixed>
     */
    public function cancel(int $bestowalId, int $actorId, string $closeReason): array
    {
        $normalizedReason = trim($closeReason);
        if ($bestowalId <= 0) {
            return $this->failureResult('Bestowal ID must be greater than zero.');
        }
        if ($normalizedReason === '') {
            return $this->failureResult('Close reason is required to cancel a bestowal.');
        }

        try {
            return $this->bestowalsTable->getConnection()->transactional(
                function () use ($bestowalId, $actorId, $normalizedReason): array {
                    $bestowal = $this->bestowalsTable->find()
                        ->where(['Bestowals.id' => $bestowalId])
                        ->contain(['Recommendations'])
                        ->epilog('FOR UPDATE')
                        ->firstOrFail();
                    $lifecycleStatus = (string)($bestowal->lifecycle_status ?? Bestowal::LIFECYCLE_OPEN);
                    if ($lifecycleStatus === Bestowal::LIFECYCLE_GIVEN) {
                        throw new RuntimeException('Given bestowals cannot be cancelled.');
                    }
                    if ($lifecycleStatus === Bestowal::LIFECYCLE_CANCELLED) {
                        throw new RuntimeException('Bestowal is already cancelled.');
                    }

                    $previousLifecycleStatus = $lifecycleStatus;
                    $cancelledTodoIds = $this->cancelOpenTodos($bestowalId, $actorId);

                    $recommendations = $this->resolveLinkedRecommendations($bestowal);
                    $recommendationIds = [];
                    $approvalScopeRecommendationIds = [];
                    foreach ($recommendations as $recommendation) {
                        $beforeState = (string)$recommendation->state;
                        $beforeStatus = (string)$recommendation->status;
                        $recommendation->bestowal_id = null;
                        $recommendation->gathering_id = null;
                        $recommendation->given = null;
                        $recommendation->close_reason = null;
                        $recommendation->state = $recommendation->recommendation_group_id === null
                            ? self::RECONSIDERATION_STATE
                            : 'Linked';
                        $recommendation->modified_by = $actorId;
                        $savedRecommendation = $this->recommendationsTable->saveOrFail(
                            $recommendation,
                            ['systemSync' => true],
                        );
                        $this->stateLogService->logStateTransition(
                            (int)$savedRecommendation->id,
                            $beforeState,
                            (string)$savedRecommendation->state,
                            $beforeStatus,
                            $savedRecommendation->status !== null ? (string)$savedRecommendation->status : null,
                            $actorId,
                        );
                        $recommendationIds[] = (int)$recommendation->id;
                        $approvalScopeRecommendationIds[] = $recommendation->recommendation_group_id !== null
                            ? (int)$recommendation->recommendation_group_id
                            : (int)$recommendation->id;
                    }

                    sort($recommendationIds);
                    $approvalScopeRecommendationIds = array_values(array_unique($approvalScopeRecommendationIds));
                    sort($approvalScopeRecommendationIds);
                    if ($recommendationIds !== []) {
                        $this->bestowalRecommendationsTable->deleteAll([
                            'bestowal_id' => $bestowalId,
                            'recommendation_id IN' => $recommendationIds,
                        ]);
                    }
                    $cancelledRunIds = $this->approvalLifecycleService->markRunsForBestowalCancellation(
                        $bestowalId,
                        $actorId,
                        $recommendationIds,
                    );
                    $restartedApprovals = $this->approvalLifecycleService->restartUnlinkedRecommendations(
                        $approvalScopeRecommendationIds,
                        $actorId,
                        RecommendationApprovalRun::TERMINAL_REASON_BESTOWAL_CANCELLED,
                    );

                    $bestowal->lifecycle_status = Bestowal::LIFECYCLE_CANCELLED;
                    $bestowal->close_reason = $normalizedReason;
                    $bestowal->modified_by = $actorId;
                    $this->bestowalsTable->saveOrFail($bestowal);

                    return [
                        'success' => true,
                        'data' => [
                            'bestowalId' => $bestowalId,
                            'recommendationIds' => $recommendationIds,
                            'approvalScopeRecommendationIds' => $approvalScopeRecommendationIds,
                            'unwindState' => self::RECONSIDERATION_STATE,
                            'closeReason' => $normalizedReason,
                            'cancelledTodoIds' => $cancelledTodoIds,
                            'cancelledApprovalRunIds' => $cancelledRunIds,
                            'restartedApprovals' => $restartedApprovals,
                            'rehydratedApprovals' => $restartedApprovals,
                            'eventName' => self::EVENT_NAME,
                            'eventPayload' => [
                                'bestowalId' => $bestowalId,
                                'recommendationIds' => $recommendationIds,
                                'closeReason' => $normalizedReason,
                                'unwindState' => self::RECONSIDERATION_STATE,
                                'memberId' => $bestowal->member_id !== null ? (int)$bestowal->member_id : null,
                                'previousState' => $previousLifecycleStatus,
                                'newState' => Bestowal::LIFECYCLE_CANCELLED,
                            ],
                        ],
                    ];
                },
            );
        } catch (Throwable $e) {
            Log::error('Bestowal cancellation failed: ' . $e->getMessage());

            return $this->failureResult($e->getMessage());
        }
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal with optional recommendations contain.
     * @return array<int, \Awards\Model\Entity\Recommendation>
     */
    private function resolveLinkedRecommendations(Bestowal $bestowal): array
    {
        if (!empty($bestowal->recommendations)) {
            return $bestowal->recommendations;
        }

        return $this->recommendationsTable->find()
            ->where(['bestowal_id' => (int)$bestowal->id])
            ->all()
            ->toList();
    }

    /**
     * Audit-cancel every open to-do while the locked bestowal still permits mutations.
     *
     * @param int $bestowalId Bestowal ID.
     * @param int $actorId Actor performing the cancellation.
     * @return array<int> Cancelled action-item IDs.
     */
    private function cancelOpenTodos(int $bestowalId, int $actorId): array
    {
        $cancelledIds = [];
        $items = $this->actionItemService->getItemsForEntity(
            Bestowal::ACTION_ITEM_ENTITY_TYPE,
            $bestowalId,
        );
        foreach ($items as $item) {
            if (!$item instanceof ActionItem || !$item->isOpen()) {
                continue;
            }

            $result = $this->actionItemService->cancel(
                (int)$item->id,
                $actorId,
                self::TODO_CANCELLATION_NOTE,
                false,
            );
            if (!$result->isSuccess()) {
                throw new RuntimeException('Open bestowal to-dos could not be cancelled.');
            }
            $cancelledIds[] = (int)$item->id;
        }

        return $cancelledIds;
    }

    /**
     * @return array<string, mixed>
     */
    private function failureResult(string $error): array
    {
        return [
            'success' => false,
            'error' => $error,
            'data' => [
                'bestowalId' => null,
                'recommendationIds' => [],
                'eventName' => null,
                'eventPayload' => null,
            ],
        ];
    }
}
