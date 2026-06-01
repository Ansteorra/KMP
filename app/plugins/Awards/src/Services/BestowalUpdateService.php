<?php
declare(strict_types=1);

namespace Awards\Services;

use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use RuntimeException;
use Throwable;

/**
 * Applies bestowal edit form updates including link changes and state transitions.
 */
class BestowalUpdateService
{
    use LocatorAwareTrait;

    private BestowalTransitionService $transitionService;
    private BestowalRecommendationSyncService $syncService;
    private BestowalRecommendationLinkService $linkService;

    /**
     * @param \Awards\Services\BestowalTransitionService|null $transitionService Optional transition service.
     * @param \Awards\Services\BestowalRecommendationSyncService|null $syncService Optional sync service.
     * @param \Awards\Services\BestowalRecommendationLinkService|null $linkService Optional link service.
     */
    public function __construct(
        ?BestowalTransitionService $transitionService = null,
        ?BestowalRecommendationSyncService $syncService = null,
        ?BestowalRecommendationLinkService $linkService = null,
    ) {
        $this->transitionService = $transitionService ?? new BestowalTransitionService();
        $this->syncService = $syncService ?? new BestowalRecommendationSyncService();
        $this->linkService = $linkService ?? new BestowalRecommendationLinkService(
            syncService: $this->syncService,
        );
    }

    /**
     * Update a bestowal from edit form data.
     *
     * @param \Cake\ORM\Table $bestowalsTable Bestowals table.
     * @param int $bestowalId Bestowal ID.
     * @param array<string, mixed> $data Form data.
     * @param int $actorId Actor performing the update.
     * @return array<string, mixed>
     */
    public function update(Table $bestowalsTable, int $bestowalId, array $data, int $actorId): array
    {
        if ($bestowalId <= 0) {
            return $this->failureResult('Bestowal ID must be greater than zero.');
        }

        try {
            return $bestowalsTable->getConnection()->transactional(
                function () use ($bestowalsTable, $bestowalId, $data, $actorId): array {
                    $bestowal = $bestowalsTable->get($bestowalId);
                    $unlinkIds = $this->normalizeIdList($data['unlink_recommendation_ids'] ?? []);
                    $linkIds = $this->normalizeIdList($data['link_recommendation_ids'] ?? []);

                    $this->linkService->assertMinimumLinkedRecommendations(
                        $bestowalId,
                        $unlinkIds,
                        $linkIds,
                    );

                    if ($linkIds !== []) {
                        $this->linkService->linkRecommendations($bestowalId, $linkIds, $actorId);
                    }
                    if ($unlinkIds !== []) {
                        $this->linkService->unlinkRecommendations($bestowalId, $unlinkIds, $actorId);
                    }

                    $targetState = trim((string)(
                        $data['state']
                        ?? $data['newState']
                        ?? $data['targetState']
                        ?? $bestowal->state
                    ));
                    if ($targetState === '') {
                        throw new RuntimeException('Target state is required.');
                    }

                    $awardId = $data['award_id'] ?? null;
                    if ($awardId === null || $awardId === '') {
                        throw new RuntimeException('Award to Bestow is required.');
                    }

                    $transitionData = [
                        'targetState' => $targetState,
                        'newState' => $targetState,
                        'award_id' => (int)$awardId,
                        'gathering_id' => $data['gathering_id'] ?? null,
                        'gathering_scheduled_activity_id' => $data['gathering_scheduled_activity_id'] ?? null,
                        'bestowed_at' => $data['bestowed_at'] ?? null,
                        'noble_notes' => $data['noble_notes'] ?? null,
                        'herald_notes' => $data['herald_notes'] ?? null,
                        'close_reason' => $data['close_reason'] ?? null,
                        'note' => $data['note'] ?? null,
                    ];

                    $transitionResult = $this->transitionService->transition(
                        $bestowalsTable,
                        $bestowalId,
                        $transitionData,
                        $actorId,
                    );
                    if (!($transitionResult['success'] ?? false)) {
                        throw new RuntimeException(
                            (string)($transitionResult['error'] ?? 'Bestowal transition failed.'),
                        );
                    }

                    $syncResult = $this->syncService->syncFromBestowal($bestowalId, $actorId);
                    if (!($syncResult['success'] ?? false)) {
                        throw new RuntimeException(
                            (string)($syncResult['error'] ?? 'Recommendation sync failed.'),
                        );
                    }

                    return [
                        'success' => true,
                        'data' => [
                            'bestowalId' => $bestowalId,
                            'result' => $transitionResult['data']['result'] ?? null,
                            'unlinkedRecommendationIds' => $unlinkIds,
                            'linkedRecommendationIds' => $linkIds,
                            'sync' => $syncResult['data'] ?? [],
                        ],
                    ];
                },
            );
        } catch (Throwable $e) {
            Log::error('Bestowal update failed: ' . $e->getMessage());

            return $this->failureResult($e->getMessage());
        }
    }

    /**
     * Bulk transition bestowals and sync linked recommendations.
     *
     * @param \Cake\ORM\Table $bestowalsTable Bestowals table.
     * @param array<string, mixed> $data Bulk transition data including ids and target state.
     * @param int $actorId Actor performing the update.
     * @return array<string, mixed>
     */
    public function bulkTransition(Table $bestowalsTable, array $data, int $actorId): array
    {
        try {
            return $bestowalsTable->getConnection()->transactional(
                function () use ($bestowalsTable, $data, $actorId): array {
                    $transitionResult = $this->transitionService->transitionMany(
                        $bestowalsTable,
                        $data,
                        $actorId,
                    );
                    if (!($transitionResult['success'] ?? false)) {
                        throw new RuntimeException(
                            (string)($transitionResult['error'] ?? 'Bulk bestowal transition failed.'),
                        );
                    }

                    $bestowalIds = $transitionResult['data']['bestowalIds'] ?? [];
                    foreach ($bestowalIds as $bestowalId) {
                        $syncResult = $this->syncService->syncFromBestowal((int)$bestowalId, $actorId);
                        if (!($syncResult['success'] ?? false)) {
                            throw new RuntimeException(
                                (string)($syncResult['error'] ?? 'Recommendation sync failed.'),
                            );
                        }
                    }

                    return $transitionResult;
                },
            );
        } catch (Throwable $e) {
            Log::error('Bestowal bulk transition failed: ' . $e->getMessage());

            return $this->failureResult($e->getMessage());
        }
    }

    /**
     * @param mixed $value Raw ID list from form data.
     * @return array<int>
     */
    private function normalizeIdList(mixed $value): array
    {
        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $value))));
    }

    /**
     * @param string $error Error message.
     * @return array<string, mixed>
     */
    private function failureResult(string $error): array
    {
        return [
            'success' => false,
            'error' => $error,
            'data' => [
                'bestowalId' => null,
                'processedCount' => 0,
            ],
        ];
    }
}
