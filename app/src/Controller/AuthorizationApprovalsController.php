<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * AuthorizationApprovals Controller
 *
 * @property \App\Model\Table\AuthorizationApprovalsTable $AuthorizationApprovals
 */

use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;

class AuthorizationApprovalsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index($member_id = null)
    {
        $query = $this->AuthorizationApprovals->find()
            ->contain(['Authorizations.Members', 'Authorizations.AuthorizationTypes', 'Approver']);
        if ($member_id) {
            $query->where(['approver_id' => $member_id])
                ->where(['responded_on IS ' => null]);
        }
        $this->Authorization->authorize($query);
        $this->Authorization->applyScope($query);
        $authorizationApprovals = $this->paginate($query);
        $this->set(compact('authorizationApprovals'));
    }

    public function myQueue()
    {
        $member_id = $this->Authentication->getIdentity()->getIdentifier();
        $query = $this->AuthorizationApprovals->find()
            ->contain(['Authorizations.Members', 'Authorizations.AuthorizationTypes', 'Approver']);

        $query->where(['approver_id' => $member_id]);
        $this->Authorization->authorize($query);
        $this->Authorization->applyScope($query);
        $authorizationApprovals = $query;
        $this->set(compact('authorizationApprovals'));
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
        $authorizationApproval = $this->AuthorizationApprovals->get($id, contain: ['Authorizations.AuthorizationTypes', 'Authorizations.AuthorizationApprovals']);

        $this->Authorization->authorize($authorizationApproval);
        $this->set(compact('authorizationApproval'));
    }

    /**
     * Approve method
     *
     * @param string|null $id Authorization Approval id.
     * @return \Cake\Http\Response|null|void Redirects on successful approval, renders view otherwise.
     */
    public function approve($id = null)
    {
        $this->request->allowMethod(['post']);
        $authorizationApproval = $this->AuthorizationApprovals->get($id, contain: ['Authorizations.AuthorizationTypes']);
        $this->Authorization->authorize($authorizationApproval);
        $this->AuthorizationApprovals->getConnection()->begin();
        //Set the approval to approved
        $authorizationApproval->responded_on = DateTime::now();
        $authorizationApproval->approved = true;
        //Save the approval
        if (!$this->AuthorizationApprovals->save($authorizationApproval)) {
            $this->AuthorizationApprovals->getConnection()->rollback();
            $this->Flash->error(__('The authorization approval could not be approved. Please, try again.'));
            return $this->redirect($this->referer());
        }
        //If the authorization requires multiple approvals, check if all approvals have been accepted
        $approve_auth = false;
        if ($authorizationApproval->authorizations->authorization_type->num_required_authorizors > 1) {
            $accepted_approvals = $this->AuthorizationApprovals->find()
                ->where(['authorization_id' => $authorizationApproval->authorization_id, 'approved' => true])
                ->count();
            if ($accepted_approvals >= $authorizationApproval->authorizations->authorization_type->num_required_authorizors) {
                $approve_auth = true;
            }else{
                $authorizationApproval->authorizations->status = 'pending';
                if (!$this->AuthorizationApprovals->Authorization->save($authorizationApproval->authorizations)) {
                    $this->AuthorizationApprovals->getConnection()->rollback();
                    $this->Flash->error(__('The authorization approval could not be approved. Please, try again.'));
                    return $this->redirect($this->referer());
                }
            }
        } else {
            $approve_auth = true;
        }
        if ($approve_auth) {
            //if there are previous authorizations for the same authorization_type that have not ended, end them now
            $previous_authorizations = TableRegistry::getTableLocator()->get('Authorizations')->find()
                ->where([
                    'member_id' => $authorizationApproval->authorizations->member_id,
                    'authorization_type_id' => $authorizationApproval->authorizations->authorization_type_id,
                    'status' => 'approved'
                ])
                ->where(['expires_on >' => DateTime::now()])
                ->all();
            foreach ($previous_authorizations as $previous_authorization) {
                $previous_authorization->expires_on = DateTime::now()->subDays(1);
                if (!$this->AuthorizationApprovals->Authorization->save($previous_authorization)) {
                    $this->AuthorizationApprovals->getConnection()->rollback();
                    $this->Flash->error(__('The authorization approval could not be approved. Please, try again.'));
                    return $this->redirect($this->referer());
                }
            }
            //Now that the previous auths are closed we can approve and set the time of the new auth.
            $authorizationApproval->authorizations->status = 'approved';
            $authorizationApproval->authorizations->start_on = DateTime::now();
            $authorizationApproval->authorizations->expires_on = DateTime::now()->addYears($authorizationApproval->authorizations->authorization_type->length);
            if (!$this->AuthorizationApprovals->Authorization->save($authorizationApproval->authorization)) {
                $this->AuthorizationApprovals->getConnection()->rollback();
                $this->Flash->error(__('The authorization approval could not be approved. Please, try again.'));
                return $this->redirect($this->referer());
            }
            $this->AuthorizationApprovals->getConnection()->commit();
            if ($approve_auth) {
                //TODO: Notify member of their new authorization via email
            }else{
                //TODO: Notify the approver they need to select the next approver
            }
        }
        $this->Flash->success(__('The authorization approval has been processed'));
        return $this->redirect($this->referer());
    }


    public function deny($id = null)
    {
        $this->request->allowMethod(['post']);
        $authorizationApproval = $this->AuthorizationApprovals->get($id, contain: ['Authorization', 'Approver']);
        $this->Authorization->authorize($authorizationApproval);
        $authorizationApproval->responded_on = DateTime::now();
        $authorizationApproval->approved = false;
        $authorizationApproval->approver_notes = $this->request->getData('approver_notes');
        $authorizationApproval->authorizations->status = 'rejected';
        if ($this->AuthorizationApprovals->save($authorizationApproval) && $this->AuthorizationApprovals->Authorization->save($authorizationApproval->authorization)) {
            $this->Flash->success(__('The authorization approval has been rejected.'));
        } else {
            $this->Flash->error(__('The authorization approval could not be rejected. Please, try again.'));
        }

        return $this->redirect($this->referer());
    }
}
