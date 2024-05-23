<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use App\KMP\PermissionsLoader;

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
    
        $this->Authentication->allowUnauthenticated(['login', 'approversList']);
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
        $member = $this->Members->get($id, contain: [
            'Roles', 
            'Branch',
            'Notes.Author',
            'Authorizations.AuthorizationType',
            'MemberRoles.Role',
            'MemberRoles.Approved_By'
        ]);
        if (!$this->Authorization->can($member, 'viewPrivateNotes')){
            // remove private notes
            $member->notes = array_filter($member->notes, function($note) {
                return !$note->private;
            });
        }
        $newNote = $this->Members->Notes->newEmptyEntity();
        $att = TableRegistry::getTableLocator()->get('AuthorizationTypes');
        $authorization_types = $att->find('list')
            ->where(['minimum_age <' => $member->age, 'maximum_age >' => $member->age]);
        $treeList = $this->Members->Branch->find('treeList', spacer: '--') -> order(['name' => 'ASC']);
        $this->set(compact('member', 'newNote', 'authorization_types','treeList'));
    }

    public function approversList($auth_id = null, $member_id = null)
    {
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Ajax');
        $query = $this->Members->getCurrentAuthorizationTypeApprovers($auth_id);
        $query = $query
            ->where(['Members.id !=' => $member_id])
            ->order(['Branch.name','Members.sca_name'])
            ->select(['id', 'sca_name','Branch.name'])->all();
        $this->response = $this->response->withType('application/json')
                                     ->withStringBody(json_encode($query));
        return $this->response;
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $member = $this->Members->newEmptyEntity();
        $this->Authorization->authorize($member);
        if ($this->request->is('post')) {
            $member = $this->Members->patchEntity($member, $this->request->getData());
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The Member has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The Member could not be saved. Please, try again.'));
        }
        $roles = $this->Members->Roles->find('list', limit: 200)->all();
        $this->set(compact('Member', 'roles'));
    }

    /**
     * Add Note method
     * 
     * @param string|null $id Member id.
     *  @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function addNote($id = null)
    {
        $member = $this->Members->get($id, contain: ['Notes']);
        $this->Authorization->authorize($member);
        $note = $this->Members->Notes->newEmptyEntity();
        if ($this->request->is('post')) {
            $note->topic_id = $member->id;
            $note->author_id = $this->Authentication->getIdentity()->getIdentifier();
            $note->body = $this->request->getData('body');
            if ($this->Authorization->can($member, 'viewPrivateNotes')){
                $note->private = $this->request->getData('private');
            } else {
                $note->private = false;
            }   
            $note->subject = $this->request->getData('subject');
            $note->topic_model = 'Members';
            $member->notes[] = $note;
            $member->setDirty('notes', true);
            if ($this->Members->Notes->save($note)) {
                $this->Flash->success(__('The Note has been saved.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(__('The Note could not be saved. Please, try again.'));
        }
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
        $member = $this->Members->get($id);
        $this->Authorization->authorize($member);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $member = $this->Members->patchEntity($member, $this->request->getData());
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The Member has been saved.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(__('The Member could not be saved. Please, try again.'));
        }
        //$this->redirect(['action' => 'view', $member->id]);
    }

    public function requestAuthorization($id = null)
    {
        //if id is null get it from the request
        if ($id == null) {
            $id = $this->request->getData('member_id');
        }   

        $member = $this->Members->get($id);
        $this->Authorization->authorize($member);
        $auth = $this->Members->Authorizations->newEmptyEntity();
        $auth->member_id = $id;
        $auth->authorization_type_id = $this->request->getData('authorization_type');
        $auth->requested_on = DateTime::now();
        $auth->status = 'new';
        if ($this->Members->Authorizations->save($auth)) {
            $approval = $this->Members->Authorizations->AuthorizationApprovals->newEmptyEntity();
            $approval->authorization_id = $auth->id;
            $approval->approver_id = $this->request->getData('approver_id');
            $approval->requested_on = DateTime::now();
            $approval->authorization_token = PermissionsLoader::generateToken();
            if ($this->Members->Authorizations->AuthorizationApprovals->save($approval)) {
                //$this->Members->Authorizations->AuthorizationApprovals->sendApprovalEmail($approval);
            }
            $this->Flash->success(__('The Authorization has been requested.'));

            return $this->redirect(['action' => 'view', $member->id]);
        }
        $this->Flash->error(__('The Authorization could not be requested. Please, try again.'));
        return $this->redirect(['action' => 'view', $member->id]);
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
        $member = $this->Members->get($id);
        $this->Authorization->authorize($member);
        if ($this->Members->delete($member)) {
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
