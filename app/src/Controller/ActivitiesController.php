<?php

declare(strict_types=1);

namespace App\Controller;

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
        $activities = $this->paginate($query);

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
        $authorizationType = $this->Activities->get(
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
        $this->Authorization->authorize($authorizationType);
        $roles = $this->Activities->Permissions->Roles
            ->find()
            ->innerJoinWith("Permissions", function ($q) use (
                $authorizationType,
            ) {
                return $q->where([
                    "OR" => [
                        "Permissions.activity_id" =>
                        $authorizationType->id,
                        "Permissions.is_super_user" => true,
                    ],
                ]);
            })
            ->distinct()
            ->all();
        $authorizationGroups = $this->Activities->ActivityGroups
            ->find("list")
            ->all();
        $authAssignableRoles = $this->Activities->Roles
            ->find("list")
            ->all();
        $this->set(
            compact(
                "authorizationType",
                "authorizationGroups",
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
        $authorizationType = $this->Activities->newEmptyEntity();
        if ($this->request->is("post")) {
            $authorizationType = $this->Activities->patchEntity(
                $authorizationType,
                $this->request->getData(),
            );
            if ($this->Activities->save($authorizationType)) {
                $this->Flash->success(
                    __("The authorization type has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $authorizationType->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The authorization type could not be saved. Please, try again.",
                ),
            );
        }
        $authAssignableRoles = $this->Activities->Roles
            ->find("list")
            ->all();
        $authorizationGroups = $this->Activities->ActivityGroups
            ->find("list", limit: 200)
            ->all();
        $this->set(compact("authorizationType", "authorizationGroups", "authAssignableRoles"));
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
        $authorizationType = $this->Activities->get($id, contain: []);
        $this->Authorization->authorize($authorizationType);
        if ($this->request->is(["patch", "post", "put"])) {
            $authorizationType = $this->Activities->patchEntity(
                $authorizationType,
                $this->request->getData(),
            );
            if ($this->Activities->save($authorizationType)) {
                $this->Flash->success(
                    __("The authorization type has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $authorizationType->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The authorization type could not be saved. Please, try again.",
                ),
            );
        }
        $authorizationGroups = $this->Activities->ActivityGroups
            ->find("list", limit: 200)
            ->all();
        $this->set(compact("authorizationType", "authorizationGroups"));
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
        $authorizationType = $this->Activities->get($id);
        $this->Authorization->authorize($authorizationType);
        if ($this->Activities->delete($authorizationType)) {
            $this->Flash->success(
                __("The authorization type has been deleted."),
            );
        } else {
            $this->Flash->error(
                __(
                    "The authorization type could not be deleted. Please, try again.",
                ),
            );
        }

        return $this->redirect(["action" => "index"]);
    }
}
