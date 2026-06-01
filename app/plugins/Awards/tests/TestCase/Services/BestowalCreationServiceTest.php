<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Services\BestowalCreationService;
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
        Bestowal::clearCache();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->bestowalsTable = $this->getTableLocator()->get('Awards.Bestowals');
        $this->service = new BestowalCreationService();
    }

    protected function tearDown(): void
    {
        Recommendation::clearCache();
        Bestowal::clearCache();
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
        $this->assertSame('Created', $bestowal->state);
        $this->assertSame($recommendationId, (int)$bestowal->primary_recommendation_id);

        $recommendation = $this->recommendationsTable->get($recommendationId);
        $this->assertSame((int)$recommendation->award_id, (int)$bestowal->award_id);

        $updated = $this->recommendationsTable->get($recommendationId);
        $this->assertSame($bestowalId, (int)$updated->bestowal_id);

        $joinCount = $this->getTableLocator()->get('Awards.BestowalRecommendations')
            ->find()
            ->where(['bestowal_id' => $bestowalId])
            ->count();
        $this->assertSame(1, $joinCount);
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
