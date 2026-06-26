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
        $awardId = $this->getFirstAwardId();

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

    private function getFirstAwardId(): int
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->first();
        $this->assertNotNull($award);

        return (int)$award->id;
    }
}
