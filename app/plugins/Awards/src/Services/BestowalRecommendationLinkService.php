<?php
declare(strict_types=1);

namespace Awards\Services;

use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use RuntimeException;

/**
 * Links and unlinks recommendations on an existing bestowal.
 */
class BestowalRecommendationLinkService
{
    use BestowalNotesSupportTrait;
    use LocatorAwareTrait;

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
     * @param \Awards\Services\RecommendationApprovalWorkflowLifecycleService|null $approvalLifecycleService Optional approval lifecycle service.
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
     * Unlink recommendations and restore their pre-link state.
     *
     * @param int $bestowalId Bestowal ID.
     * @param array<int> $recommendationIds Recommendation IDs to unlink.
     * @param int $actorId Actor performing the change.
     * @return array<int> Unlinked recommendation IDs.
     */
    public function unlinkRecommendations(int $bestowalId, array $recommendationIds, int $actorId): array
    {
        $recommendationIds = array_values(array_unique(array_filter(array_map('intval', $recommendationIds))));
        if ($recommendationIds === []) {
            return [];
        }

        return $this->bestowalsTable->getConnection()->transactional(
            function () use ($bestowalId, $recommendationIds, $actorId): array {
                $currentLinkedIds = $this->getLinkedRecommendationIds($bestowalId);
                $recommendationIds = array_values(array_intersect($recommendationIds, $currentLinkedIds));
                if ($recommendationIds === []) {
                    return [];
                }

                if (count($currentLinkedIds) - count($recommendationIds) < 1) {
                    throw new RuntimeException(
                        'A bestowal must keep at least one linked recommendation.',
                    );
                }

                $bestowal = $this->bestowalsTable->get($bestowalId, contain: [
                    'Members',
                    'Recommendations' => ['Awards', 'Awards.Levels', 'Requesters'],
                ]);
                $unwindState = $this->syncService->resolveUnwindTargetStateName();
                $unlinked = [];

                foreach ($recommendationIds as $recommendationId) {
                    $join = $this->bestowalRecommendationsTable->find()
                        ->where([
                            'bestowal_id' => $bestowalId,
                            'recommendation_id' => $recommendationId,
                        ])
                        ->first();
                    if ($join === null) {
                        continue;
                    }

                    $recommendation = $this->recommendationsTable->get($recommendationId);
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
                    $this->recommendationsTable->saveOrFail($recommendation, ['systemSync' => true]);
                    $this->bestowalRecommendationsTable->deleteOrFail($join);
                    $unlinked[] = $recommendationId;
                }

                if ($unlinked !== []) {
                    $this->refreshBestowalAfterLinkChange($bestowal, $actorId);
                    $this->approvalLifecycleService->rehydrateUnlinkedRecommendations(
                        $unlinked,
                        $actorId,
                        'bestowal_unlinked',
                    );
                }

                return $unlinked;
            },
        );
    }

    /**
     * Link recommendations to a bestowal and sync their states.
     *
     * @param int $bestowalId Bestowal ID.
     * @param array<int> $recommendationIds Recommendation IDs to link.
     * @param int $actorId Actor performing the change.
     * @return array<int> Linked recommendation IDs.
     */
    public function linkRecommendations(int $bestowalId, array $recommendationIds, int $actorId): array
    {
        $recommendationIds = array_values(array_unique(array_filter(array_map('intval', $recommendationIds))));
        if ($recommendationIds === []) {
            return [];
        }

        return $this->bestowalsTable->getConnection()->transactional(
            function () use ($bestowalId, $recommendationIds, $actorId): array {
                $bestowal = $this->bestowalsTable->get($bestowalId, contain: [
                    'Members',
                    'Recommendations' => ['Awards', 'Awards.Levels', 'Requesters'],
                ]);
                $linked = [];

                foreach ($recommendationIds as $recommendationId) {
                    $existingJoin = $this->bestowalRecommendationsTable->find()
                        ->where([
                            'bestowal_id' => $bestowalId,
                            'recommendation_id' => $recommendationId,
                        ])
                        ->first();
                    if ($existingJoin !== null) {
                        $recommendation = $this->recommendationsTable->get(
                            $recommendationId,
                            contain: ['Awards', 'Awards.Levels'],
                        );
                        $this->assertLinkable($bestowal, $recommendation);
                        if ($this->syncLinkedRecommendation($bestowal, $recommendation, $actorId)) {
                            $linked[] = $recommendationId;
                        }

                        continue;
                    }

                    $recommendation = $this->recommendationsTable->get(
                        $recommendationId,
                        contain: ['Awards', 'Awards.Levels'],
                    );
                    $this->assertLinkable($bestowal, $recommendation);

                    $join = $this->bestowalRecommendationsTable->newEmptyEntity();
                    $join->bestowal_id = $bestowalId;
                    $join->recommendation_id = $recommendationId;
                    $this->bestowalRecommendationsTable->saveOrFail($join);

                    $this->approvalLifecycleService->supersedeActiveRunsForBestowalLink(
                        [$recommendationId],
                        $bestowalId,
                        $actorId,
                    );
                    $this->syncLinkedRecommendation($bestowal, $recommendation, $actorId);
                    $linked[] = $recommendationId;
                }

                if ($linked !== []) {
                    $this->refreshBestowalAfterLinkChange($bestowal, $actorId);
                }

                return $linked;
            },
        );
    }

    /**
     * Ensure link/unlink changes leave at least one recommendation on the bestowal.
     *
     * @param int $bestowalId Bestowal ID.
     * @param array<int> $unlinkIds Recommendation IDs to unlink.
     * @param array<int> $linkIds Recommendation IDs to link.
     * @return void
     */
    public function assertMinimumLinkedRecommendations(
        int $bestowalId,
        array $unlinkIds,
        array $linkIds,
    ): void {
        $currentLinkedIds = $this->getLinkedRecommendationIds($bestowalId);
        $unlinkIds = array_values(array_intersect(
            array_values(array_unique(array_filter(array_map('intval', $unlinkIds)))),
            $currentLinkedIds,
        ));
        $linkIds = array_values(array_unique(array_filter(array_map('intval', $linkIds))));
        $newLinkIds = array_values(array_diff($linkIds, $currentLinkedIds));

        $remaining = array_values(array_diff($currentLinkedIds, $unlinkIds));
        $finalLinked = array_values(array_unique(array_merge($remaining, $newLinkIds)));

        if ($finalLinked === []) {
            throw new RuntimeException(
                'A bestowal must keep at least one linked recommendation.',
            );
        }
    }

    /**
     * @param int $bestowalId Bestowal ID.
     * @return array<int>
     */
    public function getLinkedRecommendationIds(int $bestowalId): array
    {
        $rows = $this->bestowalRecommendationsTable->find()
            ->where(['bestowal_id' => $bestowalId])
            ->all();

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row->recommendation_id;
        }

        return $ids;
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity.
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation to sync with the bestowal.
     * @param int $actorId Actor performing the link.
     * @return bool True when persisted recommendation fields were repaired.
     */
    private function syncLinkedRecommendation(
        Bestowal $bestowal,
        Recommendation $recommendation,
        int $actorId,
    ): bool {
        $updated = false;
        if ((int)($recommendation->bestowal_id ?? 0) !== (int)$bestowal->id) {
            $recommendation->bestowal_id = (int)$bestowal->id;
            $recommendation->modified_by = $actorId;
            $this->recommendationsTable->saveOrFail($recommendation, ['systemSync' => true]);
            $updated = true;
        }

        $targetState = $this->syncService->resolveSyncTargetStateName((string)$bestowal->state);
        if ($targetState !== null && (string)$recommendation->state !== $targetState) {
            $recommendation = $this->syncService->applySystemRecommendationState(
                $recommendation,
                $targetState,
                $actorId,
            );
            $updated = true;
        }

        if ($this->syncService->syncRecommendationGatheringFromBestowal($bestowal, $recommendation, $actorId)) {
            $updated = true;
        }
        if ($this->syncService->syncRecommendationGivenFromBestowal($bestowal, $recommendation, $actorId)) {
            $updated = true;
        }

        return $updated;
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity.
     * @param \Awards\Model\Entity\Recommendation $recommendation Recommendation to link.
     * @return void
     */
    private function assertLinkable(Bestowal $bestowal, Recommendation $recommendation): void
    {
        if ($recommendation->recommendation_group_id !== null) {
            throw new RuntimeException('Grouped child recommendations cannot be linked directly.');
        }

        if ((int)$recommendation->member_id !== (int)$bestowal->member_id) {
            throw new RuntimeException('Recommendation member must match the bestowal member.');
        }

        if (
            $recommendation->bestowal_id !== null
            && (int)$recommendation->bestowal_id !== (int)$bestowal->id
        ) {
            $otherBestowal = $this->bestowalsTable->get((int)$recommendation->bestowal_id);
            if ($otherBestowal->isActiveBestowal()) {
                throw new RuntimeException(
                    'Recommendation is already linked to another active bestowal.',
                );
            }
        }
    }

    /**
     * Rebuild notes and primary recommendation after link changes.
     *
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal entity (may be stale contain).
     * @param int $actorId Actor performing the change.
     * @return void
     */
    private function refreshBestowalAfterLinkChange(Bestowal $bestowal, int $actorId): void
    {
        $bestowal = $this->bestowalsTable->get((int)$bestowal->id, contain: [
            'Members',
            'Recommendations' => ['Awards', 'Awards.Levels', 'Requesters'],
        ]);

        $recommendations = $bestowal->recommendations ?? [];
        if ($recommendations === []) {
            $bestowal->primary_recommendation_id = null;
            $bestowal->specialty = null;
            $bestowal->noble_notes = null;
            $bestowal->herald_notes = null;
            $bestowal->reason_summary = null;
        } else {
            if (
                $bestowal->primary_recommendation_id === null
                || !$this->isLinkedRecommendation($bestowal, (int)$bestowal->primary_recommendation_id)
            ) {
                $bestowal->primary_recommendation_id = (int)$recommendations[0]->id;
            }

            $memberName = (string)($bestowal->member->sca_name ?? '');
            $bestowal->specialty = $this->buildSpecialtySummary($recommendations);
            $bestowal->noble_notes = $this->buildNobleNotes($recommendations);
            $bestowal->herald_notes = $this->buildHeraldNotes($recommendations, $memberName);
            $bestowal->reason_summary = $this->buildReasonSummary($recommendations);
        }

        $bestowal->modified_by = $actorId;
        $this->bestowalsTable->saveOrFail($bestowal);
    }

    /**
     * @param \Awards\Model\Entity\Bestowal $bestowal Bestowal with recommendations contain.
     * @param int $recommendationId Recommendation ID.
     * @return bool
     */
    private function isLinkedRecommendation(Bestowal $bestowal, int $recommendationId): bool
    {
        foreach ($bestowal->recommendations ?? [] as $recommendation) {
            if ((int)$recommendation->id === $recommendationId) {
                return true;
            }
        }

        return false;
    }
}
