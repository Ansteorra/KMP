<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;

/**
 * Activities Controller
 *
 * @property \App\Model\Table\ActivitiesTable $Activities
 */
class ActivitiesController extends AppController
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
        $query = $this->Activities->find()->contain([
            "ActivityGroups" => function ($q) {
                return $q->select(["id", "name"]);
            },
            "Roles" => function ($q) {
                return $q->select(["id", "name"]);
            },
        ]);
        $activities = $this->paginate($query, [
            'order' => [
                'name' => 'asc',
            ]
        ]);

        $this->set(compact("activities"));
    }

    /**
     * View method
     *
     * @param string|null $id Activity id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $activity = $this->Activities->get(
            $id,
            contain: [
                "ActivityGroups" => function ($q) {
                    return $q->select(["id", "name"]);
                },
                "Roles" => function ($q) {
                    return $q->select(["id", "name"]);
                },
                "Authorizations.AuthorizationApprovals.Approvers" => function (
                    $q,
                ) {
                    return $q->select(["id", "sca_name"]);
                },
                "Authorizations.Members" => function ($q) {
                    return $q->select(["id", "sca_name"]);
                },
            ],
        );
        if (!$activity) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($activity);
        $roles = $this->Activities->Permissions->Roles
            ->find()
            ->innerJoinWith("Permissions", function ($q) use (
                $activity,
            ) {
                return $q->where([
                    "OR" => [
                        "Permissions.activity_id" =>
                        $activity->id,
                        "Permissions.is_super_user" => true,
                    ],
                ]);
            })
            ->distinct()
            ->all();
        $activityGroup = $this->Activities->ActivityGroups
            ->find("list")
            ->all();
        $authAssignableRoles = $this->Activities->Roles
            ->find("list")
            ->all();
        $this->set(
            compact(
                "activity",
                "activityGroup",
                "roles",
                "authAssignableRoles",
            ),
        );
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $activity = $this->Activities->newEmptyEntity();
        if ($this->request->is("post")) {
            $activity = $this->Activities->patchEntity(
                $activity,
                $this->request->getData(),
            );
            $this->Activities->getConnection()->begin();
            if (!$this->Activities->save($activity)) {
                $this->Flash->error(__("The authorization type could not be saved. Please, try again.",),);
                return $this->redirect(["action" => "view", $activity->id,]);
            }
            $permissionsTbl = TableRegistry::getTableLocator()->get("Permissions");
            $newPermission = $this->Activities->Roles->newEmptyEntity();
            $newPermission->name = "Can Authorize " . $activity->name;
            $newPermission->activity_id = $activity->id;
            $newPermission->is_super_user = false;
            $newPermission->is_system = false;
            $newPermission->require_active_membership = true;
            $newPermission->require_min_age = 18;
            $newPermission->requires_warrant = true;
            if (!$permissionsTbl->save($newPermission)) {
                $this->Flash->error(__("The authorization type could not be saved. Please, try again.",),);
                $this->Activities->getConnection()->rollback();
                return $this->redirect(["action" => "view", $activity->id,]);
            }
            $this->Activities->getConnection()->commit();
            $this->Flash->success(__("The authorization type has been saved."),);
            return $this->redirect(["action" => "view", $activity->id,]);
        }
        $authAssignableRoles = $this->Activities->Roles
            ->find("list")
            ->all();
        $activityGroup = $this->Activities->ActivityGroups
            ->find("list", limit: 200)
            ->all();
        $this->set(compact("activity", "activityGroup", "authAssignableRoles"));
    }

    /**
     * Edit method
     *
     * @param string|null $id Activity id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $activity = $this->Activities->get($id, contain: []);
        if (!$activity) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($activity);
        if ($this->request->is(["patch", "post", "put"])) {
            $activity = $this->Activities->patchEntity(
                $activity,
                $this->request->getData(),
            );
            if ($this->Activities->save($activity)) {
                $this->Flash->success(
                    __("The authorization type has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $activity->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The authorization type could not be saved. Please, try again.",
                )
            );
            return $this->redirect([
                "action" => "view",
                $activity->id,
            ]);
        }
        return $this->redirect([
            "action" => "view",
            $activity->id,
        ]);
    }

    /**
     * Delete method
     *
     * @param string|null $id Activity id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $activity = $this->Activities->get($id);
        if (!$activity) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($activity);
        if ($this->Activities->delete($activity)) {
            $this->Flash->success(
                __("The activity has been deleted."),
            );
        } else {
            $this->Flash->error(
                __(
                    "The activity could not be deleted. Please, try again.",
                ),
            );
        }

        return $this->redirect(["action" => "index"]);
    }
}