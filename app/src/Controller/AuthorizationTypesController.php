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

    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index','add');
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->AuthorizationTypes->find()
            ->contain(['AuthorizationGroups']);
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
        $authorizationType = $this->AuthorizationTypes->get($id, contain: [
            'AuthorizationGroups', 
            'Authorizations.AuthorizationApprovals.Approvers' => function($q){
                return $q->select(['id', 'sca_name']);
            },
            'Authorizations.Members' => function($q){
                return $q->select(['id', 'sca_name']);
            },
        ]);
        $this->Authorization->authorize($authorizationType);
        $roles = $this->AuthorizationTypes->Permissions->Roles->find()->innerJoinWith('Permissions', function ($q) use ($authorizationType){
            return $q->where(['OR'=>['Permissions.authorization_type_id' => $authorizationType->id, 'Permissions.is_super_user'=>true]]);
        })->distinct()->all();
        $AuthorizationGroups = $this->AuthorizationTypes->AuthorizationGroups->find('list')->all();
        $this->set(compact('authorizationType', 'AuthorizationGroups', 'roles'));
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

                return $this->redirect(['action' => 'view', $authorizationType->id]);
            }
            $this->Flash->error(__('The authorization type could not be saved. Please, try again.'));
        }
        $AuthorizationGroups = $this->AuthorizationTypes->AuthorizationGroups->find('list', limit: 200)->all();
        $this->set(compact('authorizationType', 'AuthorizationGroups'));
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
        $this->Authorization->authorize($authorizationType);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $authorizationType = $this->AuthorizationTypes->patchEntity($authorizationType, $this->request->getData());
            if ($this->AuthorizationTypes->save($authorizationType)) {
                $this->Flash->success(__('The authorization type has been saved.'));

                return $this->redirect(['action' => 'view', $authorizationType->id]);
            }
            $this->Flash->error(__('The authorization type could not be saved. Please, try again.'));
        }
        $AuthorizationGroups = $this->AuthorizationTypes->AuthorizationGroups->find('list', limit: 200)->all();
        $this->set(compact('authorizationType', 'AuthorizationGroups'));
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
        $this->Authorization->authorize($authorizationType);
        if ($this->AuthorizationTypes->delete($authorizationType)) {
            $this->Flash->success(__('The authorization type has been deleted.'));
        } else {
            $this->Flash->error(__('The authorization type could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
