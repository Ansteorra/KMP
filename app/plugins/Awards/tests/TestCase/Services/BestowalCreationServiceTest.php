<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Services\BestowalCreationService;
use Awards\Services\RecommendationGroupingService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;

class BestowalCreationServiceTest extends BaseTestCase
{
    private Table $recommendationsTable;
    private Table $bestowalsTable;
    private BestowalCreationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        Recommendation::clearCache();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->bestowalsTable = $this->getTableLocator()->get('Awards.Bestowals');
        $this->service = new BestowalCreationService();
    }

    protected function tearDown(): void
    {
        Recommendation::clearCache();
        parent::tearDown();
    }

    public function testCreateFromRecommendationLinksBestowalAndRecommendations(): void
    {
        $recommendationId = $this->createRecommendation('Need to Schedule');

        $result = $this->service->createFromRecommendation($recommendationId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $this->assertFalse($result['skipped'] ?? false);
        $this->assertNotEmpty($result['data']['bestowalId']);

        $bestowalId = (int)$result['data']['bestowalId'];
        $bestowal = $this->bestowalsTable->get($bestowalId);
        $this->assertSame(Bestowal::LIFECYCLE_OPEN, $bestowal->lifecycle_status);
        $this->assertSame($recommendationId, (int)$bestowal->primary_recommendation_id);

        $recommendation = $this->recommendationsTable->get($recommendationId);
        $this->assertSame(
            (int)$recommendation->award_id,
            (int)$bestowal->award_id,
            sprintf(
                'Expected bestowal award %d to match recommendation award %d.',
                (int)$bestowal->award_id,
                (int)$recommendation->award_id,
            ),
        );

        $updated = $this->recommendationsTable->get($recommendationId);
        $this->assertSame($bestowalId, (int)$updated->bestowal_id);

        $joinCount = $this->getTableLocator()->get('Awards.BestowalRecommendations')
            ->find()
            ->where(['bestowal_id' => $bestowalId])
            ->count();
        $this->assertSame(1, $joinCount);
    }

    public function testCreateFromRecommendationPopulatesReasonSummary(): void
    {
        $recommendationId = $this->createRecommendation('Need to Schedule', [
            'reason' => 'Organized the court list and kept everyone on time.',
            'requester_sca_name' => 'Mistress Submitter',
        ]);

        $result = $this->service->createFromRecommendation($recommendationId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $bestowal = $this->bestowalsTable->get((int)$result['data']['bestowalId']);
        $summary = (string)$bestowal->reason_summary;
        $this->assertStringContainsString('Submitted by Mistress Submitter:', $summary);
        $this->assertStringContainsString('Organized the court list and kept everyone on time.', $summary);
    }

    public function testCreateFromRecommendationCopiesSpecialty(): void
    {
        $recommendationId = $this->createRecommendation('Need to Schedule', [
            'specialty' => 'Court Coordination',
        ]);

        $result = $this->service->createFromRecommendation($recommendationId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $bestowal = $this->bestowalsTable->get((int)$result['data']['bestowalId']);
        $this->assertSame('Court Coordination', $bestowal->specialty);
    }

    public function testCreateFromRecommendationSkipsWhenBestowalAlreadyLinked(): void
    {
        $recommendationId = $this->createRecommendation('Need to Schedule');
        $first = $this->service->createFromRecommendation($recommendationId, self::ADMIN_MEMBER_ID);
        $this->assertTrue($first['success'], $first['error'] ?? json_encode($first));

        $second = $this->service->createFromRecommendation($recommendationId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($second['success'], $second['error'] ?? json_encode($second));
        $this->assertTrue($second['skipped']);
    }

    public function testCreateFromGroupedRecommendationsCreatesOneBestowalForGroup(): void
    {
        $secondAwardId = $this->getDifferentAwardId($this->getFirstAwardId());
        $headId = $this->createRecommendation('King Approved', [
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Admin von Admin',
        ]);
        $childId = $this->createRecommendation('King Approved', [
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Admin von Admin',
            'award_id' => $secondAwardId,
        ]);
        (new RecommendationGroupingService($this->recommendationsTable))
            ->groupRecommendations([$headId, $childId], self::ADMIN_MEMBER_ID);

        $result = $this->service->createFromRecommendation($headId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $this->assertCount(1, $result['data']['bestowalIds']);

        $head = $this->recommendationsTable->get($headId);
        $child = $this->recommendationsTable->get($childId);
        $this->assertSame((int)$head->bestowal_id, (int)$child->bestowal_id);

        $headBestowal = $this->bestowalsTable->get((int)$head->bestowal_id);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$headBestowal->member_id);
        $this->assertSame((int)$headBestowal->award_id, (int)$head->award_id);
    }

    public function testCreateFromGroupedRecommendationsUsesSelectedGathering(): void
    {
        $gatheringId = $this->getFirstGatheringId();
        $headId = $this->createRecommendation('King Approved', [
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Admin von Admin',
            'gathering_id' => null,
        ]);
        $childId = $this->createRecommendation('King Approved', [
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Admin von Admin',
            'gathering_id' => null,
        ]);
        (new RecommendationGroupingService($this->recommendationsTable))
            ->groupRecommendations([$headId, $childId], self::ADMIN_MEMBER_ID);

        $result = $this->service->createFromRecommendation($headId, self::ADMIN_MEMBER_ID, $gatheringId);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $bestowal = $this->bestowalsTable->get((int)$result['data']['bestowalId']);
        $this->assertSame($gatheringId, (int)$bestowal->gathering_id);

        $head = $this->recommendationsTable->get($headId);
        $child = $this->recommendationsTable->get($childId);
        $this->assertSame((int)$head->bestowal_id, (int)$child->bestowal_id);
    }

    public function testCreateFromGroupedRecommendationsIncludesAllLinkedReasonsInSummary(): void
    {
        $awardId = $this->getFirstAwardId();
        $headId = $this->createRecommendation('King Approved', [
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Admin von Admin',
            'award_id' => $awardId,
            'reason' => 'First grouped reason.',
            'requester_sca_name' => 'First Submitter',
        ]);
        $childId = $this->createRecommendation('King Approved', [
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Admin von Admin',
            'award_id' => $awardId,
            'reason' => 'Second grouped reason.',
            'requester_sca_name' => 'Second Submitter',
        ]);
        (new RecommendationGroupingService($this->recommendationsTable))
            ->groupRecommendations([$headId, $childId], self::ADMIN_MEMBER_ID);

        $result = $this->service->createFromRecommendation($headId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $this->assertCount(1, $result['data']['bestowalIds']);
        $bestowal = $this->bestowalsTable->get((int)$result['data']['bestowalId']);
        $summary = (string)$bestowal->reason_summary;
        $this->assertStringContainsString('Submitted by First Submitter:', $summary);
        $this->assertStringContainsString('First grouped reason.', $summary);
        $this->assertStringContainsString('Submitted by Second Submitter:', $summary);
        $this->assertStringContainsString('Second grouped reason.', $summary);
    }

    public function testCreateFromGroupedRecommendationsCopiesUniqueSpecialties(): void
    {
        $awardId = $this->getFirstAwardId();
        $headId = $this->createRecommendation('King Approved', [
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Admin von Admin',
            'award_id' => $awardId,
            'specialty' => 'Illumination',
        ]);
        $childId = $this->createRecommendation('King Approved', [
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Admin von Admin',
            'award_id' => $awardId,
            'specialty' => 'Calligraphy',
        ]);
        (new RecommendationGroupingService($this->recommendationsTable))
            ->groupRecommendations([$headId, $childId], self::ADMIN_MEMBER_ID);

        $result = $this->service->createFromRecommendation($headId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $bestowal = $this->bestowalsTable->get((int)$result['data']['bestowalId']);
        $this->assertSame('Illumination, Calligraphy', $bestowal->specialty);
    }

    public function testLockedRecommendationRejectsManualStateChange(): void
    {
        $recommendationId = $this->createRecommendation('Need to Schedule');
        $createResult = $this->service->createFromRecommendation($recommendationId, self::ADMIN_MEMBER_ID);
        $this->assertTrue($createResult['success'], $createResult['error'] ?? json_encode($createResult));

        $recommendation = $this->recommendationsTable->get($recommendationId);
        $recommendation->state = 'Submitted';

        $saved = $this->recommendationsTable->save($recommendation);
        $this->assertFalse($saved);
    }

    private function createRecommendation(string $state, array $overrides = []): int
    {
        $entity = $this->recommendationsTable->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $this->getFirstAwardId(),
            'reason' => 'Bestowal service test',
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@test.com',
            'status' => $this->statusForState($state),
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

        return (int)$this->recommendationsTable->saveOrFail($entity)->id;
    }

    private function getFirstAwardId(): int
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->first();
        $this->assertNotNull($award);

        return (int)$award->id;
    }

    private function getDifferentAwardId(int $awardId): int
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->where(['id !=' => $awardId])
            ->first();
        if ($award === null) {
            $this->markTestSkipped('Need at least two awards for grouped bestowal split tests');
        }

        return (int)$award->id;
    }

    private function getFirstGatheringId(): int
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->first();
        if ($gathering === null) {
            $this->markTestSkipped('Need at least one gathering for selected gathering tests');
        }

        return (int)$gathering->id;
    }

    private function statusForState(string $state): string
    {
        foreach (Recommendation::getStatuses() as $status => $states) {
            if (in_array($state, $states, true)) {
                return $status;
            }
        }

        $this->fail('Unknown recommendation state: ' . $state);
    }
}
