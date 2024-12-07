<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Warrants Controller
 *
 * @property \App\Model\Table\WarrantsTable $Warrants
 * @property \Authorization\Controller\Component\AuthorizationComponent $Authorization
 */
class WarrantsController extends AppController
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
        $query = $this->Warrants->find()
            ->contain(['Members', 'WarrantApprovalSets', 'MemberRoles']);
        $query = $this->Authorization->applyScope($query);
        $warrants = $this->paginate($query);

        $this->set(compact('warrants'));
    }

    /**
     * View method
     *
     * @param string|null $id Warrant id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $warrant = $this->Warrants->get($id, contain: ['Members', 'WarrantApprovalSets', 'MemberRoles']);
        $this->Authorization->authorize($warrant);
        $this->set(compact('warrant'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $warrant = $this->Warrants->newEmptyEntity();
        $this->Authorization->authorize($warrant);
        if ($this->request->is('post')) {
            $warrant = $this->Warrants->patchEntity($warrant, $this->request->getData());
            if ($this->Warrants->save($warrant)) {
                $this->Flash->success(__('The warrant has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant could not be saved. Please, try again.'));
        }
        $members = $this->Warrants->Members->find('list', limit: 200)->all();
        $warrantApprovalSets = $this->Warrants->WarrantApprovalSets->find('list', limit: 200)->all();
        $memberRoles = $this->Warrants->MemberRoles->find('list', limit: 200)->all();
        $this->set(compact('warrant', 'members', 'warrantApprovalSets', 'memberRoles'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Warrant id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $warrant = $this->Warrants->get($id, contain: []);
        $this->Authorization->authorize($warrant);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $warrant = $this->Warrants->patchEntity($warrant, $this->request->getData());
            if ($this->Warrants->save($warrant)) {
                $this->Flash->success(__('The warrant has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant could not be saved. Please, try again.'));
        }
        $members = $this->Warrants->Members->find('list', limit: 200)->all();
        $warrantApprovalSets = $this->Warrants->WarrantApprovalSets->find('list', limit: 200)->all();
        $memberRoles = $this->Warrants->MemberRoles->find('list', limit: 200)->all();
        $this->set(compact('warrant', 'members', 'warrantApprovalSets', 'memberRoles'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Warrant id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $warrant = $this->Warrants->get($id);
        $this->Authorization->authorize($warrant);
        if ($this->Warrants->delete($warrant)) {
            $this->Flash->success(__('The warrant has been deleted.'));
        } else {
            $this->Flash->error(__('The warrant could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
