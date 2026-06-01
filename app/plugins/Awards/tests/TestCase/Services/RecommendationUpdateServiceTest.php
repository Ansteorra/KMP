<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Services\RecommendationGroupingService;
use Awards\Services\RecommendationUpdateService;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use DateTimeZone;

class RecommendationUpdateServiceTest extends BaseTestCase
{
    protected RecommendationUpdateService $service;

    protected $Recommendations;

    protected $Members;

    protected $Awards;

    protected $Notes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $locator = TableRegistry::getTableLocator();
        $this->Recommendations = $locator->get('Awards.Recommendations');
        $this->Members = $locator->get('Members');
        $this->Awards = $locator->get('Awards.Awards');
        $this->Notes = $this->Recommendations->Notes;
        $this->service = new RecommendationUpdateService();
    }

    public function testUpdateHydratesChangedMemberNormalizesGivenCreatesNoteAndSyncsGatherings(): void
    {
        $existing = $this->createRecommendation(self::TEST_MEMBER_BRYCE_ID, array_slice($this->getGatheringIds(), 0, 2));
        $member = $this->Members->get(
            self::TEST_MEMBER_AGATHA_ID,
            select: ['id', 'sca_name', 'branch_id', 'public_id'],
        );
        $gatheringIds = $this->getGatheringIds();

        $result = $this->service->update(
            $this->Recommendations,
            $existing,
            [
                'member_sca_name' => $member->sca_name,
                'member_public_id' => $member->public_id,
                'specialty' => 'No specialties available',
                'gatherings' => ['_ids' => array_slice($gatheringIds, 2, 2)],
                'given' => '2026-02-03',
                'note' => 'Updated through service',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success']);

        $saved = $this->Recommendations->get($existing->id, contain: ['Gatherings']);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, (int)$saved->member_id);
        $this->assertSame($member->branch_id, (int)$saved->branch_id);
        $this->assertSame('With Notice', $saved->call_into_court);
        $this->assertSame('Evening', $saved->court_availability);
        $this->assertSame('Bryce Demoer', $saved->person_to_notify);
        $this->assertNull($saved->specialty);
        $this->assertNotNull($saved->given);
        $this->assertSame(
            '2026-02-03 00:00:00',
            $saved->given->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        );
        $this->assertSame(array_slice($gatheringIds, 2, 2), $result['output']['gatheringIds']);
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, $result['output']['previousMemberId']);
        $this->assertTrue($result['output']['memberChanged']);
        $this->assertSame('2026-02-03', $result['output']['given']);

        $note = $this->Notes->find()
            ->where([
                'entity_id' => $existing->id,
                'entity_type' => 'Awards.Recommendations',
                'subject' => 'Recommendation Updated',
            ])
            ->firstOrFail();
        $this->assertSame('Updated through service', $note->body);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$note->author_id);
        $this->assertSame((int)$note->id, (int)$result['output']['noteId']);
        $this->assertSame('Updated through service', $result['output']['noteBody']);
    }

    public function testUpdateClearsMemberFieldsWhenRecommendationIsDetachedFromMember(): void
    {
        $existing = $this->createRecommendation(self::TEST_MEMBER_AGATHA_ID, array_slice($this->getGatheringIds(), 0, 2));

        $result = $this->service->update(
            $this->Recommendations,
            $existing,
            [
                'member_id' => 0,
                'member_sca_name' => 'Unknown Candidate',
                'branch_id' => self::TEST_BRANCH_STARGATE_ID,
                'gatherings' => ['_ids' => []],
                'given' => '',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success']);

        $saved = $this->Recommendations->get($existing->id, contain: ['Gatherings']);
        $this->assertNull($saved->member_id);
        $this->assertSame(self::TEST_BRANCH_STARGATE_ID, (int)$saved->branch_id);
        $this->assertSame('Not Set', $saved->call_into_court);
        $this->assertSame('Not Set', $saved->court_availability);
        $this->assertSame('', $saved->person_to_notify);
        $this->assertNull($saved->given);
        $this->assertSame([], $result['output']['gatheringIds']);
        $this->assertFalse($result['output']['notFound']);
    }

    public function testUpdateSyncsLinkedChildrenWhenHeadTransitionsToClosedStatus(): void
    {
        $headState = $this->stateForStatus('In Progress', ['Linked']);
        $childOriginState = $this->differentNonLinkedState($headState);
        $head = $this->createRecommendation(self::TEST_MEMBER_AGATHA_ID, [], ['state' => $headState]);
        $child = $this->createRecommendation(self::TEST_MEMBER_AGATHA_ID, [], ['state' => $childOriginState]);

        $groupingService = new RecommendationGroupingService($this->Recommendations);
        $groupingService->groupRecommendations([(int)$head->id, (int)$child->id], self::ADMIN_MEMBER_ID);

        $closedState = $this->stateForStatus('Closed', ['Linked - Closed', 'Given']);
        $head = $this->Recommendations->get((int)$head->id, contain: ['Gatherings']);
        $result = $this->service->update(
            $this->Recommendations,
            $head,
            ['state' => $closedState],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success']);

        $freshChild = $this->Recommendations->get((int)$child->id);
        $this->assertSame('Linked - Closed', $freshChild->state);
        $this->assertSame('Closed', $freshChild->status);

        $childLog = $this->getTableLocator()
            ->get('Awards.RecommendationsStatesLogs')
            ->find()
            ->where(['recommendation_id' => (int)$child->id])
            ->orderBy(['id' => 'DESC'])
            ->first();
        $this->assertNotNull($childLog);
        $this->assertSame('Linked', $childLog->from_state);
        $this->assertSame('Linked - Closed', $childLog->to_state);
    }

    public function testUpdateToNeedToScheduleCreatesHandoffBestowal(): void
    {
        $existing = $this->createRecommendation(
            self::TEST_MEMBER_AGATHA_ID,
            [],
            ['state' => 'King Approved'],
        );

        $result = $this->service->update(
            $this->Recommendations,
            $existing,
            ['state' => 'Need to Schedule'],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['message'] ?? json_encode($result));
        $this->assertNotEmpty($result['output']['bestowalId']);

        $saved = $this->Recommendations->get((int)$existing->id);
        $this->assertSame('Need to Schedule', $saved->state);
        $this->assertSame('Scheduling', $saved->status);
        $this->assertSame((int)$result['output']['bestowalId'], (int)$saved->bestowal_id);
    }

    public function testUpdateRejectsDirectBestowalManagedState(): void
    {
        $existing = $this->createRecommendation(
            self::TEST_MEMBER_AGATHA_ID,
            [],
            ['state' => 'King Approved'],
        );

        $result = $this->service->update(
            $this->Recommendations,
            $existing,
            ['state' => 'Given'],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_state_transition', $result['errorCode']);
        $this->assertStringContainsString('managed by the bestowal workflow', $result['message']);

        $saved = $this->Recommendations->get((int)$existing->id);
        $this->assertSame('King Approved', $saved->state);
        $this->assertNull($saved->bestowal_id);
    }

    public function testUpdateRejectsExistingUnlinkedBestowalManagedState(): void
    {
        $existing = $this->createRecommendation(
            self::TEST_MEMBER_AGATHA_ID,
            [],
            [
                'status' => 'Closed',
                'state' => 'Given',
                'reason' => 'Managed state should not be user editable',
            ],
        );

        $result = $this->service->update(
            $this->Recommendations,
            $existing,
            [
                'reason' => 'Attempted manual edit',
                'note' => 'This should not save',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_state_transition', $result['errorCode']);
        $this->assertStringContainsString('managed by the bestowal workflow', $result['message']);

        $saved = $this->Recommendations->get((int)$existing->id);
        $this->assertSame('Given', $saved->state);
        $this->assertSame('Managed state should not be user editable', $saved->reason);

        $moveOutResult = $this->service->update(
            $this->Recommendations,
            $saved,
            ['state' => 'King Approved'],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($moveOutResult['success']);
        $this->assertSame('invalid_state_transition', $moveOutResult['errorCode']);
        $this->assertSame('Given', $this->Recommendations->get((int)$existing->id)->state);
    }

    public function testUpdateAllowsNoActionWithoutBestowal(): void
    {
        $existing = $this->createRecommendation(
            self::TEST_MEMBER_AGATHA_ID,
            [],
            ['state' => 'King Approved'],
        );

        $result = $this->service->update(
            $this->Recommendations,
            $existing,
            [
                'state' => 'No Action',
                'close_reason' => 'Not selected by Crown',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['message'] ?? json_encode($result));

        $saved = $this->Recommendations->get((int)$existing->id);
        $this->assertSame('No Action', $saved->state);
        $this->assertSame('Closed', $saved->status);
        $this->assertSame('Not selected by Crown', $saved->close_reason);
        $this->assertNull($saved->bestowal_id);
    }

    /**
     * @param array<int> $gatheringIds
     */
    private function createRecommendation(int $memberId, array $gatheringIds, array $overrides = []): Recommendation
    {
        $member = $this->Members->get($memberId, select: ['id', 'sca_name', 'branch_id']);
        $requester = $this->Members->get(
            self::ADMIN_MEMBER_ID,
            select: ['id', 'sca_name', 'email_address', 'phone_number'],
        );
        $award = $this->Awards->find()->select(['id'])->firstOrFail();
        $statuses = Recommendation::getStatuses();
        $status = array_key_first($statuses);
        $state = $statuses[$status][0];

        $recommendation = $this->Recommendations->newEntity(
            array_merge([
                'award_id' => $award->id,
                'requester_id' => $requester->id,
                'requester_sca_name' => $requester->sca_name,
                'member_id' => $member->id,
                'member_sca_name' => $member->sca_name,
                'branch_id' => $member->branch_id,
                'contact_email' => $requester->email_address,
                'contact_number' => $requester->phone_number,
                'reason' => 'Original recommendation body',
                'status' => $status,
                'state' => $state,
                'state_date' => DateTime::now(),
                'not_found' => false,
                'call_into_court' => 'Not Set',
                'court_availability' => 'Not Set',
                'person_to_notify' => '',
                'gatherings' => ['_ids' => $gatheringIds],
            ], $overrides),
            ['associated' => ['Gatherings']],
        );

        return $this->Recommendations->saveOrFail(
            $recommendation,
            ['associated' => ['Gatherings']],
        );
    }

    /**
     * @return array<int>
     */
    private function getGatheringIds(): array
    {
        return $this->getTableLocator()
            ->get('Gatherings')
            ->find()
            ->select(['id'])
            ->limit(4)
            ->all()
            ->extract('id')
            ->map(fn($id) => (int)$id)
            ->toList();
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
