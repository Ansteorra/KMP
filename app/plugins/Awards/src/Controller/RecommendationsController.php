<?php

declare(strict_types=1);

namespace Awards\Controller;

use Awards\Controller\AppController;
use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;
use App\KMP\StaticHelpers;

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
        $recommendations = $this->Recommendations->find()
            ->contain([
                'Requesters' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Members',
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Awards' => function ($q) {
                    return $q->select(['id', 'name']);
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
                }
            ]);

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
        if ($this->request->getQuery("status")) {
            $recommendations->where(["Recommendations.status" => $this->request->getQuery("status")]);
        }
        $statuses = Recommendation::getStatues();
        $awards = $this->Recommendations->Awards->find('list', limit: 200)->all();
        $domains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $branches = $this->Recommendations->Branches
            ->find("treeList", spacer: "--")
            ->orderBy(["name" => "ASC"]);
        $callIntoCourtOptions = explode(",", StaticHelpers::getAppSetting("Awards.CallIntoCourtOptions", "Never,With Notice,Without Notice"));
        $callIntoCourt = [];
        foreach ($callIntoCourtOptions as $option) {
            $callIntoCourt[$option] = $option;
        }
        $courtAvailabilityOptions = explode(",", StaticHelpers::getAppSetting("Awards.CourtAvailabilityOptions", "None,Morning,Evening,Any"));
        $courtAvailability = [];
        foreach ($courtAvailabilityOptions as $option) {
            $courtAvailability[$option] = $option;
        }
        $this->paginate = [
            'sortableFields' => [
                'Branches.name',
                'Awards.name',
                'Domains.name',
                'member_sca_name',
                'created',
                'status',
                'call_into_court',
                'court_availability',
                'requester_sca_name',
                'contact_email',
                'contact_phone',
                'status_date',
            ],
        ];
        $recommendations = $this->paginate($recommendations);
        $this->set(compact('recommendations', 'statuses', 'awards', 'domains', 'branches', 'callIntoCourt', 'courtAvailability'));
    }

    public function toBeProcessed()
    {
        $recommendations = $this->Recommendations->find()
            ->where(["Recommendations.status not IN" => [Recommendation::STATUS_DECLINED, Recommendation::STATUS_NEED_TO_SCHEDULE, Recommendation::STATUS_SCHEDULED, Recommendation::STATUS_GIVEN]])
            ->contain([
                'Requesters' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Members',
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Awards' => function ($q) {
                    return $q->select(['id', 'name']);
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
                }
            ]);

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
        $statuses = Recommendation::getStatues();
        $awards = $this->Recommendations->Awards->find('list', limit: 200)->all();
        $domains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $branches = $this->Recommendations->Branches
            ->find("treeList", spacer: "--")
            ->orderBy(["name" => "ASC"]);
        $callIntoCourtOptions = explode(",", StaticHelpers::getAppSetting("Awards.CallIntoCourtOptions", "Never,With Notice,Without Notice"));
        $callIntoCourt = [];
        foreach ($callIntoCourtOptions as $option) {
            $callIntoCourt[$option] = $option;
        }
        $courtAvailabilityOptions = explode(",", StaticHelpers::getAppSetting("Awards.CourtAvailabilityOptions", "None,Morning,Evening,Any"));
        $courtAvailability = [];
        foreach ($courtAvailabilityOptions as $option) {
            $courtAvailability[$option] = $option;
        }
        $this->paginate = [
            'sortableFields' => [
                'Branches.name',
                'Awards.name',
                'Domains.name',
                'member_sca_name',
                'created',
                'status',
                'call_into_court',
                'court_availability',
                'requester_sca_name',
                'contact_email',
                'contact_phone',
                'status_date',
            ],
        ];
        $recommendations = $this->paginate($recommendations);
        $this->set(compact('recommendations', 'statuses', 'awards', 'domains', 'branches', 'callIntoCourt', 'courtAvailability'));
        $this->render("index");
    }


    /**
     * board view
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function board()
    {

        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($emptyRecommendation, 'board');

        $recommendations = $this->Recommendations->find()
            ->contain(['Requesters', 'Members', 'Branches', 'Awards'])->orderBy(['Recommendations.status', 'stack_rank'])->all();

        $statuses = Recommendation::getStatues();
        foreach ($recommendations as $recommendation) {
            if (!is_array($statuses[$recommendation->status])) {
                $statuses[$recommendation->status] = [];
            }
            $statuses[$recommendation->status][] = $recommendation;
        }
        $this->set(compact('recommendations', 'statuses'));
    }

    public function memberSubmissions($id)
    {
        $member = $this->Recommendations->Members->find()
            ->where(["id" => $id])
            ->select("id")->first();
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($member, 'view');
        $recommendations = $this->Recommendations->find()
            ->where(["requester_id" => $id])
            ->contain(['Awards' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->select(['id', 'member_sca_name', 'Awards.name', 'Recommendations.created', 'reason'])
            ->orderBy(['Recommendations.created'])->all();
        $statuses = Recommendation::getStatues();
        foreach ($recommendations as $recommendation) {
            if (!is_array($statuses[$recommendation->status])) {
                $statuses[$recommendation->status] = [];
            }
            $statuses[$recommendation->status][] = $recommendation;
        }
        $this->set(compact('recommendations'));
    }

    public function submittedForMember($id)
    {
        $member = $this->Recommendations->Members->find()
            ->where(["id" => $id])
            ->select("id")->first();
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($emptyRecommendation, 'view');
        $recommendations = $this->Recommendations->find()
            ->where(["member_id" => $id])
            ->contain(['Awards' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->select(['id', 'requester_sca_name', 'Awards.name', 'Recommendations.created', 'Recommendations.status', 'reason'])
            ->orderBy(['Recommendations.created'])->all();
        $statuses = Recommendation::getStatues();
        foreach ($recommendations as $recommendation) {
            if (!is_array($statuses[$recommendation->status])) {
                $statuses[$recommendation->status] = [];
            }
            $statuses[$recommendation->status][] = $recommendation;
        }
        $this->set(compact('recommendations'));
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
        $this->Authorization->authorize($recommendation, 'edit');
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
            $recommendation->status_date = DateTime::now();
            $recommendation["not_found"] = $this->request->getData("not_found") == "on";
            if ($recommendation->not_found) {
                $recommendation->member_id = null;
            } else {
                $recommendation->branch_id = $this->Recommendations->Members->get($recommendation->member_id, select: ["branch_id"])->branch_id;
            }
            if ($this->Recommendations->save($recommendation)) {
                $this->Flash->success(__('The recommendation has been saved.'));
                if ($user->can("view", $recommendation)) {
                    return $this->redirect(['action' => 'view', $recommendation->id]);
                }
                return $this->redirect(['controller' => 'members', 'plugin' => null, 'action' => 'view', $this->request->getAttribute("identity")->id]);
            }
            $this->Flash->error(__('The recommendation could not be saved. Please, try again.'));
        }
        $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();
        $branches = $this->Recommendations->Awards->Branches
            ->find("treeList", spacer: "--")
            ->orderBy(["name" => "ASC"]);
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
        $callIntoCourtOptions = explode(",", StaticHelpers::getAppSetting("Awards.CallIntoCourtOptions", "Never,With Notice,Without Notice"));
        $courtAvailabilityOptions = explode(",", StaticHelpers::getAppSetting("Awards.CourtAvailabilityOptions", "None,Morning,Evening,Any"));
        $this->set(compact('recommendation', 'branches', 'awards', 'events', 'awardsDomains', 'awardsLevels', 'callIntoCourtOptions', 'courtAvailabilityOptions'));
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
            $recommendation->status_date = DateTime::now();
            $recommendation["not_found"] = $this->request->getData("not_found") == "on";
            if ($recommendation->not_found) {
                $recommendation->member_id = null;
            } else {
                $recommendation->branch_id = $this->Recommendations->Members->get($recommendation->member_id, select: ["branch_id"])->branch_id;
            }
            if ($this->Recommendations->save($recommendation)) {
                $this->Flash->success(__('The recommendation has been submitted.'));
            } else {
                $this->Flash->error(__('The recommendation could not be submitted. Please, try again.'));
            }
        }
        $headerImage = StaticHelpers::getAppSetting(
            "KMP.Login.Graphic",
            "populace_badge.png",
        );
        $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();
        $branches = $this->Recommendations->Awards->Branches
            ->find("treeList", spacer: "--")
            ->orderBy(["name" => "ASC"]);
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
        $callIntoCourtOptions = explode(",", StaticHelpers::getAppSetting("Awards.CallIntoCourtOptions", "Never,With Notice,Without Notice"));
        $courtAvailabilityOptions = explode(",", StaticHelpers::getAppSetting("Awards.CourtAvailabilityOptions", "None,Morning,Evening,Any"));
        $this->set(compact('recommendation', 'branches', 'awards', 'events', 'awardsDomains', 'awardsLevels', 'headerImage', 'callIntoCourtOptions', 'courtAvailabilityOptions'));
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
            $recommendation = $this->Recommendations->patchEntity($recommendation, $this->request->getData());
            if ($recommendation->member_id == 0) {
                $recommendation->member_id = null;
            }
            if ($this->request->getData()["given"] != null) {
                $recommendation->given = new DateTime($this->request->getData()["given"]);
            }
            //begin transaction
            $this->Recommendations->getConnection()->begin();
            if (!$this->Recommendations->save($recommendation)) {
                $this->Recommendations->getConnection()->rollback();
                $this->Flash->error(__('The recommendation could not be saved. Please, try again.'));
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
                    $this->Flash->error(__('The note could not be saved. Please, try again.'));
                    if ($this->request->getData("current_page")) {
                        return $this->redirect($this->request->getData("current_page"));
                    }
                    return $this->redirect(['action' => 'view', $id]);
                }
            }
            $this->Recommendations->getConnection()->commit();
            $this->Flash->success(__('The recommendation has been saved.'));
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
            $recommendation->status = $this->request->getData('status');
            $placeBefore = $this->request->getData('placeBefore');
            $placeAfter = $this->request->getData('placeAfter');
            if ($placeAfter == null) {
                $placeAfter = -1;
            }
            if ($placeBefore == null) {
                $placeBefore = -1;
            }
            $recommendation->status_date = DateTime::now();
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
        $this->Authorization->authorize($recommendation, 'edit');
        $recommendation->domain_id = $recommendation->award->domain_id;
        $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();
        $branches = $this->Recommendations->Awards->Branches
            ->find("treeList", spacer: "--")
            ->orderBy(["name" => "ASC"]);
        $awards = $this->Recommendations->Awards->find('all', limit: 200)->select(["id", "name", "specialties"])->where(['domain_id' => $recommendation->domain_id])->all();
        $eventsData = $this->Recommendations->Events->find()
            ->contain(['Branches' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->where(["start_date >" => DateTime::now()])
            ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
            ->all();
        $statusList = Recommendation::getStatues();
        $eventList = [];
        foreach ($eventsData as $event) {
            $eventList[$event->id] = $event->name . " in " . $event->branch->name . " on " . $event->start_date->toDateString() . " - " . $event->end_date->toDateString();
        }
        $callIntoCourtOptions = explode(",", StaticHelpers::getAppSetting("Awards.CallIntoCourtOptions", "Never,With Notice,Without Notice"));
        $courtAvailabilityOptions = explode(",", StaticHelpers::getAppSetting("Awards.CourtAvailabilityOptions", "None,Morning,Evening,Any"));
        $this->set(compact('recommendation', 'branches', 'awards', 'eventList', 'awardsDomains', 'awardsLevels', 'statusList', 'callIntoCourtOptions', 'courtAvailabilityOptions'));
    }

    #endregion
}