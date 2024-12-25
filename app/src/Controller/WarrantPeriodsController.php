<?php

declare(strict_types=1);

namespace App\Controller;

/**
 * WarrantPeriods Controller
 *
 * @property \App\Model\Table\WarrantPeriodsTable $WarrantPeriods
 * @property \Authorization\Controller\Component\AuthorizationComponent $Authorization
 */
class WarrantPeriodsController extends AppController
{
    /**
     * Initialize controller
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Authorization.Authorization');
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->WarrantPeriods->find();
        $query = $this->Authorization->applyScope($query);
        $warrantPeriods = $this->paginate($query, [
            "order" => ["start_date" => "DESC"]
        ]);
        $emptyWarrantPeriod = $this->WarrantPeriods->newEmptyEntity();
        $this->set(compact('warrantPeriods', 'emptyWarrantPeriod'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $warrantPeriod = $this->WarrantPeriods->newEmptyEntity();
        $this->Authorization->authorize($warrantPeriod);
        if ($this->request->is('post')) {
            $warrantPeriod = $this->WarrantPeriods->patchEntity($warrantPeriod, $this->request->getData());
            if ($this->WarrantPeriods->save($warrantPeriod)) {
                $this->Flash->success(__('The warrant period has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant period could not be saved. Please, try again.'));
        }
        $this->set(compact('warrantPeriod'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Warrant Period id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $warrantPeriod = $this->WarrantPeriods->get($id);
        $this->Authorization->authorize($warrantPeriod);
        if ($this->WarrantPeriods->delete($warrantPeriod)) {
            $this->Flash->success(__('The warrant period has been deleted.'));
        } else {
            $this->Flash->error(__('The warrant period could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}