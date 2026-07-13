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
 * Synchronizes bestowal-owned recommendation projections from the bestowal lifecycle.
 */
class BestowalRecommendationSyncService
{
    use LocatorAwareTrait;

    private Table $bestowalsTable;
    private Table $recommendationsTable;

    /**
     * @param \Cake\ORM\Table|null $bestowalsTable Optional injected bestowals table.
     * @param \Cake\ORM\Table|null $recommendationsTable Optional injected recommendations table.
     */
    public function __construct(
        ?Table $bestowalsTable = null,
        ?Table $recommendationsTable = null,
    ) {
        $this->bestowalsTable = $bestowalsTable ?? $this->fetchTable('Awards.Bestowals');
        $this->recommendationsTable = $recommendationsTable ?? $this->fetchTable('Awards.Recommendations');
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

                    $syncedIds = [];
                    foreach ($recommendations as $recommendation) {
                        $updated = false;

                        if ($this->syncRecommendationGatheringFromBestowal($bestowal, $recommendation, $actorId)) {
                            $updated = true;
                        }

                        if ($this->syncRecommendationGivenFromBestowal($bestowal, $recommendation, $actorId)) {
                            $updated = true;
                        }

                        if ($this->syncRecommendationLifecycleFromBestowal($bestowal, $recommendation, $actorId)) {
                            $updated = true;
                        }

                        if ($updated) {
                            $syncedIds[] = (int)$recommendation->id;
                        }
                    }

                    sort($syncedIds);

                    return [
                        'success' => true,
                        'skipped' => $syncedIds === [],
                        'data' => [
                            'bestowalId' => $bestowalId,
                            'recommendationIds' => $syncedIds,
                            'targetState' => null,
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
     * Advance a linked recommendation's status/state to match the bestowal lifecycle.
     *
     * Once a recommendation is converted, the board state lives on the bestowal:
     * an open bestowal without a gathering means "Scheduling / Need to Schedule",
     * an open bestowal with a gathering means "To Give / Scheduled", and a given
     * bestowal means "Closed / Given". Closed recommendations and the manual
     * "Announced Not Given" board state are never overwritten by an open bestowal.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Source bestowal.
     * @param \Awards\Model\Entity\Recommendation $recommendation Linked recommendation to update.
     * @param int $actorId Actor ID for audit logging.
     * @return bool True when the recommendation status/state was updated.
     */
    public function syncRecommendationLifecycleFromBestowal(
        Bestowal $bestowal,
        Recommendation $recommendation,
        int $actorId,
    ): bool {
        $lifecycle = (string)($bestowal->lifecycle_status ?? Bestowal::LIFECYCLE_OPEN);
        if ($lifecycle === Bestowal::LIFECYCLE_GIVEN) {
            $targetStatus = 'Closed';
            $targetState = 'Given';
        } elseif ($lifecycle === Bestowal::LIFECYCLE_OPEN) {
            if ((string)$recommendation->status === 'Closed') {
                return false;
            }
            if (
                (string)$recommendation->status === 'To Give'
                && (string)$recommendation->state === 'Announced Not Given'
            ) {
                return false;
            }
            if ($bestowal->gathering_id !== null) {
                $targetStatus = 'To Give';
                $targetState = 'Scheduled';
            } else {
                $targetStatus = 'Scheduling';
                $targetState = 'Need to Schedule';
            }
        } else {
            return false;
        }

        if (
            (string)$recommendation->status === $targetStatus
            && (string)$recommendation->state === $targetState
        ) {
            return false;
        }

        $recommendation->status = $targetStatus;
        $recommendation->state = $targetState;
        $recommendation->state_date = DateTime::now();
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
}
