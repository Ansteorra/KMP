<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\WarrantManager\WarrantManagerInterface;
use Cake\I18n\DateTime;
use Cake\Http\Exception\NotFoundException;


/**
 * WarrantRosters Controller
 *
 * @property \App\Model\Table\WarrantRostersTable $WarrantRosters
 * @property \Authorization\Controller\Component\AuthorizationComponent $Authorization
 */
class WarrantRostersController extends AppController
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
        $query = $this->WarrantRosters->find()
            ->contain(['CreatedByMember' => function ($q) {
                return $q->select(["id", "sca_name"]);
            }]);

        $query = $query->matching('Warrants')
            ->select(['id', 'name', 'status', 'approvals_required', 'approval_count', 'created', 'warrant_count' => $query->func()->count('Warrants.id')])
            ->groupBy(['WarrantRosters.id']);

        $query = $this->Authorization->applyScope($query);
        $warrantRosters = $this->paginate($query);

        $this->set(compact('warrantRosters'));
    }

    /**
     * View method
     *
     * @param string|null $id Warrant Approval Set id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $warrantRoster = $this->WarrantRosters->find()
            ->where(['WarrantRosters.id' => $id])
            ->contain([
                'WarrantRosterApprovals' => function ($q) {
                    return $q->orderBy(['approved_on' => 'ASC']);
                },
                'Warrants',
                'Warrants.Members' => function ($q) {
                    return $q->select(["id", "sca_name"]);
                },
                'WarrantRosterApprovals.Members' => function ($q) {
                    return $q->select(["id", "sca_name"]);
                },
                'CreatedByMember' => function ($q) {
                    return $q->select(["id", "sca_name"]);
                }
            ])
            ->first();
        $this->Authorization->authorize($warrantRoster);
        $this->set(compact('warrantRoster'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $warrantRoster = $this->WarrantRosters->newEmptyEntity();
        $this->Authorization->authorize($warrantRoster);
        if ($this->request->is('post')) {
            $warrantRoster = $this->WarrantRosters->patchEntity($warrantRoster, $this->request->getData());
            if ($this->WarrantRosters->save($warrantRoster)) {
                $this->Flash->success(__('The warrant approval set has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant approval set could not be saved. Please, try again.'));
        }
        $this->set(compact('warrantRoster'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Warrant Approval Set id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $warrantRoster = $this->WarrantRosters->get($id, contain: []);
        $this->Authorization->authorize($warrantRoster);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $warrantRoster = $this->WarrantRosters->patchEntity($warrantRoster, $this->request->getData());
            if ($this->WarrantRosters->save($warrantRoster)) {
                $this->Flash->success(__('The warrant approval set has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant approval set could not be saved. Please, try again.'));
        }
        $this->set(compact('warrantRoster'));
    }

    function approve(WarrantManagerInterface $wManager, $id = null)
    {
        $this->request->allowMethod(['post']);
        $warrantRoster = $this->WarrantRosters->get($id, ['contain' => ['Warrants']]);
        if ($warrantRoster == null) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($warrantRoster);
        $wmResult = $wManager->approve($warrantRoster->id, $this->Authentication->getIdentity()->getIdentifier());
        if ($wmResult->success) {
            $this->Flash->success(__('The approval has been been processed.'));
        } else {
            $this->Flash->error(__($wmResult->reason));
        }
        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Delete method
     *
     * @param string|null $id Warrant Approval Set id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $warrantRoster = $this->WarrantRosters->get($id);
        $this->Authorization->authorize($warrantRoster);
        if ($this->WarrantRosters->delete($warrantRoster)) {
            $this->Flash->success(__('The warrant approval set has been deleted.'));
        } else {
            $this->Flash->error(__('The warrant approval set could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}