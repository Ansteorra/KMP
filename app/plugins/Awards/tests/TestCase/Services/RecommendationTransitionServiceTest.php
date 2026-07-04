<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Services\BestowalCancellationService;
use Awards\Services\RecommendationGroupingService;
use Awards\Services\RecommendationTransitionService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use DateTimeZone;

class RecommendationTransitionServiceTest extends BaseTestCase
{
    private Table $recommendationsTable;
    private RecommendationTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        Recommendation::clearCache();
        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->service = new RecommendationTransitionService();
    }

    protected function tearDown(): void
    {
        Recommendation::clearCache();
        parent::tearDown();
    }

    public function testTransitionToNeedToScheduleCreatesHandoffBestowal(): void
    {
        $recommendationId = $this->createRecommendation('Submitted');
        $gatheringId = $this->getFirstGatheringId();
        $before = $this->recommendationsTable->get($recommendationId);

        $result = $this->service->transition(
            $this->recommendationsTable,
            $recommendationId,
            [
                'targetState' => 'Need to Schedule',
                'gathering_id' => (string)$gatheringId,
                'note' => 'Ready for scheduling',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $this->assertSame($recommendationId, $result['data']['recommendationId']);
        $this->assertSame('Submitted', $result['data']['result']['previousState']);
        $this->assertSame('Need to Schedule', $result['data']['result']['newState']);
        $this->assertSame('Scheduling', $result['data']['result']['newStatus']);
        $this->assertNotEmpty($result['data']['result']['bestowalId']);
        $this->assertSame([$result['data']['result']['bestowalId']], $result['data']['bestowalIds']);

        $updated = $this->recommendationsTable->get($recommendationId);
        $this->assertSame('Need to Schedule', $updated->state);
        $this->assertSame('Scheduling', $updated->status);
        $this->assertSame($gatheringId, $updated->gathering_id);
        $this->assertSame((int)$result['data']['result']['bestowalId'], (int)$updated->bestowal_id);
        $this->assertNotNull($updated->state_date);
        $this->assertNotSame(
            $before->state_date?->format(DATE_ATOM),
            $updated->state_date?->format(DATE_ATOM),
        );

        $bestowal = $this->getTableLocator()->get('Awards.Bestowals')
            ->get((int)$result['data']['result']['bestowalId']);
        $this->assertSame(Bestowal::LIFECYCLE_OPEN, $bestowal->lifecycle_status);
        $this->assertSame($recommendationId, (int)$bestowal->primary_recommendation_id);

        $note = $this->recommendationsTable->Notes->find()
            ->where([
                'entity_id' => $recommendationId,
                'entity_type' => 'Awards.Recommendations',
                'subject' => 'Recommendation Updated',
            ])
            ->orderBy(['id' => 'DESC'])
            ->first();
        $this->assertNotNull($note);
        $this->assertSame('Ready for scheduling', $note->body);
        $this->assertSame(self::ADMIN_MEMBER_ID, $note->author_id);

        $latestLog = $this->getLatestStateLog($recommendationId);
        $this->assertNotNull($latestLog);
        $this->assertSame('Submitted', $latestLog->from_state);
        $this->assertSame('Need to Schedule', $latestLog->to_state);
        $this->assertSame('In Progress', $latestLog->from_status);
        $this->assertSame('Scheduling', $latestLog->to_status);
        $this->assertSame(self::ADMIN_MEMBER_ID, $latestLog->created_by);

        $this->assertSame((string)$gatheringId, (string)$result['data']['result']['changes']['gathering_id']['after']);
    }

    public function testDirectTransitionsToBestowalManagedStatesAreRejected(): void
    {
        foreach (['Scheduled', 'Given', 'Announced Not Given'] as $targetState) {
            $recommendationId = $this->createRecommendation('Submitted');

            $result = $this->service->transition(
                $this->recommendationsTable,
                $recommendationId,
                ['targetState' => $targetState],
                self::ADMIN_MEMBER_ID,
            );

            $this->assertFalse($result['success'], 'Expected ' . $targetState . ' to be rejected.');
            $this->assertStringContainsString('managed by the bestowal workflow', $result['error']);

            $updated = $this->recommendationsTable->get($recommendationId);
            $this->assertSame('Submitted', $updated->state);
            $this->assertNull($updated->bestowal_id);
        }
    }

    public function testTransitionManyClearsGatheringAndCreatesBulkNotes(): void
    {
        $gatheringId = $this->getFirstGatheringId();
        $firstId = $this->createRecommendation('Need to Schedule', ['gathering_id' => $gatheringId]);
        $secondId = $this->createRecommendation('Need to Schedule', ['gathering_id' => $gatheringId]);

        $result = $this->service->transitionMany(
            $this->recommendationsTable,
            [
                'ids' => [(string)$firstId, (string)$secondId],
                'newState' => 'No Action',
                'close_reason' => 'Already recognized elsewhere',
                'note' => 'Bulk closure note',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['data']['processedCount']);
        $this->assertSame('No Action', $result['data']['targetState']);
        $this->assertCount(2, $result['data']['results']);

        foreach ([$firstId, $secondId] as $recommendationId) {
            $updated = $this->recommendationsTable->get($recommendationId);
            $this->assertSame('No Action', $updated->state);
            $this->assertSame('Closed', $updated->status);
            $this->assertNull($updated->gathering_id);
            $this->assertSame('Already recognized elsewhere', $updated->close_reason);

            $note = $this->recommendationsTable->Notes->find()
                ->where([
                    'entity_id' => $recommendationId,
                    'entity_type' => 'Awards.Recommendations',
                    'subject' => 'Recommendation Bulk Updated',
                ])
                ->orderBy(['id' => 'DESC'])
                ->first();
            $this->assertNotNull($note);
            $this->assertSame('Bulk closure note', $note->body);
            $this->assertSame(self::ADMIN_MEMBER_ID, $note->author_id);

            $latestLog = $this->getLatestStateLog($recommendationId);
            $this->assertNotNull($latestLog);
            $this->assertSame('Need to Schedule', $latestLog->from_state);
            $this->assertSame('No Action', $latestLog->to_state);
            $this->assertSame('Scheduling', $latestLog->from_status);
            $this->assertSame('Closed', $latestLog->to_status);
            $this->assertSame(self::ADMIN_MEMBER_ID, $latestLog->created_by);
        }

        $firstResult = $result['data']['results'][0];
        $this->assertSame('Already recognized elsewhere', $firstResult['closeReason']);
        $this->assertNull($firstResult['gatheringId']);
        $this->assertTrue($firstResult['noteCreated']);
        $this->assertArrayHasKey('gathering_id', $firstResult['changes']);
    }

    public function testTransitionManyPreservesOptionalFieldsWhenNullValuesArePassed(): void
    {
        $gatheringId = $this->getFirstGatheringId();
        $existingGiven = new DateTime('2025-02-02 00:00:00', new DateTimeZone('UTC'));
        $recommendationId = $this->createRecommendation('Need to Schedule', [
            'gathering_id' => $gatheringId,
            'given' => $existingGiven,
            'close_reason' => 'Original close reason',
        ]);

        $result = $this->service->transitionMany(
            $this->recommendationsTable,
            [
                'ids' => [(string)$recommendationId],
                'newState' => 'No Action',
                'gathering_id' => null,
                'given' => null,
                'close_reason' => null,
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['data']['processedCount']);

        $updated = $this->recommendationsTable->get($recommendationId);
        $this->assertSame('No Action', $updated->state);
        $this->assertSame('Closed', $updated->status);
        $this->assertNull($updated->gathering_id);
        $this->assertNotNull($updated->given);
        $this->assertSame(
            '2025-02-02 00:00:00',
            $updated->given?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        );
        $this->assertSame('Original close reason', $updated->close_reason);

        $this->assertRecordCount('Notes', 0, [
            'entity_id' => $recommendationId,
            'entity_type' => 'Awards.Recommendations',
        ]);

        $transitionResult = $result['data']['results'][0];
        $this->assertFalse($transitionResult['noteCreated']);
        $this->assertSame('Original close reason', $transitionResult['closeReason']);
        $this->assertArrayHasKey('gathering_id', $transitionResult['changes']);
        $this->assertArrayNotHasKey('given', $transitionResult['changes']);
    }

    public function testTransitionRejectsExistingUnlinkedBestowalManagedState(): void
    {
        $recommendationId = $this->createRecommendation('Scheduled');

        $result = $this->service->transition(
            $this->recommendationsTable,
            $recommendationId,
            ['targetState' => 'No Action'],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('managed by the bestowal workflow', $result['error']);

        $updated = $this->recommendationsTable->get($recommendationId);
        $this->assertSame('Scheduled', $updated->state);
        $this->assertSame('To Give', $updated->status);
    }

    public function testBulkHandoffNormalizesGroupedChildrenToHead(): void
    {
        $headId = $this->createRecommendation('King Approved');
        $childId = $this->createRecommendation('Queen Approved');

        $groupingService = new RecommendationGroupingService($this->recommendationsTable);
        $groupingService->groupRecommendations([$headId, $childId], self::ADMIN_MEMBER_ID);

        $result = $this->service->transitionMany(
            $this->recommendationsTable,
            [
                'ids' => [(string)$childId],
                'newState' => 'Need to Schedule',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $this->assertSame([$childId], $result['data']['requestedRecommendationIds']);
        $this->assertSame([$headId], $result['data']['recommendationIds']);
        $this->assertCount(1, $result['data']['bestowalIds']);

        $head = $this->recommendationsTable->get($headId);
        $child = $this->recommendationsTable->get($childId);
        $this->assertSame('Need to Schedule', $head->state);
        $this->assertNotNull($head->bestowal_id);
        $this->assertSame((int)$head->bestowal_id, (int)$child->bestowal_id);
        $this->assertSame('Linked', $child->state);
    }

    public function testSingleHandoffRejectsGroupedChild(): void
    {
        $headId = $this->createRecommendation('King Approved');
        $childId = $this->createRecommendation('Queen Approved');

        $groupingService = new RecommendationGroupingService($this->recommendationsTable);
        $groupingService->groupRecommendations([$headId, $childId], self::ADMIN_MEMBER_ID);

        $result = $this->service->transition(
            $this->recommendationsTable,
            $childId,
            ['targetState' => 'Need to Schedule'],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('group head', $result['error']);

        $child = $this->recommendationsTable->get($childId);
        $this->assertSame('Linked', $child->state);
        $this->assertNull($child->bestowal_id);
    }

    public function testCancelledBestowalCanBeHandedOffAgain(): void
    {
        $recommendationId = $this->createRecommendation('King Approved');

        $firstHandoff = $this->service->transition(
            $this->recommendationsTable,
            $recommendationId,
            ['targetState' => 'Need to Schedule'],
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($firstHandoff['success'], $firstHandoff['error'] ?? json_encode($firstHandoff));
        $firstBestowalId = (int)$firstHandoff['data']['result']['bestowalId'];
        $bestowalRecommendations = $this->getTableLocator()->get('Awards.BestowalRecommendations');
        $this->assertSame(1, $bestowalRecommendations->find()->where([
            'bestowal_id' => $firstBestowalId,
            'recommendation_id' => $recommendationId,
        ])->count());

        $cancelResult = (new BestowalCancellationService())->cancel(
            $firstBestowalId,
            self::ADMIN_MEMBER_ID,
            'Court plans changed',
        );
        $this->assertTrue($cancelResult['success'], $cancelResult['error'] ?? json_encode($cancelResult));

        $unwound = $this->recommendationsTable->get($recommendationId);
        $this->assertSame('Need to Schedule', $unwound->state);
        $this->assertNull($unwound->bestowal_id);
        $this->assertSame(0, $bestowalRecommendations->find()->where([
            'bestowal_id' => $firstBestowalId,
            'recommendation_id' => $recommendationId,
        ])->count());

        $secondHandoff = $this->service->transition(
            $this->recommendationsTable,
            $recommendationId,
            ['targetState' => 'Need to Schedule'],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($secondHandoff['success'], $secondHandoff['error'] ?? json_encode($secondHandoff));
        $secondBestowalId = (int)$secondHandoff['data']['result']['bestowalId'];
        $this->assertNotSame($firstBestowalId, $secondBestowalId);
    }

    public function testTransitionSyncsLinkedChildrenWhenGroupHeadCloses(): void
    {
        $headState = $this->stateForStatus('In Progress', ['Linked']);
        $childOriginState = $this->differentNonLinkedState($headState);
        $headId = $this->createRecommendation($headState);
        $childId = $this->createRecommendation($childOriginState);

        $groupingService = new RecommendationGroupingService($this->recommendationsTable);
        $groupingService->groupRecommendations([$headId, $childId], self::ADMIN_MEMBER_ID);

        $closedState = $this->stateForStatus('Closed', ['Linked - Closed', 'Given']);
        $result = $this->service->transition(
            $this->recommendationsTable,
            $headId,
            ['targetState' => $closedState],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success']);

        $freshChild = $this->recommendationsTable->get($childId);
        $this->assertSame('Linked - Closed', $freshChild->state);
        $this->assertSame('Closed', $freshChild->status);

        $latestLog = $this->getLatestStateLog($childId);
        $this->assertNotNull($latestLog);
        $this->assertSame('Linked', $latestLog->from_state);
        $this->assertSame('Linked - Closed', $latestLog->to_state);
        $this->assertSame(self::ADMIN_MEMBER_ID, $latestLog->created_by);
    }

    private function createRecommendation(string $state, array $overrides = []): int
    {
        $entity = $this->recommendationsTable->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $this->getFirstAwardId(),
            'reason' => 'Test recommendation transition',
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@test.com',
            'status' => 'In Progress',
            'state' => $state,
            'state_date' => new DateTime('2024-01-01 00:00:00'),
            'call_into_court' => 'Not Set',
            'court_availability' => 'Not Set',
            'person_to_notify' => '',
            'branch_id' => self::KINGDOM_BRANCH_ID,
        ]);

        foreach ($overrides as $field => $value) {
            $entity->set($field, $value);
        }

        $saved = $this->recommendationsTable->saveOrFail($entity);

        return (int)$saved->id;
    }

    private function getFirstAwardId(): int
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->first();

        $this->assertNotNull($award, 'Expected seeded awards data for transition tests.');

        return (int)$award->id;
    }

    private function getFirstGatheringId(): int
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->first();

        $this->assertNotNull($gathering, 'Expected seeded gatherings data for transition tests.');

        return (int)$gathering->id;
    }

    private function getLatestStateLog(int $recommendationId): mixed
    {
        return $this->getTableLocator()->get('Awards.RecommendationsStatesLogs')
            ->find()
            ->where(['recommendation_id' => $recommendationId])
            ->orderBy(['id' => 'DESC'])
            ->first();
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

    private function differentNonLinkedState(string $excludeState): string
    {
        foreach (Recommendation::getStates() as $state) {
            if (!in_array($state, ['Linked', 'Linked - Closed', $excludeState], true)) {
                return $state;
            }
        }

        $this->markTestSkipped('Need a non-linked state for grouping tests');
    }
}
