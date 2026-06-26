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

    /**
     * @param \Awards\Services\BestowalRecommendationSyncService|null $syncService Optional sync service.
     * @param \Awards\Services\BestowalRecommendationLinkService|null $linkService Optional link service.
     */
    public function __construct(
        ?BestowalRecommendationSyncService $syncService = null,
        ?BestowalRecommendationLinkService $linkService = null,
    ) {
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
