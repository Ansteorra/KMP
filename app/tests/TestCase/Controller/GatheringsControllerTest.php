<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\KMP\TimezoneHelper;
use App\Model\Entity\ActionItem;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Model\Entity\CourtAgendaItem;
use Awards\Model\Entity\CourtAgendaSegment;
use Cake\Cache\Cache;
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
     * Test grid table frame keeps column metadata when date/type filters are applied.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::gridData()
     */
    public function testGridDataTableFrameRendersColumnsWithFilteredRows(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $gatherings->newEntity([
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'Grid Frame Column Regression',
            'description' => 'Verifies filtered table-frame responses render columns.',
            'start_date' => '2026-07-15 10:00:00',
            'end_date' => '2026-07-15 12:00:00',
            'location' => 'Grid Test Site',
            'timezone' => 'America/Chicago',
            'public_page_enabled' => true,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);
        $gatherings->saveOrFail($gathering);

        $this->configRequest([
            'headers' => ['Turbo-Frame' => 'gatherings-grid-table'],
        ]);
        $this->get(
            '/gatherings/grid-data?start_date_start=2026-07-01&start_date_end=2026-07-31'
            . '&filter%5Bgathering_type_id%5D%5B%5D=1&dirty%5Bfilters%5D=1',
        );

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('data-column-key="name"', $body);
        $this->assertStringContainsString('data-column-key="gathering_type_id"', $body);
        $this->assertResponseContains('Grid Frame Column Regression');
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

    /**
     * The calendar list view shows the pre-register link when open and hides
     * it once pre-registration has closed.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::calendarGridData()
     */
    public function testCalendarListPreregisterLinkVisibility(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $open = $gatherings->saveOrFail($gatherings->newEntity([
            'public_id' => 'prgopen1',
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'Prereg Open Calendar Event',
            'start_date' => '2099-11-10 10:00:00',
            'end_date' => '2099-11-10 16:00:00',
            'timezone' => 'America/Chicago',
            'preregister_url' => 'https://ex.test/open-prereg',
            'preregister_closes_on' => '2099-11-01',
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $closed = $gatherings->saveOrFail($gatherings->newEntity([
            'public_id' => 'prgclsd1',
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'Prereg Closed Calendar Event',
            'start_date' => '2099-11-12 10:00:00',
            'end_date' => '2099-11-12 16:00:00',
            'timezone' => 'America/Chicago',
            'preregister_url' => 'https://ex.test/closed-prereg',
            // close date already in the past
            'preregister_closes_on' => '2000-01-01',
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));

        $this->configRequest([
            'headers' => ['Turbo-Frame' => 'gatherings-calendar-grid-table'],
        ]);
        $this->get('/gatherings/calendar-grid-data?view=list&year=2099&month=11');

        $this->assertResponseOk();
        $this->assertResponseContains('https://ex.test/open-prereg');
        $this->assertResponseNotContains('https://ex.test/closed-prereg');
    }

    /**
     * The calendar quick-view modal shows the pre-register link when open.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::quickView()
     */
    public function testQuickViewShowsOpenPreregisterLink(): void
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $gatherings->saveOrFail($gatherings->newEntity([
            'public_id' => 'prgqv001',
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'Prereg Quick View Event',
            'start_date' => '2099-11-20 10:00:00',
            'end_date' => '2099-11-20 16:00:00',
            'timezone' => 'America/Chicago',
            'preregister_url' => 'https://ex.test/qv-prereg',
            'preregister_closes_on' => '2099-11-15',
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));

        $this->get('/gatherings/quick-view/' . $gathering->public_id);

        $this->assertResponseOk();
        $this->assertResponseContains('https://ex.test/qv-prereg');
        $this->assertResponseContains('Pre-Register');
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

    public function testDeleteScheduledActivityClearsBestowalCourtAssignments(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $scheduledActivity = $this->createScheduledActivity(
            $gathering,
            self::ADMIN_MEMBER_ID,
            'Court Slot to Delete',
        );
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $award->id,
            'gathering_id' => $gathering->id,
            'gathering_scheduled_activity_id' => $scheduledActivity->id,
            'lifecycle_status' => Bestowal::LIFECYCLE_OPEN,
            'source' => Bestowal::SOURCE_AD_HOC,
            'stack_rank' => 10,
        ]));
        $agendas = $this->getTableLocator()->get('Awards.CourtAgendas');
        $agenda = $agendas->saveOrFail($agendas->newEntity([
            'gathering_id' => $gathering->id,
            'name' => 'Delete Court Slot Agenda',
            'is_default' => true,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $segments = $this->getTableLocator()->get('Awards.CourtAgendaSegments');
        $segment = $segments->saveOrFail($segments->newEntity([
            'court_agenda_id' => $agenda->id,
            'gathering_scheduled_activity_id' => $scheduledActivity->id,
            'name' => 'Court Slot to Delete',
            'court_type' => CourtAgendaSegment::TYPE_COURT,
            'sort_order' => 10,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $items = $this->getTableLocator()->get('Awards.CourtAgendaItems');
        $item = $items->saveOrFail($items->newEntity([
            'court_agenda_segment_id' => $segment->id,
            'bestowal_id' => $bestowal->id,
            'item_type' => CourtAgendaItem::TYPE_BESTOWAL,
            'role' => CourtAgendaItem::ROLE_PRESENT,
            'sort_order' => 10,
            'estimated_minutes' => 5,
            'duration_locked' => false,
            'include_reasons' => true,
            'include_specialties' => true,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));
        $actionItems = $this->getTableLocator()->get('ActionItems');
        $addedToAgendaTodo = $actionItems->saveOrFail($actionItems->newEntity([
            'entity_type' => Bestowal::ACTION_ITEM_ENTITY_TYPE,
            'entity_id' => (int)$bestowal->id,
            'title' => 'Added to Agenda',
            'assignee_type' => ActionItem::ASSIGNEE_TYPE_MEMBER,
            'assignee_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => ActionItem::STATUS_COMPLETED,
            'completed_by' => self::ADMIN_MEMBER_ID,
            'is_gating' => true,
            'sort_order' => 20,
            'source_ref' => BestowalTodoTemplateItem::ITEM_KEY_ADDED_TO_AGENDA,
            'completion_config' => [
                ActionItem::COMPLETION_CONFIG_AUTO_COMPLETE => true,
                'required_fields' => [
                    [
                        'provider' => BestowalTodoTemplateItem::COMPLETION_PROVIDER_BESTOWAL_COURT_SLOT,
                        'field' => BestowalTodoTemplateItem::REQUIRED_FIELD_COURT_SLOT,
                    ],
                ],
            ],
        ]));

        $this->post('/gatherings/delete-scheduled-activity/' . $gathering->public_id . '/' . $scheduledActivity->id);

        $this->assertRedirect(['action' => 'view', $gathering->public_id]);
        $updatedBestowal = $bestowals->get((int)$bestowal->id);
        $this->assertNull($updatedBestowal->gathering_scheduled_activity_id);
        $this->assertFalse((bool)$updatedBestowal->roaming_court);
        $this->assertFalse($segments->exists(['id' => (int)$segment->id]));
        $this->assertFalse($items->exists(['id' => (int)$item->id]));
        $this->assertTrue($actionItems->get((int)$addedToAgendaTodo->id)->isOpen());
        $this->assertFalse(
            $this->getTableLocator()->get('GatheringScheduledActivities')->exists(['id' => $scheduledActivity->id]),
        );
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

    /**
     * Create a future gathering for public-calendar tests.
     */
    private function createCalendarGathering(string $name, bool $published, array $extra = []): object
    {
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $gatherings->newEntity($extra + [
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => $name,
            'start_date' => '2026-09-15 10:00:00',
            'end_date' => '2026-09-15 18:00:00',
            'timezone' => 'America/Chicago',
            'created_by' => self::ADMIN_MEMBER_ID,
        ]);
        $gatherings->saveOrFail($gathering);
        if ($published) {
            // published is guarded against mass assignment; set explicitly
            $gathering->set('published', true);
            $gatherings->saveOrFail($gathering);
        }

        return $gathering;
    }

    /**
     * The public kingdom calendar is unauthenticated and only lists published events.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publicCalendar()
     */
    public function testPublicCalendarListsOnlyPublishedEvents(): void
    {
        $published = $this->createCalendarGathering('Published Kingdom Event Alpha', true);
        $unpublished = $this->createCalendarGathering('Unpublished Draft Event Beta', false);

        $this->session(['Auth' => null]);
        $this->get('/events');

        $this->assertResponseOk();
        $this->assertResponseContains('Kingdom Calendar');
        $this->assertResponseContains($published->name);
        $this->assertResponseNotContains($unpublished->name);
    }

    /**
     * Royal progress attendances render on the public calendar with the
     * office snapshot title.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publicCalendar()
     */
    public function testPublicCalendarShowsRoyalProgress(): void
    {
        $published = $this->createCalendarGathering('Progress Kingdom Event Gamma', true);

        $attendances = $this->getTableLocator()->get('GatheringAttendances');
        $attendance = $attendances->newEntity([
            'gathering_id' => $published->id,
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'share_with_kingdom' => true,
            'created_by' => self::TEST_MEMBER_AGATHA_ID,
        ]);
        // Progress fields are guarded; set them the way applyRoyalProgress does
        $attendance->set('is_royal_progress', true);
        $attendance->set('progress_office_name', 'Crown');
        $attendance->set('progress_branch_name', 'Test Kingdom');
        $attendances->saveOrFail($attendance);

        $this->session(['Auth' => null]);
        $this->get('/events');

        $this->assertResponseOk();
        $this->assertResponseContains('Crown of Test Kingdom');
    }

    /**
     * Link an activity to a gathering through the join table.
     */
    private function linkActivity(object $gathering, string $activityName, bool $isCircle = false): object
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');
        $activity = $activities->find()->where(['name' => $activityName])->first();
        if (!$activity) {
            $activity = $activities->saveOrFail($activities->newEntity([
                'name' => $activityName,
                'is_circle' => $isCircle,
            ]));
        }

        $links = $this->getTableLocator()->get('GatheringsGatheringActivities');
        $links->saveOrFail($links->newEntity([
            'gathering_id' => $gathering->id,
            'gathering_activity_id' => $activity->id,
            'sort_order' => 1,
        ]));

        return $activity;
    }

    /**
     * The public calendar can be filtered by activity - circles are just
     * activities, so this covers the circles facet too.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publicCalendar()
     */
    public function testPublicCalendarFiltersByActivity(): void
    {
        $withCircle = $this->createCalendarGathering('Circle Event Theta', true);
        $without = $this->createCalendarGathering('No Circle Event Iota', true);
        $circleActivity = $this->linkActivity($withCircle, 'Pelican Circle');

        $this->session(['Auth' => null]);
        $this->get('/events?activities[]=' . $circleActivity->id);

        $this->assertResponseOk();
        $this->assertResponseContains($withCircle->name);
        $this->assertResponseNotContains($without->name);
        // The filter bar lists the activity as an option
        $this->assertResponseContains('Pelican Circle');
    }

    /**
     * The order-circle icon is driven by the activity's is_circle flag, not by
     * its name: a flagged activity gets the icon even without "circle" in the
     * name, and a "circle"-named activity that is not flagged does not.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publicCalendar()
     */
    public function testPublicCalendarCircleIconUsesFlagNotName(): void
    {
        $flagged = $this->createCalendarGathering('Flagged Circle Event Kappa', true);
        // Deliberately not named "circle" - only the flag should matter
        $this->linkActivity($flagged, 'Order of the Laurel', true);

        $notFlagged = $this->createCalendarGathering('Drum Circle Event Kappa2', true);
        // Named "circle" but not flagged - must not get the icon
        $this->linkActivity($notFlagged, 'Drum Circle', false);

        $this->session(['Auth' => null]);
        $this->get('/events');

        $this->assertResponseOk();
        // Exactly one activity chip is styled as a circle (the flagged one)
        $this->assertSame(
            1,
            substr_count((string)$this->_response->getBody(), 'kc-activity-chip-circle'),
        );
    }

    /**
     * Event link precedence on the public calendar: the KMP public page
     * supersedes the Event Website; the website is used only when the public
     * page is disabled.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publicCalendar()
     */
    public function testPublicCalendarEventLinkPrecedence(): void
    {
        $withPublicPage = $this->createCalendarGathering('Public Page Event Lambda', true, [
            'public_page_enabled' => true,
            'website_url' => 'https://example.org/superseded-site',
        ]);
        $websiteOnly = $this->createCalendarGathering('Website Only Event Mu', true, [
            'public_page_enabled' => false,
            'website_url' => 'https://example.org/external-site',
        ]);

        $this->session(['Auth' => null]);
        $this->get('/events');

        $this->assertResponseOk();
        // Public page wins: landing link shown, external website suppressed
        $this->assertResponseContains('public-landing/' . $withPublicPage->public_id);
        $this->assertResponseNotContains('https://example.org/superseded-site');
        // No public page: the external website is the event link
        $this->assertResponseContains('https://example.org/external-site');
    }

    /**
     * The pre-registration link renders as a call-to-action on the public
     * landing page.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publicLanding()
     */
    public function testPublicLandingShowsPreregisterLink(): void
    {
        $gathering = $this->createCalendarGathering('Prereg Event Nu', true, [
            'public_page_enabled' => true,
            'preregister_url' => 'https://example.org/prereg-and-pay',
            'preregister_closes_on' => '2099-12-31',
        ]);

        $this->session(['Auth' => null]);
        $this->get('/gatherings/public-landing/' . $gathering->public_id);

        $this->assertResponseOk();
        $this->assertResponseContains('https://example.org/prereg-and-pay');
        $this->assertResponseContains(__('Pre-Register'));
        // The close date is surfaced as an "until" note
        $this->assertResponseContains('December 31, 2099');
    }

    /**
     * Once pre-registration has closed, the landing page hides the button.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publicLanding()
     */
    public function testPublicLandingHidesClosedPreregisterLink(): void
    {
        $gathering = $this->createCalendarGathering('Prereg Closed Event Xi', true, [
            'public_page_enabled' => true,
            'preregister_url' => 'https://example.org/closed-prereg',
            'preregister_closes_on' => '2000-01-01',
        ]);

        $this->session(['Auth' => null]);
        $this->get('/gatherings/public-landing/' . $gathering->public_id);

        $this->assertResponseOk();
        $this->assertResponseNotContains('https://example.org/closed-prereg');
    }

    /**
     * The public calendar is meant to be iframed, so it must not record
     * itself in the back-navigation stack.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publicCalendar()
     */
    public function testPublicCalendarDoesNotUpdatePageStack(): void
    {
        // An authenticated KMP user may load a page that iframes /events; the
        // iframe request shares their session, so the calendar must not push
        // itself onto their back-navigation stack. setUp() authenticates.
        $this->get('/events');

        $this->assertResponseOk();
        // The pageStack view variable reflects the in-request navigation-history
        // computation. The public calendar is excluded, so it must never appear.
        $pageStack = (array)$this->viewVariable('pageStack');
        $this->assertNotContains(
            '/events',
            $pageStack,
            'The public calendar must not be recorded in navigation history.',
        );
    }

    /**
     * The public iCal feed only includes published events.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::feed()
     */
    public function testFeedOnlyIncludesPublishedEvents(): void
    {
        $published = $this->createCalendarGathering('Feed Published Event Delta', true);
        $unpublished = $this->createCalendarGathering('Feed Unpublished Event Epsilon', false);

        $this->session(['Auth' => null]);
        $this->get('/gatherings/feed');

        $this->assertResponseOk();
        $this->assertResponseContains($published->name);
        $this->assertResponseNotContains($unpublished->name);
    }

    /**
     * Publishing requires the dedicated publish permission; a regular member
     * cannot publish even their own branch's gathering.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publish()
     */
    public function testPublishDeniedWithoutPermission(): void
    {
        $gathering = $this->createCalendarGathering('Publish Denied Event Zeta', false);

        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->post('/gatherings/publish/' . $gathering->id . '?publish=true');

        $gatherings = $this->getTableLocator()->get('Gatherings');
        $this->assertFalse((bool)$gatherings->get($gathering->id)->published);
    }

    /**
     * A super user can publish and unpublish, stamping published_by/on.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publish()
     */
    public function testPublishAndUnpublishAsSuperUser(): void
    {
        $gathering = $this->createCalendarGathering('Publish Allowed Event Eta', false);

        $this->post('/gatherings/publish/' . $gathering->id . '?publish=true');
        $this->assertRedirect();

        $gatherings = $this->getTableLocator()->get('Gatherings');
        $published = $gatherings->get($gathering->id);
        $this->assertTrue((bool)$published->published);
        $this->assertNotNull($published->published_by);
        $this->assertNotNull($published->published_on);

        $this->post('/gatherings/publish/' . $gathering->id . '?publish=false');
        $unpublished = $gatherings->get($gathering->id);
        $this->assertFalse((bool)$unpublished->published);
        $this->assertNull($unpublished->published_by);
        $this->assertNull($unpublished->published_on);
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
            'INSERT INTO roles_permissions (role_id, permission_id, created, created_by)
             VALUES (?, ?, NOW(), 1)',
            [(int)$role->id, (int)$permission->id],
        );
        $connection->execute(
            'INSERT INTO member_roles
             (member_id, role_id, branch_id, start_on, expires_on, approver_id, entity_type, created, modified, created_by, modified_by)
             VALUES (?, ?, ?, ?, ?, 1, ?, NOW(), NOW(), 1, 1)',
            [$memberId, (int)$role->id, $branchId, '2020-01-01 00:00:00', '2100-01-01', 'Direct Grant'],
        );

        // The role/permission rows above are inserted via raw SQL, bypassing the ORM
        // afterSave hooks that normally clear the security cache. The member_permissions
        // cache config belongs to the 'security' group, so clear that group directly
        // (clearGroup('member_permissions') would be a no-op — no such group exists).
        Cache::clearGroup('security');
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
