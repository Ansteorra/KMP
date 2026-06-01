<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Services\BestowalCreationService;
use Awards\Services\BestowalRecommendationLinkService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use RuntimeException;

class BestowalRecommendationLinkServiceTest extends BaseTestCase
{
    private Table $recommendationsTable;
    private Table $bestowalsTable;
    private BestowalCreationService $creationService;
    private BestowalRecommendationLinkService $linkService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        Recommendation::clearCache();
        Bestowal::clearCache();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->bestowalsTable = $this->getTableLocator()->get('Awards.Bestowals');
        $this->creationService = new BestowalCreationService();
        $this->linkService = new BestowalRecommendationLinkService();
    }

    protected function tearDown(): void
    {
        Recommendation::clearCache();
        Bestowal::clearCache();
        parent::tearDown();
    }

    public function testCannotUnlinkOnlyLinkedRecommendation(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A bestowal must keep at least one linked recommendation.');

        $linkedIds = $this->linkService->getLinkedRecommendationIds($bestowalId);
        $this->linkService->unlinkRecommendations($bestowalId, $linkedIds, self::ADMIN_MEMBER_ID);
    }

    public function testCannotUnlinkAllRecommendationsWhenMultipleLinked(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(2);
        $linkedIds = $this->linkService->getLinkedRecommendationIds($bestowalId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A bestowal must keep at least one linked recommendation.');

        $this->linkService->unlinkRecommendations($bestowalId, $linkedIds, self::ADMIN_MEMBER_ID);
    }

    public function testCanUnlinkOneRecommendationWhenMultipleLinked(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(2);
        $linkedIds = $this->linkService->getLinkedRecommendationIds($bestowalId);

        $unlinked = $this->linkService->unlinkRecommendations(
            $bestowalId,
            [$linkedIds[0]],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertSame([$linkedIds[0]], $unlinked);
        $this->assertSame([$linkedIds[1]], $this->linkService->getLinkedRecommendationIds($bestowalId));
    }

    public function testAssertMinimumLinkedRecommendationsAllowsLinkBeforeUnlinkSwap(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(1);
        $linkedIds = $this->linkService->getLinkedRecommendationIds($bestowalId);
        $replacementId = $this->createRecommendation('King Approved');

        $this->linkService->assertMinimumLinkedRecommendations(
            $bestowalId,
            $linkedIds,
            [$replacementId],
        );

        $this->assertTrue(true);
    }

    public function testAssertMinimumLinkedRecommendationsRejectsUnlinkingAllWithoutReplacement(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(2);
        $linkedIds = $this->linkService->getLinkedRecommendationIds($bestowalId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A bestowal must keep at least one linked recommendation.');

        $this->linkService->assertMinimumLinkedRecommendations($bestowalId, $linkedIds, []);
    }

    /**
     * @param int $recommendationCount Number of recommendations to link to the bestowal.
     * @return int Bestowal ID.
     */
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
            'reason' => 'Bestowal link service test',
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
