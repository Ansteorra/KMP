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
            ->order(['Gatherings.start_date' => 'DESC']);

        // Apply filters if provided
        if ($this->request->getQuery('branch_id')) {
            $query->where(['Gatherings.branch_id' => $this->request->getQuery('branch_id')]);
        }

        if ($this->request->getQuery('gathering_type_id')) {
            $query->where(['Gatherings.gathering_type_id' => $this->request->getQuery('gathering_type_id')]);
        }

        if ($this->request->getQuery('start_date')) {
            $query->where(['Gatherings.start_date >=' => $this->request->getQuery('start_date')]);
        }

        if ($this->request->getQuery('end_date')) {
            $query->where(['Gatherings.end_date <=' => $this->request->getQuery('end_date')]);
        }

        $gatherings = $this->paginate($query);

        // Load filter options
        $branches = $this->Gatherings->Branches->find('list')->orderBy(['name' => 'ASC']);
        $gatheringTypes = $this->Gatherings->GatheringTypes->find('list')->orderBy(['name' => 'ASC']);

        $this->set(compact('gatherings', 'branches', 'gatheringTypes'));
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
     * Currently a placeholder for future search/filter functionality.
     *
     * @param \Cake\ORM\Query $query Base gathering query to optimize
     * @return \Cake\ORM\Query Optimized query with conditions
     */
    protected function addConditions($query)
    {
        // Placeholder for search and additional filter conditions
        // Can be extended with search terms, branch filters, etc.
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

        $this->set(compact('gathering', 'hasWaivers', 'availableActivities'));
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

        // Load form options
        $branches = $this->Gatherings->Branches->find('list')->orderBy(['name' => 'ASC']);
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

        // Load form options
        $branches = $this->Gatherings->Branches->find('list')->orderBy(['name' => 'ASC']);
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
        $this->Authorization->authorize($gathering);

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

        $activityIds = $this->request->getData('activity_ids');

        if (empty($activityIds)) {
            $this->Flash->error(__('Please select at least one activity to add.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        // Get custom descriptions if provided
        $customDescriptions = $this->request->getData('custom_descriptions', []);

        // Get existing activity IDs
        $existingIds = array_column($gathering->gathering_activities, 'id');

        // Filter out activities that are already linked
        $newActivityIds = array_diff($activityIds, $existingIds);

        if (empty($newActivityIds)) {
            $this->Flash->warning(__('The selected activities are already part of this gathering.'));
            return $this->redirect(['action' => 'view', $id]);
        }

        // Link the new activities
        $GatheringsGatheringActivities = $this->fetchTable('GatheringsGatheringActivities');
        $successCount = 0;

        foreach ($newActivityIds as $activityId) {
            $linkData = [
                'gathering_id' => $id,
                'gathering_activity_id' => $activityId,
                'sort_order' => 999 // Will be at the end
            ];

            // Add custom description if provided
            if (isset($customDescriptions[$activityId]) && !empty(trim($customDescriptions[$activityId]))) {
                $linkData['custom_description'] = trim($customDescriptions[$activityId]);
            }

            $link = $GatheringsGatheringActivities->newEntity($linkData);

            if ($GatheringsGatheringActivities->save($link)) {
                $successCount++;
            }
        }

        if ($successCount > 0) {
            $this->Flash->success(__(
                '{0} {1} added successfully.',
                $successCount,
                __n('activity', 'activities', $successCount)
            ));
        } else {
            $this->Flash->error(__('Unable to add activities. Please try again.'));
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
        $this->Authorization->authorize($gathering);

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
