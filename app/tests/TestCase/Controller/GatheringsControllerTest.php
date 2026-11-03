<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\KMP\GridColumns\GatheringsGridColumns;
use App\KMP\TimezoneHelper;
use App\Model\Entity\ActionItem;
use App\Model\Entity\GridView;
use App\Services\GridViewService;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\BestowalTodoTemplateItem;
use Awards\Model\Entity\CourtAgendaItem;
use Awards\Model\Entity\CourtAgendaSegment;
use Cake\Cache\Cache;
use Cake\I18n\DateTime;
use DateTimeImmutable;
use DateTimeZone;
use DOMDocument;
use DOMXPath;
use Officers\Model\Entity\Officer;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $monthStart = new DateTimeImmutable('first day of this month 00:00:00', new DateTimeZone('UTC'));
        $monthEnd = $monthStart->modify('last day of this month');
        $gatheringStart = $monthStart->modify('+14 days')->setTime(10, 0);
        $gatheringEnd = $gatheringStart->modify('+2 hours');
        $gatherings = $this->getTableLocator()->get('Gatherings');
        $gathering = $gatherings->newEntity([
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => 'Grid Frame Column Regression',
            'description' => 'Verifies filtered table-frame responses render columns.',
            'start_date' => $gatheringStart,
            'end_date' => $gatheringEnd,
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
            '/gatherings/grid-data?start_date_start=' . $monthStart->format('Y-m-d')
            . '&start_date_end=' . $monthEnd->format('Y-m-d')
            . '&filter%5Bgathering_type_id%5D%5B%5D=1&dirty%5Bfilters%5D=1'
            . '&search=Grid%20Frame%20Column%20Regression',
        );

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('data-column-key="name"', $body);
        $this->assertStringContainsString('data-column-key="gathering_type_id"', $body);
        $this->assertResponseContains('Grid Frame Column Regression');
    }

    /**
     * Filter-only metadata cannot be forced into the rendered grid columns.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::gridData()
     */
    public function testGridDataExcludesFilterOnlyColumnsFromRequestedColumns(): void
    {
        $this->configRequest([
            'headers' => ['Turbo-Frame' => 'gatherings-grid-table'],
        ]);
        $this->get('/gatherings/grid-data?columns=name,relative_event_date');

        $this->assertResponseOk();
        $this->assertResponseContains('data-column-key="name"');
        $this->assertResponseNotContains('data-column-key="relative_event_date"');
    }

    /**
     * A saved relative-date filter resolves "today" on every request.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::gridData()
     */
    public function testSavedGridViewFiltersGatheringsTodayOrAfter(): void
    {
        $past = $this->createCalendarGathering('Relative Date Filter Past', false, [
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-2 days +4 hours')),
        ]);
        $future = $this->createCalendarGathering('Relative Date Filter Future', false, [
            'start_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+2 days +4 hours')),
        ]);
        $ongoing = $this->createCalendarGathering('Relative Date Filter Ongoing', false, [
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
        ]);
        $currentUser = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $gridView = (new GridViewService())->createView([
            'grid_key' => 'Gatherings.index.main',
            'name' => 'Rolling Future Gatherings',
            'config' => json_encode([
                'filters' => [
                    [
                        'field' => 'relative_event_date',
                        'operator' => 'in',
                        'value' => ['today_or_after'],
                    ],
                ],
            ]),
        ], $currentUser);
        $this->assertInstanceOf(GridView::class, $gridView);
        if (!$gridView instanceof GridView) {
            return;
        }

        $this->get(
            '/gatherings/grid-data?view_id=' . $gridView->id
            . '&search=Relative%20Date%20Filter',
        );

        $this->assertResponseOk();
        $this->assertResponseContains($future->name);
        $this->assertResponseContains($ongoing->name);
        $this->assertResponseNotContains($past->name);
    }

    /**
     * A copied system view keeps its symbolic schedule scope while other filters are edited.
     */
    public function testCopiedGatheringViewKeepsScheduleWindowWhenFiltersAreDirty(): void
    {
        $prefix = 'Copied Schedule Window ' . uniqid();
        $overlap = $this->createCalendarGathering($prefix . ' Overlap', false, [
            'start_date' => (new DateTimeImmutable(
                'first day of last month 00:00:00',
                new DateTimeZone('UTC'),
            ))->format('Y-m-d H:i:s'),
            'end_date' => (new DateTimeImmutable(
                'last day of next month 23:59:59',
                new DateTimeZone('UTC'),
            ))->format('Y-m-d H:i:s'),
        ]);
        $past = $this->createCalendarGathering($prefix . ' Past', false, [
            'start_date' => (new DateTimeImmutable(
                'first day of -5 months 00:00:00',
                new DateTimeZone('UTC'),
            ))->format('Y-m-d H:i:s'),
            'end_date' => (new DateTimeImmutable(
                'last day of -4 months 23:59:59',
                new DateTimeZone('UTC'),
            ))->format('Y-m-d H:i:s'),
        ]);
        $this->assertContains(
            [
                'field' => 'schedule_window',
                'operator' => 'eq',
                'value' => GatheringsGridColumns::SCHEDULE_WINDOW_THIS_MONTH,
            ],
            GatheringsGridColumns::getSystemViews()['sys-gatherings-this-month']['config']['filters'],
        );
        $currentUser = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
        $gridView = (new GridViewService())->createView([
            'grid_key' => 'Gatherings.index.main',
            'name' => 'Copied This Month ' . uniqid(),
            'config' => json_encode([
                'filters' => [
                    [
                        'field' => 'schedule_window',
                        'operator' => 'in',
                        'value' => [GatheringsGridColumns::SCHEDULE_WINDOW_THIS_MONTH],
                    ],
                    [
                        'field' => 'gathering_type_id',
                        'operator' => 'in',
                        'value' => ['1'],
                    ],
                ],
            ]),
        ], $currentUser);
        $this->assertInstanceOf(GridView::class, $gridView);
        if (!$gridView instanceof GridView) {
            return;
        }
        $this->assertSame(
            ['schedule_window', 'gathering_type_id'],
            array_column($gridView->getConfigArray()['filters'], 'field'),
        );

        $this->get('/gatherings/grid-data?' . http_build_query([
            'view_id' => $gridView->id,
            'search' => $prefix,
            'filter' => [
                'schedule_window' => GatheringsGridColumns::SCHEDULE_WINDOW_THIS_MONTH,
                'gathering_type_id' => '1',
            ],
            'dirty' => ['filters' => '1'],
        ]));

        $this->assertResponseOk();
        $this->assertResponseContains($overlap->name);
        $this->assertResponseNotContains($past->name);
        $gridState = $this->viewVariable('gridState');
        $this->assertSame(
            [GatheringsGridColumns::SCHEDULE_WINDOW_THIS_MONTH],
            $gridState['filters']['active']['schedule_window'],
        );
        $this->assertContains('schedule_window', $gridState['config']['lockedFilters']);
        $this->assertFalse($gridState['filters']['available']['schedule_window']['showInFilterMenu']);
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
        $gathering = $gatherings->find()
            ->contain(['Creators'])
            ->first();
        if (!$gathering) {
            $this->markTestSkipped('No gathering found in seed data');
        }
        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();
        $this->assertResponseContains('Created By');
        $this->assertResponseContains(h($gathering->creator->sca_name));

        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();
        $this->assertResponseNotContains('Created By');
    }

    /** Members get the native reader and only contacts marked for sharing. */
    public function testMemberReaderKeepsPrivateContactsAndManagementOut(): void
    {
        $gathering = $this->createCalendarGathering('Native reader gathering', false, [
            'description' => 'A **readable** day of activities.',
            'location' => 'Reader Park, Testville',
            'public_page_enabled' => false,
        ]);
        $staff = $this->getTableLocator()->get('GatheringStaff');
        foreach ([true => 'Shared contact', false => 'Private contact'] as $shared => $name) {
            $staff->saveOrFail($staff->newEntity([
                'gathering_id' => $gathering->id,
                'sca_name' => $name,
                'role' => 'Helper',
                'show_on_public_page' => (bool)$shared,
                'email' => $shared ? 'shared@example.test' : 'private@example.test',
                'created_by' => self::ADMIN_MEMBER_ID,
            ]));
        }
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();
        $this->assertResponseContains('gathering-reader');
        $this->assertResponseContains('id="nav-overview-tab"');
        $this->assertResponseContains('<strong>readable</strong>');
        $this->assertResponseContains('Shared contact');
        $this->assertResponseNotContains('Private contact');
        $this->assertResponseNotContains('private@example.test');
        $this->assertResponseNotContains('gathering-public-surface');
        $this->assertResponseNotContains('fonts.googleapis.com');
        $this->assertResponseNotContains('id="nav-details-tab"');
        $this->assertResponseNotContains('id="nav-staff-tab"');
        $this->assertResponseNotContains('id="qrCodeModal"');
        $this->assertSame(1, substr_count((string)$this->_response->getBody(), 'id="attendGatheringModal"'));
    }

    /** Metadata is a secondary panel, while authorized tools retain their original targets. */
    public function testOrganizerReaderKeepsToolsAndOneRsvpModal(): void
    {
        $gathering = $this->createCalendarGathering('Organizer reader', false, ['public_page_enabled' => true]);
        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();
        foreach (['nav-overview', 'nav-details', 'nav-schedule', 'nav-activities', 'nav-attendance'] as $panel) {
            $this->assertResponseContains('id="' . $panel . '"');
        }
        $body = (string)$this->_response->getBody();
        $this->assertLessThan(strpos($body, 'Created By'), strpos($body, 'id="nav-tabButtons"'));
        $this->assertSame(1, substr_count($body, 'id="attendGatheringModal"'));
        $this->assertSame(1, substr_count($body, 'id="qrCodeModal"'));
        $this->assertSame('upcoming', $this->viewVariable('readerStatus'));
        $this->assertTrue($this->viewVariable('readerCanRsvp'));
    }

    /** Cancellation prevents new RSVPs; owners can still update an existing RSVP. */
    public function testReaderCancellationAndExistingAttendance(): void
    {
        $gathering = $this->createCalendarGathering('Cancelled reader', false);
        $gathering->set('cancelled_at', new DateTime());
        $this->getTableLocator()->get('Gatherings')->saveOrFail($gathering);
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();
        $this->assertSame('cancelled', $this->viewVariable('readerStatus'));
        $this->assertFalse($this->viewVariable('readerCanRsvp'));
        $this->assertResponseNotContains('id="attendGatheringModal"');

        $attendances = $this->getTableLocator()->get('GatheringAttendances');
        $attendances->saveOrFail($attendances->newEntity([
            'gathering_id' => $gathering->id,
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
        ]));
        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();
        $this->assertTrue($this->viewVariable('readerCanRsvp'));
        $this->assertResponseContains('Update Attendance');
    }

    /** The duration picker persists computed ends and can switch back to an open-ended entry. */
    public function testScheduleDurationPickerPersistsAndEdits(): void
    {
        $gathering = $this->createCalendarGathering('Duration picker', false, [
            'start_date' => new DateTimeImmutable('2026-10-10 00:00:00', new DateTimeZone('UTC')),
            'end_date' => new DateTimeImmutable('2026-10-12 00:00:00', new DateTimeZone('UTC')),
        ]);
        $data = [
            'start_datetime' => '2026-10-10T23:45', 'duration_minutes' => '90',
            'is_other' => '1', 'display_title' => 'Midnight activity', 'description' => 'Timing regression',
        ];
        $this->post('/gatherings/add-scheduled-activity/' . $gathering->public_id, $data);
        $this->assertResponseOk();
        $result = json_decode((string)$this->_response->getBody(), true);
        $this->assertTrue($result['success'], json_encode($result));
        $table = $this->getTableLocator()->get('GatheringScheduledActivities');
        $activity = $table->get($result['data']['id']);
        $this->assertTrue($activity->has_end_time);
        $this->assertSame('2026-10-11 04:45', $activity->start_datetime->format('Y-m-d H:i'));
        $this->assertSame('2026-10-11 06:15', $activity->end_datetime->format('Y-m-d H:i'));

        $url = '/gatherings/edit-scheduled-activity/' . $gathering->public_id . '/' . $activity->id;
        $this->post($url, array_replace($data, ['duration_minutes' => 'existing', 'display_title' => 'Renamed']));
        $this->assertTrue(json_decode((string)$this->_response->getBody(), true)['success']);
        $this->assertSame('2026-10-11 06:15', $table->get($activity->id)->end_datetime->format('Y-m-d H:i'));

        $this->post($url, array_replace($data, ['duration_minutes' => 'other']));
        $this->assertTrue(json_decode((string)$this->_response->getBody(), true)['success']);
        $this->assertNull($table->get($activity->id)->end_datetime);
        $this->assertFalse($table->get($activity->id)->has_end_time);
    }

    /** Reject unsupported durations and activities whose computed end exceeds the gathering. */
    public function testScheduleDurationPickerRejectsInvalidAndOutOfRangeDurations(): void
    {
        $gathering = $this->createCalendarGathering('Duration bounds', false, [
            'start_date' => new DateTimeImmutable('2026-10-10 00:00:00', new DateTimeZone('UTC')),
            'end_date' => new DateTimeImmutable('2026-10-10 23:00:00', new DateTimeZone('UTC')),
        ]);
        foreach (['17', '0', '-15', '241', 'existing', '60'] as $duration) {
            $this->post('/gatherings/add-scheduled-activity/' . $gathering->public_id, [
                'start_datetime' => '2026-10-10T17:45', 'duration_minutes' => $duration,
                'is_other' => '1', 'display_title' => 'Must not save',
            ]);
            $this->assertResponseOk();
            $result = json_decode((string)$this->_response->getBody(), true);
            $this->assertFalse($result['success'], json_encode($result));
        }
    }

    /** Scheduled activities show exact minute durations, including quarter-hour choices. */
    public function testViewRendersScheduledActivityDurations(): void
    {
        $gathering = $this->createCalendarGathering('Schedule Duration Regression', false);
        $scheduledActivities = $this->getTableLocator()->get('GatheringScheduledActivities');
        $expectedLabels = [
            1 => '1 minute', 15 => '15 minutes', 30 => '30 minutes', 45 => '45 minutes',
            60 => '60 minutes', 90 => '90 minutes', 120 => '120 minutes', 240 => '240 minutes',
        ];
        foreach ($expectedLabels as $minutes => $label) {
            $activity = $this->createScheduledActivity(
                $gathering,
                self::ADMIN_MEMBER_ID,
                'Duration ' . $minutes,
            );
            $activity->has_end_time = true;
            $activity->end_datetime = $activity->start_datetime->modify('+' . $minutes . ' minutes');
            $scheduledActivities->saveOrFail($activity);
        }
        $this->createScheduledActivity($gathering, self::ADMIN_MEMBER_ID, 'Open-ended activity');

        $this->get('/gatherings/view/' . $gathering->public_id);
        $this->assertResponseOk();
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML((string)$this->_response->getBody());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        foreach ($expectedLabels as $minutes => $label) {
            $cells = $xpath->query('//tr[td/strong[text()="Duration ' . $minutes . '"]]/td[1]');
            $this->assertCount(1, $cells);
            $text = preg_replace('/\s+/', ' ', $cells->item(0)->textContent);
            $this->assertStringContainsString('(' . $label . ')', $text);
        }
        $openEndedCells = $xpath->query('//tr[td/strong[text()="Open-ended activity"]]/td[1]');
        $this->assertCount(1, $openEndedCells);
        $this->assertStringNotContainsString('minute', $openEndedCells->item(0)->textContent);
    }

    /**
     * Creator metadata remains visible when a gathering has no explicit timezone.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::view()
     */
    public function testViewShowsCreatorWithoutGatheringTimezone(): void
    {
        $gathering = $this->createCalendarGathering('Gathering Without Timezone', false, [
            'timezone' => null,
        ]);

        $this->get('/gatherings/view/' . $gathering->public_id);

        $this->assertResponseOk();
        $this->assertResponseContains('Created By');
    }

    /**
     * Share controls remain operable so disabled-state guidance is reachable.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::view()
     */
    public function testViewShareControlsAreAccessible(): void
    {
        $disabledGathering = $this->createCalendarGathering('Private Gathering', false, [
            'public_page_enabled' => false,
        ]);
        $this->get('/gatherings/view/' . $disabledGathering->public_id);

        $this->assertResponseOk();
        $this->assertResponseContains('Public landing page is disabled for this gathering.');
        $this->assertResponseNotContains('aria-expanded="false" disabled');

        $enabledGathering = $this->createCalendarGathering('Public Gathering', false, [
            'public_page_enabled' => true,
        ]);
        $this->get('/gatherings/view/' . $enabledGathering->public_id);

        $this->assertResponseOk();
        $this->assertResponseContains('label class="text-muted d-block mb-2" for="publicLandingUrlInput"');
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
     * Personal calendar views are available to ordinary members and apply saved filters.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::calendarGridData()
     */
    public function testCalendarSavedViewsAreAvailableToAllMembers(): void
    {
        $branches = $this->getTableLocator()->get('Branches')->find()
            ->select(['id', 'public_id'])
            ->where(['public_id IS NOT' => null])
            ->orderBy(['id' => 'ASC'])
            ->limit(2)
            ->all()
            ->toList();
        $this->assertCount(2, $branches, 'Seed data must provide two branches for calendar view filtering.');

        $matching = $this->createCalendarGathering('Personal Calendar View Match', false, [
            'branch_id' => (int)$branches[0]->id,
            'start_date' => '2099-10-15 10:00:00',
            'end_date' => '2099-10-15 18:00:00',
        ]);
        $excluded = $this->createCalendarGathering('Personal Calendar View Excluded', false, [
            'branch_id' => (int)$branches[1]->id,
            'start_date' => '2099-10-16 10:00:00',
            'end_date' => '2099-10-16 18:00:00',
        ]);
        $member = $this->getTableLocator()->get('Members')->get(self::TEST_MEMBER_BRYCE_ID);
        $gridView = (new GridViewService())->createView([
            'grid_key' => 'Gatherings.calendar.main',
            'name' => 'My Branch Calendar',
            'config' => json_encode([
                'filters' => [[
                    'field' => 'branch_id',
                    'operator' => 'in',
                    'value' => [(string)$branches[0]->public_id],
                ]],
            ]),
        ], $member);
        $this->assertInstanceOf(GridView::class, $gridView);
        if (!$gridView instanceof GridView) {
            return;
        }
        $this->authenticateAsMember(self::TEST_MEMBER_BRYCE_ID);

        $this->get('/gatherings/calendar-grid-data?' . http_build_query([
            'view_id' => $gridView->id,
            'view' => 'list',
            'year' => '2099',
            'month' => '10',
        ]));

        $this->assertResponseOk();
        $this->assertResponseContains($matching->name);
        $this->assertResponseNotContains($excluded->name);
        $this->assertResponseContains('aria-label="Saved calendar views"');
        $this->assertResponseContains('role="tablist"');
        $this->assertResponseNotContains('flex-nowrap overflow-x-auto');
        $this->assertResponseContains('grid-view#saveView');
        $gridState = $this->viewVariable('gridState');
        $this->assertTrue($gridState['config']['canAddViews']);
        $this->assertTrue($gridState['config']['showViewTabs']);
        $this->assertSame((int)$gridView->id, (int)$gridState['view']['currentId']);
        $this->assertContains('My Branch Calendar', array_column($gridState['view']['available'], 'name'));
    }

    /**
     * Calendar navigation exposes the rendered mode and opens list events outside its frame.
     *
     * @return void
     */
    public function testCalendarViewControlsAndListEventTargets(): void
    {
        $this->createCalendarGathering('Calendar Navigation Target', false, [
            'start_date' => '2099-10-15 10:00:00',
            'end_date' => '2099-10-15 18:00:00',
        ]);
        $this->createCalendarGathering('Cancelled Calendar Navigation Target', true, [
            'start_date' => '2099-10-16 10:00:00',
            'end_date' => '2099-10-16 18:00:00',
        ]);
        foreach (['month', 'week', 'list'] as $mode) {
            $this->get('/gatherings/calendar-grid-data?year=2099&month=10&view=' . $mode);
            $this->assertResponseOk();
            $dom = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $dom->loadHTML((string)$this->_response->getBody());
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new DOMXPath($dom);
            $selected = $xpath->query('//a[@data-calendar-view-mode][@aria-current="true"]');
            $this->assertCount(1, $selected);
            $this->assertSame($mode, $selected->item(0)->getAttribute('data-calendar-view-mode'));
            if ($mode === 'list') {
                $titles = $xpath->query('//h5/a[contains(@href,"/gatherings/view/")]');
                $this->assertGreaterThanOrEqual(2, $titles->length);
                foreach ($titles as $title) {
                    $this->assertSame('_top', $title->getAttribute('data-turbo-frame'));
                }
            }
        }
    }

    /**
     * Today navigation renders a scroll target in every calendar mode.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::calendarGridData()
     */
    public function testCalendarTodayNavigationRendersTargetInEveryView(): void
    {
        $baseQuery = 'year=2000&month=01&scroll_to_today=1';

        foreach (['month', 'week', 'list'] as $view) {
            $this->configRequest([
                'headers' => ['Turbo-Frame' => 'gatherings-calendar-grid-table'],
            ]);
            $weekStart = $view === 'week' ? '&week_start=2000-01-01' : '';
            $this->get("/gatherings/calendar-grid-data?view={$view}&{$baseQuery}{$weekStart}");

            $this->assertResponseOk();
            $this->assertResponseContains('id="gatherings-calendar-today"');
            $this->assertResponseContains('data-gatherings-calendar-scroll-to-today-value="1"');
        }
    }

    /**
     * The list-view today marker precedes a gathering already in progress.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::calendarGridData()
     */
    public function testCalendarListTodayMarkerPrecedesOngoingGathering(): void
    {
        $today = new DateTimeImmutable('now', new DateTimeZone(TimezoneHelper::getAppTimezone()));
        $ongoing = $this->createCalendarGathering('Ongoing Calendar Gathering', false, [
            'start_date' => $today->modify('-2 days')->format('Y-m-d 10:00:00'),
            'end_date' => $today->modify('+2 days')->format('Y-m-d 18:00:00'),
        ]);

        $this->configRequest([
            'headers' => ['Turbo-Frame' => 'gatherings-calendar-grid-table'],
        ]);
        $this->get(sprintf(
            '/gatherings/calendar-grid-data?view=list&year=%s&month=%s&scroll_to_today=1',
            $today->format('Y'),
            $today->format('m'),
        ));

        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $markerPosition = strpos($body, 'id="gatherings-calendar-today"');
        $gatheringPosition = strpos($body, h($ongoing->name));
        $this->assertNotFalse($markerPosition);
        $this->assertNotFalse($gatheringPosition);
        $this->assertLessThan($gatheringPosition, $markerPosition);
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
        $gatherings->saveOrFail($gatherings->newEntity([
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
        $gatherings->saveOrFail($gatherings->newEntity([
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
        $courtActivity = $this->createCourtActivity($gathering, true);
        $this->grantCourtSchedulePermission($memberId, (int)$gathering->branch_id);
        $this->authenticateAsMember($memberId);

        $this->post('/gatherings/add-scheduled-activity/' . $gathering->public_id, [
            'start_datetime' => $this->localScheduleInput($gathering, 1),
            'has_end_time' => '0',
            'display_title' => 'Delegated Court Session',
            'description' => 'Created by a court schedule manager.',
            'pre_register' => '0',
            'is_other' => '0',
            'gathering_activity_id' => (string)$courtActivity->id,
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
        $this->assertSame((int)$courtActivity->id, (int)$created->gathering_activity_id);
    }

    public function testCourtScheduleManagerCanAttachCourtActivityBeforeBestowalAssignment(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $memberId = self::TEST_MEMBER_AGATHA_ID;
        $courtActivity = $this->createCourtActivity($gathering, false);
        $this->assertFalse($this->getTableLocator()->get('Awards.Bestowals')->exists([
            'gathering_id' => (int)$gathering->id,
        ]));
        $this->grantCourtSchedulePermission($memberId, (int)$gathering->branch_id);
        $this->authenticateAsMember($memberId);

        $this->post('/gatherings/add-activity/' . $gathering->id, [
            'activity_id' => (string)$courtActivity->id,
            'custom_description' => 'Royal Court for this gathering.',
        ]);

        $this->assertRedirect(['action' => 'view', $gathering->public_id]);
        $link = $this->getTableLocator()->get('GatheringsGatheringActivities')->find()
            ->where([
                'gathering_id' => (int)$gathering->id,
                'gathering_activity_id' => (int)$courtActivity->id,
            ])
            ->first();
        $this->assertNotNull($link);
        $this->assertSame('Royal Court for this gathering.', $link->custom_description);
    }

    public function testCourtScheduleManagerCannotAttachNonCourtActivity(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $memberId = self::TEST_MEMBER_AGATHA_ID;
        $nonCourtActivity = $this->createNonCourtActivity();
        $this->grantCourtSchedulePermission($memberId, (int)$gathering->branch_id);
        $this->authenticateAsMember($memberId);

        $this->post('/gatherings/add-activity/' . $gathering->id, [
            'activity_id' => (string)$nonCourtActivity->id,
        ]);

        $this->assertResponseCode(403);
        $this->assertFalse($this->getTableLocator()->get('GatheringsGatheringActivities')->exists([
            'gathering_id' => (int)$gathering->id,
            'gathering_activity_id' => (int)$nonCourtActivity->id,
        ]));
    }

    public function testCourtScheduleManagerCannotScheduleOtherActivity(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $memberId = self::TEST_MEMBER_AGATHA_ID;
        $this->grantCourtSchedulePermission($memberId, (int)$gathering->branch_id);
        $this->authenticateAsMember($memberId);

        $this->post('/gatherings/add-scheduled-activity/' . $gathering->public_id, [
            'start_datetime' => $this->localScheduleInput($gathering, 1),
            'has_end_time' => '0',
            'display_title' => 'Forged Other Activity',
            'description' => 'Should be rejected.',
            'pre_register' => '0',
            'is_other' => '1',
        ]);

        $this->assertResponseCode(403);
        $this->assertFalse($this->getTableLocator()->get('GatheringScheduledActivities')->exists([
            'gathering_id' => (int)$gathering->id,
            'display_title' => 'Forged Other Activity',
        ]));
    }

    public function testStewardCannotBypassConfiguredActivityRules(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $activity = $this->createCourtActivity($gathering, true);
        $this->grantCourtSchedulePermission(self::TEST_MEMBER_BRYCE_ID, (int)$gathering->branch_id);
        $memberId = self::TEST_MEMBER_AGATHA_ID;
        $staff = $this->getTableLocator()->get('GatheringStaff');
        $staff->saveOrFail($staff->newEntity([
            'gathering_id' => $gathering->id, 'member_id' => $memberId, 'role' => 'Steward',
            'is_steward' => true, 'email' => 'synthetic@example.test',
        ]));
        $this->authenticateAsMember($memberId);
        $this->post('/gatherings/edit-activity-description/' . $gathering->id, [
            'activity_id' => $activity->id, 'custom_description' => 'Unauthorized restricted edit',
        ]);
        $this->assertResponseCode(403);
        $this->post('/gatherings/add-scheduled-activity/' . $gathering->public_id, [
            'gathering_activity_id' => $activity->id, 'is_other' => '0',
            'start_datetime' => $this->localScheduleInput($gathering, 1),
            'display_title' => 'Unauthorized restricted schedule', 'has_end_time' => '0',
        ]);
        $this->assertResponseCode(403);
        $this->assertFalse($this->getTableLocator()->get('GatheringScheduledActivities')->exists([
            'gathering_id' => $gathering->id, 'display_title' => 'Unauthorized restricted schedule',
        ]));
    }

    public function testDelegateCannotSwitchToUnrelatedTypeOrForgeScheduleOwnership(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $activity = $this->createCourtActivity($gathering, true);
        $other = $this->createNonCourtActivity();
        $memberId = self::TEST_MEMBER_AGATHA_ID;
        $scheduled = $this->createScheduledActivity($gathering, self::ADMIN_MEMBER_ID, 'Authorized activity', $activity->id);
        $this->grantCourtSchedulePermission($memberId, (int)$gathering->branch_id);
        $this->authenticateAsMember($memberId);
        $url = '/gatherings/edit-scheduled-activity/' . $gathering->public_id . '/' . $scheduled->id;
        $this->post($url, ['gathering_activity_id' => $other->id, 'is_other' => 'false']);
        $this->assertResponseCode(403);
        $this->post($url, [
            'gathering_activity_id' => $activity->id, 'is_other' => '0',
            'gathering_id' => -1, 'created_by' => $memberId, 'id' => -1,
            'start_datetime' => $this->localScheduleInput($gathering, 2),
            'has_end_time' => '0', 'display_title' => 'Valid delegated edit',
        ]);
        $this->assertResponseOk();
        $persisted = $this->getTableLocator()->get('GatheringScheduledActivities')->get($scheduled->id);
        $this->assertSame((int)$gathering->id, (int)$persisted->gathering_id);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$persisted->created_by);
        $this->assertSame('Valid delegated edit', $persisted->display_title);
    }

    public function testCourtScheduleManagerCanEditOwnScheduledActivity(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $memberId = self::TEST_MEMBER_AGATHA_ID;
        $courtActivity = $this->createCourtActivity($gathering, true);
        $scheduledActivity = $this->createScheduledActivity(
            $gathering,
            $memberId,
            'Own Court Session',
            (int)$courtActivity->id,
        );
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
                'is_other' => '0',
                'gathering_activity_id' => (string)$courtActivity->id,
            ],
        );

        $this->assertResponseOk();
        $updated = $this->getTableLocator()->get('GatheringScheduledActivities')->get($scheduledActivity->id);
        $this->assertSame('Updated Own Court Session', $updated->display_title);
        $this->assertSame($memberId, (int)$updated->created_by);
    }

    public function testActivityDelegateCanEditAnotherCreatorsSameActivitySchedule(): void
    {
        $gathering = $this->getScheduleTestGathering();
        $memberId = self::TEST_MEMBER_AGATHA_ID;
        $courtActivity = $this->createCourtActivity($gathering, true);
        $scheduledActivity = $this->createScheduledActivity(
            $gathering,
            self::ADMIN_MEMBER_ID,
            'Someone Else Court Session',
            (int)$courtActivity->id,
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
                'is_other' => '0',
                'gathering_activity_id' => (string)$courtActivity->id,
            ],
        );

        $this->assertResponseOk();
        $unchanged = $this->getTableLocator()->get('GatheringScheduledActivities')->get($scheduledActivity->id);
        $this->assertSame('Unauthorized Edit Attempt', $unchanged->display_title);
    }

    public function testActivityDelegateCannotDeleteUnrelatedOtherSchedule(): void
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
        $courtActivity = $this->createCourtActivity($gathering, true);
        $this->createScheduledActivity(
            $gathering,
            $memberId,
            'Own Delegated Court',
            (int)$courtActivity->id,
        );
        $this->createScheduledActivity(
            $gathering,
            self::ADMIN_MEMBER_ID,
            'Other Court',
            (int)$courtActivity->id,
        );
        $this->grantCourtSchedulePermission($memberId, (int)$gathering->branch_id);
        $this->authenticateAsMember($memberId);

        $this->get('/gatherings/view/' . $gathering->public_id);

        $this->assertResponseOk();
        $this->assertResponseContains('id="nav-schedule-tab"');
        $this->assertResponseContains('Add Activity');
        $this->assertResponseContains('Add Scheduled Activity');
        $this->assertResponseContains((string)$courtActivity->name);
        $this->assertResponseNotContains('This is an &quot;Other&quot; activity');
        $this->assertResponseContains('aria-label="Edit Own Delegated Court"');
        $this->assertResponseContains('aria-label="Edit Other Court"');
        $this->assertResponseContains('aria-label="Delete Own Delegated Court"');
        $this->assertResponseContains('aria-label="Delete Other Court"');
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
        $futureDate = new DateTimeImmutable('+10 days', new DateTimeZone('America/Chicago'));
        $gathering = $gatherings->newEntity($extra + [
            'branch_id' => 2,
            'gathering_type_id' => 1,
            'name' => $name,
            'start_date' => $futureDate->setTime(10, 0),
            'end_date' => $futureDate->setTime(18, 0),
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
     * Create a snapshot RSVP without relying on a current office assignment.
     */
    private function addRoyalProgress(
        object $gathering,
        string $office,
        ?string $branch,
        int $memberId = self::TEST_MEMBER_AGATHA_ID,
        bool $royal = true,
    ): object {
        $attendances = $this->getTableLocator()->get('GatheringAttendances');
        $attendance = $attendances->newEntity([
            'gathering_id' => $gathering->id,
            'member_id' => $memberId,
            'created_by' => $memberId,
            'public_note' => 'Private RSVP detail must not appear in mobile progress metadata',
        ]);
        $attendance->set('is_royal_progress', $royal);
        $attendance->set('progress_office_name', $office);
        $attendance->set('progress_branch_name', $branch);

        return $attendances->saveOrFail($attendance);
    }

    public static function royalProgressSurfaces(): array
    {
        return [
            'gatherings grid' => ['grid-data'],
            'calendar grid' => ['calendar-grid-data'],
            'public calendar' => ['publicCalendar'],
            'subscription feed' => ['feed'],
        ];
    }

    #[DataProvider('royalProgressSurfaces')]
    public function testRoyalProgressFiltersByOfficeAndRepresentedBranch(string $surface): void
    {
        $matching = $this->createCalendarGathering('Royal Filter Matching Crown', true);
        $otherBranch = $this->createCalendarGathering('Royal Filter Other Branch', true);
        $otherOffice = $this->createCalendarGathering('Royal Filter Other Office', true);
        $ordinary = $this->createCalendarGathering('Royal Filter Ordinary RSVP', true);
        $unpublished = $this->createCalendarGathering('Royal Filter Unpublished', false);
        $snapshot = $this->addRoyalProgress($matching, 'Crown', 'Snapshot Kingdom');
        $this->addRoyalProgress($matching, 'Crown', 'Snapshot Kingdom', self::ADMIN_MEMBER_ID);
        $this->addRoyalProgress($otherBranch, 'Crown', 'Other Kingdom');
        $this->addRoyalProgress($otherOffice, 'Principality Coronet', 'Snapshot Kingdom');
        $this->addRoyalProgress($ordinary, 'Crown', 'Snapshot Kingdom', royal: false);
        $this->addRoyalProgress($unpublished, 'Crown', 'Snapshot Kingdom');

        $params = ['filter' => ['royal_progress' => [$snapshot->progress_filter_value]]];
        if ($surface === 'publicCalendar') {
            $this->session(['Auth' => null]);
            $params = ['royal_progress' => [$snapshot->progress_filter_value]];
            $path = '/events';
        } else {
            $path = '/gatherings/' . $surface;
            if ($surface === 'grid-data') {
                // Use a personal view so this test has no month-specific system scope.
                $member = $this->getTableLocator()->get('Members')->get(self::ADMIN_MEMBER_ID);
                $view = (new GridViewService())->createView([
                    'grid_key' => 'Gatherings.index.main',
                    'name' => 'Follow Crown',
                    'config' => json_encode(['filters' => [[
                        'field' => 'royal_progress',
                        'operator' => 'in',
                        'value' => [$snapshot->progress_filter_value],
                    ]]]),
                ], $member);
                $this->assertInstanceOf(GridView::class, $view);
                $params = ['view_id' => $view->id];
            } elseif ($surface === 'calendar-grid-data') {
                $params += [
                    'year' => $matching->start_date->format('Y'),
                    'month' => $matching->start_date->format('m'),
                ];
                $this->configRequest(['headers' => ['Turbo-Frame' => 'gatherings-calendar-grid-table']]);
            } else {
                $this->session(['Auth' => null]);
            }
        }
        $this->get($path . '?' . http_build_query($params));

        $this->assertResponseOk();
        $this->assertResponseContains($matching->name);
        $this->assertResponseNotContains($otherBranch->name);
        $this->assertResponseNotContains($otherOffice->name);
        $this->assertResponseNotContains($ordinary->name);
        if (in_array($surface, ['publicCalendar', 'feed'], true)) {
            $this->assertResponseNotContains($unpublished->name);
        }
        if ($surface === 'grid-data') {
            $this->assertCount(2, $this->viewVariable('data'));
        }
        if ($surface === 'publicCalendar') {
            $this->assertResponseContains('Crown of Other Kingdom');
            $this->assertResponseContains('Principality Coronet of Snapshot Kingdom');
            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            $document->loadHTML((string)$this->_response->getBody());
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            $xpath = new DOMXPath($document);
            $subscribe = $xpath->query('//a[contains(@class, "kc-subscribe-link")]')->item(0);
            parse_str(parse_url($subscribe->getAttribute('href'), PHP_URL_QUERY), $subscription);
            $this->assertSame([$snapshot->progress_filter_value], $subscription['filter']['royal_progress']);
        }
    }

    public function testRoyalProgressSelectionSupportsAnyMultipleAndDeletedRsvps(): void
    {
        $crown = $this->createCalendarGathering('Multiple Progress Crown', true);
        $coronet = $this->createCalendarGathering('Multiple Progress Coronet', true);
        $ordinary = $this->createCalendarGathering('Multiple Progress Ordinary', true);
        $deleted = $this->createCalendarGathering('Multiple Progress Deleted', true);
        $first = $this->addRoyalProgress($crown, 'Crown', 'Historic Kingdom');
        $second = $this->addRoyalProgress($coronet, 'Coronet', null);
        $removed = $this->addRoyalProgress($deleted, 'Crown', 'Historic Kingdom');
        $this->getTableLocator()->get('GatheringAttendances')->deleteOrFail($removed);
        $table = $this->getTableLocator()->get('Gatherings');
        $ids = [$crown->id, $coronet->id, $ordinary->id, $deleted->id];
        foreach ([['any'], [$first->progress_filter_value, $second->progress_filter_value]] as $selection) {
            $matches = $table->find('royalProgress', selections: $selection)
                ->where(['Gatherings.id IN' => $ids])->all()->extract('id')->toList();
            $this->assertEqualsCanonicalizing([$crown->id, $coronet->id], $matches);
        }
        $this->assertSame(0, $table->find('royalProgress', selections: [
            'invalid', base64_encode('[1,2]'), base64_encode('["Crown",{}]'),
        ])->count());
        $this->assertSame(4, $table->find('royalProgress', selections: [])->where(['Gatherings.id IN' => $ids])->count());
    }

    public static function calendarRoyalProgressBadgeViews(): array
    {
        return ['month' => ['month'], 'week' => ['week'], 'list' => ['list']];
    }

    #[DataProvider('calendarRoyalProgressBadgeViews')]
    public function testCalendarShowsRoyalProgressBadgeWithoutMarkingViewerAttending(string $view): void
    {
        $royal = $this->createCalendarGathering('Badge Royal Gathering', true);
        $ordinary = $this->createCalendarGathering('Badge Ordinary Gathering', true);
        $this->addRoyalProgress($royal, 'Crown', 'Snapshot Kingdom');
        $this->addRoyalProgress($royal, 'Crown', 'Snapshot Kingdom', self::TEST_MEMBER_BRYCE_ID);
        $this->addRoyalProgress($royal, 'Coronet', 'Snapshot Principality', self::TEST_MEMBER_DEVON_ID);
        $this->addRoyalProgress($ordinary, 'Crown', 'Snapshot Kingdom', royal: false);

        $this->configRequest(['headers' => ['Turbo-Frame' => 'gatherings-calendar-grid-table']]);
        $this->get('/gatherings/calendar-grid-data?' . http_build_query([
            'year' => $royal->start_date->format('Y'),
            'month' => $royal->start_date->format('m'),
            'week_start' => $royal->start_date->format('Y-m-d'),
            'view' => $view,
            'search' => 'Badge',
        ]));

        $this->assertResponseOk();
        $this->assertResponseContains($royal->name);
        $this->assertResponseContains($ordinary->name);
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML((string)$this->_response->getBody());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        $badges = $xpath->query('//*[@data-royal-progress-badge]');
        $this->assertGreaterThan(0, $badges->length);
        foreach ($badges as $badge) {
            $label = $badge->getAttribute('aria-label');
            $this->assertSame('img', $badge->getAttribute('role'));
            $this->assertSame($label, $badge->getAttribute('title'));
            $this->assertSame(1, substr_count($label, 'Crown of Snapshot Kingdom'));
            $this->assertStringContainsString('Coronet of Snapshot Principality', $label);
        }
        $this->assertCount(0, $xpath->query('//*[@aria-label="I\'m attending"]'));
        foreach ($this->viewVariable('gatherings') as $gathering) {
            $this->assertEmpty($gathering->gathering_attendances);
            $this->assertCount($gathering->id === $royal->id ? 2 : 0, $gathering->royal_progress);
        }
    }

    public function testMobileCalendarIncludesOnlyPublicProgressMetadata(): void
    {
        $gathering = $this->createCalendarGathering('Mobile Royal Filter', true);
        $snapshot = $this->addRoyalProgress($gathering, 'Coronet', 'Snapshot Principality');
        $this->get('/gatherings/mobile-calendar-data?' . http_build_query([
            'year' => $gathering->start_date->format('Y'),
            'month' => $gathering->start_date->format('m'),
        ]));

        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);
        $events = array_column($body['data']['events'], null, 'id');
        $event = $events[$gathering->id];
        $this->assertSame([[
            'value' => $snapshot->progress_filter_value,
            'label' => 'Coronet of Snapshot Principality',
        ]], $event['royal_progress']);
        $this->assertFalse($event['user_attending']);
        $this->assertResponseNotContains('Private RSVP detail');
    }

    public function testPublicRoyalProgressCombinesWithActivityAndKeepsOptionsPublished(): void
    {
        $matching = $this->createCalendarGathering('Combined Royal Filter Match', true);
        $withoutActivity = $this->createCalendarGathering('Combined Royal Filter No Activity', true);
        $withoutRoyal = $this->createCalendarGathering('Combined Royal Filter No Royal', true);
        $draft = $this->createCalendarGathering('Combined Royal Filter Draft', false);
        $snapshot = $this->addRoyalProgress($matching, 'Crown', 'Historic Branch');
        $this->addRoyalProgress($withoutActivity, 'Crown', 'Historic Branch');
        $this->addRoyalProgress($draft, 'Private Draft Office', 'Private Draft Branch');
        $activity = $this->linkActivity($matching, 'Combined Filter Circle', true);
        $this->linkActivity($withoutRoyal, $activity->name, true);

        $this->session(['Auth' => null]);
        $this->get('/events?' . http_build_query([
            'activities' => [$activity->id],
            'royal_progress' => [$snapshot->progress_filter_value],
        ]));

        $this->assertResponseOk();
        $this->assertResponseContains($matching->name);
        $this->assertResponseNotContains($withoutActivity->name);
        $this->assertResponseNotContains($withoutRoyal->name);
        $this->assertResponseNotContains('Private Draft Office');
        $this->assertResponseNotContains('Private Draft Branch');
    }

    public static function royalProgressSwitchStates(): array
    {
        return ['on' => [true], 'off' => [false]];
    }

    private function assertRoyalProgressSwitch(bool $enabled): void
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML((string)$this->_response->getBody());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $switches = (new DOMXPath($document))->query(
            '//input[@name="royal_progress_enabled" and @type="checkbox" and @role="switch"]',
        );
        $this->assertCount(1, $switches);
        $this->assertSame($enabled, $switches->item(0)->hasAttribute('checked'));
    }

    #[DataProvider('royalProgressSwitchStates')]
    public function testCalendarAttendanceFormOffersAndRecordsEligibleRoyalOffice(bool $enabled): void
    {
        $gathering = $this->createCalendarGathering('Calendar Royal RSVP Choice', true);
        $offices = $this->getTableLocator()->get('Officers.Offices');
        $office = $offices->saveOrFail($offices->newEntity([
            'name' => 'Calendar Crown',
            'requires_warrant' => false,
            'required_office' => false,
            'can_skip_report' => true,
            'only_one_per_branch' => false,
            'is_royal_progress' => true,
            'term_length' => 12,
        ]));
        $officers = $this->getTableLocator()->get('Officers.Officers');
        $officer = $officers->saveOrFail($officers->newEntity([
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'office_id' => $office->id,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'status' => Officer::CURRENT_STATUS,
            'start_on' => new DateTimeImmutable('-10 days'),
            'expires_on' => new DateTimeImmutable('+6 months'),
            'approver_id' => self::ADMIN_MEMBER_ID,
            'approval_date' => new DateTimeImmutable(),
            'email_address' => 'calendar-crown@example.test',
        ]));
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/gatherings/attendance-modal/' . $gathering->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Attend as Royal Progress for');
        $this->assertResponseContains('Calendar Crown of Ansteorra');
        $this->assertRoyalProgressSwitch(true);

        $this->get('/members/view/' . self::TEST_MEMBER_AGATHA_ID);
        $this->assertResponseOk();
        $this->assertRoyalProgressSwitch(true);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/gathering-attendances/add', [
            'gathering_id' => $gathering->id,
            'royal_progress_enabled' => $enabled ? '1' : '0',
            'progress_officer_id' => $officer->id,
            'share_with_kingdom' => false,
        ]);
        $this->assertResponseOk();
        $attendance = $this->getTableLocator()->get('GatheringAttendances')->find()
            ->where(['gathering_id' => $gathering->id, 'member_id' => self::TEST_MEMBER_AGATHA_ID])->firstOrFail();
        $this->assertSame($enabled, $attendance->is_royal_progress);
        $this->assertSame($enabled, $attendance->share_with_kingdom);
        $this->assertSame($enabled ? 'Calendar Crown of Ansteorra' : null, $attendance->progress_title);

        $this->get('/gatherings/attendance-modal/' . $gathering->id . '?attendance_id=' . $attendance->id);
        $this->assertResponseOk();
        $this->assertRoyalProgressSwitch($enabled);

        if ($enabled) {
            $office->name = 'Renamed Calendar Crown';
            $offices->saveOrFail($office);
            $this->get('/gatherings/attendance-modal/' . $gathering->id . '?attendance_id=' . $attendance->id);
            $this->assertResponseOk();
            $this->assertResponseContains('value="preserve"');
            $this->post('/gathering-attendances/edit/' . $attendance->id, [
                'royal_progress_enabled' => '1',
                'progress_officer_id' => 'preserve',
            ]);
            $this->assertSame(
                'Calendar Crown of Ansteorra',
                $this->getTableLocator()->get('GatheringAttendances')->get($attendance->id)->progress_title,
            );
        }

        $this->post('/gathering-attendances/edit/' . $attendance->id, [
            'royal_progress_enabled' => '0',
            'progress_officer_id' => $officer->id,
        ]);
        $updated = $this->getTableLocator()->get('GatheringAttendances')->get($attendance->id);
        $this->assertFalse($updated->is_royal_progress);
        $this->assertNull($updated->progress_office_id);
        $this->assertNull($updated->progress_office_name);
        $this->assertNull($updated->progress_branch_name);
    }

    public function testHistoricalRoyalProgressSwitchPreservesOrClearsSnapshot(): void
    {
        $gathering = $this->createCalendarGathering('Historical Royal Switch', true);
        $attendance = $this->addRoyalProgress($gathering, 'Historic Crown', 'Historic Kingdom');
        $this->authenticateAsMember(self::TEST_MEMBER_AGATHA_ID);
        $this->get('/gatherings/attendance-modal/' . $gathering->id . '?attendance_id=' . $attendance->id);
        $this->assertResponseOk();
        $this->assertRoyalProgressSwitch(true);
        $this->assertResponseContains('Historic Crown of Historic Kingdom');

        $this->post('/gathering-attendances/edit/' . $attendance->id, [
            'royal_progress_enabled' => '1',
            'progress_officer_id' => 'preserve',
        ]);
        $attendances = $this->getTableLocator()->get('GatheringAttendances');
        $this->assertSame('Historic Crown of Historic Kingdom', $attendances->get($attendance->id)->progress_title);

        $this->post('/gathering-attendances/edit/' . $attendance->id, [
            'royal_progress_enabled' => '0',
            'progress_officer_id' => 'preserve',
        ]);
        $this->assertFalse($attendances->get($attendance->id)->is_royal_progress);

        $newGathering = $this->createCalendarGathering('Forged Royal Switch', true);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/gathering-attendances/add', [
            'gathering_id' => $newGathering->id,
            'royal_progress_enabled' => '1',
            'progress_officer_id' => 'preserve',
        ]);
        $this->assertResponseCode(400);
        $this->assertFalse($attendances->exists(['gathering_id' => $newGathering->id]));
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
        $body = (string)$this->_response->getBody();
        $this->assertMatchesRegularExpression(
            '/<span class="kc-activity-chip kc-activity-chip-circle">\s*'
            . '<kmp-icon name="circle-dot"[^>]*><\/kmp-icon>\s*'
            . '.*?Order of the Laurel.*?<\/span>/s',
            $body,
        );
        $this->assertMatchesRegularExpression(
            '/<span class="kc-activity-chip">\s*.*?Drum Circle.*?<\/span>/s',
            $body,
        );
    }

    /**
     * Activities are folded behind a <details> expander so the mobile list
     * stays scannable, while the essentials (title, date, location) stay
     * inline.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publicCalendar()
     */
    public function testPublicCalendarCollapsesActivitiesBehindDetails(): void
    {
        $gathering = $this->createCalendarGathering('Details Fold Event Sigma', true);
        $this->linkActivity($gathering, 'Grand Feast');

        $this->session(['Auth' => null]);
        $this->get('/events');

        $this->assertResponseOk();
        // The expander wraps the activities; essentials remain outside it
        $this->assertResponseContains('<details class="kc-more">');
        $this->assertResponseContains('Grand Feast');
        $this->assertResponseContains('Details Fold Event Sigma');
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
        $this->createCalendarGathering('Website Only Event Mu', true, [
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
     * Multi-day events show a duration ribbon counting calendar days (date-only,
     * so a Fri 5pm–Sun 3pm event reads as 3 days, not 2).
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publicCalendar()
     */
    public function testPublicCalendarShowsMultiDayDuration(): void
    {
        // Fri 17:00 -> Sun 15:00 is under 2*24h but spans 3 calendar days.
        // Dates stay within the calendar's +2 year horizon so they list.
        $this->createCalendarGathering('Three Day War', true, [
            'start_date' => '2027-11-12 17:00:00',
            'end_date' => '2027-11-14 15:00:00',
        ]);

        $this->session(['Auth' => null]);
        $this->get('/events');

        $this->assertResponseOk();
        $this->assertResponseContains('kc-event-through');
        $this->assertResponseContains('3 days');
    }

    /**
     * The admin "Public calendar CSS" app setting is injected into /events so
     * kingdoms can restyle the page.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publicCalendar()
     */
    public function testPublicCalendarInjectsCustomCss(): void
    {
        $this->getTableLocator()->get('AppSettings')->setAppSetting(
            'Plugin.PublicGatherings.CustomCSS',
            '.kingdom-calendar-page { --kc-accent: #abcdef; }',
            'css',
        );

        $this->session(['Auth' => null]);
        $this->get('/events');

        $this->assertResponseOk();
        $this->assertResponseContains('<style>');
        $this->assertResponseContains('--kc-accent: #abcdef;');
    }

    /**
     * Custom CSS cannot break out of the injected <style> element.
     *
     * @return void
     * @uses \App\Controller\GatheringsController::publicCalendar()
     */
    public function testPublicCalendarCustomCssCannotBreakOutOfStyleTag(): void
    {
        $this->getTableLocator()->get('AppSettings')->setAppSetting(
            'Plugin.PublicGatherings.CustomCSS',
            'body{}</style><script>alert(1)</script>',
            'css',
        );

        $this->session(['Auth' => null]);
        $this->get('/events');

        $this->assertResponseOk();
        // The closing tag is neutralized, so no live script is injected
        $this->assertResponseNotContains('</style><script>alert(1)');
        $this->assertResponseContains('<\/style>');
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

        $activityIds = $this->getTableLocator()->get('Awards.AwardGatheringActivities')->find()
            ->all()->extract('gathering_activity_id')->toList();
        $rules = $this->getTableLocator()->get('GatheringActivityAssignmentPermissions');
        foreach (array_unique($activityIds) as $activityId) {
            $conditions = ['gathering_activity_id' => $activityId, 'permission_id' => $permission->id];
            if (!$rules->exists($conditions)) {
                $rules->saveOrFail($rules->newEntity($conditions));
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

    private function createScheduledActivity(
        $gathering,
        int $createdBy,
        string $title,
        ?int $gatheringActivityId = null,
    ) {
        $scheduledActivities = $this->getTableLocator()->get('GatheringScheduledActivities');
        $scheduledActivity = $scheduledActivities->newEntity([
            'gathering_id' => $gathering->id,
            'start_datetime' => (clone $gathering->start_date)->modify('+1 hour'),
            'has_end_time' => false,
            'display_title' => $title,
            'description' => $title . ' description.',
            'pre_register' => false,
            'is_other' => $gatheringActivityId === null,
            'gathering_activity_id' => $gatheringActivityId,
            'created_by' => $createdBy,
        ]);
        $saved = $scheduledActivities->save($scheduledActivity);
        $this->assertNotFalse($saved, 'Expected scheduled activity fixture to save');

        return $saved;
    }

    private function createCourtActivity($gathering, bool $linkToGathering): object
    {
        $activity = $this->getTableLocator()->get('GatheringActivities')->saveOrFail(
            $this->getTableLocator()->get('GatheringActivities')->newEntity([
                'name' => 'Scoped Court Activity ' . $gathering->id . '-' . ($linkToGathering ? 'linked' : 'available'),
                'description' => 'Court-capable activity for authorization testing.',
            ]),
        );
        $award = $this->getTableLocator()->get('Awards.Awards')->find()->select(['id'])->firstOrFail();
        $awardActivities = $this->getTableLocator()->get('Awards.AwardGatheringActivities');
        $awardActivities->saveOrFail($awardActivities->newEntity([
            'award_id' => (int)$award->id,
            'gathering_activity_id' => (int)$activity->id,
        ]));

        if ($linkToGathering) {
            $links = $this->getTableLocator()->get('GatheringsGatheringActivities');
            $links->saveOrFail($links->newEntity([
                'gathering_id' => (int)$gathering->id,
                'gathering_activity_id' => (int)$activity->id,
                'sort_order' => 999,
            ]));
        }

        return $activity;
    }

    private function createNonCourtActivity(): object
    {
        $activities = $this->getTableLocator()->get('GatheringActivities');

        return $activities->saveOrFail($activities->newEntity([
            'name' => 'Non-Court Authorization Test Activity',
            'description' => 'Not associated with any award.',
        ]));
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
