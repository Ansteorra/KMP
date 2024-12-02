<?php

declare(strict_types=1);

namespace Awards\Controller;

use Awards\Controller\AppController;
use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;
use App\KMP\StaticHelpers;
use Authorization\Exception\ForbiddenException;

/**
 * Recommendations Controller
 *
 * @property \Awards\Model\Table\RecommendationsTable $Recommendations
 */
class RecommendationsController extends AppController
{

    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated([
            "submitRecommendation"
        ]);
    }

    public function index()
    {
        $view = $this->request->getQuery("view");
        if ($view == null) {
            $view = "Index";
        }
        $status = $this->request->getQuery("status");
        if ($status == null) {
            $status = "All";
        }
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $queryArgs = $this->request->getQuery();
        $user = $this->request->getAttribute("identity");
        $user->authorizeWithArgs($emptyRecommendation, "index", $view, $status, $queryArgs);


        if ($view || $view != "Index") {
            try {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig." . $view);
            } catch (\Exception $e) {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
            }
        } else {
            $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
        }


        if ($pageConfig['board']['use']) {
            $pageConfig['board']['use'] = $user->checkCan("UseBoard", $emptyRecommendation, $status, $view);
        }

        $this->set(compact('view', 'status', 'pageConfig'));
    }

    public function table($view = null, $status = null)
    {
        if ($view == null) {
            $view = "Default";
        }

        if ($status == null) {
            $status = "All";
        }

        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        if ($view && $view != "Default") {
            try {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig." . $view);
            } catch (\Exception $e) {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
            }
            $filter = $pageConfig['table']['filter'];
        } else {
            $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
            $filter = $pageConfig['table']['filter'];
        }
        $permission = "index";
        if ($pageConfig['table']['optionalPermission']) {
            $permission = $pageConfig['table']['optionalPermission'];
        }
        $queryArgs = $this->request->getQuery();
        $user = $this->request->getAttribute("identity");
        $user->authorizeWithArgs($emptyRecommendation, $permission, $view, $status, $queryArgs);



        $filter = $this->processFilter($filter);

        $enableExport = $pageConfig['table']['enableExport'];
        $this->set(compact('pageConfig', 'enableExport'));
        $this->runTable($filter, $status, $view);
    }

    public function board($view = null, $status = null)
    {

        if ($view == null) {
            $view = "Default";
        }
        if ($status == null) {
            $status = "All";
        }

        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $queryArgs = $this->request->getQuery();
        $user = $this->request->getAttribute("identity");
        $user->authorizeWithArgs($emptyRecommendation, "index", $view, $status, $queryArgs);

        if ($view && $view != "Index") {
            try {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig." . $view);
            } catch (\Exception $e) {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
            }
        } else {
            $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
        }
        if (!$pageConfig['board']['use']) {
            return;
        }
        $this->set(compact('pageConfig'));



        $this->runBoard($view, $pageConfig, $emptyRecommendation);
    }

    public function export($view = null, $status = null)
    {
        if ($view == null) {
            $view = "Default";
        }


        if ($status == null) {
            $status = "All";
        }

        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        if ($view && $view != "Default") {
            try {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig." . $view);
            } catch (\Exception $e) {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
            }
            $filter = $pageConfig['table']['filter'];
        } else {
            $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
            $filter = $pageConfig['table']['filter'];
        }

        if (!$pageConfig['table']['enableExport']) {
            throw new ForbiddenException();
        }

        $permission = "index";
        if ($pageConfig['table']['optionalPermission']) {
            $permission = $pageConfig['table']['optionalPermission'];
        }
        $queryArgs = $this->request->getQuery();
        $user = $this->request->getAttribute("identity");



        $user->authorizeWithArgs($emptyRecommendation, $permission, $view, $status, $queryArgs);



        $filter = $this->processFilter($filter);

        $columns = $pageConfig['table']['export'];

        $this->runExport($filter, $columns);
    }

    /**
     * View method
     *
     * @param string|null $id Recommendation id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $recommendation = $this->Recommendations->get($id, contain: ['Requesters', 'Members', 'Branches', 'Awards', 'Events', 'ScheduledEvent']);
        if (!$recommendation) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($recommendation, 'view');
        $recommendation->domain_id = $recommendation->award->domain_id;
        $this->set(compact('recommendation'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $user = $this->request->getAttribute("identity");
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
            $recommendation["not_found"] = $this->request->getData("not_found") == "on";
            if ($recommendation->specialty == "No specialties available") {
                $recommendation->specialty = null;
            }
            $setCourtPrefs = false;
            if ($recommendation->not_found) {
                $recommendation->member_id = null;
            } else {
                $member = $this->Recommendations->Members->get($recommendation->member_id, select: ["branch_id", "additional_info"]);
                $recommendation->branch_id = $this->Recommendations->Members->get($recommendation->member_id, select: ["branch_id"])->branch_id;
                if ($member->additional_info != null && $member->additional_info != "") {
                    $addInfo = $member->additional_info;
                    if (isset($addInfo["CallIntoCourt"])) {
                        $recommendation->call_into_court = $addInfo["CallIntoCourt"];
                    }
                    if (isset($addInfo["CourtAvailability"])) {
                        $recommendation->court_availability = $addInfo["CourtAvailability"];
                    }
                    if (isset($addInfo["PersonToGiveNoticeTo"])) {
                        $recommendation->person_to_notify = $addInfo["PersonToGiveNoticeTo"];
                    }
                }
            }
            if (!isset($recommendation->call_into_court)) {
                $recommendation->call_into_court = "Not Set";
            }
            if (!isset($recommendation->court_availability)) {
                $recommendation->court_availability = "Not Set";
            }
            if (!isset($recommendation->person_to_notify)) {
                $recommendation->person_to_notify = "";
            }
            if ($this->Recommendations->save($recommendation)) {
                $this->Flash->success(__('The recommendation has been saved.'));
                if ($user->checkCan("view", $recommendation)) {
                    return $this->redirect(['action' => 'view', $recommendation->id]);
                }
                return $this->redirect(['controller' => 'members', 'plugin' => null, 'action' => 'view', $this->request->getAttribute("identity")->id]);
            }
            $this->Flash->error(__('The recommendation could not be saved. Please, try again.'));
        }
        $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();
        $branches = $this->Recommendations->Awards->Branches
            ->find("list", keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? "true" : "false");
            })
            ->where(["can_have_members" => true])
            ->orderBy(["name" => "ASC"])->toArray();
        $awards = $this->Recommendations->Awards->find('list', limit: 200)->all();
        $eventsData = $this->Recommendations->Events->find()
            ->contain(['Branches' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->where(["start_date >" => DateTime::now(), 'OR' => ['closed' => false, 'closed IS' => null]])
            ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
            ->all();
        $events = [];
        foreach ($eventsData as $event) {
            $events[$event->id] = $event->name . " in " . $event->branch->name . " on " . $event->start_date->toDateString() . " - " . $event->end_date->toDateString();
        }
        $this->set(compact('recommendation', 'branches', 'awards', 'events', 'awardsDomains', 'awardsLevels'));
    }


    public function submitRecommendation()
    {
        $this->Authorization->skipAuthorization();
        $user = $this->request->getAttribute("identity");
        if ($user != null) {
            $this->redirect(['action' => 'add']);
        }

        $recommendation = $this->Recommendations->newEmptyEntity();
        if ($this->request->is(['post', 'put'])) {
            $recommendation = $this->Recommendations->patchEntity($recommendation, $this->request->getData());
            if ($recommendation->requester_id != null) {
                $recommendation->requester_sca_name = $this->Recommendations->Requesters->get($recommendation->requester_id, fields: ['sca_name'])->sca_name;
            }
            $statuses = Recommendation::getStatuses();
            $recommendation->status = array_key_first($statuses);
            $recommendation->state = $statuses[$recommendation->status][0];
            $recommendation->state_date = DateTime::now();
            if ($recommendation->specialty == "No specialties available") {
                $recommendation->specialty = null;
            }
            $recommendation["not_found"] = $this->request->getData("not_found") == "on";
            if ($recommendation->not_found) {
                $recommendation->member_id = null;
            } else {
                $member = $this->Recommendations->Members->get($recommendation->member_id, select: ["branch_id", "additional_info"]);
                $recommendation->branch_id = $this->Recommendations->Members->get($recommendation->member_id, select: ["branch_id"])->branch_id;
                if ($member->additional_info != null && $member->additional_info != "") {
                    $addInfo = $member->additional_info;
                    if (isset($addInfo["CallIntoCourt"])) {
                        $recommendation->call_into_court = $addInfo["CallIntoCourt"];
                    }
                    if (isset($addInfo["CourtAvailability"])) {
                        $recommendation->court_availability = $addInfo["CourtAvailability"];
                    }
                    if (isset($addInfo["PersonToGiveNoticeTo"])) {
                        $recommendation->person_to_notify = $addInfo["PersonToGiveNoticeTo"];
                    }
                }
            }
            if (!isset($recommendation->call_into_court)) {
                $recommendation->call_into_court = "Not Set";
            }
            if (!isset($recommendation->court_availability)) {
                $recommendation->court_availability = "Not Set";
            }
            if (!isset($recommendation->person_to_notify)) {
                $recommendation->person_to_notify = "";
            }
            if ($this->Recommendations->save($recommendation)) {
                $this->Flash->success(__('The recommendation has been submitted.'));
            } else {
                $this->Flash->error(__('The recommendation could not be submitted. Please, try again.'));
            }
        }
        $headerImage = StaticHelpers::getAppSetting(
            "KMP.Login.Graphic",
        );
        $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();
        $branches = $this->Recommendations->Awards->Branches
            ->find("list", keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? "true" : "false");
            })
            ->where(["can_have_members" => true])
            ->orderBy(["name" => "ASC"])->toArray();
        $awards = $this->Recommendations->Awards->find('list', limit: 200)->all();
        $eventsData = $this->Recommendations->Events->find()
            ->contain(['Branches' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->where(["start_date >" => DateTime::now()])
            ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
            ->all();
        $events = [];
        foreach ($eventsData as $event) {
            $events[$event->id] = $event->name . " in " . $event->branch->name . " on " . $event->start_date->toDateString() . " - " . $event->end_date->toDateString();
        }
        $this->set(compact('recommendation', 'branches', 'awards', 'events', 'awardsDomains', 'awardsLevels', 'headerImage'));
    }


    /**
     * Edit method
     *
     * @param string|null $id Recommendation id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $recommendation = $this->Recommendations->get($id);
        if (!$recommendation) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($recommendation, 'edit');
        if ($this->request->is(['patch', 'post', 'put'])) {
            $beforeMemberId = $recommendation->member_id;
            $recommendation = $this->Recommendations->patchEntity($recommendation, $this->request->getData());
            if ($recommendation->specialty == "No specialties available") {
                $recommendation->specialty = null;
            }
            if ($recommendation->member_id == 0 || $recommendation->member_id == null) {
                $recommendation->member_id = null;
                $recommendation->call_into_court = null;
                $recommendation->court_availability = null;
                $recommendation->person_to_notify = null;
            } elseif ($recommendation->member_id != $beforeMemberId) {
                $recommendation->call_into_court = null;
                $recommendation->court_availability = null;
                $recommendation->person_to_notify = null;
                $member = $this->Recommendations->Members->get($recommendation->member_id, select: ["branch_id", "additional_info"]);
                $recommendation->branch_id = $this->Recommendations->Members->get($recommendation->member_id, select: ["branch_id"])->branch_id;
                if ($member->additional_info != null && $member->additional_info != "") {
                    $addInfo = $member->additional_info;
                    if (isset($addInfo["CallIntoCourt"])) {
                        $recommendation->call_into_court = $addInfo["CallIntoCourt"];
                    }
                    if (isset($addInfo["CourtAvailability"])) {
                        $recommendation->court_availability = $addInfo["CourtAvailability"];
                    }
                    if (isset($addInfo["PersonToGiveNoticeTo"])) {
                        $recommendation->person_to_notify = $addInfo["PersonToGiveNoticeTo"];
                    }
                }
            }
            if (!isset($recommendation->call_into_court)) {
                $recommendation->call_into_court = "Not Set";
            }
            if (!isset($recommendation->court_availability)) {
                $recommendation->court_availability = "Not Set";
            }
            if (!isset($recommendation->person_to_notify)) {
                $recommendation->person_to_notify = "";
            }
            if ($this->request->getData()["given"] != null) {
                $recommendation->given = new DateTime($this->request->getData()["given"]);
            }
            //begin transaction
            $this->Recommendations->getConnection()->begin();
            if (!$this->Recommendations->save($recommendation)) {
                $this->Recommendations->getConnection()->rollback();
                if (!$this->request->getHeader('Turbo-Frame')) {
                    $this->Flash->error(__('The recommendation could not be saved. Please, try again.'));
                }
                if ($this->request->getData("current_page")) {
                    return $this->redirect($this->request->getData("current_page"));
                }
                return $this->redirect(['action' => 'view', $id]);
            }
            if ($this->request->getData("note")) {
                $newNote = $this->Recommendations->Notes->newEmptyEntity();
                $newNote->topic_id = $recommendation->id;
                $newNote->subject = "Recommendation Updated";
                $newNote->topic_model = "Awards.Recommendations";
                $newNote->body = $this->request->getData("note");
                $newNote->author_id = $this->request->getAttribute("identity")->id;
                if (!$this->Recommendations->Notes->save($newNote)) {
                    $this->Recommendations->getConnection()->rollback();
                    if (!$this->request->getHeader('Turbo-Frame')) {
                        $this->Flash->error(__('The note could not be saved. Please, try again.'));
                    }
                    if ($this->request->getData("current_page")) {
                        return $this->redirect($this->request->getData("current_page"));
                    }
                    return $this->redirect(['action' => 'view', $id]);
                }
            }
            $this->Recommendations->getConnection()->commit();
            if (!$this->request->getHeader('Turbo-Frame')) {
                $this->Flash->success(__('The recommendation has been saved.'));
            }
        }
        if ($this->request->getData("current_page")) {
            return $this->redirect($this->request->getData("current_page"));
        }
        return $this->redirect(['action' => 'view', $id]);
    }

    public function kanbanUpdate($id = null)
    {
        $recommendation = $this->Recommendations->get($id);
        if (!$recommendation) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($recommendation, 'edit');
        if ($this->request->is(['patch', 'post', 'put'])) {
            $recommendation->state = $this->request->getData('newCol');
            $placeBefore = $this->request->getData('placeBefore');
            $placeAfter = $this->request->getData('placeAfter');
            if ($placeAfter == null) {
                $placeAfter = -1;
            }
            if ($placeBefore == null) {
                $placeBefore = -1;
            }
            $recommendation->state_date = DateTime::now();
            $this->Recommendations->getConnection()->begin();
            $failed = false;
            if (!$this->Recommendations->save($recommendation)) {
                $this->Recommendations->getConnection()->rollback();
                $failed = true;
                $message = ('failed');
            }
            if ($placeBefore != -1 && !$failed) {
                if (!$this->Recommendations->moveBefore($id, $placeBefore)) {
                    $this->Recommendations->getConnection()->rollback();
                    $failed = true;
                    $message = ('failed');
                }
            }
            if ($placeAfter != -1 && !$failed) {
                if (!$this->Recommendations->moveAfter($id, $placeAfter)) {
                    $this->Recommendations->getConnection()->rollback();
                    $failed = true;
                    $message = ('failed');
                }
            }
            if (!$failed) {
                $this->Recommendations->getConnection()->commit();
                $message = ('success');
            }
        } else {
            $message = ('failed');
        }
        $this->response = $this->response->withType("application/json")->withStringBody(json_encode($message));
        return $this->response;
    }

    /**
     * Delete method
     *
     * @param string|null $id Recommendation id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $recommendation = $this->Recommendations->get($id);
        if (!$recommendation) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($recommendation);
        if ($this->Recommendations->delete($recommendation)) {
            $this->Flash->success(__('The recommendation has been deleted.'));
        } else {
            $this->Flash->error(__('The recommendation could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    #region JSON calls
    public function turboEditForm($id = null)
    {
        $recommendation = $this->Recommendations->get($id, contain: ['Requesters', 'Members', 'Branches', 'Awards', 'Events', 'ScheduledEvent', 'Awards.Domains']);
        if (!$recommendation) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($recommendation, 'view');
        $recommendation->domain_id = $recommendation->award->domain_id;
        $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();
        $branches = $this->Recommendations->Awards->Branches
            ->find("list", keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? "true" : "false");
            })
            ->where(["can_have_members" => true])
            ->orderBy(["name" => "ASC"])->toArray();
        $awards = $this->Recommendations->Awards->find('all', limit: 200)->select(["id", "name", "specialties"])->where(['domain_id' => $recommendation->domain_id])->all();
        $eventsData = $this->Recommendations->Events->find()
            ->contain(['Branches' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->where(['OR' => ['closed' => false, 'closed IS' => null]])
            ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
            ->orderBy(['start_date' => 'ASC'])
            ->all();
        $statusList = Recommendation::getStatuses();
        foreach ($statusList as $key => $value) {
            $states = $value;
            $statusList[$key] = [];
            foreach ($states as $state) {
                $statusList[$key][$state] = $state;
            }
        }
        $eventList = [];
        foreach ($eventsData as $event) {
            $eventList[$event->id] = $event->name . " in " . $event->branch->name . " on " . $event->start_date->toDateString() . " - " . $event->end_date->toDateString();
        }
        $rules = StaticHelpers::getAppSetting("Awards.RecommendationStateRules");
        $this->set(compact('rules', 'recommendation', 'branches', 'awards', 'eventList', 'awardsDomains', 'awardsLevels', 'statusList'));
    }

    public function turboQuickEditForm($id = null)
    {
        $recommendation = $this->Recommendations->get($id, contain: ['Requesters', 'Members', 'Branches', 'Awards', 'Events', 'ScheduledEvent', 'Awards.Domains']);
        if (!$recommendation) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($recommendation, 'view');
        $recommendation->domain_id = $recommendation->award->domain_id;
        $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();
        $branches = $this->Recommendations->Awards->Branches
            ->find("list", keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? "true" : "false");
            })
            ->where(["can_have_members" => true])
            ->orderBy(["name" => "ASC"])->toArray();
        $awards = $this->Recommendations->Awards->find('all', limit: 200)->select(["id", "name", "specialties"])->where(['domain_id' => $recommendation->domain_id])->all();
        $eventsData = $this->Recommendations->Events->find()
            ->contain(['Branches' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->where(['OR' => ['closed' => false, 'closed IS' => null]])
            ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
            ->orderBy(['start_date' => 'ASC'])
            ->all();
        $statusList = Recommendation::getStatuses();
        foreach ($statusList as $key => $value) {
            $states = $value;
            $statusList[$key] = [];
            foreach ($states as $state) {
                $statusList[$key][$state] = $state;
            }
        }
        $eventList = [];
        foreach ($eventsData as $event) {
            $eventList[$event->id] = $event->name . " in " . $event->branch->name . " on " . $event->start_date->toDateString() . " - " . $event->end_date->toDateString();
        }
        $rules = StaticHelpers::getAppSetting("Awards.RecommendationStateRules");
        $this->set(compact('rules', 'recommendation', 'branches', 'awards', 'eventList', 'awardsDomains', 'awardsLevels', 'statusList'));
    }
    #endregion

    protected function runTable($filterArray, $status, $view = "Default")
    {
        $recommendations = $this->getRecommendationQuery($filterArray);
        if ($status == "All") {
            $statusList = Recommendation::getStatuses();
        } else {
            $statusList[$status] = Recommendation::getStatuses()[$status];
        }
        foreach ($statusList as $key => $value) {
            $statusList[$key] = array_combine($value, $value);
        }
        $user = $this->request->getAttribute("identity");
        $blank = $this->Recommendations->newEmptyEntity();
        if (!$user->checkCan("ViewHidden", $blank)) {
            $hiddenStates = StaticHelpers::getAppSetting("Awards.RecommendationStatesRequireCanViewHidden");
            $recommendations->where(["Recommendations.status not IN  " => $hiddenStates]);
            foreach ($statusList as $key => $value) {
                $tmpStatus = $statusList[$key];
                foreach ($hiddenStates as $hiddenState) {
                    try {
                        unset($tmpStatus[$hiddenState]);
                    } catch (\Exception $e) {
                        //do nothing
                    }
                }
                if (empty($tmpStatus)) {
                    unset($statusList[$key]);
                } else {
                    $statusList[$key] = $tmpStatus;
                }
            }
        }


        $awards = $this->Recommendations->Awards->find(
            'list',
            limit: 200,
            keyField: 'id',
            valueField: 'abbreviation'
        )->all();
        $domains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $branches = $this->Recommendations->Branches
            ->find("list", keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members == 1 ? "true" : "false");
            })

            ->where(["can_have_members" => true])
            ->orderBy(["name" => "ASC"])->toArray();
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
            ],
        ];
        $action = $view;
        $recommendations = $this->paginate($recommendations);
        $this->set(compact('recommendations', 'statusList', 'awards', 'domains', 'branches', 'view', 'status', 'action'));
    }

    protected function runBoard($view, $pageConfig, $emptyRecommendation)
    {
        $statesList = $pageConfig['board']['states'];
        $states = [];
        foreach ($statesList as $state) {
            $states[$state] = [];
        }
        $statesToLoad = $pageConfig['board']['states'];
        $hiddenByDefault = $pageConfig['board']['hiddenByDefault'];
        $hiddenByDefaultStates = [];
        if (is_array($hiddenByDefault) && !empty($hiddenByDefault)) {
            foreach ($hiddenByDefault["states"] as $state) {
                //remove values that match the key from the statesToLoad
                $hiddenByDefaultStates[] = $state;
                $statesToLoad = array_diff($statesToLoad, [$state]);
            }
        }
        $user = $this->request->getAttribute("identity");
        $hiddenByDefault = $pageConfig['board']['hiddenByDefault'];
        $hiddenByDefaultStates = [];
        if (is_array($hiddenByDefault) && !empty($hiddenByDefault)) {
            foreach ($hiddenByDefault["states"] as $state) {
                //remove values that match the key from the statesToLoad
                $hiddenByDefaultStates[] = $state;
            }
        }
        $recommendations = $this->Recommendations->find()
            ->contain(['Requesters', 'Members', 'Awards'])->orderBy(['Recommendations.state', 'stack_rank'])
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

        if (!$user->checkCan("ViewHidden", $emptyRecommendation)) {
            $hiddenStates = StaticHelpers::getAppSetting("Awards.RecommendationStatesRequireCanViewHidden");
            $recommendations = $recommendations->where(["Recommendations.state not IN  " => $hiddenStates]);
            foreach ($hiddenStates as $state) {
                if (in_array($state, $hiddenByDefaultStates)) {
                    $hiddenByDefaultStates = array_diff($hiddenByDefaultStates, [$state]);
                }
            }
        }
        $showHidden = $this->request->getQuery("showHidden") == 'true';
        $range = $hiddenByDefault["lookback"];
        $hiddenStates = "";
        if (is_array($hiddenByDefaultStates) && !empty($hiddenByDefaultStates)) {
            $hiddenStates = implode(",", $hiddenByDefaultStates);
            if ($showHidden) {
                $recommendations = $recommendations->where([
                    'OR' => [
                        "Recommendations.state IN" => $statesToLoad,
                        'AND' => ["Recommendations.state IN  " => $hiddenByDefaultStates, "Recommendations.state_date >" => DateTime::now()->subDays($range)]
                    ]
                ]);
            } else {
                $recommendations = $recommendations->where(["Recommendations.state IN" => $statesToLoad]);
            }
        } else {
            $recommendations = $recommendations->where(["Recommendations.state IN" => $statesToLoad]);
        }

        $recommendations = $recommendations->all();

        foreach ($recommendations as $recommendation) {
            if (!is_array($states[$recommendation->state])) {
                $states[$recommendation->state] = [];
            }
            $states[$recommendation->state][] = $recommendation;
        }
        $rules = StaticHelpers::getAppSetting("Awards.RecommendationStateRules");
        $this->set(compact('recommendations', 'states', 'view', 'showHidden', 'range', 'hiddenStates', 'rules'));
    }

    protected function runExport($filterArray, $columns)
    {
        $recommendations = $this->getRecommendationQuery($filterArray);
        $recommendations = $recommendations->all();

        $header = [];
        $data[] = [];
        foreach ($columns as $key => $use) {
            if ($use) {
                $header[] = $key;
            }
        }

        foreach ($recommendations as $recommendation) {
            $row = [];
            foreach ($header as $key) {
                switch ($key) {
                    case "Submitted":
                        $row[] = $recommendation->created;
                        break;
                    case "For":
                        $row[] = $recommendation->member_sca_name;
                        break;
                    case "For Herald":
                        if ($recommendation->member) {
                            $row[] = $recommendation->member->name_for_herald;
                        } else {
                            $row[] = $recommendation->member_sca_name;
                        }
                        break;
                    case "Title":
                        if ($recommendation->member) {
                            $row[] = $recommendation->member->title;
                        } else {
                            $row[] = "";
                        }
                        break;
                    case "Pronouns":
                        if ($recommendation->member) {
                            $row[] = $recommendation->member->pronouns;
                        } else {
                            $row[] = "";
                        }
                        break;
                    case "Pronunciation":
                        if ($recommendation->member) {
                            $row[] = $recommendation->member->pronunciation;
                        } else {
                            $row[] = "";
                        }
                        break;
                    case "OP":
                        $links = "";
                        if ($recommendation->member) {
                            $member = $recommendation->member;
                            $externalLinks =  $member->publicLinks();
                            if ($externalLinks) {
                                foreach ($externalLinks as $name => $link) {
                                    $links = "$links | $name : $link";
                                }
                                $links = "$links |";
                            }
                        }
                        $row[] = $links;
                        break;
                    case "Branch":
                        $row[] = $recommendation->branch->name;
                        break;
                    case "Call Into Court":
                        $row[] = $recommendation->call_into_court;
                        break;
                    case "Court Avail":
                        $row[] = $recommendation->court_availability;
                        break;
                    case "Person to Notify":
                        $row[] = $recommendation->person_to_notify;
                        break;
                    case "Submitted By":
                        $row[] = $recommendation->requester_sca_name;
                        break;
                    case "Contact Email":
                        $row[] = $recommendation->contact_email;
                        break;
                    case "Contact Phone":
                        $row[] = $recommendation->contact_phone;
                        break;
                    case "Domain":
                        $row[] = $recommendation->award->domain->name;
                        break;
                    case "Award":
                        $row[] = $recommendation->award->abbreviation . ($recommendation->specialty ? " (" . $recommendation->specialty . ")" : "");
                        break;
                    case "Reason":
                        $row[] = $recommendation->reason;
                        break;
                    case "Events":
                        $events = "";
                        foreach ($recommendation->events as $event) {
                            $startDate = $event->start_date->toDateString();
                            $endDate = $event->end_date->toDateString();
                            $events = "$events$event->name : $startDate  - $endDate\n\n";
                        }
                        $row[] = $events;
                        break;
                    case "Notes":
                        $notes = "";
                        foreach ($recommendation->notes as $note) {
                            $createDate = $note->created->toDateTimeString();
                            $notes = "$notes$createDate : $note->body\n\n";
                        }
                        $row[] = $notes;
                        break;
                    case "Status":
                        $row[] = $recommendation->status;
                        break;
                    case "Event":
                        $row[] = $recommendation->assigned_event ? $recommendation->assigned_event->name : "";
                        break;
                    case "State":
                        $row[] = $recommendation->state;
                        break;
                    case "Close Reason":
                        $row[] = $recommendation->close_reason;
                        break;
                    case "State Date":
                        $row[] = $recommendation->state_date->toDateString();
                        break;
                    case "Given Date":
                        $row[] = $recommendation->given ? $recommendation->given->toDateString() : "";
                        break;
                }
            }
            $data[] = $row;
        }


        $this->set(compact('data'));
        $this->viewBuilder()
            ->setClassName('CsvView.Csv')
            ->setOptions(['serialize' => 'data', 'header' => $header]);
    }

    protected function getRecommendationQuery($filterArray = null)
    {
        $recommendations = $this->Recommendations->find()
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
                'Awards' => function ($q) {
                    return $q->select(['id', 'abbreviation']);
                },
                'Awards.Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Events' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                },
                'Notes' => function ($q) {
                    return $q->select(['id', 'topic_id', 'subject', 'body', 'created']);
                },
                'Notes.Authors' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'AssignedEvent' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ]);
        if ($filterArray) {
            $recommendations->where($filterArray);
        }
        if ($this->request->getQuery("award_id")) {
            $recommendations->where(["award_id" => $this->request->getQuery("award_id")]);
        }
        if ($this->request->getQuery("branch_id")) {
            $recommendations->where(["Recommendations.branch_id" => $this->request->getQuery("branch_id")]);
        }
        if ($this->request->getQuery("for")) {
            $recommendations->where(["member_sca_name LIKE" => "%" . $this->request->getQuery("for") . "%"]);
        }
        if ($this->request->getQuery("call_into_court")) {
            $recommendations->where(["call_into_court" => $this->request->getQuery("call_into_court")]);
        }
        if ($this->request->getQuery("court_avail")) {
            $recommendations->where(["court_availability" => $this->request->getQuery("court_avail")]);
        }
        if ($this->request->getQuery("requester_sca_name")) {
            $recommendations->where(["requester_sca_name" => $this->request->getQuery("requester_sca_name")]);
        }
        if ($this->request->getQuery("domain_id")) {
            $recommendations->where(["Awards.domain_id" => $this->request->getQuery("domain_id")]);
        }
        if ($this->request->getQuery("state")) {
            $recommendations->where(["Recommendations.state" => $this->request->getQuery("state")]);
        }
        return $recommendations;
    }

    protected function processFilter($filter)
    {
        $filterArray = [];
        foreach ($filter as $key => $value) {
            $fixedKey = str_replace("->", ".", $key);
            //if value starts with { and ends with } then it grab it from the query
            if (substr($value, 0, 1) == "-" && substr($value, -1) == "-") {
                $value = substr($value, 1, -1);
                $filterArray[$fixedKey] = $this->request->getQuery($value);
            } else {
                $filterArray[$fixedKey] = $value;
            }
        }
        return $filterArray;
    }
}