<?php

declare(strict_types=1);

namespace App\Controller;

/**
 * AuthorizationGroups Controller
 *
 * @property \App\Model\Table\AuthorizationGroupsTable $AuthorizationGroups
 */
class AuthorizationGroupsController extends AppController
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
        $query = $this->AuthorizationGroups->find();
        $authorizationGroups = $this->paginate($query);

        $this->set(compact("authorizationGroups"));
    }

    /**
     * View method
     *
     * @param string|null $id Authorization Group id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $authorizationGroup = $this->AuthorizationGroups->get(
            $id,
            contain: ["AuthorizationTypes"],
        );
        $this->set(compact("authorizationGroup"));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $authorizationGroup = $this->AuthorizationGroups->newEmptyEntity();
        if ($this->request->is("post")) {
            $authorizationGroup = $this->AuthorizationGroups->patchEntity(
                $authorizationGroup,
                $this->request->getData(),
            );
            if ($this->AuthorizationGroups->save($authorizationGroup)) {
                $this->Flash->success(
                    __("The Authorization Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $authorizationGroup->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Authorization Group could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("authorizationGroup"));
    }

    /**
     * Edit method
     *
     * @param string|null $id Authorization Group id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $authorizationGroup = $this->AuthorizationGroups->get($id, contain: []);
        $this->Authorization->authorize($authorizationGroup);
        if ($this->request->is(["patch", "post", "put"])) {
            $authorizationGroup = $this->AuthorizationGroups->patchEntity(
                $authorizationGroup,
                $this->request->getData(),
            );
            if ($this->AuthorizationGroups->save($authorizationGroup)) {
                $this->Flash->success(
                    __("The Authorization Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $authorizationGroup->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Authorization Group could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("AuthorizationGroup"));
    }

    /**
     * Delete method
     *
     * @param string|null $id Authorization Group id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $authorizationGroup = $this->AuthorizationGroups->get($id);

        $this->Authorization->authorize($authorizationGroup);
        if ($this->AuthorizationGroups->delete($authorizationGroup)) {
            $this->Flash->success(
                __("The Authorization Group has been deleted."),
            );
        } else {
            $this->Flash->error(
                __(
                    "The Authorization Group could not be deleted. Please, try again.",
                ),
            );
        }

        return $this->redirect(["action" => "index"]);
    }
}
