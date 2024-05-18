<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\DateTime;
/**
 * MemberRoles Controller
 *
 * @property \App\Model\Table\MemberRolesTable $MemberRoles
 */
class MemberRolesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index','deactivate','add');
    }
    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $roleid = $this->request->getData('role_id');
        $memberid = $this->request->getData('member_id');
        $this->request->allowMethod(['post']);
        $oldMemberRoles = $this->MemberRoles->find('all')
            ->where(['role_id' => $roleid, 'member_id' => $memberid, 'ended_on IS' => null]);
        //begin transaction
        $this->MemberRoles->getConnection()->begin();
        foreach ($oldMemberRoles as $oldMemberRole) {
            $oldMemberRole->ended_on = DateTime::now();
            $this->MemberRoles->save($oldMemberRole);
        }
        $MemberRole = $this->MemberRoles->newEmptyEntity();
        $MemberRole->role_id = $roleid;
        $MemberRole->Member_id = $memberid;
        $MemberRole->started_on = DateTime::now();
        $MemberRole->authorized_by_id = $this->Authentication->getIdentity()->get('id');
        if ($this->MemberRoles->save($MemberRole)) {
            $this->Flash->success(__('The Member role has been saved.'));
            $this->MemberRoles->getConnection()->commit();
        } else {
            $this->Flash->error(__('The Member role could not be saved. Please, try again.'));
            $this->MemberRoles->getConnection()->rollback();
        }
        return $this->redirect($this->referer());

    }

    public function deactivate($id = null)
    {
        $this->request->allowMethod(['post']);
        $MemberRole = $this->MemberRoles->get($id);
        $MemberRole->ended_on = DateTime::now();
        if ($this->MemberRoles->save($MemberRole)) {
            $this->Flash->success(__('The Member role has been deactivated.'));
        } else {
            $this->Flash->error(__('The Member role could not be deactivated. Please, try again.'));
        }
        return $this->redirect($this->referer());
    }
}
