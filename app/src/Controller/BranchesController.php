<?php

declare(strict_types=1);

namespace App\Controller;

use App\KMP\StaticHelpers;
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
        $this->Authorization->authorizeModel('index', 'add');
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
     * Index method - List and search organizational branches
     *
     * Displays a comprehensive list of all branches with advanced search
     * capabilities and hierarchical organization. Supports multi-level
     * search across branch names, locations, and parent relationships.
     *
     * **Search Features:**
     * - Text search across branch names and locations
     * - Parent branch name searching (3 levels deep)
     * - Special character handling for Norse/Icelandic names (th/Þ)
     * - Real-time filtering with query persistence
     *
     * **Hierarchy Display:**
     * - Threaded tree structure for organizational clarity
     * - Parent-child relationship visualization
     * - Efficient join queries for related data
     * - Alphabetical sorting within hierarchy levels
     *
     * **Authorization Integration:**
     * - Automatic scope application based on user permissions
     * - Branch-level access control enforcement
     * - Role-based visibility restrictions
     *
     * **Performance Optimizations:**
     * - Minimal field selection for large datasets
     * - Efficient join queries for parent relationships
     * - Search optimization with indexed fields
     *
     * **Usage Examples:**
     * ```php
     * // Basic listing
     * GET /branches
     * 
     * // Search operations
     * GET /branches?search=atlantia        // Find "Atlantia" branches
     * GET /branches?search=virginia        // Search by location
     * GET /branches?search=windmaster      // Partial name match
     * GET /branches?search=Þórshöfn        // Norse character handling
     * ```
     *
     * @return \Cake\Http\Response|null|void Renders view with branches and search results
     */
    public function index()
    {

        $search = $this->request->getQuery('search');
        $search = $search ? trim($search) : null;
        $query = $this->Branches
            ->find('threaded')
            ->join([
                'parent' => [
                    'table' => 'branches',
                    'type' => 'LEFT',
                    'conditions' => 'parent.id = Branches.parent_id',
                ],
            ])
            ->join([
                'parent2' => [
                    'table' => 'branches',
                    'type' => 'LEFT',
                    'conditions' => 'parent2.id = parent.parent_id',
                ],
            ])
            ->join([
                'parent3' => [
                    'table' => 'branches',
                    'type' => 'LEFT',
                    'conditions' => 'parent3.id = parent2.parent_id',
                ],
            ])
            ->orderBy(['Branches.name' => 'ASC']);

        if ($search) {
            //detect th and replace with Þ
            $nsearch = $search;
            if (preg_match('/th/', $search)) {
                $nsearch = str_replace('th', 'Þ', $search);
            }
            //detect Þ and replace with th
            $usearch = $search;
            if (preg_match('/Þ/', $search)) {
                $usearch = str_replace('Þ', 'th', $search);
            }
            $query = $query->where([
                'OR' => [
                    ['Branches.name LIKE' => '%' . $search . '%'],
                    ['Branches.name LIKE' => '%' . $nsearch . '%'],
                    ['Branches.name LIKE' => '%' . $usearch . '%'],
                    ['Branches.location LIKE' => '%' . $search . '%'],
                    ['Branches.location LIKE' => '%' . $nsearch . '%'],
                    ['Branches.location LIKE' => '%' . $usearch . '%'],
                    ['parent.name LIKE' => '%' . $search . '%'],
                    ['parent.name LIKE' => '%' . $nsearch . '%'],
                    ['parent.name LIKE' => '%' . $usearch . '%'],
                    ['parent2.name LIKE' => '%' . $search . '%'],
                    ['parent2.name LIKE' => '%' . $nsearch . '%'],
                    ['parent2.name LIKE' => '%' . $usearch . '%'],
                    ['parent3.name LIKE' => '%' . $search . '%'],
                    ['parent3.name LIKE' => '%' . $nsearch . '%'],
                    ['parent3.name LIKE' => '%' . $usearch . '%'],
                ],
            ]);
        }
        $this->Authorization->authorizeAction();
        $this->Authorization->applyScope($query);
        $branches = $query->toArray();

        $this->set(compact('branches', 'search'));
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
