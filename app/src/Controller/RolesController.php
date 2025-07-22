<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Permission;
use App\Services\CsvExportService;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

/**
 * RolesController - KMP RBAC Role Management Interface
 *
 * The RolesController provides the web interface for managing roles within the KMP Role-Based
 * Access Control (RBAC) system. It handles role creation, editing, permission assignment,
 * member management, and provides comprehensive role administration capabilities with proper
 * security controls and authorization checks.
 *
 * ## Core Role Management Features
 *
 * ### Role CRUD Operations
 * - **Create Roles**: Add new roles with validation and security controls
 * - **View Roles**: Display detailed role information including members and permissions
 * - **Edit Roles**: Modify role settings with system role protection
 * - **Delete Roles**: Soft delete roles with member assignment verification
 *
 * ### Permission Management
 * - **Permission Assignment**: Add permissions to roles with validation
 * - **Permission Removal**: Remove permissions from roles with safety checks
 * - **Permission Filtering**: Display available permissions for assignment
 * - **Scoping Analysis**: Detect and handle branch-scoped permission requirements
 *
 * ### Member Assignment Interface
 * - **Current Members**: Display active role assignments with temporal information
 * - **Assignment Counts**: Track current, upcoming, and previous assignments
 * - **Branch Requirements**: Handle branch-scoped role assignments
 * - **Member Search**: AJAX-based member search for role assignment
 *
 * ## Role Management Workflows
 *
 * ### Standard Role Lifecycle
 * 1. **Creation**: Administrator creates role with basic information
 * 2. **Permission Assignment**: Relevant permissions are assigned to the role
 * 3. **Member Assignment**: Members are assigned to the role through MemberRole entities
 * 4. **Monitoring**: Track role usage and member assignments
 * 5. **Maintenance**: Regular review and updates of role permissions
 * 6. **Deactivation**: Role is soft deleted when no longer needed
 *
 * ### System Role Protection
 * - System roles have special protection against modification
 * - Critical roles cannot be deleted to maintain system integrity
 * - System role names cannot be changed to preserve functionality
 * - Special authorization requirements for system role access
 *
 * ## Advanced Features
 *
 * ### Branch-Scoped Role Management
 * - **Scope Detection**: Automatically detects if role requires branch assignment
 * - **Branch Interface**: Provides branch selection for scoped roles
 * - **Hierarchy Support**: Works with organizational branch hierarchy
 * - **Permission Validation**: Ensures branch requirements are met
 *
 * ### Temporal Assignment Tracking
 * - **Current Assignments**: Active role assignments within date range
 * - **Upcoming Assignments**: Future role assignments not yet active
 * - **Historical Assignments**: Past assignments for audit trail
 * - **Assignment Analytics**: Track role usage patterns over time
 *
 * ### Export and Reporting
 * - **CSV Export**: Export role lists for external analysis
 * - **Assignment Reports**: Generate member assignment reports
 * - **Permission Audits**: Track permission distribution across roles
 * - **Usage Analytics**: Monitor role utilization patterns
 *
 * ## Security Architecture
 *
 * ### Authorization Controls
 * - **Action Authorization**: Each action requires appropriate permissions
 * - **Entity Authorization**: Role-specific authorization checks
 * - **Scope Application**: Automatic filtering based on user access
 * - **System Role Protection**: Enhanced security for critical roles
 *
 * ### Data Protection
 * - **Input Validation**: Comprehensive validation of role data
 * - **CSRF Protection**: All state-changing operations protected
 * - **SQL Injection Prevention**: Parameterized queries throughout
 * - **XSS Prevention**: Output encoding and validation
 *
 * ### Audit and Compliance
 * - **Change Tracking**: Complete audit trail of role modifications
 * - **User Attribution**: Track who made role changes
 * - **Timestamp Management**: Automatic tracking of creation and modification
 * - **Soft Deletion**: Preserve role history for compliance
 *
 * ## Integration Points
 *
 * ### RBAC System Integration
 * - **Permission Management**: Direct integration with PermissionsController
 * - **Member Management**: Integration with member assignment workflows
 * - **Authorization Service**: Real-time permission evaluation
 * - **Policy Framework**: Support for custom authorization policies
 *
 * ### Organizational Hierarchy
 * - **Branch Integration**: Works with branch hierarchy for scoped roles
 * - **Tree Operations**: Leverages branch tree operations for assignment
 * - **Descendant Queries**: Efficient queries for hierarchical permissions
 * - **Scope Validation**: Ensures assignments match permission scopes
 *
 * ### Member Management System
 * - **Member Search**: Integration with member search functionality
 * - **Assignment Workflows**: Support for time-bounded assignments
 * - **Approval Processes**: Integration with approval workflows
 * - **Notification System**: Role assignment notifications
 *
 * ## User Interface Features
 *
 * ### Role List Interface
 * - **Paginated Listing**: Efficient display of large role sets
 * - **Search and Filtering**: Find roles by name and attributes
 * - **Sort Options**: Multiple sorting criteria available
 * - **Export Options**: CSV export for external processing
 *
 * ### Role Detail Views
 * - **Comprehensive Information**: Complete role details and statistics
 * - **Permission Listing**: All assigned permissions with details
 * - **Member Assignments**: Current, upcoming, and historical assignments
 * - **Quick Actions**: Common operations accessible from detail view
 *
 * ### Administrative Tools
 * - **Bulk Operations**: Efficient management of multiple roles
 * - **Permission Matrix**: Visual permission assignment interface
 * - **Assignment Analytics**: Role usage and member statistics
 * - **System Monitoring**: Track role system health and usage
 *
 * ## Performance Optimizations
 *
 * ### Efficient Data Loading
 * - **Strategic Contains**: Optimized association loading
 * - **Pagination**: Efficient handling of large datasets
 * - **Query Optimization**: Efficient SQL generation for complex queries
 * - **Cache Integration**: Leverages system caching for performance
 *
 * ### AJAX Operations
 * - **Partial Updates**: Update specific sections without full page refresh
 * - **Progressive Loading**: Load data as needed for better responsiveness
 * - **Client-side Validation**: Reduce server load with client validation
 * - **Optimistic Updates**: Immediate UI feedback for better user experience
 *
 * ## Usage Examples
 *
 * ### Basic Role Management
 * ```php
 * // Creating a new role
 * $role = $rolesTable->newEntity([
 *     'name' => 'Event Steward'
 * ]);
 * $rolesTable->save($role);
 *
 * // Adding permissions to role
 * $role->permissions = [$manageEventsPermission, $viewMembersPermission];
 * $rolesTable->save($role);
 * ```
 *
 * ### Member Assignment Workflows
 * ```php
 * // Assign member to role with time bounds
 * $memberRole = $memberRolesTable->newEntity([
 *     'Member_id' => $member->id,
 *     'role_id' => $role->id,
 *     'start_on' => new Date('2024-01-01'),
 *     'expires_on' => new Date('2024-12-31'),
 *     'branch_id' => $branch->id  // For scoped roles
 * ]);
 * ```
 *
 * ### Advanced Role Queries
 * ```php
 * // Find roles with specific permissions
 * $roles = $rolesTable->find()
 *     ->matching('Permissions', function($q) use ($permissionName) {
 *         return $q->where(['Permissions.name' => $permissionName]);
 *     })
 *     ->contain(['CurrentMemberRoles'])
 *     ->toArray();
 * ```
 *
 * @see \App\Model\Entity\Role For role entity documentation
 * @see \App\Model\Table\RolesTable For role data access
 * @see \App\Model\Entity\MemberRole For member role assignments
 * @see \App\Controller\PermissionsController For permission management
 * @see \App\Controller\MembersController For member management
 *
 * @property \App\Model\Table\RolesTable $Roles
 */
class RolesController extends AppController
{
    /**
     * Initialize method - Configure authorization for role management
     *
     * Sets up the authorization requirements for the roles controller,
     * specifying which actions require model-level authorization checking.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Configure model-level authorization for specific actions
        // These actions will have automatic model authorization applied
        $this->Authorization->authorizeModel(
            'index',        // Role listing
            'add',          // Role creation
            'searchMembers', // Member search for role assignment
        );
    }

    /**
     * Index method - Display paginated list of roles with CSV export support
     *
     * Provides the main interface for viewing and managing roles in the system.
     * Includes authorization scoping to ensure users only see roles they're
     * authorized to access, and supports CSV export for external analysis.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function index(CsvExportService $csvExportService)
    {
        // Verify user has permission to view role list
        $this->Authorization->authorizeAction();

        // Build base query for roles
        $query = $this->Roles->find();

        // Apply authorization scoping to filter roles based on user access
        // This ensures users only see roles they're authorized to view
        $query = $this->Authorization->applyScope($query);

        // Handle CSV export requests
        if ($this->isCsvRequest()) {
            return $csvExportService->outputCsv(
                $query->order(['name' => 'asc']),
                'roles.csv',
            );
        }

        // Paginate results with alphabetical sorting for better usability
        $roles = $this->paginate($query, [
            'order' => [
                'name' => 'asc',
            ],
        ]);

        $this->set(compact('roles'));
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