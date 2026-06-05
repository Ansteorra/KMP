<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;
use Waivers\Policy\GatheringWaiverPolicy;

/**
 * App\Controller\GatheringsController Test Case
 *
 * Tests CRUD operations, activity selection, waiver requirement determination,
 * and activity locking functionality for gatherings.
 *
 * @uses \App\Controller\GatheringsController
 */
class GatheringsControllerTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\GatheringsController::index()
     */
    public function testIndex(): void
    {
        $this->get('/gatherings');
        $this->assertResponseOk();
        $this->assertResponseContains('Gatherings');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\GatheringsController::view()
     */
    public function testView(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $gatherings->find()->first();
        if (!$gathering) {
            $this->markTestSkipped('No gathering found in seed data');
        }
        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();
    }

    public function testGatheringAwardsTabRendersQuickEditModalWhenRecommendationsExist(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $branches = $this->getTableLocator()->get('Branches');
        $members = $this->getTableLocator()->get('Members');
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');

        $gathering = $gatherings->find()->first();
        $award = $awards->find()->first();
        $branch = $branches->find()->first();
        $member = $members->find()->first();
        if (!$gathering || !$award || !$member || !$branch) {
            $this->markTestSkipped('Required seed data for gathering award recommendation test is unavailable.');
        }

        $branchId = $award->branch_id ?? $member->branch_id ?? $branch->id ?? null;
        if ($branchId === null) {
            $this->markTestSkipped('No branch available for recommendation test data.');
        }

        $status = $this->statusForState('Scheduled');
        if ($status === null) {
            $this->markTestSkipped('Scheduled recommendation state is unavailable.');
        }

        $recommendation = $recommendations->newEntity([
            'requester_id' => (int)$member->id,
            'member_id' => (int)$member->id,
            'branch_id' => (int)$branchId,
            'award_id' => (int)$award->id,
            'gathering_id' => (int)$gathering->id,
            'status' => $status,
            'state' => 'Scheduled',
            'state_date' => DateTime::now(),
            'requester_sca_name' => (string)($member->sca_name ?? 'Requester'),
            'member_sca_name' => (string)($member->sca_name ?? 'Member'),
            'contact_email' => (string)($member->email_address ?? 'test@example.com'),
            'contact_number' => (string)($member->phone_number ?? ''),
            'reason' => 'Quick edit modal regression test recommendation.',
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]);
        $savedRecommendation = $recommendations->save($recommendation);
        $this->assertNotFalse($savedRecommendation);

        $this->get('/gatherings/view/' . $gathering->public_id);

        $this->assertResponseOk();
        $this->assertResponseContains('id="editRecommendationModal"');
    }

    public function testEditClearsGatheringWhenMovedToUnsupportedState(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $branches = $this->getTableLocator()->get('Branches');
        $members = $this->getTableLocator()->get('Members');
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');

        $gathering = $gatherings->find()->first();
        $award = $awards->find()->first();
        $branch = $branches->find()->first();
        $member = $members->find()->first();
        if (!$gathering || !$award || !$member || !$branch) {
            $this->markTestSkipped('Required seed data for gathering state transition test is unavailable.');
        }

        $scheduledStatus = $this->statusForState('Scheduled');
        $closedStatus = $this->statusForState('No Action');
        if ($scheduledStatus === null || $closedStatus === null) {
            $this->markTestSkipped('Required recommendation states are unavailable.');
        }

        $branchId = $award->branch_id ?? $member->branch_id ?? $branch->id ?? null;
        if ($branchId === null) {
            $this->markTestSkipped('No branch available for recommendation test data.');
        }

        $recommendation = $recommendations->newEntity([
            'requester_id' => (int)$member->id,
            'member_id' => (int)$member->id,
            'branch_id' => (int)$branchId,
            'award_id' => (int)$award->id,
            'gathering_id' => (int)$gathering->id,
            'status' => $scheduledStatus,
            'state' => 'Scheduled',
            'state_date' => DateTime::now(),
            'requester_sca_name' => (string)($member->sca_name ?? 'Requester'),
            'member_sca_name' => (string)($member->sca_name ?? 'Member'),
            'contact_email' => (string)($member->email_address ?? 'test@example.com'),
            'contact_number' => (string)($member->phone_number ?? ''),
            'reason' => 'State transition should clear gathering assignment.',
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]);
        $savedRecommendation = $recommendations->save($recommendation);
        $this->assertNotFalse($savedRecommendation);

        $this->post('/awards/recommendations/edit/' . $savedRecommendation->id, [
            'state' => 'No Action',
            'gathering_id' => (string)$gathering->id,
            'close_reason' => 'No action needed',
            'current_page' => '/gatherings/view/' . $gathering->public_id,
        ]);

        $this->assertRedirectContains('/gatherings/view/' . $gathering->public_id);

        $updated = $recommendations->get($savedRecommendation->id);
        $this->assertSame('No Action', $updated->state);
        $this->assertSame($closedStatus, $updated->status);
        $this->assertNull($updated->gathering_id);
    }

    public function testBulkUpdateClearsGatheringWhenMovedToUnsupportedState(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $branches = $this->getTableLocator()->get('Branches');
        $members = $this->getTableLocator()->get('Members');
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');

        $gathering = $gatherings->find()->first();
        $award = $awards->find()->first();
        $branch = $branches->find()->first();
        $member = $members->find()->first();
        if (!$gathering || !$award || !$member || !$branch) {
            $this->markTestSkipped('Required seed data for gathering bulk transition test is unavailable.');
        }

        $scheduledStatus = $this->statusForState('Scheduled');
        $closedStatus = $this->statusForState('No Action');
        if ($scheduledStatus === null || $closedStatus === null) {
            $this->markTestSkipped('Required recommendation states are unavailable.');
        }

        $branchId = $award->branch_id ?? $member->branch_id ?? $branch->id ?? null;
        if ($branchId === null) {
            $this->markTestSkipped('No branch available for recommendation test data.');
        }

        $recommendation = $recommendations->newEntity([
            'requester_id' => (int)$member->id,
            'member_id' => (int)$member->id,
            'branch_id' => (int)$branchId,
            'award_id' => (int)$award->id,
            'gathering_id' => (int)$gathering->id,
            'status' => $scheduledStatus,
            'state' => 'Scheduled',
            'state_date' => DateTime::now(),
            'requester_sca_name' => (string)($member->sca_name ?? 'Requester'),
            'member_sca_name' => (string)($member->sca_name ?? 'Member'),
            'contact_email' => (string)($member->email_address ?? 'test@example.com'),
            'contact_number' => (string)($member->phone_number ?? ''),
            'reason' => 'Bulk state transition should clear gathering assignment.',
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]);
        $savedRecommendation = $recommendations->save($recommendation);
        $this->assertNotFalse($savedRecommendation);

        $this->post('/awards/recommendations/update-states', [
            'ids' => (string)$savedRecommendation->id,
            'newState' => 'No Action',
            'gathering_id' => (string)$gathering->id,
            'close_reason' => 'No action needed',
            'view' => 'Index',
            'status' => 'All',
        ]);

        $this->assertRedirect();

        $updated = $recommendations->get($savedRecommendation->id);
        $this->assertSame('No Action', $updated->state);
        $this->assertSame($closedStatus, $updated->status);
        $this->assertNull($updated->gathering_id);
    }

    private function statusForState(string $state): ?string
    {
        foreach (Recommendation::getStatuses() as $status => $states) {
            if (in_array($state, $states, true)) {
                return $status;
            }
        }

        return null;
    }

    /**
     * Test add method - GET
     *
     * @return void
     * @uses \App\Controller\GatheringsController::add()
     */
    public function testAddGet(): void
    {
        $this->get('/gatherings/add');
        $this->assertResponseOk();
        $this->assertResponseContains('Add Gathering');
    }

    /**
     * Test add method - POST with valid data
     *
     * @return void
     * @uses \App\Controller\GatheringsController::add()
     */
    public function testAddPost(): void
    {
        $this->enableCsrfToken();
        $this->post('/gatherings/add', [
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'New Test Gathering',
            'description' => 'Test description',
            'start_date' => '2025-08-01',
            'end_date' => '2025-08-03',
            'location' => 'Test Location',
        ]);
        $this->assertResponseSuccess();
        $this->assertRedirect();

        // Verify the gathering was created
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $query = $Gatherings->find()->where(['name' => 'New Test Gathering']);
        $this->assertEquals(1, $query->count());
    }

    /**
     * Test add method - POST with activities selected
     *
     * @return void
     * @uses \App\Controller\GatheringsController::add()
     */
    public function testAddPostWithActivities(): void
    {
        $this->enableCsrfToken();
        $this->post('/gatherings/add', [
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'Gathering with Activities',
            'description' => 'Test gathering with selected activities',
            'start_date' => '2025-09-01',
            'end_date' => '2025-09-03',
            'location' => 'Activity Test Location',
            'gathering_activities' => [
                '_ids' => [1, 2],
            ],
        ]);
        $this->assertResponseSuccess();

        // Verify the gathering was created
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $Gatherings->find()
            ->where(['name' => 'Gathering with Activities'])
            ->first();

        $this->assertNotNull($gathering);
    }

    /**
     * Test edit method - GET
     *
     * @return void
     * @uses \App\Controller\GatheringsController::edit()
     */
    public function testEditGet(): void
    {
        $this->get('/gatherings/edit/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Edit Gathering');
    }

    /**
     * Test edit method - POST with valid data
     *
     * @return void
     * @uses \App\Controller\GatheringsController::edit()
     */
    public function testEditPost(): void
    {
        $this->enableCsrfToken();
        $this->post('/gatherings/edit/1', [
            'name' => 'Updated Spring Crown',
            'description' => 'Updated description',
            'start_date' => '2025-05-15',
            'end_date' => '2025-05-17',
            'location' => 'Updated Location',
        ]);
        $this->assertResponseSuccess();

        // Verify the gathering was updated
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $Gatherings->get(1);
        $this->assertEquals('Updated Spring Crown', $gathering->name);
        $this->assertRedirect(['action' => 'view', $gathering->public_id]);
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\GatheringsController::delete()
     */
    public function testDelete(): void
    {
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $Gatherings->find()->orderBy(['id' => 'DESC'])->first();
        if (!$gathering) {
            $this->markTestSkipped('No gathering found in seed data');
        }

        $this->enableCsrfToken();
        $this->post('/gatherings/delete/' . $gathering->id);
        $this->assertResponseSuccess();
        $this->assertRedirect(['action' => 'index']);
    }

    /**
     * Test date validation - end_date must be >= start_date
     *
     * @return void
     * @uses \App\Controller\GatheringsController::add()
     */
    public function testDateValidation(): void
    {
        $this->enableCsrfToken();
        $this->post('/gatherings/add', [
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'Invalid Date Gathering',
            'description' => 'End date before start date',
            'start_date' => '2025-08-10',
            'end_date' => '2025-08-05', // Before start_date
            'location' => 'Test Location',
        ]);

        // Verify the gathering was NOT created (validation failure)
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $query = $Gatherings->find()->where(['name' => 'Invalid Date Gathering']);
        $this->assertEquals(0, $query->count());
    }

    /**
     * Test multi-day gathering date ranges (T121)
     *
     * @return void
     * @uses \App\Controller\GatheringsController::add()
     */
    public function testMultiDayGatheringDateRanges(): void
    {
        $this->enableCsrfToken();
        $this->post('/gatherings/add', [
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'Long Multi-Day Event',
            'description' => 'Week-long gathering',
            'start_date' => '2025-08-01',
            'end_date' => '2025-08-07', // 7 days
            'location' => 'Test Location',
        ]);
        $this->assertResponseSuccess();

        // Verify the gathering was created
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $Gatherings->find()->where(['name' => 'Long Multi-Day Event'])->first();
        $this->assertNotNull($gathering);
    }

    /**
     * Test required waiver consolidation (T122)
     * When multiple activities require the same waiver type, it should appear once
     *
     * @return void
     * @uses \App\Controller\GatheringsController::view()
     */
    public function testRequiredWaiverConsolidation(): void
    {
        // Find a gathering that has both activities and waivers
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');

        $waiver = $GatheringWaivers->find()
            ->contain(['Gatherings'])
            ->first();
        if (!$waiver || !$waiver->gathering) {
            $this->markTestSkipped('No gathering with waivers found in seed data');
        }

        $gathering = $Gatherings->get($waiver->gathering_id);
        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();

        // The view should load successfully with activities tab content
        $this->assertResponseContains('Activities');
    }

    /**
     * Test calendar grid returns all events in the requested range.
     *
     * The calendar view is bounded by date range and intentionally disables
     * Dataverse pagination so dense months are not capped at the old 200 rows.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::calendarGridData()
     */
    public function testCalendarGridDataReturnsMoreThanDefaultPageLimit(): void
    {
        $Gatherings = $this->getTableLocator()->get('Gatherings');
        $eventCount = 205;
        $records = [];

        for ($i = 1; $i <= $eventCount; $i++) {
            $day = (($i - 1) % 31) + 1;
            $hour = 8 + (($i - 1) % 10);
            $records[] = [
                'public_id' => sprintf('pg%06d', $i),
                'branch_id' => 2,
                'gathering_type_id' => 1,
                'name' => sprintf('Pagination Off Calendar Event %03d', $i),
                'description' => 'Calendar pagination regression test event.',
                'start_date' => sprintf('2099-12-%02d %02d:00:00', $day, $hour),
                'end_date' => sprintf('2099-12-%02d %02d:30:00', $day, $hour),
                'location' => 'Calendar Pagination Test Site',
                'timezone' => 'America/Chicago',
                'public_page_enabled' => true,
                'created_by' => self::ADMIN_MEMBER_ID,
            ];
        }

        $entities = $Gatherings->newEntities($records);
        $this->assertCount($eventCount, $Gatherings->saveManyOrFail($entities));

        $this->configRequest([
            'headers' => ['Turbo-Frame' => 'gatherings-calendar-grid-table'],
        ]);
        $this->get('/gatherings/calendar-grid-data?view=month&year=2099&month=12');

        $this->assertResponseOk();
        $this->assertResponseContains('Pagination Off Calendar Event 001');
        $this->assertResponseContains('Pagination Off Calendar Event 200');
        $this->assertResponseContains('Pagination Off Calendar Event 205');
    }

    public function testPublicLandingGroupsScheduleByGatheringTimezoneDate(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $scheduledActivities = $this->getTableLocator()->get('GatheringScheduledActivities');

        $gathering = $gatherings->newEntity([
            'public_id' => 'tzpub001',
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'Timezone Grouping Public Test Event',
            'description' => 'Verifies public schedule date grouping.',
            'start_date' => '2099-12-01 00:00:00',
            'end_date' => '2099-12-03 12:00:00',
            'location' => 'Timezone Test Site',
            'timezone' => 'America/Los_Angeles',
            'public_page_enabled' => true,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);
        $savedGathering = $gatherings->save($gathering);
        $this->assertNotFalse($savedGathering);

        $scheduled = $scheduledActivities->newEntity([
            'gathering_id' => $savedGathering->id,
            'start_datetime' => '2099-12-02 01:30:00',
            'end_datetime' => '2099-12-02 02:00:00',
            'has_end_time' => true,
            'display_title' => 'Late Night Activity',
            'description' => 'Crosses local day boundary from UTC.',
            'pre_register' => false,
            'is_other' => true,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);
        $savedScheduled = $scheduledActivities->save($scheduled);
        $this->assertNotFalse($savedScheduled);

        $this->get('/gatherings/public-landing/' . $savedGathering->public_id);
        $this->assertResponseOk();
        $this->assertResponseContains('Tuesday, December 1, 2099');
        $this->assertResponseNotContains('Wednesday, December 2, 2099');
        $this->assertResponseContains('5:30 PM');
    }

    /**
     * Test activity management remains available when waivers are uploaded.
     *
     * When waivers have been uploaded for a gathering, editors can still add
     * activities because late activity additions may introduce new waiver needs,
     * but removals remain locked to preserve waiver context.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::view()
     */
    public function testActivityManagementAvailableWhenWaiversUploaded(): void
    {
        $gathering = $this->getGatheringWithUploadedWaivers();
        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();

        $this->assertResponseContains('Add Activity');
        $this->assertResponseContains('addActivityModal');
    }

    /**
     * Test adding an activity remains allowed when waivers are uploaded.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::addActivity()
     */
    public function testAddActivityAllowedWhenWaiversUploaded(): void
    {
        $gathering = $this->getGatheringWithUploadedWaivers();
        $activity = $this->getActivityNotLinkedToGathering((int)$gathering->id);
        $links = $this->getTableLocator()->get('GatheringsGatheringActivities');

        $beforeCount = $links->find()
            ->where([
                'gathering_id' => $gathering->id,
                'gathering_activity_id' => $activity->id,
            ])
            ->count();
        $this->assertSame(0, $beforeCount);

        $this->post('/gatherings/add-activity/' . $gathering->id, [
            'activity_id' => (string)$activity->id,
            'custom_description' => 'Late-added activity that now needs waiver coverage.',
        ]);

        $this->assertRedirect(['action' => 'view', $gathering->public_id]);

        $link = $links->find()
            ->where([
                'gathering_id' => $gathering->id,
                'gathering_activity_id' => $activity->id,
            ])
            ->first();
        $this->assertNotNull($link);
        $this->assertSame('Late-added activity that now needs waiver coverage.', $link->custom_description);
    }

    /**
     * Test policy blocks removing an activity when it is the last one requiring submitted waivers.
     *
     * @return void
     * @uses \Waivers\Policy\GatheringWaiverPolicy::canRemoveGatheringActivity()
     */
    public function testRemoveActivityBlockedWhenLastRequiredWaiverActivity(): void
    {
        $gathering = $this->getGatheringWithUploadedWaivers();
        $waiverTypeId = $this->getSubmittedWaiverTypeIdForGathering((int)$gathering->id);
        $activity = $this->getLinkedActivityForGathering((int)$gathering->id);
        $this->ensureActivityIsOnlyWaiverRequirement((int)$gathering->id, (int)$activity->id, $waiverTypeId);

        $waiverPolicy = new GatheringWaiverPolicy();
        $authorizationEntity = $this->getTableLocator()->get('Waivers.GatheringWaivers')->newEmptyEntity();
        $authorizationEntity->gathering_id = (int)$gathering->id;
        $authorizationEntity->gathering = $gathering;

        $this->assertFalse(
            $waiverPolicy->canRemoveGatheringActivity(
                $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID),
                $authorizationEntity,
                (int)$activity->id,
            ),
        );
    }

    /**
     * Test removing an activity is allowed when another activity still requires submitted waivers.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::removeActivity()
     */
    public function testRemoveActivityAllowedWhenAnotherActivityStillRequiresSubmittedWaiver(): void
    {
        $gathering = $this->getGatheringWithUploadedWaivers();
        $waiverTypeId = $this->getSubmittedWaiverTypeIdForGathering((int)$gathering->id);
        $activityToRemove = $this->getLinkedActivityForGathering((int)$gathering->id);
        $links = $this->getTableLocator()->get('GatheringsGatheringActivities');

        $otherActivity = $this->getActivityNotLinkedToGathering((int)$gathering->id);
        $newLink = $links->newEntity([
            'gathering_id' => $gathering->id,
            'gathering_activity_id' => $otherActivity->id,
            'sort_order' => 999,
        ]);
        $this->assertNotFalse($links->save($newLink));

        $this->ensureActivitiesShareWaiverRequirement(
            (int)$gathering->id,
            (int)$activityToRemove->id,
            (int)$otherActivity->id,
            $waiverTypeId,
        );

        $beforeCount = $links->find()
            ->where([
                'gathering_id' => $gathering->id,
                'gathering_activity_id' => $activityToRemove->id,
            ])
            ->count();
        $this->assertSame(1, $beforeCount);

        $this->post('/gatherings/remove-activity/' . $gathering->id . '/' . $activityToRemove->id);

        $this->assertRedirect(['action' => 'view', $gathering->public_id]);

        $afterCount = $links->find()
            ->where([
                'gathering_id' => $gathering->id,
                'gathering_activity_id' => $activityToRemove->id,
            ])
            ->count();
        $this->assertSame(0, $afterCount);
    }

    /**
     * Test authorization - unauthenticated user
     *
     * @return void
     * @uses \App\Controller\GatheringsController::index()
     */
    public function testIndexUnauthenticated(): void
    {
        $this->session(['Auth' => null]);
        $this->get('/gatherings');
        $this->assertRedirect();
    }

    private function getGatheringWithUploadedWaivers()
    {
        $GatheringWaivers = $this->getTableLocator()->get('Waivers.GatheringWaivers');
        $Gatherings = $this->getTableLocator()->get('Gatherings');

        $waiver = $GatheringWaivers->find()
            ->contain(['Gatherings'])
            ->first();
        if (!$waiver || !$waiver->gathering) {
            $this->markTestSkipped('No gathering with waivers found in seed data');
        }

        return $Gatherings->get($waiver->gathering_id, contain: ['GatheringActivities']);
    }

    private function getActivityNotLinkedToGathering(int $gatheringId)
    {
        $links = $this->getTableLocator()->get('GatheringsGatheringActivities');
        $linkedActivityIds = $links->find()
            ->select(['gathering_activity_id'])
            ->where(['gathering_id' => $gatheringId])
            ->all()
            ->extract('gathering_activity_id')
            ->toList();

        $activities = $this->getTableLocator()->get('GatheringActivities');
        $query = $activities->find()->orderBy(['id' => 'ASC']);
        if (!empty($linkedActivityIds)) {
            $query->where(['id NOT IN' => $linkedActivityIds]);
        }

        $activity = $query->first();
        if (!$activity) {
            $this->markTestSkipped('No unlinked gathering activity available in seed data');
        }

        return $activity;
    }

    private function getLinkedActivityForGathering(int $gatheringId)
    {
        $links = $this->getTableLocator()->get('GatheringsGatheringActivities');
        $link = $links->find()
            ->where(['gathering_id' => $gatheringId])
            ->first();

        if (!$link) {
            $activity = $this->getActivityNotLinkedToGathering($gatheringId);
            $link = $links->newEntity([
                'gathering_id' => $gatheringId,
                'gathering_activity_id' => $activity->id,
                'sort_order' => 999,
            ]);
            $this->assertNotFalse($links->save($link));
        }

        return $this->getTableLocator()->get('GatheringActivities')->get($link->gathering_activity_id);
    }

    private function getSubmittedWaiverTypeIdForGathering(int $gatheringId): int
    {
        $waiver = $this->getTableLocator()
            ->get('Waivers.GatheringWaivers')
            ->find()
            ->where(['gathering_id' => $gatheringId])
            ->orderBy(['id' => 'ASC'])
            ->first();

        if (!$waiver || !$waiver->waiver_type_id) {
            $this->markTestSkipped('No submitted waiver type found for gathering');
        }

        return (int)$waiver->waiver_type_id;
    }

    private function ensureActivityIsOnlyWaiverRequirement(int $gatheringId, int $activityId, int $waiverTypeId): void
    {
        $links = $this->getTableLocator()->get('GatheringsGatheringActivities');
        $linkedActivityIds = $links->find()
            ->select(['gathering_activity_id'])
            ->where(['gathering_id' => $gatheringId])
            ->all()
            ->extract('gathering_activity_id')
            ->toList();

        $requirements = $this->getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $requirements->deleteAll([
            'waiver_type_id' => $waiverTypeId,
            'gathering_activity_id IN' => $linkedActivityIds,
        ]);

        $requirement = $requirements->newEntity([
            'gathering_activity_id' => $activityId,
            'waiver_type_id' => $waiverTypeId,
        ]);
        $this->assertNotFalse($requirements->save($requirement));
    }

    private function ensureActivitiesShareWaiverRequirement(
        int $gatheringId,
        int $activityId,
        int $otherActivityId,
        int $waiverTypeId,
    ): void {
        $this->ensureActivityIsOnlyWaiverRequirement($gatheringId, $activityId, $waiverTypeId);

        $requirements = $this->getTableLocator()->get('Waivers.GatheringActivityWaivers');
        $otherRequirement = $requirements->newEntity([
            'gathering_activity_id' => $otherActivityId,
            'waiver_type_id' => $waiverTypeId,
        ]);
        $this->assertNotFalse($requirements->save($otherRequirement));
    }
}
