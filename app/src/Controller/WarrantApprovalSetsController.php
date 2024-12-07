<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * WarrantApprovalSets Controller
 *
 * @property \App\Model\Table\WarrantApprovalSetsTable $WarrantApprovalSets
 * @property \Authorization\Controller\Component\AuthorizationComponent $Authorization
 */
class WarrantApprovalSetsController extends AppController
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
        $query = $this->WarrantApprovalSets->find();
        $query = $this->Authorization->applyScope($query);
        $warrantApprovalSets = $this->paginate($query);

        $this->set(compact('warrantApprovalSets'));
    }

    /**
     * View method
     *
     * @param string|null $id Warrant Approval Set id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $warrantApprovalSet = $this->WarrantApprovalSets->get($id, contain: ['WarrantApprovals', 'Warrants']);
        $this->Authorization->authorize($warrantApprovalSet);
        $this->set(compact('warrantApprovalSet'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $warrantApprovalSet = $this->WarrantApprovalSets->newEmptyEntity();
        $this->Authorization->authorize($warrantApprovalSet);
        if ($this->request->is('post')) {
            $warrantApprovalSet = $this->WarrantApprovalSets->patchEntity($warrantApprovalSet, $this->request->getData());
            if ($this->WarrantApprovalSets->save($warrantApprovalSet)) {
                $this->Flash->success(__('The warrant approval set has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant approval set could not be saved. Please, try again.'));
        }
        $this->set(compact('warrantApprovalSet'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Warrant Approval Set id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $warrantApprovalSet = $this->WarrantApprovalSets->get($id, contain: []);
        $this->Authorization->authorize($warrantApprovalSet);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $warrantApprovalSet = $this->WarrantApprovalSets->patchEntity($warrantApprovalSet, $this->request->getData());
            if ($this->WarrantApprovalSets->save($warrantApprovalSet)) {
                $this->Flash->success(__('The warrant approval set has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant approval set could not be saved. Please, try again.'));
        }
        $this->set(compact('warrantApprovalSet'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Warrant Approval Set id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $warrantApprovalSet = $this->WarrantApprovalSets->get($id);
        $this->Authorization->authorize($warrantApprovalSet);
        if ($this->WarrantApprovalSets->delete($warrantApprovalSet)) {
            $this->Flash->success(__('The warrant approval set has been deleted.'));
        } else {
            $this->Flash->error(__('The warrant approval set could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
