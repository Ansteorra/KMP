<?php

declare(strict_types=1);

namespace Awards\Controller;

use Awards\Controller\AppController;

/**
 * Awards Controller
 *
 * @property \Awards\Model\Table\AwardsTable $Awards
 */
class AwardsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("index", "add");

        $this->Authentication->allowUnauthenticated([
            "awardsByDomain"
        ]);
    }
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->Awards->find()
            ->contain([
                'Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Levels' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ])
            ->select(['id', 'name', 'description', 'domain_id', 'level_id', 'branch_id', "Domains.name", "Levels.name", "Branches.name"]);
        $awards = $this->paginate($query);

        $this->set(compact('awards'));
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
        $award = $this->Awards->find()->where(['Awards.id' => $id])
            ->contain([
                'Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Levels' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ])
            ->first();

        if (!$award) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($award);

        $awardsDomains = $this->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Awards->Levels->find('list', limit: 200, orderBy: ["progression_order"])->all();
        $branches = $this->Awards->Branches
            ->find("treeList", spacer: "--")
            ->orderBy(["name" => "ASC"]);
        $this->set(compact('award', 'awardsDomains', 'awardsLevels', 'branches'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $award = $this->Awards->newEmptyEntity();
        if ($this->request->is('post')) {
            $award = $this->Awards->patchEntity($award, $this->request->getData());
            if ($this->Awards->save($award)) {
                $this->Flash->success(__('The award has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The award could not be saved. Please, try again.'));
        }
        $awardsDomains = $this->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Awards->Levels->find('list', limit: 200)->all();
        $branches = $this->Awards->Branches
            ->find("treeList", spacer: "--")
            ->orderBy(["name" => "ASC"]);
        $this->set(compact('award', 'awardsDomains', 'awardsLevels', 'branches'));
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
        $award = $this->Awards->get($id, contain: []);
        if (!$award) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($award);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $award = $this->Awards->patchEntity($award, $this->request->getData());
            $specialties = json_decode($this->request->getData('specialties'), true);
            $award->specialties = $specialties;
            if ($this->Awards->save($award)) {
                $this->Flash->success(__('The award has been saved.'));

                return $this->redirect(['action' => 'view', $award->id]);
            }
            $this->Flash->error(__('The award could not be saved. Please, try again.'));
        }
        return $this->redirect(['action' => 'view', $award->id]);
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
        $award = $this->Awards->get($id);
        if (!$award) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($award);

        $countRecommendations = $this->Awards->Recommendations->find()
            ->where(['award_id' => $award->id])
            ->count();
        if ($countRecommendations > 0) {
            $this->Flash->error(
                __('The award could not be deleted because it has {0} recommendations.', $countRecommendations)
            );
            return $this->redirect(['action' => 'view', $award->id]);
        }
        $award->name = "Deleted: " . $award->name;
        if ($this->Awards->delete($award)) {
            $this->Flash->success(__('The award has been deleted.'));
        } else {
            $this->Flash->error(__('The award could not be deleted. Please, try again.'));
            return $this->redirect(['action' => 'view', $award->id]);
        }

        return $this->redirect(['action' => 'index']);
    }

    public function awardsByDomain($domainId = null)
    {
        $this->Authorization->skipAuthorization();
        $awards = $this->Awards->find()
            ->where(['domain_id' => $domainId])
            ->contain([
                'Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Levels' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ])
            ->orderBy(["Levels.progression_order" => "ASC", "Awards.name" => "ASC"])
            ->all();
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($awards));
        return $this->response;
    }
}