<?php

declare(strict_types=1);

namespace Awards\Controller;

use Awards\Controller\AppController;
use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;
use App\KMP\StaticHelpers;
use Authorization\Exception\ForbiddenException;
use Cake\Log\Log;
use Exception;
use PhpParser\Node\Stmt\TryCatch;
use App\Services\CsvExportService;

/**
 * Recommendations Controller
 *
 * @property \Awards\Model\Table\RecommendationsTable $Recommendations
 */
class RecommendationsController extends AppController
{
    /**
     * Before filter callback.
     *
     * @param \Cake\Event\EventInterface $event The event instance.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(\Cake\Event\EventInterface $event): ?\Cake\Http\Response
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated([
            'submitRecommendation'
        ]);

        return null;
    }

    /**
     * Index method - Landing page for recommendations with view configuration
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index(): ?\Cake\Http\Response
    {
        $view = $this->request->getQuery('view') ?? 'Index';
        $status = $this->request->getQuery('status') ?? 'All';

        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $queryArgs = $this->request->getQuery();
        $user = $this->request->getAttribute('identity');
        $user->authorizeWithArgs($emptyRecommendation, 'index', $view, $status, $queryArgs);

        try {
            if ($view && $view !== 'Index') {
                try {
                    $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig." . $view);
                } catch (\Exception $e) {
                    Log::debug('View config not found for ' . $view . ': ' . $e->getMessage());
                    $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
                }
            } else {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
            }

            if ($pageConfig['board']['use']) {
                $pageConfig['board']['use'] = $user->checkCan('UseBoard', $emptyRecommendation, $status, $view);
            }

            $this->set(compact('view', 'status', 'pageConfig'));
            return null;
        } catch (\Exception $e) {
            Log::error('Error in recommendations index: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while loading recommendations.'));
            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'home']);
        }
    }

    /**
     * Table display method for recommendations with optional CSV export
     *
     * @param \App\Services\CsvExportService $csvExportService Service for CSV exports
     * @param string|null $view View configuration name
     * @param string|null $status Status filter
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function table(CsvExportService $csvExportService, ?string $view = null, ?string $status = null): ?\Cake\Http\Response
    {
        $view = $view ?? 'Default';
        $status = $status ?? 'All';

        try {
            $emptyRecommendation = $this->Recommendations->newEmptyEntity();
            if ($view && $view !== 'Default') {
                try {
                    $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig." . $view);
                } catch (\Exception $e) {
                    Log::debug('View config not found for ' . $view . ': ' . $e->getMessage());
                    $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
                }
                $filter = $pageConfig['table']['filter'];
            } else {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
                $filter = $pageConfig['table']['filter'];
            }

            $permission = isset($pageConfig['table']['optionalPermission']) && $pageConfig['table']['optionalPermission']
                ? $pageConfig['table']['optionalPermission']
                : 'index';

            $queryArgs = $this->request->getQuery();
            $user = $this->request->getAttribute('identity');

            if ($view === 'SubmittedByMember') {
                $emptyRecommendation->requester_id = $user->id;
            }

            $user->authorizeWithArgs($emptyRecommendation, $permission, $view, $status, $queryArgs);

            $filter = $this->processFilter($filter);
            $enableExport = $pageConfig['table']['enableExport'];

            if ($enableExport && $this->isCsvRequest()) {
                $columns = $pageConfig['table']['export'];
                return $this->runExport($csvExportService, $filter, $columns);
            }

            $this->set(compact('pageConfig', 'enableExport'));
            $this->runTable($filter, $status, $view);
            return null;
        } catch (\Exception $e) {
            Log::error('Error in recommendations table: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while loading recommendations.'));
            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * Board view method for kanban-style recommendation display
     *
     * @param string|null $view View configuration name
     * @param string|null $status Status filter
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function board(?string $view = null, ?string $status = null): ?\Cake\Http\Response
    {
        $view = $view ?? 'Default';
        $status = $status ?? 'All';

        try {
            $emptyRecommendation = $this->Recommendations->newEmptyEntity();
            $queryArgs = $this->request->getQuery();
            $user = $this->request->getAttribute('identity');
            $user->authorizeWithArgs($emptyRecommendation, 'index', $view, $status, $queryArgs);

            if ($view && $view !== 'Index') {
                try {
                    $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig." . $view);
                } catch (\Exception $e) {
                    Log::debug('View config not found for ' . $view . ': ' . $e->getMessage());
                    $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
                }
            } else {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
            }

            if (!$pageConfig['board']['use']) {
                $this->Flash->info(__('Board view is not enabled for this configuration.'));
                return $this->redirect(['action' => 'index']);
            }

            $this->set(compact('pageConfig'));
            $this->runBoard($view, $pageConfig, $emptyRecommendation);
            return null;
        } catch (\Exception $e) {
            Log::error('Error in recommendations board: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while loading the board view.'));
            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * Bulk update the state and status of recommendations.
     *
     * @return \Cake\Http\Response|null Redirects to index or specified page.
     */
    public function updateStates(): ?\Cake\Http\Response
    {
        $view = $this->request->getData('view') ?? 'Index';
        $status = $this->request->getData('status') ?? 'All';

        $this->request->allowMethod(['post', 'get']);
        $user = $this->request->getAttribute('identity');
        $recommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($recommendation);

        $ids = explode(',', $this->request->getData('ids'));
        $newState = $this->request->getData('newState');
        $event_id = $this->request->getData('event_id');
        $given = $this->request->getData('given');
        $note = $this->request->getData('note');
        $close_reason = $this->request->getData('close_reason');

        if (empty($ids) || empty($newState)) {
            $this->Flash->error(__('No recommendations selected or new state not specified.'));
        } else {
            $this->Recommendations->getConnection()->begin();
            try {
                $statusList = Recommendation::getStatuses();
                $newStatus = '';

                // Find the status corresponding to the new state
                foreach ($statusList as $key => $value) {
                    foreach ($value as $state) {
                        if ($state === $newState) {
                            $newStatus = $key;
                            break 2;
                        }
                    }
                }

                // Build flat associative array for updateAll
                $updateFields = [
                    'state' => $newState,
                    'status' => $newStatus
                ];

                if ($event_id) {
                    $updateFields['event_id'] = $event_id;
                }

                if ($given) {
                    $updateFields['given'] = new DateTime($given);
                }

                if ($close_reason) {
                    $updateFields['close_reason'] = $close_reason;
                }

                if (!$this->Recommendations->updateAll($updateFields, ['id IN' => $ids])) {
                    throw new \Exception('Failed to update recommendations');
                }

                if ($note) {
                    foreach ($ids as $id) {
                        $newNote = $this->Recommendations->Notes->newEmptyEntity();
                        $newNote->entity_id = $id;
                        $newNote->subject = 'Recommendation Bulk Updated';
                        $newNote->entity_type = 'Awards.Recommendations';
                        $newNote->body = $note;
                        $newNote->author_id = $user->id;

                        if (!$this->Recommendations->Notes->save($newNote)) {
                            throw new \Exception('Failed to save note');
                        }
                    }
                }

                $this->Recommendations->getConnection()->commit();
                if (!$this->request->getHeader('Turbo-Frame')) {
                    $this->Flash->success(__('The recommendations have been updated.'));
                }
            } catch (\Exception $e) {
                $this->Recommendations->getConnection()->rollback();
                Log::error('Error updating recommendations: ' . $e->getMessage());

                if (!$this->request->getHeader('Turbo-Frame')) {
                    $this->Flash->error(__('The recommendations could not be updated. Please, try again.'));
                }
            }
        }

        $currentPage = $this->request->getData('current_page');
        if ($currentPage) {
            return $this->redirect($currentPage);
        }

        return $this->redirect(['action' => 'table', $view, $status]);
    }

    /**
     * View method
     *
     * @param string|null $id Recommendation id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function view(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id, contain: ['Requesters', 'Members', 'Branches', 'Awards', 'Events', 'ScheduledEvent']);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'view');
            $recommendation->domain_id = $recommendation->award->domain_id;
            $this->set(compact('recommendation'));
            return null;
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add(): ?\Cake\Http\Response
    {
        try {
            $user = $this->request->getAttribute('identity');
            $recommendation = $this->Recommendations->newEmptyEntity();
            $this->Authorization->authorize($recommendation);

            if ($this->request->is('post')) {
                $recommendation = $this->Recommendations->patchEntity($recommendation, $this->request->getData());
                $recommendation->requester_id = $user->id;
                $recommendation->requester_sca_name = $user->sca_name;
                $recommendation->contact_email = $user->email_address;
                $recommendation->contact_number = $user->phone_number;

                $statuses = Recommendation::getStatuses();
                $recommendation->status = array_key_first($statuses);
                $recommendation->state = $statuses[$recommendation->status][0];
                $recommendation->state_date = DateTime::now();
                $recommendation->not_found = $this->request->getData('not_found') === 'on';

                if ($recommendation->specialty === 'No specialties available') {
                    $recommendation->specialty = null;
                }

                if ($recommendation->not_found) {
                    $recommendation->member_id = null;
                } else {
                    $this->Recommendations->getConnection()->begin();
                    try {
                        $member = $this->Recommendations->Members->get(
                            $recommendation->member_id,
                            select: ['branch_id', 'additional_info']
                        );

                        $recommendation->branch_id = $member->branch_id;

                        if (!empty($member->additional_info)) {
                            $addInfo = $member->additional_info;
                            if (isset($addInfo['CallIntoCourt'])) {
                                $recommendation->call_into_court = $addInfo['CallIntoCourt'];
                            }
                            if (isset($addInfo['CourtAvailability'])) {
                                $recommendation->court_availability = $addInfo['CourtAvailability'];
                            }
                            if (isset($addInfo['PersonToGiveNoticeTo'])) {
                                $recommendation->person_to_notify = $addInfo['PersonToGiveNoticeTo'];
                            }
                        }
                    } catch (\Exception $e) {
                        $this->Recommendations->getConnection()->rollback();
                        Log::error('Error loading member data: ' . $e->getMessage());
                        $this->Flash->error(__('Could not load member information. Please try again.'));
                    }
                }

                // Set default values for court preferences
                $recommendation->call_into_court = $recommendation->call_into_court ?? 'Not Set';
                $recommendation->court_availability = $recommendation->court_availability ?? 'Not Set';
                $recommendation->person_to_notify = $recommendation->person_to_notify ?? '';

                if ($this->Recommendations->save($recommendation)) {
                    $this->Recommendations->getConnection()->commit();
                    $this->Flash->success(__('The recommendation has been saved.'));

                    if ($user->checkCan('view', $recommendation)) {
                        return $this->redirect(['action' => 'view', $recommendation->id]);
                    }

                    return $this->redirect([
                        'controller' => 'members',
                        'plugin' => null,
                        'action' => 'view',
                        $user->id
                    ]);
                }
                $this->Recommendations->getConnection()->rollback();
                $this->Flash->error(__('The recommendation could not be saved. Please, try again.'));
            }

            // Get data for dropdowns
            $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
            $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();
            $branches = $this->Recommendations->Awards->Branches
                ->find('list', keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                })
                ->where(['can_have_members' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            $awards = $this->Recommendations->Awards->find('list', limit: 200)->all();

            $eventsData = $this->Recommendations->Events->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where([
                    'start_date >' => DateTime::now(),
                    'OR' => ['closed' => false, 'closed IS' => null]
                ])
                ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
                ->orderBy(['start_date' => 'ASC'])
                ->all();

            $events = [];
            foreach ($eventsData as $event) {
                $events[$event->id] = $event->name . ' in ' . $event->branch->name . ' on '
                    . $event->start_date->toDateString() . ' - ' . $event->end_date->toDateString();
            }

            $this->set(compact('recommendation', 'branches', 'awards', 'events', 'awardsDomains', 'awardsLevels'));
            return null;
        } catch (\Exception $e) {
            $this->Recommendations->getConnection()->rollback();
            Log::error('Error in add recommendation: ' . $e->getMessage());
            $this->Flash->error(__('An unexpected error occurred. Please try again.'));
            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * Submit a recommendation without authentication
     *
     * @return \Cake\Http\Response|null|void Redirects on successful submission, renders view otherwise.
     */
    public function submitRecommendation(): ?\Cake\Http\Response
    {
        $this->Authorization->skipAuthorization();
        $user = $this->request->getAttribute('identity');

        if ($user !== null) {
            return $this->redirect(['action' => 'add']);
        }

        $recommendation = $this->Recommendations->newEmptyEntity();

        if ($this->request->is(['post', 'put'])) {
            try {
                $this->Recommendations->getConnection()->begin();

                $recommendation = $this->Recommendations->patchEntity($recommendation, $this->request->getData());

                if ($recommendation->requester_id !== null) {
                    $requester = $this->Recommendations->Requesters->get(
                        $recommendation->requester_id,
                        fields: ['sca_name']
                    );
                    $recommendation->requester_sca_name = $requester->sca_name;
                }

                $statuses = Recommendation::getStatuses();
                $recommendation->status = array_key_first($statuses);
                $recommendation->state = $statuses[$recommendation->status][0];
                $recommendation->state_date = DateTime::now();

                if ($recommendation->specialty === 'No specialties available') {
                    $recommendation->specialty = null;
                }

                $recommendation->not_found = $this->request->getData('not_found') === 'on';

                if ($recommendation->not_found) {
                    $recommendation->member_id = null;
                } else {
                    try {
                        $member = $this->Recommendations->Members->get(
                            $recommendation->member_id,
                            select: ['branch_id', 'additional_info']
                        );

                        $recommendation->branch_id = $member->branch_id;

                        if (!empty($member->additional_info)) {
                            $addInfo = $member->additional_info;

                            if (isset($addInfo['CallIntoCourt'])) {
                                $recommendation->call_into_court = $addInfo['CallIntoCourt'];
                            }

                            if (isset($addInfo['CourtAvailability'])) {
                                $recommendation->court_availability = $addInfo['CourtAvailability'];
                            }

                            if (isset($addInfo['PersonToGiveNoticeTo'])) {
                                $recommendation->person_to_notify = $addInfo['PersonToGiveNoticeTo'];
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error loading member data: ' . $e->getMessage());
                    }
                }

                // Set default values for court preferences
                $recommendation->call_into_court = $recommendation->call_into_court ?? 'Not Set';
                $recommendation->court_availability = $recommendation->court_availability ?? 'Not Set';
                $recommendation->person_to_notify = $recommendation->person_to_notify ?? '';

                if ($this->Recommendations->save($recommendation)) {
                    $this->Recommendations->getConnection()->commit();
                    $this->Flash->success(__('The recommendation has been submitted.'));
                } else {
                    $this->Recommendations->getConnection()->rollback();
                    $this->Flash->error(__('The recommendation could not be submitted. Please, try again.'));
                }
            } catch (\Exception $e) {
                $this->Recommendations->getConnection()->rollback();
                Log::error('Error submitting recommendation: ' . $e->getMessage());
                $this->Flash->error(__('An error occurred while submitting the recommendation. Please try again.'));
            }
        }

        // Load data for the form
        $headerImage = StaticHelpers::getAppSetting('KMP.Login.Graphic');
        $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();

        $branches = $this->Recommendations->Awards->Branches
            ->find('list', keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members ? 'true' : 'false');
            })
            ->where(['can_have_members' => true])
            ->orderBy(['name' => 'ASC'])
            ->toArray();

        $awards = $this->Recommendations->Awards->find('list', limit: 200)->all();

        $eventsData = $this->Recommendations->Events->find()
            ->contain(['Branches' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->where(['start_date >' => DateTime::now()])
            ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
            ->orderBy(['start_date' => 'ASC'])
            ->all();

        $events = [];
        foreach ($eventsData as $event) {
            $events[$event->id] = $event->name . ' in ' . $event->branch->name . ' on '
                . $event->start_date->toDateString() . ' - ' . $event->end_date->toDateString();
        }

        $this->set(compact(
            'recommendation',
            'branches',
            'awards',
            'events',
            'awardsDomains',
            'awardsLevels',
            'headerImage'
        ));
        return null;
    }

    /**
     * Edit method
     *
     * @param string|null $id Recommendation id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function edit(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'edit');

            if ($this->request->is(['patch', 'post', 'put'])) {
                $beforeMemberId = $recommendation->member_id;
                $recommendation = $this->Recommendations->patchEntity($recommendation, $this->request->getData());

                if ($recommendation->specialty === 'No specialties available') {
                    $recommendation->specialty = null;
                }

                // Handle member related fields
                if ($recommendation->member_id == 0 || $recommendation->member_id == null) {
                    $recommendation->member_id = null;
                    $recommendation->call_into_court = null;
                    $recommendation->court_availability = null;
                    $recommendation->person_to_notify = null;
                } elseif ($recommendation->member_id != $beforeMemberId) {
                    // Reset member-related fields when member changes
                    $recommendation->call_into_court = null;
                    $recommendation->court_availability = null;
                    $recommendation->person_to_notify = null;

                    try {
                        $member = $this->Recommendations->Members->get(
                            $recommendation->member_id,
                            select: ['branch_id', 'additional_info']
                        );

                        $recommendation->branch_id = $member->branch_id;

                        if (!empty($member->additional_info)) {
                            $addInfo = $member->additional_info;
                            if (isset($addInfo['CallIntoCourt'])) {
                                $recommendation->call_into_court = $addInfo['CallIntoCourt'];
                            }
                            if (isset($addInfo['CourtAvailability'])) {
                                $recommendation->court_availability = $addInfo['CourtAvailability'];
                            }
                            if (isset($addInfo['PersonToGiveNoticeTo'])) {
                                $recommendation->person_to_notify = $addInfo['PersonToGiveNoticeTo'];
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error loading member data in edit: ' . $e->getMessage());
                    }
                }

                // Set default values for court preferences
                $recommendation->call_into_court = $recommendation->call_into_court ?? 'Not Set';
                $recommendation->court_availability = $recommendation->court_availability ?? 'Not Set';
                $recommendation->person_to_notify = $recommendation->person_to_notify ?? '';

                if ($this->request->getData('given') !== null) {
                    $recommendation->given = new DateTime($this->request->getData('given'));
                }

                // Begin transaction
                $this->Recommendations->getConnection()->begin();

                try {
                    if (!$this->Recommendations->save($recommendation)) {
                        throw new \Exception('Failed to save recommendation');
                    }

                    $note = $this->request->getData('note');
                    if ($note) {
                        $newNote = $this->Recommendations->Notes->newEmptyEntity();
                        $newNote->entity_id = $recommendation->id;
                        $newNote->subject = 'Recommendation Updated';
                        $newNote->entity_type = 'Awards.Recommendations';
                        $newNote->body = $note;
                        $newNote->author_id = $this->request->getAttribute('identity')->id;

                        if (!$this->Recommendations->Notes->save($newNote)) {
                            throw new \Exception('Failed to save note');
                        }
                    }

                    $this->Recommendations->getConnection()->commit();

                    if (!$this->request->getHeader('Turbo-Frame')) {
                        $this->Flash->success(__('The recommendation has been saved.'));
                    }
                } catch (\Exception $e) {
                    $this->Recommendations->getConnection()->rollback();
                    Log::error('Error saving recommendation: ' . $e->getMessage());

                    if (!$this->request->getHeader('Turbo-Frame')) {
                        $this->Flash->error(__('The recommendation could not be saved. Please, try again.'));
                    }
                }
            }

            if ($this->request->getData('current_page')) {
                return $this->redirect($this->request->getData('current_page'));
            }

            return $this->redirect(['action' => 'view', $id]);
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        } catch (\Exception $e) {
            Log::error('Error in edit recommendation: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while editing the recommendation.'));
            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * Update a recommendation in Kanban board interface
     *
     * @param string|null $id Recommendation id.
     * @return \Cake\Http\Response JSON response
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function kanbanUpdate(?string $id = null): \Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'edit');
            $message = 'failed';

            if ($this->request->is(['patch', 'post', 'put'])) {
                $recommendation->state = $this->request->getData('newCol');
                $placeBefore = $this->request->getData('placeBefore');
                $placeAfter = $this->request->getData('placeAfter');

                $placeAfter = $placeAfter ?? -1;
                $placeBefore = $placeBefore ?? -1;

                $recommendation->state_date = DateTime::now();
                $this->Recommendations->getConnection()->begin();

                try {
                    $failed = false;

                    if (!$this->Recommendations->save($recommendation)) {
                        throw new \Exception('Failed to save recommendation state');
                    }

                    if ($placeBefore != -1) {
                        if (!$this->Recommendations->moveBefore($id, $placeBefore)) {
                            throw new \Exception('Failed to move recommendation before target');
                        }
                    }

                    if ($placeAfter != -1) {
                        if (!$this->Recommendations->moveAfter($id, $placeAfter)) {
                            throw new \Exception('Failed to move recommendation after target');
                        }
                    }

                    $this->Recommendations->getConnection()->commit();
                    $message = 'success';
                } catch (\Exception $e) {
                    $this->Recommendations->getConnection()->rollback();
                    Log::error('Error updating kanban: ' . $e->getMessage());
                    $message = 'failed';
                }
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode($message));
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            Log::error('Kanban update failed - recommendation not found: ' . $id);
            return $this->response
                ->withType('application/json')
                ->withStatus(404)
                ->withStringBody(json_encode('not_found'));
        }
    }

    /**
     * Delete method
     *
     * @param string|null $id Recommendation id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Http\Exception\NotFoundException When record not found.
     */
    public function delete(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $this->request->allowMethod(['post', 'delete']);

            $recommendation = $this->Recommendations->get($id);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation);

            $this->Recommendations->getConnection()->begin();
            try {
                if (!$this->Recommendations->delete($recommendation)) {
                    throw new \Exception('Failed to delete recommendation');
                }

                $this->Recommendations->getConnection()->commit();
                $this->Flash->success(__('The recommendation has been deleted.'));
            } catch (\Exception $e) {
                $this->Recommendations->getConnection()->rollback();
                Log::error('Error deleting recommendation: ' . $e->getMessage());
                $this->Flash->error(__('The recommendation could not be deleted. Please, try again.'));
            }

            return $this->redirect(['action' => 'index']);
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    #region JSON calls
    /**
     * Turbo-compatible edit form for recommendations
     *
     * @param string|null $id Recommendation id
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Http\Exception\NotFoundException When record not found
     */
    public function turboEditForm(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id, contain: [
                'Requesters',
                'Members',
                'Branches',
                'Awards',
                'Events',
                'ScheduledEvent',
                'Awards.Domains'
            ]);

            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'view');
            $recommendation->domain_id = $recommendation->award->domain_id;

            // Get data for form dropdowns and options
            $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
            $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();

            $branches = $this->Recommendations->Awards->Branches
                ->find('list', keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                })
                ->where(['can_have_members' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            $awards = $this->Recommendations->Awards->find('all', limit: 200)
                ->select(['id', 'name', 'specialties'])
                ->where(['domain_id' => $recommendation->domain_id])
                ->all();

            $eventsData = $this->Recommendations->Events->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where(['OR' => ['closed' => false, 'closed IS' => null]])
                ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
                ->orderBy(['start_date' => 'ASC'])
                ->all();

            // Format status list for dropdown
            $statusList = Recommendation::getStatuses();
            foreach ($statusList as $key => $value) {
                $states = $value;
                $statusList[$key] = [];
                foreach ($states as $state) {
                    $statusList[$key][$state] = $state;
                }
            }

            // Format event list for dropdown
            $eventList = [];
            foreach ($eventsData as $event) {
                $eventList[$event->id] = $event->name . ' in ' . $event->branch->name . ' on '
                    . $event->start_date->toDateString() . ' - ' . $event->end_date->toDateString();
            }

            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');
            $this->set(compact(
                'rules',
                'recommendation',
                'branches',
                'awards',
                'eventList',
                'awardsDomains',
                'awardsLevels',
                'statusList'
            ));
            return null;
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    /**
     * Turbo-compatible quick edit form for recommendations
     *
     * @param string|null $id Recommendation id
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Http\Exception\NotFoundException When record not found
     */
    public function turboQuickEditForm(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id, contain: [
                'Requesters',
                'Members',
                'Branches',
                'Awards',
                'Events',
                'ScheduledEvent',
                'Awards.Domains'
            ]);

            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'view');
            $recommendation->domain_id = $recommendation->award->domain_id;

            // Get data for form dropdowns and options
            $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
            $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();

            $branches = $this->Recommendations->Awards->Branches
                ->find('list', keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                })
                ->where(['can_have_members' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            $awards = $this->Recommendations->Awards->find('all', limit: 200)
                ->select(['id', 'name', 'specialties'])
                ->where(['domain_id' => $recommendation->domain_id])
                ->all();

            $eventsData = $this->Recommendations->Events->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where(['OR' => ['closed' => false, 'closed IS' => null]])
                ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
                ->orderBy(['start_date' => 'ASC'])
                ->all();

            // Format status list for dropdown
            $statusList = Recommendation::getStatuses();
            foreach ($statusList as $key => $value) {
                $states = $value;
                $statusList[$key] = [];
                foreach ($states as $state) {
                    $statusList[$key][$state] = $state;
                }
            }

            // Format event list for dropdown
            $eventList = [];
            foreach ($eventsData as $event) {
                $eventList[$event->id] = $event->name . ' in ' . $event->branch->name . ' on '
                    . $event->start_date->toDateString() . ' - ' . $event->end_date->toDateString();
            }

            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');
            $this->set(compact(
                'rules',
                'recommendation',
                'branches',
                'awards',
                'eventList',
                'awardsDomains',
                'awardsLevels',
                'statusList'
            ));
            return null;
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    /**
     * Turbo-compatible bulk edit form for recommendations
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function turboBulkEditForm(): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->newEmptyEntity();
            $this->Authorization->authorize($recommendation, 'view');

            // Get branch list for dropdown
            $branches = $this->Recommendations->Awards->Branches
                ->find('list', keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                })
                ->where(['can_have_members' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            // Get events data
            $eventsData = $this->Recommendations->Events->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where(['OR' => ['closed' => false, 'closed IS' => null]])
                ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
                ->orderBy(['start_date' => 'ASC'])
                ->all();

            // Format status list for dropdown
            $statusList = Recommendation::getStatuses();
            foreach ($statusList as $key => $value) {
                $states = $value;
                $statusList[$key] = [];
                foreach ($states as $state) {
                    $statusList[$key][$state] = $state;
                }
            }

            // Format event list for dropdown
            $eventList = [];
            foreach ($eventsData as $event) {
                $eventList[$event->id] = $event->name . ' in ' . $event->branch->name . ' on '
                    . $event->start_date->toDateString() . ' - ' . $event->end_date->toDateString();
            }

            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');
            $this->set(compact('rules', 'branches', 'eventList', 'statusList'));
            return null;
        } catch (\Exception $e) {
            Log::error('Error in bulk edit form: ' . $e->getMessage());
            throw new \Cake\Http\Exception\InternalErrorException(__('An error occurred while preparing the bulk edit form.'));
        }
    }
    #endregion

    /**
     * Process and display recommendation data in tabular format
     *
     * @param array $filterArray Filter criteria for querying recommendations
     * @param string $status Status filter to apply
     * @param string $view Current view configuration name
     * @return void
     */
    protected function runTable(array $filterArray, string $status, string $view = "Default"): void
    {
        try {
            // Build and execute the recommendation query with filters
            $recommendations = $this->getRecommendationQuery($filterArray);

            // Process status lists for display
            $fullStatusList = Recommendation::getStatuses();
            if ($status == "All") {
                $statusList = Recommendation::getStatuses();
            } else {
                $statusList[$status] = Recommendation::getStatuses()[$status];
            }

            // Format status lists for display
            foreach ($fullStatusList as $key => $value) {
                $fullStatusList[$key] = array_combine($value, $value);
            }

            foreach ($statusList as $key => $value) {
                $statusList[$key] = array_combine($value, $value);
            }

            // Apply visibility filters based on user permissions
            $user = $this->request->getAttribute("identity");
            $blank = $this->Recommendations->newEmptyEntity();

            if (!$user->checkCan("ViewHidden", $blank)) {
                $hiddenStates = StaticHelpers::getAppSetting("Awards.RecommendationStatesRequireCanViewHidden");
                $recommendations->where(["Recommendations.status not IN" => $hiddenStates]);

                // Filter out hidden states from status lists
                foreach ($statusList as $key => $value) {
                    $tmpStatus = $statusList[$key];
                    foreach ($hiddenStates as $hiddenState) {
                        try {
                            unset($tmpStatus[$hiddenState]);
                        } catch (\Exception $e) {
                            // Silently continue if state doesn't exist
                        }
                    }

                    if (empty($tmpStatus)) {
                        unset($statusList[$key]);
                    } else {
                        $statusList[$key] = $tmpStatus;
                    }
                }
            }

            // Get awards, domains and branches for filters/display
            $awards = $this->Recommendations->Awards->find(
                'list',
                limit: 200,
                keyField: 'id',
                valueField: 'abbreviation'
            );
            $awards = $this->Authorization->applyScope($awards, 'index')->all();

            $domains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();

            $branches = $this->Recommendations->Branches
                ->find("list", keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? "true" : "false");
                })
                ->where(["can_have_members" => true])
                ->orderBy(["name" => "ASC"])
                ->toArray();

            // Configure pagination
            $this->paginate = [
                'sortableFields' => [
                    'Branches.name',
                    'Awards.name',
                    'Domains.name',
                    'member_sca_name',
                    'created',
                    'state',
                    'Events.name',
                    'call_into_court',
                    'court_availability',
                    'requester_sca_name',
                    'contact_email',
                    'contact_phone',
                    'state_date',
                    'AssignedEvent.name'
                ],
            ];

            $action = $view;
            $recommendations = $this->paginate($recommendations);

            // Get recommendation state rules and events data
            $rules = StaticHelpers::getAppSetting("Awards.RecommendationStateRules");

            $eventsData = $this->Recommendations->Events->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where(['OR' => ['closed' => false, 'closed IS' => null]])
                ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
                ->orderBy(['start_date' => 'ASC'])
                ->all();

            // Format event list for display
            $eventList = [];
            foreach ($eventsData as $event) {
                $eventList[$event->id] = $event->name . " in " . $event->branch->name . " on "
                    . $event->start_date->toDateString() . " - " . $event->end_date->toDateString();
            }

            // Set variables for the view
            $this->set(compact(
                'recommendations',
                'statusList',
                'awards',
                'domains',
                'branches',
                'view',
                'status',
                'action',
                'fullStatusList',
                'rules',
                'eventList'
            ));
        } catch (\Exception $e) {
            Log::error('Error in runTable: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while loading the recommendations table.'));
        }
    }

    /**
     * Process and display recommendation data in kanban board format
     *
     * @param string $view Current view configuration name
     * @param array $pageConfig Configuration settings for current view
     * @param \Awards\Model\Entity\Recommendation $emptyRecommendation Empty entity for authorization checks
     * @return void
     */
    protected function runBoard(string $view, array $pageConfig, \Awards\Model\Entity\Recommendation $emptyRecommendation): void
    {
        try {
            // Initialize states from board configuration
            $statesList = $pageConfig['board']['states'];
            $states = [];
            foreach ($statesList as $state) {
                $states[$state] = [];
            }

            $statesToLoad = $pageConfig['board']['states'];
            $hiddenByDefault = $pageConfig['board']['hiddenByDefault'];
            $hiddenByDefaultStates = [];

            // Process hidden states configuration
            if (is_array($hiddenByDefault) && !empty($hiddenByDefault)) {
                foreach ($hiddenByDefault["states"] as $state) {
                    $hiddenByDefaultStates[] = $state;
                    $statesToLoad = array_diff($statesToLoad, [$state]);
                }
            }

            $user = $this->request->getAttribute('identity');

            // Apply permissions to hidden states
            if (!$user->checkCan('ViewHidden', $emptyRecommendation)) {
                $hiddenStates = StaticHelpers::getAppSetting('Awards.RecommendationStatesRequireCanViewHidden');

                // Filter out any hidden states the user doesn't have permission to view
                foreach ($hiddenStates as $state) {
                    if (in_array($state, $hiddenByDefaultStates)) {
                        $hiddenByDefaultStates = array_diff($hiddenByDefaultStates, [$state]);
                    }
                }
            }

            // Build base query for recommendations
            $recommendations = $this->Recommendations->find()
                ->contain(['Requesters', 'Members', 'Awards'])
                ->orderBy(['Recommendations.state', 'stack_rank'])
                ->select([
                    'Recommendations.id',
                    'Recommendations.member_sca_name',
                    'Recommendations.reason',
                    'Recommendations.stack_rank',
                    'Recommendations.state',
                    'Recommendations.status',
                    'Recommendations.modified',
                    'Recommendations.specialty',
                    'Members.sca_name',
                    'Awards.abbreviation',
                    'ModifiedByMembers.sca_name'
                ])
                ->join([
                    'table' => 'members',
                    'alias' => 'ModifiedByMembers',
                    'type' => 'LEFT',
                    'conditions' => 'Recommendations.modified_by = ModifiedByMembers.id'
                ]);

            // Apply authorization scope
            $recommendations = $this->Authorization->applyScope($recommendations, 'index');

            // Apply hidden states filter based on permissions
            if (!$user->checkCan('ViewHidden', $emptyRecommendation)) {
                $hiddenStates = StaticHelpers::getAppSetting('Awards.RecommendationStatesRequireCanViewHidden');
                $recommendations = $recommendations->where(['Recommendations.state NOT IN' => $hiddenStates]);
            }

            // Process show/hide filter from query parameters
            $showHidden = $this->request->getQuery('showHidden') === 'true';
            $range = $hiddenByDefault['lookback'] ?? 30; // Default to 30 days if not specified

            // Build comma-separated list of hidden states for view
            $hiddenStatesStr = '';
            if (is_array($hiddenByDefaultStates) && !empty($hiddenByDefaultStates)) {
                $hiddenStatesStr = implode(',', $hiddenByDefaultStates);

                // Apply filter based on showHidden parameter
                if ($showHidden) {
                    $cutoffDate = DateTime::now()->subDays($range);
                    $recommendations = $recommendations->where([
                        'OR' => [
                            'Recommendations.state IN' => $statesToLoad,
                            'AND' => [
                                'Recommendations.state IN' => $hiddenByDefaultStates,
                                'Recommendations.state_date >' => $cutoffDate
                            ]
                        ]
                    ]);
                } else {
                    $recommendations = $recommendations->where(['Recommendations.state IN' => $statesToLoad]);
                }
            } else {
                $recommendations = $recommendations->where(['Recommendations.state IN' => $statesToLoad]);
            }

            // Execute the query and get all recommendations
            $recommendations = $recommendations->all();

            // Group recommendations by state for kanban board display
            foreach ($recommendations as $recommendation) {
                if (!isset($states[$recommendation->state])) {
                    $states[$recommendation->state] = [];
                }
                $states[$recommendation->state][] = $recommendation;
            }

            // Get recommendation state rules for UI
            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');

            // Set variables for the view
            $this->set(compact(
                'recommendations',
                'states',
                'view',
                'showHidden',
                'range',
                'hiddenStatesStr',
                'rules'
            ));
        } catch (\Exception $e) {
            Log::error('Error in runBoard: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while loading the board view.'));
        }
    }

    /**
     * Generate a CSV export of recommendations based on filter criteria
     *
     * @param \App\Services\CsvExportService $csvExportService Service for generating CSV exports
     * @param array $filterArray Filter criteria for querying recommendations
     * @param array $columns Configuration of which columns to include in export
     * @return \Cake\Http\Response CSV download response
     */
    protected function runExport(CsvExportService $csvExportService, array $filterArray, array $columns): \Cake\Http\Response
    {
        try {
            // Get filtered recommendations
            $recommendations = $this->getRecommendationQuery($filterArray);
            $recommendations = $recommendations->all();

            // Build header row from selected columns
            $header = [];
            $data = [];
            foreach ($columns as $key => $use) {
                if ($use) {
                    $header[] = $key;
                }
            }

            // Process each recommendation into a row based on selected columns
            foreach ($recommendations as $recommendation) {
                $row = [];
                foreach ($header as $key) {
                    $row[$key] = $this->formatExportColumn($recommendation, $key);
                }
                $data[] = $row;
            }

            // Generate and return CSV response
            return $csvExportService->outputCsv(
                $data,
                filename: "recommendations.csv",
                headers: $header
            );
        } catch (\Exception $e) {
            Log::error('Error generating CSV export: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while generating the export.'));
            throw $e; // Re-throw to be caught by the parent method
        }
    }

    /**
     * Format a single column value for CSV export
     * 
     * @param \Awards\Model\Entity\Recommendation $recommendation The recommendation entity
     * @param string $columnName The name of the column to format
     * @return string The formatted value
     */
    private function formatExportColumn(\Awards\Model\Entity\Recommendation $recommendation, string $columnName): string
    {
        switch ($columnName) {
            case "Submitted":
                return (string)$recommendation->created;

            case "For":
                return $recommendation->member_sca_name;

            case "For Herald":
                return $recommendation->member
                    ? $recommendation->member->name_for_herald
                    : $recommendation->member_sca_name;

            case "Title":
                return $recommendation->member
                    ? (string)$recommendation->member->title
                    : "";

            case "Pronouns":
                return $recommendation->member
                    ? (string)$recommendation->member->pronouns
                    : "";

            case "Pronunciation":
                return $recommendation->member
                    ? (string)$recommendation->member->pronunciation
                    : "";

            case "OP":
                $links = "";
                if ($recommendation->member) {
                    $member = $recommendation->member;
                    $externalLinks = $member->publicLinks();
                    if ($externalLinks) {
                        foreach ($externalLinks as $name => $link) {
                            $links .= "| $name : $link ";
                        }
                        $links .= "|";
                    }
                }
                return $links;

            case "Branch":
                return $recommendation->branch->name;

            case "Call Into Court":
                return (string)$recommendation->call_into_court;

            case "Court Avail":
                return (string)$recommendation->court_availability;

            case "Person to Notify":
                return (string)$recommendation->person_to_notify;

            case "Submitted By":
                return $recommendation->requester_sca_name;

            case "Contact Email":
                return (string)$recommendation->contact_email;

            case "Contact Phone":
                return (string)$recommendation->contact_phone;

            case "Domain":
                return $recommendation->award->domain->name;

            case "Award":
                $awardText = $recommendation->award->abbreviation;
                if ($recommendation->specialty) {
                    $awardText .= " (" . $recommendation->specialty . ")";
                }
                return $awardText;

            case "Reason":
                return (string)$recommendation->reason;

            case "Events":
                $events = "";
                foreach ($recommendation->events as $event) {
                    $startDate = $event->start_date->toDateString();
                    $endDate = $event->end_date->toDateString();
                    $events .= "$event->name : $startDate - $endDate\n\n";
                }
                return $events;

            case "Notes":
                $notes = "";
                foreach ($recommendation->notes as $note) {
                    $createDate = $note->created->toDateTimeString();
                    $notes .= "$createDate : $note->body\n\n";
                }
                return $notes;

            case "Status":
                return $recommendation->status;

            case "Event":
                return $recommendation->assigned_event
                    ? $recommendation->assigned_event->name
                    : "";

            case "State":
                return $recommendation->state;

            case "Close Reason":
                return (string)$recommendation->close_reason;

            case "State Date":
                return $recommendation->state_date->toDateString();

            case "Given Date":
                return $recommendation->given
                    ? $recommendation->given->toDateString()
                    : "";

            default:
                return "";
        }
    }

    /**
     * Build a query for recommendations with optional filtering
     *
     * @param array|null $filterArray Optional array of conditions to filter recommendations
     * @return \Cake\Datasource\QueryInterface The recommendation query with containments and filters applied
     */
    protected function getRecommendationQuery(?array $filterArray = null): \Cake\Datasource\QueryInterface
    {

        // Build base query with containments
        $recommendations = $this->Recommendations->find()
            ->select([
                'Recommendations.id',
                'Recommendations.stack_rank',
                'Recommendations.requester_id',
                'Recommendations.member_id',
                'Recommendations.branch_id',
                'Recommendations.award_id',
                'Recommendations.specialty',
                'Recommendations.requester_sca_name',
                'Recommendations.member_sca_name',
                'Recommendations.contact_number',
                'Recommendations.contact_email',
                'Recommendations.reason',
                'Recommendations.call_into_court',
                'Recommendations.court_availability',
                'Recommendations.status',
                'Recommendations.state_date',
                'Recommendations.event_id',
                'Recommendations.given',
                'Recommendations.modified',
                'Recommendations.created',
                'Recommendations.created_by',
                'Recommendations.modified_by',
                'Recommendations.deleted',
                'Recommendations.person_to_notify',
                'Recommendations.no_action_reason',
                'Recommendations.close_reason',
                'Recommendations.state',
                'Branches.id',
                'Branches.name',
                'Requesters.id',
                'Requesters.sca_name',
                'Members.id',
                'Members.sca_name',
                'Members.title',
                'Members.pronouns',
                'Members.pronunciation',
                'AssignedEvent.id',
                'AssignedEvent.name',
                'Awards.id',
                'Awards.abbreviation',
                'Awards.branch_id',
                'AwardsBranches.type',
            ])
            // First, establish the Awards join using leftJoinWith
            ->leftJoinWith('Awards', function ($q) {
                return $q->select(['id', 'abbreviation', 'branch_id']);
            })
            ->join([
                'AwardsForBranches' => [
                    'table' => 'awards_awards',
                    'type' => 'LEFT',
                    'conditions' => 'AwardsForBranches.id = Recommendations.award_id AND AwardsForBranches.deleted IS NULL'
                ]
            ])
            // Then add the manual join for AwardsBranches
            ->join([
                'AwardsBranches' => [
                    'table' => 'branches',
                    'type' => 'LEFT',
                    'conditions' => 'AwardsBranches.id = AwardsForBranches.branch_id AND AwardsBranches.deleted IS NULL'
                ]
            ])
            ->contain([
                'Requesters' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name', 'title', 'pronouns', 'pronunciation']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Awards.Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Events' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                },
                'Notes' => function ($q) {
                    return $q->select(['id', 'entity_id', 'subject', 'body', 'created']);
                },
                'Notes.Authors' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'AssignedEvent' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ]);

        // Apply filter array if provided
        if ($filterArray) {
            $recommendations->where($filterArray);
        }

        // Apply additional filters from query parameters
        if ($this->request->getQuery('award_id')) {
            $recommendations->where(['award_id' => $this->request->getQuery('award_id')]);
        }

        if ($this->request->getQuery('branch_id')) {
            $recommendations->where(['Recommendations.branch_id' => $this->request->getQuery('branch_id')]);
        }

        if ($this->request->getQuery('for')) {
            $recommendations->where(['member_sca_name LIKE' => '%' . $this->request->getQuery('for') . '%']);
        }

        if ($this->request->getQuery('call_into_court')) {
            $recommendations->where(['call_into_court' => $this->request->getQuery('call_into_court')]);
        }

        if ($this->request->getQuery('court_avail')) {
            $recommendations->where(['court_availability' => $this->request->getQuery('court_avail')]);
        }

        if ($this->request->getQuery('requester_sca_name')) {
            $recommendations->where(['requester_sca_name' => $this->request->getQuery('requester_sca_name')]);
        }

        if ($this->request->getQuery('domain_id')) {
            $recommendations->where(['Awards.domain_id' => $this->request->getQuery('domain_id')]);
        }

        if ($this->request->getQuery('state')) {
            $recommendations->where(['Recommendations.state' => $this->request->getQuery('state')]);
        }

        if ($this->request->getQuery('branch_type')) {
            $recommendations->where(['AwardsBranches.type like ' => '%' . $this->request->getQuery('branch_type') . '%']);
        }

        // Apply authorization scope policy
        return $this->Authorization->applyScope($recommendations, 'index');
    }

    /**
     * Process filter configuration into a query condition array
     * 
     * This method transforms a filter configuration array into query conditions,
     * handling special syntax for dynamic query parameter substitution.
     * Values wrapped in "-" delimiters are treated as request query parameter names.
     *
     * @param array $filter The filter configuration array
     * @return array The processed filter array ready for use in queries
     */
    protected function processFilter(array $filter): array
    {
        $filterArray = [];

        foreach ($filter as $key => $value) {
            // Convert "->" notation to "." for proper SQL path expressions
            $fixedKey = str_replace("->", ".", $key);

            // Check if value is a request parameter reference (wrapped in "-" delimiters)
            if (
                is_string($value) &&
                strlen($value) >= 2 &&
                substr($value, 0, 1) === "-" &&
                substr($value, -1) === "-"
            ) {

                // Extract parameter name and get its value from the request
                $paramName = substr($value, 1, -1);
                $paramValue = $this->request->getQuery($paramName);

                // Only add the condition if the parameter has a value
                if ($paramValue !== null && $paramValue !== '') {
                    $filterArray[$fixedKey] = $paramValue;
                }
            } else {
                $filterArray[$fixedKey] = $value;
            }
        }

        return $filterArray;
    }
}