<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Services\BestowalCreationService;
use Awards\Services\BestowalRecommendationSyncService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use DateTimeZone;

class BestowalRecommendationSyncServiceTest extends BaseTestCase
{
    private Table $bestowalsTable;
    private Table $recommendationsTable;
    private BestowalCreationService $creationService;
    private BestowalRecommendationSyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        Recommendation::clearCache();
        Bestowal::clearCache();

        $this->bestowalsTable = $this->getTableLocator()->get('Awards.Bestowals');
        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->creationService = new BestowalCreationService();
        $this->syncService = new BestowalRecommendationSyncService();
    }

    protected function tearDown(): void
    {
        Recommendation::clearCache();
        Bestowal::clearCache();
        parent::tearDown();
    }

    public function testSyncFromBestowalCopiesGatheringWhenStateAlreadyMatches(): void
    {
        $recommendationId = $this->createRecommendation('Need to Schedule');
        $createResult = $this->creationService->createFromRecommendation(
            $recommendationId,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($createResult['success'], $createResult['error'] ?? json_encode($createResult));

        $bestowalId = (int)$createResult['data']['bestowalId'];
        $gatheringId = $this->getFirstGatheringId();

        $bestowal = $this->bestowalsTable->get($bestowalId);
        $bestowal->state = 'Court Pending';
        $bestowal->gathering_id = $gatheringId;
        $bestowal->modified_by = self::ADMIN_MEMBER_ID;
        $this->bestowalsTable->saveOrFail($bestowal);

        $recommendation = $this->recommendationsTable->get($recommendationId);
        $recommendation->gathering_id = null;
        $recommendation->modified_by = self::ADMIN_MEMBER_ID;
        $this->recommendationsTable->saveOrFail($recommendation, ['systemSync' => true]);

        $result = $this->syncService->syncFromBestowal($bestowalId, self::ADMIN_MEMBER_ID);
        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));

        $reloaded = $this->recommendationsTable->get($recommendationId);
        $this->assertSame($gatheringId, $reloaded->gathering_id);
    }

    public function testSyncFromBestowalClearsGatheringWhenBestowalHasNone(): void
    {
        $recommendationId = $this->createRecommendation('Need to Schedule');
        $createResult = $this->creationService->createFromRecommendation(
            $recommendationId,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($createResult['success'], $createResult['error'] ?? json_encode($createResult));

        $bestowalId = (int)$createResult['data']['bestowalId'];
        $gatheringId = $this->getFirstGatheringId();

        $recommendation = $this->recommendationsTable->get($recommendationId);
        $recommendation->gathering_id = $gatheringId;
        $recommendation->modified_by = self::ADMIN_MEMBER_ID;
        $this->recommendationsTable->saveOrFail($recommendation, ['systemSync' => true]);

        $bestowal = $this->bestowalsTable->get($bestowalId);
        $bestowal->gathering_id = null;
        $bestowal->modified_by = self::ADMIN_MEMBER_ID;
        $this->bestowalsTable->saveOrFail($bestowal);

        $result = $this->syncService->syncFromBestowal($bestowalId, self::ADMIN_MEMBER_ID);
        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));

        $reloaded = $this->recommendationsTable->get($recommendationId);
        $this->assertNull($reloaded->gathering_id);
    }

    public function testSyncFromBestowalCopiesGivenDateWhenRecommendationIsGiven(): void
    {
        $recommendationId = $this->createRecommendation('Need to Schedule');
        $createResult = $this->creationService->createFromRecommendation(
            $recommendationId,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($createResult['success'], $createResult['error'] ?? json_encode($createResult));

        $bestowalId = (int)$createResult['data']['bestowalId'];
        $bestowal = $this->bestowalsTable->get($bestowalId);
        $bestowal->gathering_id = $this->getFirstGatheringId();
        $bestowal->state = 'Given';
        $bestowal->bestowed_at = new DateTime('2026-06-15 00:00:00', new DateTimeZone('UTC'));
        $bestowal->modified_by = self::ADMIN_MEMBER_ID;
        $this->bestowalsTable->saveOrFail($bestowal);

        $recommendation = $this->recommendationsTable->get($recommendationId);
        $recommendation->state = 'Given';
        $recommendation->status = $this->statusForState('Given');
        $recommendation->given = new DateTime('2020-01-01 00:00:00', new DateTimeZone('UTC'));
        $recommendation->modified_by = self::ADMIN_MEMBER_ID;
        $this->recommendationsTable->saveOrFail($recommendation, ['systemSync' => true]);

        $result = $this->syncService->syncFromBestowal($bestowalId, self::ADMIN_MEMBER_ID);
        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));

        $reloaded = $this->recommendationsTable->get($recommendationId);
        $this->assertSame('Given', $reloaded->state);
        $this->assertNotNull($reloaded->given);
        $this->assertSame(
            '2026-06-15',
            $reloaded->given->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d'),
        );
    }

    private function createRecommendation(string $state): int
    {
        $entity = $this->recommendationsTable->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $this->getFirstAwardId(),
            'reason' => 'Bestowal sync service test',
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
            ->first();
        $this->assertNotNull($award);

        return (int)$award->id;
    }

    private function getFirstGatheringId(): int
    {
        $gathering = $this->getTableLocator()->get('Gatherings')
            ->find()
            ->select(['id'])
            ->first();
        $this->assertNotNull($gathering, 'Expected seeded gatherings data for sync tests.');

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
