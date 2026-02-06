<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\ServicePrincipal;
use App\Model\Entity\ServicePrincipalToken;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;

/**
 * ServicePrincipals Controller - Admin Management
 *
 * Provides web interface for managing service principals, their roles, and tokens.
 *
 * @property \App\Model\Table\ServicePrincipalsTable $ServicePrincipals
 */
class ServicePrincipalsController extends AppController
{
    /**
     * Initialize controller and configure authorization.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel(
            'index',
            'add',
            'credentials',
            'regenerateToken',
            'revokeToken',
            'addRole',
            'revokeRole',
            'toggleActive'
        );
    }

    /**
     * Index method - list all service principals.
     *
     * @return void
     */
    public function index(): void
    {
        $servicePrincipals = $this->paginate($this->ServicePrincipals->find()
            ->contain(['CreatedByMembers'])
            ->orderBy(['ServicePrincipals.name' => 'ASC']));

        $this->set(compact('servicePrincipals'));
    }

    /**
     * View method - show service principal details.
     *
     * @param int $id Service principal ID
     * @return void
     */
    public function view(int $id): void
    {
        $servicePrincipal = $this->ServicePrincipals->get($id, contain: [
            'ServicePrincipalRoles' => [
                'Roles',
                'Branches',
                'ApprovedBy',
            ],
            'ServicePrincipalTokens',
            'ServicePrincipalAuditLogs' => function ($q) {
                return $q->orderBy(['ServicePrincipalAuditLogs.created' => 'DESC'])->limit(50);
            },
            'CreatedByMembers',
            'ModifiedByMembers',
        ]);

        $this->Authorization->authorize($servicePrincipal, 'view');

        // Get current roles only
        $now = DateTime::now();
        $currentRoles = [];
        $expiredRoles = [];

        foreach ($servicePrincipal->service_principal_roles as $role) {
            if ($role->revoked_on !== null) {
                $expiredRoles[] = $role;
            } elseif ($role->expires_on !== null && $role->expires_on->isPast()) {
                $expiredRoles[] = $role;
            } elseif ($role->start_on->isFuture()) {
                $currentRoles[] = $role; // Upcoming roles shown with current
            } else {
                $currentRoles[] = $role;
            }
        }

        // Available roles for adding
        $roles = $this->fetchTable('Roles')->find('list', keyField: 'id', valueField: 'name')
            ->orderBy(['name' => 'ASC']);

        // Available branches for scoping
        $branches = $this->fetchTable('Branches')->find('list', keyField: 'id', valueField: 'name')
            ->orderBy(['name' => 'ASC']);

        $this->set(compact('servicePrincipal', 'currentRoles', 'expiredRoles', 'roles', 'branches'));
    }

    /**
     * Add method - create new service principal.
     *
     * @return \Cake\Http\Response|null
     */
    public function add()
    {
        $servicePrincipal = $this->ServicePrincipals->newEmptyEntity();

        if ($this->request->is('post')) {
            $data = $this->request->getData();

            // Generate credentials
            $clientId = ServicePrincipal::generateClientId();
            $clientSecret = ServicePrincipal::generateClientSecret();

            // Patch entity with user data
            $servicePrincipal = $this->ServicePrincipals->patchEntity($servicePrincipal, $data);

            // Set credentials directly (not mass-assignable for security)
            $servicePrincipal->set('client_id', $clientId);
            $servicePrincipal->set('client_secret_hash', ServicePrincipal::hashSecret($clientSecret));

            if ($this->ServicePrincipals->save($servicePrincipal)) {
                // Generate initial token
                $token = ServicePrincipalToken::generateToken();
                $tokenEntity = $this->fetchTable('ServicePrincipalTokens')->newEntity([
                    'service_principal_id' => $servicePrincipal->id,
                    'name' => 'Initial Token',
                ]);
                $tokenEntity->set('token_hash', ServicePrincipalToken::hashToken($token));
                if (!$this->fetchTable('ServicePrincipalTokens')->save($tokenEntity)) {
                    $this->Flash->error(__('Service principal created but initial token could not be saved. Generate a new token manually.'));
                    return $this->redirect(['action' => 'view', $servicePrincipal->id]);
                }

                // Store credentials in session to display once
                $this->request->getSession()->write('ServicePrincipal.newCredentials', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'bearer_token' => $token,
                ]);

                $this->Flash->success(__('Service principal created. Save the credentials shown below - they will not be displayed again.'));

                return $this->redirect(['action' => 'credentials', $servicePrincipal->id]);
            }

            $this->Flash->error(__('Could not create service principal. Please try again.'));
        }

        $this->set(compact('servicePrincipal'));

        return null;
    }

    /**
     * Credentials method - display newly created credentials (one-time).
     *
     * @param int $id Service principal ID
     * @return \Cake\Http\Response|null
     */
    public function credentials(int $id)
    {
        $servicePrincipal = $this->ServicePrincipals->get($id);
        $this->Authorization->authorize($servicePrincipal, 'view');

        $credentials = $this->request->getSession()->consume('ServicePrincipal.newCredentials');

        if (!$credentials) {
            $this->Flash->warning(__('Credentials are only shown once after creation.'));

            return $this->redirect(['action' => 'view', $id]);
        }

        $this->set(compact('servicePrincipal', 'credentials'));

        return null;
    }

    /**
     * Edit method - modify service principal details.
     *
     * @param int $id Service principal ID
     * @return \Cake\Http\Response|null
     */
    public function edit(int $id)
    {
        $servicePrincipal = $this->ServicePrincipals->get($id);
        $this->Authorization->authorize($servicePrincipal, 'edit');

        if ($this->request->is(['patch', 'post', 'put'])) {
            // Don't allow changing client_id or client_secret through edit
            $data = $this->request->getData();
            unset($data['client_id'], $data['client_secret_hash']);

            $servicePrincipal = $this->ServicePrincipals->patchEntity($servicePrincipal, $data);

            if ($this->ServicePrincipals->save($servicePrincipal)) {
                $this->Flash->success(__('Service principal updated.'));

                return $this->redirect(['action' => 'view', $id]);
            }

            $this->Flash->error(__('Could not update service principal. Please try again.'));
        }

        $this->set(compact('servicePrincipal'));

        return null;
    }

    /**
     * Delete method - remove service principal.
     *
     * @param int $id Service principal ID
     * @return \Cake\Http\Response
     */
    public function delete(int $id)
    {
        $this->request->allowMethod(['post', 'delete']);

        $servicePrincipal = $this->ServicePrincipals->get($id);
        $this->Authorization->authorize($servicePrincipal, 'delete');

        if ($this->ServicePrincipals->delete($servicePrincipal)) {
            $this->Flash->success(__('Service principal deleted.'));
        } else {
            $this->Flash->error(__('Could not delete service principal. Please try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Regenerate token - create new bearer token.
     *
     * @param int $id Service principal ID
     * @return \Cake\Http\Response|null
     */
    public function regenerateToken(int $id)
    {
        $this->request->allowMethod(['post']);

        $servicePrincipal = $this->ServicePrincipals->get($id);
        $this->Authorization->authorize($servicePrincipal, 'edit');

        // Generate new token
        $token = ServicePrincipalToken::generateToken();
        $tokenName = $this->request->getData('name') ?: 'Token ' . DateTime::now()->toDateTimeString();

        $tokenEntity = $this->fetchTable('ServicePrincipalTokens')->newEntity([
            'service_principal_id' => $servicePrincipal->id,
            'name' => $tokenName,
            'expires_at' => $this->request->getData('expires_at'),
        ]);
        $tokenEntity->set('token_hash', ServicePrincipalToken::hashToken($token));

        if ($this->fetchTable('ServicePrincipalTokens')->save($tokenEntity)) {
            // Store token in session to display once
            $this->request->getSession()->write('ServicePrincipal.newToken', [
                'bearer_token' => $token,
                'name' => $tokenName,
            ]);

            $this->Flash->success(__('New token created. Save it now - it will not be shown again.'));
        } else {
            $this->Flash->error(__('Could not create token.'));
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Revoke token - invalidate a bearer token.
     *
     * @param int $id Service principal ID
     * @param int $tokenId Token ID
     * @return \Cake\Http\Response
     */
    public function revokeToken(int $id, int $tokenId)
    {
        $this->request->allowMethod(['post', 'delete']);

        $servicePrincipal = $this->ServicePrincipals->get($id);
        $this->Authorization->authorize($servicePrincipal, 'edit');

        $tokensTable = $this->fetchTable('ServicePrincipalTokens');
        $token = $tokensTable->find()
            ->where([
                'id' => $tokenId,
                'service_principal_id' => $id,
            ])
            ->first();

        if (!$token) {
            throw new NotFoundException('Token not found');
        }

        if ($tokensTable->delete($token)) {
            $this->Flash->success(__('Token revoked.'));
        } else {
            $this->Flash->error(__('Could not revoke token.'));
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Add role to service principal.
     *
     * @param int $id Service principal ID
     * @return \Cake\Http\Response|null
     */
    public function addRole(int $id)
    {
        $this->request->allowMethod(['post']);

        $servicePrincipal = $this->ServicePrincipals->get($id);
        $this->Authorization->authorize($servicePrincipal, 'edit');

        $rolesTable = $this->fetchTable('ServicePrincipalRoles');
        $role = $rolesTable->newEntity([
            'service_principal_id' => $id,
            'role_id' => $this->request->getData('role_id'),
            'branch_id' => $this->request->getData('branch_id') ?: null,
            'start_on' => $this->request->getData('start_on') ?: DateTime::now(),
            'expires_on' => $this->request->getData('expires_on') ?: null,
            'approver_id' => $this->Authentication->getIdentity()->getIdentifier(),
        ]);

        if ($rolesTable->save($role)) {
            $this->Flash->success(__('Role assigned to service principal.'));
        } else {
            $this->Flash->error(__('Could not assign role. Please check the form.'));
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Revoke role from service principal.
     *
     * @param int $id Service principal ID
     * @param int $roleId ServicePrincipalRole ID
     * @return \Cake\Http\Response
     */
    public function revokeRole(int $id, int $roleId)
    {
        $this->request->allowMethod(['post', 'delete']);

        $servicePrincipal = $this->ServicePrincipals->get($id);
        $this->Authorization->authorize($servicePrincipal, 'edit');

        $rolesTable = $this->fetchTable('ServicePrincipalRoles');
        $role = $rolesTable->find()
            ->where([
                'id' => $roleId,
                'service_principal_id' => $id,
            ])
            ->first();

        if (!$role) {
            throw new NotFoundException('Role assignment not found');
        }

        $role->revoked_on = DateTime::now();
        $role->revoker_id = $this->Authentication->getIdentity()->getIdentifier();

        if ($rolesTable->save($role)) {
            $this->Flash->success(__('Role revoked.'));
        } else {
            $this->Flash->error(__('Could not revoke role.'));
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Toggle active status.
     *
     * @param int $id Service principal ID
     * @return \Cake\Http\Response
     */
    public function toggleActive(int $id)
    {
        $this->request->allowMethod(['post']);

        $servicePrincipal = $this->ServicePrincipals->get($id);
        $this->Authorization->authorize($servicePrincipal, 'edit');

        $servicePrincipal->is_active = !$servicePrincipal->is_active;

        if ($this->ServicePrincipals->save($servicePrincipal)) {
            $status = $servicePrincipal->is_active ? 'activated' : 'deactivated';
            $this->Flash->success(__('Service principal {0}.', $status));
        } else {
            $this->Flash->error(__('Could not update status.'));
        }

        return $this->redirect(['action' => 'view', $id]);
    }
}
