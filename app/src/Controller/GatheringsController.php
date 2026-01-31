<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\CsvExportService;
use App\Services\ICalendarService;
use Cake\Http\Exception\NotFoundException;
use DateTime;
use DateTimeZone;
use Twig\Sandbox\SecurityError;

/**
 * Gatherings Controller
 *
 * Manages gatherings (events) with activity selection and waiver tracking.
 * Enables gathering stewards to create gatherings with basic information
 * and automatically determines required waivers based on selected activities.
 *
 * @property \App\Model\Table\GatheringsTable $Gatherings
 * @method \App\Model\Entity\Gathering[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class GatheringsController extends AppController
{
    use DataverseGridTrait;
    /**
     * Service dependency injection configuration
     *
     * @var array<string> Service injection configuration
     */
    public static array $inject = [CsvExportService::class, ICalendarService::class];

    /**
     * Initialize controller
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Authorize model-level operations
        $this->Authorization->authorizeModel('index', 'add', 'gridData', 'calendar', 'calendarGridData');
    }

    /**
     * Before filter callback
     *
     * @param \Cake\Event\EventInterface $event The beforeFilter event
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        // Allow public access to landing page and calendar download
        $this->Authentication->allowUnauthenticated(['publicLanding', 'downloadCalendar']);
    }

    /**
     * Index method
     *
     * Lists all gatherings with filtering options.
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Dataverse grid handles all data loading via gridData action
        // This action now only renders the dv_grid shell defined in the template
        $this->set('user', $this->Authentication->getIdentity());
    }

    /**
     * Grid Data provider for gatherings index dv_grid
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @return \Cake\Http\Response|null
     */
    public function gridData(CsvExportService $csvExportService)
    {
        $securityGathering = $this->Gatherings->newEmptyEntity();
        $this->Authorization->authorize($securityGathering, 'index');

        $currentUser = $this->Authentication->getIdentity();
        $userTimezone = \App\KMP\TimezoneHelper::getUserTimezone($currentUser);

        $systemViews = \App\KMP\GridColumns\GatheringsGridColumns::getSystemViews(['timezone' => $userTimezone]);
        $queryCallback = $this->buildGatheringSystemViewQueryCallback($userTimezone);

        $baseQuery = $this->Gatherings->find()
            ->select([
                'Gatherings.id',
                'Gatherings.public_id',
                'Gatherings.name',
                'Gatherings.branch_id',
                'Gatherings.gathering_type_id',
                'Gatherings.start_date',
                'Gatherings.end_date',
                'Gatherings.location',
                'Gatherings.created',
                'Gatherings.modified',
            ])
            ->contain([
                'Branches' => ['fields' => ['id', 'name']],
                'GatheringTypes' => ['fields' => ['id', 'name']],
                'GatheringActivities' => ['fields' => ['GatheringActivities.id', 'GatheringActivities.name']],
                'Creators' => ['fields' => ['id', 'sca_name']],
            ])
            ->leftJoinWith('GatheringActivities')
            ->distinct(['Gatherings.id']);

        $result = $this->processDataverseGrid([
            'gridKey' => 'Gatherings.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\GatheringsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Gatherings',
            'defaultSort' => ['Gatherings.start_date' => 'DESC'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-gatherings-this-month',
            'queryCallback' => $queryCallback,
            'showAllTab' => false,
            'canAddViews' => true,
            'canFilter' => true,
            'canExportCsv' => true,
            'showFilterPills' => true,
        ]);

        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'gatherings');
        }

        // Set view variables for dv_grid_* elements
        $this->set([
            'data' => $result['data'],
            'gatherings' => $result['data'],
            'gridState' => $result['gridState'],
            'rowActions' => \App\KMP\GridColumns\GatheringsGridColumns::getRowActions(),
        ]);

        // Build URLs for grid
        $queryParams = $this->request->getQueryParams();
        $dataUrl = \Cake\Routing\Router::url(['action' => 'gridData']);
        $tableDataUrl = $dataUrl;
        if (!empty($queryParams)) {
            $tableDataUrl .= '?' . http_build_query($queryParams);
        }

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');
        $frameId = 'gatherings-grid';

        if ($turboFrame === $frameId . '-table') {
            $this->set('tableFrameId', $frameId . '-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            $this->set('frameId', $frameId);
            $this->set('dataUrl', $dataUrl);
            $this->set('tableDataUrl', $tableDataUrl);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Build query callback for temporal system views
     *
     * @param string $userTimezone User timezone identifier
     * @return callable
     */
    protected function buildGatheringSystemViewQueryCallback(string $userTimezone): callable
    {
        $boundaries = \App\KMP\GridColumns\GatheringsGridColumns::getSystemViewDateBoundaries($userTimezone);

        return function ($query, $selectedSystemView) use ($boundaries) {
            if (!$selectedSystemView || empty($selectedSystemView['id'])) {
                return $query;
            }

            switch ($selectedSystemView['id']) {
                case 'sys-gatherings-this-month':
                    $query->where([
                        'OR' => [
                            [
                                'Gatherings.start_date >=' => $boundaries['thisMonthStartUtc'],
                                'Gatherings.start_date <=' => $boundaries['thisMonthEndUtc'],
                            ],
                            [
                                'Gatherings.end_date >=' => $boundaries['thisMonthStartUtc'],
                                'Gatherings.end_date <=' => $boundaries['thisMonthEndUtc'],
                            ],
                            [
                                'Gatherings.start_date <' => $boundaries['thisMonthStartUtc'],
                                'Gatherings.end_date >' => $boundaries['thisMonthEndUtc'],
                            ],
                        ],
                    ]);
                    break;

                case 'sys-gatherings-next-month':
                    $query->where([
                        'OR' => [
                            [
                                'Gatherings.start_date >=' => $boundaries['nextMonthStartUtc'],
                                'Gatherings.start_date <=' => $boundaries['nextMonthEndUtc'],
                            ],
                            [
                                'Gatherings.end_date >=' => $boundaries['nextMonthStartUtc'],
                                'Gatherings.end_date <=' => $boundaries['nextMonthEndUtc'],
                            ],
                            [
                                'Gatherings.start_date <' => $boundaries['nextMonthStartUtc'],
                                'Gatherings.end_date >' => $boundaries['nextMonthEndUtc'],
                            ],
                        ],
                    ]);
                    break;

                case 'sys-gatherings-future':
                    $query->where(['Gatherings.start_date >' => $boundaries['nextMonthEndUtc']]);
                    break;

                case 'sys-gatherings-previous':
                    $query->where(['Gatherings.end_date <' => $boundaries['previousCutoffUtc']]);
                    break;
            }

            return $query;
        };
    }

    /**
     * Calendar view method
     *
     * Displays gatherings in an interactive calendar format with month/week/list views.
     * Allows users to view, filter, and mark attendance for gatherings.
     *
     * @return \Cake\Http\Response|null|void Renders calendar view
     */
    public function calendar()
    {
        $securityGathering = $this->Gatherings->newEmptyEntity();
        $this->Authorization->authorize($securityGathering, 'index');

        $currentUser = $this->Authentication->getIdentity();
        $userTimezone = \App\KMP\TimezoneHelper::getUserTimezone($currentUser);
        $timezone = new DateTimeZone($userTimezone);
        $today = new DateTime('now', $timezone);

        $defaultYear = (int)$today->format('Y');
        $defaultMonth = (int)$today->format('m');
        $defaultView = $this->request->getQuery('view', 'month');

        $this->set(compact('defaultYear', 'defaultMonth', 'defaultView'));
    }

    /**
     * Dataverse grid data provider for calendar layout
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @return \Cake\Http\Response|null
     */
    public function calendarGridData(CsvExportService $csvExportService)
    {
        $securityGathering = $this->Gatherings->newEmptyEntity();
        $this->Authorization->authorize($securityGathering, 'index');

        $currentUser = $this->Authentication->getIdentity();
        $userTimezone = \App\KMP\TimezoneHelper::getUserTimezone($currentUser);
        $timezone = new DateTimeZone($userTimezone);

        $year = (int)$this->request->getQuery('year', date('Y'));
        $month = (int)$this->request->getQuery('month', date('m'));
        $view = $this->request->getQuery('view', 'month');
        $weekStartParam = $this->request->getQuery('week_start');

        if ($year < 1900 || $year > 2100) {
            $year = (int)date('Y');
        }
        if ($month < 1 || $month > 12) {
            $month = (int)date('m');
        }
        if (!in_array($view, ['month', 'week', 'list'], true)) {
            $view = 'month';
        }

        if ($view === 'week') {
            $startDate = null;

            if (is_string($weekStartParam) && $weekStartParam !== '') {
                try {
                    $startDate = new DateTime($weekStartParam, $timezone);
                } catch (\Exception $exception) {
                    $startDate = null;
                }
            }

            if ($startDate === null) {
                $startDate = new DateTime('now', $timezone);
            }

            $startDate->setTime(0, 0, 0);
            $year = (int)$startDate->format('Y');
            $month = (int)$startDate->format('m');
        } else {
            $startDate = new DateTime(sprintf('%04d-%02d-01', $year, $month), $timezone);
        }
        $endDate = (clone $startDate)->modify('last day of this month')->setTime(23, 59, 59);

        $calendarStart = clone $startDate;
        $dayOfWeek = (int)$calendarStart->format('w');
        if ($dayOfWeek > 0) {
            $calendarStart->modify("-{$dayOfWeek} days");
        }

        $calendarEnd = clone $endDate;
        $endDayOfWeek = (int)$calendarEnd->format('w');
        if ($endDayOfWeek < 6) {
            $calendarEnd->modify('+' . (6 - $endDayOfWeek) . ' days');
        }

        $calendarStartUtc = \App\KMP\TimezoneHelper::toUtc($calendarStart->format('Y-m-d H:i:s'), $userTimezone);
        $calendarEndUtc = \App\KMP\TimezoneHelper::toUtc($calendarEnd->format('Y-m-d H:i:s'), $userTimezone);

        $calendarStartUtcString = $calendarStartUtc->format('Y-m-d H:i:s');
        $calendarEndUtcString = $calendarEndUtc->format('Y-m-d H:i:s');

        $baseQuery = $this->Gatherings->find()
            ->select([
                'Gatherings.id',
                'Gatherings.public_id',
                'Gatherings.name',
                'Gatherings.branch_id',
                'Gatherings.gathering_type_id',
                'Gatherings.start_date',
                'Gatherings.end_date',
                'Gatherings.location',
                'Gatherings.created',
                'Gatherings.modified',
            ])
            ->contain([
                'Branches' => ['fields' => ['id', 'name']],
                'GatheringTypes' => ['fields' => ['id', 'name', 'color']],
                'GatheringActivities' => ['fields' => ['GatheringActivities.id', 'GatheringActivities.name']],
                'GatheringAttendances' => [
                    'conditions' => ['GatheringAttendances.member_id' => $currentUser->id],
                    'fields' => ['id', 'gathering_id', 'member_id'],
                ],
            ])
            ->leftJoinWith('GatheringActivities')
            ->distinct(['Gatherings.id'])
            ->where([
                'OR' => [
                    [
                        'Gatherings.start_date >=' => $calendarStartUtcString,
                        'Gatherings.start_date <=' => $calendarEndUtcString,
                    ],
                    [
                        'Gatherings.end_date >=' => $calendarStartUtcString,
                        'Gatherings.end_date <=' => $calendarEndUtcString,
                    ],
                    [
                        'Gatherings.start_date <' => $calendarStartUtcString,
                        'Gatherings.end_date >' => $calendarEndUtcString,
                    ],
                ],
            ])
            ->orderBy(['Gatherings.start_date' => 'ASC']);

        $result = $this->processDataverseGrid([
            'gridKey' => 'Gatherings.calendar.main',
            'gridColumnsClass' => \App\KMP\GridColumns\GatheringsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Gatherings',
            'defaultSort' => ['Gatherings.start_date' => 'ASC'],
            'defaultPageSize' => 200,
            'showAllTab' => false,
            'showViewTabs' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'enableColumnPicker' => false,
            'showFilterPills' => true,
        ]);

        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'gatherings-calendar');
        }

        foreach ($result['data'] as $gathering) {
            if (isset($gathering->gathering_activities)) {
                $gathering->activity_count = count($gathering->gathering_activities);
            }
        }

        $currentFilters = $result['currentFilters'] ?? [];
        $branchName = null;
        $branchFilter = $currentFilters['branch_id'] ?? null;

        if (is_array($branchFilter)) {
            $branchFilter = $branchFilter[0] ?? null;
        }

        if ($branchFilter) {
            $branchName = $this->Gatherings->Branches->find()
                ->select(['name'])
                ->where(['Branches.id' => $branchFilter])
                ->first()
                ?->name;
        }

        $prevMonth = (clone $startDate)->modify('-1 month');
        $nextMonth = (clone $startDate)->modify('+1 month');

        $queryParams = $this->request->getQueryParams();
        if ($view === 'week' && empty($queryParams['week_start']) && $startDate instanceof \DateTimeInterface) {
            $queryParams['week_start'] = $startDate->format('Y-m-d');
        }

        $calendarMeta = [
            'year' => $year,
            'month' => $month,
            'view' => $view,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'calendarStart' => $calendarStart,
            'calendarEnd' => $calendarEnd,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'selectedBranch' => $branchName,
            'queryParams' => $queryParams,
        ];

        $this->set([
            'gatherings' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\GatheringsGridColumns::getSearchableColumns(),
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $currentFilters,
            'currentSearch' => $result['currentSearch'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
            'frameId' => 'gatherings-calendar-grid',
            'customElement' => 'gatherings/calendar_renderer',
            'customElementOptions' => [
                'calendarMeta' => $calendarMeta,
                'viewMode' => $view,
            ],
            'rowActions' => [],
        ]);

        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        if ($turboFrame === 'gatherings-calendar-grid-table') {
            // Inner frame: just calendar grid (reloads with filters/navigation)
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'gatherings-calendar-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame: toolbar + calendar grid (toolbar doesn't reload)
            $this->set('data', $result['data']);
            $this->set('calendarMeta', $calendarMeta);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/gatherings/calendar_content');
        }
    }

    /**
     * Quick view method for calendar modal
     *
     * Returns a simplified view of a gathering for the calendar quick view modal.
     * This provides essential information without the full page layout.
     *
     * @param string|null $id Gathering id.
     * @return \Cake\Http\Response|null|void Renders quick view partial
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function quickView($publicId = null)
    {
        $gathering = $this->Gatherings->find('byPublicId', [$publicId])
            ->contain([
                'Branches' => ['fields' => ['id', 'name']],
                'GatheringTypes' => ['fields' => ['id', 'name', 'color', 'clonable']],
                'GatheringActivities',
                'GatheringAttendances' => [
                    'Members' => ['fields' => ['id', 'sca_name']],
                    'conditions' => [
                        'OR' => [
                            'GatheringAttendances.share_with_kingdom' => true,
                        ]
                    ]
                ],
                'Creators' => ['fields' => ['id', 'sca_name']]
            ])
            ->select([
                'id',
                'public_id',
                'name',
                'description',
                'start_date',
                'end_date',
                'location',
                'latitude',
                'longitude',
                'branch_id',
                'gathering_type_id',
                'created',
                'modified'
            ])
            ->firstOrFail();

        // Limit attendances to 10 for the quick view
        if (!empty($gathering->gathering_attendances)) {
            $gathering->gathering_attendances = array_slice($gathering->gathering_attendances, 0, 10);
        }

        $this->Authorization->authorize($gathering);

        // Check if current user is attending
        $currentUser = $this->Authentication->getIdentity();
        $userAttendance = $this->Gatherings->GatheringAttendances
            ->find()
            ->where([
                'gathering_id' => $gathering->id,
                'member_id' => $currentUser->id
            ])
            ->first();

        // Check if user can still attend (gathering hasn't ended)
        $today = \Cake\I18n\Date::now();
        $canAttend = $gathering->end_date >= $today;

        $this->set(compact('gathering', 'userAttendance', 'canAttend'));
    }

    /**
     * Attendance Modal - Returns the attendance modal content for AJAX loading
     * 
     * This action provides the attendance modal form dynamically for use in the calendar view.
     * It reuses the attendGatheringModal element to ensure consistent UI and functionality.
     *
     * @param string|null $id Gathering ID
     * @return void
     */
    public function attendanceModal($id = null)
    {
        $this->request->allowMethod(['get']);

        $gathering = $this->Gatherings->get($id, [
            'contain' => [
                'Branches' => ['fields' => ['id', 'name']],
                'GatheringTypes' => ['fields' => ['id', 'name']],
            ],
            'fields' => [
                'id',
                'name',
                'start_date',
                'end_date',
                'branch_id',
                'gathering_type_id'
            ]
        ]);

        $this->Authorization->authorize($gathering, 'quickView');

        $currentUser = $this->Authentication->getIdentity();

        // Check if editing existing attendance
        $attendanceId = $this->request->getQuery('attendance_id');
        $userAttendance = null;

        if ($attendanceId) {
            $userAttendance = $this->Gatherings->GatheringAttendances
                ->find()
                ->where([
                    'id' => $attendanceId,
                    'member_id' => $currentUser->id,
                    'gathering_id' => $id
                ])
                ->first();
        }

        // Render just the modal content (not full layout)
        $this->viewBuilder()->setLayout('ajax');
        $this->set(compact('gathering', 'userAttendance', 'currentUser'));
    }

    /**
     * All gatherings method - Filtered gathering listing with export capability
     *
     * Provides gathering listing with temporal filtering (this_month, next_month, future, previous),
     * pagination, and CSV export. Uses user timezone for accurate month boundary calculations.
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @param string $state Temporal filter state (this_month|next_month|future|previous)
     * @return \Cake\Http\Response|null|void Renders gathering list or returns CSV export
     * @throws \Cake\Http\Exception\NotFoundException When invalid state provided
     * @see /docs/4.6-gatherings-system.md For complete gatherings documentation
     */
    public function allGatherings(CsvExportService $csvExportService, $state)
    {
        // Validate state parameter to prevent invalid filter attempts
        if (!in_array($state, ['this_month', 'next_month', 'future', 'previous'])) {
            throw new NotFoundException('Invalid gathering state filter');
        }

        // Create security entity for authorization checking
        $securityGathering = $this->Gatherings->newEmptyEntity();
        $this->Authorization->authorize($securityGathering, 'index');

        // Build base query with optimized association loading
        $gatheringsQuery = $this->Gatherings->find()
            ->select([
                'Gatherings.id',
                'Gatherings.public_id',
                'Gatherings.name',
                'Gatherings.start_date',
                'Gatherings.end_date',
                'Gatherings.branch_id',
                'Gatherings.gathering_type_id',
                'Gatherings.created',
            ])
            ->contain([
                'Branches' => ['fields' => ['id', 'name']],
                'GatheringTypes' => ['fields' => ['id', 'name']],
                'GatheringActivities' => ['fields' => ['id', 'name']],
                'Creators' => ['fields' => ['id', 'sca_name']]
            ]);

        // Apply temporal filtering based on current date and month boundaries
        // Use user's timezone for accurate month boundary calculations
        $currentUser = $this->Authentication->getIdentity();
        $userTimezone = \App\KMP\TimezoneHelper::getUserTimezone($currentUser);
        $timezone = new \DateTimeZone($userTimezone);

        $today = new DateTime('now', $timezone);
        $today->setTime(0, 0, 0); // Set to start of day for accurate comparisons

        // Calculate month boundaries in user's timezone
        $thisMonthStart = new DateTime('first day of this month', $timezone);
        $thisMonthStart->setTime(0, 0, 0);

        $thisMonthEnd = new DateTime('last day of this month', $timezone);
        $thisMonthEnd->setTime(23, 59, 59);

        $nextMonthStart = new DateTime('first day of next month', $timezone);
        $nextMonthStart->setTime(0, 0, 0);

        $nextMonthEnd = new DateTime('last day of next month', $timezone);
        $nextMonthEnd->setTime(23, 59, 59);

        // Convert boundaries to UTC for database queries (gatherings are stored in UTC)
        $thisMonthStartUtc = \App\KMP\TimezoneHelper::toUtc($thisMonthStart->format('Y-m-d H:i:s'), $userTimezone);
        $thisMonthEndUtc = \App\KMP\TimezoneHelper::toUtc($thisMonthEnd->format('Y-m-d H:i:s'), $userTimezone);
        $nextMonthStartUtc = \App\KMP\TimezoneHelper::toUtc($nextMonthStart->format('Y-m-d H:i:s'), $userTimezone);
        $nextMonthEndUtc = \App\KMP\TimezoneHelper::toUtc($nextMonthEnd->format('Y-m-d H:i:s'), $userTimezone);

        switch ($state) {
            case 'this_month':
                // Gatherings that overlap with the current calendar month
                $gatheringsQuery = $gatheringsQuery->where([
                    'OR' => [
                        // Starts this month
                        [
                            'Gatherings.start_date >=' => $thisMonthStartUtc->format('Y-m-d H:i:s'),
                            'Gatherings.start_date <=' => $thisMonthEndUtc->format('Y-m-d H:i:s')
                        ],
                        // Ends this month
                        [
                            'Gatherings.end_date >=' => $thisMonthStartUtc->format('Y-m-d H:i:s'),
                            'Gatherings.end_date <=' => $thisMonthEndUtc->format('Y-m-d H:i:s')
                        ],
                        // Spans across this month
                        [
                            'Gatherings.start_date <' => $thisMonthStartUtc->format('Y-m-d H:i:s'),
                            'Gatherings.end_date >' => $thisMonthEndUtc->format('Y-m-d H:i:s')
                        ]
                    ]
                ]);
                break;
            case 'next_month':
                // Gatherings that overlap with next calendar month
                $gatheringsQuery = $gatheringsQuery->where([
                    'OR' => [
                        // Starts next month
                        [
                            'Gatherings.start_date >=' => $nextMonthStartUtc->format('Y-m-d H:i:s'),
                            'Gatherings.start_date <=' => $nextMonthEndUtc->format('Y-m-d H:i:s')
                        ],
                        // Ends next month
                        [
                            'Gatherings.end_date >=' => $nextMonthStartUtc->format('Y-m-d H:i:s'),
                            'Gatherings.end_date <=' => $nextMonthEndUtc->format('Y-m-d H:i:s')
                        ],
                        // Spans across next month
                        [
                            'Gatherings.start_date <' => $nextMonthStartUtc->format('Y-m-d H:i:s'),
                            'Gatherings.end_date >' => $nextMonthEndUtc->format('Y-m-d H:i:s')
                        ]
                    ]
                ]);
                break;
            case 'future':
                // Gatherings that start after next month
                $gatheringsQuery = $gatheringsQuery->where([
                    'Gatherings.start_date >' => $nextMonthEndUtc->format('Y-m-d H:i:s')
                ]);
                break;
            case 'previous':
                // Past gatherings that ended before this month
                $gatheringsQuery = $gatheringsQuery->where([
                    'Gatherings.end_date <' => $thisMonthStartUtc->format('Y-m-d H:i:s')
                ]);
                break;
        }

        // Apply search conditions if provided
        $gatheringsQuery = $this->addConditions($gatheringsQuery);

        // Default ordering by start date
        $gatheringsQuery = $gatheringsQuery->order(['Gatherings.start_date' => 'DESC']);

        // CSV export for filtered gathering data
        if ($this->isCsvRequest()) {
            return $csvExportService->outputCsv(
                $gatheringsQuery,
                'gatherings.csv',
            );
        }

        // Paginated results for web interface
        $gatherings = $this->paginate($gatheringsQuery);

        $this->set(compact('gatherings', 'state'));
    }

    /**
     * Add conditions - Optimize gathering queries for performance and security
     *
     * Applies query optimization and field selection for gathering listing operations.
     * Supports branch filtering via query parameters.
     *
     * @param \Cake\ORM\Query $query Base gathering query to optimize
     * @return \Cake\ORM\Query Optimized query with conditions
     */
    protected function addConditions($query)
    {
        // Apply branch filter if provided
        if ($this->request->getQuery('branch_id')) {
            $query->where(['Gatherings.branch_id' => $this->request->getQuery('branch_id')]);
        }

        // Placeholder for additional search and filter conditions
        return $query;
    }

    /**
     * View method
     *
     * Displays gathering details including activities and required waivers.
     *
     * @param string|null $id Gathering id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($publicId = null)
    {
        $gathering = $this->Gatherings->find('byPublicId', [$publicId])
            ->contain([
                'Branches',
                'GatheringTypes' => ['fields' => ['id', 'name', 'clonable']],
                'GatheringActivities',
                'GatheringScheduledActivities' => [
                    'GatheringActivities',
                    'Creators' => ['fields' => ['id', 'sca_name']],
                    'Modifiers' => ['fields' => ['id', 'sca_name']],
                ],
                'GatheringStaff' => [
                    'Members' => ['fields' => ['id', 'sca_name']],
                    'sort' => [
                        'GatheringStaff.is_steward' => 'DESC',
                        'GatheringStaff.sort_order' => 'ASC'
                    ]
                ],
                'Creators' => ['fields' => ['id', 'sca_name']],
                'GatheringAttendances' => [
                    'Members' => ['fields' => ['id', 'sca_name']],
                    'conditions' => [
                        'OR' => [
                            'GatheringAttendances.share_with_hosting_group' => true,
                            'GatheringAttendances.share_with_kingdom' => true,
                        ]
                    ]
                ],
            ])
            ->firstOrFail();

        $user = $this->Authentication->getIdentity();
        $canView = $user->can('view', $gathering);


        //TODO: find a way to do this with out breaking the plugin/core boundry.
        // Check if waivers exist (for activity locking)
        // This is used to determine if activities can be added/removed
        $hasWaivers = false;
        if (class_exists('Waivers\Model\Table\GatheringWaiversTable')) {
            $hasWaivers = $this->fetchTable('Waivers.GatheringWaivers')
                ->find()->where(['gathering_id' => $gathering->id])->count() > 0;
        }

        // Get available activities (not already in this gathering)
        $existingActivityIds = array_column($gathering->gathering_activities, 'id');
        $availableActivities = $this->Gatherings->GatheringActivities->find('all')
            ->where(['id NOT IN' => $existingActivityIds ?: [0]])
            ->orderBy(['name' => 'ASC'])
            ->all();

        // Get total attendance count (all attendance records, including private)
        $totalAttendanceCount = $this->Gatherings->GatheringAttendances
            ->find()
            ->where(['gathering_id' => $gathering->id])
            ->count();

        // Check if current user has an attendance record for this gathering
        $currentUser = $this->Authentication->getIdentity();
        $userAttendance = $this->Gatherings->GatheringAttendances
            ->find()
            ->where([
                'gathering_id' => $gathering->id,
                'member_id' => $currentUser->id
            ])
            ->first();

        $kingdomAttendances = [];
        if ($gathering->public_page_enabled && $currentUser) {
            $kingdomAttendances = $this->Gatherings->GatheringAttendances
                ->find()
                ->contain(['Members' => ['fields' => ['id', 'sca_name']]])
                ->where([
                    'gathering_id' => $gathering->id,
                    'share_with_kingdom' => true,
                ])
                ->orderBy(['Members.sca_name' => 'ASC'])
                ->all()
                ->toArray();
        }

        $this->set(compact(
            'gathering',
            'hasWaivers',
            'availableActivities',
            'totalAttendanceCount',
            'userAttendance',
            'kingdomAttendances'
        ));

        // Override recordId to use integer ID for plugin cells that expect it
        // (recordId is auto-set to the URL param which is now public_id)
        $this->set('recordId', $gathering->id);
        //if the user can view the gathering use the standard view template
        if ($canView) {
            $this->viewBuilder()->setTemplate('view');
        } else {
            //if the user can not view the gathering use the limited view template
            $this->viewBuilder()->setTemplate('view_public');
        }
    }

    /**
     * Add method
     *
     * Creates a new gathering. Activities can be added after creation.
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $gathering = $this->Gatherings->newEmptyEntity();
        $this->Authorization->authorize($gathering);

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            // Set the creator automatically
            $data['created_by'] = $this->Authentication->getIdentity()->id;

            // Convert datetime inputs from user/gathering timezone to UTC for storage
            if (!empty($data['start_date'])) {
                $timezone = !empty($data['timezone']) ? $data['timezone'] :
                    \App\KMP\TimezoneHelper::getUserTimezone($this->Authentication->getIdentity());
                $data['start_date'] = \App\KMP\TimezoneHelper::toUtc($data['start_date'], $timezone);
            }

            if (!empty($data['end_date'])) {
                $timezone = !empty($data['timezone']) ? $data['timezone'] :
                    \App\KMP\TimezoneHelper::getUserTimezone($this->Authentication->getIdentity());
                $data['end_date'] = \App\KMP\TimezoneHelper::toUtc($data['end_date'], $timezone);
            }

            // Default end_date to start_date if not provided
            if (empty($data['end_date']) && !empty($data['start_date'])) {
                $data['end_date'] = $data['start_date'];
            }

            $gathering = $this->Gatherings->patchEntity($gathering, $data);

            if ($this->Gatherings->save($gathering)) {
                $this->Flash->success(__(
                    'The gathering "{0}" has been created successfully.',
                    $gathering->name
                ));

                return $this->redirect(['action' => 'view', $gathering->public_id]);
            }

            $errors = $gathering->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }
                $this->Flash->error(__(
                    'The gathering could not be saved: {0}',
                    implode(', ', $errorMessages)
                ));
            } else {
                $this->Flash->error(__('The gathering could not be saved. Please, try again.'));
            }
        }

        // Load form options - filter branches based on user permissions
        $currentUser = $this->Authentication->getIdentity();
        $branchIds = $currentUser->getBranchIdsForAction('add', 'Gatherings');

        $branchesQuery = $this->Gatherings->Branches->find('list')->orderBy(['name' => 'ASC']);
        if ($branchIds !== null) {
            // User has limited access - filter to specific branches
            $branchesQuery->where(['Branches.id IN' => $branchIds]);
        }
        $branches = $branchesQuery;

        $gatheringTypes = $this->Gatherings->GatheringTypes->find('list')->orderBy(['name' => 'ASC']);

        $this->set(compact('gathering', 'branches', 'gatheringTypes'));
    }

    /**
     * Edit method
     *
     * Edits an existing gathering.
     * Activities are locked if waivers have been uploaded (T118).
     *
     * @param string|null $id Gathering id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $gathering = $this->Gatherings->get($id);
        $this->Authorization->authorize($gathering);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();

            // Convert datetime inputs from user/gathering timezone to UTC for storage
            if (!empty($data['start_date'])) {
                $timezone = !empty($data['timezone']) ? $data['timezone'] :
                    \App\KMP\TimezoneHelper::getGatheringTimezone($gathering, $this->Authentication->getIdentity());
                $data['start_date'] = \App\KMP\TimezoneHelper::toUtc($data['start_date'], $timezone);
            }

            if (!empty($data['end_date'])) {
                $timezone = !empty($data['timezone']) ? $data['timezone'] :
                    \App\KMP\TimezoneHelper::getGatheringTimezone($gathering, $this->Authentication->getIdentity());
                $data['end_date'] = \App\KMP\TimezoneHelper::toUtc($data['end_date'], $timezone);
            }

            $gathering = $this->Gatherings->patchEntity($gathering, $data);

            if ($this->Gatherings->save($gathering)) {
                $this->Flash->success(__(
                    'The gathering "{0}" has been updated successfully.',
                    $gathering->name
                ));

                return $this->redirect(['action' => 'view', $gathering->public_id]);
            }

            $errors = $gathering->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }
                $this->Flash->error(__(
                    'The gathering could not be saved: {0}',
                    implode(', ', $errorMessages)
                ));
            } else {
                $this->Flash->error(__('The gathering could not be saved. Please, try again.'));
            }
        }

        // Load form options - filter branches based on user permissions
        $currentUser = $this->Authentication->getIdentity();
        $branchIds = $currentUser->getBranchIdsForAction('edit', $gathering);

        $branchesQuery = $this->Gatherings->Branches->find('list')->orderBy(['name' => 'ASC']);
        if ($branchIds !== null) {
            // User has limited access - filter to specific branches
            $branchesQuery->where(['Branches.id IN' => $branchIds]);
        }
        $branches = $branchesQuery;

        $gatheringTypes = $this->Gatherings->GatheringTypes->find('list')->orderBy(['name' => 'ASC']);

        $this->set(compact('gathering', 'branches', 'gatheringTypes'));
    }

    /**
     * Delete method
     *
     * Soft deletes a gathering.
     *
     * @param string|null $id Gathering id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $gathering = $this->Gatherings->get($id);
        $this->Authorization->authorize($gathering);

        $gatheringName = $gathering->name;

        if ($this->Gatherings->delete($gathering)) {
            $this->Flash->success(__(
                'The gathering "{0}" has been deleted successfully.',
                $gatheringName
            ));
        } else {
            $errors = $gathering->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }
                $this->Flash->error(__(
                    'The gathering "{0}" could not be deleted: {1}',
                    $gatheringName,
                    implode(', ', $errorMessages)
                ));
            } else {
                $this->Flash->error(__(
                    'The gathering "{0}" could not be deleted. Please, try again.',
                    $gatheringName
                ));
            }
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Cancel method
     *
     * Marks a gathering as cancelled without deleting it.
     * Preserves all associated data (waivers, attendances, etc.)
     *
     * @param string|null $id Gathering id.
     * @return \Cake\Http\Response|null Redirects to view.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function cancel($id = null)
    {
        $this->request->allowMethod(['post']);
        $gathering = $this->Gatherings->get($id);
        $this->Authorization->authorize($gathering, 'edit');

        $gatheringName = $gathering->name;

        // Check if already cancelled
        if ($gathering->cancelled_at !== null) {
            $this->Flash->warning(__(
                'The gathering "{0}" is already cancelled.',
                $gatheringName
            ));
            return $this->redirect(['action' => 'view', $gathering->public_id]);
        }

        // Mark as cancelled
        $gathering->cancelled_at = \Cake\I18n\DateTime::now();
        $gathering->cancellation_reason = $this->request->getData('cancellation_reason');

        if ($this->Gatherings->save($gathering)) {
            $this->Flash->success(__(
                'The gathering "{0}" has been cancelled.',
                $gatheringName
            ));
        } else {
            $this->Flash->error(__(
                'The gathering "{0}" could not be cancelled. Please try again.',
                $gatheringName
            ));
        }

        return $this->redirect(['action' => 'view', $gathering->public_id]);
    }

    /**
     * Uncancel method
     *
     * Removes the cancelled status from a gathering.
     *
     * @param string|null $id Gathering id.
     * @return \Cake\Http\Response|null Redirects to view.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function uncancel($id = null)
    {
        $this->request->allowMethod(['post']);
        $gathering = $this->Gatherings->get($id);
        $this->Authorization->authorize($gathering, 'edit');

        $gatheringName = $gathering->name;

        // Check if not cancelled
        if ($gathering->cancelled_at === null) {
            $this->Flash->warning(__(
                'The gathering "{0}" is not cancelled.',
                $gatheringName
            ));
            return $this->redirect(['action' => 'view', $gathering->public_id]);
        }

        // Remove cancellation
        $gathering->cancelled_at = null;
        $gathering->cancellation_reason = null;

        if ($this->Gatherings->save($gathering)) {
            $this->Flash->success(__(
                'The gathering "{0}" has been restored.',
                $gatheringName
            ));
        } else {
            $this->Flash->error(__(
                'The gathering "{0}" could not be restored. Please try again.',
                $gatheringName
            ));
        }

        return $this->redirect(['action' => 'view', $gathering->public_id]);
    }

    /**
     * Add Activity method
     *
     * Adds one or more activities to a gathering via modal.
     *
     * @param string|null $id Gathering id.
     * @return \Cake\Http\Response|null Redirects to view.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function addActivity($id = null)
    {
        $this->request->allowMethod(['post']);
        $gathering = $this->Gatherings->get($id, contain: ['GatheringActivities']);
        $this->Authorization->authorize($gathering, "edit");

        // Check if waivers exist - can't modify activities if they do
        // TODO: Implement when Waivers plugin is available
        $hasWaivers = false;
        // $hasWaivers = $this->fetchTable('Waivers.GatheringWaivers')
        //     ->find()->where(['gathering_id' => $id])->count() > 0;

        if ($hasWaivers) {
            $this->Flash->error(__(
                'Cannot add activities because waivers have been uploaded for this gathering.'
            ));
            return $this->redirect(['action' => 'view', $gathering->public_id]);
        }

        $activityId = $this->request->getData('activity_id');

        if (empty($activityId)) {
            $this->Flash->error(__('Please select an activity to add.'));
            return $this->redirect(['action' => 'view', $gathering->public_id]);
        }

        // Get custom description if provided
        $customDescription = $this->request->getData('custom_description');

        // Get existing activity IDs
        $existingIds = array_column($gathering->gathering_activities, 'id');

        // Check if activity is already linked
        if (in_array($activityId, $existingIds)) {
            $this->Flash->warning(__('This activity is already part of this gathering.'));
            return $this->redirect(['action' => 'view', $gathering->public_id]);
        }

        // Link the new activity
        $GatheringsGatheringActivities = $this->fetchTable('GatheringsGatheringActivities');

        $linkData = [
            'gathering_id' => $id,
            'gathering_activity_id' => $activityId,
            'sort_order' => 999 // Will be at the end
        ];

        // Add custom description if provided
        if (!empty(trim($customDescription))) {
            $linkData['custom_description'] = trim($customDescription);
        }

        $link = $GatheringsGatheringActivities->newEntity($linkData);

        if ($GatheringsGatheringActivities->save($link)) {
            $this->Flash->success(__('Activity added successfully.'));
        } else {
            $this->Flash->error(__('Unable to add activity. Please try again.'));
        }

        return $this->redirect(['action' => 'view', $gathering->public_id]);
    }

    /**
     * Remove Activity method
     *
     * Removes an activity from a gathering.
     *
     * @param string|null $gatheringId Gathering id.
     * @param string|null $activityId Activity id.
     * @return \Cake\Http\Response|null Redirects to view.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function removeActivity($gatheringId = null, $activityId = null)
    {
        $this->request->allowMethod(['post']);
        $gathering = $this->Gatherings->get($gatheringId);
        $this->Authorization->authorize($gathering, "edit");

        // Check if waivers exist - can't modify activities if they do
        // TODO: Implement when Waivers plugin is available
        $hasWaivers = false;
        // $hasWaivers = $this->fetchTable('Waivers.GatheringWaivers')
        //     ->find()->where(['gathering_id' => $gatheringId])->count() > 0;

        if ($hasWaivers) {
            $this->Flash->error(__(
                'Cannot remove activities because waivers have been uploaded for this gathering.'
            ));
            return $this->redirect(['action' => 'view', $gathering->public_id]);
        }

        $GatheringsGatheringActivities = $this->fetchTable('GatheringsGatheringActivities');
        $link = $GatheringsGatheringActivities->find()
            ->where([
                'gathering_id' => $gatheringId,
                'gathering_activity_id' => $activityId
            ])
            ->first();

        if (!$link) {
            $this->Flash->error(__('Activity link not found.'));
            return $this->redirect(['action' => 'view', $gathering->public_id]);
        }

        if ($GatheringsGatheringActivities->delete($link)) {
            $this->Flash->success(__('Activity removed successfully.'));
        } else {
            $this->Flash->error(__('Unable to remove activity. Please try again.'));
        }

        return $this->redirect(['action' => 'view', $gathering->public_id]);
    }

    /**
     * Edit Activity Description method
     *
     * Updates the custom description for an activity in a gathering.
     *
     * @param string|null $gatheringId Gathering id.
     * @return \Cake\Http\Response|null Redirects to view.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function editActivityDescription($gatheringId = null)
    {
        $this->request->allowMethod(['post']);
        $gathering = $this->Gatherings->get($gatheringId);
        $this->Authorization->authorize($gathering);

        // Check if waivers exist - can't modify activities if they do
        // TODO: Implement when Waivers plugin is available
        $hasWaivers = false;
        // $hasWaivers = $this->fetchTable('Waivers.GatheringWaivers')
        //     ->find()->where(['gathering_id' => $gatheringId])->count() > 0;

        if ($hasWaivers) {
            $this->Flash->error(__(
                'Cannot edit activity descriptions because waivers have been uploaded for this gathering.'
            ));
            return $this->redirect(['action' => 'view', $gathering->public_id]);
        }

        $activityId = $this->request->getData('activity_id');
        $customDescription = $this->request->getData('custom_description');

        if (empty($activityId)) {
            $this->Flash->error(__('Activity ID is required.'));
            return $this->redirect(['action' => 'view', $gathering->public_id]);
        }

        $GatheringsGatheringActivities = $this->fetchTable('GatheringsGatheringActivities');
        $link = $GatheringsGatheringActivities->find()
            ->where([
                'gathering_id' => $gatheringId,
                'gathering_activity_id' => $activityId
            ])
            ->first();

        if (!$link) {
            $this->Flash->error(__('Activity link not found.'));
            return $this->redirect(['action' => 'view', $gathering->public_id]);
        }

        // Update the custom description (can be empty to clear it)
        $link->custom_description = !empty(trim($customDescription)) ? trim($customDescription) : null;

        if ($GatheringsGatheringActivities->save($link)) {
            $this->Flash->success(__('Activity description updated successfully.'));
        } else {
            $errors = $link->getErrors();
            if (!empty($errors)) {
                $errorMessages = [];
                foreach ($errors as $field => $fieldErrors) {
                    foreach ($fieldErrors as $error) {
                        $errorMessages[] = $error;
                    }
                }
                $this->Flash->error(__(
                    'Unable to update activity description: {0}',
                    implode(', ', $errorMessages)
                ));
            } else {
                $this->Flash->error(__('Unable to update activity description. Please try again.'));
            }
        }

        return $this->redirect(['action' => 'view', $gathering->public_id]);
    }

    /**
     * Clone method
     *
     * Creates a copy of an existing gathering with new name and dates.
     * Optionally includes all activities from the original gathering.
     *
     * @param string|null $id Gathering id to clone.
     * @return \Cake\Http\Response|null Redirects on success.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function clone($id = null)
    {
        $this->request->allowMethod(['post']);

        // Get the original gathering with all its activities, staff, and schedule
        $originalGathering = $this->Gatherings->get($id, contain: [
            'GatheringActivities',
            'GatheringStaff' => ['Members'],
            'GatheringScheduledActivities' => ['GatheringActivities']
        ]);
        $this->Authorization->authorize($originalGathering, 'add');

        // Check if the gathering type is clonable
        $gatheringType = $this->Gatherings->GatheringTypes->get($originalGathering->gathering_type_id);
        if (!$gatheringType->clonable) {
            $this->Flash->error(__('This gathering type cannot be cloned.'));
            return $this->redirect(['action' => 'view', $originalGathering->public_id]);
        }

        // Create new gathering entity with data from form
        $data = $this->request->getData();
        $data['branch_id'] = $originalGathering->branch_id;
        $data['gathering_type_id'] = $originalGathering->gathering_type_id;
        $data['location'] = $originalGathering->location;
        $data['description'] = $originalGathering->description;
        $data['public_page_enabled'] = $originalGathering->public_page_enabled;
        $data['created_by'] = $this->Authentication->getIdentity()->id;

        // Convert datetime inputs from user/gathering timezone to UTC for storage
        if (!empty($data['start_date'])) {
            $timezone = !empty($data['timezone']) ? $data['timezone'] :
                \App\KMP\TimezoneHelper::getUserTimezone($this->Authentication->getIdentity());
            $data['start_date'] = \App\KMP\TimezoneHelper::toUtc($data['start_date'], $timezone);
        }

        if (!empty($data['end_date'])) {
            $timezone = !empty($data['timezone']) ? $data['timezone'] :
                \App\KMP\TimezoneHelper::getUserTimezone($this->Authentication->getIdentity());
            $data['end_date'] = \App\KMP\TimezoneHelper::toUtc($data['end_date'], $timezone);
        }

        // Default end_date to start_date if not provided
        if (empty($data['end_date']) && !empty($data['start_date'])) {
            $data['end_date'] = $data['start_date'];
        }

        $newGathering = $this->Gatherings->newEntity($data);

        if ($this->Gatherings->save($newGathering)) {
            $clonedActivities = 0;
            $clonedStaff = 0;
            $clonedSchedule = 0;

            // Clone activities if requested
            if (!empty($data['clone_activities'])) {
                $GatheringsGatheringActivities = $this->fetchTable('GatheringsGatheringActivities');

                foreach ($originalGathering->gathering_activities as $activity) {
                    $link = $GatheringsGatheringActivities->newEntity([
                        'gathering_id' => $newGathering->id,
                        'gathering_activity_id' => $activity->id,
                        'sort_order' => $activity->_joinData->sort_order ?? 999,
                        'description' => $activity->_joinData->custom_description ?? null
                    ]);

                    if ($GatheringsGatheringActivities->save($link)) {
                        $clonedActivities++;
                    }
                }
            }

            // Clone staff if requested
            if (!empty($data['clone_staff'])) {
                $GatheringStaff = $this->fetchTable('GatheringStaff');

                foreach ($originalGathering->gathering_staff as $staff) {
                    $newStaff = $GatheringStaff->newEntity([
                        'gathering_id' => $newGathering->id,
                        'member_id' => $staff->member_id,
                        'sca_name' => $staff->sca_name,
                        'role' => $staff->role,
                        'is_steward' => $staff->is_steward,
                        'show_on_public_page' => $staff->show_on_public_page,
                        'email' => $staff->email,
                        'phone' => $staff->phone,
                        'contact_notes' => $staff->contact_notes,
                        'sort_order' => $staff->sort_order
                    ]);

                    if ($GatheringStaff->save($newStaff)) {
                        $clonedStaff++;
                    }
                }
            }

            // Clone schedule if requested
            if (!empty($data['clone_schedule'])) {
                $GatheringScheduledActivities = $this->fetchTable('GatheringScheduledActivities');

                // Calculate the time offset between original and new gathering
                // Both dates are now DateTime objects (already converted to UTC)
                $originalStart = $originalGathering->start_date;
                $newStart = $newGathering->start_date;

                // Calculate difference in seconds for precise time offset
                $timeDiff = $newStart->getTimestamp() - $originalStart->getTimestamp();

                foreach ($originalGathering->gathering_scheduled_activities as $scheduledActivity) {
                    // Clone the datetime objects to avoid modifying the originals
                    $newStartDateTime = clone $scheduledActivity->start_datetime;
                    $newStartDateTime = $newStartDateTime->modify(sprintf('%+d seconds', $timeDiff));

                    $newEndDateTime = null;
                    if ($scheduledActivity->end_datetime) {
                        $newEndDateTime = clone $scheduledActivity->end_datetime;
                        $newEndDateTime = $newEndDateTime->modify(sprintf('%+d seconds', $timeDiff));
                    }

                    $newScheduledActivity = $GatheringScheduledActivities->newEntity([
                        'gathering_id' => $newGathering->id,
                        'gathering_activity_id' => $scheduledActivity->gathering_activity_id,
                        'start_datetime' => $newStartDateTime,
                        'end_datetime' => $newEndDateTime,
                        'has_end_time' => !empty($scheduledActivity->end_datetime),
                        'display_title' => $scheduledActivity->display_title,
                        'description' => $scheduledActivity->description,
                        'pre_register' => $scheduledActivity->pre_register ?? false,
                        'is_other' => $scheduledActivity->is_other ?? false,
                    ]);

                    if ($GatheringScheduledActivities->save($newScheduledActivity)) {
                        $clonedSchedule++;
                    } else {
                        // Log errors for debugging
                        $errors = $newScheduledActivity->getErrors();
                        \Cake\Log\Log::error('Failed to clone scheduled activity: ' . json_encode($errors));
                    }
                }
            }

            // Build success message
            $successParts = [];
            if ($clonedActivities > 0) {
                $successParts[] = __('{0} {1}', $clonedActivities, __n('activity', 'activities', $clonedActivities));
            }
            if ($clonedStaff > 0) {
                $successParts[] = __('{0} {1}', $clonedStaff, __n('staff member', 'staff members', $clonedStaff));
            }
            if ($clonedSchedule > 0) {
                $successParts[] = __('{0} {1}', $clonedSchedule, __n('scheduled activity', 'scheduled activities', $clonedSchedule));
            }

            if (!empty($successParts)) {
                $this->Flash->success(__(
                    'Gathering "{0}" has been cloned successfully with {1}.',
                    $newGathering->name,
                    implode(', ', $successParts)
                ));
            } else {
                $this->Flash->success(__(
                    'Gathering "{0}" has been cloned successfully.',
                    $newGathering->name
                ));
            }

            return $this->redirect(['action' => 'view', $newGathering->public_id]);
        }

        $errors = $newGathering->getErrors();
        if (!empty($errors)) {
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $errorMessages[] = $error;
                }
            }
            $this->Flash->error(__(
                'Could not clone gathering: {0}',
                implode(', ', $errorMessages)
            ));
        } else {
            $this->Flash->error(__('Could not clone gathering. Please try again.'));
        }

        return $this->redirect(['action' => 'view', $originalGathering->public_id]);
    }

    /**
     * Add scheduled activity
     *
     * Creates a new scheduled activity for a gathering via AJAX modal.
     *
     * @param string|null $id Gathering id
     * @return \Cake\Http\Response|null JSON response
     */
    public function addScheduledActivity($publicId = null)
    {
        $this->request->allowMethod(['post']);
        $this->viewBuilder()->setClassName('Json');

        $gathering = $this->Gatherings->find('byPublicId', [$publicId])->firstOrFail();
        $this->Authorization->authorize($gathering, 'edit');

        $scheduledActivitiesTable = $this->fetchTable('GatheringScheduledActivities');
        $scheduledActivity = $scheduledActivitiesTable->newEmptyEntity();

        $data = $this->request->getData();
        $data['gathering_id'] = $gathering->id;
        $data['created_by'] = $this->Authentication->getIdentity()->id;

        // Convert datetime inputs from gathering/user timezone to UTC for storage
        $timezone = \App\KMP\TimezoneHelper::getGatheringTimezone($gathering, $this->Authentication->getIdentity());
        if (!empty($data['start_datetime'])) {
            $data['start_datetime'] = \App\KMP\TimezoneHelper::toUtc($data['start_datetime'], $timezone);
        }
        if (!empty($data['end_datetime'])) {
            $data['end_datetime'] = \App\KMP\TimezoneHelper::toUtc($data['end_datetime'], $timezone);
        }

        // Handle "other" checkbox
        if (!empty($data['is_other'])) {
            $data['gathering_activity_id'] = null;
        }

        // Handle "has_end_time" checkbox - clear end_datetime if unchecked
        if (empty($data['has_end_time'])) {
            $data['end_datetime'] = null;
        }

        $scheduledActivity = $scheduledActivitiesTable->patchEntity($scheduledActivity, $data);

        if ($scheduledActivitiesTable->save($scheduledActivity)) {
            $this->set([
                'success' => true,
                'message' => __('Scheduled activity added successfully.'),
                'data' => $scheduledActivity,
            ]);
        } else {
            $errors = $scheduledActivity->getErrors();
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $errorMessages[] = is_string($error) ? $error : implode(', ', $error);
                }
            }

            $this->set([
                'success' => false,
                'message' => __('Could not add scheduled activity.'),
                'errors' => $errorMessages,
            ]);
        }

        $this->viewBuilder()->setOption('serialize', ['success', 'message', 'data', 'errors']);
        return null;
    }

    /**
     * Edit scheduled activity
     *
     * Updates an existing scheduled activity via AJAX modal.
     *
     * @param string|null $gatheringId Gathering id
     * @param string|null $id Scheduled activity id
     * @return \Cake\Http\Response|null JSON response
     */
    public function editScheduledActivity($gatheringPublicId = null, $id = null)
    {
        $this->request->allowMethod(['post', 'put', 'patch']);
        $this->viewBuilder()->setClassName('Json');

        $gathering = $this->Gatherings->find('byPublicId', [$gatheringPublicId])->firstOrFail();
        $this->Authorization->authorize($gathering, 'edit');

        $scheduledActivitiesTable = $this->fetchTable('GatheringScheduledActivities');
        $scheduledActivity = $scheduledActivitiesTable->get($id);

        // Ensure scheduled activity belongs to this gathering
        if ($scheduledActivity->gathering_id != $gathering->id) {
            $this->set([
                'success' => false,
                'message' => __('Invalid scheduled activity.'),
            ]);
            $this->viewBuilder()->setOption('serialize', ['success', 'message']);
            return null;
        }

        $data = $this->request->getData();
        $data['modified_by'] = $this->Authentication->getIdentity()->id;

        // Convert datetime inputs from gathering/user timezone to UTC for storage
        $timezone = \App\KMP\TimezoneHelper::getGatheringTimezone($gathering, $this->Authentication->getIdentity());
        if (!empty($data['start_datetime'])) {
            $data['start_datetime'] = \App\KMP\TimezoneHelper::toUtc($data['start_datetime'], $timezone);
        }
        if (!empty($data['end_datetime'])) {
            $data['end_datetime'] = \App\KMP\TimezoneHelper::toUtc($data['end_datetime'], $timezone);
        }

        // Handle "other" checkbox
        if (!empty($data['is_other'])) {
            $data['gathering_activity_id'] = null;
        }

        // Handle "has_end_time" checkbox - clear end_datetime if unchecked
        if (empty($data['has_end_time'])) {
            $data['end_datetime'] = null;
        }

        $scheduledActivity = $scheduledActivitiesTable->patchEntity($scheduledActivity, $data);

        if ($scheduledActivitiesTable->save($scheduledActivity)) {
            $this->set([
                'success' => true,
                'message' => __('Scheduled activity updated successfully.'),
                'data' => $scheduledActivity,
            ]);
        } else {
            $errors = $scheduledActivity->getErrors();
            $errorMessages = [];
            foreach ($errors as $field => $fieldErrors) {
                foreach ($fieldErrors as $error) {
                    $errorMessages[] = is_string($error) ? $error : implode(', ', $error);
                }
            }

            $this->set([
                'success' => false,
                'message' => __('Could not update scheduled activity.'),
                'errors' => $errorMessages,
            ]);
        }

        $this->viewBuilder()->setOption('serialize', ['success', 'message', 'data', 'errors']);
        return null;
    }

    /**
     * Delete scheduled activity
     *
     * Removes a scheduled activity from a gathering.
     *
     * @param string|null $gatheringId Gathering id
     * @param string|null $id Scheduled activity id
     * @return \Cake\Http\Response|null Redirect response
     */
    public function deleteScheduledActivity($gatheringPublicId = null, $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);

        $gathering = $this->Gatherings->find('byPublicId', [$gatheringPublicId])->firstOrFail();
        $this->Authorization->authorize($gathering, 'edit');

        $scheduledActivitiesTable = $this->fetchTable('GatheringScheduledActivities');
        $scheduledActivity = $scheduledActivitiesTable->get($id);

        // Ensure scheduled activity belongs to this gathering
        if ($scheduledActivity->gathering_id != $gathering->id) {
            $this->Flash->error(__('Invalid scheduled activity.'));
            return $this->redirect(['action' => 'view', $gathering->public_id]);
        }

        if ($scheduledActivitiesTable->delete($scheduledActivity)) {
            $this->Flash->success(__('Scheduled activity deleted successfully.'));
        } else {
            $this->Flash->error(__('Could not delete scheduled activity. Please try again.'));
        }

        return $this->redirect(['action' => 'view', $gathering->public_id]);
    }

    /**
     * Public landing page for a gathering
     *
     * Displays a beautiful, mobile-friendly event landing page that requires no authentication.
     * This page showcases all gathering information including schedule, activities, location,
     * and provides a streamlined experience for potential attendees.
     *
     * @param string|null $id Gathering id
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function publicLanding($publicId = null)
    {
        // Skip authorization for public page
        $this->Authorization->skipAuthorization();

        // Use a clean layout without authentication
        $this->viewBuilder()->setLayout('public_event');

        // Load the gathering with all related data
        $gathering = $this->Gatherings->find('byPublicId', [$publicId])
            ->contain([
                'Branches',
                'GatheringTypes',
                'Creators' => ['fields' => ['id', 'sca_name']],
                'GatheringStaff' => [
                    'Members' => ['fields' => ['id', 'sca_name']],
                    'conditions' => ['GatheringStaff.show_on_public_page' => true],
                    'sort' => ['GatheringStaff.is_steward' => 'DESC', 'GatheringStaff.sort_order' => 'ASC']
                ],
                'GatheringActivities' => [
                    'sort' => ['GatheringsGatheringActivities.sort_order' => 'ASC']
                ],
                'GatheringScheduledActivities' => [
                    'GatheringActivities',
                    'sort' => ['GatheringScheduledActivities.start_datetime' => 'ASC']
                ]
            ])
            ->firstOrFail();

        // Check if public page is enabled for this gathering
        if (!$gathering->public_page_enabled) {
            throw new NotFoundException(__('The public page for this gathering is not available.'));
        }

        // Enrich scheduled activities with custom descriptions from junction table
        // Create a map of gathering_activity_id => custom_description
        $customDescriptions = [];
        foreach ($gathering->gathering_activities as $gatheringActivity) {
            if (!empty($gatheringActivity->custom_description)) {
                $customDescriptions[$gatheringActivity->id] = $gatheringActivity->custom_description;
            }
        }

        // Apply custom descriptions to scheduled activities
        foreach ($gathering->gathering_scheduled_activities as $scheduledActivity) {
            if ($scheduledActivity->gathering_activity_id && isset($customDescriptions[$scheduledActivity->gathering_activity_id])) {
                $scheduledActivity->gathering_activity->custom_description = $customDescriptions[$scheduledActivity->gathering_activity_id];
            }
        }

        // Group scheduled activities by date
        $scheduleByDate = [];
        foreach ($gathering->gathering_scheduled_activities as $scheduledActivity) {
            $date = $scheduledActivity->start_datetime->format('Y-m-d');
            if (!isset($scheduleByDate[$date])) {
                $scheduleByDate[$date] = [];
            }
            $scheduleByDate[$date][] = $scheduledActivity;
        }

        // Calculate event duration
        $durationDays = $gathering->start_date->diffInDays($gathering->end_date) + 1;

        // Check if user is authenticated and load their attendance record
        $user = null;
        $userAttendance = null;

        $identity = $this->Authentication->getIdentity();
        if ($identity) {
            $user = $this->fetchTable('Members')->get($identity->id);

            // Check if user has an attendance record for this gathering
            $attendanceTable = $this->fetchTable('GatheringAttendances');
            $userAttendance = $attendanceTable->find()
                ->where([
                    'gathering_id' => $gathering->id,
                    'member_id' => $user->id
                ])
                ->first();
        }

        $kingdomAttendances = [];
        if ($identity) {
            $kingdomAttendances = $this->fetchTable('GatheringAttendances')
                ->find()
                ->contain(['Members' => ['fields' => ['id', 'sca_name']]])
                ->where([
                    'gathering_id' => $gathering->id,
                    'share_with_kingdom' => true,
                ])
                ->orderBy(['Members.sca_name' => 'ASC'])
                ->all()
                ->toArray();
        }

        $this->set(compact(
            'gathering',
            'scheduleByDate',
            'durationDays',
            'user',
            'userAttendance',
            'kingdomAttendances'
        ));
    }

    /**
     * Download calendar file for a gathering
     *
     * Generates an iCalendar (.ics) file that can be imported into
     * calendar applications (Google Calendar, Outlook, iOS Calendar, etc.)
     *
     * Security logic:
     * - If gathering has public_page_enabled = true: accessible to anyone (authenticated or not)
     * - If gathering has public_page_enabled = false: requires authentication (but no policy check)
     *
     * @param \App\Services\ICalendarService $iCalendarService iCalendar service
     * @param string|null $publicId Gathering public ID (for public access)
     * @param int|null $id Gathering ID (for authenticated access)
     * @return \Cake\Http\Response iCalendar file download response
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When gathering not found
     */
    public function downloadCalendar(ICalendarService $iCalendarService, string $publicId = null)
    {

        if (!$publicId) {
            throw new NotFoundException(__('Gathering not found.'));
        }
        $gathering = $this->Gatherings->find()
            ->where(['Gatherings.public_id' => $publicId])
            ->contain([
                'Branches',
                'GatheringTypes',
                'GatheringActivities',
                'GatheringStaff' => [
                    'Members' => ['fields' => ['id', 'sca_name']]
                ]
            ])
            ->firstOrFail();

        // Build public URL
        $baseUrl = $this->request->getAttribute('base');
        $fullBaseUrl = $this->request->scheme() . '://' . $this->request->host() . $baseUrl;
        $eventUrl = $fullBaseUrl . '/gatherings/public-landing/' . $gathering->public_id;

        // Check security based on public_page_enabled setting
        if ($gathering->public_page_enabled === false) {
            // Private gathering - require authentication but no policy check
            $identity = $this->Authentication->getIdentity();
            if (!$identity) {
                throw new NotFoundException(__('The calendar for this gathering is not publicly available.'));
            }
        }

        // Skip authorization policy check - authentication (if required) is sufficient
        $this->Authorization->skipAuthorization();
        // Generate iCalendar content
        $icsContent = $iCalendarService->generateICalendar($gathering, $eventUrl);

        // Generate filename
        $filename = $iCalendarService->getFilename($gathering) . '.ics';

        // Create response with iCalendar content
        $response = $this->response
            ->withType('text/calendar')
            ->withCharset('UTF-8')
            ->withDownload($filename)
            ->withStringBody($icsContent);

        return $response;
    }
}
