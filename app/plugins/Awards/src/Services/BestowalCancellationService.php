<?php
declare(strict_types=1);

namespace Awards\Services;

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

    private Table $bestowalsTable;
    private Table $recommendationsTable;
    private Table $bestowalRecommendationsTable;
    private BestowalRecommendationSyncService $syncService;
    private RecommendationApprovalWorkflowLifecycleService $approvalLifecycleService;

    /**
     * @param \Cake\ORM\Table|null $bestowalsTable Optional injected bestowals table.
     * @param \Cake\ORM\Table|null $recommendationsTable Optional injected recommendations table.
     * @param \Cake\ORM\Table|null $bestowalRecommendationsTable Optional injected join table.
     * @param \Awards\Services\BestowalRecommendationSyncService|null $syncService Optional injected sync service.
     * @param \Awards\Services\RecommendationApprovalWorkflowLifecycleService|null $approvalLifecycleService Optional lifecycle service.
     */
    public function __construct(
        ?Table $bestowalsTable = null,
        ?Table $recommendationsTable = null,
        ?Table $bestowalRecommendationsTable = null,
        ?BestowalRecommendationSyncService $syncService = null,
        ?RecommendationApprovalWorkflowLifecycleService $approvalLifecycleService = null,
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
                    $bestowal = $this->bestowalsTable->get($bestowalId, contain: ['Recommendations']);
                    $lifecycleStatus = (string)($bestowal->lifecycle_status ?? Bestowal::LIFECYCLE_OPEN);
                    if ($lifecycleStatus === Bestowal::LIFECYCLE_GIVEN) {
                        throw new RuntimeException('Given bestowals cannot be cancelled.');
                    }
                    if ($lifecycleStatus === Bestowal::LIFECYCLE_CANCELLED) {
                        throw new RuntimeException('Bestowal is already cancelled.');
                    }

                    $previousLifecycleStatus = $lifecycleStatus;
                    $bestowal->lifecycle_status = Bestowal::LIFECYCLE_CANCELLED;
                    $bestowal->close_reason = $normalizedReason;
                    $bestowal->modified_by = $actorId;
                    $this->bestowalsTable->saveOrFail($bestowal);

                    $recommendations = $this->resolveLinkedRecommendations($bestowal);
                    $recommendationIds = [];
                    foreach ($recommendations as $recommendation) {
                        $recommendation->bestowal_id = null;
                        $recommendation->gathering_id = null;
                        $recommendation->modified_by = $actorId;
                        $this->recommendationsTable->saveOrFail(
                            $recommendation,
                            ['systemSync' => true],
                        );
                        $recommendationIds[] = (int)$recommendation->id;
                    }

                    sort($recommendationIds);
                    if ($recommendationIds !== []) {
                        $this->bestowalRecommendationsTable->deleteAll([
                            'bestowal_id' => $bestowalId,
                            'recommendation_id IN' => $recommendationIds,
                        ]);
                    }
                    $cancelledRunIds = $this->approvalLifecycleService->markRunsForBestowalCancellation(
                        $bestowalId,
                        $actorId,
                    );
                    $rehydrated = $this->approvalLifecycleService->rehydrateUnlinkedRecommendations(
                        $recommendationIds,
                        $actorId,
                        RecommendationApprovalRun::TERMINAL_REASON_BESTOWAL_CANCELLED,
                    );

                    return [
                        'success' => true,
                        'data' => [
                            'bestowalId' => $bestowalId,
                            'recommendationIds' => $recommendationIds,
                            'unwindState' => null,
                            'closeReason' => $normalizedReason,
                            'cancelledApprovalRunIds' => $cancelledRunIds,
                            'rehydratedApprovals' => $rehydrated,
                            'eventName' => self::EVENT_NAME,
                            'eventPayload' => [
                                'bestowalId' => $bestowalId,
                                'recommendationIds' => $recommendationIds,
                                'closeReason' => $normalizedReason,
                                'unwindState' => null,
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
