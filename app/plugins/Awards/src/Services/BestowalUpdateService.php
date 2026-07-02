<?php
declare(strict_types=1);

namespace Awards\Services;

use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use RuntimeException;
use Throwable;

/**
 * Applies bestowal edit form updates including link changes and field updates.
 */
class BestowalUpdateService
{
    use LocatorAwareTrait;

    private BestowalRecommendationSyncService $syncService;
    private BestowalRecommendationLinkService $linkService;
    private BestowalGatheringLookupService $gatheringLookupService;

    /**
     * @param \Awards\Services\BestowalRecommendationSyncService|null $syncService Optional sync service.
     * @param \Awards\Services\BestowalRecommendationLinkService|null $linkService Optional link service.
     */
    public function __construct(
        ?BestowalRecommendationSyncService $syncService = null,
        ?BestowalRecommendationLinkService $linkService = null,
        ?BestowalGatheringLookupService $gatheringLookupService = null,
    ) {
        $this->syncService = $syncService ?? new BestowalRecommendationSyncService();
        $this->linkService = $linkService ?? new BestowalRecommendationLinkService(
            syncService: $this->syncService,
        );
        $this->gatheringLookupService = $gatheringLookupService ?? new BestowalGatheringLookupService();
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

                    $awardId = $data['award_id'] ?? null;
                    if ($awardId === null || $awardId === '') {
                        throw new RuntimeException('Award to Bestow is required.');
                    }

                    $bestowal->set('award_id', (int)$awardId, ['guard' => false]);
                    $bestowal->setDirty('award_id', true);

                    $editableFields = [
                        'gathering_id',
                        'gathering_scheduled_activity_id',
                        'bestowed_at',
                        'specialty',
                        'noble_notes',
                        'herald_notes',
                        'reason_summary',
                        'close_reason',
                    ];
                    foreach ($editableFields as $field) {
                        if (array_key_exists($field, $data)) {
                            $value = $data[$field];
                            $bestowal->set($field, $value === '' ? null : $value, ['guard' => false]);
                            $bestowal->setDirty($field, true);
                        }
                    }
                    $bestowal->set('modified_by', $actorId, ['guard' => false]);
                    $bestowalsTable->saveOrFail($bestowal);

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
                            'result' => ['lifecycleStatus' => $bestowal->lifecycle_status],
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
     * Assign a gathering using the same guarded update path as the edit form.
     *
     * @param \Cake\ORM\Table $bestowalsTable Bestowals table.
     * @param int $bestowalId Bestowal ID.
     * @param int $gatheringId Gathering ID.
     * @param int $actorId Actor performing the update.
     * @param bool $futureOnly When true, only future gatherings are selectable.
     * @return array<string, mixed>
     */
    public function assignGathering(
        Table $bestowalsTable,
        int $bestowalId,
        int $gatheringId,
        int $actorId,
        bool $futureOnly = true,
    ): array {
        if ($bestowalId <= 0 || $gatheringId <= 0) {
            return $this->failureResult('A valid bestowal and gathering are required.');
        }

        try {
            return $bestowalsTable->getConnection()->transactional(
                function () use ($bestowalsTable, $bestowalId, $gatheringId, $actorId, $futureOnly): array {
                    $bestowal = $bestowalsTable->find()
                        ->where(['Bestowals.id' => $bestowalId])
                        ->contain([
                            'Recommendations' => function ($query) {
                                return $query->select(['id', 'award_id', 'member_id', 'bestowal_id']);
                            },
                        ])
                        ->first();
                    if ($bestowal === null) {
                        throw new RuntimeException('Bestowal not found.');
                    }
                    if (!$bestowal->isActiveBestowal()) {
                        throw new RuntimeException('Only open bestowals can be assigned to a gathering.');
                    }
                    if ($bestowal->award_id === null) {
                        throw new RuntimeException('Award to Bestow is required.');
                    }
                    if (
                        !$this->gatheringLookupService->isGatheringSelectableForBestowal(
                            $bestowal,
                            $gatheringId,
                            $futureOnly,
                        )
                    ) {
                        $message = $futureOnly
                            ? 'Select a valid future gathering for this bestowal.'
                            : 'Select a valid gathering for this bestowal.';
                        throw new RuntimeException($message);
                    }

                    $currentGatheringId = $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null;
                    if ($currentGatheringId !== $gatheringId) {
                        $bestowal->set('gathering_id', $gatheringId, ['guard' => false]);
                        $bestowal->setDirty('gathering_id', true);
                        $bestowal->set('gathering_scheduled_activity_id', null, ['guard' => false]);
                        $bestowal->setDirty('gathering_scheduled_activity_id', true);
                        $bestowal->set('modified_by', $actorId, ['guard' => false]);
                        $bestowalsTable->saveOrFail($bestowal);
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
                            'result' => ['lifecycleStatus' => $bestowal->lifecycle_status],
                            'sync' => $syncResult['data'] ?? [],
                        ],
                    ];
                },
            );
        } catch (Throwable $e) {
            Log::error('Bestowal gathering assignment failed: ' . $e->getMessage());

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
