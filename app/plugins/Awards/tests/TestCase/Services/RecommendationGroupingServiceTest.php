<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Services\RecommendationDeletionService;
use Awards\Services\RecommendationGroupingService;
use Awards\Services\RecommendationTransitionService;
use Cake\ORM\Table;

class RecommendationGroupingServiceTest extends BaseTestCase
{
    private RecommendationGroupingService $service;
    private Table $recommendationsTable;
    private Table $stateLogsTable;
    private int $awardId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->stateLogsTable = $this->getTableLocator()->get('Awards.RecommendationsStatesLogs');
        $this->service = new RecommendationGroupingService($this->recommendationsTable);
        $this->awardId = $this->getFirstAwardId();
    }

    public function testGroupRecommendationsSnapshotsOriginStateAndWritesLinkedLog(): void
    {
        $head = $this->createTestRecommendation(['state' => $this->stateForStatus('In Progress', ['Linked'])]);
        $childOriginState = $this->differentNonLinkedState((string)$head->state);
        $child = $this->createTestRecommendation(['state' => $childOriginState]);

        $this->service->groupRecommendations([(int)$head->id, (int)$child->id], self::ADMIN_MEMBER_ID);

        $freshChild = $this->recommendationsTable->get((int)$child->id);
        $this->assertSame((int)$head->id, (int)$freshChild->recommendation_group_id);
        $this->assertSame('Linked', $freshChild->state);
        $this->assertSame($childOriginState, $freshChild->group_origin_state);
        $this->assertSame($this->statusForState($childOriginState), $freshChild->group_origin_status);

        $log = $this->latestStateLogFor((int)$child->id);
        $this->assertSame($childOriginState, $log->from_state);
        $this->assertSame('Linked', $log->to_state);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$log->created_by);
    }

    public function testUngroupRecommendationsRestoresOriginalStateAfterLinkedClosedSync(): void
    {
        $head = $this->createTestRecommendation(['state' => $this->stateForStatus('In Progress', ['Linked'])]);
        $childOriginState = $this->differentNonLinkedState((string)$head->state);
        $child = $this->createTestRecommendation(['state' => $childOriginState]);

        $this->service->groupRecommendations([(int)$head->id, (int)$child->id], self::ADMIN_MEMBER_ID);

        $transitionService = new RecommendationTransitionService($this->service);
        $transitionService->transition(
            $this->recommendationsTable,
            (int)$head->id,
            ['targetState' => $this->stateForStatus('Closed', ['Linked - Closed', 'Given'])],
            self::ADMIN_MEMBER_ID,
        );

        $syncedChild = $this->recommendationsTable->get((int)$child->id);
        $this->assertSame('Linked - Closed', $syncedChild->state);

        $this->service->ungroupRecommendations((int)$head->id, self::ADMIN_MEMBER_ID);

        $restoredChild = $this->recommendationsTable->get((int)$child->id);
        $this->assertNull($restoredChild->recommendation_group_id);
        $this->assertSame($childOriginState, $restoredChild->state);
        $this->assertNull($restoredChild->group_origin_state);
        $this->assertNull($restoredChild->group_origin_status);

        $log = $this->latestStateLogFor((int)$child->id);
        $this->assertSame('Linked - Closed', $log->from_state);
        $this->assertSame($childOriginState, $log->to_state);
    }

    public function testRemoveFromGroupAutoUngroupsFinalChildAndRestoresBothOrigins(): void
    {
        $head = $this->createTestRecommendation(['state' => $this->stateForStatus('In Progress', ['Linked'])]);
        $firstChildOrigin = $this->differentNonLinkedState((string)$head->state);
        $secondChildOrigin = $this->differentNonLinkedState($firstChildOrigin, [(string)$head->state]);
        $firstChild = $this->createTestRecommendation(['state' => $firstChildOrigin]);
        $secondChild = $this->createTestRecommendation(['state' => $secondChildOrigin]);

        $this->service->groupRecommendations(
            [(int)$head->id, (int)$firstChild->id, (int)$secondChild->id],
            self::ADMIN_MEMBER_ID,
        );

        $formerHeadId = $this->service->removeFromGroup((int)$firstChild->id, self::ADMIN_MEMBER_ID);

        $this->assertSame((int)$head->id, $formerHeadId);

        $freshFirstChild = $this->recommendationsTable->get((int)$firstChild->id);
        $freshSecondChild = $this->recommendationsTable->get((int)$secondChild->id);
        $this->assertNull($freshFirstChild->recommendation_group_id);
        $this->assertNull($freshSecondChild->recommendation_group_id);
        $this->assertSame($firstChildOrigin, $freshFirstChild->state);
        $this->assertSame($secondChildOrigin, $freshSecondChild->state);
        $this->assertNull($freshFirstChild->group_origin_state);
        $this->assertNull($freshSecondChild->group_origin_state);

        $secondChildLog = $this->latestStateLogFor((int)$secondChild->id);
        $this->assertSame('Linked', $secondChildLog->from_state);
        $this->assertSame($secondChildOrigin, $secondChildLog->to_state);
    }

    public function testSoftDeletingHeadRestoresChildrenUsingOriginSnapshots(): void
    {
        $head = $this->createTestRecommendation(['state' => $this->stateForStatus('In Progress', ['Linked'])]);
        $childOriginState = $this->differentNonLinkedState((string)$head->state);
        $child = $this->createTestRecommendation(['state' => $childOriginState]);

        $this->service->groupRecommendations([(int)$head->id, (int)$child->id], self::ADMIN_MEMBER_ID);

        $transitionService = new RecommendationTransitionService($this->service);
        $transitionService->transition(
            $this->recommendationsTable,
            (int)$head->id,
            ['targetState' => $this->stateForStatus('Closed', ['Linked - Closed', 'Given'])],
            self::ADMIN_MEMBER_ID,
        );

        $deleteService = new RecommendationDeletionService($this->service);
        $deleteResult = $deleteService->delete(
            $this->recommendationsTable,
            $this->recommendationsTable->get((int)$head->id),
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($deleteResult['success']);

        $restoredChild = $this->recommendationsTable->get((int)$child->id);
        $this->assertNull($restoredChild->recommendation_group_id);
        $this->assertSame($childOriginState, $restoredChild->state);
        $this->assertNull($restoredChild->group_origin_state);

        $log = $this->latestStateLogFor((int)$child->id);
        $this->assertSame('Linked - Closed', $log->from_state);
        $this->assertSame($childOriginState, $log->to_state);
    }

    private function createTestRecommendation(array $overrides = []): Recommendation
    {
        $state = (string)($overrides['state'] ?? $this->stateForStatus('In Progress', ['Linked']));
        $status = (string)($overrides['status'] ?? $this->statusForState($state));

        $data = array_merge([
            'award_id' => $this->awardId,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'requester_sca_name' => 'Test Requester',
            'member_sca_name' => 'Test Member',
            'contact_email' => 'grouping@example.com',
            'reason' => 'Testing recommendation grouping',
            'call_into_court' => 'No preference',
            'court_availability' => 'Available anytime',
        ], $overrides);

        unset($data['state'], $data['status']);

        /** @var \Awards\Model\Entity\Recommendation $entity */
        $entity = $this->recommendationsTable->newEmptyEntity();
        foreach ($data as $field => $value) {
            $entity->$field = $value;
        }
        $entity->status = $status;
        $entity->state = $state;

        return $this->recommendationsTable->saveOrFail($entity);
    }

    private function latestStateLogFor(int $recommendationId): object
    {
        $log = $this->stateLogsTable->find()
            ->where(['recommendation_id' => $recommendationId])
            ->orderBy(['id' => 'DESC'])
            ->first();

        $this->assertNotNull($log, "Expected a state log for recommendation {$recommendationId}");

        return $log;
    }

    private function getFirstAwardId(): int
    {
        $awardsTable = $this->getTableLocator()->get('Awards.Awards');
        $award = $awardsTable->find()->select(['id'])->first();
        if ($award === null) {
            $this->markTestSkipped('No awards in test database');
        }

        return (int)$award->id;
    }

    private function stateForStatus(string $status, array $exclude = []): string
    {
        $states = Recommendation::getStatuses()[$status] ?? [];
        foreach ($states as $state) {
            if (!in_array($state, $exclude, true)) {
                return $state;
            }
        }

        $this->markTestSkipped("No usable {$status} state available");
    }

    private function statusForState(string $state): string
    {
        foreach (Recommendation::getStatuses() as $status => $states) {
            if (in_array($state, $states, true)) {
                return (string)$status;
            }
        }

        $this->fail("Unknown status for state {$state}");
    }

    private function differentNonLinkedState(string $excludeState, array $extraExcludes = []): string
    {
        $excluded = array_merge(['Linked', 'Linked - Closed', $excludeState], $extraExcludes);
        foreach (Recommendation::getStates() as $state) {
            if (!in_array($state, $excluded, true)) {
                return $state;
            }
        }

        $this->markTestSkipped('Need at least two non-linked states for grouping tests');
    }
}
