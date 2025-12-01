<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\StaticHelpers;
use App\Services\CsvExportService;
use Cake\Database\Exception\DatabaseException;
use Cake\Http\Exception\NotFoundException;

/**
 * Branches Controller - Hierarchical Organization Management for KMP
 * 
 * Manages the complete lifecycle of organizational branches within the Kingdom
 * Management Portal. Provides CRUD operations, tree structure maintenance,
 * and organizational search capabilities with comprehensive authorization.
 * 
 * **Core Responsibilities:**
 * - Branch creation, editing, and deletion with tree integrity
 * - Hierarchical search with special character handling
 * - Member association and organizational reporting
 * - Tree structure recovery and maintenance
 * - JSON links management for external resources
 * - Authorization integration with branch-scoped permissions
 * 
 * **Tree Structure Management:**
 * - Automatic tree recovery on initialization
 * - Circular reference detection and prevention
 * - Parent-child relationship validation
 * - Nested set model integrity maintenance
 * 
 * **Search Capabilities:**
 * - Multi-level hierarchy search (branch, parent, grandparent)
 * - Special character handling (th/Þ conversion for Norse names)
 * - Location-based search across organizational units
 * - Real-time search with query parameter persistence
 * 
 * **Authorization Architecture:**
 * - Policy-based authorization for all operations
 * - Branch-scoped permissions with hierarchical inheritance
 * - Role-based access control for organizational management
 * - Automatic scope application for data security
 * 
 * **Member Integration:**
 * - Branch-member association management
 * - Member count and status reporting
 * - Organizational visibility controls
 * - Member transfer and branch assignment
 * 
 * **Administrative Features:**
 * - Bulk branch operations and management
 * - Tree structure validation and repair
 * - Organizational reporting and analytics
 * - Branch type classification and configuration
 * 
 * **Usage Examples:**
 * ```php
 * // Branch hierarchy navigation
 * GET /branches                    // List all branches with search
 * GET /branches/view/123          // View specific branch details
 * 
 * // Branch management
 * GET  /branches/add              // Create new branch form
 * POST /branches/add              // Save new branch
 * GET  /branches/edit/123         // Edit branch form  
 * POST /branches/edit/123         // Update branch
 * POST /branches/delete/123       // Delete branch (soft delete)
 * 
 * // Search operations
 * GET /branches?search=atlantia   // Search by name
 * GET /branches?search=virginia   // Search by location
 * ```
 * 
 * **Error Handling:**
 * - Tree structure validation errors
 * - Circular reference prevention
 * - Database constraint violation handling
 * - User-friendly error messages with corrective guidance
 * 
 * **Security Considerations:**
 * - Authorization required for all operations
 * - Branch-scoped data access control
 * - Input validation and sanitization
 * - CSRF protection for state-changing operations
 * 
 * @property \App\Model\Table\BranchesTable $Branches
 * @see \App\Model\Entity\Branch For branch entity documentation
 * @see \App\Model\Table\BranchesTable For tree operations and relationships
 * @see \App\Policy\BranchPolicy For authorization rules and permissions
 * @see \App\Controller\AppController For base controller functionality
 */
class BranchesController extends AppController
{
    use DataverseGridTrait;

    /**
     * Initialize controller with authorization and tree recovery
     * 
     * Sets up the BranchesController with proper authorization configuration
     * and ensures tree structure integrity through automatic recovery. This
     * method runs before any action and establishes the security and data
     * consistency foundation.
     * 
     * **Authorization Setup:**
     * - Enables model-level authorization for index and add operations
     * - Integrates with policy-based authorization system
     * - Applies branch-scoped permissions automatically
     * 
     * **Tree Recovery Process:**
     * - Checks if tree recovery has been performed
     * - Runs tree recovery to rebuild lft/rght values if needed
     * - Marks recovery as complete to prevent repeated operations
     * - Ensures tree integrity for all subsequent operations
     * 
     * **Performance Considerations:**
     * - Recovery runs only once per application lifecycle
     * - Uses app settings to track recovery status
     * - Minimal overhead for normal operations
     * 
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel('index', 'add', 'gridData');
        $setting = StaticHelpers::getAppSetting('KMP.BranchInitRun', '');
        if (!$setting == 'recovered') {
            $branches = $this->Branches;
            $branches->recover();
            StaticHelpers::setAppSetting(
                'KMP.BranchInitRun',
                'recovered',
            );
        }
    }

    /**
     * Index method - Display Dataverse grid for branches
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Simple index page - just renders the dv_grid element
        // The dv_grid element will lazy-load the actual data via gridData action
    }

    /**
     * Grid Data method - Provides Dataverse grid data for branches
     *
     * Branches use a flat grid with a computed "path" column showing hierarchy.
     * The path is computed by walking up the parent chain for each branch.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Build base query with parent for path computation
        $baseQuery = $this->Branches->find()
            ->contain(['Parent']);

        // Use unified trait for grid processing
        // Sort by 'lft' (left value) to maintain hierarchical tree order
        $result = $this->processDataverseGrid([
            'gridKey' => 'Branches.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\BranchesGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Branches',
            'defaultSort' => ['Branches.lft' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Post-process data to compute path for each branch
        $branches = $result['data'];
        $this->computeBranchPaths($branches);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'branches');
        }

        // Set view variables
        $this->set([
            'branches' => $branches,
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\BranchesGridColumns::getSearchableColumns(),
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

        // Override data for grid rendering
        $this->set('data', $branches);

        if ($turboFrame === 'branches-grid-table') {
            // Inner frame request - render table data only
            $this->set('tableFrameId', 'branches-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('frameId', 'branches-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Compute hierarchical path for each branch
     *
     * Walks up the parent chain to build a path like "/Kingdom/Barony/Shire"
     * Uses a cache to avoid repeated lookups for the same ancestors.
     *
     * @param iterable $branches The branches to compute paths for
     * @return void
     */
    protected function computeBranchPaths(iterable $branches): void
    {
        // Build a lookup map of all branches by ID for efficient path computation
        $branchMap = [];
        foreach ($branches as $branch) {
            $branchMap[$branch->id] = $branch;
        }

        // We need to load all parent IDs that aren't in the current page
        $missingParentIds = [];
        foreach ($branches as $branch) {
            if ($branch->parent_id && !isset($branchMap[$branch->parent_id])) {
                $missingParentIds[$branch->parent_id] = true;
            }
        }

        // Load missing parents if any
        if (!empty($missingParentIds)) {
            $parentBranches = $this->Branches->find()
                ->where(['Branches.id IN' => array_keys($missingParentIds)])
                ->contain(['Parent'])
                ->all();

            foreach ($parentBranches as $parent) {
                $branchMap[$parent->id] = $parent;
                // Also check if this parent's parent is missing
                if ($parent->parent_id && !isset($branchMap[$parent->parent_id])) {
                    $missingParentIds[$parent->parent_id] = true;
                }
            }

            // Recursively load grandparents etc. (max 10 levels to prevent infinite loops)
            for ($i = 0; $i < 10; $i++) {
                $newMissing = [];
                foreach ($missingParentIds as $parentId => $v) {
                    if (isset($branchMap[$parentId]) && $branchMap[$parentId]->parent_id) {
                        if (!isset($branchMap[$branchMap[$parentId]->parent_id])) {
                            $newMissing[$branchMap[$parentId]->parent_id] = true;
                        }
                    }
                }

                if (empty($newMissing)) {
                    break;
                }

                $moreBranches = $this->Branches->find()
                    ->where(['Branches.id IN' => array_keys($newMissing)])
                    ->all();

                foreach ($moreBranches as $parent) {
                    $branchMap[$parent->id] = $parent;
                }

                $missingParentIds = array_merge($missingParentIds, $newMissing);
            }
        }

        // Now compute paths for each branch
        foreach ($branches as $branch) {
            $pathParts = [$branch->name];
            $currentId = $branch->parent_id;

            // Walk up the parent chain
            while ($currentId && isset($branchMap[$currentId])) {
                $parent = $branchMap[$currentId];
                array_unshift($pathParts, $parent->name);
                $currentId = $parent->parent_id;
            }

            // Store computed path
            $branch->path = '/' . implode('/', $pathParts);
        }
    }

    /**
     * View method - Display detailed branch information
     *
     * Shows comprehensive information about a specific branch including
     * member lists, child branches, and administrative details. Provides
     * the primary interface for branch management and oversight.
     *
     * **Branch Information Display:**
     * - Complete branch details and configuration
     * - Parent branch relationship and hierarchy path
     * - Member association list with key information
     * - Child branch listing and organization
     *
     * **Member Management:**
     * - Associated member listing with status information
     * - Member count and demographic overview
     * - Direct links to member management functions
     * - Member status and expiration tracking
     *
     * **Organizational Context:**
     * - Child branch listing and management
     * - Tree list for reorganization operations
     * - Branch type configuration and settings
     * - External links and resource management
     *
     * **Administrative Features:**
     * - Edit and delete action availability
     * - Permission-based action visibility
     * - Branch-specific configuration options
     * - Integration with Officers plugin for leadership display
     *
     * **Usage Examples:**
     * ```php
     * // View branch details
     * GET /branches/view/123
     * 
     * // Branch with members and children
     * $branch->members;     // Associated member list
     * $branch->children;    // Direct child branches
     * $branch->parent;      // Parent branch if applicable
     * ```
     *
     * @param string|null $id Branch id.
     * @return \Cake\Http\Response|null|void Renders view with branch details
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view(?string $id = null)
    {
        $branch = $this->Branches->get(
            $id,
            contain: [
                'Parent',
                'Members' => function ($q) {
                    return $q
                        ->select(['id', 'sca_name', 'branch_id', 'membership_number', 'membership_expires_on', 'status', 'birth_month', 'birth_year'])
                        ->orderBy(['sca_name' => 'ASC']);
                },
            ],
        );
        if (!$branch) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($branch);
        // get the children for the branch
        $branch->children = $this->Branches
            ->find('children', for: $branch->id, direct: true)
            ->toArray();
        $treeList = $this->Branches
            ->find('treeList', spacer: '--')
            ->orderBy(['name' => 'ASC']);

        $btArray = StaticHelpers::getAppSetting('Branches.Types');
        $branch_types = [];
        foreach ($btArray as $branchType) {
            $branch_types[$branchType] = $branchType;
        }

        // get a list of required offices and officers for the branch

        $this->set(compact('branch', 'treeList', 'branch_types'));
    }

    /**
     * Add method - Create new organizational branch
     *
     * Handles the creation of new organizational branches with comprehensive
     * validation, tree structure maintenance, and JSON links processing.
     * Supports both GET (form display) and POST (form submission) operations.
     *
     * **Form Processing:**
     * - Entity creation and validation
     * - JSON links parsing and storage
     * - Tree structure integration
     * - Parent-child relationship establishment
     *
     * **Validation Features:**
     * - Unique branch name validation
     * - Required field checking (name, location)
     * - Business rule enforcement
     * - Tree structure integrity validation
     *
     * **JSON Links Processing:**
     * - External resource URL storage
     * - Website and social media link management
     * - Calendar and newsletter integration
     * - Custom link configuration support
     *
     * **Branch Type Configuration:**
     * - Kingdom, Principality, Barony, Shire classification
     * - Custom branch type support
     * - Administrative hierarchy establishment
     *
     * **Usage Examples:**
     * ```php
     * // Display add form
     * GET /branches/add
     * 
     * // Submit new branch
     * POST /branches/add
     * {
     *     "name": "Barony of Example",
     *     "location": "Example State",
     *     "type": "Barony",
     *     "parent_id": 123,
     *     "branch_links": '{"website": "https://example.com"}'
     * }
     * ```
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $branch = $this->Branches->newEmptyEntity();
        if ($this->request->is('post')) {
            $branch = $this->Branches->patchEntity(
                $branch,
                $this->request->getData(),
            );
            $links = json_decode($this->request->getData('branch_links'), true);
            $branch->links = $links;
            if ($this->Branches->save($branch)) {
                $this->Flash->success(__('The branch has been saved.'));

                return $this->redirect(['action' => 'view', $branch->id]);
            }
            $this->Flash->error(
                __('The branch could not be saved. Please, try again.'),
            );
        }
        $btArray = StaticHelpers::getAppSetting('Branches.Types');
        $branch_types = [];
        foreach ($btArray as $branchType) {
            $branch_types[$branchType] = $branchType;
        }
        $treeList = $this->Branches
            ->find('list')
            ->orderBy(['name' => 'ASC']);
        $this->set(compact('branch', 'treeList', 'branch_types'));
    }

    /**
     * Edit method - Update existing organizational branch
     *
     * Handles branch modification with comprehensive tree structure validation,
     * circular reference prevention, and automatic tree recovery. Supports
     * both GET (form display) and POST/PUT/PATCH (form submission) operations.
     *
     * **Authorization:**
     * - Entity-level authorization before any modifications
     * - Policy-based permission checking
     * - Branch-scoped access control enforcement
     *
     * **Tree Structure Validation:**
     * - Circular reference detection and prevention
     * - Parent-child relationship validation
     * - Automatic tree recovery after structure changes
     * - Database constraint violation handling
     *
     * **JSON Links Management:**
     * - External resource URL updates
     * - Link configuration modification
     * - Website and resource link maintenance
     *
     * **Error Handling:**
     * - Database exception catching and user-friendly messages
     * - Tree structure error detection (circular references)
     * - Validation error display and form preservation
     * - Graceful degradation with helpful error messages
     *
     * **Performance Considerations:**
     * - Tree recovery only on successful save
     * - Minimal query overhead for validation
     * - Efficient error handling and response
     *
     * **Usage Examples:**
     * ```php
     * // Display edit form
     * GET /branches/edit/123
     * 
     * // Submit branch updates
     * POST /branches/edit/123
     * {
     *     "name": "Updated Branch Name",
     *     "location": "Updated Location",
     *     "parent_id": 456,  // Can trigger tree restructuring
     *     "branch_links": '{"website": "https://newsite.com"}'
     * }
     * ```
     *
     * @param string|null $id Branch id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit(?string $id = null)
    {
        $branch = $this->Branches->get($id);
        if (!$branch) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($branch);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $branch = $this->Branches->patchEntity(
                $branch,
                $this->request->getData(),
            );
            $links = json_decode($this->request->getData('branch_links'), true);
            $branch->links = $links;
            try {
                if ($this->Branches->save($branch)) {
                    $branches = $this->getTableLocator()->get('Branches');
                    $branches->recover();
                    $this->Flash->success(__('The branch has been saved.'));

                    return $this->redirect(['action' => 'view', $branch->id]);
                }
                $this->Flash->error(
                    __('The branch could not be saved. Please, try again.'),
                );

                return $this->redirect(['action' => 'view', $branch->id]);
            } catch (DatabaseException $e) {
                // if the error message starts with 'Cannot use node' then it is a tree error
                if (strpos($e->getMessage(), 'Cannot use node') === 0) {
                    $this->Flash->error(
                        __(
                            'The branch could not be saved, save would have created a circular reference.',
                        ),
                    );
                } else {
                    $this->Flash->error(
                        __(
                            'The branch could not be saved. Please, try again. Error` {0}',
                            $e->getMessage(),
                        ),
                    );
                }

                return $this->redirect(['action' => 'view', $branch->id]);
            }
        }
        $treeList = $this->Branches
            ->find('list')
            ->orderBy(['name' => 'ASC']);
        $this->set(compact('branch', 'treeList'));
        // Mirror MembersController pattern: GET edit displays view template with modal
        if ($this->request->is('get')) {
            // Provide branch_types for edit modal element
            $btArray = StaticHelpers::getAppSetting('Branches.Types');
            $branch_types = [];
            foreach ($btArray as $branchType) {
                $branch_types[$branchType] = $branchType;
            }
            $this->set(compact('branch_types'));
            return $this->render('view');
        }
    }

    /**
     * Delete method - Remove organizational branch with constraints
     *
     * Handles branch deletion with comprehensive safety checks to prevent
     * orphaned data and maintain organizational integrity. Implements soft
     * deletion with name prefixing for audit trail maintenance.
     *
     * **Safety Constraints:**
     * - Cannot delete branches with child branches
     * - Cannot delete branches with associated members
     * - Prevents orphaned data and broken hierarchies
     * - Maintains referential integrity
     *
     * **Soft Deletion:**
     * - Prefixes name with "Deleted:" for audit trail
     * - Uses Trash behavior for recoverable deletion
     * - Maintains historical records and relationships
     * - Enables data recovery if needed
     *
     * **Authorization:**
     * - Entity-level authorization required
     * - Policy-based permission checking
     * - Administrative role verification
     *
     * **Security Features:**
     * - POST/DELETE method restriction
     * - CSRF token validation
     * - Confirmation dialog requirement
     * - User feedback and error handling
     *
     * **Usage Examples:**
     * ```php
     * // Delete branch (with confirmation)
     * POST /branches/delete/123
     * 
     * // Only allowed if:
     * // - No child branches exist
     * // - No members are associated
     * // - User has delete permissions
     * ```
     *
     * @param string|null $id Branch id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $branch = $this->Branches->get($id);
        if (!$branch) {
            throw new NotFoundException();
        }
        $this->Authorization->authorize($branch);
        $branch->name = 'Deleted: ' . $branch->name;
        if ($this->Branches->delete($branch)) {
            $this->Flash->success(__('The branch has been deleted.'));
        } else {
            $this->Flash->error(
                __('The branch could not be deleted. Please, try again.'),
            );
        }

        return $this->redirect(['action' => 'index']);
    }
}
