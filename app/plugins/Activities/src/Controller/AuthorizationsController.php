<?php

declare(strict_types=1);

namespace Activities\Controller;

/**
 * AuthorizationApprovals Controller
 *
 * @property \App\Model\Table\AuthorizationApprovalsTable $AuthorizationApprovals
 */

use Activities\Services\AuthorizationManagerInterface;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use Cake\ORM\Query\SelectQuery;
use Activities\Model\Entity\Authorization;

class AuthorizationsController extends AppController
{

    public function revoke(AuthorizationManagerInterface $maService, $id = null)
    {
        $this->request->allowMethod(["post"]);
        if ($id == null) {
            $id = (int)$this->request->getData("id");
        }

        $authorization = $this->Authorizations->get($id);
        if (!$authorization) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorization);

        $revokedReason = $this->request->getData("revoked_reason");
        $revokerId = $this->Authentication->getIdentity()->getIdentifier();
        $maResult = $maService->revoke($id, $revokerId, $revokedReason);
        if (!$maResult->success) {
            $this->Flash->error(
                __($maResult->reason),
            );

            return $this->redirect($this->referer());
        }
        $this->Flash->success(
            __("The authorization revocation has been processed"),
        );

        return $this->redirect($this->referer());
    }

    public function add(AuthorizationManagerInterface $maService)
    {
        $this->request->allowMethod(["post"]);
        $memberId = $this->request->getData("member_id");

        $authorization = $this->Authorizations->newEmptyEntity();
        $authorization->member_id = $memberId;
        $this->Authorization->authorize($authorization);

        $activity_id = $this->request->getData("activity");
        $approverId = $this->request->getData("approver_id");
        $is_renewal = false;
        $maResult = $maService->request(
            (int) $memberId,
            (int) $activity_id,
            (int) $approverId,
            (bool) $is_renewal,
        );
        if ($maResult->success) {
            $this->Flash->success(__("The Authorization has been requested."));

            return $this->redirect($this->referer());
        }
        $this->Flash->error(
            __($maResult->reason),
        );

        return $this->redirect($this->referer());
    }

    public function renew(AuthorizationManagerInterface $maService)
    {
        $this->request->allowMethod(["post"]);
        $memberId = $this->request->getData("member_id");

        $authorization = $this->Authorizations->newEmptyEntity();
        $authorization->member_id = $memberId;
        if (!$authorization) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorization);

        $activity_id = $this->request->getData("activity");
        $approverId = $this->request->getData("approver_id");
        $is_renewal = true;
        $maResult = $maService->request(
            (int) $memberId,
            (int) $activity_id,
            (int) $approverId,
            (bool) $is_renewal,
        );
        if (
            $maResult->success
        ) {
            $this->Flash->success(__("The Authorization has been requested."));

            return $this->redirect($this->referer());
        }
        $this->Flash->error(
            __($maResult->reason),
        );

        return $this->redirect($this->referer());
    }

    public function memberAuthorizations($state, $id)
    {
        if ($state != 'current' && $state == 'pending' && $state == 'previous') {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $member = $this->Authorizations->Members->find()
            ->where(["id" => $id])
            ->select("id")->first();
        if (!$member) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($member, 'view');
        $auths = $this->Authorizations->find();
        switch ($state) {
            case 'current':
                $auths = $this->addConditionsForMembers($this->Authorizations->find('current')->where(['member_id' => $id]));
                break;
            case 'pending':
                $auths = $this->addConditionsForMembers($this->Authorizations->find('pending')->where(['member_id' => $id]));
                break;
            case 'previous':
                $auths = $this->addConditionsForMembers($this->Authorizations->find('previous')->where(['member_id' => $id]));
                break;
        }
        $authorizations = $this->paginate($auths);
        $this->set(compact('authorizations', 'member', 'state'));
    }

    public function activityAuthorizations($state, $id)
    {
        if ($state != 'current' && $state == 'pending' && $state == 'previous') {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $activity = $this->Authorizations->Activities->find()
            ->where(["id" => $id])
            ->select("id")->first();
        if (!$activity) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($activity, 'view');
        $auths = $this->Authorizations->find();
        switch ($state) {
            case 'current':
                $auths = $this->addConditionsForActivities($this->Authorizations->find('current')->where(['activity_id' => $id]));
                break;
            case 'pending':
                $auths = $this->addConditionsForActivities($this->Authorizations->find('pending')->where(['activity_id' => $id]));
                break;
            case 'previous':
                $auths = $this->addConditionsForActivities($this->Authorizations->find('previous')->where(['activity_id' => $id]));
                break;
        }
        $authorizations = $this->paginate($auths);
        $this->set(compact('authorizations', 'activity', 'state'));
    }

    protected function addConditionsForMembers(SelectQuery $q)
    {

        $rejectFragment = $q->func()->concat([
            "Authorizations.status" => 'identifier',
            ' - ',
            "RevokedBy.sca_name" => 'identifier',
            " on ",
            "expires_on" => 'identifier',
            " note: ",
            "revoked_reason" => 'identifier'
        ]);

        $revokeReasonCase = $q->newExpr()
            ->case()
            ->when(['Authorizations.status' => Authorization::DENIED_STATUS])
            ->then($rejectFragment)
            ->when(['Authorizations.status' => Authorization::REVOKED_STATUS])
            ->then($rejectFragment)
            ->when(['Authorizations.status' => Authorization::EXPIRED_STATUS])
            ->then("Authorization Expired")
            ->else("");
        return $q
            ->select([
                "id",
                "member_id",
                "activity_id",
                "Authorizations.status",
                "start_on",
                "expires_on",
                "revoked_reason" => $revokeReasonCase,
                "revoker_id",
            ])
            ->contain([
                "CurrentPendingApprovals" => function (SelectQuery $q) {
                    return $q->select(["Approvers.sca_name", "requested_on"])
                        ->contain("Approvers");
                },
                "Activities" => function (SelectQuery $q) {
                    return $q->select(["Activities.name", "Activities.id"]);
                },
                "RevokedBy" => function (SelectQuery $q) {
                    return $q->select(["RevokedBy.sca_name"]);
                }
            ]);
    }

    protected function addConditionsForActivities($q)
    {

        $rejectFragment = $q->func()->concat([
            "Authorizations.status" => 'identifier',
            ' - ',
            "RevokedBy.sca_name" => 'identifier',
            " on ",
            "expires_on" => 'identifier',
            " note: ",
            "revoked_reason" => 'identifier'
        ]);

        $revokeReasonCase = $q->newExpr()
            ->case()
            ->when(['Authorizations.status' => Authorization::DENIED_STATUS])
            ->then($rejectFragment)
            ->when(['Authorizations.status' => Authorization::REVOKED_STATUS])
            ->then($rejectFragment)
            ->when(['Authorizations.status' => Authorization::EXPIRED_STATUS])
            ->then("Authorization Expired")
            ->else("");
        return $q
            ->select([
                "id",
                "member_id",
                "activity_id",
                "Authorizations.status",
                "start_on",
                "expires_on",
                "revoked_reason" => $revokeReasonCase,
                "revoker_id",
                "Members.sca_name"
            ])
            ->contain([
                "CurrentPendingApprovals" => function (SelectQuery $q) {
                    return $q->select(["Approvers.sca_name", "requested_on"])
                        ->contain("Approvers");
                },
                "Members" => function (SelectQuery $q) {
                    return $q->select(["Members.id", "Members.sca_name"]);
                },
                "RevokedBy" => function (SelectQuery $q) {
                    return $q->select(["RevokedBy.sca_name"]);
                }
            ]);
    }
}