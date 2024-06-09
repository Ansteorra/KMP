<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\DateTime;

use Cake\I18n\Date;

/**
 * Offices Controller
 *
 * @property \App\Model\Table\OfficesTable $Offices
 */
class OfficersController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("add");
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $officer = $this->Officers->newEmptyEntity();
        $this->Authorization->authorize($officer);
        if ($this->request->is('post')) {
            //begin transaction
            $this->Officers->getConnection()->begin();
            $member_id = $this->request->getData('member_id');
            $office_id = $this->request->getData('office_id');
            $branch_id = $this->request->getData('branch_id');
            $start_on = new DateTime($this->request->getData('start_on'));
            $office = $this->Officers->Offices->get($office_id);
            $end_on = $start_on->addYears($office->term_length);

            $officer->member_id = $member_id;
            $officer->office_id = $office_id;
            $officer->branch_id = $branch_id;
            $officer->start_on = $start_on;
            $officer->expires_on = $end_on;
            $officer->approver_id = $this->Authentication->getIdentity()->getIdentifier();
            $officer->approval_date = DateTime::now();

            if ($office->only_one_per_branch) {
                //if there are others of the same office in the same branch with a end date before the start date of the new officer end them the day before the new start date
                try {
                    //get all the previously granted roles
                    $previousRoles = $this->Officers->find()
                        ->select(["granted_member_role_id"])
                        ->where([
                            'office_id' => $office_id,
                            'branch_id' => $branch_id,
                            'expires_on <=' => $start_on
                        ])
                        ->toArray();
                    if (count($previousRoles) > 0) {
                        $previousRolesArray = array_map(function ($role) {
                            return $role->granted_member_role_id;
                        }, $previousRoles);
                        $this->Officers->Members->MemberRoles->updateAll(
                            ['expires_on' => $start_on->subDays(1)],
                            ['id IN' => $previousRolesArray]
                        );
                    }
                    $this->Officers->updateAll(
                        ['expires_on' => $start_on->subDays(1)],
                        ['office_id' => $office_id, 'branch_id' => $branch_id, 'expires_on >=' => $start_on]
                    );
                } catch (\Exception $e) {
                    $this->Officers->getConnection()->rollback();
                    $this->Flash->error(__('The officer could not be saved. Please, try again.'));
                    $this->redirect($this->referer());
                }
            }
            if ($officer->grants_role) {
                $memberRole = $this->Officers->Members->MemberRoles->newEmptyEntity();
                $memberRole->member_id = $member_id;
                $memberRole->role_id = $office->role_id;
                $memberRole->start_on = $start_on;
                $memberRole->expires_on = $end_on;
                $memberRole->approver_id = $this->Authentication->getIdentity()->getIdentifier();
                if (!$this->Officers->Members->MemberRoles->save($memberRole)) {
                    $this->Officers->getConnection()->rollback();
                    $this->Flash->error(__('The officer could not be saved. Please, try again.'));
                    $this->redirect($this->referer());
                }
                $officer->granted_member_role_id = $memberRole->id;
            }
            if (!$this->Officers->save($officer)) {
                $this->Officers->getConnection()->rollback();
                $this->Flash->error(__('The officer could not be saved. Please, try again.'));
                $this->redirect($this->referer());
            }
            //commit transaction
            $this->Officers->getConnection()->commit();
            $this->Flash->success(__('The officer has been saved.'));
            $this->redirect($this->referer());
        }
    }

    public function release()
    {
        $officer = $this->Officers->get($this->request->getData('id'));
        $this->Authorization->authorize($officer);
        if ($this->request->is('post')) {
            $releaseReason = $this->request->getData('revoked_reason');
            $officer->revoked_reason = $releaseReason;
            $officer->revoker_id = $this->Authentication->getIdentity()->getIdentifier();
            $officer->expires_on = Date::now()->subDays(1);
            $officer->status = 'released';
            if ($officer->start_on > Date::now()) {
                $officer->start_on = $officer->expires_on;
                $officer->status = 'cancelled';
            }
            //begin transaction
            $this->Officers->getConnection()->begin();
            if (!$this->Officers->save($officer)) {
                $this->Officers->getConnection()->rollback();
                $this->Flash->error(__('The officer could not be released. Please, try again.'));
                $this->redirect($this->referer());
            }
            if ($officer->granted_member_role_id) {
                $memberRole = $this->Officers->Members->MemberRoles->get($officer->granted_member_role_id);
                $memberRole->expires_on = DateTime::now()->subSeconds(1);
                if (!$this->Officers->Members->MemberRoles->save($memberRole)) {
                    $this->Officers->getConnection()->rollback();
                    $this->Flash->error(__('The officer could not be released. Please, try again.'));
                    $this->redirect($this->referer());
                }
            }
            //commit transaction
            $this->Officers->getConnection()->commit();
            $this->Flash->success(__('The officer has been released.'));
            $this->redirect($this->referer());
        }
    }
}
