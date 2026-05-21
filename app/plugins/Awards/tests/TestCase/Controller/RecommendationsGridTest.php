<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Recommendation;

class RecommendationsGridTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testGridDataIncludesAwardLevelFilterAndAppliesIt(): void
    {
        [$matchingAward, $otherAward] = $this->getAwardsWithDifferentLevels();

        $matchingReason = 'award-level-filter-match-' . uniqid();
        $otherReason = 'award-level-filter-other-' . uniqid();

        $this->createRecommendation($matchingAward->id, $matchingReason);
        $this->createRecommendation($otherAward->id, $otherReason);

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'view_id' => 'sys-recs-all',
            'search' => 'award-level-filter-',
            'filter' => [
                'level_name' => (string)$matchingAward->level_id,
            ],
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('Award Level');
        $this->assertResponseContains($matchingReason);
        $this->assertResponseNotContains($otherReason);
    }

    public function testGridDataShowsOrderOfPrecedenceLink(): void
    {
        $appSettings = $this->getTableLocator()->get('AppSettings');
        $members = $this->getTableLocator()->get('Members');
        $awards = $this->getTableLocator()->get('Awards.Awards');

        $appSettings->setAppSetting(
            'Member.ExternalLink.Order of Precedence',
            'https://op.example.test/people/id/{{additional_info->OrderOfPrecedence_Id}}',
            'string',
            false,
        );

        $member = $members->get(self::ADMIN_MEMBER_ID);
        $member->additional_info = ['OrderOfPrecedence_Id' => '424242'];
        $savedMember = $members->save($member);
        $this->assertNotFalse($savedMember, 'Failed to save test member OP data');

        $award = $awards->find()->select(['id'])->firstOrFail();
        $reason = 'op-link-grid-test-' . uniqid();
        $this->createRecommendation($award->id, $reason);

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'view_id' => 'sys-recs-all',
            'search' => $reason,
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('https://op.example.test/people/id/424242');
    }

    /**
     * @return array{0: \Awards\Model\Entity\Award, 1: \Awards\Model\Entity\Award}
     */
    private function getAwardsWithDifferentLevels(): array
    {
        $awards = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id', 'level_id'])
            ->where(['Awards.deleted IS' => null])
            ->orderByAsc('Awards.id')
            ->all()
            ->toList();

        $firstAward = null;
        $secondAward = null;

        foreach ($awards as $award) {
            if ($firstAward === null) {
                $firstAward = $award;
                continue;
            }

            if ($award->level_id !== $firstAward->level_id) {
                $secondAward = $award;
                break;
            }
        }

        $this->assertNotNull($firstAward, 'Expected at least one seeded award');
        $this->assertNotNull($secondAward, 'Expected seeded awards with different levels');

        return [$firstAward, $secondAward];
    }

    private function createRecommendation(int $awardId, string $reason): Recommendation
    {
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $states = Recommendation::getStates();
        $this->assertNotEmpty($states, 'Expected configured recommendation states');

        $rankResult = $recommendations->find()
            ->select(['max_rank' => $recommendations->find()->func()->max('stack_rank')])
            ->disableHydration()
            ->first();
        $maxStackRank = (int)($rankResult['max_rank'] ?? 0);

        $recommendation = $recommendations->newEntity([
            'stack_rank' => $maxStackRank + 1,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'award_id' => $awardId,
            'state' => $states[0],
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@amp.ansteorra.org',
            'contact_number' => '555-555-0100',
            'reason' => $reason,
            'call_into_court' => 'Never',
            'court_availability' => 'Any',
            'created_by' => self::ADMIN_MEMBER_ID,
            'modified_by' => self::ADMIN_MEMBER_ID,
        ]);

        $saved = $recommendations->save($recommendation);
        $this->assertNotFalse($saved, 'Failed to save test recommendation');

        return $saved;
    }
}
