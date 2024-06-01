<?php

declare(strict_types=1);

namespace App\Controller;

/**
 * AuthorizationApprovals Controller
 *
 * @property \App\Model\Table\AuthorizationApprovalsTable $AuthorizationApprovals
 */

use App\Services\ActivityAuthorizations\AuthorizationServiceInterface;

class AuthorizationsController extends AppController
{
    public function revoke(AuthorizationServiceInterface $maService, $id = null)
    {
        $this->request->allowMethod(["post"]);
        if ($id == null) {
            $id = (int)$this->request->getData("id");
        }

        $authorization = $this->Authorizations->get($id);
        $this->Authorization->authorize($authorization);

        $revokedReason = $this->request->getData("revoked_reason");
        $revokerId = $this->Authentication->getIdentity()->getIdentifier();
        if (!$maService->revoke($id, $revokerId, $revokedReason)) {
            $this->Flash->error(
                __("The authorization revocation could not be processed"),
            );

            return $this->redirect($this->referer());
        }
        $this->Flash->success(
            __("The authorization revocation has been processed"),
        );

        return $this->redirect($this->referer());
    }

    public function add(AuthorizationServiceInterface $maService)
    {
        $this->request->allowMethod(["post"]);
        $memberId = $this->request->getData("member_id");

        $authorization = $this->Authorizations->newEmptyEntity();
        $authorization->member_id = $memberId;
        $this->Authorization->authorize($authorization);

        $authorization_type_id = $this->request->getData("authorization_type");
        $approverId = $this->request->getData("approver_id");
        $is_renewal = false;
        if (
            $maService->request(
                (int) $memberId,
                (int) $authorization_type_id,
                (int) $approverId,
                (bool) $is_renewal,
            )
        ) {
            $this->Flash->success(__("The Authorization has been requested."));

            return $this->redirect($this->referer());
        }
        $this->Flash->error(
            __("The Authorization could not be requested. Please, try again."),
        );

        return $this->redirect($this->referer());
    }

    public function renew(AuthorizationServiceInterface $maService)
    {
        $this->request->allowMethod(["post"]);
        $memberId = $this->request->getData("member_id");

        $authorization = $this->Authorizations->newEmptyEntity();
        $authorization->member_id = $memberId;
        $this->Authorization->authorize($authorization);

        $authorization_type_id = $this->request->getData("authorization_type");
        $approverId = $this->request->getData("approver_id");
        $is_renewal = true;
        if (
            $maService->request(
                (int) $memberId,
                (int) $authorization_type_id,
                (int) $approverId,
                (bool) $is_renewal,
            )
        ) {
            $this->Flash->success(__("The Authorization has been requested."));

            return $this->redirect($this->referer());
        }
        $this->Flash->error(
            __("The Authorization could not be requested. Please, try again."),
        );

        return $this->redirect($this->referer());
    }
}