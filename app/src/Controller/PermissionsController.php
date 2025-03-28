<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\PermissionsLoader;

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
        $this->Authorization->authorizeModel("index", "add");
    }
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorizeAction();
        $query = $this->Permissions->find();
        $query = $this->Authorization->applyScope($query);
        $permissions = $this->paginate($query, [
            'order' => [
                'name' => 'asc',
            ]
        ]);

        $this->set(compact("permissions"));
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
        $permission = $this->Permissions->get(
            $id,
            contain: ["Roles", "PermissionPolicies"],
        );
        if (!$permission) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($permission);
        //Get all the roles not already assigned to the permission
        $currentRoleIds = [];
        foreach ($permission->roles as $role) {
            $currentRoleIds[] = $role->id;
        }
        $roles = [];
        if (count($currentRoleIds) > 0) {
            $roles = $this->Permissions->Roles
                ->find("list")
                ->where(["NOT" => ["id IN" => $currentRoleIds], "is_system !=" => true])
                ->all();
        } else {
            $roles = $this->Permissions->Roles->find("list")->where(['is_system !=' => true])->all();
        }
        $appPolicies = PermissionsLoader::getApplicationPolicies();
        $this->set(compact("permission", "roles", "appPolicies"));
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
        if ($this->request->is("post")) {
            $permission = $this->Permissions->patchEntity(
                $permission,
                $this->request->getData(),
            );
            $permission->is_system = false;
            if (!$this->Authentication->getIdentity()->isSuperUser()) {
                $permission->is_super_user = false;
            }
            if ($this->Permissions->save($permission)) {
                $this->Flash->success(__("The permission has been saved."));

                return $this->redirect(["action" => "view", $permission->id]);
            }
            $this->Flash->error(
                __("The permission could not be saved. Please, try again."),
            );
        }
        $this->set(compact("permission"));
    }

    public function updatePolicy($id)
    {
        //json call to add a policy to a permission
        //check that the call is an ajax call
        if (!$this->request->is("json")) {
            throw new \Cake\Http\Exception\BadRequestException();
        }
        if (!$this->request->is("post")) {
            throw new \Cake\Http\Exception\BadRequestException();
        }
        $permission = $this->Permissions->get($id);
        if (!$permission) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($permission);
        $policyJson = $this->request->getData();
        $policy = $this->Permissions->PermissionPolicies->newEmptyEntity();
        $policy->permission_id = $id;
        $policy->policy_class = $policyJson['className'];
        $policy->policy_method = $policyJson['method'];
        if ($policyJson['action'] == "add") {
            //check if the policy already exists
            $policyCheck = $this->Permissions->PermissionPolicies
                ->find()
                ->where([
                    "permission_id" => $id,
                    "policy_class" => $policyJson['className'],
                    "policy_method" => $policyJson['method'],
                ])
                ->first();
            if ($policyCheck) {
                $this->response = $this->response
                    ->withType("application/json")
                    ->withStringBody(json_encode(true));
                $this->response->withStatus(200);
                return $this->response;
            }
            if ($this->Permissions->PermissionPolicies->save($policy)) {
                $this->response = $this->response
                    ->withType("application/json")
                    ->withStringBody(json_encode(true));
                $this->response->withStatus(200);
                return $this->response;
            } else {
                $this->response = $this->response
                    ->withType("application/json")
                    ->withStringBody(json_encode(false));
                $this->response->withStatus(500);
                return $this->response;
            }
        } else {
            //we look up the policy for the permission and delete it
            $policy = $this->Permissions->PermissionPolicies
                ->find()
                ->where([
                    "permission_id" => $id,
                    "policy_class" => $policyJson['className'],
                    "policy_method" => $policyJson['method'],
                ])
                ->first();
            if ($policy) {
                if ($this->Permissions->PermissionPolicies->delete($policy)) {
                    $this->response = $this->response
                        ->withType("application/json")
                        ->withStringBody(json_encode(true));
                    $this->response->withStatus(200);
                    return $this->response;
                } else {
                    $this->response = $this->response
                        ->withType("application/json")
                        ->withStringBody(json_encode(false));
                    $this->response->withStatus(500);
                    return $this->response;
                }
            } else {
                $this->response = $this->response
                    ->withType("application/json")
                    ->withStringBody(json_encode(false));
                $this->response->withStatus(500);
                return $this->response;
            }
        }
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
        $permission = $this->Permissions->get($id, contain: ["Roles"]);
        if (!$permission) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($permission);
        $patch = $this->request->getData();
        if ($this->request->is(["patch", "post", "put"])) {
            if ($permission->is_system) {
                //unset the name of the permission if it is a system permission
                unset($patch["name"]);
            }
            if (!$this->Authentication->getIdentity()->isSuperUser()) {
                unset($patch["is_super_user"]);
            }
            $permission = $this->Permissions->patchEntity($permission, $patch);
            if ($this->Permissions->save($permission)) {
                $this->Flash->success(__("The permission has been saved."));

                return $this->redirect($this->referer());
            }
            $this->Flash->error(
                __("The permission could not be saved. Please, try again."),
            );
        }
        $activities = $this->Permissions->Activities
            ->find("list", limit: 200)
            ->all();
        $roles = $this->Permissions->Roles->find("list", limit: 200)->all();
        $this->set(compact("permission", "activities", "roles"));
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
        $this->request->allowMethod(["post", "delete"]);
        $permission = $this->Permissions->get($id);
        if (!$permission) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($permission);
        if ($permission->is_system) {
            $this->Flash->error(
                __(
                    "The permission could not be deleted. System permissions cannot be deleted.",
                ),
            );
            return $this->redirect($this->referer());
        }
        $permission->name = "Deleted: " . $permission->name;
        if ($this->Permissions->delete($permission)) {
            $this->Flash->success(__("The permission has been deleted."));
        } else {
            $this->Flash->error(
                __("The permission could not be deleted. Please, try again."),
            );
        }

        return $this->redirect(["action" => "index"]);
    }
}