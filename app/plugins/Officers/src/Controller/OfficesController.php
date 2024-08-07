<?php

declare(strict_types=1);

namespace Officers\Controller;

/**
 * Offices Controller
 *
 * @property \App\Model\Table\OfficesTable $Offices
 */
class OfficesController extends AppController
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
        $query = $this->Offices->find()
            ->contain(['Departments' => function ($q) {
                return $q->select(['id', 'name']);
            }, 'GrantsRole' => function ($q) {
                return $q->select(['id', 'name']);
            }, 'DeputyTo' => function ($q) {
                return $q->select(['id', 'name']);
            }]);

        $offices = $this->paginate($query, [
            'order' => [
                'name' => 'asc',
            ]
        ]);

        $this->set(compact('offices'));
    }

    /**
     * View method
     *
     * @param string|null $id Office id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $office = $this->Offices->get(
            $id,
            contain: ['Departments' => function ($q) {
                return $q->select(['id', 'name']);
            }, 'GrantsRole' => function ($q) {
                return $q->select(['id', 'name']);
            }, 'DeputyTo' => function ($q) {
                return $q->select(['id', 'name']);
            }]
        );
        if (!$office) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($office);
        $departments = $this->Offices->Departments->find('list', limit: 200)->all();
        $offices = $this->Offices->find('list', limit: 200)->all();
        $roles = $this->Offices->GrantsRole->find('list', limit: 200)->all();
        $this->set(compact('office', 'departments', 'offices', 'roles'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $office = $this->Offices->newEmptyEntity();
        $this->Authorization->authorize($office);
        if ($this->request->is('post')) {
            $office = $this->Offices->patchEntity($office, $this->request->getData());
            if ($this->Offices->save($office)) {
                $this->Flash->success(__('The office has been saved.'));

                return $this->redirect(['action' => 'view', $office['id']]);
            }
            $this->Flash->error(__('The office could not be saved. Please, try again.'));
        }
        $departments = $this->Offices->Departments->find('list', limit: 200)->all();
        $offices = $this->Offices->find('list')->where(["deputy_to_id IS" => null])->all();
        $roles = $this->Offices->GrantsRole->find('list', limit: 200)->all();
        $this->set(compact('office', 'departments', 'offices', 'roles'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Office id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $office = $this->Offices->get($id, contain: []);

        if (!$office) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($office);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $office = $this->Offices->patchEntity($office, $this->request->getData());
            if ($this->Offices->save($office)) {
                $this->Flash->success(__('The office has been saved.'));
            } else {
                $this->Flash->error(__('The office could not be saved. Please, try again.'));
            }
        }
        return $this->redirect(['action' => 'view', $office['id']]);
    }

    /**
     * Delete method
     *
     * @param string|null $id Office id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $office = $this->Offices->get($id);
        if (!$office) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($office);
        $office->name = "Deleted: " . $office->name;
        if ($this->Offices->delete($office)) {
            $this->Flash->success(__('The office has been deleted.'));
        } else {
            $this->Flash->error(__('The office could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    public function availableOfficesForBranch($id = null)
    {
        $branch = $this->getTableLocator()->get("Branches")
            ->find()->select(['id', 'parent_id'])
            ->where(['id' => $id])->first();
        if (!$branch) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($branch);
        $officesTbl = $this->Offices;
        $officeQuery = $officesTbl->find("all")
            ->contain(["Deputies" => function ($q) {
                return $q
                    ->select(["id", "name", "deputy_to_id"]);
            }])
            ->select(["id", "name", "deputy_to_id"])
            ->orderBY(["name" => "ASC"]);
        if ($branch->parent_id != null) {
            $officeQuery = $officeQuery->where(['kingdom_only' => false]);
        }
        $offices = $officeQuery->toArray();
        $this->viewBuilder()->setClassName("Ajax");
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($offices));

        return $this->response;
    }
}