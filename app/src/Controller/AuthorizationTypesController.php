<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * AuthorizationTypes Controller
 *
 * @property \App\Model\Table\AuthorizationTypesTable $AuthorizationTypes
 */
class AuthorizationTypesController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->AuthorizationTypes->find()
            ->contain(['MartialGroups']);
        $authorizationTypes = $this->paginate($query);

        $this->set(compact('authorizationTypes'));
    }

    /**
     * View method
     *
     * @param string|null $id Authorization Type id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $authorizationType = $this->AuthorizationTypes->get($id, contain: ['MartialGroups', 'MemberAuthorizationTypes', 'PendingAuthorizations', 'Permissions']);
        $this->set(compact('authorizationType'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $authorizationType = $this->AuthorizationTypes->newEmptyEntity();
        if ($this->request->is('post')) {
            $authorizationType = $this->AuthorizationTypes->patchEntity($authorizationType, $this->request->getData());
            if ($this->AuthorizationTypes->save($authorizationType)) {
                $this->Flash->success(__('The authorization type has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The authorization type could not be saved. Please, try again.'));
        }
        $martialGroups = $this->AuthorizationTypes->MartialGroups->find('list', limit: 200)->all();
        $this->set(compact('authorizationType', 'martialGroups'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Authorization Type id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $authorizationType = $this->AuthorizationTypes->get($id, contain: []);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $authorizationType = $this->AuthorizationTypes->patchEntity($authorizationType, $this->request->getData());
            if ($this->AuthorizationTypes->save($authorizationType)) {
                $this->Flash->success(__('The authorization type has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The authorization type could not be saved. Please, try again.'));
        }
        $martialGroups = $this->AuthorizationTypes->MartialGroups->find('list', limit: 200)->all();
        $this->set(compact('authorizationType', 'martialGroups'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Authorization Type id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $authorizationType = $this->AuthorizationTypes->get($id);
        if ($this->AuthorizationTypes->delete($authorizationType)) {
            $this->Flash->success(__('The authorization type has been deleted.'));
        } else {
            $this->Flash->error(__('The authorization type could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
