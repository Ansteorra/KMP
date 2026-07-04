<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Services\BestowalCourtSlotService;
use Awards\Services\BestowalCreationService;
use Awards\Services\BestowalUpdateService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use RuntimeException;

class BestowalUpdateServiceTest extends BaseTestCase
{
    private Table $recommendationsTable;
    private Table $bestowalsTable;
    private BestowalCreationService $creationService;
    private BestowalUpdateService $updateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        Recommendation::clearCache();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->bestowalsTable = $this->getTableLocator()->get('Awards.Bestowals');
        $this->creationService = new BestowalCreationService();
        $this->updateService = new BestowalUpdateService();
    }

    protected function tearDown(): void
    {
        Recommendation::clearCache();
        parent::tearDown();
    }

    public function testUpdateRequiresAwardId(): void
    {
        $bestowalId = $this->createBestowalFromRecommendation();

        $result = $this->updateService->update(
            $this->bestowalsTable,
            $bestowalId,
            [
                'award_id' => '',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Award to Bestow is required', (string)$result['error']);
    }

    public function testUpdateBestowalAwardDoesNotChangeLinkedRecommendationAward(): void
    {
        $recommendationId = $this->createRecommendation('Need to Schedule');
        $recommendation = $this->recommendationsTable->get($recommendationId);
        $originalRecommendationAwardId = (int)$recommendation->award_id;

        $createResult = $this->creationService->createFromRecommendation(
            $recommendationId,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($createResult['success'], $createResult['error'] ?? json_encode($createResult));

        $bestowalId = (int)$createResult['data']['bestowalId'];
        $replacementAwardId = $this->getAlternateAwardId($originalRecommendationAwardId);

        $result = $this->updateService->update(
            $this->bestowalsTable,
            $bestowalId,
            [
                'award_id' => $replacementAwardId,
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));

        $updatedBestowal = $this->bestowalsTable->get($bestowalId);
        $this->assertSame($replacementAwardId, (int)$updatedBestowal->award_id);

        $updatedRecommendation = $this->recommendationsTable->get($recommendationId);
        $this->assertSame(
            $originalRecommendationAwardId,
            (int)$updatedRecommendation->award_id,
            'Bestowal award edits must not sync back to linked recommendations.',
        );
    }

    public function testUpdatePersistsSpecialty(): void
    {
        $bestowalId = $this->createBestowalFromRecommendation();
        $bestowal = $this->bestowalsTable->get($bestowalId);

        $result = $this->updateService->update(
            $this->bestowalsTable,
            $bestowalId,
            [
                'award_id' => (int)$bestowal->award_id,
                'specialty' => 'Scribal Arts',
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $updatedBestowal = $this->bestowalsTable->get($bestowalId);
        $this->assertSame('Scribal Arts', $updatedBestowal->specialty);
    }

    public function testUpdateNormalizesRoamingCourtSelection(): void
    {
        $bestowalId = $this->createBestowalFromRecommendation();
        $bestowal = $this->bestowalsTable->get($bestowalId);

        $result = $this->updateService->update(
            $this->bestowalsTable,
            $bestowalId,
            [
                'award_id' => (int)$bestowal->award_id,
                'gathering_scheduled_activity_id' => BestowalCourtSlotService::ROAMING_COURT_VALUE,
            ],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $updatedBestowal = $this->bestowalsTable->get($bestowalId);
        $this->assertTrue((bool)$updatedBestowal->roaming_court);
        $this->assertNull($updatedBestowal->gathering_scheduled_activity_id);
    }

    private function createBestowalFromRecommendation(): int
    {
        $recommendationId = $this->createRecommendation('Need to Schedule');
        $createResult = $this->creationService->createFromRecommendation(
            $recommendationId,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($createResult['success'], $createResult['error'] ?? json_encode($createResult));

        return (int)$createResult['data']['bestowalId'];
    }

    private function createRecommendation(string $state): int
    {
        $entity = $this->recommendationsTable->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $this->getFirstAwardId(),
            'reason' => 'Bestowal update service test',
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

        return (int)$this->recommendationsTable->saveOrFail($entity)->id;
    }

    private function getFirstAwardId(): int
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->orderByAsc('id')
            ->firstOrFail();

        return (int)$award->id;
    }

    private function getAlternateAwardId(int $excludeAwardId): int
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->where(['id !=' => $excludeAwardId])
            ->orderByAsc('id')
            ->firstOrFail();

        return (int)$award->id;
    }

    private function statusForState(string $state): string
    {
        $statusList = Recommendation::getStatuses();
        foreach ($statusList as $status => $states) {
            if (in_array($state, $states, true)) {
                return $status;
            }
        }

        throw new RuntimeException('Unknown recommendation state: ' . $state);
    }
}
