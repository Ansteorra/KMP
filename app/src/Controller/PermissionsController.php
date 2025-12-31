<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\PermissionsLoader;
use App\Services\CsvExportService;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;

/**
 * Manages RBAC permissions: CRUD, policy matrix, and role assignments.
 *
 * Provides policy matrix interface for visual permission-policy management.
 * System permissions are protected from modification/deletion.
 *
 * @property \App\Model\Table\PermissionsTable $Permissions
 */
class PermissionsController extends AppController
{
    use DataverseGridTrait;

    /**
     * Configure authorization for permission management actions.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Configure model-level authorization for specific actions
        // These actions will have automatic model authorization applied
        $this->Authorization->authorizeModel('index', 'add', 'matrix', 'gridData', 'exportPolicies', 'importPolicies', 'previewImport');
    }

    /**
     * Index method - Display Dataverse grid for permissions
     *
     * Renders the permissions grid page which uses lazy-loading turbo-frame
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
     * Grid Data method - Provides Dataverse grid data for permissions
     *
     * Returns grid content with toolbar and table for the permissions grid.
     * Handles both outer frame (toolbar + table frame) and inner frame
     * (table only) requests. Also supports CSV export.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Get system views from GridColumns
        $systemViews = \App\KMP\GridColumns\PermissionsGridColumns::getSystemViews([]);

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Permissions.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\PermissionsGridColumns::class,
            'baseQuery' => $this->Permissions->find(),
            'tableName' => 'Permissions',
            'defaultSort' => ['Permissions.name' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => true,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'permissions');
        }

        // Set view variables
        $this->set([
            'permissions' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\PermissionsGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'permissions-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'permissions-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'permissions-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
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

        // Sort policies alphabetically by class name for easier navigation
        ksort($appPolicies);

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

    /**
     * Export policies - Export a single permission with its policy associations
     *
     * Exports a permission and its policy mappings to JSON format.
     * Only available to super users for security purposes.
     *
     * @param string|null $id Permission id to export
     * @return \Cake\Http\Response JSON file download
     * @throws \Cake\Http\Exception\ForbiddenException If user is not a super user
     * @throws \Cake\Http\Exception\NotFoundException If permission not found
     */
    public function exportPolicies(?string $id = null)
    {
        // Verify super user access
        if (!$this->Authentication->getIdentity()->isSuperUser()) {
            throw new ForbiddenException(__('Only super users can export permission policies.'));
        }

        // Load the specific permission with its policy associations
        $permission = $this->Permissions->get($id, contain: ['PermissionPolicies']);
        if (!$permission) {
            throw new NotFoundException(__('Permission not found.'));
        }

        // Build export data structure
        $exportData = [
            'version' => '1.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'permission_name' => $permission->name,
            'policies' => [],
        ];

        // Export policies by class and method name (not IDs)
        foreach ($permission->permission_policies as $policy) {
            $exportData['policies'][] = [
                'policy_class' => $policy->policy_class,
                'policy_method' => $policy->policy_method,
            ];
        }

        // Return as downloadable JSON file
        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $permission->name);
        $filename = 'permission-' . $safeName . '-' . date('Y-m-d-His') . '.json';

        $this->response = $this->response
            ->withType('application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withStringBody($jsonContent);

        return $this->response;
    }

    /**
     * Preview import - Analyze import file and show changes for a single permission
     *
     * Analyzes the uploaded JSON file and returns a preview of changes
     * that would be made during import. This is an AJAX endpoint.
     *
     * @param string|null $id Permission id to import into
     * @return \Cake\Http\Response JSON response with preview data
     * @throws \Cake\Http\Exception\ForbiddenException If user is not a super user
     * @throws \Cake\Http\Exception\BadRequestException If file is invalid
     */
    public function previewImport(?string $id = null)
    {
        // Verify super user access
        if (!$this->Authentication->getIdentity()->isSuperUser()) {
            throw new ForbiddenException(__('Only super users can import permission policies.'));
        }

        if (!$this->request->is('post')) {
            throw new BadRequestException(__('Invalid request method.'));
        }

        if (!$id) {
            return $this->response
                ->withType('application/json')
                ->withStatus(400)
                ->withStringBody(json_encode(['error' => __('Permission ID required.')]));
        }

        // Load the target permission
        $permission = $this->Permissions->get($id, contain: ['PermissionPolicies']);
        if (!$permission) {
            return $this->response
                ->withType('application/json')
                ->withStatus(404)
                ->withStringBody(json_encode(['error' => __('Permission not found.')]));
        }

        // Get uploaded file
        $uploadedFile = $this->request->getUploadedFile('import_file');
        if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return $this->response
                ->withType('application/json')
                ->withStatus(400)
                ->withStringBody(json_encode(['error' => __('No file uploaded or upload error.')]));
        }

        // Read and parse JSON
        $content = $uploadedFile->getStream()->getContents();
        $importData = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->response
                ->withType('application/json')
                ->withStatus(400)
                ->withStringBody(json_encode(['error' => __('Invalid JSON file.')]));
        }

        if (!isset($importData['policies']) || !is_array($importData['policies'])) {
            return $this->response
                ->withType('application/json')
                ->withStatus(400)
                ->withStringBody(json_encode(['error' => __('Invalid file format: missing policies array.')]));
        }

        // Build current policy set for this permission
        $currentPolicies = [];
        foreach ($permission->permission_policies as $policy) {
            $currentPolicies[] = $policy->policy_class . '::' . $policy->policy_method;
        }
        sort($currentPolicies);

        // Build import policy set
        $importPolicies = [];
        foreach ($importData['policies'] as $policy) {
            $importPolicies[] = $policy['policy_class'] . '::' . $policy['policy_method'];
        }
        sort($importPolicies);

        // Calculate changes
        $toAdd = array_diff($importPolicies, $currentPolicies);
        $toRemove = array_diff($currentPolicies, $importPolicies);

        $changes = [
            'policies_to_add' => [],
            'policies_to_remove' => [],
            'source_permission' => $importData['permission_name'] ?? 'Unknown',
            'target_permission' => $permission->name,
            'summary' => [
                'total_add' => count($toAdd),
                'total_remove' => count($toRemove),
            ],
        ];

        foreach ($toAdd as $policy) {
            $parts = explode('::', $policy);
            $changes['policies_to_add'][] = [
                'policy_class' => $parts[0],
                'policy_method' => $parts[1],
            ];
        }

        foreach ($toRemove as $policy) {
            $parts = explode('::', $policy);
            $changes['policies_to_remove'][] = [
                'policy_class' => $parts[0],
                'policy_method' => $parts[1],
            ];
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'changes' => $changes,
                'import_data' => base64_encode($content),
            ]));
    }

    /**
     * Import policies - Apply imported policy configuration to a single permission
     *
     * Applies the policy changes from the import preview. Expects JSON body
     * with the import data that was previewed.
     *
     * @param string|null $id Permission id to import into
     * @return \Cake\Http\Response JSON response with import results
     * @throws \Cake\Http\Exception\ForbiddenException If user is not a super user
     * @throws \Cake\Http\Exception\BadRequestException If request is invalid
     */
    public function importPolicies(?string $id = null)
    {
        // Verify super user access
        if (!$this->Authentication->getIdentity()->isSuperUser()) {
            throw new ForbiddenException(__('Only super users can import permission policies.'));
        }

        if (!$this->request->is('post') || !$this->request->is('json')) {
            throw new BadRequestException(__('Invalid request.'));
        }

        if (!$id) {
            return $this->response
                ->withType('application/json')
                ->withStatus(400)
                ->withStringBody(json_encode(['error' => __('Permission ID required.')]));
        }

        // Load the target permission
        $permission = $this->Permissions->get($id, contain: ['PermissionPolicies']);
        if (!$permission) {
            return $this->response
                ->withType('application/json')
                ->withStatus(404)
                ->withStringBody(json_encode(['error' => __('Permission not found.')]));
        }

        $requestData = $this->request->getData();
        if (empty($requestData['import_data'])) {
            return $this->response
                ->withType('application/json')
                ->withStatus(400)
                ->withStringBody(json_encode(['error' => __('No import data provided.')]));
        }

        // Decode the import data
        $content = base64_decode($requestData['import_data']);
        $importData = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($importData['policies'])) {
            return $this->response
                ->withType('application/json')
                ->withStatus(400)
                ->withStringBody(json_encode(['error' => __('Invalid import data.')]));
        }

        $permissionPoliciesTable = $this->fetchTable('PermissionPolicies');
        $results = [
            'added' => 0,
            'removed' => 0,
            'errors' => [],
        ];

        // Build current policy lookup for this permission
        $currentPolicies = [];
        foreach ($permission->permission_policies as $policy) {
            $key = $policy->policy_class . '::' . $policy->policy_method;
            $currentPolicies[$key] = $policy;
        }

        // Build import policy set
        $importPolicies = [];
        foreach ($importData['policies'] as $policy) {
            $key = $policy['policy_class'] . '::' . $policy['policy_method'];
            $importPolicies[$key] = $policy;
        }

        // Add policies that are in import but not current
        foreach ($importPolicies as $key => $policyData) {
            if (!isset($currentPolicies[$key])) {
                $newPolicy = $permissionPoliciesTable->newEntity([
                    'permission_id' => $permission->id,
                    'policy_class' => $policyData['policy_class'],
                    'policy_method' => $policyData['policy_method'],
                ]);

                if ($permissionPoliciesTable->save($newPolicy)) {
                    $results['added']++;
                } else {
                    $results['errors'][] = __('Failed to add policy {0}', $key);
                }
            }
        }

        // Remove policies that are in current but not import
        foreach ($currentPolicies as $key => $policy) {
            if (!isset($importPolicies[$key])) {
                if ($permissionPoliciesTable->delete($policy)) {
                    $results['removed']++;
                } else {
                    $results['errors'][] = __('Failed to remove policy {0}', $key);
                }
            }
        }

        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode([
                'success' => true,
                'results' => $results,
            ]));
    }
}
