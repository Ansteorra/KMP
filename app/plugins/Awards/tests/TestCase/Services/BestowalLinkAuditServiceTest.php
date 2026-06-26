<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Services\BestowalCreationService;
use Awards\Services\BestowalLinkAuditService;
use Awards\Services\BestowalRecommendationLinkService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;

class BestowalLinkAuditServiceTest extends BaseTestCase
{
    private Table $recommendationsTable;
    private Table $bestowalsTable;
    private Table $bestowalRecommendationsTable;
    private BestowalCreationService $creationService;
    private BestowalRecommendationLinkService $linkService;
    private BestowalLinkAuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        Recommendation::clearCache();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->bestowalsTable = $this->getTableLocator()->get('Awards.Bestowals');
        $this->bestowalRecommendationsTable = $this->getTableLocator()->get('Awards.BestowalRecommendations');
        $this->creationService = new BestowalCreationService();
        $this->linkService = new BestowalRecommendationLinkService();
        $this->auditService = new BestowalLinkAuditService();
    }

    protected function tearDown(): void
    {
        Recommendation::clearCache();
        parent::tearDown();
    }

    public function testDetectsJoinRowsWithoutRecommendationShortcut(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(1);
        $recommendationId = $this->linkService->getLinkedRecommendationIds($bestowalId)[0];

        $recommendation = $this->recommendationsTable->get($recommendationId);
        $recommendation->bestowal_id = null;
        $this->recommendationsTable->saveOrFail($recommendation, ['systemSync' => true]);

        $audit = $this->auditService->audit();

        $this->assertIssueSampleContains(
            $audit,
            'joinRowsWithoutRecommendationShortcut',
            'recommendation_id',
            $recommendationId,
        );
    }

    public function testDetectsRecommendationShortcutsWithoutJoinRows(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(1);
        $recommendationId = $this->linkService->getLinkedRecommendationIds($bestowalId)[0];

        $this->bestowalRecommendationsTable->deleteAll([
            'bestowal_id' => $bestowalId,
            'recommendation_id' => $recommendationId,
        ]);

        $audit = $this->auditService->audit();

        $this->assertIssueSampleContains(
            $audit,
            'recommendationShortcutsWithoutJoinRow',
            'recommendation_id',
            $recommendationId,
        );
    }

    public function testDetectsCancelledBestowalsWithActiveJoinRows(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(1);

        $this->bestowalsTable->updateAll(
            ['lifecycle_status' => Bestowal::LIFECYCLE_CANCELLED],
            ['id' => $bestowalId],
        );

        $audit = $this->auditService->audit();

        $this->assertIssueSampleContains(
            $audit,
            'cancelledBestowalsWithActiveJoinRows',
            'bestowal_id',
            $bestowalId,
        );
    }

    public function testDetectsRecommendationBestowalsMissingAward(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(1);

        $this->bestowalsTable->updateAll(
            ['award_id' => null],
            ['id' => $bestowalId],
        );

        $audit = $this->auditService->audit();

        $this->assertIssueSampleContains(
            $audit,
            'recommendationBestowalsMissingAward',
            'bestowal_id',
            $bestowalId,
        );
    }

    public function testDetectsActiveRecommendationBestowalsWithoutJoinRows(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(1);

        $this->bestowalRecommendationsTable->deleteAll(['bestowal_id' => $bestowalId]);

        $audit = $this->auditService->audit();

        $this->assertIssueSampleContains(
            $audit,
            'activeRecommendationBestowalsWithoutJoinRows',
            'bestowal_id',
            $bestowalId,
        );
    }

    /**
     * @param array<string, array{count:int,samples:array<int, array<string, mixed>>}> $audit Audit result.
     */
    private function assertIssueSampleContains(
        array $audit,
        string $issue,
        string $field,
        int $expectedValue,
    ): void {
        $this->assertArrayHasKey($issue, $audit);
        $this->assertGreaterThan(0, $audit[$issue]['count']);
        foreach ($audit[$issue]['samples'] as $sample) {
            if ((int)($sample[$field] ?? 0) === $expectedValue) {
                $this->assertTrue(true);

                return;
            }
        }

        $this->fail(sprintf(
            'Expected %s sample to contain %s=%d; samples: %s',
            $issue,
            $field,
            $expectedValue,
            json_encode($audit[$issue]['samples']),
        ));
    }

    private function createBestowalWithRecommendations(int $recommendationCount): int
    {
        $this->assertGreaterThan(0, $recommendationCount);

        $firstRecommendationId = $this->createRecommendation('Need to Schedule');
        $createResult = $this->creationService->createFromRecommendation(
            $firstRecommendationId,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($createResult['success'], $createResult['error'] ?? json_encode($createResult));

        $bestowalId = (int)$createResult['data']['bestowalId'];
        for ($i = 1; $i < $recommendationCount; $i++) {
            $additionalRecommendationId = $this->createRecommendation('King Approved');
            $this->linkService->linkRecommendations(
                $bestowalId,
                [$additionalRecommendationId],
                self::ADMIN_MEMBER_ID,
            );
        }

        return $bestowalId;
    }

    private function createRecommendation(string $state): int
    {
        $entity = $this->recommendationsTable->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $this->getFirstAwardId(),
            'reason' => 'Bestowal link audit service test',
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
