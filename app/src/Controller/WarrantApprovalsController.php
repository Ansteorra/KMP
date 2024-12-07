<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * WarrantApprovals Controller
 *
 * @property \App\Model\Table\WarrantApprovalsTable $WarrantApprovals
 * @property \Authorization\Controller\Component\AuthorizationComponent $Authorization
 */
class WarrantApprovalsController extends AppController
{
    /**
     * Initialize controller
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Authorization.Authorization');
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->WarrantApprovals->find()
            ->contain(['WarrantApprovalSets', 'Members']);
        $query = $this->Authorization->applyScope($query);
        $warrantApprovals = $this->paginate($query);

        $this->set(compact('warrantApprovals'));
    }

    /**
     * View method
     *
     * @param string|null $id Warrant Approval id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $warrantApproval = $this->WarrantApprovals->get($id, contain: ['WarrantApprovalSets', 'Members']);
        $this->Authorization->authorize($warrantApproval);
        $this->set(compact('warrantApproval'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $warrantApproval = $this->WarrantApprovals->newEmptyEntity();
        $this->Authorization->authorize($warrantApproval);
        if ($this->request->is('post')) {
            $warrantApproval = $this->WarrantApprovals->patchEntity($warrantApproval, $this->request->getData());
            if ($this->WarrantApprovals->save($warrantApproval)) {
                $this->Flash->success(__('The warrant approval has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant approval could not be saved. Please, try again.'));
        }
        $warrantApprovalSets = $this->WarrantApprovals->WarrantApprovalSets->find('list', limit: 200)->all();
        $members = $this->WarrantApprovals->Members->find('list', limit: 200)->all();
        $this->set(compact('warrantApproval', 'warrantApprovalSets', 'members'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Warrant Approval id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $warrantApproval = $this->WarrantApprovals->get($id, contain: []);
        $this->Authorization->authorize($warrantApproval);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $warrantApproval = $this->WarrantApprovals->patchEntity($warrantApproval, $this->request->getData());
            if ($this->WarrantApprovals->save($warrantApproval)) {
                $this->Flash->success(__('The warrant approval has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant approval could not be saved. Please, try again.'));
        }
        $warrantApprovalSets = $this->WarrantApprovals->WarrantApprovalSets->find('list', limit: 200)->all();
        $members = $this->WarrantApprovals->Members->find('list', limit: 200)->all();
        $this->set(compact('warrantApproval', 'warrantApprovalSets', 'members'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Warrant Approval id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $warrantApproval = $this->WarrantApprovals->get($id);
        $this->Authorization->authorize($warrantApproval);
        if ($this->WarrantApprovals->delete($warrantApproval)) {
            $this->Flash->success(__('The warrant approval has been deleted.'));
        } else {
            $this->Flash->error(__('The warrant approval could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
