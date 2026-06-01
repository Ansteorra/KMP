<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
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
    private BestowalTransitionService $transitionService;
    private BestowalRecommendationSyncService $syncService;

    /**
     * @param \Cake\ORM\Table|null $bestowalsTable Optional injected bestowals table.
     * @param \Cake\ORM\Table|null $recommendationsTable Optional injected recommendations table.
     * @param \Awards\Services\BestowalTransitionService|null $transitionService Optional injected transition service.
     * @param \Awards\Services\BestowalRecommendationSyncService|null $syncService Optional injected sync service.
     */
    public function __construct(
        ?Table $bestowalsTable = null,
        ?Table $recommendationsTable = null,
        ?BestowalTransitionService $transitionService = null,
        ?BestowalRecommendationSyncService $syncService = null,
    ) {
        $this->bestowalsTable = $bestowalsTable ?? $this->fetchTable('Awards.Bestowals');
        $this->recommendationsTable = $recommendationsTable ?? $this->fetchTable('Awards.Recommendations');
        $this->transitionService = $transitionService ?? new BestowalTransitionService();
        $this->syncService = $syncService ?? new BestowalRecommendationSyncService();
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
                    if ((string)$bestowal->state === 'Given') {
                        throw new RuntimeException('Given bestowals cannot be cancelled.');
                    }
                    if (!$bestowal->isActiveBestowal() && (string)$bestowal->state === 'Cancelled') {
                        throw new RuntimeException('Bestowal is already cancelled.');
                    }

                    $transitionResult = $this->transitionService->transition(
                        $this->bestowalsTable,
                        $bestowalId,
                        [
                            'targetState' => 'Cancelled',
                            'closeReason' => $normalizedReason,
                        ],
                        $actorId,
                    );
                    if (!($transitionResult['success'] ?? false)) {
                        throw new RuntimeException(
                            (string)($transitionResult['error'] ?? 'Failed to transition bestowal to Cancelled.'),
                        );
                    }

                    $unwindState = $this->syncService->resolveUnwindTargetStateName();
                    $recommendations = $this->resolveLinkedRecommendations($bestowal);
                    $recommendationIds = [];
                    foreach ($recommendations as $recommendation) {
                        if ($unwindState !== null) {
                            $this->syncService->applySystemRecommendationState(
                                $recommendation,
                                $unwindState,
                                $actorId,
                            );
                        }

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

                    return [
                        'success' => true,
                        'data' => [
                            'bestowalId' => $bestowalId,
                            'recommendationIds' => $recommendationIds,
                            'unwindState' => $unwindState,
                            'closeReason' => $normalizedReason,
                            'eventName' => self::EVENT_NAME,
                            'eventPayload' => [
                                'bestowalId' => $bestowalId,
                                'recommendationIds' => $recommendationIds,
                                'closeReason' => $normalizedReason,
                                'unwindState' => $unwindState,
                                'memberId' => (int)$bestowal->member_id,
                                'previousState' => $transitionResult['data']['result']['previousState'] ?? null,
                                'newState' => 'Cancelled',
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
