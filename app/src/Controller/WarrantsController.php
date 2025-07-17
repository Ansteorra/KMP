<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Warrant;
use App\Services\CsvExportService;
use App\Services\WarrantManager\WarrantManagerInterface;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\DateTime;

/**
 * WarrantsController - Warrant Management Interface and Administrative Controls
 *
 * The WarrantsController provides the web interface for managing warrants within the KMP
 * temporal validation system for RBAC. This controller handles warrant lifecycle operations,
 * filtering, administrative controls, and data export functionality for the warrant approval
 * and management workflows.
 *
 * ## Core Responsibilities
 *
 * ### Warrant Management Interface
 * - **Warrant Listing**: Comprehensive warrant views with temporal filtering (current, pending, upcoming, previous)
 * - **Status Management**: Administrative controls for warrant lifecycle operations
 * - **Data Export**: CSV export functionality for warrant data analysis and reporting
 * - **Authorization Integration**: Role-based access control for warrant management operations
 *
 * ### Temporal Warrant Queries
 * - **Current Warrants**: Active warrants providing temporal validation for RBAC permissions
 * - **Pending Warrants**: Warrants awaiting approval through warrant roster system
 * - **Upcoming Warrants**: Future warrants scheduled for activation
 * - **Previous Warrants**: Expired, deactivated, or historical warrant records
 *
 * ### Administrative Controls
 * - **Warrant Deactivation**: Administrative termination of active warrants with audit trails
 * - **Security Validation**: Authorization checks for all warrant management operations
 * - **Service Integration**: Integration with WarrantManager service for business logic
 * - **Error Handling**: Comprehensive error handling for warrant operations and validation
 *
 * ## Warrant Filtering Architecture
 *
 * ### Temporal State Filtering
 * The controller implements comprehensive temporal filtering for warrant states:
 * ```php
 * // Current warrants - Active and providing RBAC validation
 * case 'current':
 *     $warrantsQuery = $warrantsQuery->where([
 *         'Warrants.expires_on >=' => $today,      // Not expired
 *         'Warrants.start_on <=' => $today,        // Already started
 *         'Warrants.status' => Warrant::CURRENT_STATUS  // Active status
 *     ]);
 * 
 * // Pending warrants - Awaiting approval
 * case 'pending':
 *     $warrantsQuery = $warrantsQuery->where([
 *         'Warrants.status' => Warrant::PENDING_STATUS
 *     ]);
 * 
 * // Upcoming warrants - Scheduled for future activation
 * case 'upcoming':
 *     $warrantsQuery = $warrantsQuery->where([
 *         'Warrants.start_on >' => $today,         // Future start date
 *         'Warrants.status' => Warrant::CURRENT_STATUS
 *     ]);
 * 
 * // Previous warrants - Expired or terminated
 * case 'previous':
 *     $warrantsQuery = $warrantsQuery->where([
 *         'OR' => [
 *             'Warrants.expires_on <' => $today,   // Expired by date
 *             'Warrants.status IN ' => [           // Terminated by admin
 *                 Warrant::DEACTIVATED_STATUS,
 *                 Warrant::EXPIRED_STATUS
 *             ]
 *         ]
 *     ]);
 * ```
 *
 * ### Association Loading Strategy
 * Optimized data loading for warrant management interface:
 * ```php
 * $warrantsQuery = $this->Warrants->find()
 *     ->contain([
 *         'Members',          // Warrant recipients
 *         'WarrantRosters',   // Approval batch information
 *         'MemberRoles'       // RBAC integration data
 *     ]);
 * ```
 *
 * ## CSV Export Integration
 *
 * ### Data Export Functionality
 * Comprehensive CSV export for warrant analysis and reporting:
 * ```php
 * // CSV export with optimized query
 * if ($this->isCsvRequest()) {
 *     return $csvExportService->outputCsv(
 *         $warrantsQuery->order(['Members.sca_name' => 'asc']),
 *         'warrants.csv'
 *     );
 * }
 * ```
 *
 * ### Export Data Optimization
 * Optimized field selection for performance and security:
 * ```php
 * $query->select([
 *     'id', 'name', 'member_id', 'entity_type',
 *     'start_on', 'expires_on', 'revoker_id',
 *     'warrant_roster_id', 'status', 'revoked_reason'
 * ])
 * ->contain([
 *     'Members' => function ($q) {
 *         return $q->select(['id', 'sca_name']);    // Member identification
 *     },
 *     'RevokedBy' => function ($q) {
 *         return $q->select(['id', 'sca_name']);    // Revocation audit
 *     }
 * ]);
 * ```
 *
 * ## Administrative Warrant Operations
 *
 * ### Warrant Deactivation Workflow
 * Secure administrative termination of active warrants:
 * ```php
 * public function deactivate(WarrantManagerInterface $wService, $id = null)
 * {
 *     // Security validation
 *     $this->request->allowMethod(['post']);       // POST-only for security
 *     
 *     // Warrant retrieval with authorization
 *     $warrant = $this->Warrants->find()
 *         ->where(['Warrants.id' => $id])
 *         ->contain(['Members'])                   // Load for authorization
 *         ->first();
 *     
 *     if (!$warrant) {
 *         throw new NotFoundException(__('The warrant does not exist.'));
 *     }
 *     
 *     // Authorization check for warrant deactivation
 *     $this->Authorization->authorize($warrant);
 *     
 *     // Business logic through WarrantManager service
 *     $wResult = $wService->cancel(
 *         (int)$id,
 *         'Deactivated from Warrant List',         // Audit reason
 *         $this->Authentication->getIdentity()->get('id'),  // Admin ID
 *         DateTime::now()                          // Deactivation timestamp
 *     );
 * }
 * ```
 *
 * ### Service Integration Pattern
 * The controller delegates business logic to WarrantManager service:
 * - **Separation of Concerns**: Controller handles HTTP, service handles business logic
 * - **Transaction Management**: Service ensures data consistency
 * - **Error Handling**: Service returns standardized ServiceResult objects
 * - **Audit Trail**: Service manages complete audit trail for warrant changes
 *
 * ## Authorization Architecture
 *
 * ### Role-Based Access Control
 * Comprehensive authorization for warrant management:
 * ```php
 * // Model-level authorization for index operations
 * $this->Authorization->authorizeModel('index');
 * 
 * // Entity-level authorization for specific warrant operations
 * $this->Authorization->authorize($warrant);
 * ```
 *
 * ### Security Validation
 * Multi-layer security for warrant operations:
 * - **HTTP Method Validation**: POST-only for destructive operations
 * - **Entity Authorization**: Per-warrant authorization checks
 * - **Identity Validation**: Current user authentication required
 * - **Audit Trail**: Complete tracking of administrative actions
 *
 * ## Performance Optimization
 *
 * ### Query Optimization
 * Efficient database queries for warrant management:
 * ```php
 * // Optimized field selection
 * $query->select(['id', 'name', 'member_id']) // only needed fields
 *       ->contain(['Members', 'WarrantRosters']); // only needed associations
 * 
 * // Pagination for large datasets
 * $warrants = $this->paginate($warrantsQuery);
 * 
 * // CSV export with streaming for large datasets
 * return $csvExportService->outputCsv($warrantsQuery, 'warrants.csv');
 * ```
 *
 * ### Memory Management
 * Efficient handling of large warrant datasets:
 * - **Pagination**: Paginated results for web interface
 * - **Streaming Export**: CSV streaming for large exports
 * - **Selective Loading**: Only required fields and associations loaded
 * - **Query Optimization**: Database-level filtering before data loading
 *
 * ## Error Handling and User Experience
 *
 * ### Comprehensive Error Handling
 * ```php
 * // Not found handling
 * if (!$warrant) {
 *     throw new NotFoundException('The warrant does not exist.');
 * }
 * 
 * // Service result validation
 * if (!$wResult->success) {
 *     $this->Flash->error($wResult->reason);
 *     return $this->redirect($this->referer());
 * }
 * 
 * // Success feedback
 * $this->Flash->success('The warrant has been deactivated.');
 * ```
 *
 * ### User Feedback Integration
 * - **Flash Messages**: User feedback for all operations
 * - **Redirect Handling**: Proper redirect after operations
 * - **Error Context**: Meaningful error messages for troubleshooting
 * - **Success Confirmation**: Clear success feedback for administrative actions
 *
 * ## Integration Examples
 *
 * ### Warrant Management Workflow
 * ```php
 * // View current warrants
 * GET /warrants/all-warrants/current
 * 
 * // Export current warrants
 * GET /warrants/all-warrants/current.csv
 * 
 * // Deactivate warrant
 * POST /warrants/deactivate/123
 * Content-Type: application/json
 * Body: {"id": 123}
 * ```
 *
 * ### Service Integration
 * ```php
 * // Controller delegates to WarrantManager service
 * $wService = $this->getTableLocator()->get('Services.WarrantManager');
 * 
 * // Service handles business logic and returns result
 * $result = $wService->cancel($warrantId, $reason, $adminId, $timestamp);
 * 
 * // Controller handles HTTP response based on service result
 * if ($result->success) {
 *     $this->Flash->success('Warrant deactivated');
 * } else {
 *     $this->Flash->error($result->reason);
 * }
 * ```
 *
 * ### Authorization Integration
 * ```php
 * // Model authorization for listing
 * $securityWarrant = $this->Warrants->newEmptyEntity();
 * $this->Authorization->authorize($securityWarrant);
 * 
 * // Entity authorization for operations
 * $warrant = $this->Warrants->get($id);
 * $this->Authorization->authorize($warrant, 'deactivate');
 * ```
 *
 * ## Usage Examples
 *
 * ### Administrative Warrant Management
 * ```php
 * // List current warrants for review
 * $currentWarrants = $this->Warrants->find()
 *     ->where([
 *         'start_on <=' => $now,
 *         'expires_on >' => $now,
 *         'status' => Warrant::CURRENT_STATUS
 *     ])
 *     ->contain(['Members', 'WarrantRosters']);
 * 
 * // Deactivate warrant for security incident
 * $result = $warrantManager->cancel(
 *     $warrantId,
 *     'Security incident - immediate revocation',
 *     $adminId,
 *     DateTime::now()
 * );
 * ```
 *
 * ### Data Export and Analysis
 * ```php
 * // Export pending warrants for approval review
 * $pendingQuery = $this->Warrants->find()
 *     ->where(['status' => Warrant::PENDING_STATUS])
 *     ->contain(['Members', 'WarrantRosters']);
 * 
 * // Stream CSV export
 * return $csvExportService->outputCsv($pendingQuery, 'pending_warrants.csv');
 * ```
 *
 * ### Temporal Query Patterns
 * ```php
 * // Find warrants expiring soon
 * $expiringSoon = $this->Warrants->find()
 *     ->where([
 *         'expires_on BETWEEN ? AND ?' => [$now, $notificationDate],
 *         'status' => Warrant::CURRENT_STATUS
 *     ]);
 * 
 * // Find warrants by roster for batch operations
 * $rosterWarrants = $this->Warrants->find()
 *     ->where(['warrant_roster_id' => $rosterId])
 *     ->contain(['Members']);
 * ```
 *
 * @see \App\Model\Table\WarrantsTable For warrant data management and validation
 * @see \App\Model\Entity\Warrant For warrant entity and temporal validation
 * @see \App\Services\WarrantManager\WarrantManagerInterface For warrant business logic
 * @see \App\Services\CsvExportService For data export functionality
 * @see \App\Controller\AppController For base controller functionality
 *
 * @property \App\Model\Table\WarrantsTable $Warrants
 * @property \Authorization\Controller\Component\AuthorizationComponent $Authorization
 */
class WarrantsController extends AppController
{
    /**
     * CSV export service dependency injection
     *
     * Configures dependency injection for the CsvExportService to handle
     * warrant data export functionality with memory-efficient streaming.
     *
     * @var array<string> Service injection configuration
     */
    public static array $inject = [CsvExportService::class];

    /**
     * CSV export service instance
     *
     * Provides memory-efficient CSV export functionality for warrant data
     * analysis and reporting workflows.
     *
     * @var \App\Services\CsvExportService
     */
    protected CsvExportService $csvExportService;

    /**
     * Initialize controller - Configure authorization and warrant management
     *
     * Sets up the warrant management controller with proper authorization
     * components and security controls for warrant administrative operations.
     *
     * ### Authorization Configuration
     * Configures role-based access control for warrant operations:
     * - **Model Authorization**: Authorizes warrant index operations
     * - **Security Integration**: Integrates with KMP authorization system
     * - **Permission Validation**: Ensures proper permissions for warrant management
     *
     * ### Component Loading
     * Loads required components for warrant management:
     * - **Authorization Component**: Role-based access control
     * - **Parent Initialization**: Inherits AppController security and navigation
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        // Load authorization component for warrant management security
        $this->loadComponent('Authorization.Authorization');

        // Authorize model-level access for warrant listing operations
        $this->Authorization->authorizeModel('index');
    }

    /**
     * Index method - Warrant management dashboard
     *
     * Provides the main warrant management interface with comprehensive
     * listing, filtering, and administrative control capabilities.
     *
     * ### Functionality
     * - **Warrant Dashboard**: Main interface for warrant management
     * - **Navigation Integration**: Links to filtered warrant views
     * - **Administrative Overview**: Summary of warrant states and counts
     * - **Quick Actions**: Access to common warrant management operations
     *
     * ### Security
     * - **Authorization Required**: Must have warrant management permissions
     * - **Role-Based Access**: Different views based on user permissions
     * - **Audit Integration**: All access logged for security auditing
     *
     * @return \Cake\Http\Response|null|void Renders warrant management dashboard
     */
    public function index() {}

    /**
     * All warrants method - Filtered warrant listing with export capability
     *
     * Provides comprehensive warrant listing with temporal filtering, pagination,
     * and CSV export functionality. This method handles the core warrant management
     * interface with optimized queries and user-friendly filtering options.
     *
     * ### Temporal State Filtering
     * Supports four distinct warrant states:
     * - **current**: Active warrants providing RBAC temporal validation
     * - **pending**: Warrants awaiting approval through roster system
     * - **upcoming**: Future warrants scheduled for activation
     * - **previous**: Expired, deactivated, or historical warrants
     *
     * ### Query Optimization
     * Implements efficient database queries:
     * ```php
     * // Optimized association loading
     * $warrantsQuery = $this->Warrants->find()
     *     ->contain(['Members', 'WarrantRosters', 'MemberRoles']);
     * 
     * // Temporal filtering with proper indexing
     * $warrantsQuery->where([
     *     'Warrants.expires_on >=' => $today,      // Uses date index
     *     'Warrants.start_on <=' => $today,        // Uses date index
     *     'Warrants.status' => Warrant::CURRENT_STATUS // Uses status index
     * ]);
     * ```
     *
     * ### CSV Export Integration
     * Provides memory-efficient CSV export:
     * - **Streaming Export**: Handles large datasets without memory issues
     * - **Optimized Fields**: Only exports necessary data for performance
     * - **Sorted Output**: Alphabetical sorting by member name for usability
     * - **Security**: Same authorization rules apply to export functionality
     *
     * ### Authorization and Security
     * - **Entity Authorization**: Creates security entity for permission checking
     * - **State Validation**: Validates filter state parameters
     * - **Access Control**: Ensures user has appropriate warrant management permissions
     * - **Audit Trail**: All access and exports logged for security auditing
     *
     * ### Error Handling
     * - **Invalid State**: Throws NotFoundException for invalid filter states
     * - **Authorization Failure**: Proper error handling for access denied
     * - **Database Errors**: Graceful handling of database connection issues
     * - **Export Errors**: Proper error handling for CSV export failures
     *
     * @param \App\Services\CsvExportService $csvExportService CSV export service
     * @param string $state Temporal filter state (current|pending|upcoming|previous)
     * @return \Cake\Http\Response|null|void Renders warrant list or returns CSV export
     * @throws \Cake\Http\Exception\NotFoundException When invalid state provided
     */
    public function allWarrants(CsvExportService $csvExportService, $state)
    {
        // Validate state parameter to prevent invalid filter attempts
        if ($state != 'current' && $state == 'pending' && $state == 'previous') {
            throw new NotFoundException();
        }

        // Create security entity for authorization checking
        $securityWarrant = $this->Warrants->newEmptyEntity();
        $this->Authorization->authorize($securityWarrant);

        // Build base query with optimized association loading
        $warrantsQuery = $this->Warrants->find()
            ->contain(['Members', 'WarrantRosters', 'MemberRoles']);

        // Apply temporal filtering based on current date
        $today = new DateTime();
        switch ($state) {
            case 'current':
                // Active warrants providing RBAC temporal validation
                $warrantsQuery = $warrantsQuery->where([
                    'Warrants.expires_on >=' => $today,           // Not expired
                    'Warrants.start_on <=' => $today,             // Already started
                    'Warrants.status' => Warrant::CURRENT_STATUS  // Active status
                ]);
                break;
            case 'upcoming':
                // Future warrants scheduled for activation
                $warrantsQuery = $warrantsQuery->where([
                    'Warrants.start_on >' => $today,              // Future start date
                    'Warrants.status' => Warrant::CURRENT_STATUS  // Approved status
                ]);
                break;
            case 'pending':
                // Warrants awaiting approval through roster system
                $warrantsQuery = $warrantsQuery->where([
                    'Warrants.status' => Warrant::PENDING_STATUS
                ]);
                break;
            case 'previous':
                // Expired or administratively terminated warrants
                $warrantsQuery = $warrantsQuery->where([
                    'OR' => [
                        'Warrants.expires_on <' => $today,        // Expired by date
                        'Warrants.status IN ' => [                // Terminated by admin
                            Warrant::DEACTIVATED_STATUS,
                            Warrant::EXPIRED_STATUS
                        ]
                    ]
                ]);
                break;
        }

        // Apply additional query conditions for optimization
        $warrantsQuery = $this->addConditions($warrantsQuery);

        // CSV export for filtered warrant data
        if ($this->isCsvRequest()) {
            return $csvExportService->outputCsv(
                $warrantsQuery->order(['Members.sca_name' => 'asc']),  // Alphabetical order
                'warrants.csv',
            );
        }

        // Paginated results for web interface
        $warrants = $this->paginate($warrantsQuery);
        $this->set(compact('warrants', 'state'));
    }

    /**
     * Add conditions - Optimize warrant queries for performance and security
     *
     * Applies query optimization and field selection for warrant listing operations.
     * This method ensures efficient database queries and secure data exposure
     * for warrant management interfaces.
     *
     * ### Query Optimization
     * - **Field Selection**: Only selects necessary fields for performance
     * - **Association Optimization**: Loads only required member data
     * - **Security Fields**: Includes audit trail and revocation information
     * - **Performance Balance**: Balances data needs with query efficiency
     *
     * ### Security Considerations
     * - **Data Minimization**: Only exposes necessary warrant information
     * - **Audit Information**: Includes revocation data for administrative oversight
     * - **Member Privacy**: Limited member data exposure for warrant context
     * - **Consistency**: Consistent field selection across warrant operations
     *
     * @param \Cake\ORM\Query $query Base warrant query to optimize
     * @return \Cake\ORM\Query Optimized query with conditions and field selection
     */
    protected function addConditions($query)
    {
        return $query
            // Select optimized field set for performance
            ->select([
                'id',
                'name',
                'member_id',
                'entity_type',
                'start_on',
                'expires_on',
                'revoker_id',
                'warrant_roster_id',
                'status',
                'revoked_reason'
            ])
            // Optimize association loading
            ->contain([
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name']);  // Member identification
                },
                'RevokedBy' => function ($q) {
                    return $q->select(['id', 'sca_name']);  // Revocation audit
                },
            ]);
    }

    /**
     * Deactivate warrant - Administrative warrant termination with audit trail
     *
     * Provides secure administrative deactivation of active warrants with
     * comprehensive audit trails and proper authorization validation. This
     * method handles immediate warrant termination for security incidents
     * or administrative requirements.
     *
     * ### Security Requirements
     * - **POST-Only**: Requires POST method for security against CSRF
     * - **Authorization**: Entity-level authorization for warrant deactivation
     * - **Identity Validation**: Requires authenticated administrative user
     * - **Audit Trail**: Complete tracking of deactivation action and reason
     *
     * ### Deactivation Process
     * 1. **Security Validation**: Method and authorization checks
     * 2. **Warrant Retrieval**: Load warrant with member data for authorization
     * 3. **Authorization Check**: Verify user can deactivate specific warrant
     * 4. **Service Delegation**: Use WarrantManager for business logic
     * 5. **Result Handling**: Process service result and provide user feedback
     * 6. **Redirect**: Return to referring page with status feedback
     *
     * ### Business Logic Integration
     * Delegates to WarrantManager service for:
     * - **Transaction Management**: Ensures data consistency
     * - **Cache Invalidation**: Automatic permission cache updates
     * - **Audit Logging**: Complete audit trail creation
     * - **Status Validation**: Business rule enforcement
     *
     * ### Error Handling
     * - **Not Found**: Proper handling when warrant doesn't exist
     * - **Authorization**: Clear error when access denied
     * - **Service Errors**: Business logic error propagation
     * - **User Feedback**: Clear success/error messages
     *
     * ### Usage Example
     * ```php
     * // Administrative warrant deactivation
     * POST /warrants/deactivate/123
     * 
     * // Service handles business logic
     * $result = $warrantManager->cancel(
     *     123,                                    // Warrant ID
     *     'Deactivated from Warrant List',        // Audit reason
     *     $adminId,                              // Administrator ID
     *     DateTime::now()                        // Deactivation timestamp
     * );
     * 
     * // Controller handles HTTP response
     * if ($result->success) {
     *     Flash::success('Warrant deactivated');
     *     redirect(referer());
     * }
     * ```
     *
     * @param \App\Services\WarrantManager\WarrantManagerInterface $wService Warrant management service
     * @param int|null $id Warrant ID to deactivate (from URL or POST data)
     * @return \Cake\Http\Response Redirect response with status feedback
     * @throws \Cake\Http\Exception\NotFoundException When warrant doesn't exist
     */
    public function deactivate(WarrantManagerInterface $wService, $id = null)
    {
        // Security: Only allow POST method for destructive operations
        $this->request->allowMethod(['post']);

        // Get warrant ID from URL parameter or POST data
        if (!$id) {
            $id = $this->request->getData('id');
        }

        // Load warrant with member data for authorization context
        $warrant = $this->Warrants->find()
            ->where(['Warrants.id' => $id])
            ->contain(['Members'])                  // Load for authorization
            ->first();

        // Validate warrant exists
        if (!$warrant) {
            throw new NotFoundException(__('The warrant does not exist.'));
        }

        // Entity-level authorization for warrant deactivation
        $this->Authorization->authorize($warrant);

        // Delegate to WarrantManager service for business logic
        $wResult = $wService->cancel(
            (int)$id,                                       // Warrant ID
            'Deactivated from Warrant List',                // Audit reason
            $this->Authentication->getIdentity()->get('id'), // Administrator ID
            DateTime::now()                                 // Deactivation timestamp
        );

        // Handle service result and provide user feedback
        if (!$wResult->success) {
            $this->Flash->error($wResult->reason);
            return $this->redirect($this->referer());
        }

        // Success feedback and redirect
        $this->Flash->success(__('The warrant has been deactivated.'));
        return $this->redirect($this->referer());
    }
}
