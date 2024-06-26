<?php

declare(strict_types=1);

namespace Awards\Controller;

use Awards\Controller\AppController;

/**
 * Awards Controller
 *
 * @property \Awards\Model\Table\AwardsTable $Awards
 */
class EventsController extends AppController
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
        $query = $this->Events->find()
            ->contain([
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ])
            ->select(['id', 'name', 'start_date', 'end_date', 'branch_id', 'Branches.name']);
        $events = $this->paginate($query);

        $this->set(compact('events'));
    }

    /**
     * View method
     *
     * @param string|null $id Award id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $event = $this->Events->find()->where(['Events.id' => $id])
            ->contain([
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ])
            ->first();

        if (!$event) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($event);

        $branches = $this->Events->Branches
            ->find("treeList", spacer: "--")
            ->orderBy(["name" => "ASC"]);
        $this->set(compact('event', 'branches'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $event = $this->Events->newEmptyEntity();
        if ($this->request->is('post')) {
            $event = $this->Events->patchEntity($event, $this->request->getData());
            $event->start_date = $this->request->getData('start_date');
            $event->end_date = $this->request->getData('end_date');
            if ($this->Events->save($event)) {
                $this->Flash->success(__('The Event has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The Event could not be saved. Please, try again.'));
        }
        $branches = $this->Events->Branches
            ->find("treeList", spacer: "--")
            ->orderBy(["name" => "ASC"]);
        $this->set(compact('event', 'branches'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Award id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $event = $this->Events->get($id, contain: []);
        if (!$event) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($event);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $event = $this->Events->patchEntity($event, $this->request->getData());
            $event->start_date = $this->request->getData('start_date');
            $event->end_date = $this->request->getData('end_date');
            if ($this->Events->save($event)) {
                $this->Flash->success(__('The Event has been saved.'));

                return $this->redirect(['action' => 'view', $event->id]);
            }
            $this->Flash->error(__('The Event could not be saved. Please, try again.'));
        }
        return $this->redirect(['action' => 'view', $event->id]);
    }

    /**
     * Delete method
     *
     * @param string|null $id Award id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $event = $this->Events->get($id);
        if (!$event) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($event);

        $event->name = "Deleted: " . $event->name;
        if ($this->Events->delete($event)) {
            $this->Flash->success(__('The Event has been deleted.'));
        } else {
            $this->Flash->error(__('The Event could not be deleted. Please, try again.'));
            return $this->redirect(['action' => 'view', $event->id]);
        }

        return $this->redirect(['action' => 'index']);
    }
}