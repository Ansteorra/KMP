<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use RuntimeException;
use Throwable;

/**
 * Creates bestowals from approved recommendations and links grouped recommendations.
 */
class BestowalCreationService
{
    use BestowalNotesSupportTrait;
    use LocatorAwareTrait;

    public const EVENT_NAME = 'Awards.BestowalCreated';

    private Table $bestowalsTable;
    private Table $recommendationsTable;
    private Table $bestowalRecommendationsTable;
    private BestowalStateLogService $stateLogService;

    /**
     * @param \Cake\ORM\Table|null $bestowalsTable Optional injected bestowals table.
     * @param \Cake\ORM\Table|null $recommendationsTable Optional injected recommendations table.
     * @param \Cake\ORM\Table|null $bestowalRecommendationsTable Optional injected join table.
     * @param \Awards\Services\BestowalStateLogService|null $stateLogService Optional injected state-log service.
     */
    public function __construct(
        ?Table $bestowalsTable = null,
        ?Table $recommendationsTable = null,
        ?Table $bestowalRecommendationsTable = null,
        ?BestowalStateLogService $stateLogService = null,
    ) {
        $this->bestowalsTable = $bestowalsTable ?? $this->fetchTable('Awards.Bestowals');
        $this->recommendationsTable = $recommendationsTable ?? $this->fetchTable('Awards.Recommendations');
        $this->bestowalRecommendationsTable = $bestowalRecommendationsTable
            ?? $this->fetchTable('Awards.BestowalRecommendations');
        $this->stateLogService = $stateLogService ?? new BestowalStateLogService();
    }

    /**
     * Create a bestowal from a recommendation (or its group head) when eligible.
     *
     * @param int $recommendationId Recommendation ID that triggered creation.
     * @param int $actorId Current user ID.
     * @return array<string, mixed>
     */
    public function createFromRecommendation(int $recommendationId, int $actorId): array
    {
        if ($recommendationId <= 0) {
            return $this->failureResult('Recommendation ID must be greater than zero.');
        }

        try {
            return $this->withTransaction(function () use ($recommendationId, $actorId): array {
                $recommendation = $this->loadRecommendationForCreation($recommendationId);

                return $this->createFromLoadedRecommendation($recommendation, $actorId);
            });
        } catch (Throwable $e) {
            Log::error('Bestowal creation failed: ' . $e->getMessage());

            return $this->failureResult($e->getMessage());
        }
    }

    /**
     * Create a bestowal while the caller owns the surrounding transaction.
     *
     * @param int $recommendationId Recommendation ID that triggered creation.
     * @param int $actorId Current user ID.
     * @return array<string, mixed>
     */
    public function createFromRecommendationInCallerTransaction(int $recommendationId, int $actorId): array
    {
        if ($recommendationId <= 0) {
            return $this->failureResult('Recommendation ID must be greater than zero.');
        }

        try {
            $recommendation = $this->loadRecommendationForCreation($recommendationId);

            return $this->createFromLoadedRecommendation($recommendation, $actorId);
        } catch (Throwable $e) {
            Log::error('Bestowal creation failed: ' . $e->getMessage());

            return $this->failureResult($e->getMessage());
        }
    }

    /**
     * @param int $recommendationId Recommendation ID.
     * @return \Awards\Model\Entity\Recommendation
     */
    private function loadRecommendationForCreation(int $recommendationId): Recommendation
    {
        $recommendation = $this->recommendationsTable->get(
            $recommendationId,
            contain: [
                'GroupChildren',
                'Awards' => ['Levels'],
            ],
        );
        if (!$recommendation instanceof Recommendation) {
            throw new RuntimeException('Recommendation could not be loaded for bestowal creation.');
        }

        return $recommendation;
    }

    /**
     * @param \Awards\Model\Entity\Recommendation $recommendation Head or standalone recommendation.
     * @param int $actorId Current user ID.
     * @return array<string, mixed>
     */
    private function createFromLoadedRecommendation(Recommendation $recommendation, int $actorId): array
    {
        if ($recommendation->recommendation_group_id !== null) {
            return $this->skippedResult('Group child recommendations do not create bestowals.');
        }

        if ($recommendation->bestowal_id !== null) {
            return $this->skippedResult('Recommendation already has an active bestowal link.');
        }

        $groupRecommendations = $this->collectGroupRecommendations($recommendation);
        $skipReason = $this->resolveActiveBestowalSkipReason($groupRecommendations);
        if ($skipReason !== null) {
            return $this->skippedResult($skipReason);
        }

        $head = $this->chooseHead($groupRecommendations);
        $memberId = $head->member_id;
        if ($memberId === null) {
            return $this->failureResult('Recommendations must have a member before creating a bestowal.');
        }
        $awardId = (int)$head->award_id;
        if ($awardId <= 0) {
            return $this->failureResult('Recommendations must have a valid award before creating a bestowal.');
        }

        $bestowal = $this->bestowalsTable->newEmptyEntity();
        if (!$bestowal instanceof Bestowal) {
            throw new RuntimeException('Bestowal entity could not be initialized.');
        }
        $bestowal->member_id = (int)$memberId;
        $bestowal->primary_recommendation_id = (int)$head->id;
        $bestowal->source = Bestowal::SOURCE_RECOMMENDATION;
        $this->applyInitialBestowalState($bestowal, 'Created');
        $bestowal->gathering_id = $head->gathering_id;
        $bestowal->call_into_court = $head->call_into_court;
        $bestowal->court_availability = $head->court_availability;
        $bestowal->person_to_notify = $head->person_to_notify;
        $bestowal->noble_notes = $this->buildNobleNotes($groupRecommendations);
        $bestowal->herald_notes = $this->buildHeraldNotes(
            $groupRecommendations,
            (string)$head->member_sca_name,
        );
        $bestowal->created_by = $actorId;
        $bestowal->modified_by = $actorId;
        $bestowal->set('award_id', $awardId, ['guard' => false]);
        $bestowal->setDirty('award_id', true);

        $savedBestowal = $this->bestowalsTable->saveOrFail($bestowal);
        if (!$savedBestowal instanceof Bestowal) {
            throw new RuntimeException('Saved bestowal did not return a bestowal entity.');
        }
        $this->stateLogService->logStateTransition(
            (int)$savedBestowal->id,
            'New',
            (string)$savedBestowal->state,
            'New',
            $savedBestowal->status !== null ? (string)$savedBestowal->status : null,
            $actorId,
        );

        $recommendationIds = [];
        foreach ($groupRecommendations as $groupRecommendation) {
            $recommendationIds[] = (int)$groupRecommendation->id;

            $join = $this->bestowalRecommendationsTable->newEmptyEntity();
            $join->set('bestowal_id', (int)$savedBestowal->id);
            $join->set('recommendation_id', (int)$groupRecommendation->id);
            $this->bestowalRecommendationsTable->saveOrFail($join);

            $groupRecommendation->bestowal_id = (int)$savedBestowal->id;
            $groupRecommendation->modified_by = $actorId;
            $this->recommendationsTable->saveOrFail($groupRecommendation, ['systemSync' => true]);
        }

        sort($recommendationIds);
        $eventPayload = $this->buildEventPayload($savedBestowal, $recommendationIds, $head);

        return [
            'success' => true,
            'skipped' => false,
            'data' => [
                'bestowalId' => (int)$savedBestowal->id,
                'recommendationIds' => $recommendationIds,
                'eventName' => self::EVENT_NAME,
                'eventPayload' => $eventPayload,
            ],
        ];
    }

    /**
     * Run a callback inside a transaction unless one is already active.
     *
     * @param callable():mixed $callback Work to execute.
     * @return mixed
     */
    private function withTransaction(callable $callback): mixed
    {
        $connection = $this->bestowalsTable->getConnection();
        if ($connection->inTransaction()) {
            return $callback();
        }

        return $connection->transactional($callback);
    }

    /**
     * Collect the head and all grouped children for a recommendation.
     *
     * @param \Awards\Model\Entity\Recommendation $recommendation Group head or standalone recommendation.
     * @return array<int, \Awards\Model\Entity\Recommendation>
     */
    private function collectGroupRecommendations(Recommendation $recommendation): array
    {
        $recommendations = [$recommendation];
        foreach ($recommendation->group_children ?? [] as $child) {
            $recommendations[] = $child;
        }

        return $recommendations;
    }

    /**
     * Determine whether an active bestowal already exists for the recommendation group.
     *
     * @param array<int, \Awards\Model\Entity\Recommendation> $recommendations Group recommendations.
     * @return string|null Skip reason when an active bestowal exists.
     */
    private function resolveActiveBestowalSkipReason(array $recommendations): ?string
    {
        foreach ($recommendations as $recommendation) {
            if ($recommendation->bestowal_id === null) {
                continue;
            }

            try {
                $bestowal = $this->bestowalsTable->get((int)$recommendation->bestowal_id);
            } catch (Throwable) {
                continue;
            }

            if (!$bestowal instanceof Bestowal) {
                continue;
            }

            if ($bestowal->isActiveBestowal()) {
                return 'An active bestowal already exists for this recommendation group.';
            }
        }

        return null;
    }

    /**
     * Choose the grouping head using the same preference rules as recommendation grouping.
     *
     * @param array<int, \Awards\Model\Entity\Recommendation> $recommendations Group recommendations.
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

        $head = $recommendations[0] ?? null;
        if (!$head instanceof Recommendation) {
            throw new RuntimeException('At least one recommendation is required to create a bestowal.');
        }

        return $head;
    }

    /**
     * Apply the initial bestowal state without requiring pre-cached state machine data.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity being created.
     * @param string $state Initial workflow state name.
     * @return void
     */
    private function applyInitialBestowalState(Bestowal $bestowal, string $state): void
    {
        try {
            $bestowal->state = $state;
        } catch (Throwable) {
            $bestowal->set('status', 'Planning', ['guard' => false]);
            $bestowal->set('state', $state, ['guard' => false]);
            $bestowal->state_date = new DateTime();
        }
    }

    /**
     * Build the reaction workflow payload for a newly created bestowal.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Saved bestowal entity.
     * @param array<int, int> $recommendationIds Linked recommendation IDs.
     * @param \Awards\Model\Entity\Recommendation $head Primary recommendation.
     * @return array<string, mixed>
     */
    private function buildEventPayload(Bestowal $bestowal, array $recommendationIds, Recommendation $head): array
    {
        return [
            'bestowalId' => (int)$bestowal->id,
            'recommendationIds' => $recommendationIds,
            'primaryRecommendationId' => (int)$head->id,
            'memberId' => (int)$bestowal->member_id,
            'memberScaName' => (string)$head->member_sca_name,
            'gatheringId' => $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null,
            'status' => (string)$bestowal->status,
            'state' => (string)$bestowal->state,
            'source' => (string)$bestowal->source,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function skippedResult(string $reason): array
    {
        return [
            'success' => true,
            'skipped' => true,
            'data' => [
                'bestowalId' => null,
                'recommendationIds' => [],
                'eventName' => null,
                'eventPayload' => null,
                'reason' => $reason,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failureResult(string $error): array
    {
        return [
            'success' => false,
            'skipped' => false,
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
