<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Services\AdHocBestowalService;
use Cake\ORM\Table;

class AdHocBestowalServiceTest extends BaseTestCase
{
    private Table $bestowalsTable;
    private Table $recommendationsTable;
    private AdHocBestowalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        Recommendation::clearCache();

        $this->bestowalsTable = $this->getTableLocator()->get('Awards.Bestowals');
        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->service = new AdHocBestowalService();
    }

    protected function tearDown(): void
    {
        Recommendation::clearCache();
        parent::tearDown();
    }

    public function testRecordCreatesStandaloneBestowalWithoutRecommendation(): void
    {
        $recommendationCount = $this->recommendationsTable->find()->count();
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $awardId = $this->getAwardIdWithSpecialties([]);

        $result = $this->service->record([
            'member_public_id' => $member->public_id,
            'award_id' => $awardId,
            'bestowed_at' => '2026-06-14',
            'noble_notes' => 'Given at court without prior recommendation.',
            'herald_notes' => 'Read from court list.',
            'call_into_court' => 'Call forward',
            'court_availability' => 'Available now',
            'person_to_notify' => 'Court herald',
        ], self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $bestowalId = (int)$result['data']['bestowalId'];
        $bestowal = $this->bestowalsTable->get($bestowalId);

        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$bestowal->member_id);
        $this->assertSame($awardId, (int)$bestowal->award_id);
        $this->assertSame(Bestowal::SOURCE_AD_HOC, $bestowal->source);
        $this->assertSame(Bestowal::LIFECYCLE_GIVEN, $bestowal->lifecycle_status);
        $this->assertNull($bestowal->primary_recommendation_id);
        $this->assertSame($recommendationCount, $this->recommendationsTable->find()->count());

        $joinCount = $this->getTableLocator()->get('Awards.BestowalRecommendations')
            ->find()
            ->where(['bestowal_id' => $bestowalId])
            ->count();
        $this->assertSame(0, $joinCount);
    }

    public function testRecordCreatesStandaloneBestowalForRecipientWithoutAccount(): void
    {
        $recommendationCount = $this->recommendationsTable->find()->count();
        $awardId = $this->getAwardIdWithSpecialties([]);

        $result = $this->service->record([
            'member_sca_name' => 'Visitor of the Lists',
            'award_id' => $awardId,
            'noble_notes' => 'Given to a visitor without an account.',
        ], self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $bestowal = $this->bestowalsTable->get((int)$result['data']['bestowalId']);
        $this->assertNull($bestowal->member_id);
        $this->assertSame('Visitor of the Lists', $bestowal->member_sca_name);
        $this->assertSame(Bestowal::SOURCE_AD_HOC, $bestowal->source);
        $this->assertSame($recommendationCount, $this->recommendationsTable->find()->count());
        $this->assertNull($result['data']['eventPayload']['memberId']);
        $this->assertSame('Visitor of the Lists', $result['data']['eventPayload']['memberScaName']);
    }

    public function testRecordRequiresRecipientNameWhenNoMemberSelected(): void
    {
        $awardId = $this->getAwardIdWithSpecialties([]);

        $result = $this->service->record([
            'award_id' => $awardId,
        ], self::ADMIN_MEMBER_ID);

        $this->assertFalse($result['success']);
        $this->assertSame('Recipient name is required for ad-hoc bestowal entry.', $result['error']);
    }

    public function testRecordRejectsInvalidMemberPublicId(): void
    {
        $awardId = $this->getAwardIdWithSpecialties([]);

        $result = $this->service->record([
            'member_public_id' => 'missing-member',
            'member_sca_name' => 'Typed Name',
            'award_id' => $awardId,
        ], self::ADMIN_MEMBER_ID);

        $this->assertFalse($result['success']);
        $this->assertSame('Member with provided public_id not found.', $result['error']);
    }

    public function testRecordPersistsConfiguredSpecialty(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $awardId = $this->getAwardIdWithSpecialties(['Court Heraldry', 'Illumination']);

        $result = $this->service->record([
            'member_public_id' => $member->public_id,
            'award_id' => $awardId,
            'specialty' => 'Illumination',
        ], self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $bestowal = $this->bestowalsTable->get((int)$result['data']['bestowalId']);
        $this->assertSame('Illumination', $bestowal->specialty);
    }

    public function testRecordRequiresSpecialtyForAwardWithConfiguredSpecialties(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $awardId = $this->getAwardIdWithSpecialties(['Court Heraldry']);

        $result = $this->service->record([
            'member_public_id' => $member->public_id,
            'award_id' => $awardId,
        ], self::ADMIN_MEMBER_ID);

        $this->assertFalse($result['success']);
        $this->assertSame('Specialty is required for the selected award.', $result['error']);
    }

    public function testRecordPersistsCustomSpecialtyOutsideAwardConfiguration(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $awardId = $this->getAwardIdWithSpecialties(['Court Heraldry']);

        $result = $this->service->record([
            'member_public_id' => $member->public_id,
            'award_id' => $awardId,
            'specialty' => 'Scribal Arts',
        ], self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $bestowal = $this->bestowalsTable->get((int)$result['data']['bestowalId']);
        $this->assertSame('Scribal Arts', $bestowal->specialty);
    }

    public function testRecordClearsSpecialtyForAwardWithoutConfiguredSpecialties(): void
    {
        $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $awardId = $this->getAwardIdWithSpecialties([]);

        $result = $this->service->record([
            'member_public_id' => $member->public_id,
            'award_id' => $awardId,
            'specialty' => 'No specialties available',
        ], self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $bestowal = $this->bestowalsTable->get((int)$result['data']['bestowalId']);
        $this->assertNull($bestowal->specialty);
    }

    /**
     * @param array<int, string> $specialties
     */
    private function getAwardIdWithSpecialties(array $specialties): int
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $award = $awards->find()->firstOrFail();
        $award->specialties = $specialties;
        $awards->saveOrFail($award);

        return (int)$award->id;
    }
}
