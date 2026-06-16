<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\KMP\TimezoneHelper;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Recommendation;
use Cake\Cache\Cache;
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

    public function testCourtScheduleManagerCanAddScheduledActivity(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $memberId = self::TEST_MEMBER_AGATHA_ID;
        $this->grantCourtSchedulePermission($memberId, (int)$gathering->branch_id);
        $this->authenticateAsMember($memberId);

        $this->post('/gatherings/add-scheduled-activity/' . $gathering->public_id, [
            'start_datetime' => $this->localScheduleInput($gathering, 1),
            'has_end_time' => '0',
            'display_title' => 'Delegated Court Session',
            'description' => 'Created by a court schedule manager.',
            'pre_register' => '0',
            'is_other' => '1',
        ]);

        $this->assertResponseOk();
        $scheduledActivities = $this->getTableLocator()->get('GatheringScheduledActivities');
        $created = $scheduledActivities->find()
            ->where([
                'gathering_id' => $gathering->id,
                'display_title' => 'Delegated Court Session',
                'created_by' => $memberId,
            ])
            ->first();
        $this->assertNotNull($created);
    }

    public function testCourtScheduleManagerCanEditOwnScheduledActivity(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $memberId = self::TEST_MEMBER_AGATHA_ID;
        $scheduledActivity = $this->createScheduledActivity($gathering, $memberId, 'Own Court Session');
        $this->grantCourtSchedulePermission($memberId, (int)$gathering->branch_id);
        $this->authenticateAsMember($memberId);

        $this->post(
            '/gatherings/edit-scheduled-activity/' . $gathering->public_id . '/' . $scheduledActivity->id,
            [
                'start_datetime' => $this->localScheduleInput($gathering, 2),
                'has_end_time' => '0',
                'display_title' => 'Updated Own Court Session',
                'description' => 'Updated by original creator.',
                'pre_register' => '0',
                'is_other' => '1',
            ],
        );

        $this->assertResponseOk();
        $updated = $this->getTableLocator()->get('GatheringScheduledActivities')->get($scheduledActivity->id);
        $this->assertSame('Updated Own Court Session', $updated->display_title);
        $this->assertSame($memberId, (int)$updated->created_by);
    }

    public function testCourtScheduleManagerCannotEditOthersScheduledActivity(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $memberId = self::TEST_MEMBER_AGATHA_ID;
        $scheduledActivity = $this->createScheduledActivity(
            $gathering,
            self::ADMIN_MEMBER_ID,
            'Someone Else Court Session',
        );
        $this->grantCourtSchedulePermission($memberId, (int)$gathering->branch_id);
        $this->authenticateAsMember($memberId);

        $this->post(
            '/gatherings/edit-scheduled-activity/' . $gathering->public_id . '/' . $scheduledActivity->id,
            [
                'start_datetime' => $this->localScheduleInput($gathering, 2),
                'has_end_time' => '0',
                'display_title' => 'Unauthorized Edit Attempt',
                'description' => 'Should not save.',
                'pre_register' => '0',
                'is_other' => '1',
            ],
        );

        $this->assertResponseCode(403);
        $unchanged = $this->getTableLocator()->get('GatheringScheduledActivities')->get($scheduledActivity->id);
        $this->assertSame('Someone Else Court Session', $unchanged->display_title);
    }

    public function testCourtScheduleManagerCannotDeleteScheduledActivity(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $memberId = self::TEST_MEMBER_AGATHA_ID;
        $scheduledActivity = $this->createScheduledActivity($gathering, $memberId, 'Delete Protected Court Session');
        $this->grantCourtSchedulePermission($memberId, (int)$gathering->branch_id);
        $this->authenticateAsMember($memberId);

        $this->post('/gatherings/delete-scheduled-activity/' . $gathering->public_id . '/' . $scheduledActivity->id);

        $this->assertResponseCode(403);
        $exists = $this->getTableLocator()->get('GatheringScheduledActivities')
            ->exists(['id' => $scheduledActivity->id]);
        $this->assertTrue($exists);
    }

    public function testCourtScheduleManagerSeesOnlyDelegatedScheduleControls(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $memberId = self::TEST_MEMBER_AGATHA_ID;
        $this->createScheduledActivity($gathering, $memberId, 'Own Delegated Court');
        $this->createScheduledActivity($gathering, self::ADMIN_MEMBER_ID, 'Other Court');
        $this->grantCourtSchedulePermission($memberId, (int)$gathering->branch_id);
        $this->authenticateAsMember($memberId);

        $this->get('/gatherings/view/' . $gathering->public_id);

        $this->assertResponseOk();
        $this->assertResponseContains('id="nav-schedule-tab"');
        $this->assertResponseContains('Add Scheduled Activity');
        $this->assertResponseContains('aria-label="Edit Own Delegated Court"');
        $this->assertResponseNotContains('aria-label="Edit Other Court"');
        $this->assertResponseNotContains('aria-label="Delete Own Delegated Court"');
        $this->assertResponseNotContains('aria-label="Delete Other Court"');
        $this->assertResponseNotContains('href="/gatherings/edit/');
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

    private function getScheduleTestGathering()
    {
        $gathering = $this->getTableLocator()->get('Gatherings')->find()
            ->where(['branch_id IS NOT' => null])
            ->contain(['GatheringActivities', 'GatheringScheduledActivities'])
            ->first();
        if (!$gathering) {
            $this->markTestSkipped('No gathering with a branch found in seed data');
        }

        return $gathering;
    }

    private function grantCourtSchedulePermission(int $memberId, int $branchId): void
    {
        $permissions = $this->getTableLocator()->get('Permissions');
        $permissionPolicies = $this->getTableLocator()->get('PermissionPolicies');
        $roles = $this->getTableLocator()->get('Roles');
        $connection = $roles->getConnection();

        $permission = $permissions->find()->where(['name' => 'Can Manage Court Schedule'])->first();
        if ($permission === null) {
            $permission = $permissions->newEntity([
                'name' => 'Can Manage Court Schedule',
                'require_active_membership' => false,
                'require_active_background_check' => false,
                'require_min_age' => 0,
                'is_system' => false,
                'is_super_user' => false,
                'requires_warrant' => false,
                'scoping_rule' => 'Branch Only',
            ]);
            $permissions->saveOrFail($permission);
        }

        foreach (
            [
            ['App\\Policy\\GatheringPolicy', 'canCreateScheduledActivity'],
            ['App\\Policy\\GatheringPolicy', 'canEditScheduledActivity'],
            ] as [$policyClass, $policyMethod]
        ) {
            $exists = $permissionPolicies->find()
                ->where([
                    'permission_id' => $permission->id,
                    'policy_class' => $policyClass,
                    'policy_method' => $policyMethod,
                ])
                ->first();
            if ($exists === null) {
                $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                    'permission_id' => $permission->id,
                    'policy_class' => $policyClass,
                    'policy_method' => $policyMethod,
                ]));
            }
        }

        $role = $roles->newEntity([
            'name' => 'Test Court Schedule Manager ' . $memberId . '-' . $branchId,
        ]);
        $roles->saveOrFail($role);

        $connection->execute(
            'INSERT INTO roles_permissions (role_id, permission_id, created, modified, created_by, modified_by)
             VALUES (?, ?, NOW(), NOW(), 1, 1)',
            [(int)$role->id, (int)$permission->id],
        );
        $connection->execute(
            'INSERT INTO member_roles
             (member_id, role_id, branch_id, start_on, expires_on, approver_id, entity_type, created, modified, created_by, modified_by)
             VALUES (?, ?, ?, NOW(), ?, 1, ?, NOW(), NOW(), 1, 1)',
            [$memberId, (int)$role->id, $branchId, '2100-01-01', 'Direct Grant'],
        );

        Cache::delete('permissions_policies' . $memberId, 'member_permissions');
        Cache::delete('member_permissions' . $memberId, 'member_permissions');
    }

    private function createScheduledActivity($gathering, int $createdBy, string $title)
    {
        $scheduledActivities = $this->getTableLocator()->get('GatheringScheduledActivities');
        $scheduledActivity = $scheduledActivities->newEntity([
            'gathering_id' => $gathering->id,
            'start_datetime' => (clone $gathering->start_date)->modify('+1 hour'),
            'has_end_time' => false,
            'display_title' => $title,
            'description' => $title . ' description.',
            'pre_register' => false,
            'is_other' => true,
            'created_by' => $createdBy,
        ]);
        $saved = $scheduledActivities->save($scheduledActivity);
        $this->assertNotFalse($saved, 'Expected scheduled activity fixture to save');

        return $saved;
    }

    private function localScheduleInput($gathering, int $hoursAfterStart): string
    {
        $start = (clone $gathering->start_date)->modify('+' . $hoursAfterStart . ' hours');

        return TimezoneHelper::toUserTimezone($start, null, null, $gathering)->format('Y-m-d\TH:i');
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
