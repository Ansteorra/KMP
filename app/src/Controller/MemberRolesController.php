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
        $this->Authorization->authorizeModel('index','deactivate');
    }
    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add($roleid, $particpantid)
    {
        $this->request->allowMethod(['post']);
        $MemberRole = $this->MemberRoles->newEmptyEntity();
        $MemberRole->role_id = $roleid;
        $MemberRole->Member_id = $particpantid;
        $MemberRole->started_on = DateTime::now();
        $MemberRole->authorized_by = $this->Authentication->getIdentity()->get('id');
        if ($this->MemberRoles->save($MemberRole)) {
            $this->Flash->success(__('The Member role has been saved.'));
        } else {
            $this->Flash->error(__('The Member role could not be saved. Please, try again.'));
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
