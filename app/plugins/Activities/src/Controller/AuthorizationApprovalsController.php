<?php

declare(strict_types=1);

namespace Activities\Controller;

/**
 * AuthorizationApprovals Controller
 *
 * @property \App\Model\Table\AuthorizationApprovalsTable $AuthorizationApprovals
 */

use Activities\Services\AuthorizationManagerInterface;
use Cake\Mailer\MailerAwareTrait;
use Cake\Event\EventInterface;

class AuthorizationApprovalsController extends AppController
{
    use MailerAwareTrait;


    /**
     * controller filters
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        $this->Authorization->authorizeModel('index', 'myQueue', 'view');
    }
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $search = $this->request->getQuery("search");

        $search = $search ? trim($search) : null;

        $query = $this->AuthorizationApprovals
            ->find()
            ->contain(["Approvers" => function ($q) {
                return $q->select(["Approvers.id", "Approvers.sca_name", "Approvers.last_login"]);
            }])
            ->innerJoinWith("Approvers");
        // group by approver and count pending, approved, and denied
        $query
            ->select([
                "approver_id",
                "Approvers.id",
                "approver_name" => "Approvers.sca_name",
                "last_login" => "Approvers.last_login",
                "pending_count" => $query
                    ->func()
                    ->count(
                        "CASE WHEN AuthorizationApprovals.responded_on IS NULL THEN 1 END",
                    ),
                "approved_count" => $query
                    ->func()
                    ->count(
                        "CASE WHEN AuthorizationApprovals.approved = 1 THEN 1 END",
                    ),
                "denied_count" => $query
                    ->func()
                    ->count(
                        "CASE WHEN AuthorizationApprovals.approved = 0  && AuthorizationApprovals.responded_on IS NOT NULL THEN 1 END",
                    ),
            ])
            ->group("Approvers.id");

        if ($search) {
            //detect th and replace with Þ
            $nsearch = $search;
            if (preg_match("/th/", $search)) {
                $nsearch = str_replace("th", "Þ", $search);
            }
            //detect Þ and replace with th
            $usearch = $search;
            if (preg_match("/Þ/", $search)) {
                $usearch = str_replace("Þ", "th", $search);
            }
            $query = $query->where([
                "OR" => [
                    ["Approvers.sca_name LIKE" => "%" . $search . "%"],
                    ["Approvers.sca_name LIKE" => "%" . $nsearch . "%"],
                    ["Approvers.sca_name LIKE" => "%" . $usearch . "%"],
                    ["Approvers.email_address LIKE" => "%" . $search . "%"],
                    ["Approvers.email_address LIKE" => "%" . $nsearch . "%"],
                    ["Approvers.email_address LIKE" => "%" . $usearch . "%"],
                ],
            ]);
        }

        $this->Authorization->applyScope($query);
        $this->paginate = [
            'sortableFields' => [
                'approver_name',
                'last_login',
                'pending_count',
                'approved_count',
                'denied_count'
            ],
        ];
        $authorizationApprovals = $this->paginate($query, [
            'order' => [
                'approver_name' => 'asc',
            ]
        ]);
        $this->set(compact("authorizationApprovals", "search"));
    }

    public function myQueue($token = null)
    {
        $member_id = $this->Authentication->getIdentity()->getIdentifier();
        $query = $this->getAuthorizationApprovalsQuery($member_id);
        if ($token) {
            $query = $query->where(["authorization_token" => $token]);
        }
        $this->Authorization->applyScope($query);
        $authorizationApprovals = $query->all();
        $queueFor = $this->Authentication->getIdentity()->sca_name;
        $isMyQueue = true;
        $this->set(compact("queueFor", "isMyQueue", "authorizationApprovals"));
        $this->render('view');
    }

    /**
     * View method
     *
     * @param string|null $id Authorization Approval id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $query = $this->getAuthorizationApprovalsQuery($id);
        $this->Authorization->applyScope($query);
        $authorizationApprovals = $query->all();
        $queueFor = $this->AuthorizationApprovals->Approvers->find()
            ->select(['sca_name'])
            ->where(['id' => $id])
            ->first()->sca_name;
        $isMyQueue = false;
        $this->set(compact("queueFor", "isMyQueue", "authorizationApprovals"));
    }

    protected function getAuthorizationApprovalsQuery($memberId)
    {
        $query = $this->AuthorizationApprovals
            ->find()
            ->contain([
                "Authorizations" => function ($q) {
                    return $q->select([
                        "Authorizations.status",
                        "Authorizations.approval_count",
                        "Authorizations.is_renewal",
                    ]);
                },
                "Authorizations.Members" => function ($q) {
                    return $q->select(["Members.sca_name", "Members.membership_number", "Members.membership_expires_on", "Members.background_check_expires_on"]);
                },
                "Authorizations.Activities" => function ($q) {
                    return $q->select([
                        "Activities.name",
                        "Activities.num_required_authorizors",
                        "Activities.num_required_renewers",
                    ]);
                },
                "Approvers" => function ($q) {
                    return $q->select(["Approvers.sca_name"]);
                },
            ])
            ->where(["approver_id" => $memberId]);

        return $query;
    }

    /**
     * Approve method
     *
     * @param string|null $id Authorization Approval id.
     * @return \Cake\Http\Response|null|void Redirects on successful approval, renders view otherwise.
     */
    public function approve(
        AuthorizationManagerInterface $maService,
        $id = null,
    ) {
        if ($id == null) {
            $id = $this->request->getData("id");
        }
        $this->request->allowMethod(["post"]);

        $authorizationApproval = $this->AuthorizationApprovals->get($id);
        if (!$authorizationApproval) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorizationApproval);

        $approverId = $this->Authentication->getIdentity()->getIdentifier();
        $nextApproverId = $this->request->getData("next_approver_id");
        $maResult = $maService->approve(
            (int)$id,
            (int)$approverId,
            (int)$nextApproverId,
        );
        if (!$maResult->success) {
            $this->Flash->error(
                __(
                    "The authorization approval could not be approved. Please, try again.",
                ),
            );

            return $this->redirect($this->referer());
        }
        $this->Flash->success(
            __("The authorization approval has been processed"),
        );

        return $this->redirect($this->referer());
    }

    public function availableApproversList($id)
    {
        $this->request->allowMethod(["get"]);
        $this->viewBuilder()->setClassName("Ajax");
        $authorizationApproval = $this->AuthorizationApprovals->get(
            $id,
            contain: [
                "Authorizations" => function ($q) {
                    return $q->select(["Authorizations.activity_id", 'Authorizations.member_id']);
                },
                "Authorizations.Activities" => function ($q) {
                    return $q->select(["Activities.id", "Activities.permission_id"]);
                },
            ],
        );
        if (!$authorizationApproval) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorizationApproval);
        $previousApprovers = $this->AuthorizationApprovals
            ->find(
                "list",
                keyField: "approver_id",
                valueField: "approver_id"
            )
            ->where([
                "authorization_id" => $authorizationApproval->authorization_id,
            ])
            ->select(["approver_id"])
            ->all()
            ->toList();
        $memberId = $this->Authentication->getIdentity()->getIdentifier();
        $previousApprovers[] = $memberId;
        $previousApprovers[] = $authorizationApproval->authorization->member_id;
        $query = $authorizationApproval->authorization->activity->getApproversQuery();
        $result = $query
            ->contain(["Branches"])
            ->where(["Members.id NOT IN " => $previousApprovers])
            ->orderBy(["Branches.name", "Members.sca_name"])
            ->select(["Members.id", "Members.sca_name", "Branches.name"])
            ->distinct()
            ->all()
            ->toArray();
        $responseData = [];
        foreach ($result as $member) {
            $responseData[] = [
                "id" => $member->id,
                "sca_name" => $member->branch->name . ": " . $member->sca_name,
            ];
        }
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($responseData));

        return $this->response;
    }

    public function deny(AuthorizationManagerInterface $maService, $id = null)
    {
        $this->request->allowMethod(["post"]);
        if ($id == null) {
            $id = $this->request->getData("id");
        }
        $authorizationApproval = $this->AuthorizationApprovals->get($id);
        if (!$authorizationApproval) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorizationApproval);
        $maResult = $maService->deny(
            (int)$id,
            $this->Authentication->getIdentity()->getIdentifier(),
            $this->request->getData("approver_notes"),
        );
        if (
            !$maResult->success
        ) {
            $this->Flash->error(
                __(
                    "The authorization approval could not be rejected. Please, try again.",
                ),
            );
        } else {
            $this->Flash->success(
                __("The authorization approval has been rejected."),
            );
        }

        return $this->redirect($this->referer());
    }
}