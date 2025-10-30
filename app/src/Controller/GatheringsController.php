<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\CsvExportService;
use Cake\Http\Exception\NotFoundException;
use DateTime;

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
    /**
     * CSV export service dependency injection
     *
     * @var array<string> Service injection configuration
     */
    public static array $inject = [CsvExportService::class];

    /**
     * CSV export service instance
     *
     * @var \App\Services\CsvExportService
     */
    protected CsvExportService $csvExportService;

    /**
     * Initialize controller
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Authorize model-level operations
        $this->Authorization->authorizeModel('index', 'add');
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
        $query = $this->Gatherings->find()
            ->contain([
                'Branches',
                'GatheringTypes',
                'GatheringActivities',
                'Creators' => ['fields' => ['id', 'sca_name']]
            ])
            ->orderBy(['Gatherings.start_date' => 'DESC']);
        $branch_id = null;
        $gathering_type_id = null;
        $start_date = null;
        $end_date = null;
        // Apply filters if provided
        if ($this->request->getQuery('branch_id')) {
            $branch_id = $this->request->getQuery('branch_id');
            $query->where(['Gatherings.branch_id' => $branch_id]);
        }

        if ($this->request->getQuery('gathering_type_id')) {
            $gathering_type_id = $this->request->getQuery('gathering_type_id');
            $query->where(['Gatherings.gathering_type_id' => $gathering_type_id]);
        }

        if ($this->request->getQuery('start_date')) {
            $start_date = $this->request->getQuery('start_date');
            $query->where(['Gatherings.start_date >=' => $start_date]);
        }

        if ($this->request->getQuery('end_date')) {
            $end_date = $this->request->getQuery('end_date');
            $query->where(['Gatherings.end_date <=' => $end_date]);
        }

        $gatherings = $this->paginate($query);

        // Load filter options - filter branches based on user permissions
        $currentUser = $this->Authentication->getIdentity();
        $branchIds = $currentUser->getBranchIdsForAction('index', 'Gatherings');

        $branchesQuery = $this->Gatherings->Branches->find('list')->orderBy(['name' => 'ASC']);
        if ($branchIds !== null) {
            // User has limited access - filter to specific branches
            $branchesQuery->where(['Branches.id IN' => $branchIds]);
        }
        $branches = $branchesQuery;

        $gatheringTypes = $this->Gatherings->GatheringTypes->find('list')->orderBy(['name' => 'ASC']);

        $this->set(compact('gatherings', 'branches', 'gatheringTypes', 'branch_id', 'gathering_type_id', 'start_date', 'end_date'));
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
        // Create security entity for authorization
        $securityGathering = $this->Gatherings->newEmptyEntity();
        $this->Authorization->authorize($securityGathering, 'index');

        $currentUser = $this->Authentication->getIdentity();
        $branchIds = $currentUser->getBranchIdsForAction('index', 'Gatherings');

        // Get query parameters for date navigation and filters
        $year = (int)$this->request->getQuery('year', date('Y'));
        $month = (int)$this->request->getQuery('month', date('m'));
        $view = $this->request->getQuery('view', 'month'); // month, week, list
        $branchFilter = $this->request->getQuery('branch_id');
        $typeFilter = $this->request->getQuery('gathering_type_id');
        $activityFilter = $this->request->getQuery('activity_id');

        // Validate year and month
        if ($year < 1900 || $year > 2100) {
            $year = (int)date('Y');
        }
        if ($month < 1 || $month > 12) {
            $month = (int)date('m');
        }

        // Calculate date ranges
        $startDate = new DateTime(sprintf("%04d-%02d-01", $year, $month));
        $endDate = clone $startDate;
        $endDate->modify('last day of this month')->setTime(23, 59, 59);

        // For calendar display, we need to include days from previous/next months
        $calendarStart = clone $startDate;
        // Move back to the previous Sunday (or stay if already Sunday)
        $dayOfWeek = (int)$calendarStart->format('w');
        if ($dayOfWeek > 0) {
            $calendarStart->modify("-{$dayOfWeek} days");
        }

        $calendarEnd = clone $endDate;
        // Move forward to the next Saturday (or stay if already Saturday)
        $dayOfWeek = (int)$calendarEnd->format('w');
        if ($dayOfWeek < 6) {
            $daysToAdd = 6 - $dayOfWeek;
            $calendarEnd->modify("+{$daysToAdd} days");
        }

        // Build query for gatherings in the calendar range
        $query = $this->Gatherings->find()
            ->contain([
                'Branches' => ['fields' => ['id', 'name']],
                'GatheringTypes' => ['fields' => ['id', 'name', 'color']],
                'GatheringActivities' => ['fields' => ['id', 'name']],
                'GatheringAttendances' => [
                    'conditions' => ['GatheringAttendances.member_id' => $currentUser->id],
                    'fields' => ['id', 'gathering_id', 'member_id']
                ]
            ])
            ->where([
                'OR' => [
                    [
                        'Gatherings.start_date >=' => $calendarStart->format('Y-m-d'),
                        'Gatherings.start_date <=' => $calendarEnd->format('Y-m-d')
                    ],
                    [
                        'Gatherings.end_date >=' => $calendarStart->format('Y-m-d'),
                        'Gatherings.end_date <=' => $calendarEnd->format('Y-m-d')
                    ],
                    [
                        'Gatherings.start_date <' => $calendarStart->format('Y-m-d'),
                        'Gatherings.end_date >' => $calendarEnd->format('Y-m-d')
                    ]
                ]
            ])
            ->orderBy(['Gatherings.start_date' => 'ASC']);

        // Apply filters
        if ($branchIds !== null) {
            $query->where(['Gatherings.branch_id IN' => $branchIds]);
        }

        if ($branchFilter) {
            $query->where(['Gatherings.branch_id' => $branchFilter]);
        }

        if ($typeFilter) {
            $query->where(['Gatherings.gathering_type_id' => $typeFilter]);
        }

        if ($activityFilter) {
            $query->matching('GatheringActivities', function ($q) use ($activityFilter) {
                return $q->where(['GatheringActivities.id' => $activityFilter]);
            });
        }

        $gatherings = $query->all();

        // Load filter options
        $branchesQuery = $this->Gatherings->Branches->find('list')->orderBy(['name' => 'ASC']);
        if ($branchIds !== null) {
            $branchesQuery->where(['Branches.id IN' => $branchIds]);
        }
        $branches = $branchesQuery;

        $gatheringTypes = $this->Gatherings->GatheringTypes->find('list')->orderBy(['name' => 'ASC']);
        $gatheringActivities = $this->Gatherings->GatheringActivities->find('list')->orderBy(['name' => 'ASC']);

        // Navigation dates
        $prevMonth = clone $startDate;
        $prevMonth->modify('-1 month');
        $nextMonth = clone $startDate;
        $nextMonth->modify('+1 month');

        $this->set(compact(
            'gatherings',
            'year',
            'month',
            'view',
            'startDate',
            'endDate',
            'calendarStart',
            'calendarEnd',
            'prevMonth',
            'nextMonth',
            'branches',
            'gatheringTypes',
            'gatheringActivities',
            'branchFilter',
            'typeFilter',
            'activityFilter'
        ));
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
    public function quickView($id = null)
    {
        $gathering = $this->Gatherings->get($id, [
            'contain' => [
                'Branches' => ['fields' => ['id', 'name']],
                'GatheringTypes' => ['fields' => ['id', 'name', 'color', 'clonable']],
                'GatheringActivities',
                'GatheringAttendances' => [
                    'Members' => ['fields' => ['id', 'sca_name']],
                    'conditions' => [
                        'OR' => [
                            'GatheringAttendances.is_public' => true,
                            'GatheringAttendances.share_with_kingdom' => true,
                        ]
                    ]
                ],
                'Creators' => ['fields' => ['id', 'sca_name']]
            ],
            'fields' => [
                'id',
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
            ]
        ]);

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
                'gathering_id' => $id,
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

        $this->Authorization->authorize($gathering, 'view');

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
     * Provides comprehensive gathering listing with temporal filtering, pagination,
     * and CSV export functionality. This method handles the core gathering management
     * interface with optimized queries and user-friendly filtering options.
     *
     * ### Temporal State Filtering
     * Supports four distinct gathering states:
     * - **this_month**: Gatherings occurring in the current calendar month
     * - **next_month**: Gatherings occurring in the next calendar month
     * - **future**: Gatherings occurring after next month
     * - **previous**: Past gatherings that have ended before this month
     *
     * ### Query Optimization
     * Implements efficient database queries with proper association loading
     * and date-based filtering for performance.
     *
     * ### CSV Export Integration
     * Provides memory-efficient CSV export:
     * - Streaming export handles large datasets without memory issues
     * - Optimized fields for performance
     * - Sorted output by gathering start date for usability
     * - Security: Same authorization rules apply to export functionality
     *
     * ### Authorization and Security
     * - Entity authorization for permission checking
     * - State validation for filter parameters
     * - Access control for gathering management permissions
     *
     * ### Error Handling
     * - Invalid state throws NotFoundException
     * - Authorization failure handled properly
     * - Database errors handled gracefully
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @param string $state Temporal filter state (this_month|next_month|future|previous)
     * @return \Cake\Http\Response|null|void Renders gathering list or returns CSV export
     * @throws \Cake\Http\Exception\NotFoundException When invalid state provided
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
            ->contain([
                'Branches' => ['fields' => ['id', 'name']],
                'GatheringTypes' => ['fields' => ['id', 'name']],
                'GatheringActivities' => ['fields' => ['id', 'name']],
                'Creators' => ['fields' => ['id', 'sca_name']]
            ]);

        // Apply temporal filtering based on current date and month boundaries
        $today = new DateTime();
        $today->setTime(0, 0, 0); // Set to start of day for accurate comparisons

        // Calculate month boundaries
        $thisMonthStart = new DateTime('first day of this month');
        $thisMonthStart->setTime(0, 0, 0);

        $thisMonthEnd = new DateTime('last day of this month');
        $thisMonthEnd->setTime(23, 59, 59);

        $nextMonthStart = new DateTime('first day of next month');
        $nextMonthStart->setTime(0, 0, 0);

        $nextMonthEnd = new DateTime('last day of next month');
        $nextMonthEnd->setTime(23, 59, 59);

        switch ($state) {
            case 'this_month':
                // Gatherings that overlap with the current calendar month
                $gatheringsQuery = $gatheringsQuery->where([
                    'OR' => [
                        // Starts this month
                        [
                            'Gatherings.start_date >=' => $thisMonthStart,
                            'Gatherings.start_date <=' => $thisMonthEnd
                        ],
                        // Ends this month
                        [
                            'Gatherings.end_date >=' => $thisMonthStart,
                            'Gatherings.end_date <=' => $thisMonthEnd
                        ],
                        // Spans across this month
                        [
                            'Gatherings.start_date <' => $thisMonthStart,
                            'Gatherings.end_date >' => $thisMonthEnd
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
                            'Gatherings.start_date >=' => $nextMonthStart,
                            'Gatherings.start_date <=' => $nextMonthEnd
                        ],
                        // Ends next month
                        [
                            'Gatherings.end_date >=' => $nextMonthStart,
                            'Gatherings.end_date <=' => $nextMonthEnd
                        ],
                        // Spans across next month
                        [
                            'Gatherings.start_date <' => $nextMonthStart,
                            'Gatherings.end_date >' => $nextMonthEnd
                        ]
                    ]
                ]);
                break;
            case 'future':
                // Gatherings that start after next month
                $gatheringsQuery = $gatheringsQuery->where([
                    'Gatherings.start_date >' => $nextMonthEnd
                ]);
                break;
            case 'previous':
                // Past gatherings that ended before this month
                $gatheringsQuery = $gatheringsQuery->where([
                    'Gatherings.end_date <' => $thisMonthStart
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
    public function view($id = null)
    {
        $gathering = $this->Gatherings->get($id, contain: [
            'Branches',
            'GatheringTypes' => ['fields' => ['id', 'name', 'clonable']],
            'GatheringActivities',
            'Creators' => ['fields' => ['id', 'sca_name']],
            'GatheringAttendances' => [
                'Members' => ['fields' => ['id', 'sca_name']],
                'conditions' => [
                    'OR' => [
                        'GatheringAttendances.share_with_hosting_group' => true,
                        'GatheringAttendances.is_public' => true,
                    ]
                ]
            ],
        ]);

        $this->Authorization->authorize($gathering);

        //TODO: find a way to do this with out breaking the plugin/core boundry.
        // Check if waivers exist (for activity locking)
        // This is used to determine if activities can be added/removed
        $hasWaivers = false;
        if (class_exists('Waivers\Model\Table\GatheringWaiversTable')) {
            $hasWaivers = $this->fetchTable('Waivers.GatheringWaivers')
                ->find()->where(['gathering_id' => $id])->count() > 0;
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
            ->where(['gathering_id' => $id])
            ->count();

        // Check if current user has an attendance record for this gathering
        $currentUser = $this->Authentication->getIdentity();
        $userAttendance = $this->Gatherings->GatheringAttendances
            ->find()
            ->where([
                'gathering_id' => $id,
                'member_id' => $currentUser->id
            ])
            ->first();

        $this->set(compact('gathering', 'hasWaivers', 'availableActivities', 'totalAttendanceCount', 'userAttendance'));
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

                return $this->redirect(['action' => 'view', $gathering->id]);
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
            $gathering = $this->Gatherings->patchEntity($gathering, $this->request->getData());

            if ($this->Gatherings->save($gathering)) {
                $this->Flash->success(__(
                    'The gathering "{0}" has been updated successfully.',
                    $gathering->name
                ));

                return $this->redirect(['action' => 'view', $id]);
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
            return $this->redirect(['action' => 'view', $id]);
        }

        $activityId = $this->request->getData('activity_id');

        if (empty($activityId)) {
            $this->Flash->error(__('Please select an activity to add.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        // Get custom description if provided
        $customDescription = $this->request->getData('custom_description');

        // Get existing activity IDs
        $existingIds = array_column($gathering->gathering_activities, 'id');

        // Check if activity is already linked
        if (in_array($activityId, $existingIds)) {
            $this->Flash->warning(__('This activity is already part of this gathering.'));
            return $this->redirect(['action' => 'view', $id]);
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

        return $this->redirect(['action' => 'view', $id]);
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
            return $this->redirect(['action' => 'view', $gatheringId]);
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
            return $this->redirect(['action' => 'view', $gatheringId]);
        }

        if ($GatheringsGatheringActivities->delete($link)) {
            $this->Flash->success(__('Activity removed successfully.'));
        } else {
            $this->Flash->error(__('Unable to remove activity. Please try again.'));
        }

        return $this->redirect(['action' => 'view', $gatheringId]);
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
            return $this->redirect(['action' => 'view', $gatheringId]);
        }

        $activityId = $this->request->getData('activity_id');
        $customDescription = $this->request->getData('custom_description');

        if (empty($activityId)) {
            $this->Flash->error(__('Activity ID is required.'));
            return $this->redirect(['action' => 'view', $gatheringId]);
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
            return $this->redirect(['action' => 'view', $gatheringId]);
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

        return $this->redirect(['action' => 'view', $gatheringId]);
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

        // Get the original gathering with all its activities
        $originalGathering = $this->Gatherings->get($id, contain: ['GatheringActivities']);
        $this->Authorization->authorize($originalGathering, 'add');

        // Check if the gathering type is clonable
        $gatheringType = $this->Gatherings->GatheringTypes->get($originalGathering->gathering_type_id);
        if (!$gatheringType->clonable) {
            $this->Flash->error(__('This gathering type cannot be cloned.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        // Create new gathering entity with data from form
        $data = $this->request->getData();
        $data['branch_id'] = $originalGathering->branch_id;
        $data['gathering_type_id'] = $originalGathering->gathering_type_id;
        $data['location'] = $originalGathering->location;
        $data['description'] = $originalGathering->description;
        $data['created_by'] = $this->Authentication->getIdentity()->id;

        // Default end_date to start_date if not provided
        if (empty($data['end_date']) && !empty($data['start_date'])) {
            $data['end_date'] = $data['start_date'];
        }

        $newGathering = $this->Gatherings->newEntity($data);

        if ($this->Gatherings->save($newGathering)) {
            // Clone activities if requested
            if (!empty($data['clone_activities'])) {
                $GatheringsGatheringActivities = $this->fetchTable('GatheringsGatheringActivities');
                $clonedCount = 0;

                foreach ($originalGathering->gathering_activities as $activity) {
                    $link = $GatheringsGatheringActivities->newEntity([
                        'gathering_id' => $newGathering->id,
                        'gathering_activity_id' => $activity->id,
                        'sort_order' => 999
                    ]);

                    if ($GatheringsGatheringActivities->save($link)) {
                        $clonedCount++;
                    }
                }

                $this->Flash->success(__(
                    'Gathering "{0}" has been cloned successfully with {1} {2}.',
                    $newGathering->name,
                    $clonedCount,
                    __n('activity', 'activities', $clonedCount)
                ));
            } else {
                $this->Flash->success(__(
                    'Gathering "{0}" has been cloned successfully.',
                    $newGathering->name
                ));
            }

            return $this->redirect(['action' => 'view', $newGathering->id]);
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

        return $this->redirect(['action' => 'view', $id]);
    }
}
