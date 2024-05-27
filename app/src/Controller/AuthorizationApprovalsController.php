<?php declare(strict_types=1);

namespace App\Controller;

/**
 * AuthorizationApprovals Controller
 *
 * @property \App\Model\Table\AuthorizationApprovalsTable $AuthorizationApprovals
 */

use App\KMP\PermissionsLoader;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

class AuthorizationApprovalsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this
            ->AuthorizationApprovals
            ->Approvers
            ->find()
            ->contain(['AuthorizationApprovals'])
            ->innerJoinWith('AuthorizationApprovals');
        // group by approver and count pending, approved, and denied
        $query
            ->select([
                'Approvers.id',
                'approver_name' => 'Approvers.sca_name',
                'last_login' => 'Approvers.last_login',
                'pending' => $query->func()->count('CASE WHEN AuthorizationApprovals.responded_on IS NULL THEN 1 END'),
                'approved' => $query->func()->count('CASE WHEN AuthorizationApprovals.approved = 1 THEN 1 END'),
                'denied' => $query->func()->count('CASE WHEN AuthorizationApprovals.approved = 0 THEN 1 END')
            ])
            ->group('Approvers.id');
        $this->Authorization->authorize($query);
        $this->Authorization->applyScope($query);
        $authorizationApprovals = $this->paginate($query);
        $this->set(compact('authorizationApprovals'));
    }

    public function myQueue()
    {
        $member_id = $this->Authentication->getIdentity()->getIdentifier();
        $query = $this->getAuthorizationApprovalsQuery($member_id);
        $this->Authorization->authorize($query);
        $this->Authorization->applyScope($query);
        $authorizationApprovals = $query->all();
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
        $query = $this->getAuthorizationApprovalsQuery($id);
        $this->Authorization->authorize($query);
        $this->Authorization->applyScope($query);
        $authorizationApprovals = $query->all();
        $this->set(compact('authorizationApprovals'));
    }

    protected function getAuthorizationApprovalsQuery($member_id)
    {
        $query = $this
            ->AuthorizationApprovals
            ->find()
            ->contain([
                'Authorizations' => function ($q) {
                    return $q->select(['Authorizations.status', 'Authorizations.approval_count']);
                },
                'Authorizations.Members' => function ($q) {
                    return $q->select(['Members.sca_name']);
                },
                'Authorizations.AuthorizationTypes' => function ($q) {
                    return $q->select(['AuthorizationTypes.name', 'AuthorizationTypes.num_required_authorizors']);
                },
                'Approvers' => function ($q) {
                    return $q->select(['Approvers.sca_name']);
                }
            ])
            ->where(['approver_id' => $member_id]);

        return $query;
    }

    /**
     * Approve method
     *
     * @param string|null $id Authorization Approval id.
     * @return \Cake\Http\Response|null|void Redirects on successful approval, renders view otherwise.
     */
    public function approve($id = null)
    {
        if ($id == null) {
            $id = $this->request->getData('id');
        }
        $this->request->allowMethod(['post']);

        $authorizationApproval = $this->AuthorizationApprovals->get($id, contain: ['Authorizations.AuthorizationTypes']);
        $this->Authorization->authorize($authorizationApproval);
        $this->AuthorizationApprovals->getConnection()->begin();

        // Set the approval to approved
        $authorizationApproval->responded_on = DateTime::now();
        $authorizationApproval->approved = true;

        // Save the approval
        if (!$this->AuthorizationApprovals->save($authorizationApproval)) {
            $this->AuthorizationApprovals->getConnection()->rollback();
            $this->Flash->error(__('The authorization approval could not be approved. Please, try again.'));
            return $this->redirect($this->referer());
        }

        // If the authorization requires multiple approvals, check if all approvals have been accepted
        if ($authorizationApproval->authorization->authorization_type->num_required_authorizors > 1) {
            $accepted_approvals = $this
                ->AuthorizationApprovals
                ->find()
                ->where(['authorization_id' => $authorizationApproval->authorization_id, 'approved' => true])
                ->count();

            if ($accepted_approvals < $authorizationApproval->authorization->authorization_type->num_required_authorizors) {
                $next_approver = $this->request->getData('approver_id');
                if ($next_approver == null) {
                    $this->AuthorizationApprovals->getConnection()->rollback();
                    $this->Flash->error(__('The authorization approval could not be approved. Please, try again.'));
                    return $this->redirect($this->referer());
                }
                $authorizationApproval->authorization->status = 'pending';
                $authorizationApproval->authorization->approval_count = $authorizationApproval->authorization->approval_count + 1;
                if (!$this->AuthorizationApprovals->Authorizations->save($authorizationApproval->authorization)) {
                    $this->AuthorizationApprovals->getConnection()->rollback();
                    $this->Flash->error(__('The authorization approval could not be approved. Please, try again.'));
                    return $this->redirect($this->referer());
                }

                $nextApproval = $this->AuthorizationApprovals->newEmptyEntity();
                $nextApproval->authorization_id = $authorizationApproval->authorization_id;
                $nextApproval->approver_id = $next_approver;
                $nextApproval->requested_on = DateTime::now();
                $nextApproval->authorization_token = PermissionsLoader::generateToken();
                if (!$this->AuthorizationApprovals->save($nextApproval)) {
                    $this->AuthorizationApprovals->getConnection()->rollback();
                    $this->Flash->error(__('The authorization approval could not be approved. Please, try again.'));
                    return $this->redirect($this->referer());
                }

                // TODO: Notify the next approver
                $this->AuthorizationApprovals->getConnection()->commit();
                $this->Flash->success(__('The authorization approval has been processed'));
                return $this->redirect($this->referer());
            }
        }
        // because we are using gateed checks we only get here if the authorization is approveable.
        // If there are previous authorizations for the same authorization_type that have not ended, end them now
        $previous_authorizations = TableRegistry::getTableLocator()
            ->get('Authorizations')
            ->find()
            ->where([
                'member_id' => $authorizationApproval->authorization->member_id,
                'authorization_type_id' => $authorizationApproval->authorization->authorization_type_id,
                'status' => 'approved'
            ])
            ->where(['expires_on >' => DateTime::now()])
            ->all();

        foreach ($previous_authorizations as $previous_authorization) {
            $previous_authorization->expires_on = DateTime::now()->subDays(1);

            if (!$this->AuthorizationApprovals->Authorizations->save($previous_authorization)) {
                $this->AuthorizationApprovals->getConnection()->rollback();
                $this->Flash->error(__('The authorization approval could not be approved. Please, try again.'));
                return $this->redirect($this->referer());
            }
        }

        // Now that the previous auths are closed we can approve and set the time of the new auth.
        $authorizationApproval->authorization->status = 'approved';
        $authorizationApproval->authorization->approval_count = $authorizationApproval->authorization->approval_count + 1;
        $authorizationApproval->authorization->start_on = DateTime::now();
        $authorizationApproval->authorization->expires_on = DateTime::now()->addYears($authorizationApproval->authorization->authorization_type->length);

        if (!$this->AuthorizationApprovals->Authorizations->save($authorizationApproval->authorization)) {
            $this->AuthorizationApprovals->getConnection()->rollback();
            $this->Flash->error(__('The authorization approval could not be approved. Please, try again.'));
            return $this->redirect($this->referer());
        }

        $this->AuthorizationApprovals->getConnection()->commit();

        // TODO: Notify member of their new authorization via email

        $this->Flash->success(__('The authorization approval has been processed'));
        return $this->redirect($this->referer());
    }

    public function availableApproversList($id)
    {
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Ajax');
        $authorizationApproval = $this->AuthorizationApprovals->get($id, contain: [
            'Authorizations' => function ($q) {
                return $q->select(['Authorizations.authorization_type_id']);
            }
        ]);
        $this->Authorization->authorize($authorizationApproval);
        $previousApprovers = $this
            ->AuthorizationApprovals
            ->find('list', ['keyField' => 'approver_id', 'valueField' => 'approver_id'])
            ->where(['authorization_id' => $authorizationApproval->authorization_id])
            ->select(['approver_id'])
            ->all()
            ->toList();
        $member_id = $this->Authentication->getIdentity()->getIdentifier();
        $previousApprovers[] = $member_id;
        $query = $this->AuthorizationApprovals->Authorizations->Members->getCurrentAuthorizationTypeApprovers($authorizationApproval->authorization->authorization_type_id);
        $query = $query
            ->where(['Members.id NOT IN ' => $previousApprovers])
            ->order(['Branches.name', 'Members.sca_name'])
            ->select(['id', 'sca_name', 'Branches.name'])
            ->all();
        $this->response = $this
            ->response
            ->withType('application/json')
            ->withStringBody(json_encode($query));
        return $this->response;
    }

    public function deny($id = null)
    {
        $this->request->allowMethod(['post']);
        if ($id == null) {
            $id = $this->request->getData('id');
        }
        $authorizationApproval = $this->AuthorizationApprovals->get($id, contain: ['Authorizations']);
        $this->Authorization->authorize($authorizationApproval);
        $authorizationApproval->responded_on = DateTime::now();
        $authorizationApproval->approved = false;
        $authorizationApproval->approver_notes = $this->request->getData('approver_notes');
        $authorizationApproval->authorization->status = 'rejected';
        $this->AuthorizationApprovals->getConnection()->begin();
        if ($this->AuthorizationApprovals->save($authorizationApproval) && $this->AuthorizationApprovals->Authorizations->save($authorizationApproval->authorization)) {
            $this->AuthorizationApprovals->getConnection()->commit();
            $this->Flash->success(__('The authorization approval has been rejected.'));
        } else {
            $this->AuthorizationApprovals->getConnection()->rollback();
            $this->Flash->error(__('The authorization approval could not be rejected. Please, try again.'));
        }

        return $this->redirect($this->referer());
    }
}
