<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\Time;

/**
 * Members Controller
 *
 * @property \App\Model\Table\MembersTable $Members
 */
class MembersController extends AppController
{

    /**
     * controller filters
     */
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
    
        $this->Authentication->allowUnauthenticated(['login']);
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->Members->find();
        $Members = $this->paginate($query);

        $this->set(compact('Members'));
    }

    /**
     * View method
     *
     * @param string|null $id Member id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $Member = $this->Members->get($id, contain: ['Roles', 'MemberAuthorizationTypes', 'PendingAuthorizations', 'PendingAuthorizationsToApprove']);
        $this->set(compact('Member'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $Member = $this->Members->newEmptyEntity();
        if ($this->request->is('post')) {
            $Member = $this->Members->patchEntity($Member, $this->request->getData());
            if ($this->Members->save($Member)) {
                $this->Flash->success(__('The Member has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The Member could not be saved. Please, try again.'));
        }
        $roles = $this->Members->Roles->find('list', limit: 200)->all();
        $this->set(compact('Member', 'roles'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Member id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $Member = $this->Members->get($id, contain: ['Roles']);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $Member = $this->Members->patchEntity($Member, $this->request->getData());
            if ($this->Members->save($Member)) {
                $this->Flash->success(__('The Member has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The Member could not be saved. Please, try again.'));
        }
        $roles = $this->Members->Roles->find('list', limit: 200)->all();
        $this->set(compact('Member', 'roles'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Member id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $Member = $this->Members->get($id);
        if ($this->Members->delete($Member)) {
            $this->Flash->success(__('The Member has been deleted.'));
        } else {
            $this->Flash->error(__('The Member could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * login logic
     */
    public function login()
    {
        $this->Authorization->skipAuthorization();
        if ($this->request->is('post')) {
            $authentication = $this->request->getAttribute('authentication');
            $result = $authentication->getResult();
            // regardless of POST or GET, redirect if user is logged in
            if ($result->isValid()) {
                
                $user = $this->Members->get($authentication->getIdentity()->getIdentifier());
                $this->Flash->success('Welcome '. $user->sca_name .'!');
                $page = $this->request->getQuery('redirect');
                if ($page == '/' || $page == '/Members/login' || $page == '/Members/logout' || $page == null) {
                    return $this->redirect(['action' => 'view', $user->id]);
                } else {
                    return $this->redirect($page);
                }
            }
            $errors = $result->getErrors();
            if (isset($errors['KMPBruteForcePassword']) && count($errors['KMPBruteForcePassword']) > 0) {
                $message = $errors['KMPBruteForcePassword'][0];
                switch($message) {
                    case 'Account Locked':
                        $this->Flash->error('Your account has been locked. Please try again later.');
                        break;
                    case 'Account Verification Pending':
                        $this->Flash->error('Your account is being verified. Please try again later.');
                        break;
                    default:
                        $this->Flash->error('Your email or password is incorrect.');
                        break;
                }
            } else {
                $this->Flash->error('Your email or password is incorrect.');
            }
        }
        
    }

    public function logout()
    {
        $this->Authorization->skipAuthorization();
        $this->Authentication->logout();
        return $this->redirect(['controller' => 'Members', 'action' => 'login']);
    }
}
