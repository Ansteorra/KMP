<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;

class AwardsControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testEditCanDisableAward(): void
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $award = $awards->saveOrFail($awards->newEntity([
            'name' => 'Edit Disable Award ' . uniqid(),
            'abbreviation' => 'EDA-' . uniqid(),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => 27,
            'is_active' => true,
        ]));

        $this->post('/awards/awards/edit/' . $award->id, [
            'name' => $award->name,
            'abbreviation' => $award->abbreviation,
            'specialties' => '[]',
            'description' => '',
            'insignia' => '',
            'badge' => '',
            'charter' => '',
            'domain_id' => $award->domain_id,
            'level_id' => $award->level_id,
            'branch_id' => $award->branch_id,
            'is_disabled' => '1',
        ]);

        $this->assertRedirectContains('/awards/awards/view/' . $award->id);

        $updatedAward = $awards->get($award->id);
        $this->assertFalse((bool)$updatedAward->is_active);
    }

    public function testGridDataShowsEnabledColumnAndFiltersDisabledAwards(): void
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $suffix = uniqid();

        $activeAward = $awards->saveOrFail($awards->newEntity([
            'name' => 'Grid Active Award ' . $suffix,
            'abbreviation' => 'GAA-' . $suffix,
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => 27,
            'is_active' => true,
        ]));

        $disabledAward = $awards->saveOrFail($awards->newEntity([
            'name' => 'Grid Disabled Award ' . $suffix,
            'abbreviation' => 'GDA-' . $suffix,
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => 27,
            'is_active' => false,
        ]));

        $this->get('/awards/awards/grid-data?' . http_build_query([
            'search' => $suffix,
            'filter' => [
                'is_active' => '0',
            ],
        ]));

        $this->assertResponseOk();
        $this->assertResponseContains('Enabled');
        $this->assertResponseContains($disabledAward->name);
        $this->assertResponseNotContains($activeAward->name);
    }

    public function testActivityAwardsGridDataUsesSharedGridWithRemoveAction(): void
    {
        $gatheringActivities = $this->getTableLocator()->get('GatheringActivities');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $awardGatheringActivities = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        $suffix = uniqid();

        $gatheringActivity = $gatheringActivities->saveOrFail($gatheringActivities->newEntity([
            'name' => 'Awards Grid Activity ' . $suffix,
            'description' => 'Activity for awards grid test',
        ]));

        $award = $awards->saveOrFail($awards->newEntity([
            'name' => 'Activity Grid Award ' . $suffix,
            'abbreviation' => 'AGA-' . $suffix,
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => 27,
            'is_active' => true,
        ]));

        $awardGatheringActivities->saveOrFail($awardGatheringActivities->newEntity([
            'award_id' => $award->id,
            'gathering_activity_id' => $gatheringActivity->id,
        ]));

        $this->get('/awards/awards/activity-awards-grid-data/' . $gatheringActivity->id);

        $this->assertResponseOk();
        $this->assertResponseContains($award->name);
        $this->assertResponseContains('/awards/awards/remove-activity/' . $award->id . '/' . $gatheringActivity->id);
        $this->assertResponseContains('Enabled');
    }
}
