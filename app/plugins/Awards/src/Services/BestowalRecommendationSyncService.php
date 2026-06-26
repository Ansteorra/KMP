<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

/**
 * Synchronizes linked recommendation states from the bestowal lifecycle status.
 */
class BestowalRecommendationSyncService
{
    use LocatorAwareTrait;

    /**
     * Recommendation state a linked recommendation moves to when its bestowal is given.
     */
    public const RECOMMENDATION_GIVEN_STATE = 'Given';

    /**
     * Recommendation state a linked recommendation unwinds to when its bestowal is cancelled or unlinked.
     */
    public const RECOMMENDATION_UNWIND_STATE = 'King Approved';

    private Table $bestowalsTable;
    private Table $recommendationsTable;
    private RecommendationStateLogService $recommendationStateLogService;

    /**
     * @param \Cake\ORM\Table|null $bestowalsTable Optional injected bestowals table.
     * @param \Cake\ORM\Table|null $recommendationsTable Optional injected recommendations table.
     * @param \Awards\Services\RecommendationStateLogService|null $recommendationStateLogService Optional injected rec state-log service.
     */
    public function __construct(
        ?Table $bestowalsTable = null,
        ?Table $recommendationsTable = null,
        ?RecommendationStateLogService $recommendationStateLogService = null,
    ) {
        $this->bestowalsTable = $bestowalsTable ?? $this->fetchTable('Awards.Bestowals');
        $this->recommendationsTable = $recommendationsTable ?? $this->fetchTable('Awards.Recommendations');
        $this->recommendationStateLogService = $recommendationStateLogService
            ?? new RecommendationStateLogService();
    }

    /**
     * Sync linked recommendations to the mapped state for the bestowal's current state.
     *
     * @param int $bestowalId Bestowal ID.
     * @param int $actorId Actor performing the sync.
     * @return array<string, mixed>
     */
    public function syncFromBestowal(int $bestowalId, int $actorId): array
    {
        if ($bestowalId <= 0) {
            return [
                'success' => false,
                'error' => 'Bestowal ID must be greater than zero.',
                'data' => [
                    'bestowalId' => null,
                    'recommendationIds' => [],
                    'targetState' => null,
                    'syncedCount' => 0,
                ],
            ];
        }

        try {
            return $this->bestowalsTable->getConnection()->transactional(
                function () use ($bestowalId, $actorId): array {
                    $bestowal = $this->bestowalsTable->get($bestowalId, contain: ['Recommendations']);
                    $recommendations = $this->resolveLinkedRecommendations($bestowal);
                    if ($recommendations === []) {
                        return [
                            'success' => true,
                            'data' => [
                                'bestowalId' => $bestowalId,
                                'recommendationIds' => [],
                                'targetState' => null,
                                'syncedCount' => 0,
                            ],
                        ];
                    }

                    $targetStateName = $this->resolveSyncTargetStateName(
                        (string)($bestowal->lifecycle_status ?? ''),
                    );
                    $syncedIds = [];
                    foreach ($recommendations as $recommendation) {
                        $updated = false;

                        if (
                            $targetStateName !== null
                            && (string)$recommendation->state !== $targetStateName
                        ) {
                            $recommendation = $this->applySystemRecommendationState(
                                $recommendation,
                                $targetStateName,
                                $actorId,
                            );
                            $updated = true;
                        }

                        if ($this->syncRecommendationGatheringFromBestowal($bestowal, $recommendation, $actorId)) {
                            $updated = true;
                        }

                        if ($this->syncRecommendationGivenFromBestowal($bestowal, $recommendation, $actorId)) {
                            $updated = true;
                        }

                        if ($updated) {
                            $syncedIds[] = (int)$recommendation->id;
                        }
                    }

                    sort($syncedIds);

                    return [
                        'success' => true,
                        'skipped' => $targetStateName === null && $syncedIds === [],
                        'data' => [
                            'bestowalId' => $bestowalId,
                            'recommendationIds' => $syncedIds,
                            'targetState' => $targetStateName,
                            'syncedCount' => count($syncedIds),
                        ],
                    ];
                },
            );
        } catch (Throwable $e) {
            Log::error('Bestowal recommendation sync failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [
                    'bestowalId' => $bestowalId,
                    'recommendationIds' => [],
                    'targetState' => null,
                    'syncedCount' => 0,
                ],
            ];
        }
    }

    /**
     * Resolve the recommendation state name a linked recommendation should move to
     * for the supplied bestowal lifecycle status.
     *
     * @param string $lifecycleStatus Bestowal lifecycle status (open|given|cancelled).
     * @return string|null Recommendation state name or null when no sync applies.
     */
    public function resolveSyncTargetStateName(string $lifecycleStatus): ?string
    {
        return $lifecycleStatus === Bestowal::LIFECYCLE_GIVEN
            ? self::RECOMMENDATION_GIVEN_STATE
            : null;
    }

    /**
     * Resolve the recommendation state name a linked recommendation unwinds to when its
     * bestowal is cancelled or the link is removed.
     *
     * @return string|null Recommendation state name to unwind to.
     */
    public function resolveUnwindTargetStateName(): ?string
    {
        return self::RECOMMENDATION_UNWIND_STATE;
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
     * Copy the bestowal gathering onto a linked recommendation when it differs.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Source bestowal.
     * @param \Awards\Model\Entity\Recommendation $recommendation Linked recommendation to update.
     * @param int $actorId Actor ID for audit logging.
     * @return bool True when the recommendation gathering was updated.
     */
    public function syncRecommendationGatheringFromBestowal(
        Bestowal $bestowal,
        Recommendation $recommendation,
        int $actorId,
    ): bool {
        $bestowalGatheringId = $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null;
        $recommendationGatheringId = $recommendation->gathering_id !== null
            ? (int)$recommendation->gathering_id
            : null;

        if ($bestowalGatheringId === $recommendationGatheringId) {
            return false;
        }

        if (
            $bestowalGatheringId !== null
            && !Recommendation::supportsGatheringAssignmentForState((string)$recommendation->state)
        ) {
            return false;
        }

        $recommendation->gathering_id = $bestowalGatheringId;
        $recommendation->modified_by = $actorId;
        $this->recommendationsTable->saveOrFail(
            $recommendation,
            ['systemSync' => true],
        );

        return true;
    }

    /**
     * Copy the bestowal bestowed date onto a linked recommendation's given date when it differs.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Source bestowal.
     * @param \Awards\Model\Entity\Recommendation $recommendation Linked recommendation to update.
     * @param int $actorId Actor ID for audit logging.
     * @return bool True when the recommendation given date was updated.
     */
    public function syncRecommendationGivenFromBestowal(
        Bestowal $bestowal,
        Recommendation $recommendation,
        int $actorId,
    ): bool {
        if ((string)$recommendation->state !== 'Given') {
            return false;
        }

        $targetGiven = $this->normalizeGivenDate($bestowal->bestowed_at);
        $currentGiven = $this->normalizeGivenDate($recommendation->given);
        if ($this->givenDatesMatch($targetGiven, $currentGiven)) {
            return false;
        }

        $recommendation->given = $targetGiven;
        $recommendation->modified_by = $actorId;
        $this->recommendationsTable->saveOrFail(
            $recommendation,
            ['systemSync' => true],
        );

        return true;
    }

    /**
     * @param \DateTimeInterface|\Cake\I18n\DateTime|null $value Bestowed or given date.
     * @return \Cake\I18n\DateTime|null
     */
    private function normalizeGivenDate(?DateTimeInterface $value): ?DateTime
    {
        if ($value === null) {
            return null;
        }

        return new DateTime(
            $value->format('Y-m-d') . ' 00:00:00',
            new DateTimeZone('UTC'),
        );
    }

    /**
     * @param \Cake\I18n\DateTime|null $left Normalized given date.
     * @param \Cake\I18n\DateTime|null $right Normalized given date.
     * @return bool
     */
    private function givenDatesMatch(?DateTime $left, ?DateTime $right): bool
    {
        if ($left === null && $right === null) {
            return true;
        }

        if ($left === null || $right === null) {
            return false;
        }

        return $left->format('Y-m-d') === $right->format('Y-m-d');
    }

    /**
     * Apply a recommendation state change as a system sync operation.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation to update.
     * @param string $targetState Target recommendation state.
     * @param int $actorId Actor ID for audit logging.
     * @return \Awards\Model\Entity\Recommendation
     */
    public function applySystemRecommendationState(
        Recommendation $recommendation,
        string $targetState,
        int $actorId,
    ): Recommendation {
        $beforeState = (string)$recommendation->state;
        $beforeStatus = (string)$recommendation->status;

        $recommendation->state = $targetState;
        $recommendation->modified_by = $actorId;

        $saved = $this->recommendationsTable->saveOrFail(
            $recommendation,
            ['systemSync' => true],
        );

        $this->recommendationStateLogService->logStateTransition(
            (int)$saved->id,
            $beforeState,
            (string)$saved->state,
            $beforeStatus,
            $saved->status !== null ? (string)$saved->status : null,
            $actorId,
        );

        return $saved;
    }
}
