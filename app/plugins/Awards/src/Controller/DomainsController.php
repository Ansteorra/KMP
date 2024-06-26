<?php

declare(strict_types=1);

namespace Awards\Controller;

/**
 * Domains Controller
 *
 * @property \App\Model\Table\DomainsTable $Domains
 */
class DomainsController extends AppController
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
        $query = $this->Domains->find();
        $domains = $this->paginate($query, [
            'order' => [
                'name' => 'asc',
            ]
        ]);

        $this->set(compact("domains"));
    }

    /**
     * View method
     *
     * @param string|null $id Award Domain id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $domain = $this->Domains->get(
            $id,
            contain: [
                "Awards",
                "Awards.Levels" => function ($q) {
                    return $q->select(["id", "name"]);
                },
                "Awards.Branches" => function ($q) {
                    return $q->select(["id", "name"]);
                },
            ]
        );
        if (!$domain) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($domain);
        $this->set(compact("domain"));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $domain = $this->Domains->newEmptyEntity();
        if ($this->request->is("post")) {
            $domain = $this->Domains->patchEntity(
                $domain,
                $this->request->getData(),
            );
            if ($this->Domains->save($domain)) {
                $this->Flash->success(
                    __("The Award Domain has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $domain->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Award Domain could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("domain"));
    }

    /**
     * Edit method
     *
     * @param string|null $id Award Domain id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $domain = $this->Domains->get($id, contain: []);
        if (!$domain) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($domain);
        if ($this->request->is(["patch", "post", "put"])) {
            $domain = $this->Domains->patchEntity(
                $domain,
                $this->request->getData(),
            );
            if ($this->Domains->save($domain)) {
                $this->Flash->success(
                    __("The Award Domain Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $domain->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Award Domain could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("domain"));
    }

    /**
     * Delete method
     *
     * @param string|null $id domain id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $domain = $this->Domains->get(
            $id,
            contain: ["Awards"],
        );
        if (!$domain) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        if ($domain->awards) {
            $this->Flash->error(
                __("The Award Domain could not be deleted because it has associated Awards."),
            );
            return $this->redirect(["action" => "view", $domain->id]);
        }
        $this->Authorization->authorize($domain);
        $domain->name = "Deleted: " . $domain->name;
        if ($this->Domains->delete($domain)) {
            $this->Flash->success(
                __("The Award Domain has been deleted."),
            );
        } else {
            $this->Flash->error(
                __(
                    "The Award Domain could not be deleted. Please, try again.",
                ),
            );

            return $this->redirect(["action" => "view", $domain->id]);
        }

        return $this->redirect(["action" => "index"]);
    }
}
