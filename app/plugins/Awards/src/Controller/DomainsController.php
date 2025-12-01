<?php

declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\DataverseGridTrait;

/**
 * Award Domains Management Controller
 *
 * Provides comprehensive domain management functionality for the Awards plugin,
 * handling the organizational categorization of awards within the hierarchical 
 * award system. Domains serve as the top-level organizational structure for
 * awards, enabling logical grouping and administrative management.
 *
 * ## Key Features
 * - **Domain Organization**: Complete CRUD operations for award domain management
 * - **Hierarchical Integration**: Integration with awards, levels, and branch systems
 * - **Administrative Interface**: Full administrative control over domain structure
 * - **Referential Integrity**: Protection against deletion of domains with associated awards
 * - **Security Framework**: Entity-level authorization with policy-based access control
 * - **Audit Trail**: Soft deletion pattern with administrative audit trail
 *
 * ## Domain Structure
 * Domains organize awards into logical categories and provide the foundation
 * for the Awards plugin hierarchical organization:
 * ```
 * Domain (e.g., "Leadership", "Technical", "Community")
 *   └── Awards (multiple awards within domain)
 *       ├── Level (award difficulty/rank)
 *       └── Branch (organizational scope)
 * ```
 *
 * ## Security Architecture
 * - **Model Authorization**: Automatic authorization for index and add operations
 * - **Entity Authorization**: Individual entity authorization for view, edit, delete
 * - **Policy Integration**: Awards plugin authorization policies control access
 * - **Administrative Control**: Permission-based domain management oversight
 *
 * ## Usage Examples
 * ```php
 * // Create new domain
 * $domainsController = new DomainsController();
 * $domain = $domainsController->add(); // Creates domain with validation
 * 
 * // View domain with awards
 * $domain = $domainsController->view($domainId); // Includes associated awards
 * 
 * // Administrative domain management
 * $domains = $domainsController->index(); // Paginated domain listing
 * ```
 *
 * ## Integration Points
 * - **AwardsTable**: Primary relationship for award organization
 * - **Authorization Framework**: Policy-based access control integration
 * - **Administrative Interface**: Full administrative management capabilities
 * - **Audit System**: Soft deletion with audit trail tracking
 * - **Navigation System**: Integration with Awards plugin navigation
 *
 * @property \Awards\Model\Table\DomainsTable $Domains Domain data management
 * @see \Awards\Model\Table\DomainsTable
 * @see \Awards\Policy\DomainPolicy
 * @see \Awards\Policy\DomainsTablePolicy
 * @package Awards\Controller
 * @since 4.3.0
 */
class DomainsController extends AppController
{
    use DataverseGridTrait;
    /**
     * Initialize Controller Components and Authorization
     *
     * Configures the DomainsController with comprehensive security framework
     * integration and component management. Establishes authorization baseline
     * for domain management operations with model-level access control.
     *
     * ## Security Configuration
     * - **Model Authorization**: Automatic authorization for index and add operations
     * - **Policy Integration**: Awards plugin authorization policies control access
     * - **Component Inheritance**: Inherits security framework from Awards AppController
     *
     * ## Authorization Framework
     * The controller automatically authorizes common operations:
     * - `index`: Domain listing with administrative access control
     * - `add`: Domain creation with administrative permission validation
     * - Individual entity operations (view, edit, delete) require explicit authorization
     *
     * ## Component Configuration
     * Inherits from Awards AppController providing:
     * - Authentication component for user validation
     * - Authorization component with policy-based access control
     * - Flash component for standardized user feedback
     *
     * @return void
     * @throws \Cake\Http\Exception\UnauthorizedException When user lacks required permissions
     * @see \Awards\Controller\AppController::initialize()
     * @see \Awards\Policy\DomainsTablePolicy
     * @since 4.3.0
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("index", "add", "gridData");
    }

    /**
     * Domain Listing Index
     *
     * Provides comprehensive paginated listing of all award domains with
     * administrative navigation support and alphabetical ordering. Serves
     * as the primary administrative interface for domain management and
     * organizational oversight.
     *
     * ## Query Features
     * - **Alphabetical Ordering**: Domains sorted by name for easy navigation
     * - **Pagination Support**: Configurable pagination for large domain sets
     * - **Administrative Access**: Policy-controlled access to domain listing
     *
     * ## Security
     * - **Model Authorization**: Automatic authorization via initialize() method
     * - **Policy Control**: DomainsTablePolicy governs access to domain listing
     * - **Administrative Access**: Requires appropriate domain management permissions
     *
     * ## Display Data
     * Sets comprehensive domain data for administrative interface:
     * - `domains`: Paginated collection of domain entities
     * - Alphabetical ordering for administrative navigation
     * - Integration with administrative interface components
     *
     * ## Usage Examples
     * ```php
     * // Administrative domain listing
     * GET /awards/domains
     * 
     * // Paginated domain access
     * GET /awards/domains?page=2
     * ```
     *
     * @return \Cake\Http\Response|null|void Renders administrative domain listing view
     * @throws \Cake\Http\Exception\UnauthorizedException When user lacks domain listing permissions
     * @see \Awards\Policy\DomainsTablePolicy::canIndex()
     * @since 4.3.0
     */
    public function index(): void
    {
        $this->set('user', $this->request->getAttribute('identity'));
    }

    /**
     * Provide grid data for Domains listing.
     *
     * This method serves data for the Dataverse grid component via Turbo Frame requests.
     * Handles filtering, sorting, pagination, and CSV export.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(\App\Services\CsvExportService $csvExportService)
    {
        // Build base query
        $baseQuery = $this->Domains->find();

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.Domains.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\DomainsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Domains',
            'defaultSort' => ['Domains.name' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'award-domains');
        }

        // Set view variables
        $this->set([
            'domains' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\DomainsGridColumns::getSearchableColumns(),
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

        // Use main app's element templates (not plugin templates)
        $this->viewBuilder()->setPlugin(null);

        if ($turboFrame === 'domains-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'domains-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'domains-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Domain Detail View
     *
     * Provides comprehensive domain detail display with associated awards
     * management interface and hierarchical navigation. Includes complete
     * award relationship data with level and branch information for
     * administrative oversight and organizational management.
     *
     * ## Data Loading
     * - **Domain Entity**: Complete domain record with comprehensive associations
     * - **Associated Awards**: All awards within the domain with hierarchical data
     * - **Level Information**: Award level data for hierarchical organization
     * - **Branch Information**: Award branch scope for organizational context
     *
     * ## Association Strategy
     * Optimized data loading with selective field inclusion:
     * ```php
     * $domain = $this->Domains->get($id, contain: [
     *     "Awards",
     *     "Awards.Levels" => function ($q) {
     *         return $q->select(["id", "name"]); // Optimized level data
     *     },
     *     "Awards.Branches" => function ($q) {
     *         return $q->select(["id", "name"]); // Optimized branch data
     *     },
     * ]);
     * ```
     *
     * ## Security Framework
     * - **Entity Authorization**: Individual domain authorization via policy
     * - **Access Control**: DomainPolicy governs view access permissions
     * - **Data Security**: Policy-controlled access to domain and award data
     *
     * ## Error Handling
     * - **Record Validation**: NotFoundException for invalid domain IDs
     * - **Authorization Validation**: UnauthorizedException for unauthorized access
     * - **Data Integrity**: Comprehensive validation of domain existence
     *
     * ## Usage Examples
     * ```php
     * // Administrative domain view
     * GET /awards/domains/view/123
     * 
     * // Domain with awards context
     * $domain = $controller->view($domainId); // Includes award relationships
     * ```
     *
     * @param string|null $id Award Domain ID for detail view
     * @return \Cake\Http\Response|null|void Renders domain detail view with awards
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When domain not found
     * @throws \Cake\Http\Exception\UnauthorizedException When user lacks view permissions
     * @see \Awards\Policy\DomainPolicy::canView()
     * @since 4.3.0
     */
    public function view($id = null)
    {
        $domain = $this->Domains->get(
            $id,
            contain: [
                "Awards",
                "Awards.Levels" => function ($q) {
                    return $q->select(["id", "name"]);
                },
                "Awards.Branches" => function ($q) {
                    return $q->select(["id", "name"]);
                },
            ]
        );
        if (!$domain) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($domain);
        $this->set(compact("domain"));
    }

    /**
     * Domain Creation Interface
     *
     * Provides comprehensive domain creation interface with form processing,
     * validation workflow, and administrative feedback. Handles both GET
     * requests for form display and POST requests for domain creation with
     * comprehensive error handling and user feedback.
     *
     * ## Creation Workflow
     * 1. **GET Request**: Display empty domain creation form
     * 2. **POST Request**: Process form data with validation
     * 3. **Validation Success**: Save domain and redirect to view
     * 4. **Validation Failure**: Redisplay form with error feedback
     *
     * ## Form Processing
     * - **Entity Creation**: New empty domain entity for form binding
     * - **Data Patching**: Request data patched to entity with validation
     * - **Validation Framework**: DomainsTable validation rules applied
     * - **Save Operation**: Transaction-safe domain persistence
     *
     * ## Security Framework
     * - **Model Authorization**: Automatic authorization via initialize() method
     * - **Policy Control**: DomainsTablePolicy governs domain creation access
     * - **Administrative Control**: Requires appropriate domain management permissions
     *
     * ## User Feedback
     * - **Success Message**: Confirmation of successful domain creation
     * - **Error Message**: Clear feedback for validation failures
     * - **Redirect Strategy**: Automatic redirect to domain view on success
     *
     * ## Usage Examples
     * ```php
     * // Display domain creation form
     * GET /awards/domains/add
     * 
     * // Process domain creation
     * POST /awards/domains/add
     * Content-Type: application/x-www-form-urlencoded
     * name=Technical&description=Technical+achievement+awards
     * ```
     *
     * @return \Cake\Http\Response|null|void Redirects to view on success, renders form on GET/failure
     * @throws \Cake\Http\Exception\UnauthorizedException When user lacks domain creation permissions
     * @see \Awards\Policy\DomainsTablePolicy::canAdd()
     * @see \Awards\Model\Table\DomainsTable::validationDefault()
     * @since 4.3.0
     */
    public function add()
    {
        $domain = $this->Domains->newEmptyEntity();
        if ($this->request->is("post")) {
            $domain = $this->Domains->patchEntity(
                $domain,
                $this->request->getData(),
            );
            if ($this->Domains->save($domain)) {
                $this->Flash->success(
                    __("The Award Domain has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $domain->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Award Domain could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("domain"));
    }

    /**
     * Domain Modification Interface
     *
     * Provides comprehensive domain modification interface with entity
     * authorization, form processing, and data integrity validation.
     * Handles both GET requests for form display and POST/PUT/PATCH
     * requests for domain updates with comprehensive security validation.
     *
     * ## Modification Workflow
     * 1. **Entity Loading**: Retrieve existing domain record for modification
     * 2. **Authorization Check**: Entity-level authorization via policy
     * 3. **GET Request**: Display domain modification form with current data
     * 4. **POST/PUT/PATCH**: Process form data with validation and save
     *
     * ## Security Framework
     * - **Entity Authorization**: Individual domain authorization via DomainPolicy
     * - **Access Control**: Policy-based validation of modification permissions
     * - **Data Integrity**: Comprehensive validation of domain existence and access
     *
     * ## Form Processing
     * - **Entity Loading**: Existing domain retrieved without associations for efficiency
     * - **Data Patching**: Request data patched to existing entity with validation
     * - **Validation Framework**: DomainsTable validation rules applied to changes
     * - **Save Operation**: Transaction-safe domain persistence with audit trail
     *
     * ## User Feedback
     * - **Success Message**: Confirmation of successful domain modification
     * - **Error Message**: Clear feedback for validation failures
     * - **Redirect Strategy**: Return to domain view on successful update
     *
     * ## Error Handling
     * - **Record Validation**: NotFoundException for invalid domain IDs
     * - **Authorization Validation**: UnauthorizedException for unauthorized modification
     * - **Validation Errors**: Form redisplay with comprehensive error feedback
     *
     * ## Usage Examples
     * ```php
     * // Display domain modification form
     * GET /awards/domains/edit/123
     * 
     * // Process domain modification
     * PUT /awards/domains/edit/123
     * Content-Type: application/x-www-form-urlencoded
     * name=Updated+Technical&description=Updated+description
     * ```
     *
     * @param string|null $id Award Domain ID for modification
     * @return \Cake\Http\Response|null|void Redirects to view on success, renders form on GET/failure
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When domain not found
     * @throws \Cake\Http\Exception\UnauthorizedException When user lacks modification permissions
     * @see \Awards\Policy\DomainPolicy::canEdit()
     * @see \Awards\Model\Table\DomainsTable::validationDefault()
     * @since 4.3.0
     */
    public function edit($id = null)
    {
        $domain = $this->Domains->get($id, contain: []);
        if (!$domain) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($domain);
        if ($this->request->is(["patch", "post", "put"])) {
            $domain = $this->Domains->patchEntity(
                $domain,
                $this->request->getData(),
            );
            if ($this->Domains->save($domain)) {
                $this->Flash->success(
                    __("The Award Domain Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $domain->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Award Domain could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("domain"));
    }

    /**
     * Domain Deletion with Referential Integrity Protection
     *
     * Provides comprehensive domain deletion with soft deletion pattern,
     * referential integrity protection, and audit trail implementation.
     * Prevents deletion of domains with associated awards while maintaining
     * administrative audit trail for successful deletions.
     *
     * ## Deletion Workflow
     * 1. **Request Validation**: Restrict to POST/DELETE methods for security
     * 2. **Entity Loading**: Retrieve domain with awards association for validation
     * 3. **Referential Integrity**: Check for associated awards before deletion
     * 4. **Authorization Check**: Entity-level authorization via policy
     * 5. **Soft Deletion**: Modify domain name with audit prefix before deletion
     *
     * ## Referential Integrity Protection
     * - **Association Check**: Validates no associated awards exist
     * - **Deletion Prevention**: Blocks deletion and provides user feedback
     * - **Business Rule Enforcement**: Maintains data integrity across award system
     * - **User Guidance**: Redirects to domain view with error explanation
     *
     * ## Soft Deletion Pattern
     * ```php
     * // Audit trail implementation
     * $domain->name = "Deleted: " . $domain->name;
     * $this->Domains->delete($domain);
     * ```
     *
     * ## Security Framework
     * - **HTTP Method Restriction**: Only POST/DELETE methods accepted
     * - **Entity Authorization**: Individual domain authorization via DomainPolicy
     * - **Referential Validation**: Business rule enforcement before deletion
     *
     * ## User Feedback
     * - **Referential Error**: Clear explanation when deletion blocked
     * - **Success Message**: Confirmation of successful domain deletion
     * - **Error Message**: Feedback for unexpected deletion failures
     * - **Navigation Strategy**: Appropriate redirects based on operation outcome
     *
     * ## Error Handling
     * - **Record Validation**: NotFoundException for invalid domain IDs
     * - **Authorization Validation**: UnauthorizedException for unauthorized deletion
     * - **Business Rule Validation**: Referential integrity protection
     * - **Operation Failures**: Comprehensive error feedback and recovery
     *
     * ## Usage Examples
     * ```php
     * // Administrative domain deletion
     * DELETE /awards/domains/delete/123
     * 
     * // Referential integrity protection
     * // Domain with awards cannot be deleted
     * ```
     *
     * @param string|null $id Domain ID for deletion
     * @return \Cake\Http\Response|null Redirects to index on success, view on failure
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When domain not found
     * @throws \Cake\Http\Exception\UnauthorizedException When user lacks deletion permissions
     * @throws \Cake\Http\Exception\MethodNotAllowedException When invalid HTTP method used
     * @see \Awards\Policy\DomainPolicy::canDelete()
     * @since 4.3.0
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $domain = $this->Domains->get(
            $id,
            contain: ["Awards"],
        );
        if (!$domain) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        if ($domain->awards) {
            $this->Flash->error(
                __("The Award Domain could not be deleted because it has associated Awards."),
            );
            return $this->redirect(["action" => "view", $domain->id]);
        }
        $this->Authorization->authorize($domain);
        $domain->name = "Deleted: " . $domain->name;
        if ($this->Domains->delete($domain)) {
            $this->Flash->success(
                __("The Award Domain has been deleted."),
            );
        } else {
            $this->Flash->error(
                __(
                    "The Award Domain could not be deleted. Please, try again.",
                ),
            );

            return $this->redirect(["action" => "view", $domain->id]);
        }

        return $this->redirect(["action" => "index"]);
    }
}
