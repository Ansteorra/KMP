<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Services\RecommendationSubmissionService;
use Cake\ORM\TableRegistry;

class RecommendationSubmissionServiceTest extends BaseTestCase
{
    protected RecommendationSubmissionService $service;

    protected $Recommendations;

    protected $Members;

    protected $Awards;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $locator = TableRegistry::getTableLocator();
        $this->Recommendations = $locator->get('Awards.Recommendations');
        $this->Members = $locator->get('Members');
        $this->Awards = $locator->get('Awards.Awards');
        $this->service = new RecommendationSubmissionService();
    }

    public function testSubmitAuthenticatedHydratesMemberDefaultsAndBuildsEventPayload(): void
    {
        $requester = $this->Members->get(
            self::ADMIN_MEMBER_ID,
            select: ['id', 'sca_name', 'email_address', 'phone_number'],
        );
        $member = $this->Members->get(
            self::TEST_MEMBER_AGATHA_ID,
            select: ['id', 'sca_name', 'branch_id', 'public_id'],
        );
        $award = $this->Awards->find()->select(['id', 'name'])->firstOrFail();
        $gatheringIds = $this->getGatheringIds();
        $statuses = Recommendation::getStatuses();
        $expectedStatus = array_key_first($statuses);
        $expectedState = $statuses[$expectedStatus][0];

        $result = $this->service->submitAuthenticated(
            $this->Recommendations,
            [
                'award_id' => $award->id,
                'member_sca_name' => $member->sca_name,
                'member_public_id' => $member->public_id,
                'reason' => 'Recognized for outstanding service',
                'specialty' => 'No specialties available',
                'gatherings' => ['_ids' => array_slice($gatheringIds, 0, 2)],
            ],
            [
                'id' => (int)$requester->id,
                'sca_name' => $requester->sca_name,
                'email_address' => $requester->email_address,
                'phone_number' => $requester->phone_number,
            ],
        );

        $this->assertTrue($result['success']);
        $this->assertSame(RecommendationSubmissionService::EVENT_NAME, $result['eventName']);

        $saved = $result['recommendation'];
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$saved->requester_id);
        $this->assertSame(self::TEST_MEMBER_AGATHA_ID, (int)$saved->member_id);
        $this->assertSame($requester->sca_name, $saved->requester_sca_name);
        $this->assertSame($requester->email_address, $saved->contact_email);
        $this->assertSame($requester->phone_number, $saved->contact_number);
        $this->assertSame($member->branch_id, (int)$saved->branch_id);
        $this->assertSame('With Notice', $saved->call_into_court);
        $this->assertSame('Evening', $saved->court_availability);
        $this->assertSame('Bryce Demoer', $saved->person_to_notify);
        $this->assertNull($saved->specialty);
        $this->assertSame($expectedStatus, $saved->status);
        $this->assertSame($expectedState, $saved->state);
        $this->assertSame(array_slice($gatheringIds, 0, 2), $result['output']['gatheringIds']);
        $this->assertFalse($result['output']['notFound']);
        $this->assertSame(
            [
                'recommendationId' => (int)$saved->id,
                'awardId' => (int)$award->id,
                'memberId' => self::TEST_MEMBER_AGATHA_ID,
                'requesterId' => self::ADMIN_MEMBER_ID,
                'branchId' => (int)$member->branch_id,
                'state' => $expectedState,
                'memberScaName' => $member->sca_name,
                'awardName' => $award->name,
                'reason' => 'Recognized for outstanding service',
                'contactEmail' => $requester->email_address,
            ],
            $result['eventPayload'],
        );

        $stateLog = $this->getTableLocator()
            ->get('Awards.RecommendationsStatesLogs')
            ->find()
            ->where(['recommendation_id' => (int)$saved->id])
            ->orderBy(['id' => 'DESC'])
            ->first();
        $this->assertNotNull($stateLog);
        $this->assertSame('New', $stateLog->from_state);
        $this->assertSame('New', $stateLog->from_status);
        $this->assertSame($expectedState, $stateLog->to_state);
        $this->assertSame($expectedStatus, $stateLog->to_status);
    }

    public function testSubmitPublicHydratesRequesterNameAndPreservesNotFoundBranchSelection(): void
    {
        $requester = $this->Members->get(self::TEST_MEMBER_BRYCE_ID, select: ['id', 'sca_name']);
        $award = $this->Awards->find()->select(['id', 'name'])->firstOrFail();

        $result = $this->service->submitPublic(
            $this->Recommendations,
            [
                'award_id' => $award->id,
                'requester_id' => $requester->id,
                'requester_sca_name' => 'Should be replaced',
                'contact_email' => 'guest@example.com',
                'contact_number' => '123-456-7890',
                'member_sca_name' => 'Unknown Candidate',
                'branch_id' => self::TEST_BRANCH_STARGATE_ID,
                'reason' => 'Guest recommendation',
                'specialty' => 'No specialties available',
                'not_found' => 'on',
            ],
        );

        $this->assertTrue($result['success']);

        $saved = $result['recommendation'];
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, (int)$saved->requester_id);
        $this->assertSame($requester->sca_name, $saved->requester_sca_name);
        $this->assertNull($saved->member_id);
        $this->assertSame(self::TEST_BRANCH_STARGATE_ID, (int)$saved->branch_id);
        $this->assertSame('Not Set', $saved->call_into_court);
        $this->assertSame('Not Set', $saved->court_availability);
        $this->assertSame('', $saved->person_to_notify);
        $this->assertNull($saved->specialty);
        $this->assertSame('guest@example.com', $saved->contact_email);
        $this->assertSame('123-456-7890', $saved->contact_number);
        $this->assertSame(self::TEST_BRANCH_STARGATE_ID, $result['output']['branchId']);
        $this->assertNull($result['output']['memberId']);
        $this->assertTrue($result['output']['notFound']);
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, $result['eventPayload']['requesterId']);
        $this->assertNull($result['eventPayload']['memberId']);
        $this->assertSame('Unknown Candidate', $result['eventPayload']['memberScaName']);
    }

    public function testSubmitReturnsLookupErrorForUnknownMemberPublicId(): void
    {
        $reason = 'Invalid public id test ' . uniqid('', true);
        $result = $this->service->submitPublic(
            $this->Recommendations,
            [
                'award_id' => $this->Awards->find()->select(['id'])->firstOrFail()->id,
                'requester_sca_name' => 'Guest Requester',
                'contact_email' => 'guest@example.com',
                'member_sca_name' => 'Unknown Candidate',
                'member_public_id' => 'not-a-real-public-id',
                'reason' => $reason,
            ],
        );

        $this->assertFalse($result['success']);
        $this->assertSame('member_public_id_not_found', $result['errorCode']);
        $this->assertSame('Member with provided public_id not found.', $result['message']);
        $this->assertFalse(
            $this->Recommendations->exists(['reason' => $reason]),
            'Failed lookups should not create recommendations',
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
}
