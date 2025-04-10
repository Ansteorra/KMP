<?php

declare(strict_types=1);

namespace Activities\Controller;

use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Activities\Model\Entity\Authorization;

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
                "Permissions" => function ($q) {
                    return $q->select(["id", "name"]);
                },
                "ActivityGroups" => function ($q) {
                    return $q->select(["id", "name"]);
                },
                "Roles" => function ($q) {
                    return $q->select(["id", "name"]);
                }
            ],
        );
        if (!$activity) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($activity);
        $activeCount = $this->Activities->CurrentAuthorizations->find()
            ->where(["activity_id" => $id])
            ->count();
        $pendingCount = $this->Activities->PendingAuthorizations->find()
            ->where(["activity_id" => $id])
            ->count();
        $previousCount = $this->Activities->PreviousAuthorizations->find()
            ->where(["activity_id" => $id])
            ->count();
        $isEmpty = $activeCount + $pendingCount + $previousCount == 0;
        if ($activity->permission_id) {
            $roles = $this->Activities->Permissions->Roles
                ->find()
                ->innerJoinWith("Permissions", function ($q) use (
                    $activity,
                ) {
                    return $q->where([
                        "OR" => [
                            "Permissions.id" =>
                            $activity->permission_id,
                            "Permissions.is_super_user" => true,
                        ],
                    ]);
                })
                ->distinct()
                ->all();
        } else {
            $roles = [];
        }
        $activityGroup = $this->Activities->ActivityGroups
            ->find("list")
            ->all();
        $authAssignableRoles = $this->Activities->Roles
            ->find("list")
            ->all();
        $authByPermissions = $this->Activities->Permissions
            ->find("list")
            ->all();
        $this->set(
            compact(
                "activity",
                "activityGroup",
                "roles",
                "authAssignableRoles",
                "authByPermissions",
                "pendingCount",
                "isEmpty",
                "id"
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
            if ($this->Activities->save($activity)) {
                $this->Flash->success(__("The authorization type has been saved."),);
                return $this->redirect(["action" => "view", $activity->id,]);
            }
            $this->Flash->error(__("The authorization type could not be saved. Please, try again.",),);
        }
        $authAssignableRoles = $this->Activities->Roles
            ->find("list")
            ->all();
        $activityGroup = $this->Activities->ActivityGroups
            ->find("list", limit: 200)
            ->all();
        $authByPermissions = $this->Activities->Permissions
            ->find("list")
            ->all();
        $this->set(compact(
            "activity",
            "activityGroup",
            "authAssignableRoles",
            "authByPermissions"
        ));
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

                return $this->redirect(
                    $this->referer()
                );
            }
            $this->Flash->error(
                __(
                    "The authorization type could not be saved. Please, try again.",
                )
            );
            return $this->redirect(
                $this->referer()
            );
        }
        return $this->redirect(
            $this->referer()
        );
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
        $activity->name = "Deleted: " . $activity->name;
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

    public function approversList($activityId = null, $memberId = null)
    {
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(["get"]);
        $activity = $this->Activities->get($activityId);
        if (!$activity) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->viewBuilder()->setClassName("Ajax");
        $member = TableRegistry::getTableLocator()->get('Members')->get($memberId);
        $query = $activity->getApproversQuery($member->branch_id);
        $result = $query
            ->contain(["Branches"])
            ->where(["Members.id !=" => $memberId])
            ->orderBy(["Branches.name", "Members.sca_name"])
            ->select(["Members.id", "Members.sca_name", "Branches.name"])
            ->distinct()
            ->all()
            ->toArray();
        $responseData = [];
        foreach ($result as $member) {
            $responseData[] = [
                "id" => $member->id,
                "sca_name" => $member->branch->name . ": " . $member->sca_name,
            ];
        }
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($responseData));

        return $this->response;
    }
}