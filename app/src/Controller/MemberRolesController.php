<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\DateTime;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;

/**
 * MemberRoles Controller
 *
 * @property \App\Model\Table\MemberRolesTable $memberRoles
 */
class MemberRolesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("index", "deactivate", "add");
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add(ActiveWindowManagerInterface $awService)
    {
        $roleid = $this->request->getData("role_id");
        $memberid = $this->request->getData("member_id");
        $this->request->allowMethod(["post"]);
        // begin transaction
        $this->MemberRoles->getConnection()->begin();
        $newMemberRole = $this->MemberRoles->newEmptyEntity();
        $newMemberRole->role_id = $roleid;
        $newMemberRole->member_id = $memberid;
        $newMemberRole->approver_id = $this->Authentication->getIdentity()->get("id");
        $newMemberRole->granting_model = "Direct Grant";
        $newMemberRole->start(DateTime::now());
        if (!$this->MemberRoles->save($newMemberRole)) {
            $this->Flash->error(
                __("The Member role could not be saved. Please, try again."),
            );
            $this->MemberRoles->getConnection()->rollback();
            return $this->redirect($this->referer());
        }
        if (!$awService->start("MemberRoles", $newMemberRole->id, $newMemberRole->approver_id, DateTime::now())) {
            $this->Flash->error(
                __("The Member role could not be saved. Please, try again."),
            );
            $this->MemberRoles->getConnection()->rollback();
            return $this->redirect($this->referer());
        }

        $this->Flash->success(__("The Member role has been saved."));
        $this->MemberRoles->getConnection()->commit();
        return $this->redirect($this->referer());
    }

    public function deactivate(ActiveWindowManagerInterface $awService, $id = null)
    {
        $this->request->allowMethod(["post"]);
        if (!$id) {
            $id = $this->request->getData("id");
        }
        $this->MemberRoles->getConnection()->begin();

        if (!$awService->stop("MemberRoles", (int)$id, $this->Authentication->getIdentity()->get("id"), "deactivated", "", DateTime::now())) {
            $this->Flash->error(
                __(
                    "The Member role could not be deactivated. Please, try again.",
                ),
            );
            $this->MemberRoles->getConnection()->rollback();
            return $this->redirect($this->referer());
        }

        $this->Flash->success(__("The Member role has been deactivated."));
        $this->MemberRoles->getConnection()->commit();
        return $this->redirect($this->referer());
    }
}
