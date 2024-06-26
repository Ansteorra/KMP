<?php

declare(strict_types=1);

namespace Awards\Controller;

/**
 * Levels Controller
 *
 * @property \App\Model\Table\LevelsTable $Levels
 */
class LevelsController extends AppController
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
        $query = $this->Levels->find();
        $levels = $this->paginate($query, [
            'order' => [
                'progression_order' => 'asc',
            ]
        ]);

        $this->set(compact("levels"));
    }

    /**
     * View method
     *
     * @param string|null $id Award Level id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $level = $this->Levels->get(
            $id,
            contain: [
                "Awards",
                "Awards.Domains" => function ($q) {
                    return $q->select(["id", "name"]);
                },
                "Awards.Branches" => function ($q) {
                    return $q->select(["id", "name"]);
                },
            ]
        );
        if (!$level) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($level);
        $this->set(compact("level"));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $level = $this->Levels->newEmptyEntity();
        if ($this->request->is("post")) {
            $level = $this->Levels->patchEntity(
                $level,
                $this->request->getData(),
            );
            if ($this->Levels->save($level)) {
                $this->Flash->success(
                    __("The Award Level has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $level->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Award Level could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("level"));
    }

    /**
     * Edit method
     *
     * @param string|null $id Award Level id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $level = $this->Levels->get($id, contain: []);
        if (!$level) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($level);
        if ($this->request->is(["patch", "post", "put"])) {
            $level = $this->Levels->patchEntity(
                $level,
                $this->request->getData(),
            );
            if ($this->Levels->save($level)) {
                $this->Flash->success(
                    __("The Award Level Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $level->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Award Level could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("level"));
    }

    /**
     * Delete method
     *
     * @param string|null $id level id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $level = $this->Levels->get(
            $id,
            contain: ["Awards"],
        );
        if (!$level) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        if ($level->awards) {
            $this->Flash->error(
                __("The Award Level could not be deleted because it has associated Awards."),
            );
            return $this->redirect(["action" => "view", $level->id]);
        }
        $this->Authorization->authorize($level);
        $level->name = "Deleted: " . $level->name;
        if ($this->Levels->delete($level)) {
            $this->Flash->success(
                __("The Award Level has been deleted."),
            );
        } else {
            $this->Flash->error(
                __(
                    "The Award Level could not be deleted. Please, try again.",
                ),
            );

            return $this->redirect(["action" => "view", $level->id]);
        }

        return $this->redirect(["action" => "index"]);
    }
}