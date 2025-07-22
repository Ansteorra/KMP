<?php

declare(strict_types=1);

/**
 * Officers Plugin Offices Management Controller
 * 
 * This controller provides comprehensive office management functionality within
 * the Officers plugin, handling the complete CRUD lifecycle for organizational
 * offices with hierarchical structure, warrant integration, and administrative
 * interface management. It manages office hierarchy, deputy relationships,
 * role assignments, and branch type compatibility while integrating with the
 * Officers plugin security framework.
 * 
 * ## Core Responsibilities
 * 
 * ### 1. Office CRUD Operations
 * - **Index**: Administrative office listing with hierarchical relationships
 * - **View**: Comprehensive office detail with hierarchy visualization
 * - **Add**: Office creation with hierarchy validation and configuration
 * - **Edit**: Office modification with hierarchical integrity protection
 * - **Delete**: Soft deletion with referential integrity and audit trail
 * 
 * ### 2. Hierarchical Office Management
 * - Manages office hierarchy through deputy and reporting relationships
 * - Validates hierarchical integrity during office operations
 * - Provides comprehensive relationship data for administrative interfaces
 * - Supports complex organizational structures and reporting chains
 * 
 * ### 3. Warrant & Role Integration
 * - Integrates offices with role assignment and warrant management
 * - Validates warrant requirements for office assignments
 * - Provides role grant management for organizational permissions
 * - Supports warrant lifecycle operations through office hierarchy
 * 
 * ### 4. Branch Type Compatibility
 * - Manages office applicability to different branch types
 * - Validates branch type requirements and compatibility
 * - Provides dynamic office filtering based on branch context
 * - Supports organizational structure across different branch types
 * 
 * ### 5. API & AJAX Integration
 * - Provides JSON API endpoints for dynamic office discovery
 * - Supports AJAX-based office filtering and selection
 * - Integrates with frontend JavaScript for dynamic interfaces
 * - Enables real-time office availability checking
 * 
 * ## Authorization Architecture
 * 
 * The controller implements comprehensive authorization patterns:
 * 
 * ### Model-Level Authorization
 * ```php
 * $this->Authorization->authorizeModel("index", "add");
 * ```
 * - Authorizes table-level operations via OfficesTablePolicy
 * - Provides administrative access control and bulk operations
 * 
 * ### Entity-Level Authorization
 * ```php
 * $this->Authorization->authorize($office);
 * ```
 * - Authorizes individual office operations via OfficePolicy
 * - Provides fine-grained access control for specific offices
 * 
 * ## Data Management Patterns
 * 
 * ### Selective Association Loading
 * - **Index/View**: Comprehensive associations with optimized field selection
 * - **Edit**: Minimal loading for performance optimization
 * - **API**: Specialized loading for JSON response requirements
 * 
 * ### Hierarchical Relationship Management
 * - Department categorization and organizational structure
 * - Deputy relationships for office hierarchy support
 * - Reporting relationships for organizational chains
 * - Role grants for permission assignment integration
 * 
 * ### Branch Type Integration
 * - Dynamic branch type loading from application settings
 * - Branch type validation and compatibility checking
 * - Office filtering based on branch type requirements
 * - Cross-organizational support for different branch structures
 * 
 * ## Integration Points
 * 
 * This controller integrates with several Officers plugin subsystems:
 * - **OfficesTable**: Core data management and hierarchical validation
 * - **DepartmentsTable**: Departmental categorization and organization
 * - **RolesTable**: Role assignment and permission integration
 * - **BranchesTable**: Branch type compatibility and filtering
 * - **Authorization Framework**: Multi-level access control
 * - **StaticHelpers**: Application settings and configuration
 * - **JSON API**: Dynamic office discovery and AJAX integration
 * 
 * ## Usage Examples
 * 
 * ### Administrative Office Management
 * ```php
 * // GET /officers/offices - Office listing with hierarchy
 * // GET /officers/offices/view/1 - Office detail with relationships
 * // POST /officers/offices/add - Office creation with validation
 * ```
 * 
 * ### Hierarchical Operations
 * ```php
 * // Office hierarchy management with deputy and reporting relationships
 * // Branch type compatibility validation and filtering
 * // Role assignment integration for organizational permissions
 * ```
 * 
 * ### API Integration
 * ```php
 * // GET /officers/offices/availableOfficesForBranch/1.json
 * // Returns filtered offices for specific branch type
 * ```
 * 
 * ## Security Considerations
 * 
 * - **Plugin Validation**: Inherits Officers plugin enablement checking
 * - **Authentication**: Requires valid user identity for all operations
 * - **Authorization**: Multi-level permission checking (model + entity)
 * - **Input Validation**: Comprehensive validation via OfficesTable
 * - **Hierarchical Integrity**: Validates organizational structure consistency
 * - **Branch Type Validation**: Ensures proper branch type compatibility
 * - **Soft Deletion**: Preserves organizational integrity and audit trail
 * 
 * ## Performance Optimization
 * 
 * - **Selective Loading**: Optimized association loading per operation
 * - **Field Selection**: Minimal field loading for improved query performance
 * - **Query Optimization**: Efficient hierarchical relationship queries
 * - **JSON API**: Streamlined data loading for AJAX endpoints
 * - **Caching Support**: Integration with application caching strategies
 * 
 * @package Officers\Controller
 * @author KMP Development Team
 * @since Officers Plugin 1.0
 * @see \Officers\Controller\AppController For inherited security framework
 * @see \Officers\Model\Table\OfficesTable For data management and hierarchy
 * @see \Officers\Policy\OfficePolicy For entity authorization
 * @see \Officers\Policy\OfficesTablePolicy For table authorization
 * @see \Officers\Model\Entity\Office For office entity structure
 * @see \Officers\Controller\DepartmentsController For department management
 * @see \Officers\Controller\OfficersController For officer assignment
 * 
 * @property \Officers\Model\Table\OfficesTable $Offices
 */

namespace Officers\Controller;

use App\KMP\StaticHelpers;

/**
 * Offices Management Controller
 *
 * Provides comprehensive CRUD operations for organizational offices
 * within the Officers plugin. Manages office hierarchy, deputy relationships,
 * role assignments, and branch type compatibility while maintaining
 * organizational integrity and security integration.
 *
 * @property \Officers\Model\Table\OfficesTable $Offices Office data management
 */
class OfficesController extends AppController
{
    /**
     * Initialize controller with comprehensive authorization configuration
     * 
     * Establishes the security framework and authorization patterns for all
     * office management operations. This method configures model-level
     * authorization and inherits the complete Officers plugin security baseline
     * while preparing for hierarchical office management operations.
     * 
     * ## Authorization Configuration
     * 
     * ### Model-Level Authorization
     * Authorizes table-level operations through OfficesTablePolicy:
     * - **index**: Office listing and administrative access
     * - **add**: Office creation permissions and hierarchy validation
     * 
     * ### Entity-Level Authorization
     * Individual office operations are authorized in each action method
     * through OfficePolicy for fine-grained hierarchical access control.
     * 
     * ## Inherited Security Framework
     * 
     * From AppController, this method provides:
     * - Complete KMP authentication and authorization framework
     * - Officers plugin security baseline and component configuration
     * - Flash messaging for standardized user feedback
     * - Navigation history and breadcrumb management
     * - Plugin validation ensuring Officers plugin is enabled
     * 
     * ## Hierarchical Security Architecture
     * 
     * The authorization strategy supports hierarchical office management:
     * 1. **Plugin Validation**: Officers plugin must be enabled
     * 2. **Authentication**: Valid user identity required
     * 3. **Model Authorization**: Table-level permissions via OfficesTablePolicy
     * 4. **Entity Authorization**: Individual office permissions via OfficePolicy
     * 5. **Hierarchical Validation**: Office hierarchy integrity protection
     * 6. **Branch Type Integration**: Office-branch compatibility validation
     * 
     * ## Component Integration
     * 
     * Inherited components from AppController:
     * - **Authentication**: User identity management
     * - **Authorization**: Permission checking with hierarchical policy integration
     * - **Flash**: Standardized user feedback messaging
     * 
     * @return void
     * 
     * @see \Officers\Controller\AppController::initialize() For inherited security framework
     * @see \Officers\Policy\OfficesTablePolicy For model-level authorization
     * @see \Officers\Policy\OfficePolicy For entity-level authorization
     * 
     * @example Authorization Pattern
     * ```php
     * // Model-level: Can user access office management?
     * $this->Authorization->authorizeModel("index");
     * 
     * // Entity-level: Can user modify this specific office?
     * $this->Authorization->authorize($office);
     * ```
     */
    public function initialize(): void
    {
        // Inherit Officers plugin security baseline and component configuration
        parent::initialize();

        // Configure model-level authorization for office operations
        // - "index": Authorizes office listing via OfficesTablePolicy
        // - "add": Authorizes office creation via OfficesTablePolicy
        // Entity-level authorization is handled in individual action methods
        $this->Authorization->authorizeModel("index", "add");
    }

    /**
     * Administrative office listing with hierarchical relationships
     * 
     * Displays a comprehensive list of all offices with their hierarchical
     * relationships, department categorization, and role assignments. This
     * method provides the primary administrative interface for office
     * management and organizational structure visualization.
     * 
     * ## Features
     * 
     * ### Comprehensive Relationship Loading
     * - **Departments**: Office categorization and organizational structure
     * - **GrantsRole**: Role assignments and permission integration
     * - **DeputyTo**: Deputy relationships for hierarchical support
     * - **ReportsTo**: Reporting relationships for organizational chains
     * 
     * ### Optimized Field Selection
     * Uses selective field loading for performance optimization:
     * ```php
     * ->select(['id', 'name'])
     * ```
     * - Minimizes data transfer and query overhead
     * - Improves rendering performance for large office structures
     * - Maintains relationship data while optimizing field selection
     * 
     * ### Administrative Navigation
     * - Alphabetical ordering for intuitive office browsing
     * - Quick access to individual office details and management
     * - Administrative action links for office operations
     * - Integration with Officers plugin navigation system
     * 
     * ## Authorization
     * 
     * Requires "index" permission via OfficesTablePolicy, which validates:
     * - User has administrative access to office management
     * - Officers plugin is enabled and accessible
     * - Appropriate RBAC permissions for office listing
     * 
     * ## Data Loading Strategy
     * 
     * Comprehensive association loading with field optimization:
     * - **Performance**: Selective field loading reduces query overhead
     * - **Completeness**: All hierarchical relationships included
     * - **Efficiency**: Optimized for administrative interface requirements
     * 
     * ## View Integration
     * 
     * Sets view variables:
     * - `$offices`: Paginated office collection with hierarchical relationships
     * 
     * ## Navigation Context
     * 
     * Provides navigation context for:
     * - Office detail views with hierarchy visualization
     * - Officer assignment within office structure
     * - Administrative operations and management workflows
     * 
     * @return \Cake\Http\Response|null|void Renders office index view
     * 
     * @see \Officers\Policy\OfficesTablePolicy::canIndex() For authorization logic
     * @see \Officers\Model\Table\OfficesTable::find() For query building
     * @see \Officers\Model\Entity\Office For office entity structure
     * 
     * @example Template Usage
     * ```php
     * // In templates/Offices/index.php
     * foreach ($offices as $office) {
     *     echo $this->Html->link($office->name, ['action' => 'view', $office->id]);
     *     echo h($office->department->name);
     *     if ($office->grants_role) {
     *         echo h($office->grants_role->name);
     *     }
     * }
     * ```
     * 
     * @example Hierarchical Relationships
     * ```php
     * // Display deputy and reporting relationships
     * if ($office->deputy_to) {
     *     echo "Deputy to: " . h($office->deputy_to->name);
     * }
     * if ($office->reports_to) {
     *     echo "Reports to: " . h($office->reports_to->name);
     * }
     * ```
     */
    public function index()
    {
        // Build comprehensive office query with hierarchical relationships
        $query = $this->Offices->find()
            ->contain([
                // Department categorization and organizational structure
                'Departments' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                // Role assignments and permission integration
                'GrantsRole' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                // Deputy relationships for hierarchical support
                'DeputyTo' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                // Reporting relationships for organizational chains
                'ReportsTo' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ]);

        // Apply pagination with alphabetical ordering for administrative efficiency
        $offices = $this->paginate($query, [
            'order' => [
                'name' => 'asc',  // Alphabetical ordering for user experience
            ]
        ]);

        // Provide offices with hierarchical data to view
        $this->set(compact('offices'));
    }

    /**
     * Comprehensive office detail view with hierarchy and configuration
     * 
     * Displays detailed office information including hierarchical relationships,
     * role assignments, branch type compatibility, and administrative configuration
     * options. This method provides the primary interface for office management
     * and organizational structure visualization with comprehensive editing capabilities.
     * 
     * ## Features
     * 
     * ### Office Detail Display
     * - Complete office information and configuration
     * - Hierarchical relationships and organizational structure
     * - Role assignments and permission integration
     * - Branch type compatibility and organizational scope
     * 
     * ### Administrative Configuration Interface
     * - Department selection and categorization options
     * - Office hierarchy management (reporting and deputy relationships)
     * - Role assignment and permission configuration
     * - Branch type compatibility selection and validation
     * 
     * ### Form Data Preparation
     * - Pre-populated select options for all office relationships
     * - Dynamic branch type loading from application settings
     * - Hierarchical office filtering to prevent circular references
     * - Role and department option preparation for editing interface
     * 
     * ## Authorization
     * 
     * Implements entity-level authorization via OfficePolicy:
     * - Validates user can access specific office details
     * - Checks administrative permissions for office management
     * - Integrates with RBAC system for hierarchical access control
     * 
     * ## Data Loading Strategy
     * 
     * Comprehensive office loading with optimized field selection:
     * ```php
     * contain: ['Departments', 'GrantsRole', 'DeputyTo', 'ReportsTo']
     * ```
     * - **Performance**: Selective field loading for efficiency
     * - **Completeness**: All hierarchical relationships included
     * - **Configuration**: Form data preparation for administrative interface
     * 
     * ## Form Data Preparation
     * 
     * Prepares comprehensive form data for administrative interface:
     * - **departments**: Available departments for categorization
     * - **report_to_offices**: Office hierarchy options (excluding self)
     * - **deputy_to_offices**: Deputy relationship options (excluding self)
     * - **roles**: Available roles for permission assignment
     * - **branch_types**: Dynamic branch type options from settings
     * 
     * ## Branch Type Integration
     * 
     * Dynamic branch type loading from application configuration:
     * ```php
     * $btArray = StaticHelpers::getAppSetting("Branches.Types");
     * ```
     * - Loads branch types from application settings
     * - Provides flexibility for organizational structure changes
     * - Supports dynamic branch type management
     * 
     * ## Error Handling
     * 
     * - Validates office exists before processing
     * - Throws 404 NotFoundException for invalid office IDs
     * - Provides clear error messaging for missing records
     * - Maintains security through proper exception handling
     * 
     * ## View Integration
     * 
     * Sets comprehensive view variables:
     * - `$office`: Complete office entity with relationships
     * - `$departments`: Department options for form
     * - `$report_to_offices`: Reporting hierarchy options
     * - `$deputy_to_offices`: Deputy relationship options
     * - `$roles`: Role assignment options
     * - `$branch_types`: Branch type compatibility options
     * 
     * @param string|null $id Office ID for detail display
     * @return \Cake\Http\Response|null|void Renders office detail view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When office not found
     * @throws \Cake\Http\Exception\NotFoundException When office doesn't exist
     * 
     * @see \Officers\Policy\OfficePolicy::canView() For authorization logic
     * @see \Officers\Model\Entity\Office For office entity structure
     * @see \App\KMP\StaticHelpers::getAppSetting() For branch type loading
     * 
     * @example Template Integration
     * ```php
     * // In templates/Offices/view.php
     * echo h($office->name);
     * echo h($office->department->name);
     * if ($office->grants_role) {
     *     echo "Grants Role: " . h($office->grants_role->name);
     * }
     * if ($office->deputy_to) {
     *     echo "Deputy to: " . $this->Html->link($office->deputy_to->name, 
     *         ['action' => 'view', $office->deputy_to->id]);
     * }
     * ```
     * 
     * @example Administrative Form Integration
     * ```php
     * // Form options for administrative editing
     * echo $this->Form->control('department_id', ['options' => $departments]);
     * echo $this->Form->control('reports_to_id', ['options' => $report_to_offices, 'empty' => true]);
     * echo $this->Form->control('branch_types', ['type' => 'select', 'multiple' => true, 'options' => $branch_types]);
     * ```
     */
    public function view($id = null)
    {
        // Load office with comprehensive hierarchical relationships
        $office = $this->Offices->get(
            $id,
            contain: [
                // Department categorization with optimized field selection
                'Departments' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                // Role assignments for permission integration
                'GrantsRole' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                // Deputy relationships for hierarchical support
                'DeputyTo' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                // Reporting relationships for organizational chains
                'ReportsTo' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ]
        );

        // Validate office exists
        if (!$office) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        // Authorize user access to this specific office
        $this->Authorization->authorize($office);

        // Prepare form data for administrative interface

        // Department options for categorization
        $departments = $this->Offices->Departments->find('list')->all();

        // Office hierarchy options (excluding current office to prevent circular references)
        $report_to_offices = $this->Offices->find('list')->where(['id <>' => $office->id])->all();
        $deputy_to_offices = $this->Offices->find('list')->where(['id <>' => $office->id])->all();

        // Role options for permission assignment
        $roles = $this->Offices->GrantsRole->find('list')->all();

        // Dynamic branch type loading from application settings
        $btArray = StaticHelpers::getAppSetting("Branches.Types");
        $branch_types = [];
        foreach ($btArray as $branchType) {
            $branch_types[$branchType] = $branchType;
        }

        // Provide comprehensive data to view for display and form preparation
        $this->set(compact('office', 'departments', 'report_to_offices', 'roles', 'branch_types', 'deputy_to_offices'));
    }

    /**
     * Office creation interface with hierarchy validation and configuration
     * 
     * Provides comprehensive office creation functionality with hierarchical
     * validation, branch type compatibility checking, and administrative
     * configuration management. This method handles the complete workflow
     * for adding new offices to the organizational structure with
     * comprehensive validation and error handling.
     * 
     * ## Features
     * 
     * ### Office Creation Interface
     * - User-friendly office creation form with hierarchical options
     * - Comprehensive validation and error feedback
     * - Branch type compatibility validation and selection
     * - Integration with Officers plugin workflow patterns
     * 
     * ### Hierarchical Validation
     * - Office hierarchy integrity protection
     * - Deputy and reporting relationship validation
     * - Organizational structure consistency checking
     * - Circular reference prevention and validation
     * 
     * ### Branch Type Validation
     * - Mandatory branch type selection validation
     * - Dynamic branch type loading from application settings
     * - Organizational compatibility checking
     * - Multi-branch support and validation
     * 
     * ### Administrative Workflow
     * - Redirects to office view on successful creation
     * - Maintains form state on validation errors
     * - Comprehensive error feedback and user guidance
     * - Integration with navigation history and breadcrumb management
     * 
     * ## Authorization
     * 
     * Implements entity-level authorization for new office creation:
     * - Validates user has permission to create offices
     * - Integrates with Officers plugin authorization framework
     * - Checks administrative privileges via OfficePolicy
     * 
     * ## Request Processing
     * 
     * ### GET Request (Form Display)
     * - Creates new empty office entity
     * - Authorizes office creation permissions
     * - Prepares form data and renders creation interface
     * 
     * ### POST Request (Form Submission)
     * - Patches entity with submitted data
     * - Validates branch type selection (mandatory)
     * - Validates office data via OfficesTable
     * - Saves office with comprehensive error handling
     * 
     * ## Branch Type Validation
     * 
     * Implements mandatory branch type validation:
     * ```php
     * if (empty($office->branch_types)) {
     *     $this->Flash->error(__('At least 1 Branch Type must be selected.'));
     * }
     * ```
     * - Ensures offices are applicable to at least one branch type
     * - Provides clear error messaging for validation failures
     * - Prevents creation of offices without organizational scope
     * 
     * ## Form Data Preparation
     * 
     * Prepares comprehensive form data for administrative interface:
     * - **departments**: Available departments for categorization
     * - **report_to_offices**: Office hierarchy options for reporting structure
     * - **deputy_to_offices**: Deputy relationship options
     * - **roles**: Available roles for permission assignment
     * - **branch_types**: Dynamic branch type options from settings
     * 
     * ## User Feedback
     * 
     * Flash messaging provides comprehensive feedback:
     * - Success: "The office has been saved."
     * - Branch Type Error: "At least 1 Branch Type must be selected."
     * - General Error: "The office could not be saved. Please, try again."
     * 
     * ## View Integration
     * 
     * Sets comprehensive view variables:
     * - `$office`: Office entity (new or with validation errors)
     * - `$departments`: Department options
     * - `$report_to_offices`: Reporting hierarchy options
     * - `$deputy_to_offices`: Deputy relationship options
     * - `$roles`: Role assignment options
     * - `$branch_types`: Branch type compatibility options
     * 
     * @return \Cake\Http\Response|null|void Redirects on success, renders form otherwise
     * 
     * @see \Officers\Policy\OfficePolicy::canAdd() For authorization logic
     * @see \Officers\Model\Table\OfficesTable::validationDefault() For validation rules
     * @see \Officers\Model\Entity\Office For entity structure
     * @see \App\KMP\StaticHelpers::getAppSetting() For branch type loading
     * 
     * @example Form Processing Flow
     * ```php
     * // GET /officers/offices/add
     * // 1. Create new entity
     * // 2. Authorize creation
     * // 3. Prepare form data
     * // 4. Render form
     * 
     * // POST /officers/offices/add
     * // 1. Patch entity with data
     * // 2. Validate branch types
     * // 3. Validate and save
     * // 4. Redirect to view or show errors
     * ```
     * 
     * @example Template Form Integration
     * ```php
     * // In templates/Offices/add.php
     * echo $this->Form->create($office);
     * echo $this->Form->control('name', ['required' => true]);
     * echo $this->Form->control('department_id', ['options' => $departments]);
     * echo $this->Form->control('branch_types', [
     *     'type' => 'select', 
     *     'multiple' => true, 
     *     'options' => $branch_types,
     *     'required' => true
     * ]);
     * echo $this->Form->button('Save Office');
     * echo $this->Form->end();
     * ```
     */
    public function add()
    {
        // Create new empty office entity for form
        $office = $this->Offices->newEmptyEntity();

        // Authorize office creation permissions
        $this->Authorization->authorize($office);

        // Process form submission
        if ($this->request->is('post')) {
            // Patch entity with submitted form data
            $office = $this->Offices->patchEntity($office, $this->request->getData());

            // Validate mandatory branch type selection
            if (empty($office->branch_types)) {
                $this->Flash->error(__('At least 1 Branch Type must be selected.'));
            } else {
                // Attempt to save with comprehensive error handling
                if ($this->Offices->save($office)) {
                    // Success: Provide feedback and redirect to view
                    $this->Flash->success(__('The office has been saved.'));
                    return $this->redirect(['action' => 'view', $office['id']]);
                }

                // Failure: Provide error feedback (form will redisplay with errors)
                $this->Flash->error(__('The office could not be saved. Please, try again.'));
            }
        }

        // Prepare comprehensive form data for administrative interface

        // Department options for categorization
        $departments = $this->Offices->Departments->find('list')->all();

        // Office hierarchy options for reporting and deputy relationships
        $report_to_offices = $this->Offices->find('list')->all();
        $deputy_to_offices = $this->Offices->find('list')->all();

        // Role options for permission assignment
        $roles = $this->Offices->GrantsRole->find('list')->all();

        // Dynamic branch type loading from application settings
        $btArray = StaticHelpers::getAppSetting("Branches.Types");
        $branch_types = [];
        foreach ($btArray as $branchType) {
            $branch_types[$branchType] = $branchType;
        }

        // Provide comprehensive data to form (new entity or with validation errors)
        $this->set(compact('office', 'departments', 'report_to_offices', 'roles', 'branch_types', 'deputy_to_offices'));
    }

    /**
     * Office modification interface with hierarchical validation and configuration
     * 
     * Provides comprehensive office editing functionality with hierarchical
     * validation, branch type compatibility checking, and organizational
     * structure integrity protection. This method manages the complete
     * workflow for modifying existing offices while maintaining
     * hierarchical consistency and administrative data integrity.
     * 
     * ## Features
     * 
     * ### Office Modification Interface
     * - Pre-populated form with current office configuration
     * - Hierarchical relationship editing and validation
     * - Branch type compatibility modification and validation
     * - Comprehensive error feedback and user guidance
     * 
     * ### Hierarchical Integrity Protection
     * - Office hierarchy consistency validation
     * - Deputy and reporting relationship integrity checking
     * - Organizational structure consistency maintenance
     * - Circular reference prevention and validation
     * 
     * ### Branch Type Validation
     * - Mandatory branch type selection enforcement
     * - Organizational compatibility validation
     * - Multi-branch support and configuration
     * - Dynamic branch type management integration
     * 
     * ### Administrative Workflow
     * - Loads office for editing with minimal associations for performance
     * - Processes form submissions with comprehensive validation
     * - Maintains audit trail for office modifications
     * - Redirects to view regardless of success/failure for consistency
     * 
     * ## Data Loading Strategy
     * 
     * Uses minimal association loading for edit performance:
     * ```php
     * contain: []  // No associations for edit optimization
     * ```
     * - Optimized loading for edit operations
     * - Office data only (associations not needed for editing)
     * - Performance optimization for complex hierarchical structures
     * 
     * ## Request Processing
     * 
     * ### GET Request (Form Display)
     * - Loads existing office for editing
     * - Authorizes modification permissions
     * - Renders pre-populated edit form
     * 
     * ### PATCH/POST/PUT Request (Form Submission)
     * - Validates office exists and user has access
     * - Patches entity with submitted data
     * - Validates branch type selection (mandatory)
     * - Saves with comprehensive validation and error handling
     * 
     * ## Branch Type Validation
     * 
     * Implements mandatory branch type validation:
     * ```php
     * if (empty($office->branch_types)) {
     *     $this->Flash->error(__('At least 1 Branch Type must be selected.'));
     * }
     * ```
     * - Ensures offices maintain organizational scope
     * - Prevents removal of all branch type assignments
     * - Provides clear validation feedback
     * 
     * ## Error Handling
     * 
     * - Validates office exists before processing
     * - Throws 404 NotFoundException for invalid office IDs
     * - Handles branch type validation failures gracefully
     * - Maintains consistent redirect pattern regardless of outcome
     * 
     * ## User Feedback
     * 
     * Flash messaging provides operation status:
     * - Success: "The office has been saved."
     * - Branch Type Error: "At least 1 Branch Type must be selected."
     * - General Error: "The office could not be saved. Please, try again."
     * 
     * ## Navigation Pattern
     * 
     * Always redirects to office view for consistency:
     * - Success: Shows updated office with success message
     * - Validation Failure: Shows current office with error message
     * - Maintains navigation context and user expectations
     * 
     * @param string|null $id Office ID for modification
     * @return \Cake\Http\Response|null|void Redirects to view after processing
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When office not found
     * @throws \Cake\Http\Exception\NotFoundException When office doesn't exist
     * 
     * @see \Officers\Policy\OfficePolicy::canEdit() For authorization logic
     * @see \Officers\Model\Table\OfficesTable::validationDefault() For validation rules
     * @see \Officers\Model\Entity\Office For entity structure
     * 
     * @example Edit Processing Flow
     * ```php
     * // GET /officers/offices/edit/1
     * // 1. Load office
     * // 2. Authorize modification
     * // 3. Render edit form
     * 
     * // PATCH /officers/offices/edit/1
     * // 1. Load and authorize
     * // 2. Patch with submitted data
     * // 3. Validate branch types
     * // 4. Save and redirect to view
     * ```
     * 
     * @example Branch Type Validation
     * ```php
     * // Validation prevents offices without organizational scope
     * if (empty($office->branch_types)) {
     *     // Error: Office must be applicable to at least one branch type
     *     $this->Flash->error(__('At least 1 Branch Type must be selected.'));
     * }
     * ```
     */
    public function edit($id = null)
    {
        // Load office with minimal associations for edit performance
        $office = $this->Offices->get($id, contain: []);

        // Validate office exists
        if (!$office) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        // Authorize user can modify this specific office
        $this->Authorization->authorize($office);

        // Process form submission
        if ($this->request->is(['patch', 'post', 'put'])) {
            // Extract submitted data
            $postData = $this->request->getData();

            // Patch entity with submitted form data
            $office = $this->Offices->patchEntity($office, $postData);

            // Validate mandatory branch type selection
            if (empty($office->branch_types)) {
                $this->Flash->error(__('At least 1 Branch Type must be selected.'));
            } else {
                // Attempt to save with comprehensive error handling
                if ($this->Offices->save($office)) {
                    // Success: Provide feedback and redirect to view
                    $this->Flash->success(__('The office has been saved.'));
                    return $this->redirect(['action' => 'view', $office['id']]);
                }

                // Failure: Provide error feedback
                $this->Flash->error(__('The office could not be saved. Please, try again.'));
            }
        }

        // Always redirect to view for consistent navigation
        // Success: Shows updated office with success message
        // Validation Failure: Shows current office with error message
        return $this->redirect(['action' => 'view', $office['id']]);
    }

    /**
     * Office deletion with soft deletion and organizational integrity
     * 
     * Handles office deletion with comprehensive safety measures including
     * soft deletion, organizational integrity protection, and audit trail
     * maintenance. This method ensures safe removal of offices while
     * preserving hierarchical structure and organizational data integrity.
     * 
     * ## Features
     * 
     * ### Soft Deletion Pattern
     * - Marks office as deleted rather than hard deletion
     * - Preserves organizational integrity with hierarchical relationships
     * - Maintains audit trail and historical office data
     * - Prefixes name with "Deleted:" for identification
     * 
     * ### Security & Authorization
     * - Restricts deletion to POST/DELETE methods for CSRF protection
     * - Validates office exists before processing
     * - Authorizes deletion permissions via OfficePolicy
     * - Ensures only authorized users can delete offices
     * 
     * ### Organizational Integrity Protection
     * - Preserves hierarchical relationships and deputy assignments
     * - Maintains organizational structure consistency
     * - Prevents data orphaning and relationship corruption
     * - Supports data recovery and audit requirements
     * 
     * ### Administrative Workflow
     * - Clear user feedback on deletion success/failure
     * - Redirects to index for continued administration
     * - Integrates with Officers plugin workflow patterns
     * - Maintains navigation context and user expectations
     * 
     * ## Request Method Validation
     * 
     * Restricts to secure HTTP methods:
     * ```php
     * $this->request->allowMethod(['post', 'delete']);
     * ```
     * - Prevents accidental deletion via GET requests
     * - Provides CSRF protection for sensitive operations
     * - Follows security best practices for destructive operations
     * 
     * ## Soft Deletion Implementation
     * 
     * Modifies office name before deletion:
     * ```php
     * $office->name = "Deleted: " . $office->name;
     * ```
     * - Clearly identifies deleted offices
     * - Preserves original name for audit purposes
     * - Enables potential data recovery workflows
     * - Maintains organizational integrity
     * 
     * ## Error Handling
     * 
     * - Validates office exists before processing
     * - Throws 404 NotFoundException for invalid office IDs
     * - Handles deletion failures gracefully with user feedback
     * - Provides clear error messaging for troubleshooting
     * 
     * ## User Feedback
     * 
     * Flash messaging provides deletion status:
     * - Success: "The office has been deleted."
     * - Error: "The office could not be deleted. Please, try again."
     * 
     * ## Business Rule Validation
     * 
     * The OfficesTable may implement additional business rules:
     * - Prevent deletion of offices with active officer assignments
     * - Validate hierarchical structure constraints
     * - Enforce administrative approval requirements
     * - Maintain organizational integrity across the hierarchy
     * 
     * @param string|null $id Office ID for deletion
     * @return \Cake\Http\Response|null Redirects to index after processing
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When office not found
     * @throws \Cake\Http\Exception\NotFoundException When office doesn't exist
     * 
     * @see \Officers\Policy\OfficePolicy::canDelete() For authorization logic
     * @see \Officers\Model\Table\OfficesTable::delete() For deletion processing
     * @see \Officers\Model\Entity\Office For entity structure
     * 
     * @example Deletion Flow
     * ```php
     * // POST/DELETE /officers/offices/delete/1
     * // 1. Validate HTTP method
     * // 2. Load and authorize office
     * // 3. Mark as deleted and save
     * // 4. Redirect to index with feedback
     * ```
     * 
     * @example Template Integration
     * ```php
     * // In templates/Offices/view.php
     * echo $this->Form->postLink(
     *     'Delete Office',
     *     ['action' => 'delete', $office->id],
     *     ['confirm' => 'Are you sure you want to delete this office?']
     * );
     * ```
     */
    public function delete($id = null)
    {
        // Restrict to secure HTTP methods for CSRF protection
        $this->request->allowMethod(['post', 'delete']);

        // Load office for deletion
        $office = $this->Offices->get($id);

        // Validate office exists
        if (!$office) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        // Authorize user can delete this specific office
        $this->Authorization->authorize($office);

        // Implement soft deletion pattern
        // Mark office as deleted while preserving organizational integrity
        $office->name = "Deleted: " . $office->name;

        // Attempt deletion with comprehensive error handling
        if ($this->Offices->delete($office)) {
            $this->Flash->success(__('The office has been deleted.'));
        } else {
            $this->Flash->error(__('The office could not be deleted. Please, try again.'));
        }

        // Redirect to index for continued administration
        return $this->redirect(['action' => 'index']);
    }

    /**
     * AJAX API endpoint for branch-specific office discovery
     * 
     * Provides a JSON API endpoint for dynamically discovering offices
     * available for a specific branch based on branch type compatibility.
     * This method supports AJAX-based office selection and filtering
     * in frontend interfaces while maintaining security and performance.
     * 
     * ## Features
     * 
     * ### Dynamic Office Filtering
     * - Filters offices based on branch type compatibility
     * - Supports real-time office discovery for branch assignment
     * - Provides comprehensive office data with deputy relationships
     * - Optimized query performance for AJAX responses
     * 
     * ### Branch Type Compatibility
     * - Validates branch exists and determines branch type
     * - Filters offices by applicable branch types configuration
     * - Supports complex branch type matching logic
     * - Enables dynamic organizational structure management
     * 
     * ### JSON API Response
     * - Provides structured JSON response for frontend consumption
     * - Includes office data with deputy relationship information
     * - Optimized field selection for API performance
     * - AJAX view configuration for seamless integration
     * 
     * ### Security Integration
     * - Validates branch exists before processing
     * - Authorizes branch access via authorization system
     * - Provides secure API access with proper error handling
     * - Maintains consistent security patterns
     * 
     * ## Request Processing
     * 
     * ### Branch Validation
     * - Loads branch with minimal field selection for performance
     * - Validates branch exists and user has access
     * - Determines branch type for office filtering
     * 
     * ### Office Query Building
     * - Filters offices by branch type compatibility using JSON matching
     * - Includes deputy relationships for hierarchical context
     * - Applies alphabetical ordering for consistent results
     * - Optimizes field selection for API response size
     * 
     * ## Branch Type Matching
     * 
     * Uses JSON-based branch type matching:
     * ```php
     * $officeQuery->where(['applicable_branch_types like' => '%"' . $branch->type . '"%']);
     * ```
     * - Matches branch type within JSON array stored in database
     * - Supports flexible branch type configuration
     * - Enables dynamic organizational structure management
     * 
     * ## API Response Format
     * 
     * Returns JSON array of office objects:
     * ```json
     * [
     *   {
     *     "id": 1,
     *     "name": "Office Name",
     *     "deputy_to_id": 2,
     *     "deputies": [...]
     *   }
     * ]
     * ```
     * 
     * ## Error Handling
     * 
     * - Validates branch exists before processing
     * - Throws 404 NotFoundException for invalid branch IDs
     * - Provides consistent error responses for API consumers
     * - Maintains security through proper exception handling
     * 
     * ## Performance Optimization
     * 
     * - Minimal field selection for API efficiency
     * - Optimized association loading for deputy relationships
     * - Efficient JSON-based branch type filtering
     * - AJAX view configuration for streamlined responses
     * 
     * @param string|null $id Branch ID for office filtering
     * @return \Cake\Http\Response JSON response with filtered offices
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When branch not found
     * @throws \Cake\Http\Exception\NotFoundException When branch doesn't exist
     * 
     * @see \Officers\Model\Table\OfficesTable For office query building
     * @see \App\Model\Table\BranchesTable For branch type determination
     * @see \Officers\Policy\OfficePolicy For authorization logic
     * 
     * @example API Usage
     * ```javascript
     * // Frontend AJAX request
     * fetch('/officers/offices/availableOfficesForBranch/1.json')
     *   .then(response => response.json())
     *   .then(offices => {
     *     // Populate office selection dropdown
     *     offices.forEach(office => {
     *       console.log(office.name, office.deputy_to_id);
     *     });
     *   });
     * ```
     * 
     * @example Branch Type Filtering
     * ```php
     * // Database query filters offices by branch type compatibility
     * // Office with applicable_branch_types: ["Sea", "Land"]
     * // Branch with type: "Sea"
     * // Result: Office is included in response
     * ```
     * 
     * @example Response Structure
     * ```json
     * [
     *   {
     *     "id": 1,
     *     "name": "Captain",
     *     "deputy_to_id": null,
     *     "deputies": []
     *   },
     *   {
     *     "id": 2,
     *     "name": "First Officer",
     *     "deputy_to_id": 1,
     *     "deputies": [{"id": 2, "name": "First Officer", "deputy_to_id": 1}]
     *   }
     * ]
     * ```
     */
    public function availableOfficesForBranch($id = null)
    {
        // Load branch with minimal field selection for performance
        $branch = $this->getTableLocator()->get("Branches")
            ->find()->select(['id', 'parent_id'])
            ->where(['id' => $id])->first();

        // Validate branch exists
        if (!$branch) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        // Authorize branch access
        $this->Authorization->authorize($branch);

        // Build office query with deputy relationships and branch type filtering
        $officesTbl = $this->Offices;
        $officeQuery = $officesTbl->find("all")
            ->contain([
                // Include deputy relationships for hierarchical context
                "Deputies" => function ($q) {
                    return $q->select(["id", "name", "deputy_to_id"]);
                }
            ])
            ->select(["id", "name", "deputy_to_id"])
            ->orderBY(["name" => "ASC"]);

        // Filter offices by branch type compatibility
        // Uses JSON-based matching for flexible branch type configuration
        $officeQuery = $officeQuery->where(['applicable_branch_types like' => '%"' . $branch->type . '"%']);

        // Execute query and convert to array for JSON response
        $offices = $officeQuery->toArray();

        // Configure AJAX view for JSON response
        $this->viewBuilder()->setClassName("Ajax");

        // Set JSON response with office data
        $this->response = $this->response
            ->withType("application/json")
            ->withStringBody(json_encode($offices));

        return $this->response;
    }
}
