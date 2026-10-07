<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function testAddingActivityAwardPreservesRefererGridQuery(): void
    {
        $fixture = $this->createActivityAwardsFixture(21);
        $query = $this->activityAwardsQuery($fixture['search']);
        $this->configRequest(['headers' => [
            'Accept' => 'text/vnd.turbo-stream.html',
            'Referer' => 'http://localhost/gathering-activities/view/' . $fixture['activityId']
                . '?' . http_build_query($query),
        ]]);

        $this->post('/awards/awards/add-activity-to-gathering-activity/' . $fixture['activityId'], [
            'award_id' => $fixture['availableAwardId'],
        ]);

        $url = $this->assertActivityAwardsRefreshQuery($fixture['activityId'], $query);
        $this->assertResponseContains('The award has been added to this activity.');
        $this->get($url);
        $this->assertResponseOk();
        $this->assertResponseContains('Page 3 of 3, showing 2 record(s) out of 22 total');
        $this->assertResponseContains('"pageSize":10');
    }

    public function testRemovingActivityAwardPreservesPostedGridQuery(): void
    {
        $fixture = $this->createActivityAwardsFixture(31);
        $query = $this->activityAwardsQuery($fixture['search']);
        $this->configRequest(['headers' => [
            'Accept' => 'text/vnd.turbo-stream.html',
            'Referer' => 'http://localhost/gathering-activities/view/' . $fixture['activityId'] . '?search=stale',
        ]]);

        $this->post('/awards/awards/remove-activity/' . $fixture['awardIds'][0] . '/' . $fixture['activityId'], [
            'page_context_url' => '/gathering-activities/view/' . $fixture['activityId']
                . '?' . http_build_query($query),
        ]);

        $url = $this->assertActivityAwardsRefreshQuery($fixture['activityId'], $query);
        $this->assertResponseContains('The activity has been removed from this award.');
        $this->get($url);
        $this->assertResponseOk();
        $this->assertResponseContains('Page 3 of 3, showing 10 record(s) out of 30 total');
    }

    public function testActivityAwardValidationFailurePreservesGridQuery(): void
    {
        $fixture = $this->createActivityAwardsFixture(21);
        $query = $this->activityAwardsQuery($fixture['search']);
        $this->configRequest(['headers' => [
            'Accept' => 'text/vnd.turbo-stream.html',
            'Referer' => 'http://localhost/gathering-activities/view/' . $fixture['activityId']
                . '?' . http_build_query($query),
        ]]);

        $this->post('/awards/awards/add-activity-to-gathering-activity/' . $fixture['activityId'], []);

        $this->assertActivityAwardsRefreshQuery($fixture['activityId'], $query);
        $this->assertResponseContains('Please select an award.');
    }

    public function testNativeActivityAwardRemovalReturnsToOriginatingGrid(): void
    {
        $fixture = $this->createActivityAwardsFixture(1);
        $context = '/gathering-activities/view/' . $fixture['activityId']
            . '?' . http_build_query($this->activityAwardsQuery($fixture['search']));
        $this->configRequest(['headers' => ['Referer' => 'http://localhost' . $context]]);

        $this->post('/awards/awards/remove-activity/' . $fixture['awardIds'][0] . '/' . $fixture['activityId']);

        $this->assertRedirect($context);
        $this->assertFalse($this->getTableLocator()->get('Awards.AwardGatheringActivities')->exists([
            'award_id' => $fixture['awardIds'][0],
            'gathering_activity_id' => $fixture['activityId'],
        ]));
    }

    public function testNativeActivityAwardAdditionReturnsToOriginatingGrid(): void
    {
        $fixture = $this->createActivityAwardsFixture(1);
        $context = '/gathering-activities/view/' . $fixture['activityId'] . '?search=needle&limit=10';
        $this->configRequest(['headers' => ['Referer' => 'http://localhost' . $context]]);

        $this->post('/awards/awards/add-activity-to-gathering-activity/' . $fixture['activityId'], [
            'award_id' => $fixture['availableAwardId'],
        ]);

        $this->assertRedirect($context);
        $this->assertTrue($this->getTableLocator()->get('Awards.AwardGatheringActivities')->exists([
            'award_id' => $fixture['availableAwardId'],
            'gathering_activity_id' => $fixture['activityId'],
        ]));
    }

    #[DataProvider('foreignActivityAwardsRefererProvider')]
    public function testActivityAwardRefreshIgnoresForeignReferer(string $referer): void
    {
        $fixture = $this->createActivityAwardsFixture(1);
        $this->configRequest(['headers' => [
            'Accept' => 'text/vnd.turbo-stream.html',
            'Referer' => $referer,
        ]]);

        $this->post('/awards/awards/add-activity-to-gathering-activity/' . $fixture['activityId'], []);

        $this->assertActivityAwardsRefreshQuery($fixture['activityId'], []);
    }

    public static function foreignActivityAwardsRefererProvider(): array
    {
        return [
            'other host' => ['http://example.test/gathering-activities/view/1?search=foreign'],
            'other scheme' => ['https://localhost/gathering-activities/view/1?search=foreign'],
            'other port' => ['http://localhost:8080/gathering-activities/view/1?search=foreign'],
            'protocol relative' => ['//localhost/gathering-activities/view/1?search=foreign'],
        ];
    }

    public function testNativeActivityAwardRemovalKeepsFallbackForUnrelatedLocalContext(): void
    {
        $fixture = $this->createActivityAwardsFixture(1);
        $this->configRequest(['headers' => ['Referer' => 'http://localhost/members/view/1?search=unrelated']]);

        $this->post('/awards/awards/remove-activity/' . $fixture['awardIds'][0] . '/' . $fixture['activityId']);

        $this->assertRedirect('/awards/awards/view/' . $fixture['awardIds'][0]);
    }

    public function testInvalidPostedActivityAwardsContextIsRejectedBeforeMutation(): void
    {
        $fixture = $this->createActivityAwardsFixture(1);
        $this->post('/awards/awards/remove-activity/' . $fixture['awardIds'][0] . '/' . $fixture['activityId'], [
            'page_context_url' => 'https://example.test/away',
        ]);

        $this->assertResponseCode(400);
        $this->assertTrue($this->getTableLocator()->get('Awards.AwardGatheringActivities')->exists([
            'award_id' => $fixture['awardIds'][0],
            'gathering_activity_id' => $fixture['activityId'],
        ]));
    }

    /** Create enough associated awards to exercise real pagination after a mutation. */
    private function createActivityAwardsFixture(int $count): array
    {
        $gatheringActivities = $this->getTableLocator()->get('GatheringActivities');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $associations = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        $seedAward = $awards->find()->firstOrFail();
        $search = 'ActivityPagination-' . uniqid();
        $activity = $gatheringActivities->saveOrFail($gatheringActivities->newEntity([
            'name' => $search,
            'description' => 'Awards pagination test activity',
        ]));
        $awardIds = [];
        for ($index = 0; $index <= $count; $index++) {
            $award = $awards->saveOrFail($awards->newEntity([
                'name' => $search . ' Award ' . sprintf('%02d', $index),
                'abbreviation' => $search . '-' . $index,
                'domain_id' => $seedAward->domain_id,
                'level_id' => $seedAward->level_id,
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'is_active' => true,
            ]));
            if ($index < $count) {
                $awardIds[] = (int)$award->id;
                $associations->saveOrFail($associations->newEntity([
                    'award_id' => $award->id,
                    'gathering_activity_id' => $activity->id,
                ]));
            }
        }

        return [
            'activityId' => (int)$activity->id,
            'awardIds' => $awardIds,
            'availableAwardId' => (int)$award->id,
            'search' => $search,
        ];
    }

    private function activityAwardsQuery(string $search): array
    {
        return [
            'tab' => 'activity-awards',
            'page' => '3',
            'search' => $search,
            'limit' => '10',
            'filter' => ['is_active' => '1'],
            'sort' => 'name',
            'direction' => 'desc',
        ];
    }

    /** Assert the replacement cell reloads the same query, then return its data URL. */
    private function assertActivityAwardsRefreshQuery(int $activityId, array $expected): string
    {
        $this->assertResponseOk();
        $this->assertSame('text/vnd.turbo-stream.html', $this->_response->getType());
        $this->assertResponseContains('<turbo-stream action="replace" target="activity-awards-' . $activityId . '">');
        $this->assertSame(1, preg_match(
            '/<turbo-frame id="activity-awards-grid-' . $activityId . '" src="([^"]+)"/',
            (string)$this->_response->getBody(),
            $matches,
        ));
        $url = html_entity_decode($matches[1], ENT_QUOTES);
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $actual);
        $this->assertSame($expected, $actual);

        return $url;
    }
}
