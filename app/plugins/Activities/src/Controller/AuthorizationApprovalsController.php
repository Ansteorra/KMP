<?php

declare(strict_types=1);

namespace Activities\Controller;

/**
 * AuthorizationApprovals Controller
 *
 * @property \App\Model\Table\AuthorizationApprovalsTable $AuthorizationApprovals
 */

use Activities\Services\AuthorizationManager\AuthorizationManagerInterface;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use Cake\Mailer\MailerAwareTrait;

class AuthorizationApprovalsController extends AppController
{
    use MailerAwareTrait;

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->AuthorizationApprovals->Approvers
            ->find()
            ->contain(["AuthorizationApprovals"])
            ->innerJoinWith("AuthorizationApprovals");
        // group by approver and count pending, approved, and denied
        $query
            ->select([
                "Approvers.id",
                "approver_name" => "Approvers.sca_name",
                "last_login" => "Approvers.last_login",
                "pending" => $query
                    ->func()
                    ->count(
                        "CASE WHEN AuthorizationApprovals.responded_on IS NULL THEN 1 END",
                    ),
                "approved" => $query
                    ->func()
                    ->count(
                        "CASE WHEN AuthorizationApprovals.approved = 1 THEN 1 END",
                    ),
                "denied" => $query
                    ->func()
                    ->count(
                        "CASE WHEN AuthorizationApprovals.approved = 0  && AuthorizationApprovals.responded_on IS NOT NULL THEN 1 END",
                    ),
            ])
            ->group("Approvers.id");
        $this->Authorization->authorize($query);
        $this->Authorization->applyScope($query);
        $authorizationApprovals = $this->paginate($query, [
            'order' => [
                'name' => 'asc',
            ]
        ]);
        $this->set(compact("authorizationApprovals"));
    }

    public function myQueue($token = null)
    {
        $member_id = $this->Authentication->getIdentity()->getIdentifier();
        $query = $this->getAuthorizationApprovalsQuery($member_id);
        if ($token) {
            $query = $query->where(["authorization_token" => $token]);
        }
        $this->Authorization->authorize($query);
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
        $this->Authorization->authorize($query);
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
                    return $q->select(["Members.sca_name"]);
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
        ActiveWindowManagerInterface $awService,
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
        if (!$maService->approve($awService, (int)$id, (int)$approverId, (int)$nextApproverId)) {
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
            ],
        );
        if (!$authorizationApproval) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorizationApproval);
        $previousApprovers = $this->AuthorizationApprovals
            ->find("list", [
                "keyField" => "approver_id",
                "valueField" => "approver_id",
            ])
            ->where([
                "authorization_id" => $authorizationApproval->authorization_id,
            ])
            ->select(["approver_id"])
            ->all()
            ->toList();
        $memberId = $this->Authentication->getIdentity()->getIdentifier();
        $previousApprovers[] = $memberId;
        $previousApprovers[] = $authorizationApproval->authorization->member_id;
        $query = $this->AuthorizationApprovals->Authorizations->Members->getCurrentActivityApprovers(
            $authorizationApproval->authorization->activity_id,
        );
        $query = $query
            ->where(["Members.id NOT IN " => $previousApprovers])
            ->orderBy(["Branches.name", "Members.sca_name"])
            ->select(["id", "sca_name", "Branches.name"])
            ->all();
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($query));

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
        if (
            !$maService->deny(
                $id,
                $this->Authentication->getIdentity()->getIdentifier(),
                $this->request->getData("approver_notes"),
            )
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
        $this->Flash->success(
            __("The authorization approval has been rejected."),
        );

        return $this->redirect($this->referer());
    }
}