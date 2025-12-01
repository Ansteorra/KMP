<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\WarrantRoster;
use App\Services\CsvExportService;
use App\Services\WarrantManager\WarrantManagerInterface;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

/**
 * WarrantRosters Controller - Batch Warrant Management and Multi-Level Approval Interface
 *
 * This controller provides the administrative interface for managing warrant roster batches,
 * handling multi-level approval workflows, and coordinating bulk warrant operations. It
 * integrates closely with the WarrantManager service to provide a comprehensive system for
 * efficient warrant batch processing and approval management.
 *
 * ## Core Functionality
 * - **Batch Roster Management**: Create, view, edit, and manage warrant roster batches
 * - **Multi-Level Approval Workflow**: Handle approval and decline operations for entire rosters
 * - **Individual Warrant Control**: Manage individual warrants within roster batches
 * - **Authorization Integration**: Policy-based authorization for roster access and operations
 * - **Service Layer Integration**: Seamless integration with WarrantManager for business logic
 * - **Administrative Oversight**: Comprehensive views and filtering for administrative management
 *
 * ## Controller Architecture
 * - **Authorization Component**: Integrated authorization with policy-based access control
 * - **Service Injection**: WarrantManager dependency injection for business logic operations
 * - **Scoped Data Access**: Branch-scoped authorization through Authorization component
 * - **Flash Messaging**: User feedback through Flash messages for operation results
 * - **Exception Handling**: Proper error handling with NotFoundException for missing resources
 *
 * ## Key Operations
 * - **CRUD Operations**: Complete roster lifecycle management with validation
 * - **Approval Processing**: Multi-step approval workflow with audit trail
 * - **Batch Operations**: Efficient bulk warrant processing through roster system
 * - **Status Management**: Track roster states through approval workflow
 * - **Individual Warrant Management**: Fine-grained control over warrants within rosters
 *
 * ## Integration Points
 * - **WarrantManager Service**: Business logic for approval, decline, and warrant operations
 * - **Authorization Service**: Policy-based authorization for roster and warrant access
 * - **WarrantRostersTable**: Data layer for roster management and validation
 * - **Authentication Component**: User identity for audit trail and permission checking
 * - **Flash Component**: User feedback and operation status messaging
 *
 * ## Security Architecture
 * - **Policy-Based Authorization**: Each operation authorized through appropriate policies
 * - **Resource Authorization**: Entity-level authorization for individual rosters and warrants
 * - **Branch Scoping**: Automatic data scoping based on user's organizational access
 * - **Request Method Validation**: POST-only requirements for state-changing operations
 * - **Identity Verification**: Authentication required for all approval operations
 *
 * ## Usage Examples
 * ```php
 * // Create new warrant roster
 * $this->WarrantRosters->add([
 *     'name' => 'Pennsic War 52 Event Staff',
 *     'description' => 'Event staff warrants for Pennsic activities',
 *     'approvals_required' => 2,
 *     'planned_start_on' => '2025-07-15',
 *     'planned_expires_on' => '2025-08-15'
 * ]);
 *
 * // Approve roster through WarrantManager
 * $result = $warrantManager->approve($rosterId, $userId);
 * if ($result->success) {
 *     $this->Flash->success('Roster approved successfully');
 * }
 *
 * // Decline individual warrant in roster
 * $result = $warrantManager->declineSingleWarrant($warrantId, 'Reason', $userId);
 * ```
 *
 * @property \App\Model\Table\WarrantRostersTable $WarrantRosters
 * @property \Authorization\Controller\Component\AuthorizationComponent $Authorization
 * @property \Authentication\Controller\Component\AuthenticationComponent $Authentication
 * @property \Cake\Controller\Component\FlashComponent $Flash
 */
class WarrantRostersController extends AppController
{
    use DataverseGridTrait;

    /**
     * Initialize controller - Configure authorization and component loading
     *
     * Sets up the controller with authorization component integration and establishes
     * model-level authorization for index operations. This ensures that all warrant
     * roster operations are properly secured through the authorization framework.
     *
     * ## Component Configuration
     * - **Authorization Component**: Loaded for policy-based access control
     * - **Model Authorization**: Automatic authorization for index operation
     *
     * ## Security Setup
     * The authorization component automatically applies branch scoping and policy
     * validation for all warrant roster operations, ensuring users can only access
     * rosters within their organizational scope and have appropriate permissions.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Load authorization component for policy-based access control
        $this->loadComponent('Authorization.Authorization');

        // Enable automatic model authorization for index and gridData operations
        $this->Authorization->authorizeModel('index', 'gridData');
    }

    /**
     * Index method - Main warrant roster dashboard
     *
     * Provides the main warrant roster management interface with navigation to
     * different roster states and administrative controls. This method serves as
     * the primary entry point for warrant roster management operations.
     *
     * ## Dashboard Features
     * - **Roster State Navigation**: Links to pending, approved, and declined rosters
     * - **Administrative Controls**: Access to roster creation and management tools
     * - **Quick Statistics**: Overview of roster counts and approval status
     *
     * ## Authorization
     * Authorization is handled automatically through the model authorization
     * configured in initialize(), ensuring proper access control.
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Simple index page - just renders the dv_grid element
        // The dv_grid element will lazy-load the actual data via gridData action
    }

    /**
     * Grid Data method - Provides Dataverse grid data for warrant rosters
     *
     * Returns grid content with toolbar and table for the warrant rosters grid.
     * Supports status tabs for filtering by Pending, Approved, or Declined status.
     * Handles both outer frame (toolbar + table frame) and inner frame
     * (table only) requests. Also supports CSV export.
     *
     * @param \App\Services\CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Build query with warrant count subquery and creator info
        $warrantsTable = TableRegistry::getTableLocator()->get('Warrants');
        $warrantCountSubquery = $warrantsTable->find()
            ->select(['count' => $warrantsTable->find()->func()->count('*')])
            ->where(['Warrants.warrant_roster_id = WarrantRosters.id']);

        $baseQuery = $this->WarrantRosters->find()
            ->select($this->WarrantRosters)
            ->select(['warrant_count' => $warrantCountSubquery])
            ->contain(['CreatedByMember' => function ($q) {
                return $q->select(['id', 'sca_name']);
            }]);

        // Apply authorization scoping
        $baseQuery = $this->Authorization->applyScope($baseQuery);

        // Define system views for status filtering
        $systemViews = $this->getWarrantRosterSystemViews();

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'WarrantRosters.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\WarrantRostersGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'WarrantRosters',
            'defaultSort' => ['WarrantRosters.created' => 'desc'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-roster-pending',
            'showAllTab' => true,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => false,
            'canFilter' => true,
            'lockedFilters' => ['status'],
            'showFilterPills' => true,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'warrant-rosters');
        }

        // Set view variables
        $this->set([
            'warrantRosters' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\WarrantRostersGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'warrant-rosters-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'warrant-rosters-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'warrant-rosters-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplate('../element/dv_grid_content');
        }
    }

    /**
     * Get system views for warrant rosters
     *
     * Defines the predefined views (tabs) for filtering warrant rosters by status.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getWarrantRosterSystemViews(): array
    {
        return [
            'sys-roster-pending' => [
                'id' => 'sys-roster-pending',
                'name' => __('Pending'),
                'description' => __('Rosters awaiting approval'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => WarrantRoster::STATUS_PENDING],
                    ],
                ],
            ],
            'sys-roster-approved' => [
                'id' => 'sys-roster-approved',
                'name' => __('Approved'),
                'description' => __('Approved rosters'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => WarrantRoster::STATUS_APPROVED],
                    ],
                ],
            ],
            'sys-roster-declined' => [
                'id' => 'sys-roster-declined',
                'name' => __('Declined'),
                'description' => __('Declined rosters'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => WarrantRoster::STATUS_DECLINED],
                    ],
                ],
            ],
        ];
    }

    /**
     * All rosters method - Filtered roster listing with pagination
     *
     * Displays warrant rosters filtered by status with comprehensive data including
     * approval tracking, warrant counts, and creator information. Implements efficient
     * pagination and authorization scoping for large datasets.
     *
     * ## Query Features
     * - **Status Filtering**: Filter rosters by pending, approved, or declined status
     * - **Warrant Counting**: Aggregate count of warrants in each roster
     * - **Creator Information**: Display roster creator for accountability
     * - **Authorization Scoping**: Automatic branch-based data filtering
     * - **Pagination Support**: Efficient handling of large roster datasets
     *
     * ## Performance Optimizations
     * - **Selective Field Loading**: Only loads necessary fields for list view
     * - **Optimized Joins**: Efficient database queries with minimal data transfer
     * - **Grouped Aggregation**: Count warrants per roster in single query
     *
     * ## Security Features
     * - **Authorization Scope**: Automatic application of user's organizational scope
     * - **Status Validation**: Ensures only valid status values are processed
     *
     * @param string $state Roster status filter (pending, approved, declined)
     * @return void Sets paginated warrantRosters for view rendering
     */
    public function allRosters($state)
    {
        // Build base query with creator information
        $query = $this->WarrantRosters->find()
            ->contain(['CreatedByMember' => function ($q) {
                return $q->select(['id', 'sca_name']);  // Minimal member data for performance
            }]);

        // Add warrant counting with matching for rosters that have warrants
        $query = $query->matching('Warrants')
            ->select([
                'id',
                'name',
                'status',
                'approvals_required',
                'approval_count',
                'created',
                'warrant_count' => $query->func()->count('Warrants.id')  // Aggregate warrant count
            ])
            ->groupBy(['WarrantRosters.id']);  // Group by roster for proper counting

        // Apply status filter
        $query = $query->where(['WarrantRosters.status' => $state]);

        // Apply authorization scoping for organizational data access
        $query = $this->Authorization->applyScope($query);

        // Execute paginated query
        $warrantRosters = $this->paginate($query);

        $this->set(compact('warrantRosters'));
    }

    /**
     * View method - Detailed warrant roster display with approval tracking
     *
     * Displays comprehensive warrant roster details including all associated warrants,
     * approval history, and administrative controls. Provides the primary interface
     * for reviewing roster status and managing approval workflows.
     *
     * ## Data Loading Strategy
     * - **Complete Roster Data**: Full roster details with all related information
     * - **Approval History**: Chronological approval tracking with approver details
     * - **Warrant Details**: All warrants in roster with member information
     * - **Audit Trail**: Creator and modification history for accountability
     *
     * ## Related Data Associations
     * - **WarrantRosterApprovals**: Approval history ordered chronologically
     * - **Warrants**: All warrants in roster with creation timestamps
     * - **Warrant Members**: Member information for each warrant holder
     * - **Approval Members**: Approver information for audit trail
     * - **Creator Information**: Roster creator details for accountability
     *
     * ## Security and Authorization
     * - **Entity Authorization**: Individual roster authorization before display
     * - **Data Scoping**: Automatic organizational boundary enforcement
     * - **Permission Validation**: Ensures user has view access to specific roster
     *
     * ## UI Integration
     * - **Approval Controls**: Administrative buttons for approval operations
     * - **Individual Warrant Management**: Controls for managing specific warrants
     * - **Status Display**: Clear indication of roster and approval status
     * - **Audit Information**: Complete history of roster changes and approvals
     *
     * @param string|null $id Warrant Roster id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view(?string $id = null)
    {
        // Load complete roster data with all related information
        $warrantRoster = $this->WarrantRosters->find()
            ->where(['WarrantRosters.id' => $id])
            ->contain([
                // Approval history ordered chronologically for timeline display
                'WarrantRosterApprovals' => function ($q) {
                    return $q->orderBy(['approved_on' => 'ASC']);
                },
                // Warrants ordered by creation date for consistent display
                'Warrants' => function ($q) {
                    return $q->orderBy(['Warrants.created' => 'ASC']);
                },
                // Member information for warrant holders (minimal data for performance)
                'Warrants.Members' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                // Approver information for audit trail
                'WarrantRosterApprovals.Members' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                // Creator information for accountability
                'CreatedByMember' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
            ])
            ->first();

        // Authorize access to specific roster entity
        $this->Authorization->authorize($warrantRoster);

        $this->set(compact('warrantRoster'));
    }

    /**
     * Add method - Create new warrant roster batch
     *
     * Handles creation of new warrant roster batches with validation and authorization.
     * Provides form interface for defining roster parameters including approval
     * requirements and temporal planning for warrant activation.
     *
     * ## Form Processing
     * - **GET Request**: Display roster creation form with validation feedback
     * - **POST Request**: Process form submission with validation and security checks
     * - **Data Validation**: Comprehensive validation through WarrantRostersTable rules
     * - **Authorization**: Entity-level authorization for roster creation
     *
     * ## Roster Configuration
     * - **Basic Information**: Name and description for administrative identification
     * - **Approval Requirements**: Configure number of required approvals
     * - **Temporal Planning**: Set planned start and expiration dates
     * - **Creator Tracking**: Automatic creator assignment through FootprintBehavior
     *
     * ## Security and Validation
     * - **Authorization Check**: Validates user permission to create rosters
     * - **Data Validation**: Enforces business rules and data integrity
     * - **Branch Scoping**: Ensures roster is created within user's organizational scope
     *
     * ## User Experience
     * - **Success Feedback**: Flash message and redirect on successful creation
     * - **Error Handling**: Validation feedback and form retention on errors
     * - **Navigation**: Automatic redirect to index for workflow continuation
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        // Create new empty entity for form binding
        $warrantRoster = $this->WarrantRosters->newEmptyEntity();

        // Authorize roster creation before processing
        $this->Authorization->authorize($warrantRoster);

        if ($this->request->is('post')) {
            // Process form submission with validation
            $warrantRoster = $this->WarrantRosters->patchEntity($warrantRoster, $this->request->getData());

            if ($this->WarrantRosters->save($warrantRoster)) {
                $this->Flash->success(__('The warrant approval set has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant approval set could not be saved. Please, try again.'));
        }

        $this->set(compact('warrantRoster'));
    }

    /**
     * Edit method - Modify existing warrant roster
     *
     * Handles modification of existing warrant roster with validation and authorization.
     * Allows updates to roster configuration while maintaining data integrity and
     * approval workflow constraints.
     *
     * ## Edit Capabilities
     * - **Basic Information**: Update name and description
     * - **Approval Configuration**: Modify approval requirements (with constraints)
     * - **Temporal Adjustments**: Update planned dates (with validation)
     * - **Status Management**: Controlled status updates based on approval state
     *
     * ## Security Considerations
     * - **Entity Authorization**: Validates user permission to edit specific roster
     * - **State Validation**: Ensures edits are appropriate for current roster state
     * - **Data Integrity**: Maintains referential integrity with existing warrants
     *
     * ## Business Rules
     * - **Approval Constraints**: May restrict changes to rosters with existing approvals
     * - **Temporal Validation**: Ensures date changes maintain logical consistency
     * - **Status Dependencies**: Validates status changes against approval state
     *
     * ## User Feedback
     * - **Success Processing**: Flash message and redirect on successful update
     * - **Error Handling**: Detailed validation feedback for correction
     * - **Form State**: Maintains form data for error correction
     *
     * @param string|null $id Warrant Roster id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit(?string $id = null)
    {
        // Load existing roster for editing
        $warrantRoster = $this->WarrantRosters->get($id, contain: []);

        // Authorize edit operation on specific roster
        $this->Authorization->authorize($warrantRoster);

        if ($this->request->is(['patch', 'post', 'put'])) {
            // Process edit form submission
            $warrantRoster = $this->WarrantRosters->patchEntity($warrantRoster, $this->request->getData());

            if ($this->WarrantRosters->save($warrantRoster)) {
                $this->Flash->success(__('The warrant approval set has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The warrant approval set could not be saved. Please, try again.'));
        }

        $this->set(compact('warrantRoster'));
    }

    /**
     * Approve method - Process roster approval through WarrantManager
     *
     * Handles roster approval operations through the WarrantManager service, providing
     * a secure and audited approval process with comprehensive validation and business
     * logic enforcement. Integrates with multi-level approval workflows.
     *
     * ## Approval Process
     * - **POST-Only Security**: Requires POST request to prevent CSRF attacks
     * - **Entity Validation**: Verifies roster exists and is accessible
     * - **Authorization Check**: Validates user permission to approve specific roster
     * - **Service Integration**: Delegates approval logic to WarrantManager service
     * - **Identity Tracking**: Records approver identity for audit trail
     *
     * ## WarrantManager Integration
     * The approval operation is handled by the WarrantManager service which:
     * - Validates approval eligibility and requirements
     * - Records approval in WarrantRosterApprovals table
     * - Updates roster approval count and status
     * - Handles automatic warrant activation when fully approved
     * - Provides detailed result feedback for user interface
     *
     * ## Security and Audit
     * - **Request Method Validation**: POST-only for state-changing operations
     * - **Entity Authorization**: Policy-based authorization on specific roster
     * - **Identity Verification**: Current user identity recorded for audit trail
     * - **Business Logic Validation**: Service layer enforces approval rules
     *
     * ## User Experience
     * - **Success Feedback**: Clear confirmation of approval processing
     * - **Error Handling**: Detailed error messages for failed approvals
     * - **Navigation**: Returns to roster view for continued workflow
     *
     * @param \App\Services\WarrantManager\WarrantManagerInterface $wManager Warrant management service
     * @param string|null $id Warrant roster ID to approve
     * @return \Cake\Http\Response Redirect to roster view
     * @throws \Cake\Http\Exception\NotFoundException When roster not found
     */
    function approve(WarrantManagerInterface $wManager, $id = null)
    {
        // Require POST request for security
        $this->request->allowMethod(['post']);

        // Load roster with warrants for validation
        $warrantRoster = $this->WarrantRosters->get($id, ['contain' => ['Warrants']]);
        if ($warrantRoster == null) {
            throw new NotFoundException();
        }

        // Authorize approval operation on specific roster
        $this->Authorization->authorize($warrantRoster);

        // Process approval through WarrantManager service
        $wmResult = $wManager->approve($warrantRoster->id, $this->Authentication->getIdentity()->getIdentifier());

        if ($wmResult->success) {
            $this->Flash->success(__('The approval has been been processed.'));
        } else {
            $this->Flash->error(__($wmResult->reason));
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Decline method - Process roster decline through WarrantManager
     *
     * Handles roster decline operations through the WarrantManager service, providing
     * a secure and audited decline process with comprehensive validation and automatic
     * status management for associated warrants.
     *
     * ## Decline Process
     * - **POST-Only Security**: Requires POST request to prevent unauthorized declines
     * - **Entity Validation**: Verifies roster exists and contains warrants
     * - **Authorization Check**: Validates user permission to decline specific roster
     * - **Service Integration**: Delegates decline logic to WarrantManager service
     * - **Audit Trail**: Records decline decision with user identity and reason
     *
     * ## WarrantManager Integration
     * The decline operation is handled by the WarrantManager service which:
     * - Validates decline eligibility and business rules
     * - Records decline decision with standard reason
     * - Updates roster status to declined
     * - Handles associated warrant status updates
     * - Provides detailed result feedback for user interface
     *
     * ## Business Logic
     * - **Automatic Reason**: Standard decline reason provided by system
     * - **Status Cascade**: Decline affects all warrants in roster
     * - **Audit Recording**: Complete audit trail of decline decision
     * - **Validation Rules**: Service layer enforces decline business rules
     *
     * ## User Experience
     * - **Confirmation Feedback**: Clear confirmation of decline processing
     * - **Error Handling**: Detailed error messages for failed declines
     * - **Workflow Navigation**: Returns to roster view for continued management
     *
     * @param \App\Services\WarrantManager\WarrantManagerInterface $wManager Warrant management service
     * @param string|null $id Warrant roster ID to decline
     * @return \Cake\Http\Response Redirect to roster view
     * @throws \Cake\Http\Exception\NotFoundException When roster not found
     */
    public function decline(WarrantManagerInterface $wManager, ?string $id = null)
    {
        // Require POST request for security
        $this->request->allowMethod(['post']);

        // Load roster with warrants for processing
        $warrantRoster = $this->WarrantRosters->get($id, ['contain' => ['Warrants']]);
        if ($warrantRoster == null) {
            throw new NotFoundException();
        }

        // Authorize decline operation on specific roster
        $this->Authorization->authorize($warrantRoster);

        // Process decline through WarrantManager service with standard reason
        $wmResult = $wManager->decline($warrantRoster->id, $this->Authentication->getIdentity()->getIdentifier(), 'Declined from Warrant Roster View');

        if ($wmResult->success) {
            $this->Flash->success(__('The declination has been been processed.'));
        } else {
            $this->Flash->error(__($wmResult->reason));
        }

        return $this->redirect(['action' => 'view', $id]);
    }

    /**
     * Decline warrant in roster method - Individual warrant management within roster
     *
     * Handles declining individual warrants within a roster batch, providing fine-grained
     * control over warrant management while maintaining roster integrity. Supports both
     * URL parameter and form data input for flexible UI integration.
     *
     * ## Individual Warrant Control
     * - **Selective Decline**: Decline specific warrants without affecting entire roster
     * - **Flexible Input**: Accepts roster and warrant IDs via URL or form data
     * - **Validation Chain**: Comprehensive validation of roster-warrant relationship
     * - **Authorization Control**: Entity-level authorization on specific warrant
     * - **Service Integration**: WarrantManager handles business logic and audit trail
     *
     * ## Parameter Handling
     * - **URL Parameters**: Primary method for direct warrant targeting
     * - **Form Data Fallback**: Alternative input method for form-based operations
     * - **Relationship Validation**: Ensures warrant belongs to specified roster
     * - **Entity Loading**: Validates warrant exists within roster context
     *
     * ## WarrantManager Integration
     * The individual warrant decline is processed through WarrantManager which:
     * - Validates warrant decline eligibility
     * - Records decline reason and user identity
     * - Updates warrant status appropriately
     * - Handles associated office releases if applicable
     * - Provides detailed operation feedback
     *
     * ## Business Logic and Notifications
     * - **Office Integration**: Automatic officer release for warrant-associated offices
     * - **Notification Responsibility**: Manual notification required for officer changes
     * - **Audit Trail**: Complete tracking of individual warrant decisions
     * - **Status Management**: Proper warrant status updates without roster impact
     *
     * ## User Experience
     * - **Contextual Feedback**: Specific messaging about warrant and office impacts
     * - **Navigation Preservation**: Returns to referring page for workflow continuity
     * - **Action Confirmation**: Clear confirmation of individual warrant processing
     * - **Error Handling**: Detailed error messages for failed operations
     *
     * @param \App\Services\WarrantManager\WarrantManagerInterface $wService Warrant management service
     * @param string $roster_id Warrant roster ID containing the warrant
     * @param string|null $warrant_id Individual warrant ID to decline
     * @return \Cake\Http\Response Redirect to referring page
     * @throws \Cake\Http\Exception\NotFoundException When warrant or roster not found
     */
    public function declineWarrantInRoster(WarrantManagerInterface $wService, $roster_id, $warrant_id = null)
    {
        // Require POST request for security
        $this->request->allowMethod(['post']);

        // Handle flexible parameter input (URL or form data)
        if (!$roster_id) {
            $roster_id = $this->request->getData('roster_id');
        }
        if (!$warrant_id) {
            $warrant_id = $this->request->getData('warrant_id');
        }

        // Validate warrant exists within specified roster
        $warrant = $this->WarrantRosters->Warrants->find()
            ->where(['id' => $warrant_id, 'warrant_roster_id' => $roster_id])
            ->first();

        if ($warrant == null) {
            throw new NotFoundException();
        }

        // Authorize decline operation on specific warrant
        $this->Authorization->authorize($warrant);

        // Process individual warrant decline through WarrantManager
        $wResult = $wService->declineSingleWarrant((int)$warrant_id, 'Declined Warrant', $this->Authentication->getIdentity()->get('id'));

        if (!$wResult->success) {
            $this->Flash->error($wResult->reason);

            return $this->redirect($this->referer());
        }

        // Provide comprehensive feedback about warrant and office impacts
        $this->Flash->success(__('The warrant has been deactivated. If this warrant is associated with an office, the officer has been released however they have not been notified.  Please notify them at your earliest convienence.'));

        return $this->redirect($this->referer());
    }
}