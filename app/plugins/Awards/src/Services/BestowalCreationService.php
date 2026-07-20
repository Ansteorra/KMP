<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
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
    private BestowalTodoMaterializationService $todoMaterializationService;

    /**
     * @param \Cake\ORM\Table|null $bestowalsTable Optional injected bestowals table.
     * @param \Cake\ORM\Table|null $recommendationsTable Optional injected recommendations table.
     * @param \Cake\ORM\Table|null $bestowalRecommendationsTable Optional injected join table.
     * @param \Awards\Services\BestowalTodoMaterializationService|null $todoMaterializationService Optional to-do service.
     */
    public function __construct(
        ?Table $bestowalsTable = null,
        ?Table $recommendationsTable = null,
        ?Table $bestowalRecommendationsTable = null,
        ?BestowalTodoMaterializationService $todoMaterializationService = null,
    ) {
        $this->bestowalsTable = $bestowalsTable ?? $this->fetchTable('Awards.Bestowals');
        $this->recommendationsTable = $recommendationsTable ?? $this->fetchTable('Awards.Recommendations');
        $this->bestowalRecommendationsTable = $bestowalRecommendationsTable
            ?? $this->fetchTable('Awards.BestowalRecommendations');
        $this->todoMaterializationService = $todoMaterializationService ?? new BestowalTodoMaterializationService();
    }

    /**
     * Create a bestowal from a recommendation (or its group head) when eligible.
     *
     * @param int $recommendationId Recommendation ID that triggered creation.
     * @param int $actorId Current user ID.
     * @param int|null $gatheringId Optional gathering override selected during approval.
     * @return array<string, mixed>
     */
    public function createFromRecommendation(int $recommendationId, int $actorId, ?int $gatheringId = null): array
    {
        if ($recommendationId <= 0) {
            return $this->failureResult('Recommendation ID must be greater than zero.');
        }

        try {
            return $this->withTransaction(function () use ($recommendationId, $actorId, $gatheringId): array {
                $recommendation = $this->loadRecommendationForCreation($recommendationId);

                return $this->createFromLoadedRecommendation($recommendation, $actorId, $gatheringId);
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
     * @param int|null $gatheringId Optional gathering override selected during approval.
     * @return array<string, mixed>
     */
    public function createFromRecommendationInCallerTransaction(
        int $recommendationId,
        int $actorId,
        ?int $gatheringId = null,
    ): array {
        if ($recommendationId <= 0) {
            return $this->failureResult('Recommendation ID must be greater than zero.');
        }

        try {
            $recommendation = $this->loadRecommendationForCreation($recommendationId);

            return $this->createFromLoadedRecommendation($recommendation, $actorId, $gatheringId);
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
                'GroupChildren' => [
                    'Awards' => ['Levels'],
                    'Requesters',
                ],
                'Awards' => ['Levels'],
                'Requesters',
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
     * @param int|null $gatheringId Optional gathering override selected during approval.
     * @return array<string, mixed>
     */
    private function createFromLoadedRecommendation(
        Recommendation $recommendation,
        int $actorId,
        ?int $gatheringId = null,
    ): array {
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

        $bestowalResult = $this->createBestowalForRecommendations($groupRecommendations, $actorId, $gatheringId);
        $bestowal = $bestowalResult['bestowal'] ?? null;
        if (!$bestowal instanceof Bestowal) {
            throw new RuntimeException('Bestowal creation did not produce a bestowal.');
        }

        $bestowalIds = [(int)$bestowal->id];
        $recommendationIds = $bestowalResult['recommendationIds'];
        $eventPayload = $bestowalResult['eventPayload'];
        sort($recommendationIds);

        return [
            'success' => true,
            'skipped' => false,
            'data' => [
                'bestowalId' => (int)$bestowal->id,
                'bestowalIds' => $bestowalIds,
                'recommendationIds' => $recommendationIds,
                'eventName' => self::EVENT_NAME,
                'eventPayload' => $eventPayload,
                'eventPayloads' => [$eventPayload],
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
     * @param array<int, \Awards\Model\Entity\Recommendation> $recommendations Recommendations for one member/award.
     * @param int $actorId Current user ID.
     * @param int|null $gatheringId Optional gathering override selected during approval.
     * @return array{
     *   bestowal: \Awards\Model\Entity\Bestowal,
     *   recommendationIds: array<int>,
     *   eventPayload: array<string,mixed>
     * }
     */
    private function createBestowalForRecommendations(
        array $recommendations,
        int $actorId,
        ?int $gatheringId = null,
    ): array {
        $head = $this->chooseHead($recommendations);
        $resolvedGatheringId = $gatheringId ?? ($head->gathering_id !== null ? (int)$head->gathering_id : null);
        $bestowal = $this->bestowalsTable->newEmptyEntity();
        if (!$bestowal instanceof Bestowal) {
            throw new RuntimeException('Bestowal entity could not be initialized.');
        }
        $bestowal->member_id = $head->member_id !== null ? (int)$head->member_id : null;
        $bestowal->member_sca_name = (string)$head->member_sca_name;
        $bestowal->primary_recommendation_id = (int)$head->id;
        $bestowal->source = Bestowal::SOURCE_RECOMMENDATION;
        $bestowal->lifecycle_status = Bestowal::LIFECYCLE_OPEN;
        $bestowal->gathering_id = $resolvedGatheringId;
        $bestowal->call_into_court = $head->call_into_court;
        $bestowal->court_availability = $head->court_availability;
        $bestowal->person_to_notify = $head->person_to_notify;
        $bestowal->specialty = $this->buildSpecialtySummary($recommendations);
        $bestowal->noble_notes = $this->buildNobleNotes($recommendations);
        $bestowal->herald_notes = $this->buildHeraldNotes(
            $recommendations,
            (string)$head->member_sca_name,
        );
        $bestowal->reason_summary = $this->buildReasonSummary($recommendations);
        $bestowal->created_by = $actorId;
        $bestowal->modified_by = $actorId;
        $bestowal->set('award_id', (int)$head->award_id, ['guard' => false]);
        $bestowal->setDirty('award_id', true);

        $savedBestowal = $this->bestowalsTable->saveOrFail($bestowal);
        if (!$savedBestowal instanceof Bestowal) {
            throw new RuntimeException('Saved bestowal did not return a bestowal entity.');
        }

        $this->materializeTodos($savedBestowal);

        $recommendationIds = [];
        foreach ($recommendations as $groupRecommendation) {
            $recommendationIds[] = (int)$groupRecommendation->id;

            $join = $this->bestowalRecommendationsTable->newEmptyEntity();
            $join->set('bestowal_id', (int)$savedBestowal->id);
            $join->set('recommendation_id', (int)$groupRecommendation->id);
            $this->bestowalRecommendationsTable->saveOrFail($join);

            $groupRecommendation->bestowal_id = (int)$savedBestowal->id;
            if (
                $gatheringId !== null
                && Recommendation::supportsGatheringAssignmentForState((string)$groupRecommendation->state)
            ) {
                $groupRecommendation->gathering_id = $gatheringId;
            }
            $groupRecommendation->modified_by = $actorId;
            $this->recommendationsTable->saveOrFail($groupRecommendation, ['systemSync' => true]);
        }

        sort($recommendationIds);

        return [
            'bestowal' => $savedBestowal,
            'recommendationIds' => $recommendationIds,
            'eventPayload' => $this->buildEventPayload($savedBestowal, $recommendationIds, $head),
        ];
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
     * Materialize the bestowal's to-do checklist from its award's template.
     *
     * Failures are logged but never abort bestowal creation: the checklist is
     * supplemental and can be re-materialized idempotently later.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Saved bestowal entity.
     * @return void
     */
    private function materializeTodos(Bestowal $bestowal): void
    {
        try {
            $result = $this->todoMaterializationService->materializeForBestowal($bestowal);
            if (!$result->success) {
                Log::warning('Bestowal to-do materialization reported failure: ' . (string)$result->reason);
            }
        } catch (Throwable $e) {
            Log::error('Bestowal to-do materialization failed: ' . $e->getMessage());
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
            'memberId' => $bestowal->member_id !== null ? (int)$bestowal->member_id : null,
            'memberScaName' => (string)$bestowal->member_sca_name,
            'gatheringId' => $bestowal->gathering_id !== null ? (int)$bestowal->gathering_id : null,
            'lifecycleStatus' => (string)($bestowal->lifecycle_status ?? Bestowal::LIFECYCLE_OPEN),
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
