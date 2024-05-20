<?php
declare(strict_types=1);

namespace App\Controller;
use Cake\Log\Log;

/**
 * Roles Controller
 *
 * @property \App\Model\Table\RolesTable $Roles
 */
class RolesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index','add','searchMembers','addPermission','deletePermission');
    }
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {

        $this->Authorization->authorizeAction();
        $query = $this->Roles->find();
        $query = $this->Authorization->applyScope($query);
        $roles = $this->paginate($query);

        $this->set(compact('roles'));
    }

    /**
     * View method
     *
     * @param string|null $id Role id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        Log::write("debug", 'user id '. $this->Authentication->getIdentity()->get('id'));
        $role = $this->Roles->get($id,contain: ['MemberRoles.Member','MemberRoles.Authorized_By','Permissions.AuthorizationTypes','Permissions']);
        $this->Authorization->authorize($role, "view");
        //get all the permissions not already assigned to the role
        $currentPermissionIds = [];
        foreach($role->permissions as $permission){
            $currentPermissionIds[] = $permission->id;
        }   
        $permissions = [];
        if(count($currentPermissionIds) > 0){
            $permissions = $this->Roles->Permissions->find('list')
                ->where(['NOT' => ['id IN' => $currentPermissionIds]])
                ->all();
        }else{
            $permissions = $this->Roles->Permissions->find('list')->all();
        }
        $this->set(compact('role','permissions'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $role = $this->Roles->newEmptyEntity();
        $this->Authorization->authorizeAction();
        if ($this->request->is('post')) {
            $role = $this->Roles->patchEntity($role, $this->request->getData());
            if ($this->Roles->save($role)) {
                $this->Flash->success(__('The role has been saved.'));

                return $this->redirect(['action' => 'view', $role->id]);
            }
            $this->Flash->error(__('The role could not be saved. Please, try again.'));
        }
        $permissions = $this->Roles->Permissions->find('list')->all();
        $this->set(compact('role', 'permissions'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Role id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $role = $this->Roles->get($id,contain: ['MemberRoles.Member','MemberRoles.Authorized_By','Permissions']);
        $this->Authorization->authorize($role);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $role = $this->Roles->patchEntity($role, $this->request->getData());
            if ($this->Roles->save($role)) {
                $this->Flash->success(__('The role has been saved.'));

                return $this->redirect($this->referer());
            }
            $this->Flash->error(__('The role could not be saved. Please, try again.'));
        }
        $permissions = $this->Roles->Permissions->find('list')->all();
        $this->set(compact('role', 'permissions'));
    }

    public function addPermission()
    {
        $this->request->allowMethod(['patch', 'post', 'put']);
        $role_id = $this->request->getData('role_id');
        $permission_id = $this->request->getData('permission_id');
        $role = $this->Roles->get($role_id,contain: ['Permissions']);
        $this->Authorization->authorizeAction();
        $permission = $this->Roles->Permissions->get($permission_id);
        for($i = 0; $i < count($role->permissions); $i++){
            if($role->permissions[$i]->id == $permission_id){
                $this->Flash->error(__('The permission is already assigned to the role.'));
                return $this->redirect($this->referer());
            }
        }
        //add the permission to the role
        $role->permissions[] = $permission;
        $role->setDirty('permissions', true);
        if ($this->Roles->save($role)) {
            $this->Flash->success(__('The permission has been added to the role.'));
        } else {
            $this->Flash->error(__('The permission could not be added to the role. Please, try again.'));
        }
        return $this->redirect($this->referer());
    }

    public function deletePermission(){
        $this->request->allowMethod(['post']);
        $role_id = $this->request->getData('role_id');
        $permission_id = $this->request->getData('permission_id');
        $role = $this->Roles->get($role_id,contain: ['Permissions']);
        $this->Authorization->authorizeAction();
        $permission = $this->Roles->Permissions->get($permission_id);
        for($i = 0; $i < count($role->permissions); $i++){
            if($role->permissions[$i]->id == $permission_id){
                unset($role->permissions[$i]);
                $role->setDirty('permissions', true);
                if ($this->Roles->save($role)) {
                    $this->Flash->success(__('The permission has been removed from the role.'));
                } else {
                    $this->Flash->error(__('The permission could not be removed from the role. Please, try again.'));
                }
                return $this->redirect($this->referer());
            }
        }
        $this->Flash->error(__('The permission is not assigned to the role.'));
        return $this->redirect($this->referer());
    }

    /**
     * Delete method
     *
     * @param string|null $id Role id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $role = $this->Roles->get($id);
        $this->Authorization->authorize($role);
        if ($this->Roles->delete($role)) {
            $this->Flash->success(__('The role has been deleted.'));
        } else {
            $this->Flash->error(__('The role could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * search for adding members to a role
     * 
     * @param string|null $q to search sca_name.
     * @return \Cake\Http\Response|null|void ajax only
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function searchMembers()
    {
        $q = $this->request->getQuery('q');
        $this->Authorization->authorizeAction();
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Ajax');
        $query = $this->Roles->Members->find('all')
            ->where(['sca_name LIKE' => "%$q%"])
            ->select(['id','sca_name'])
            ->limit(10);
        //$query = $this->Authorization->applyScope($query);
        $this->response = $this->response->withType('application/json')
                                     ->withStringBody(json_encode($query));
        return $this->response;
    }
}
