<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\DateTime;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Model\Entity\MemberRole;

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
        $newMemberRole->entity_type = "Direct Grant";
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

        if (!$awService->stop("MemberRoles", (int)$id, $this->Authentication->getIdentity()->get("id"), MemberRole::DEACTIVATED_STATUS, "", DateTime::now())) {
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

    public function roleMemberRoles($state, $id)
    {

        if ($state != 'current' && $state == 'upcoming' && $state == 'previous') {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $role = $this->MemberRoles->Roles->find()
            ->where(["id" => $id])
            ->select("id")->first();
        if (!$role) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($role, 'View');
        $members = $this->MemberRoles->find();
        switch ($state) {
            case 'current':
                $members = $this->addConditions($this->MemberRoles->find('current')->where(['role_id' => $id]));
                break;
            case 'upcoming':
                $members = $this->addConditions($this->MemberRoles->find('upcoming')->where(['role_id' => $id]));
                break;
            case 'previous':
                $members = $this->addConditions($this->MemberRoles->find('previous')->where(['role_id' => $id]));
                break;
        }
        $memberRoles = $this->paginate($members);
        $this->set(compact('memberRoles', 'role', 'state'));
    }
    protected function addConditions($query)
    {
        return $query
            ->select(['id', 'role_id', 'member_id', 'approver_id', 'entity_type', 'entity_id', 'start_on', 'expires_on', 'revoker_id'])
            ->contain([
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'ApprovedBy' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'RevokedBy' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
            ]);
    }
}