<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\DateTime;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WarrantManager\WarrantRequest;
use App\Model\Entity\MemberRole;
use App\Model\Entity\Permission;

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
    public function add(ActiveWindowManagerInterface $awService, WarrantManagerInterface $warrantService)

    {
        $roleid = $this->request->getData("role_id");
        $memberid = $this->request->getData("member_id");
        $role = $this->MemberRoles->Roles->find()
            ->where(["id" => $roleid])
            ->select("name")->first();
        if (!$role) {
            $this->Flash->error(__("The role could not be found."));
            return $this->redirect($this->referer());
        }
        $member = $this->MemberRoles->Members->find()
            ->where(["id" => $memberid])
            ->select("sca_name")->first();
        if (!$member) {
            $this->Flash->error(__("The member could not be found."));
            return $this->redirect($this->referer());
        }
        $permissions = $this->MemberRoles->Roles->Permissions->find()
            ->join([
                'table' => 'roles_permissions',
                'alias' => 'rp',
                'type' => 'INNER',
                'conditions' => 'rp.permission_id = Permissions.id',
            ])
            ->where(["rp.role_id" => $roleid, 'scoping_rule <>' => Permission::SCOPE_GLOBAL])
            ->count();
        $branch_id = null;
        if ($permissions > 0) {
            $branch_id = $this->request->getData("branch_id");
            $branch = $this->MemberRoles->Branches->find()
                ->where(["id" => $branch_id])
                ->select("name")->first();
            if (!$branch) {
                $this->Flash->error(__("The branch could not be found."));
                return $this->redirect($this->referer());
            }
        }
        $this->request->allowMethod(["post"]);
        // begin transaction
        $this->MemberRoles->getConnection()->begin();
        $newMemberRole = $this->MemberRoles->newEmptyEntity();
        $newMemberRole->role_id = $roleid;
        $newMemberRole->member_id = $memberid;
        $newMemberRole->approver_id = $this->Authentication->getIdentity()->get("id");
        $newMemberRole->entity_type = "Direct Grant";
        $newMemberRole->branch_id = $branch_id;
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
        #if the member role includes a permission that requires a warrant, then 
        #we need to start the warrant process
        $permissions = $this->MemberRoles->Roles->Permissions->find()
            ->join([
                'table' => 'roles_permissions',
                'alias' => 'rp',
                'type' => 'INNER',
                'conditions' => 'rp.permission_id = Permissions.id',
            ])
            ->where(["rp.role_id" => $roleid, 'requires_warrant' => true])
            ->count();
        $warrantRequired = $permissions > 0;
        if ($warrantRequired) {
            $warrant = $warrantService->request(
                "Direct Grant:" . $role->name . " for " . $member->sca_name,

                "Warrant for a direct grant of a Role",
                [
                    new WarrantRequest(
                        "Direct Grant:" . $role->name . " for " . $member->sca_name,
                        "Direct Grant",
                        -1,
                        $this->Authentication->getIdentity()->get("id"),
                        toInt($memberid),
                        DateTime::now(),
                        null,
                        $newMemberRole->id,
                    ),
                ],
            );
            if (!$warrant->success) {
                $this->Flash->error(
                    __($warrant->reason),
                );
                $this->MemberRoles->getConnection()->rollback();
                return $this->redirect($this->referer());
            }
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