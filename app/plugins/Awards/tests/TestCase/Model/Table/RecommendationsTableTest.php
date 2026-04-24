<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Model\Table;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Table\RecommendationsTable;
use Cake\I18n\DateTime;

class RecommendationsTableTest extends BaseTestCase
{
    private RecommendationsTable $Recommendations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->Recommendations = $this->getTableLocator()->get('Awards.Recommendations');
    }

    public function testSaveRejectsNewRecommendationWithInactiveAward(): void
    {
        $inactiveAward = $this->createAward(false, 'Model Rule Inactive Award');

        $recommendation = $this->Recommendations->newEntity(
            $this->buildRecommendationData($inactiveAward->id, 'reject-inactive-new-' . uniqid('', true)),
        );

        $this->assertFalse($this->Recommendations->save($recommendation));
        $this->assertContains(
            'This award is inactive and can no longer be selected for new recommendations.',
            $recommendation->getError('award_id'),
        );
    }

    public function testSaveAllowsExistingRecommendationToKeepInactiveAwardWhenAwardUnchanged(): void
    {
        $activeAward = $this->createAward(true, 'Model Rule Active Award');

        $recommendation = $this->Recommendations->saveOrFail($this->Recommendations->newEntity(
            $this->buildRecommendationData($activeAward->id, 'keep-inactive-existing-' . uniqid('', true)),
        ));

        $activeAward->is_active = false;
        $this->Recommendations->Awards->saveOrFail($activeAward);

        $recommendation->reason = 'keep-inactive-existing-updated-' . uniqid('', true);

        $savedRecommendation = $this->Recommendations->save($recommendation);

        $this->assertNotFalse($savedRecommendation);
        $this->assertSame($activeAward->id, $savedRecommendation->award_id);
    }

    public function testSaveRejectsExistingRecommendationWhenSwitchingToInactiveAward(): void
    {
        $activeAward = $this->createAward(true, 'Model Rule Current Active Award');
        $inactiveAward = $this->createAward(false, 'Model Rule Target Inactive Award');

        $recommendation = $this->Recommendations->saveOrFail($this->Recommendations->newEntity(
            $this->buildRecommendationData($activeAward->id, 'switch-to-inactive-' . uniqid('', true)),
        ));

        $recommendation->award_id = $inactiveAward->id;

        $this->assertFalse($this->Recommendations->save($recommendation));
        $this->assertContains(
            'This award is inactive and can no longer be selected for new recommendations.',
            $recommendation->getError('award_id'),
        );
    }

    /**
     * @return \Awards\Model\Entity\Award
     */
    private function createAward(bool $isActive, string $namePrefix)
    {
        return $this->Recommendations->Awards->saveOrFail($this->Recommendations->Awards->newEntity([
            'name' => $namePrefix . ' ' . uniqid('', true),
            'abbreviation' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => 27,
            'is_active' => $isActive,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRecommendationData(int $awardId, string $reason): array
    {
        return [
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'award_id' => $awardId,
            'status' => 'To Give',
            'state' => Recommendation::getStatuses()['To Give'][0],
            'state_date' => DateTime::now(),
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@amp.ansteorra.org',
            'contact_number' => '555-555-0100',
            'reason' => $reason,
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ];
    }
}
