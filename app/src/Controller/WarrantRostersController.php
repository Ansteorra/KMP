<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\WarrantManager\WarrantManagerInterface;
use Cake\I18n\DateTime;
use Cake\Http\Exception\NotFoundException;
use App\Model\Entity\WarrantRoster;


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
        $this->Authorization->authorizeModel("index");
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index() {}

    public function allRosters($state)
    {
        $query = $this->WarrantRosters->find()
            ->contain(['CreatedByMember' => function ($q) {
                return $q->select(["id", "sca_name"]);
            }]);

        $query = $query->matching('Warrants')
            ->select(['id', 'name', 'status', 'approvals_required', 'approval_count', 'created', 'warrant_count' => $query->func()->count('Warrants.id')])
            ->groupBy(['WarrantRosters.id']);
        $query = $query->where(['WarrantRosters.status' => $state]);
        $query = $this->Authorization->applyScope($query);
        $warrantRosters = $this->paginate($query);

        $this->set(compact('warrantRosters'));
    }






    /**
     * View method
     *
     * @param string|null $id Warrant Roster id.
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
                'Warrants' => function ($q) {
                    return $q->orderBy(['Warrants.created' => 'ASC']);
                },
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
     * @param string|null $id Warrant Roster id.
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
     * @param string|null $id Warrant Roster id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function decline(WarrantManagerInterface $wManager, $id = null)
    {
        $this->request->allowMethod(['post']);
        $warrantRoster = $this->WarrantRosters->get($id, ['contain' => ['Warrants']]);
        if ($warrantRoster == null) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($warrantRoster);
        $wmResult = $wManager->decline($warrantRoster->id, $this->Authentication->getIdentity()->getIdentifier(), "Declined from Warrant Roster View");
        if ($wmResult->success) {
            $this->Flash->success(__('The declination has been been processed.'));
        } else {
            $this->Flash->error(__($wmResult->reason));
        }
        return $this->redirect(['action' => 'view', $id]);
    }

    public function declineWarrantInRoster(WarrantManagerInterface $wService, $roster_id, $warrant_id = null)
    {
        $this->request->allowMethod(["post"]);
        if (!$roster_id) {
            $roster_id = $this->request->getData("roster_id");
        }
        if (!$warrant_id) {
            $warrant_id = $this->request->getData("warrant_id");
        }
        //check if the warrant exists in that roster
        $warrant = $this->WarrantRosters->Warrants->find()
            ->where(['id' => $warrant_id, 'warrant_roster_id' => $roster_id])
            ->first();
        if ($warrant == null) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($warrant);
        $wResult = $wService->declineSingleWarrant((int)$warrant_id, "Declined Warrant", $this->Authentication->getIdentity()->get("id"));
        if (!$wResult->success) {
            $this->Flash->error($wResult->reason);
            return $this->redirect($this->referer());
        }

        $this->Flash->success(__("The warrant has been deactivated. If this warrant is associated with an office, the officer has been released however they have not been notified.  Please notify them at your earliest convienence."));
        return $this->redirect($this->referer());
    }
}
