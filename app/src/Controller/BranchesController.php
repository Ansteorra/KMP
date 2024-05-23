<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Database\Exception\DatabaseException;
use Cake\Log\Log;
/**
 * Branches Controller
 *
 * @property \App\Model\Table\BranchesTable $Branches
 */
class BranchesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index','add');
        $setting = $this->getTableLocator()->get('AppSettings')->find()->where(['name' => '_sys_branches_init'])->first();
        if(!$setting){
            $branches = $this->getTableLocator()->get('Branches');
            $branches->recover();
            $setting = $this->getTableLocator()->get('AppSettings')->newEmptyEntity();
            $setting->name = '_sys_branches_init';
            $setting->value = '1';
            $this->getTableLocator()->get('AppSettings')->save($setting);
        }
    }
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->Authorization->authorizeAction();
        $branches = $this->Branches
            ->find('threaded')->order(['name' => 'ASC'])
            ->toArray();
        $this->set(compact('branches'));
    }

    /**
     * View method
     *
     * @param string|null $id Branch id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $branch = $this->Branches->get($id, contain: ['Parent','Members']);
        $this->Authorization->authorize($branch);
        //get the children for the branch
        $branch->children = $this->Branches->find('children', ['for' => $branch->id])->toArray();
        $treeList = $this->Branches->find('treeList', spacer: '--') -> order(['name' => 'ASC']);
        $this->set(compact('branch', 'treeList'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $branch = $this->Branches->newEmptyEntity();
        if ($this->request->is('post')) {
            $branch = $this->Branches->patchEntity($branch, $this->request->getData());
            if ($this->Branches->save($branch)) {
                $this->Flash->success(__('The branch has been saved.'));

                return $this->redirect(['action' => 'view', $branch->id]);
            }
            $this->Flash->error(__('The branch could not be saved. Please, try again.'));
        }
        $treeList = $this->Branches->find('treeList', spacer: '--') -> order(['name' => 'ASC']);
        $this->set(compact('branch', 'treeList'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Branch id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $branch = $this->Branches->get($id);
        $this->Authorization->authorize($branch);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $branch = $this->Branches->patchEntity($branch, $this->request->getData());
            try{
                if ($this->Branches->save($branch)) {
                    $branches = $this->getTableLocator()->get('Branches');
                    $branches->recover();
                    $this->Flash->success(__('The branch has been saved.'));

                    return $this->redirect(['action' => 'view', $branch->id]);
                }
                $this->Flash->error(__('The branch could not be saved. Please, try again.'));
                return $this->redirect(['action' => 'view', $branch->id]);
            } catch( DatabaseException $e) {
                //if the error message starts with 'Cannot use node' then it is a tree error
                if(strpos($e->getMessage(), 'Cannot use node') === 0){
                    $this->Flash->error(__('The branch could not be saved, save would have created a circular reference.'));
                } else {
                    $this->Flash->error(__('The branch could not be saved. Please, try again. Error` {0}', $e->getMessage()));
                }
                return $this->redirect(['action' => 'view', $branch->id]);
            }
        }
        $treeList = $this->Branches->find('treeList', spacer: '--') -> order(['name' => 'ASC']);
        $this->set(compact('branch', 'treeList'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Branch id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $branch = $this->Branches->get($id);
        $this->Authorization->authorize($branch);
        if ($this->Branches->delete($branch)) {
            $this->Flash->success(__('The branch has been deleted.'));
        } else {
            $this->Flash->error(__('The branch could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
