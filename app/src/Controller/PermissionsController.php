<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Permissions Controller
 *
 * @property \App\Model\Table\PermissionsTable $Permissions
 */
class PermissionsController extends AppController
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
        $this->Authorization->authorizeAction();
        $query = $this->Permissions->find()
            ->contain(['AuthorizationTypes']);
        $query = $this->Authorization->applyScope($query);
        $permissions = $this->paginate($query);

        $this->set(compact('permissions'));
    }

    /**
     * View method
     *
     * @param string|null $id Permission id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $permission = $this->Permissions->get($id, contain: ['AuthorizationTypes', 'Roles']);
        $this->Authorization->authorize($permission);
        //Get all the roles not already assigned to the permission
        $currentRoleIds = [];
        foreach($permission->roles as $role){
            $currentRoleIds[] = $role->id;
        }
        $roles = [];
        if(count($currentRoleIds) > 0){
            $roles = $this->Permissions->Roles->find('list')
                ->where(['NOT' => ['id IN' => $currentRoleIds]])
                ->all();
        }else{
            $roles = $this->Permissions->Roles->find('list')->all();
        }
        $authorizationTypes = $this->Permissions->AuthorizationTypes->find('list', limit: 200)->all();
        $this->set(compact('permission','roles','authorizationTypes'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->Authorization->authorizeAction();
        $permission = $this->Permissions->newEmptyEntity();
        if ($this->request->is('post')) {
            $permission = $this->Permissions->patchEntity($permission, $this->request->getData());
            $permission->system = false;
            if(!$this->Authentication->getIdentity()->isSuperUser()){
                $permission->is_super_user = false;
            }   
            if ($this->Permissions->save($permission)) {
                $this->Flash->success(__('The permission has been saved.'));

                return $this->redirect(['action' => 'view', $permission->id]);
            }
            $this->Flash->error(__('The permission could not be saved. Please, try again.'));
        }
        $authorizationTypes = $this->Permissions->AuthorizationTypes->find('list', limit: 200)->all();
        $this->set(compact('permission', 'authorizationTypes'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Permission id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $permission = $this->Permissions->get($id, contain: ['Roles']);
        $this->Authorization->authorize($permission);
        $patch = $this->request->getData();
        if ($this->request->is(['patch', 'post', 'put'])) {
            if($permission->system){
                //unset the name of the permission if it is a system permission
                unset($patch['name']);
            }       
            if(!$this->Authentication->getIdentity()->isSuperUser()){
                unset($patch['is_super_user']);
            }   
            $permission = $this->Permissions->patchEntity($permission, $patch);
            if ($this->Permissions->save($permission)) {
                $this->Flash->success(__('The permission has been saved.'));

                return $this->redirect($this->referer());
            }
            $this->Flash->error(__('The permission could not be saved. Please, try again.'));
        }
        $authorizationTypes = $this->Permissions->AuthorizationTypes->find('list', limit: 200)->all();
        $roles = $this->Permissions->Roles->find('list', limit: 200)->all();
        $this->set(compact('permission', 'authorizationTypes', 'roles'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Permission id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $permission = $this->Permissions->get($id);
        $this->Authorization->authorize($permission);
        if($permission->system){
            $this->Flash->error(__('The permission could not be deleted. System permissions cannot be deleted.'));
            return $this->redirect($this->referer());
        }   
        if ($this->Permissions->delete($permission)) {
            $this->Flash->success(__('The permission has been deleted.'));
        } else {
            $this->Flash->error(__('The permission could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
