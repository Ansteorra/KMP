<?php

declare(strict_types=1);

namespace Awards\Controller;

use App\Controller\DataverseGridTrait;

/**
 * Award Levels Management Controller
 *
 * CRUD operations for level management defining award precedence/rank.
 * Levels use progression_order for hierarchical ranking.
 * Uses DataverseGridTrait for table-based data display.
 *
 * @property \Awards\Model\Table\LevelsTable $Levels
 * @package Awards\Controller
 */
class LevelsController extends AppController
{
    use DataverseGridTrait;
    /**
     * Initialize Controller Components and Authorization
     *
     * Configures the LevelsController with comprehensive security framework
     * integration and component management. Establishes authorization baseline
     * for level management operations with model-level access control and
     * hierarchical precedence management integration.
     *
     * ## Security Configuration
     * - **Model Authorization**: Automatic authorization for index and add operations
     * - **Policy Integration**: Awards plugin authorization policies control access
     * - **Component Inheritance**: Inherits security framework from Awards AppController
     * - **Precedence Management**: Integration with level hierarchy and ordering system
     *
     * ## Authorization Framework
     * The controller automatically authorizes common operations:
     * - `index`: Level listing with administrative access control and precedence ordering
     * - `add`: Level creation with administrative permission validation and precedence management
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
     * @see \Awards\Policy\LevelsTablePolicy
     * @since 4.3.0
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("index", "add", "gridData");
    }

    /**
     * Level Listing Index with Precedence Ordering
     *
     * Provides comprehensive paginated listing of all award levels with
     * precedence-based ordering and administrative navigation support.
     * Serves as the primary administrative interface for level hierarchy
     * management and precedence oversight.
     *
     * ## Precedence Features
     * - **Progression Order**: Levels sorted by progression_order for hierarchical display
     * - **Pagination Support**: Configurable pagination for large level sets
     * - **Administrative Access**: Policy-controlled access to level hierarchy
     * - **Hierarchical Display**: Maintains consistent precedence ordering across interface
     *
     * ## Security
     * - **Model Authorization**: Automatic authorization via initialize() method
     * - **Policy Control**: LevelsTablePolicy governs access to level listing
     * - **Administrative Access**: Requires appropriate level management permissions
     *
     * ## Display Data
     * Sets comprehensive level data for administrative interface:
     * - `levels`: Paginated collection of level entities ordered by precedence
     * - Progression order sorting for administrative navigation and hierarchy visualization
     * - Integration with administrative interface components
     *
     * ## Precedence Management
     * The index provides hierarchical context essential for:
     * - **Award Ranking**: Understanding level precedence within award system
     * - **Administrative Planning**: Level hierarchy management and modification
     * - **System Overview**: Complete precedence structure visualization
     *
     * ## Usage Examples
     * ```php
     * // Administrative level listing by precedence
     * GET /awards/levels
     * 
     * // Paginated level access with hierarchy
     * GET /awards/levels?page=2
     * ```
     *
     * @return \Cake\Http\Response|null|void Renders administrative level listing view with precedence
     * @throws \Cake\Http\Exception\UnauthorizedException When user lacks level listing permissions
     * @see \Awards\Policy\LevelsTablePolicy::canIndex()
     * @since 4.3.0
     */
    public function index(): void
    {
        $this->set('user', $this->request->getAttribute('identity'));
    }

    /**
     * Provide grid data for Levels listing.
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
        $baseQuery = $this->Levels->find();

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Awards.Levels.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\LevelsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Levels',
            'defaultSort' => ['Levels.progression_order' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'award-levels');
        }

        // Set view variables
        $this->set([
            'levels' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\LevelsGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'levels-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'levels-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'levels-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Level Detail View with Awards Integration
     *
     * Provides comprehensive level detail display with associated awards
     * management interface and hierarchical navigation. Includes complete
     * award relationship data with domain and branch information for
     * administrative oversight and precedence context management.
     *
     * ## Data Loading
     * - **Level Entity**: Complete level record with precedence and hierarchical data
     * - **Associated Awards**: All awards assigned to this level with categorical data
     * - **Domain Information**: Award domain data for categorical organization
     * - **Branch Information**: Award branch scope for organizational context
     *
     * ## Association Strategy
     * Optimized data loading with selective field inclusion:
     * ```php
     * $level = $this->Levels->get($id, contain: [
     *     "Awards",
     *     "Awards.Domains" => function ($q) {
     *         return $q->select(["id", "name"]); // Optimized domain data
     *     },
     *     "Awards.Branches" => function ($q) {
     *         return $q->select(["id", "name"]); // Optimized branch data
     *     },
     * ]);
     * ```
     *
     * ## Precedence Context
     * - **Level Ranking**: Display progression_order for hierarchical context
     * - **Award Organization**: Awards organized by level precedence
     * - **Administrative Insight**: Complete level-award relationship overview
     * - **Hierarchy Navigation**: Integration with level precedence system
     *
     * ## Security Framework
     * - **Entity Authorization**: Individual level authorization via policy
     * - **Access Control**: LevelPolicy governs view access permissions
     * - **Data Security**: Policy-controlled access to level and award data
     *
     * ## Error Handling
     * - **Record Validation**: NotFoundException for invalid level IDs
     * - **Authorization Validation**: UnauthorizedException for unauthorized access
     * - **Data Integrity**: Comprehensive validation of level existence
     *
     * ## Usage Examples
     * ```php
     * // Administrative level view with precedence context
     * GET /awards/levels/view/123
     * 
     * // Level with awards and hierarchy context
     * $level = $controller->view($levelId); // Includes award relationships
     * ```
     *
     * @param string|null $id Award Level ID for detail view
     * @return \Cake\Http\Response|null|void Renders level detail view with awards and precedence
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When level not found
     * @throws \Cake\Http\Exception\UnauthorizedException When user lacks view permissions
     * @see \Awards\Policy\LevelPolicy::canView()
     * @since 4.3.0
     */
    public function view($id = null)
    {
        $level = $this->Levels->get(
            $id,
            contain: [
                "Awards",
                "Awards.Domains" => function ($q) {
                    return $q->select(["id", "name"]);
                },
                "Awards.Branches" => function ($q) {
                    return $q->select(["id", "name"]);
                },
            ]
        );
        if (!$level) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($level);
        $this->set(compact("level"));
    }

    /**
     * Level Creation Interface with Precedence Management
     *
     * Provides comprehensive level creation interface with form processing,
     * precedence validation workflow, and administrative feedback. Handles
     * both GET requests for form display and POST requests for level creation
     * with comprehensive error handling and precedence management.
     *
     * ## Creation Workflow
     * 1. **GET Request**: Display empty level creation form with precedence guidance
     * 2. **POST Request**: Process form data with precedence validation
     * 3. **Validation Success**: Save level and redirect to view with precedence context
     * 4. **Validation Failure**: Redisplay form with error feedback and precedence guidance
     *
     * ## Precedence Management
     * - **Progression Order**: Validation of unique progression_order values
     * - **Hierarchy Integration**: Automatic precedence assignment and validation
     * - **Administrative Guidance**: Form assistance for precedence selection
     * - **Conflict Resolution**: Validation to prevent precedence conflicts
     *
     * ## Form Processing
     * - **Entity Creation**: New empty level entity for form binding
     * - **Data Patching**: Request data patched to entity with precedence validation
     * - **Validation Framework**: LevelsTable validation rules applied including precedence
     * - **Save Operation**: Transaction-safe level persistence with precedence integrity
     *
     * ## Security Framework
     * - **Model Authorization**: Automatic authorization via initialize() method
     * - **Policy Control**: LevelsTablePolicy governs level creation access
     * - **Administrative Control**: Requires appropriate level management permissions
     *
     * ## User Feedback
     * - **Success Message**: Confirmation of successful level creation with precedence
     * - **Error Message**: Clear feedback for validation failures including precedence conflicts
     * - **Redirect Strategy**: Automatic redirect to level view on success
     *
     * ## Usage Examples
     * ```php
     * // Display level creation form
     * GET /awards/levels/add
     * 
     * // Process level creation with precedence
     * POST /awards/levels/add
     * Content-Type: application/x-www-form-urlencoded
     * name=Advanced&progression_order=3&description=Advanced+level+awards
     * ```
     *
     * @return \Cake\Http\Response|null|void Redirects to view on success, renders form on GET/failure
     * @throws \Cake\Http\Exception\UnauthorizedException When user lacks level creation permissions
     * @see \Awards\Policy\LevelsTablePolicy::canAdd()
     * @see \Awards\Model\Table\LevelsTable::validationDefault()
     * @since 4.3.0
     */
    public function add()
    {
        $level = $this->Levels->newEmptyEntity();
        if ($this->request->is("post")) {
            $level = $this->Levels->patchEntity(
                $level,
                $this->request->getData(),
            );
            if ($this->Levels->save($level)) {
                $this->Flash->success(
                    __("The Award Level has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $level->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Award Level could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("level"));
    }

    /**
     * Level Modification Interface with Precedence Management
     *
     * Provides comprehensive level modification interface with entity
     * authorization, precedence management, and data integrity validation.
     * Handles both GET requests for form display and POST/PUT/PATCH
     * requests for level updates with comprehensive precedence validation.
     *
     * ## Modification Workflow
     * 1. **Entity Loading**: Retrieve existing level record for modification
     * 2. **Authorization Check**: Entity-level authorization via policy
     * 3. **GET Request**: Display level modification form with current precedence data
     * 4. **POST/PUT/PATCH**: Process form data with precedence validation and save
     *
     * ## Precedence Management
     * - **Progression Order Validation**: Prevents precedence conflicts during updates
     * - **Hierarchy Integrity**: Maintains level hierarchy consistency
     * - **Administrative Control**: Precedence modification with validation
     * - **Conflict Resolution**: Handles precedence conflicts with clear feedback
     *
     * ## Security Framework
     * - **Entity Authorization**: Individual level authorization via LevelPolicy
     * - **Access Control**: Policy-based validation of modification permissions
     * - **Data Integrity**: Comprehensive validation of level existence and access
     * - **Precedence Security**: Protection against unauthorized precedence modification
     *
     * ## Form Processing
     * - **Entity Loading**: Existing level retrieved without associations for efficiency
     * - **Data Patching**: Request data patched to existing entity with precedence validation
     * - **Validation Framework**: LevelsTable validation rules applied to changes including precedence
     * - **Save Operation**: Transaction-safe level persistence with precedence audit trail
     *
     * ## User Feedback
     * - **Success Message**: Confirmation of successful level modification with precedence context
     * - **Error Message**: Clear feedback for validation failures including precedence conflicts
     * - **Redirect Strategy**: Return to level view on successful update
     *
     * ## Error Handling
     * - **Record Validation**: NotFoundException for invalid level IDs
     * - **Authorization Validation**: UnauthorizedException for unauthorized modification
     * - **Precedence Conflicts**: Validation errors for progression_order conflicts
     * - **Validation Errors**: Form redisplay with comprehensive error feedback
     *
     * ## Usage Examples
     * ```php
     * // Display level modification form
     * GET /awards/levels/edit/123
     * 
     * // Process level modification with precedence
     * PUT /awards/levels/edit/123
     * Content-Type: application/x-www-form-urlencoded
     * name=Updated+Advanced&progression_order=4&description=Updated+description
     * ```
     *
     * @param string|null $id Award Level ID for modification
     * @return \Cake\Http\Response|null|void Redirects to view on success, renders form on GET/failure
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When level not found
     * @throws \Cake\Http\Exception\UnauthorizedException When user lacks modification permissions
     * @see \Awards\Policy\LevelPolicy::canEdit()
     * @see \Awards\Model\Table\LevelsTable::validationDefault()
     * @since 4.3.0
     */
    public function edit($id = null)
    {
        $level = $this->Levels->get($id, contain: []);
        if (!$level) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($level);
        if ($this->request->is(["patch", "post", "put"])) {
            $level = $this->Levels->patchEntity(
                $level,
                $this->request->getData(),
            );
            if ($this->Levels->save($level)) {
                $this->Flash->success(
                    __("The Award Level Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $level->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Award Level could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("level"));
    }

    /**
     * Level Deletion with Precedence Integrity Protection
     *
     * Provides comprehensive level deletion with soft deletion pattern,
     * precedence integrity protection, and audit trail implementation.
     * Prevents deletion of levels with associated awards while maintaining
     * administrative audit trail and precedence hierarchy consistency.
     *
     * ## Deletion Workflow
     * 1. **Request Validation**: Restrict to POST/DELETE methods for security
     * 2. **Entity Loading**: Retrieve level with awards association for validation
     * 3. **Referential Integrity**: Check for associated awards before deletion
     * 4. **Authorization Check**: Entity-level authorization via policy
     * 5. **Soft Deletion**: Modify level name with audit prefix before deletion
     * 6. **Precedence Maintenance**: Preserve precedence hierarchy integrity
     *
     * ## Precedence Integrity Protection
     * - **Hierarchy Validation**: Ensures precedence system remains consistent
     * - **Referential Protection**: Validates no associated awards exist
     * - **Deletion Prevention**: Blocks deletion and provides user feedback
     * - **Business Rule Enforcement**: Maintains data integrity across award system
     * - **Administrative Guidance**: Redirects to level view with error explanation
     *
     * ## Soft Deletion Pattern
     * ```php
     * // Audit trail implementation with precedence context
     * $level->name = "Deleted: " . $level->name;
     * $this->Levels->delete($level);
     * ```
     *
     * ## Security Framework
     * - **HTTP Method Restriction**: Only POST/DELETE methods accepted
     * - **Entity Authorization**: Individual level authorization via LevelPolicy
     * - **Referential Validation**: Business rule enforcement before deletion
     * - **Precedence Security**: Protection against unauthorized precedence modification
     *
     * ## User Feedback
     * - **Referential Error**: Clear explanation when deletion blocked by awards
     * - **Success Message**: Confirmation of successful level deletion with precedence context
     * - **Error Message**: Feedback for unexpected deletion failures
     * - **Navigation Strategy**: Appropriate redirects based on operation outcome
     *
     * ## Error Handling
     * - **Record Validation**: NotFoundException for invalid level IDs
     * - **Authorization Validation**: UnauthorizedException for unauthorized deletion
     * - **Business Rule Validation**: Referential integrity and precedence protection
     * - **Operation Failures**: Comprehensive error feedback and recovery
     *
     * ## Usage Examples
     * ```php
     * // Administrative level deletion
     * DELETE /awards/levels/delete/123
     * 
     * // Referential integrity protection
     * // Level with awards cannot be deleted - maintains hierarchy
     * ```
     *
     * @param string|null $id Level ID for deletion
     * @return \Cake\Http\Response|null Redirects to index on success, view on failure
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When level not found
     * @throws \Cake\Http\Exception\UnauthorizedException When user lacks deletion permissions
     * @throws \Cake\Http\Exception\MethodNotAllowedException When invalid HTTP method used
     * @see \Awards\Policy\LevelPolicy::canDelete()
     * @since 4.3.0
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $level = $this->Levels->get(
            $id,
            contain: ["Awards"],
        );
        if (!$level) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        if ($level->awards) {
            $this->Flash->error(
                __("The Award Level could not be deleted because it has associated Awards."),
            );
            return $this->redirect(["action" => "view", $level->id]);
        }
        $this->Authorization->authorize($level);
        $level->name = "Deleted: " . $level->name;
        if ($this->Levels->delete($level)) {
            $this->Flash->success(
                __("The Award Level has been deleted."),
            );
        } else {
            $this->Flash->error(
                __(
                    "The Award Level could not be deleted. Please, try again.",
                ),
            );

            return $this->redirect(["action" => "view", $level->id]);
        }

        return $this->redirect(["action" => "index"]);
    }
}
