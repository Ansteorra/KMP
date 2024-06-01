<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\DateTime;

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
    public function add()
    {
        $roleid = $this->request->getData("role_id");
        $memberid = $this->request->getData("member_id");
        $this->request->allowMethod(["post"]);
        $oldMemberRoles = $this->MemberRoles->find("all")->where([
            "role_id" => $roleid,
            "member_id" => $memberid,
            "expires_on IS" => null,
        ]);
        // begin transaction
        $this->MemberRoles->getConnection()->begin();
        foreach ($oldMemberRoles as $oldMemberRole) {
            $oldMemberRole->expires_on = DateTime::now();
            $this->MemberRoles->save($oldMemberRole);
        }
        $memberRole = $this->MemberRoles->newEmptyEntity();
        $memberRole->role_id = $roleid;
        $memberRole->member_id = $memberid;
        $memberRole->started_on = DateTime::now();
        $memberRole->approver_id = $this->Authentication
            ->getIdentity()
            ->get("id");
        if ($this->MemberRoles->save($memberRole)) {
            $this->Flash->success(__("The Member role has been saved."));
            $this->MemberRoles->getConnection()->commit();
        } else {
            $this->Flash->error(
                __("The Member role could not be saved. Please, try again."),
            );
            $this->MemberRoles->getConnection()->rollback();
        }

        return $this->redirect($this->referer());
    }

    public function deactivate($id = null)
    {
        $this->request->allowMethod(["post"]);
        $memberRole = $this->MemberRoles->get($id);
        $memberRole->expires_on = DateTime::now();
        if ($this->MemberRoles->save($memberRole)) {
            $this->Flash->success(__("The Member role has been deactivated."));
        } else {
            $this->Flash->error(
                __(
                    "The Member role could not be deactivated. Please, try again.",
                ),
            );
        }

        return $this->redirect($this->referer());
    }
}
