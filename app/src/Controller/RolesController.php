<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Permission;
use App\Services\CsvExportService;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

/**
 * Manages RBAC roles: CRUD, permission assignment, and member management.
 *
 * Handles branch-scoped roles, temporal assignments (current/upcoming/previous),
 * and provides member search for role assignment. System roles are protected.
 *
 * @property \App\Model\Table\RolesTable $Roles
 */
class RolesController extends AppController
{
    use DataverseGridTrait;

    /**
     * Configure authorization for role management actions.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel(
            'index',
            'add',
            'searchMembers',
            'gridData',
        );
    }

    /**
     * Index method - Display Dataverse grid for roles
     *
     * Renders the roles grid page which uses lazy-loading turbo-frame
     * to load the actual grid data via the gridData action.
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Simple index page - just renders the dv_grid element
        // The dv_grid element will lazy-load the actual data via gridData action
    }

    /**
     * Grid Data method - Provides Dataverse grid data for roles
     *
     * Returns grid content with toolbar and table for the roles grid.
     * Handles both outer frame (toolbar + table frame) and inner frame
     * (table only) requests. Also supports CSV export.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Build query with current member count subquery (only active assignments)
        $memberRolesTable = TableRegistry::getTableLocator()->get('MemberRoles');
        $now = new \Cake\I18n\DateTime();
        $memberCountSubquery = $memberRolesTable->find()
            ->select(['count' => $memberRolesTable->find()->func()->count('*')])
            ->where([
                'MemberRoles.role_id = Roles.id',
                'MemberRoles.start_on <=' => $now,
                'OR' => [
                    'MemberRoles.expires_on >=' => $now,
                    'MemberRoles.expires_on IS' => null,
                ],
            ]);

        $baseQuery = $this->Roles->find()
            ->select($this->Roles)
            ->select(['member_count' => $memberCountSubquery]);

        // Get system views from GridColumns
        $systemViews = \App\KMP\GridColumns\RolesGridColumns::getSystemViews([]);

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Roles.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\RolesGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Roles',
            'defaultSort' => ['Roles.name' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => true,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'roles');
        }

        // Set view variables
        $this->set([
            'roles' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\RolesGridColumns::getSearchableColumns(),
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $result['currentFilters'],
            'currentSearch' => $result['currentSearch'],
            'currentView' => $result['currentView'],
            'availableViews' => $result['availableViews'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
        ]);

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        if ($turboFrame === 'roles-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'roles-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'roles-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * View method - Display detailed role information with assignment interface
     *
     * Shows comprehensive role details including associated permissions, member
     * assignments, and provides interfaces for managing role assignments and
     * permissions. Handles branch-scoped roles and temporal assignment tracking.
     *
     * @param string|null $id Role id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view(?string $id = null)
    {
        // Load role with associated permissions for detailed view
        $role = $this->Roles->get(
            $id,
            contain: [
                'Permissions',  // Include all permissions assigned to this role
            ],
        );

        if (!$role) {
            throw new NotFoundException();
        }

        // Verify user has permission to view this specific role
        $this->Authorization->authorize($role);

        // Analyze permission scoping requirements
        // Check if any permissions require branch assignment
        $branch_required = false;
        foreach ($role->permissions as $permission) {
            if ($permission->scoping_rule != Permission::SCOPE_GLOBAL) {
                $branch_required = true;
                break;
            }
        }

        // Load branch tree if role requires branch assignments
        $branches = [];
        if ($branch_required) {
            $branches = TableRegistry::getTableLocator()
                ->get('Branches')
                ->find('treeList', spacer: '--')  // Hierarchical list format
                ->orderBy(['name' => 'ASC']);
        }

        // Calculate assignment statistics for role overview
        $currentMembersCount = $this->Roles->MemberRoles->find('current')
            ->where(['role_id' => $id])
            ->count();

        $upcomingMembersCount = $this->Roles->MemberRoles->find('upcoming')
            ->where(['role_id' => $id])
            ->count();

        $previousMembersCount = $this->Roles->MemberRoles->find('previous')
            ->where(['role_id' => $id])
            ->count();

        // Determine if role has never been assigned (for deletion safety)
        $isEmpty = ($currentMembersCount + $upcomingMembersCount + $previousMembersCount) == 0;

        // Build list of permissions not currently assigned to this role
        // This provides the interface for adding new permission assignments
        $currentPermissionIds = [];
        foreach ($role->permissions as $permission) {
            $currentPermissionIds[] = $permission->id;
        }

        $permissions = [];
        if (count($currentPermissionIds) > 0) {
            $permissions = $this->Roles->Permissions
                ->find('list')
                ->where(['NOT' => ['id IN' => $currentPermissionIds]])  // Exclude assigned
                ->all();
        } else {
            // No permissions assigned yet, show all available permissions
            $permissions = $this->Roles->Permissions->find('list')->all();
        }

        // Load branch hierarchy for descendant operations
        $branchTree = TableRegistry::getTableLocator()
            ->get('Branches')
            ->getAllDecendentIds(1);  // Get all branches under root

        $this->set(compact(
            'role',
            'permissions',
            'id',
            'isEmpty',
            'branches',
            'branch_required',
            'branchTree'
        ));
    }

    /**
     * Add method - Create new role
     *
     * Handles the creation of new roles with appropriate security controls
     * and validation. Prevents creation of system roles and provides
     * interface for initial permission assignment.
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $role = $this->Roles->newEmptyEntity();

        // Verify user has permission to create roles
        $this->Authorization->authorizeAction();

        if ($this->request->is('post')) {
            // Patch entity with submitted data
            $role = $this->Roles->patchEntity($role, $this->request->getData());

            // Security control: new roles are never system roles
            $role->is_system = false;

            if ($this->Roles->save($role)) {
                $this->Flash->success(__('The role has been saved.'));

                // Redirect to view page to continue configuration
                return $this->redirect(['action' => 'view', $role->id]);
            }

            $this->Flash->error(
                __('The role could not be saved. Please, try again.'),
            );
        }

        // Load permissions for initial assignment interface
        $permissions = $this->Roles->Permissions->find('list')->all();

        $this->set(compact('role', 'permissions'));
    }

    /**
     * Edit method - Modify existing role properties
     *
     * Allows editing of role name and description while maintaining security
     * controls. System roles cannot be modified to prevent accidental damage
     * to critical security infrastructure.
     *
     * @param string|null $id Role id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit(?string $id = null)
    {
        // Load role with all associations for editing interface
        $role = $this->Roles->get(
            $id,
            contain: [
                'MemberRoles.Members',      // Member assignment history
                'MemberRoles.ApprovedBy',   // Approval tracking
                'Permissions',              // Associated permissions
            ],
        );

        // Security check: system roles and missing roles cannot be edited
        if (!$role || $role->is_system) {
            throw new NotFoundException();
        }

        // Verify user has permission to edit this role
        $this->Authorization->authorize($role);

        if ($this->request->is(['patch', 'post', 'put'])) {
            // Apply submitted changes to role entity
            $role = $this->Roles->patchEntity($role, $this->request->getData());

            if ($this->Roles->save($role)) {
                $this->Flash->success(__('The role has been saved.'));

                // Return to previous page after successful update
                return $this->redirect($this->referer());
            }

            $this->Flash->error(
                __('The role could not be saved. Please, try again.'),
            );
        }

        // Load available permissions for assignment interface
        $permissions = $this->Roles->Permissions->find('list')->all();

        $this->set(compact('role', 'permissions'));
    }

    /**
     * Add Permission method - Assign permission to role (legacy method)
     *
     * Legacy method for adding permissions to roles. Validates that permission
     * is not already assigned and system roles cannot be modified. This method
     * is being replaced by assignPermission() for AJAX functionality.
     *
     * @return \Cake\Http\Response Redirects to previous page
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found
     */
    public function addPermission()
    {
        $this->request->allowMethod(['patch', 'post', 'put']);

        $role_id = $this->request->getData('role_id');
        $permission_id = $this->request->getData('permission_id');

        // Load role with current permissions
        $role = $this->Roles->get($role_id, contain: ['Permissions']);

        // Security check: system roles cannot be modified
        if (!$role || $role->is_system) {
            throw new NotFoundException();
        }

        // Verify user has permission to modify this role
        $this->Authorization->authorize($role);

        // Load the permission to be assigned
        $permission = $this->Roles->Permissions->get($permission_id);

        // Check if permission is already assigned to prevent duplicates
        for ($i = 0; $i < count($role->permissions); $i++) {
            if ($role->permissions[$i]->id == $permission_id) {
                $this->Flash->error(
                    __('The permission is already assigned to the role.'),
                );

                return $this->redirect($this->referer());
            }
        }

        // Add the permission to the role's permission collection
        $role->permissions[] = $permission;
        $role->setDirty('permissions', true);  // Mark association as modified

        if ($this->Roles->save($role)) {
            $this->Flash->success(
                __('The permission has been added to the role.'),
            );
        } else {
            $this->Flash->error(
                __(
                    'The permission could not be added to the role. Please, try again.',
                ),
            );
        }

        return $this->redirect($this->referer());
    }

    /**
     * Delete Permission method - Remove permission from role (legacy method)
     *
     * Legacy method for removing permissions from roles. Validates that permission
     * is currently assigned and system roles cannot be modified. This method
     * is being replaced by removePermission() for AJAX functionality.
     *
     * @return \Cake\Http\Response Redirects to previous page
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found
     */
    public function deletePermission()
    {
        $this->request->allowMethod(['post']);

        $role_id = $this->request->getData('role_id');
        $permission_id = $this->request->getData('permission_id');

        // Load role with current permissions
        $role = $this->Roles->get($role_id, contain: ['Permissions']);

        // Security check: system roles cannot be modified
        if (!$role || $role->is_system) {
            throw new NotFoundException();
        }

        // Verify user has permission to modify this role
        $this->Authorization->authorize($role);

        // Load permission for validation
        $permission = $this->Roles->Permissions->get($permission_id);

        // Find and remove the permission from the role's collection
        for ($i = 0; $i < count($role->permissions); $i++) {
            if ($role->permissions[$i]->id == $permission_id) {
                // Remove permission from collection
                unset($role->permissions[$i]);
                $role->setDirty('permissions', true);  // Mark association as modified

                if ($this->Roles->save($role)) {
                    $this->Flash->success(
                        __('The permission has been removed from the role.'),
                    );
                } else {
                    $this->Flash->error(
                        __(
                            'The permission could not be removed from the role. Please, try again.',
                        ),
                    );
                }

                return $this->redirect($this->referer());
            }
        }

        // Permission was not found in role's collection
        $this->Flash->error(__('The permission is not assigned to the role.'));

        return $this->redirect($this->referer());
    }

    /**
     * Delete method - Remove role from system
     *
     * Handles role deletion with safety checks to prevent removal of system roles.
     * Implements soft delete by prefixing name with "Deleted:" to maintain
     * referential integrity while marking the role as removed.
     *
     * @param string|null $id Role id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);

        $role = $this->Roles->get($id);

        // Security check: system roles cannot be deleted
        if (!$role || $role->is_system) {
            throw new NotFoundException();
        }

        // Verify user has permission to delete this role
        $this->Authorization->authorize($role);

        // Implement soft delete by prefixing name with "Deleted:"
        // This maintains referential integrity while marking as deleted
        $role->name = 'Deleted: ' . $role->name;

        if ($this->Roles->delete($role)) {
            $this->Flash->success(__('The role has been deleted.'));
        } else {
            $this->Flash->error(
                __('The role could not be deleted. Please, try again.'),
            );
        }

        return $this->redirect(['action' => 'index']);
    }
}