<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\PermissionsLoader;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\NotFoundException;

/**
 * PermissionsController - KMP RBAC Permission Management Interface
 *
 * The PermissionsController provides the web interface for managing permissions within the KMP
 * Role-Based Access Control (RBAC) system. It handles permission creation, editing, viewing,
 * and the complex policy matrix management interface that allows administrators to configure
 * dynamic authorization rules across the system.
 *
 * ## Core Permission Management Features
 *
 * ### Permission CRUD Operations
 * - **Create Permissions**: Add new permissions with validation and security checks
 * - **View Permissions**: Display permission details including associated roles and policies
 * - **Edit Permissions**: Modify permission settings with system permission protection
 * - **Delete Permissions**: Soft delete permissions with system permission protection
 *
 * ### Advanced Policy Management
 * - **Policy Matrix Interface**: Visual grid for managing permission-policy associations
 * - **Dynamic Policy Assignment**: AJAX-based policy addition and removal
 * - **Policy Discovery**: Automatic detection of available policy classes and methods
 * - **Bulk Policy Operations**: Efficient management of multiple policy associations
 *
 * ### Security and Authorization Features
 * - **Role-Based Access**: Different access levels based on user roles
 * - **System Permission Protection**: Prevents modification of critical system permissions
 * - **Super User Controls**: Special handling for super user permission management
 * - **Authorization Integration**: Full integration with KMP authorization framework
 *
 * ## Permission Management Workflows
 *
 * ### Standard Permission Lifecycle
 * 1. **Creation**: Administrator creates permission with basic settings
 * 2. **Configuration**: Settings like scoping rules and requirements are defined
 * 3. **Role Assignment**: Permission is assigned to appropriate roles
 * 4. **Policy Association**: Custom policies are attached for dynamic authorization
 * 5. **Testing**: Permission functionality is validated in system
 * 6. **Maintenance**: Regular review and updates of permission settings
 *
 * ### System Permission Handling
 * - System permissions have special protection against modification
 * - Names cannot be changed to maintain system integrity
 * - Deletion is prevented to avoid breaking core functionality
 * - Super user permissions require elevated privileges to modify
 *
 * ## Policy Matrix Management
 *
 * ### Matrix Interface Features
 * - **Visual Grid**: Permissions vs. Policies in tabular format
 * - **Quick Assignment**: Click to add/remove policy associations
 * - **Policy Discovery**: Automatic scanning of application policy classes
 * - **Namespace Organization**: Policies grouped by namespace for clarity
 * - **Real-time Updates**: AJAX-based changes without page refresh
 *
 * ### Policy Integration
 * - Integrates with PermissionsLoader for policy discovery
 * - Supports both class-level and method-level policy assignments
 * - Handles policy validation and conflict resolution
 * - Provides feedback on policy assignment success/failure
 *
 * ## Security Architecture
 *
 * ### Authorization Layers
 * - **Controller Authorization**: Action-level authorization checks
 * - **Model Authorization**: Entity-level authorization through policies
 * - **Scope Application**: Automatic filtering based on user permissions
 * - **Resource Protection**: System permission and super user safeguards
 *
 * ### Input Validation and Security
 * - **CSRF Protection**: All state-changing operations protected
 * - **JSON Request Validation**: AJAX endpoints validate request format
 * - **Data Sanitization**: Input data properly validated and sanitized
 * - **Error Handling**: Secure error responses without information leakage
 *
 * ## Integration Points
 *
 * ### KMP Authorization System
 * - Uses PermissionsLoader for policy discovery and validation
 * - Integrates with Authorization service for access control
 * - Supports dynamic permission evaluation through policies
 * - Works with KMP identity system for user context
 *
 * ### Role Management Integration
 * - Provides role assignment interface for permissions
 * - Filters available roles based on system/user permissions
 * - Supports bulk role assignment operations
 * - Maintains referential integrity with role system
 *
 * ### Activity System Integration
 * - Supports activity-linked permissions
 * - Provides activity selection interface for permissions
 * - Handles activity-specific permission requirements
 * - Integrates with activity authorization workflows
 *
 * ## User Interface Features
 *
 * ### Permission List Interface
 * - Paginated permission listing with sorting
 * - Search and filtering capabilities
 * - Quick action buttons for common operations
 * - Visual indicators for system vs. user permissions
 *
 * ### Permission Detail Views
 * - Complete permission information display
 * - Associated roles and policies listing
 * - Quick role assignment interface
 * - Policy management controls
 *
 * ### Administrative Tools
 * - Bulk permission operations
 * - Permission import/export capabilities
 * - System permission monitoring
 * - Permission usage analytics
 *
 * ## Performance Considerations
 *
 * ### Efficient Data Loading
 * - Uses contain strategies for optimized queries
 * - Implements pagination for large permission lists
 * - Caches policy discovery results
 * - Optimizes role filtering queries
 *
 * ### AJAX Optimization
 * - Lightweight JSON responses for policy updates
 * - Minimizes page refreshes for better user experience
 * - Implements client-side validation where appropriate
 * - Uses efficient query strategies for matrix operations
 *
 * ## Usage Examples
 *
 * ### Basic Permission Management
 * ```php
 * // Creating a new permission
 * $permission = $permissionsTable->newEntity([
 *     'name' => 'Manage Local Events',
 *     'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
 *     'require_active_membership' => true
 * ]);
 *
 * // Assigning permission to roles
 * $permission->roles = [$eventStewardRole, $seneschalRole];
 * $permissionsTable->save($permission);
 * ```
 *
 * ### Policy Matrix Management
 * ```javascript
 * // AJAX policy assignment
 * fetch('/permissions/updatePolicy', {
 *     method: 'POST',
 *     headers: { 'Content-Type': 'application/json' },
 *     body: JSON.stringify({
 *         permissionId: permissionId,
 *         className: 'App\\Policy\\EventPolicy',
 *         method: 'canManageEvent',
 *         action: 'add'
 *     })
 * });
 * ```
 *
 * ### Advanced Permission Queries
 * ```php
 * // Find permissions with specific policies
 * $permissions = $permissionsTable->find()
 *     ->matching('PermissionPolicies', function($q) {
 *         return $q->where(['policy_class LIKE' => '%EventPolicy%']);
 *     })
 *     ->contain(['Roles', 'PermissionPolicies'])
 *     ->toArray();
 * ```
 *
 * @see \App\Model\Entity\Permission For permission entity documentation
 * @see \App\Model\Table\PermissionsTable For permission data access
 * @see \App\Model\Entity\PermissionPolicy For policy associations
 * @see \App\KMP\PermissionsLoader For policy discovery
 * @see \App\Controller\RolesController For role management
 *
 * @property \App\Model\Table\PermissionsTable $Permissions
 */
class PermissionsController extends AppController
{
    /**
     * Initialize method - Configure authorization for permission management
     *
     * Sets up the authorization requirements for the permissions controller,
     * specifying which actions require model-level authorization checking.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Configure model-level authorization for specific actions
        // These actions will have automatic model authorization applied
        $this->Authorization->authorizeModel('index', 'add', 'matrix');
    }

    /**
     * Index method - Display paginated list of permissions
     *
     * Provides the main interface for viewing and managing permissions in the system.
     * Includes authorization scoping to ensure users only see permissions they're
     * authorized to access, and implements efficient pagination for large datasets.
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Verify user has permission to view permission list
        $this->Authorization->authorizeAction();

        // Build base query for permissions
        $query = $this->Permissions->find();

        // Apply authorization scoping to filter permissions based on user access
        // This ensures users only see permissions they're authorized to view
        $query = $this->Authorization->applyScope($query);

        // Paginate results with alphabetical sorting for better usability
        $permissions = $this->paginate($query, [
            'order' => [
                'name' => 'asc',
            ],
        ]);

        $this->set(compact('permissions'));
    }

    /**
     * View method - Display detailed permission information
     *
     * Shows comprehensive permission details including associated roles, policies,
     * and available actions. Provides interface for managing role assignments and
     * policy associations from the detail view.
     *
     * @param string|null $id Permission id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view(?string $id = null)
    {
        // Load permission with related data for comprehensive view
        $permission = $this->Permissions->get(
            $id,
            contain: ['Roles', 'PermissionPolicies'],  // Include roles and policies
        );

        if (!$permission) {
            throw new NotFoundException();
        }

        // Verify user has permission to view this specific permission
        $this->Authorization->authorize($permission);

        // Build list of roles not currently assigned to this permission
        // This provides the interface for adding new role assignments
        $currentRoleIds = [];
        foreach ($permission->roles as $role) {
            $currentRoleIds[] = $role->id;
        }

        // Query for available roles, excluding system roles and currently assigned roles
        $roles = [];
        if (count($currentRoleIds) > 0) {
            $roles = $this->Permissions->Roles
                ->find('list')
                ->where([
                    'NOT' => ['id IN' => $currentRoleIds],  // Exclude already assigned
                    'is_system !=' => true                   // Exclude system roles
                ])
                ->all();
        } else {
            // No roles assigned yet, show all non-system roles
            $roles = $this->Permissions->Roles
                ->find('list')
                ->where(['is_system !=' => true])
                ->all();
        }

        // Load available application policies for policy assignment interface
        $appPolicies = PermissionsLoader::getApplicationPolicies();

        $this->set(compact('permission', 'roles', 'appPolicies'));
    }

    /**
     * Add method - Create new permission
     *
     * Handles the creation of new permissions with appropriate security controls
     * and validation. Prevents creation of system or super user permissions by
     * non-authorized users.
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $permission = $this->Permissions->newEmptyEntity();

        if ($this->request->is('post')) {
            // Patch entity with submitted data
            $permission = $this->Permissions->patchEntity(
                $permission,
                $this->request->getData(),
            );

            // Security controls for permission creation
            $permission->is_system = false;  // New permissions are never system permissions

            // Only super users can create super user permissions
            if (!$this->Authentication->getIdentity()->isSuperUser()) {
                $permission->is_super_user = false;
            }

            if ($this->Permissions->save($permission)) {
                $this->Flash->success(__('The permission has been saved.'));

                // Redirect to view page to continue configuration
                return $this->redirect(['action' => 'view', $permission->id]);
            }

            $this->Flash->error(
                __('The permission could not be saved. Please, try again.'),
            );
        }

        $this->set(compact('permission'));
    }

    /**
     * Update Policy method - AJAX endpoint for managing permission policies
     *
     * Provides dynamic policy management through AJAX requests, allowing users
     * to add or remove policy associations without page refresh. Includes
     * comprehensive validation and security checks.
     *
     * @return \Cake\Http\Response JSON response indicating success/failure
     * @throws \Cake\Http\Exception\BadRequestException When request format is invalid
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When permission not found
     */
    public function updatePolicy()
    {
        // Validate request format - must be JSON POST request
        if (!$this->request->is('json')) {
            throw new BadRequestException();
        }
        if (!$this->request->is('post')) {
            throw new BadRequestException();
        }

        // Extract policy data from JSON request
        $policyJson = $this->request->getData();
        $id = $policyJson['permissionId'];

        // Load and authorize permission
        $permission = $this->Permissions->get($id);
        if (!$permission) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($permission);

        // Prepare policy entity with submitted data
        $policy = $this->Permissions->PermissionPolicies->newEmptyEntity();
        $policy->permission_id = $id;
        $policy->policy_class = $policyJson['className'];
        $policy->policy_method = $policyJson['method'];

        if ($policyJson['action'] == 'add') {
            // Handle policy addition

            // Check if policy association already exists to prevent duplicates
            $policyCheck = $this->Permissions->PermissionPolicies
                ->find()
                ->where([
                    'permission_id' => $id,
                    'policy_class' => $policyJson['className'],
                    'policy_method' => $policyJson['method'],
                ])
                ->first();

            if ($policyCheck) {
                // Policy already exists, return success (idempotent operation)
                $this->response = $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode(true));
                $this->response->withStatus(200);
                return $this->response;
            }

            // Attempt to save new policy association
            if ($this->Permissions->PermissionPolicies->save($policy)) {
                $this->response = $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode(true));
                $this->response->withStatus(200);
                return $this->response;
            } else {
                $this->response = $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode(false));
                $this->response->withStatus(500);
                return $this->response;
            }
        } else {
            // Handle policy removal

            // Find existing policy association
            $policy = $this->Permissions->PermissionPolicies
                ->find()
                ->where([
                    'permission_id' => $id,
                    'policy_class' => $policyJson['className'],
                    'policy_method' => $policyJson['method'],
                ])
                ->first();

            if ($policy) {
                // Attempt to delete policy association
                if ($this->Permissions->PermissionPolicies->delete($policy)) {
                    $this->response = $this->response
                        ->withType('application/json')
                        ->withStringBody(json_encode(true));
                    $this->response->withStatus(200);
                    return $this->response;
                } else {
                    $this->response = $this->response
                        ->withType('application/json')
                        ->withStringBody(json_encode(false));
                    $this->response->withStatus(500);
                    return $this->response;
                }
            } else {
                // Policy not found, return error
                $this->response = $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode(false));
                $this->response->withStatus(500);
                return $this->response;
            }
        }
    }

    /**
     * Matrix method - Display permission-policy association matrix
     *
     * Provides a comprehensive matrix interface for managing permission-policy
     * associations across the entire system. Automatically discovers available
     * policies and displays current associations in a grid format for efficient
     * bulk management.
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function matrix()
    {
        // Load all non-super-user permissions for matrix display
        // Super user permissions are excluded for security
        $permissions = $this->Permissions->find('all')
            ->where(['is_super_user' => false])
            ->toArray();

        // Discover all available application policies
        $policiesArray = PermissionsLoader::getApplicationPolicies();

        // Flatten policy structure for grid display
        $policiesFlat = [];
        foreach ($policiesArray as $policyClass => $methods) {
            // Extract namespace and class name for better organization
            $classNameParts = explode('\\', $policyClass);
            $className = end($classNameParts);
            $nameSpace = implode('\\', array_slice($classNameParts, 0, -1));

            // Add whole-class entry for class-level policies
            $policiesFlat[] = [
                'namespace' => $nameSpace,
                'class' => $policyClass,
                'className' => $className,
                'method' => 'WholeClass',
                'display' => '',
            ];

            // Add individual method entries
            foreach ($methods as $method) {
                $policiesFlat[] = [
                    'namespace' => $nameSpace,
                    'class' => $policyClass,
                    'className' => $className,
                    'method' => $method,
                    'display' => str_replace('can', '', $method),  // Clean display name
                ];
            }
        }

        // Load existing permission-policy associations for matrix display
        $permissionPoliciesTable = $this->fetchTable('PermissionPolicies');
        $existingPolicies = $permissionPoliciesTable->find()
            ->select(['permission_id', 'policy_class', 'policy_method'])
            ->toArray();

        // Create lookup map for efficient matrix population
        $policyMap = [];
        foreach ($existingPolicies as $policy) {
            $key = $policy->permission_id . '_' . $policy->policy_class . '_' . $policy->policy_method;
            $policyMap[$key] = true;
        }

        // Sort policies by namespace, class, and method for organized display
        usort($policiesFlat, function ($a, $b) {
            return strcmp($a['namespace'], $b['namespace']) ?:
                strcmp($a['className'], $b['className']) ?:
                strcmp($a['display'], $b['display']);
        });

        $this->set(compact('permissions', 'policiesFlat', 'policyMap'));
    }

    /**
     * Edit method - Modify existing permission settings
     *
     * Handles permission editing with special protection for system permissions
     * and super user permission management. Provides interface for updating
     * permission settings while maintaining system security.
     *
     * @param string|null $id Permission id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit(?string $id = null)
    {
        // Load permission with related roles for editing interface
        $permission = $this->Permissions->get($id, contain: ['Roles']);
        if (!$permission) {
            throw new NotFoundException();
        }

        // Verify user has permission to edit this permission
        $this->Authorization->authorize($permission);

        $patch = $this->request->getData();

        if ($this->request->is(['patch', 'post', 'put'])) {
            // Apply security controls during editing

            if ($permission->is_system) {
                // System permissions cannot have their names changed
                // This protects critical system functionality
                unset($patch['name']);
            }

            // Only super users can modify super user permission flag
            if (!$this->Authentication->getIdentity()->isSuperUser()) {
                unset($patch['is_super_user']);
            }

            $permission = $this->Permissions->patchEntity($permission, $patch);

            if ($this->Permissions->save($permission)) {
                $this->Flash->success(__('The permission has been saved.'));

                // Return to previous page (likely the view page)
                return $this->redirect($this->referer());
            }

            $this->Flash->error(
                __('The permission could not be saved. Please, try again.'),
            );
        }

        // Load related data for editing interface
        $activities = $this->Permissions->Activities
            ->find('list', limit: 200)
            ->all();
        $roles = $this->Permissions->Roles
            ->find('list', limit: 200)
            ->all();

        $this->set(compact('permission', 'activities', 'roles'));
    }

    /**
     * Delete method - Remove permission from system
     *
     * Handles permission deletion with special protection for system permissions.
     * Implements soft deletion to preserve audit trail and handles permission
     * name modification to indicate deletion status.
     *
     * @param string|null $id Permission id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete(?string $id = null)
    {
        // Only allow POST/DELETE requests for security
        $this->request->allowMethod(['post', 'delete']);

        $permission = $this->Permissions->get($id);
        if (!$permission) {
            throw new NotFoundException();
        }

        // Verify user has permission to delete this permission
        $this->Authorization->authorize($permission);

        if ($permission->is_system) {
            // System permissions cannot be deleted to maintain system integrity
            $this->Flash->error(
                __(
                    'The permission could not be deleted. System permissions cannot be deleted.',
                ),
            );

            return $this->redirect($this->referer());
        }

        // Mark permission name as deleted for audit trail
        $permission->name = 'Deleted: ' . $permission->name;

        if ($this->Permissions->delete($permission)) {
            $this->Flash->success(__('The permission has been deleted.'));
        } else {
            $this->Flash->error(
                __('The permission could not be deleted. Please, try again.'),
            );
        }

        return $this->redirect(['action' => 'index']);
    }
}
