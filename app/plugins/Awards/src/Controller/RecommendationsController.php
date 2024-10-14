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
        $filter = [];
        $this->runIndexPage("Index", $filter, Recommendation::getStatues());
    }

    public function toBeProcessed()
    {
        $filter = ["Recommendations.status not IN" => [Recommendation::STATUS_DECLINED, Recommendation::STATUS_NEED_TO_SCHEDULE, Recommendation::STATUS_SCHEDULED, Recommendation::STATUS_GIVEN]];
        $this->runIndexPage("To_Be_Processed", $filter, Recommendation::getToBeProcessedStatues());
    }

    public function toBeScheduled()
    {
        $filter = ["Recommendations.status not IN" => [Recommendation::STATUS_DECLINED, Recommendation::STATUS_SUBMITTED, Recommendation::STATUS_IN_CONSIDERATION, Recommendation::STATUS_AWAITING_FEEDBACK, Recommendation::STATUS_GIVEN]];
        $this->runIndexPage("To_Be_Scheduled", $filter, Recommendation::getToBeProcessedStatues());
    }

    public function toBeGiven()
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
                    return $q->select(['id', 'abbreviation']);
                },
                'Awards.Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'AssignedEvent' => function ($q) {
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
        if ($this->request->getQuery("event_id")) {
            $recommendations->where(["event_id" => $this->request->getQuery("event_id")]);
        }
        $recommendations->where(["Recommendations.status" => Recommendation::STATUS_SCHEDULED]);

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
        $events = $this->Recommendations->Events->find('list', limit: 200)
            ->where(["start_date >" => DateTime::now()])
            ->all();
        $this->paginate = [
            'sortableFields' => [
                'Branches.name',
                'Awards.name',
                'Domains.name',
                'member_sca_name',
                'call_into_court',
                'court_availability',
                'person_to_notify',
                'contact_email',
                'status_date',
                'Event.start_date',
            ],
        ];
        if ($this->request->getQuery("csv") == "true") {
            $csvData = [];
            $csvData[] = ['Title', 'Name', "Pronunciation", "Pronouns", 'Award', 'Court Availability', 'Call Into Court', 'Person To Notify', 'Event', 'Status', 'Reason'];
            $recommendations = $recommendations->toArray();
            foreach ($recommendations as $rec) {
                $csvData[] = [
                    $rec->member->title,
                    $rec->member_sca_name,
                    $rec->member->pronunciation,
                    $rec->member->pronouns,
                    $rec->award->abbreviation . ($rec->specialty ? " (" . $rec->specialty . ")" : ""),
                    $rec->court_availability,
                    $rec->call_into_court,
                    $rec->person_to_notify,
                    $rec->assigned_event->name,
                    $rec->status,
                    $rec->reason
                ];
            }
            $csv = StaticHelpers::arrayToCsv($csvData);
            $this->response = $this->response->withType("csv")->withDownload("recommendations.csv")->withStringBody($csv);
            return $this->response;
        } else {
            $recommendations = $this->paginate($recommendations);
            $this->set(compact('recommendations', 'awards', 'domains', 'branches', 'callIntoCourt', 'courtAvailability', 'events'));
        }
    }

    /**
     * board view
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function toBeProcessedBoard()
    {

        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($emptyRecommendation, 'toBeProcessedBoard');

        $recommendations = $this->Recommendations->find()
            ->contain(['Requesters', 'Members', 'Awards'])->orderBy(['Recommendations.status', 'stack_rank'])
            ->select([
                'Recommendations.id',
                'Recommendations.member_sca_name',
                'Recommendations.reason',
                'Recommendations.stack_rank',
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
        $ShowDeclined = false;
        //if get30DaysDenied is true
        if ($this->request->getQuery('includeDeclined') == 'true') {
            $ShowDeclined = true;
            $recommendations =
                $recommendations->where([
                    'OR' => [
                        "Recommendations.status not IN" => [Recommendation::STATUS_DECLINED, Recommendation::STATUS_NEED_TO_SCHEDULE, Recommendation::STATUS_SCHEDULED, Recommendation::STATUS_GIVEN],
                        'AND' => ['Recommendations.modified >=' => DateTime::now()->subDays(30), 'Recommendations.status' => Recommendation::STATUS_DECLINED]
                    ]
                ]);
        } else {
            $recommendations = $recommendations->where(["Recommendations.status not IN" => [Recommendation::STATUS_DECLINED, Recommendation::STATUS_NEED_TO_SCHEDULE, Recommendation::STATUS_SCHEDULED, Recommendation::STATUS_GIVEN]]);
        }
        $recommendations = $recommendations->all();
        $statuses = Recommendation::getToBeProcessedStatues();
        $statuses[Recommendation::STATUS_NEED_TO_SCHEDULE] = "Need to Schedule";
        $statuses[Recommendation::STATUS_DECLINED] = "Declined";

        $statusNames = $statuses;

        foreach ($recommendations as $recommendation) {
            if (!is_array($statuses[$recommendation->status])) {
                $statuses[$recommendation->status] = [];
            }
            $statuses[$recommendation->status][] = $recommendation;
        }
        $viewAction = "To_Be_Processed";
        $this->set(compact('recommendations', 'statuses', 'viewAction', 'statusNames', 'ShowDeclined'));
    }

    /**
     * board view
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function toBeScheduledBoard()
    {

        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($emptyRecommendation, 'toBeScheduledBoard');

        $recommendations = $this->Recommendations->find()
            ->where(["Recommendations.status not IN" => [Recommendation::STATUS_DECLINED, Recommendation::STATUS_AWAITING_FEEDBACK, Recommendation::STATUS_IN_CONSIDERATION, Recommendation::STATUS_SUBMITTED]])
            ->contain(['Requesters', 'Members', 'Branches', 'Awards'])->orderBy(['Recommendations.status', 'stack_rank'])
            ->select([
                'Recommendations.id',
                'Recommendations.member_sca_name',
                'Recommendations.reason',
                'Recommendations.stack_rank',
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
            ])
            ->all();

        $statuses = Recommendation::getToBeScheduledStatues();

        $statuses[Recommendation::STATUS_GIVEN] = "Given";
        $statusNames = $statuses;

        foreach ($recommendations as $recommendation) {
            if (!is_array($statuses[$recommendation->status])) {
                $statuses[$recommendation->status] = [];
            }
            $statuses[$recommendation->status][] = $recommendation;
        }
        $viewAction = "To_Be_Scheduled";
        $this->set(compact('recommendations', 'statuses', 'viewAction', 'statusNames'));
        $this->render('board');
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

    protected function runIndexPage($action, $filterArray, $pageStatuses)
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
        if ($this->request->getQuery("status")) {
            $recommendations->where(["Recommendations.status" => $this->request->getQuery("status")]);
        }
        $statuses = $pageStatuses;

        $user = $this->request->getAttribute("identity");
        $firstRec = $recommendations->first();
        if ($firstRec) {
            if (!$user->can("ViewDeclined", $firstRec)) {
                $recommendations->where(["Recommendations.status <> " => Recommendation::STATUS_DECLINED]);
                //remove declined from statuses
                unset($statuses[Recommendation::STATUS_DECLINED]);
            }
        } else {
            unset($statuses[Recommendation::STATUS_DECLINED]);
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
        $viewAction = $action;
        $recommendations = $this->paginate($recommendations);
        $this->set(compact('recommendations', 'statuses', 'awards', 'domains', 'branches', 'callIntoCourt', 'courtAvailability', 'viewAction'));
        $this->render("index");
    }
}