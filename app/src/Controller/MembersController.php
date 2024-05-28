<?php declare(strict_types=1);

namespace App\Controller;

use App\Form\ResetPasswordForm;
use App\KMP\PermissionsLoader;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use Cake\Mailer\MailerAwareTrait;

/**
 * Members Controller
 *
 * @property \App\Model\Table\MembersTable $Members
 */
class MembersController extends AppController
{
    use MailerAwareTrait;
    /**
     * controller filters
     */
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated(['login', 'approversList','forgotPassword','resetPassword']);
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $search = $this->request->getQuery('search');
        $search = ($search)?trim($search):null;
        //get sort and direction from query string
        $sort = $this->request->getQuery('sort');
        $direction = $this->request->getQuery('direction');

        $query = $this
            ->Members
            ->find()
            ->contain(['Branches'])
            ->select([
                'Members.id',
                'Members.sca_name',
                'Members.first_name',
                'Members.last_name',
                'Branches.name',
                'Members.hidden',
                'Members.email_address',
                'Members.last_login',
            ]);
        //if there is a search term, filter the query
        if ($search) {
            $query = $query
                ->where([
                    'OR' => [
                        'Members.sca_name LIKE' => '%' . $search . '%',
                        'Members.first_name LIKE' => '%' . $search . '%',
                        'Members.last_name LIKE' => '%' . $search . '%',
                        'Members.email_address LIKE' => '%' . $search . '%',
                        'Branches.name LIKE' => '%' . $search . '%',
                    ],
                ]);
        }
        //sort by branches.name manually if its in the query string
        if ($sort == 'Branches.name') {
            //check the direction of the sort
            if(!$direction){
                $direction = 'asc';
            }
            if (strtolower($direction) == 'asc'){
                $query = $query->order(['Branches.name' => 'ASC']);
            } else {
                $query = $query->order(['Branches.name' => 'DESC']);
            }
        }
        $query = $this->Authorization->applyScope($query);
        $Members = $this->paginate($query);

        $this->set(compact('Members', 'sort', 'direction', 'search'));
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
        // Get Member Details
        $member = $this
            ->Members
            ->find()
            ->contain([
                'Roles',
                'Branches',
                'MemberRoles' => function (SelectQuery $q) {
                    return $q->select(['start_on', 'ended_on', 'member_id']);
                },
                'Notes.Authors' => function (SelectQuery $q) {
                    return $q->select(['Authors.sca_name']);
                },
                'Authorizations.AuthorizationTypes' => function (SelectQuery $q) {
                    return $q->select(['AuthorizationTypes.name']);
                },
                'MemberRoles.Roles' => function (SelectQuery $q) {
                    return $q->select(['Roles.name']);
                },
                'MemberRoles.Approved_By' => function (SelectQuery $q) {
                    return $q->select(['Approved_By.sca_name']);
                },
            ])
            ->contain('Authorizations.AuthorizationApprovals.Approvers', function (SelectQuery $q) {
                return $q
                    ->select(['Approvers.sca_name'])
                    ->where(['AuthorizationApprovals.responded_on IS' => null]);
            })
            ->where(['Members.id' => $id])
            ->first();
        // Check to see if the current user can view private notes
        $this->Authorization->authorize($member);
        if (!$this->Authorization->can($member, 'viewPrivateNotes')) {
            // remove private notes
            $member->notes = array_filter($member->notes, function ($note) {
                return !$note->private;
            });
        }
        // Create the new Note form
        $newNote = $this->Members->Notes->newEmptyEntity();
        $att = TableRegistry::getTableLocator()->get('AuthorizationTypes');
        // Get the list of authorization types the member can request based on their age
        $authorization_types = $att
            ->find('list')
            ->where(['minimum_age <' => $member->age, 'maximum_age >' => $member->age]);
        $Session = $this->request->getSession();
        // Get the member form data for the edit modal
        $memberForm = $this->Members->get($id);
        // If there is form data in the session, patch the entity so we can show the errors
        $memberFormData = $Session->consume('memberFormData');
        if ($memberFormData != null) {
            $this->Members->patchEntity($memberForm, $memberFormData);
        }
        // Get the password reset form data for the change password modal so we can show errors
        $passwordResetData = $Session->consume('passwordResetData');
        $passwordReset = new ResetPasswordForm();
        if (!$passwordResetData == null) {
            $passwordReset->setData($passwordResetData);
            $passwordReset->validate($passwordResetData);
        }
        $treeList = $this->Members->Branches->find('treeList', spacer: '--')->order(['name' => 'ASC']);
        $this->set(compact('member', 'newNote', 'authorization_types', 'treeList', 'passwordReset', 'memberForm'));
    }

    public function viewCard($id = null)
    {
        $member = $this
            ->Members
            ->find()
            ->contain([
                'Branches' => function (SelectQuery $q) {
                    return $q->select(['Branches.name']);
                },
                'Authorizations' => function (SelectQuery $q) {
                    return $q->where(['Authorizations.status' => 'approved', 'Authorizations.expires_on >' => DateTime::now()]);
                },
                'Authorizations.AuthorizationTypes.AuthorizationGroups' => function (SelectQuery $q) {
                    return $q->select(['AuthorizationGroups.name']);
                },
                'Authorizations.AuthorizationTypes' => function (SelectQuery $q) {
                    return $q->select(['AuthorizationTypes.name']);
                }
            ])
            ->where(['Members.id' => $id])
            ->first();
        $this->Authorization->authorize($member);
        // sort filter out expired member roles
        $permissions = $member->getPermissions();
        $authTypes = [];
        foreach ($permissions as $permission) {
            if ($permission->authorization_type != null)
                $authTypes[] = $permission->authorization_type->name;
        }
        $authTypes = array_unique($authTypes);
        // sort by name
        sort($authTypes);
        $message_variables = [
            'secretary_email' => $this->appSettings->getAppSetting('Marshalate Secretary Email', 'please_set'),
            'kingdom' => $this->appSettings->getAppSetting('Kingdom Name', 'please_set'),
            'secratary' => $this->appSettings->getAppSetting('Marshalate Secretary Name', 'please_set'),
            'marshal_auth_graphic' => $this->appSettings->getAppSetting('Marshal Authorization Graphic', 'auth_card_back.gif'),
            'marshal_auth_header_color' => $this->appSettings->getAppSetting('Marshal Authorization Header Color', 'gold'),
        ];
        Log::Write('debug', implode(',', $message_variables));
        $this->set(compact('member', 'authTypes', 'message_variables'));
    }

    public function approversList($auth_id = null, $member_id = null)
    {
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(['get']);
        $this->viewBuilder()->setClassName('Ajax');
        $query = $this->Members->getCurrentAuthorizationTypeApprovers($auth_id);
        $query = $query
            ->where(['Members.id !=' => $member_id])
            ->order(['Branches.name', 'Members.sca_name'])
            ->select(['id', 'sca_name', 'Branches.name'])
            ->all();
        $this->response = $this
            ->response
            ->withType('application/json')
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
            if (!$member->getErrors()) {
                $treeList = $this->Members->Branches->find('treeList', spacer: '--')->order(['name' => 'ASC']);
                $this->set(compact('member', 'treeList'));
                return;
            }
            $member->hidden = false;
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The Member has been saved.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(__('The Member could not be saved. Please, try again.'));
        }

        $treeList = $this->Members->Branches->find('treeList', spacer: '--')->order(['name' => 'ASC']);
        $this->set(compact('member', 'treeList'));
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
            if ($this->Authorization->can($member, 'viewPrivateNotes')) {
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
            if ($member->getErrors()) {
                $Session = $this->request->getSession();
                $Session->write('memberFormData', $this->request->getData());
                return $this->redirect(['action' => 'view', $member->id]);
            }
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The Member has been saved.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(__('The Member could not be saved. Please, try again.'));
        }
        // $this->redirect(['action' => 'view', $member->id]);
    }

    public function partialEdit($id = null)
    {
        $member = $this->Members->get($id);
        $this->Authorization->authorize($member);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $member->sca_name = $this->request->getData('sca_name');
            $member->branch_id = $this->request->getData('branch_id');
            $member->first_name = $this->request->getData('first_name');
            $member->middle_name = $this->request->getData('middle_name');
            $member->last_name = $this->request->getData('last_name');
            $member->street_address = $this->request->getData('street_address');
            $member->city = $this->request->getData('city');
            $member->state = $this->request->getData('state');
            $member->zip = $this->request->getData('zip');
            $member->phone_number = $this->request->getData('phone_number');
            $member->email_address = $this->request->getData('email_address');
            $member->parent_name = $this->request->getData('parent_name');
            if ($member->getErrors()) {
                $Session = $this->request->getSession();
                $Session->write('memberFormData', $this->request->getData());
                return $this->redirect(['action' => 'view', $member->id]);
            }
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The Member has been saved.'));

                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(__('The Member could not be saved. Please, try again.'));
        }
        // $this->redirect(['action' => 'view', $member->id]);
    }

    public function changePassword($id = null)
    {
        $member = $this->Members->get($id);
        $this->Authorization->authorize($member);
        $passwordReset = new ResetPasswordForm();
        if ($this->request->is(['patch', 'post', 'put'])) {
            $passwordReset->validate($this->request->getData());
            if ($passwordReset->getErrors()) {
                $Session = $this->request->getSession();
                $Session->write('passwordResetData', $this->request->getData());
                return $this->redirect(['action' => 'view', $member->id]);
            }
            $member->password = $this->request->getData()['new_password'];
            $member->password_token = null;
            $member->password_token_expires_on = null;
            if ($this->Members->save($member)) {
                $this->Flash->success(__('The password has been changed.'));
                return $this->redirect(['action' => 'view', $member->id]);
            }
            $this->Flash->error(__('The password could not be changed. Please, try again.'));
        }
    }

    public function requestAuthorization($id = null)
    {
        // if id is null get it from the request
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
                // $this->Members->Authorizations->AuthorizationApprovals->sendApprovalEmail($approval);
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
                $this->Flash->success('Welcome ' . $user->sca_name . '!');
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
                switch ($message) {
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

    public function forgotPassword()
    {
        $this->Authorization->skipAuthorization();
        if ($this->request->is('post')) {
            $member = $this
                ->Members
                ->find()
                ->where(
                    ['email_address' => $this->request->getData('email_address')]
                )
                ->first();
            if ($member) {
                $member->password_token = PermissionsLoader::generateToken();
                $member->password_token_expires_on = DateTime::now()->addDays(1);
                $this->Members->save($member);
                $this->getMailer('KMP')->send('resetPassword', [$member]);
                $this->Flash->success(
                    __('Password reset request sent to ' . $member->email_address)
                );
                return $this->redirect(['action' => 'login']);
            } else {
                $this->Flash->error(
                    __('Your email was not found, please contact the Marshalate Secretary at ' . $this->getAppSetting->get('Marshalate Secretary Email'))
                );
            }
        }
    }

    public function resetPassword($token = null)
    {
        $this->Authorization->skipAuthorization();
        $member = $this->Members->find()
            ->where(['password_token' => $token])
            ->first();
        if ($member) {
            if ($member->password_token_expires_on < DateTime::now()) {
                $this->Flash->error(__(TOKEN_INVALID));
                return $this->redirect(['action' => 'forgotPassword']);
            }
            $passwordReset = new ResetPasswordForm();
            if ($this->request->is('post') 
                && ($passwordReset->validate($this->request->getData()))
            ) {
                $member->password = $this->request->getData("new_password");
                $member->password_token = null;
                $member->password_token_expires_on = null;
                $this->Members->save($member);
                $this->Flash->success(__('Password successfully reset'));
                return $this->redirect(['action' => 'login']);
            }
            $this->set('passwordReset', $passwordReset);
            
        } else {
            $this->Flash->error(__(TOKEN_INVALID));
            return $this->redirect(['action' => 'forgotPassword']);
        }
    }

    /**
     * Import Member Expiration dates from CSV based on Membership number
     */
    public function importExpirationDates(){
        if ($this->request->is('post')) {
            $this->Authorization->authorize($this->Members->newEmptyEntity());
            $file = $this->request->getData('importData');
            $file = $file->getStream()->getMetadata('uri');
            $csv = array_map('str_getcsv', file($file));
            $this->Members->getConnection()->begin();
            foreach ($csv as $row) {
                if('Member Number' == $row[0] || 'Expiration Date' == $row[1])
                    continue;

                $member = $this->Members->find()
                    ->where(['membership_number' => $row[0]])
                    ->first();
                if ($member) {
                    $member->membership_expires_on = new DateTime($row[1]);
                    $member->setDirty('membership_expires_on',true);
                    if(!$this->Members->save($member)){
                        $this->Members->getConnection()->rollback();
                        $this->Flash->error(__('Error saving member expiration date at ' . $row[0] . ' with date ' . $row[1] . '. All updated have been rolled back.'));
                        return;
                    }
                }
            }
            $this->Members->getConnection()->commit();
            $this->Flash->success(__('Expiration dates imported successfully'));
        }
    }

}
