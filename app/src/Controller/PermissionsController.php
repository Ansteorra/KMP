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
        $this->Authorization->authorizeModel("index", "add", "matrix");
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

    public function updatePolicy()
    {
        //json call to add a policy to a permission
        //check that the call is an ajax call
        if (!$this->request->is("json")) {
            throw new \Cake\Http\Exception\BadRequestException();
        }
        if (!$this->request->is("post")) {
            throw new \Cake\Http\Exception\BadRequestException();
        }
        $policyJson = $this->request->getData();
        $id = $policyJson["permissionId"];
        $permission = $this->Permissions->get($id);
        if (!$permission) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($permission);

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
     * Matrix view for permissions and policies
     */
    public function matrix()
    {
        // Get all permissions
        $permissions = $this->Permissions->find('all')->where(['is_super_user' => false])->toArray();

        // Get all application policies
        $policiesArray = \App\KMP\PermissionsLoader::getApplicationPolicies();

        // Prepare data structure for the view
        $policiesFlat = [];
        foreach ($policiesArray as $policyClass => $methods) {
            // Extract the class name without namespace
            $classNameParts = explode('\\', $policyClass);
            $className = end($classNameParts);
            $nameSpace = implode('\\', array_slice($classNameParts, 0, -1));
            $policiesFlat[] = [
                'namespace' => $nameSpace,
                'class' => $policyClass,
                'className' => $className,
                'method' => "WholeClass",
                'display' => ""
            ];
            foreach ($methods as $method) {
                $policiesFlat[] = [
                    'namespace' => $nameSpace,
                    'class' => $policyClass,
                    'className' => $className,
                    'method' => $method,
                    'display' => str_replace('can', '', $method)
                ];
            }
        }

        // Get existing permission policy associations
        $permissionPoliciesTable = $this->fetchTable('PermissionPolicies');
        $existingPolicies = $permissionPoliciesTable->find()
            ->select(['permission_id', 'policy_class', 'policy_method'])
            ->toArray();

        // Create a lookup array for easy checking in the view
        $policyMap = [];
        foreach ($existingPolicies as $policy) {
            $key = $policy->permission_id . '_' . $policy->policy_class . '_' . $policy->policy_method;
            $policyMap[$key] = true;
        }
        //sort the policiesFlat by namespace, class and method
        usort($policiesFlat, function ($a, $b) {
            return strcmp($a['namespace'], $b['namespace']) ?: strcmp($a['className'], $b['className']) ?: strcmp($a['display'], $b['display']);
        });

        $this->set(compact('permissions', 'policiesFlat', 'policyMap'));
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