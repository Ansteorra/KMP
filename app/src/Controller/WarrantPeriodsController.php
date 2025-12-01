<?php

declare(strict_types=1);

namespace App\Controller;

use App\Services\CsvExportService;

/**
 * WarrantPeriodsController - Warrant Period Template Management Interface
 *
 * The WarrantPeriodsController provides a comprehensive administrative interface for
 * managing warrant period templates within the KMP warrant system. This controller
 * handles the creation, listing, and deletion of period templates that serve as
 * standardized duration definitions for organizational warrant management.
 *
 * **Core Architecture:**
 * - Extends AppController for KMP framework integration
 * - Implements Authorization component for security controls
 * - Provides REST-like actions for period template management
 * - Integrates with authorization policies for access control
 * - Supports pagination and scoped data access
 *
 * **Administrative Interface Features:**
 * - Period template listing with chronological ordering
 * - Modal-based period creation interface
 * - Inline add/delete operations for administrative efficiency
 * - Authorization-scoped data access for organizational security
 * - Flash messaging for user feedback and operation status
 *
 * **Security Architecture:**
 * - Authorization component integration for access control
 * - Policy-based authorization for all CRUD operations
 * - Scoped queries for organizational data isolation
 * - CSRF protection for state-changing operations
 * - Administrative permission requirements for all actions
 *
 * **Template Management Operations:**
 * - **Index**: List available period templates with pagination
 * - **Add**: Create new period templates with validation
 * - **Delete**: Remove existing period templates with authorization
 * - Modal-based UI for streamlined administrative workflows
 * - Chronological ordering for temporal template management
 *
 * **Integration Points:**
 * - WarrantPeriodsTable for data management and validation
 * - Authorization policies for security enforcement
 * - Flash component for user feedback messaging
 * - Pagination component for large template collections
 * - Template system for administrative interface rendering
 *
 * **Business Logic:**
 * - Period template standardization for organizational consistency
 * - Administrative tools for warrant duration management
 * - Template lifecycle operations with audit support
 * - Organizational scope enforcement through authorization
 * - Validation integration for temporal consistency
 *
 * **Usage Examples:**
 * ```php
 * // Administrative access to period templates
 * // GET /warrant-periods - List all available period templates
 * // POST /warrant-periods/add - Create new period template
 * // DELETE /warrant-periods/delete/123 - Remove period template
 * 
 * // Controller integration example
 * $controller = new WarrantPeriodsController();
 * $controller->initialize(); // Sets up authorization
 * $response = $controller->index(); // Lists period templates
 * ```
 *
 * **Authorization Requirements:**
 * - Administrative permissions required for all operations
 * - Policy-based authorization for entity-level access control
 * - Organizational scope enforcement for data isolation
 * - Authorization component integration for security validation
 *
 * **User Interface Integration:**
 * - Bootstrap modal integration for period creation
 * - Table-based listing with sortable columns
 * - Action buttons for administrative operations
 * - Flash messaging for operation feedback
 * - Responsive design for various screen sizes
 *
 * **Template Management Workflow:**
 * ```php
 * // Typical administrative workflow
 * 1. Access period management interface (/warrant-periods)
 * 2. Review existing period templates (index action)
 * 3. Create new period template via modal (add action)
 * 4. Validate and save period data with audit trail
 * 5. Remove obsolete templates as needed (delete action)
 * ```
 *
 * @see \App\Model\Table\WarrantPeriodsTable For period data management
 * @see \App\Model\Entity\WarrantPeriod For period entity functionality
 * @see \App\Policy\WarrantPeriodPolicy For authorization rules
 *
 * @property \App\Model\Table\WarrantPeriodsTable $WarrantPeriods
 * @property \Authorization\Controller\Component\AuthorizationComponent $Authorization
 */
class WarrantPeriodsController extends AppController
{
    use DataverseGridTrait;

    /**
     * Initialize controller - Component Setup and Authorization Integration
     *
     * Configures the WarrantPeriodsController with essential components for
     * authorization and security. This initialization ensures that all period
     * template management operations are properly secured and follow KMP
     * organizational access control patterns.
     *
     * **Component Configuration:**
     * - **Authorization Component**: Integrates CakePHP Authorization plugin
     *   - Enables policy-based authorization for all controller actions
     *   - Provides entity-level authorization checking
     *   - Supports scoped queries for organizational data isolation
     *   - Integrates with KMP identity and permission system
     *
     * **Security Architecture:**
     * - All actions require administrative authorization
     * - Entity-level authorization for period template operations
     * - Organizational scope enforcement through authorization policies
     * - Integration with KMP RBAC system for permission validation
     *
     * **Administrative Access Control:**
     * - Period template management requires administrative permissions
     * - Authorization component enforces policy-based access control
     * - Scoped data access ensures organizational data isolation
     * - Integration with KMP authentication and authorization framework
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Authorization.Authorization');
        $this->Authorization->authorizeModel('index', 'add', 'gridData');
    }

    /**
     * Index method - Display Dataverse grid for warrant periods
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Simple index page - just renders the dv_grid element
        // The dv_grid element will lazy-load the actual data via gridData action

        // Keep empty entity for add modal
        $emptyWarrantPeriod = $this->WarrantPeriods->newEmptyEntity();
        $this->set(compact('emptyWarrantPeriod'));
    }

    /**
     * Grid Data method - Provides Dataverse grid data for warrant periods
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'WarrantPeriods.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\WarrantPeriodsGridColumns::class,
            'baseQuery' => $this->WarrantPeriods->find(),
            'tableName' => 'WarrantPeriods',
            'defaultSort' => ['WarrantPeriods.start_date' => 'desc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'warrant-periods');
        }

        // Set view variables
        $this->set([
            'warrantPeriods' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\WarrantPeriodsGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'warrant-periods-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'warrant-periods-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'warrant-periods-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Add method - Period Template Creation and Validation
     *
     * Handles the creation of new warrant period templates through a comprehensive
     * validation and authorization workflow. This action supports both GET requests
     * for form display and POST requests for template creation, integrating with
     * the KMP authorization system and validation framework.
     *
     * **Request Handling:**
     * - **GET**: Displays period creation form (typically in modal)
     * - **POST**: Processes period template creation with validation
     *
     * **Authorization Flow:**
     * - Creates empty period entity for authorization checking
     * - Applies policy-based authorization before any operations
     * - Ensures administrative permissions for period template creation
     * - Integrates with organizational access control structure
     *
     * **Creation Workflow:**
     * 1. **Authorization**: Verify user permissions for period creation
     * 2. **Form Processing**: Handle POST data with entity patching
     * 3. **Validation**: Apply WarrantPeriodsTable validation rules
     * 4. **Persistence**: Save validated period template to database
     * 5. **Feedback**: Provide user feedback through Flash messaging
     * 6. **Redirect**: Return to index on successful creation
     *
     * **Data Processing:**
     * - Creates new empty WarrantPeriod entity
     * - Patches entity with validated request data
     * - Applies comprehensive validation rules from WarrantPeriodsTable
     * - Handles creation timestamps and audit trail automatically
     *
     * **Validation Integration:**
     * - Date format validation for start_date and end_date
     * - Required field validation for essential period data
     * - Business rule validation for temporal consistency
     * - Integration with entity-level validation rules
     *
     * **User Feedback:**
     * - **Success**: Flash success message and redirect to index
     * - **Failure**: Flash error message and re-display form
     * - Validation errors displayed through form field integration
     * - Administrative feedback for operation status
     *
     * **Security Features:**
     * - CSRF protection for POST requests
     * - Authorization checking before any operations
     * - Input validation and sanitization
     * - Administrative permission requirements
     *
     * **Administrative Usage:**
     * ```php
     * // Typical period creation workflow
     * 1. Administrator clicks "Add" button on index page
     * 2. Modal form displays with empty period template
     * 3. Administrator enters period dates and details
     * 4. Form submission triggers validation and authorization
     * 5. Successful creation redirects to updated index listing
     * ```
     *
     * **Error Handling:**
     * - Validation errors displayed in form fields
     * - Authorization failures handled by authorization component
     * - Database errors result in flash error messages
     * - Form data preserved on validation failures
     *
     * **Integration Points:**
     * - WarrantPeriodsTable for validation and persistence
     * - Authorization policies for security enforcement
     * - Flash component for user feedback
     * - Template system for form rendering
     *
     * **View Variables:**
     * - `$warrantPeriod`: Period entity (empty or with validation errors)
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $warrantPeriod = $this->WarrantPeriods->newEmptyEntity();
        $this->Authorization->authorize($warrantPeriod);
        if ($this->request->is('post')) {
            $warrantPeriod = $this->WarrantPeriods->patchEntity($warrantPeriod, $this->request->getData());
            if ($this->WarrantPeriods->save($warrantPeriod)) {
                $this->Flash->success(__('The warrant period has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant period could not be saved. Please, try again.'));
        }
        $this->set(compact('warrantPeriod'));
    }

    /**
     * Delete method - Period Template Removal with Authorization
     *
     * Handles the secure deletion of warrant period templates through a comprehensive
     * authorization and validation workflow. This action ensures that only authorized
     * administrators can remove period templates while maintaining data integrity
     * and providing appropriate user feedback.
     *
     * **Security Architecture:**
     * - Restricts HTTP methods to POST and DELETE for security
     * - Loads existing period template for authorization checking
     * - Applies policy-based authorization before deletion
     * - Ensures administrative permissions for template removal
     *
     * **Deletion Workflow:**
     * 1. **Method Validation**: Ensure POST or DELETE request method
     * 2. **Entity Loading**: Retrieve existing period template by ID
     * 3. **Authorization**: Verify user permissions for template deletion
     * 4. **Deletion**: Remove template from database with integrity checks
     * 5. **Feedback**: Provide operation status through Flash messaging
     * 6. **Redirect**: Return to index listing after operation
     *
     * **HTTP Method Security:**
     * - Only accepts POST and DELETE methods for state-changing operations
     * - Prevents accidental deletions through GET requests
     * - Integrates with CSRF protection for security validation
     * - Follows REST principles for destructive operations
     *
     * **Entity Management:**
     * - Loads period template by provided ID parameter
     * - Throws RecordNotFoundException for invalid IDs
     * - Performs authorization checking on loaded entity
     * - Handles entity deletion through WarrantPeriodsTable
     *
     * **Authorization Integration:**
     * - Applies policy-based authorization on loaded entity
     * - Ensures administrative permissions for deletion operations
     * - Integrates with organizational access control structure
     * - Prevents unauthorized template removal
     *
     * **Data Integrity:**
     * - Checks for period template dependencies before deletion
     * - Handles database constraints and referential integrity
     * - Provides appropriate error handling for constraint violations
     * - Maintains audit trail through deletion operations
     *
     * **User Feedback:**
     * - **Success**: Flash success message confirming deletion
     * - **Failure**: Flash error message with failure details
     * - Consistent messaging for administrative operations
     * - Redirect to index for updated template listing
     *
     * **Error Handling:**
     * - RecordNotFoundException for invalid period IDs
     * - Authorization failures handled by authorization component
     * - Database constraint violations result in error messages
     * - Graceful failure handling with user feedback
     *
     * **Administrative Usage:**
     * ```php
     * // Typical period deletion workflow
     * 1. Administrator views period template listing
     * 2. Clicks delete action for specific template
     * 3. System validates authorization and method
     * 4. Template removed with integrity checking
     * 5. Success feedback and return to updated listing
     * ```
     *
     * **Business Logic Considerations:**
     * - Prevents deletion of period templates with active references
     * - Maintains data consistency across warrant system
     * - Supports administrative template lifecycle management
     * - Integrates with audit trail and change tracking
     *
     * **Security Features:**
     * - HTTP method restriction for operation security
     * - Authorization checking before any operations
     * - Administrative permission requirements
     * - CSRF protection for state-changing requests
     *
     * **Integration Points:**
     * - WarrantPeriodsTable for entity loading and deletion
     * - Authorization policies for security enforcement
     * - Flash component for user feedback messaging
     * - Exception handling for error conditions
     *
     * @param string|null $id Warrant Period id.
     * @return \Cake\Http\Response|null Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete(?string $id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $warrantPeriod = $this->WarrantPeriods->get($id);
        $this->Authorization->authorize($warrantPeriod);
        if ($this->WarrantPeriods->delete($warrantPeriod)) {
            $this->Flash->success(__('The warrant period has been deleted.'));
        } else {
            $this->Flash->error(__('The warrant period could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
