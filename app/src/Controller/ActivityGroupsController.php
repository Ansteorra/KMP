<?php

declare(strict_types=1);

namespace App\Controller;

/**
 * ActivityGroups Controller
 *
 * @property \App\Model\Table\ActivityGroupsTable $ActivityGroups
 */
class ActivityGroupsController extends AppController
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
        $query = $this->ActivityGroups->find();
        $activityGroup = $this->paginate($query, [
            'order' => [
                'name' => 'asc',
            ]
        ]);

        $this->set(compact("activityGroup"));
    }

    /**
     * View method
     *
     * @param string|null $id Activity Group id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $authorizationGroup = $this->ActivityGroups->get(
            $id,
            contain: ["Activities"],
        );
        if (!$authorizationGroup) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($authorizationGroup);
        $this->set(compact("authorizationGroup"));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $authorizationGroup = $this->ActivityGroups->newEmptyEntity();
        if ($this->request->is("post")) {
            $authorizationGroup = $this->ActivityGroups->patchEntity(
                $authorizationGroup,
                $this->request->getData(),
            );
            if ($this->ActivityGroups->save($authorizationGroup)) {
                $this->Flash->success(
                    __("The Activity Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $authorizationGroup->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Activity Group could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("authorizationGroup"));
    }

    /**
     * Edit method
     *
     * @param string|null $id Activity Group id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $authorizationGroup = $this->ActivityGroups->get($id, contain: []);
        if (!$authorizationGroup) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorizationGroup);
        if ($this->request->is(["patch", "post", "put"])) {
            $authorizationGroup = $this->ActivityGroups->patchEntity(
                $authorizationGroup,
                $this->request->getData(),
            );
            if ($this->ActivityGroups->save($authorizationGroup)) {
                $this->Flash->success(
                    __("The Activity Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $authorizationGroup->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Activity Group could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("ActivityGroup"));
    }

    /**
     * Delete method
     *
     * @param string|null $id Activity Group id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $authorizationGroup = $this->ActivityGroups->get($id);
        if (!$authorizationGroup) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorizationGroup);
        if ($this->ActivityGroups->delete($authorizationGroup)) {
            $this->Flash->success(
                __("The Activity Group has been deleted."),
            );
        } else {
            $this->Flash->error(
                __(
                    "The Activity Group could not be deleted. Please, try again.",
                ),
            );
        }

        return $this->redirect(["action" => "index"]);
    }
}