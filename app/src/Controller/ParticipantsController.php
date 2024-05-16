<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\Time;

/**
 * Participants Controller
 *
 * @property \App\Model\Table\ParticipantsTable $Participants
 */
class ParticipantsController extends AppController
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
        $query = $this->Participants->find();
        $participants = $this->paginate($query);

        $this->set(compact('participants'));
    }

    /**
     * View method
     *
     * @param string|null $id Participant id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $participant = $this->Participants->get($id, contain: ['Roles', 'ParticipantAuthorizationTypes', 'PendingAuthorizations', 'PendingAuthorizationsToApprove']);
        $this->set(compact('participant'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $participant = $this->Participants->newEmptyEntity();
        if ($this->request->is('post')) {
            $participant = $this->Participants->patchEntity($participant, $this->request->getData());
            if ($this->Participants->save($participant)) {
                $this->Flash->success(__('The participant has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The participant could not be saved. Please, try again.'));
        }
        $roles = $this->Participants->Roles->find('list', limit: 200)->all();
        $this->set(compact('participant', 'roles'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Participant id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $participant = $this->Participants->get($id, contain: ['Roles']);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $participant = $this->Participants->patchEntity($participant, $this->request->getData());
            if ($this->Participants->save($participant)) {
                $this->Flash->success(__('The participant has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The participant could not be saved. Please, try again.'));
        }
        $roles = $this->Participants->Roles->find('list', limit: 200)->all();
        $this->set(compact('participant', 'roles'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Participant id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $participant = $this->Participants->get($id);
        if ($this->Participants->delete($participant)) {
            $this->Flash->success(__('The participant has been deleted.'));
        } else {
            $this->Flash->error(__('The participant could not be deleted. Please, try again.'));
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
                
                $user = $this->Participants->get($authentication->getIdentity()->getIdentifier());
                $this->Flash->success('Welcome '. $user->sca_name .'!');
                $page = $this->request->getQuery('redirect');
                if ($page == '/' || $page == '/participants/login' || $page == '/participants/logout' || $page == null) {
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
        return $this->redirect(['controller' => 'Participants', 'action' => 'login']);
    }
}
