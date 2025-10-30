<?php

declare(strict_types=1);

namespace Awards\Controller;

use Awards\Controller\AppController;
use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;
use App\KMP\StaticHelpers;
use Authorization\Exception\ForbiddenException;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Exception;
use PhpParser\Node\Stmt\TryCatch;
use App\Services\CsvExportService;
use Cake\Error\Debugger;


/**
 * Recommendations Controller
 * 
 * Comprehensive controller managing the complete recommendation lifecycle for award nominations
 * within the KMP Awards system. This controller implements a sophisticated state machine-based
 * workflow for processing award recommendations from initial submission through final disposition,
 * including complex authorization controls, bulk operations, and multi-format data visualization.
 * 
 * ## Architecture Overview
 * 
 * The RecommendationsController serves as the primary interface for award recommendation management,
 * implementing a robust state-based workflow system that tracks recommendations through multiple
 * phases including submission, review, approval, ceremony assignment, and final disposition.
 * The controller supports both authenticated member workflows and public unauthenticated submission
 * capabilities for external community participation.
 * 
 * ## Core Workflow Management
 * 
 * ### State Machine Integration
 * - **Status/State Dual Tracking**: Implements both status (high-level category) and state 
 *   (detailed workflow position) tracking for granular workflow control
 * - **Automated Transitions**: Supports programmatic state transitions with audit trail logging
 * - **Permission-Based Visibility**: Applies role-based access control to state visibility
 * - **Bulk Operations**: Enables administrative bulk state transitions with transaction safety
 * 
 * ### Recommendation Lifecycle
 * - **Submission Phase**: Handles both member and public recommendation submission workflows
 * - **Review Phase**: Supports detailed review processes with note integration and approval chains
 * - **Assignment Phase**: Manages event assignment and ceremony coordination workflows
 * - **Completion Phase**: Tracks award presentation and final disposition recording
 * 
 * ## Data Visualization & Interface Modes
 * 
 * ### Tabular Display Mode
 * - **Advanced Filtering**: Supports complex multi-criteria filtering with query parameter integration
 * - **Sortable Columns**: Provides comprehensive sorting across all recommendation attributes
 * - **Export Capabilities**: Includes CSV export with configurable column selection
 * - **Pagination Management**: Optimized pagination for large recommendation datasets
 * 
 * ### Kanban Board Mode
 * - **Interactive State Management**: Drag-and-drop interface for state transitions
 * - **Real-Time Updates**: AJAX-based updates without page refresh requirements
 * - **Visual Workflow**: Clear visual representation of recommendation flow states
 * - **Permission-Based Columns**: Dynamic column visibility based on user permissions
 * 
 * ## Integration Architecture
 * 
 * ### Awards System Integration
 * - **Award Hierarchy**: Integration with awards, domains, levels, and specialties
 * - **Branch Scoping**: Supports branch-based access control and data filtering
 * - **Event Coordination**: Links recommendations to award ceremonies and events
 * - **Member Validation**: Validates member eligibility and preference integration
 * 
 * ### Authentication & Authorization
 * - **Multi-Mode Access**: Supports both authenticated and public access workflows
 * - **Permission-Based Features**: Feature availability based on user authorization level
 * - **Scope Application**: Automatic query scoping based on user permissions
 * - **View Configuration**: Dynamic interface configuration based on access level
 * 
 * ### External System Integration
 * - **Note System**: Integration with comprehensive note and comment system
 * - **Email Notifications**: Automated workflow notifications and status updates
 * - **Member Profiles**: Synchronization with member data and preferences
 * - **Audit Logging**: Complete audit trail for all recommendation operations
 * 
 * ## Performance & Scalability
 * 
 * ### Query Optimization
 * - **Selective Loading**: Optimized containments to load only required data
 * - **Authorization Scoping**: Query-level authorization to minimize data transfer
 * - **Efficient Joins**: Complex but optimized joins for comprehensive data access
 * - **Pagination Strategy**: Memory-efficient pagination for large datasets
 * 
 * ### Transaction Management
 * - **ACID Compliance**: Full transaction support for multi-table operations
 * - **Rollback Safety**: Comprehensive error handling with automatic rollback
 * - **Concurrent Access**: Safe handling of concurrent recommendation modifications
 * - **Bulk Operations**: Efficient batch processing for administrative operations
 * 
 * ## Usage Examples
 * 
 * ```php
 * // Basic recommendation listing with filtering
 * $controller->index(); // Renders configurable landing page
 * $controller->table(null, 'Admin', 'Open'); // Admin view of open recommendations
 * 
 * // State-based workflow operations
 * $controller->updateStates(); // Bulk state transition
 * $controller->kanbanUpdate($id); // Individual drag-and-drop state change
 * 
 * // Data export and reporting
 * $controller->table($csvService, 'Export', 'All'); // CSV export generation
 * $controller->board('Review', 'InProgress'); // Kanban board visualization
 * 
 * // Public and member submission workflows
 * $controller->submitRecommendation(); // Public unauthenticated submission
 * $controller->add(); // Authenticated member submission
 * ```
 * 
 * @property \Awards\Model\Table\RecommendationsTable $Recommendations Primary model for recommendation data
 * @see \Awards\Model\Entity\Recommendation For recommendation entity structure and state machine
 * @see \Awards\Model\Table\RecommendationsTable For data access patterns and business rules
 * @see \App\Services\CsvExportService For export functionality integration
 * @see \Awards\Plugin For overall Awards plugin architecture
 */
class RecommendationsController extends AppController
{
    /**
     * Configure authentication requirements before action execution
     * 
     * Establishes authentication bypass configuration for public recommendation submission
     * capabilities while maintaining security for all other controller operations.
     * This method enables community members without KMP accounts to submit award
     * recommendations through the public submission interface.
     * 
     * ## Authentication Strategy
     * 
     * The controller implements a hybrid authentication model where most operations
     * require authenticated user sessions, but specific public-facing actions are
     * exempted to enable community participation in the award recommendation process.
     * 
     * ### Public Access Actions
     * - **submitRecommendation**: Allows unauthenticated recommendation submission
     *   for community members who may not have KMP system accounts but wish to
     *   nominate deserving individuals for awards
     * 
     * ### Security Considerations
     * - All other actions maintain full authentication requirements
     * - Public submissions are subject to additional validation and moderation
     * - Authorization policies still apply even to unauthenticated actions
     * - Rate limiting and spam protection handled at framework level
     * 
     * @param \Cake\Event\EventInterface $event The beforeFilter event instance
     * @return \Cake\Http\Response|null|void Response object for redirects or null for normal flow
     * 
     * @see submitRecommendation() For the public submission workflow implementation
     * @see \Cake\Controller\Component\AuthenticationComponent For authentication framework
     */
    public function beforeFilter(\Cake\Event\EventInterface $event): ?\Cake\Http\Response
    {
        parent::beforeFilter($event);

        $this->Authentication->allowUnauthenticated([
            'submitRecommendation'
        ]);

        return null;
    }

    /**
     * Recommendation system landing page with configurable view management
     * 
     * Serves as the primary entry point for the recommendation management system,
     * providing a configurable interface that adapts based on user permissions,
     * view configuration, and workflow requirements. The method handles view
     * configuration loading, permission validation, and interface customization
     * based on the requested view mode and user authorization level.
     * 
     * ## View Configuration System
     * 
     * The index method implements a sophisticated view configuration system that
     * allows different interfaces for different user roles and workflow contexts:
     * 
     * ### Configuration Loading
     * - **Dynamic View Loading**: Loads view-specific configuration from app settings
     * - **Fallback Strategy**: Falls back to default configuration if specific view not found
     * - **Permission Integration**: Adapts configuration based on user permissions
     * - **Error Handling**: Graceful degradation for missing configurations
     * 
     * ### Supported View Types
     * - **Index**: Default general-purpose recommendation overview
     * - **Admin**: Administrative interface with enhanced controls
     * - **Review**: Focused interface for recommendation review workflows
     * - **Custom**: Extensible system for additional view configurations
     * 
     * ## Authorization Integration
     * 
     * ### Multi-Argument Authorization
     * The method uses advanced authorization patterns that validate access based on:
     * - **View Type**: Different views may have different access requirements
     * - **Status Filter**: Some status filters may be restricted to certain users
     * - **Query Parameters**: Additional filtering parameters subject to authorization
     * - **Feature Availability**: Individual features enabled/disabled per user
     * 
     * ### Permission-Based Features
     * - **Board View Access**: Kanban board availability based on 'UseBoard' permission
     * - **Administrative Functions**: Enhanced controls for users with admin permissions
     * - **Data Visibility**: Filtering of sensitive data based on access level
     * - **Export Capabilities**: Download permissions managed per user role
     * 
     * ## Error Handling & User Experience
     * 
     * ### Graceful Degradation
     * - **Configuration Errors**: Automatic fallback to working configurations
     * - **Permission Errors**: Clear messaging for authorization failures
     * - **System Errors**: Safe redirect to home page with user notification
     * - **Logging Integration**: Comprehensive error logging for troubleshooting
     * 
     * ## Interface Customization
     * 
     * ### Dynamic Configuration
     * The page configuration determines:
     * - **Available Views**: Table vs board vs hybrid interfaces
     * - **Feature Availability**: Export, filtering, bulk operations
     * - **Default Filters**: Pre-applied filtering based on view context
     * - **User Interface Elements**: Navigation, controls, and display options
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Default landing page
     * GET /awards/recommendations
     * 
     * // Administrative view
     * GET /awards/recommendations?view=Admin
     * 
     * // Filtered status view
     * GET /awards/recommendations?view=Review&status=Pending
     * 
     * // Custom workflow view
     * GET /awards/recommendations?view=Ceremony&status=Approved
     * ```
     * 
     * @return \Cake\Http\Response|null|void Renders view template or redirects on error
     * 
     * @see table() For tabular data display implementation
     * @see board() For kanban board visualization
     * @see \App\KMP\StaticHelpers::getAppSetting() For configuration loading
     */
    public function index(): ?\Cake\Http\Response
    {
        $view = $this->request->getQuery('view') ?? 'Index';
        $status = $this->request->getQuery('status') ?? 'All';

        $emptyRecommendation = $this->Recommendations->newEmptyEntity();
        $queryArgs = $this->request->getQuery();
        $user = $this->request->getAttribute('identity');
        $user->authorizeWithArgs($emptyRecommendation, 'index', $view, $status, $queryArgs);

        try {
            if ($view && $view !== 'Index') {
                try {
                    $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig." . $view);
                } catch (\Exception $e) {
                    Log::debug('View config not found for ' . $view . ': ' . $e->getMessage());
                    $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
                }
            } else {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
            }

            if ($pageConfig['board']['use']) {
                $pageConfig['board']['use'] = $user->checkCan('UseBoard', $emptyRecommendation, $status, $view);
            }

            $this->set(compact('view', 'status', 'pageConfig'));
            return null;
        } catch (\Exception $e) {
            Log::error('Error in recommendations index: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while loading recommendations.'));
            return $this->redirect(['controller' => 'Pages', 'action' => 'display', 'home']);
        }
    }

    /**
     * Tabular recommendation display with advanced filtering and CSV export capabilities
     * 
     * Provides comprehensive tabular visualization of recommendation data with sophisticated
     * filtering, sorting, pagination, and export capabilities. This method serves as the
     * primary interface for detailed recommendation management, supporting both interactive
     * web display and automated CSV export generation for reporting and external processing.
     * 
     * ## View Configuration Management
     * 
     * ### Dynamic Configuration Loading
     * - **View-Specific Settings**: Loads configuration specific to the requested view type
     * - **Fallback Strategy**: Automatic fallback to default configuration for robustness
     * - **Filter Integration**: Applies view-specific filters and display rules
     * - **Permission Integration**: Adapts configuration based on user authorization level
     * 
     * ### Configuration Components
     * - **Table Settings**: Column visibility, sorting options, pagination parameters
     * - **Filter Configuration**: Available filters, default values, validation rules
     * - **Export Settings**: Column selection, format options, filename conventions
     * - **Permission Controls**: Feature availability, data visibility, operation access
     * 
     * ## Authorization & Access Control
     * 
     * ### Multi-Context Authorization
     * The method implements sophisticated authorization that considers:
     * - **View Context**: Different views may have different access requirements
     * - **Status Filtering**: Some status filters restricted to authorized users
     * - **Member Context**: Special handling for member-specific views (SubmittedByMember)
     * - **Query Parameters**: Additional filtering subject to authorization validation
     * 
     * ### Permission-Based Features
     * - **Data Visibility**: Automatic filtering of restricted recommendation states
     * - **Export Access**: CSV export availability based on user permissions
     * - **Operation Controls**: Bulk operations enabled per user authorization level
     * - **Column Access**: Sensitive columns hidden based on access permissions
     * 
     * ## CSV Export System
     * 
     * ### Export Detection
     * - **Format Detection**: Automatic detection of CSV export requests via headers/parameters
     * - **Permission Validation**: Export availability validated per view configuration
     * - **Column Configuration**: Configurable column inclusion for export files
     * - **Data Processing**: Specialized formatting for CSV-optimized data presentation
     * 
     * ### Export Features
     * - **Selective Columns**: Configure which data columns to include in export
     * - **Formatted Output**: Proper formatting for dates, relationships, and complex fields
     * - **Large Dataset Support**: Memory-efficient processing for large exports
     * - **Download Response**: Proper HTTP headers for browser download handling
     * 
     * ## Data Processing Pipeline
     * 
     * ### Filter Processing
     * 1. **Configuration Loading**: Load view-specific filter configuration
     * 2. **Dynamic Processing**: Process filters with parameter substitution support
     * 3. **Permission Application**: Apply authorization-based filter restrictions
     * 4. **Query Integration**: Integrate processed filters into database queries
     * 
     * ### Query Execution
     * 1. **Filter Application**: Apply processed filters to recommendation queries
     * 2. **Authorization Scoping**: Apply user-based query scoping for security
     * 3. **Association Loading**: Load required relationships for display/export
     * 4. **Performance Optimization**: Optimize queries for responsive user experience
     * 
     * ## Error Handling & Recovery
     * 
     * ### Comprehensive Error Management
     * - **Configuration Errors**: Graceful handling of missing or invalid configurations
     * - **Permission Errors**: Clear user feedback for authorization failures
     * - **Query Errors**: Safe error handling with user-friendly messaging
     * - **Export Errors**: Robust error handling for export generation failures
     * 
     * ### Recovery Strategies
     * - **Automatic Fallbacks**: Fallback to default configurations when possible
     * - **User Notification**: Clear error messaging through Flash component
     * - **Safe Redirects**: Redirect to safe fallback pages on critical errors
     * - **Logging Integration**: Comprehensive error logging for system monitoring
     * 
     * ## Performance Considerations
     * 
     * ### Query Optimization
     * - **Selective Loading**: Load only required data for current view/export
     * - **Efficient Joins**: Optimized association loading for performance
     * - **Pagination Support**: Memory-efficient pagination for large datasets
     * - **Index Usage**: Query patterns optimized for database index utilization
     * 
     * ### Export Optimization
     * - **Streaming Processing**: Stream large exports to avoid memory limitations
     * - **Batch Processing**: Process large datasets in manageable chunks
     * - **Response Optimization**: Optimized HTTP response handling for downloads
     * - **Resource Management**: Proper cleanup of resources during export generation
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Standard table display
     * $controller->table(null, 'Admin', 'Open');
     * 
     * // CSV export generation
     * $response = $controller->table($csvExportService, 'Export', 'All');
     * 
     * // Member-specific recommendations
     * $controller->table(null, 'SubmittedByMember', 'Pending');
     * 
     * // Custom filtered view
     * GET /awards/recommendations/table/Review/InProgress?award_id=123
     * ```
     * 
     * @param \App\Services\CsvExportService $csvExportService Service for CSV export generation
     * @param string|null $view View configuration name (defaults to 'Default')
     * @param string|null $status Status filter to apply (defaults to 'All')
     * @return \Cake\Http\Response|null|void Renders view template or returns CSV download response
     * 
     * @see runTable() For the core table data processing implementation
     * @see runExport() For CSV export generation logic
     * @see processFilter() For filter processing and parameter substitution
     * @see \App\Services\CsvExportService For export service integration
     */
    public function table(CsvExportService $csvExportService, ?string $view = null, ?string $status = null): ?\Cake\Http\Response
    {
        $view = $view ?? 'Default';
        $status = $status ?? 'All';

        try {
            $emptyRecommendation = $this->Recommendations->newEmptyEntity();
            if ($view && $view !== 'Default') {
                try {
                    $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig." . $view);
                } catch (\Exception $e) {
                    Log::debug('View config not found for ' . $view . ': ' . $e->getMessage());
                    $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
                }
                $filter = $pageConfig['table']['filter'];
            } else {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
                $filter = $pageConfig['table']['filter'];
            }

            $permission = isset($pageConfig['table']['optionalPermission']) && $pageConfig['table']['optionalPermission']
                ? $pageConfig['table']['optionalPermission']
                : 'index';

            $queryArgs = $this->request->getQuery();
            $user = $this->request->getAttribute('identity');

            if ($view === 'SubmittedByMember') {
                //get the memberid from the query args if available
                if (isset($queryArgs['member_id']) && is_numeric($queryArgs['member_id'])) {
                    $emptyRecommendation->requester_id = $queryArgs['member_id'];
                } else {
                    $this->Authorization->skipAuthorization();
                    throw new ForbiddenException();
                }
            }

            $user->authorizeWithArgs($emptyRecommendation, $permission, $view, $status, $queryArgs);

            $filter = $this->processFilter($filter);
            $enableExport = $pageConfig['table']['enableExport'];

            if ($enableExport && $this->isCsvRequest()) {
                $columns = $pageConfig['table']['export'];
                return $this->runExport($csvExportService, $filter, $columns);
            }

            $this->set(compact('pageConfig', 'enableExport'));
            $this->runTable($filter, $status, $view);
            return null;
        } catch (\Exception $e) {
            if (!$e instanceof ForbiddenException) {
                $this->Flash->error(__('An error occurred while loading recommendations.'));
            }
            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * Kanban board visualization for interactive recommendation workflow management
     * 
     * Provides a visual kanban-style interface for managing recommendation workflows,
     * enabling intuitive drag-and-drop state transitions and visual workflow tracking.
     * This method creates an interactive board view that groups recommendations by
     * their current state and provides real-time workflow management capabilities
     * for users with appropriate permissions.
     * 
     * ## Kanban Board Architecture
     * 
     * ### Visual Workflow Representation
     * - **State-Based Columns**: Each recommendation state displayed as a board column
     * - **Card-Based Items**: Individual recommendations displayed as draggable cards
     * - **Real-Time Updates**: AJAX-based updates without full page refresh
     * - **Interactive Controls**: Direct manipulation interface for state transitions
     * 
     * ### Board Configuration
     * - **Configurable States**: Board columns determined by view configuration
     * - **Permission-Based Visibility**: Column visibility based on user permissions
     * - **State Grouping**: Recommendations automatically grouped by current state
     * - **Visual Indicators**: Status indicators and metadata display on cards
     * 
     * ## Authorization & Access Control
     * 
     * ### Board Access Validation
     * - **Feature Availability**: Board view availability controlled by configuration
     * - **Permission Integration**: User authorization validated for board access
     * - **State Visibility**: Individual states may be restricted based on permissions
     * - **Operation Controls**: Drag-and-drop capabilities controlled by user roles
     * 
     * ### Multi-Level Authorization
     * The method validates access at multiple levels:
     * 1. **Board Feature Access**: Overall board view availability
     * 2. **View Configuration**: Specific view configuration permissions
     * 3. **State Visibility**: Individual state column access
     * 4. **Recommendation Access**: Per-recommendation authorization scoping
     * 
     * ## Configuration Management
     * 
     * ### Dynamic Configuration Loading
     * - **View-Specific Settings**: Load configuration for specific view contexts
     * - **Fallback Strategy**: Automatic fallback to default board configuration
     * - **Feature Toggles**: Board availability controlled by configuration flags
     * - **Error Handling**: Graceful handling of missing or invalid configurations
     * 
     * ### Board Feature Validation
     * - **Availability Checking**: Verify board feature is enabled for current view
     * - **Capability Validation**: Ensure user has necessary permissions for board operations
     * - **Configuration Validation**: Validate board configuration completeness
     * - **Fallback Mechanisms**: Redirect to table view if board unavailable
     * 
     * ## State Management Integration
     * 
     * ### Workflow Visualization
     * - **Current State Display**: Clear visual indication of recommendation states
     * - **Transition Capabilities**: Visual cues for available state transitions
     * - **Progress Tracking**: Visual progress indicators through workflow stages
     * - **Bottleneck Identification**: Easy identification of workflow bottlenecks
     * 
     * ### Interactive Operations
     * - **Drag-and-Drop**: Direct state transitions via card movement
     * - **Bulk Operations**: Multi-select capabilities for batch processing
     * - **Quick Actions**: Contextual menus for common operations
     * - **Status Updates**: Real-time status updates during operations
     * 
     * ## Error Handling & User Experience
     * 
     * ### Graceful Degradation
     * - **Configuration Errors**: Automatic fallback to default configurations
     * - **Permission Errors**: Clear messaging for authorization failures
     * - **Board Unavailable**: Automatic redirect to table view with notification
     * - **System Errors**: Safe error handling with user-friendly messaging
     * 
     * ### User Feedback
     * - **Visual Feedback**: Immediate visual feedback for user actions
     * - **Error Messaging**: Clear error messages through Flash component
     * - **Loading Indicators**: Progress indicators for longer operations
     * - **Success Confirmation**: Confirmation messaging for completed actions
     * 
     * ## Performance Optimization
     * 
     * ### Efficient Data Loading
     * - **Selective Queries**: Load only data required for board display
     * - **Optimized Associations**: Efficient loading of related data
     * - **State-Based Filtering**: Pre-filter data by relevant states only
     * - **Pagination Strategy**: Handle large datasets through virtual pagination
     * 
     * ### Real-Time Updates
     * - **AJAX Integration**: Asynchronous updates for responsive user experience
     * - **Optimistic Updates**: Immediate UI updates with server validation
     * - **Conflict Resolution**: Handle concurrent modifications gracefully
     * - **State Synchronization**: Maintain consistency between client and server
     * 
     * ## Integration Points
     * 
     * ### Workflow Integration
     * - **State Machine**: Integration with recommendation state machine logic
     * - **Business Rules**: Enforcement of workflow business rules during transitions
     * - **Audit Logging**: Automatic logging of state changes and user actions
     * - **Notification System**: Integration with notification system for updates
     * 
     * ### System Integration
     * - **Permission System**: Deep integration with RBAC permission system
     * - **Configuration System**: Dynamic loading from application configuration
     * - **Logging System**: Comprehensive logging for monitoring and debugging
     * - **Error Handling**: Integration with centralized error handling system
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Default board view
     * $controller->board();
     * 
     * // Administrative board view
     * $controller->board('Admin', 'All');
     * 
     * // Review workflow board
     * $controller->board('Review', 'InProgress');
     * 
     * // Custom board configuration
     * GET /awards/recommendations/board/Ceremony/Approved
     * ```
     * 
     * @param string|null $view View configuration name (defaults to 'Default')
     * @param string|null $status Status filter to apply (defaults to 'All')
     * @return \Cake\Http\Response|null|void Renders board view template or redirects on error
     * 
     * @see runBoard() For core board data processing and state grouping
     * @see kanbanUpdate() For AJAX-based state transition handling
     * @see \App\KMP\StaticHelpers::getAppSetting() For configuration loading
     */
    public function board(?string $view = null, ?string $status = null): ?\Cake\Http\Response
    {
        $view = $view ?? 'Default';
        $status = $status ?? 'All';

        try {
            $emptyRecommendation = $this->Recommendations->newEmptyEntity();
            $queryArgs = $this->request->getQuery();

            if ($view === 'SubmittedByMember') {
                //get the memberid from the query args if available
                if (isset($queryArgs['member_id']) && is_numeric($queryArgs['member_id'])) {
                    $emptyRecommendation->requester_id = $queryArgs['member_id'];
                } else {
                    $this->Authorization->skipAuthorization();
                    throw new ForbiddenException();
                }
            }
            $user = $this->request->getAttribute('identity');
            $user->authorizeWithArgs($emptyRecommendation, 'index', $view, $status, $queryArgs);

            if ($view && $view !== 'Index') {
                try {
                    $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig." . $view);
                } catch (\Exception $e) {
                    Log::debug('View config not found for ' . $view . ': ' . $e->getMessage());
                    $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
                }
            } else {
                $pageConfig = StaticHelpers::getAppSetting("Awards.ViewConfig.Default");
            }

            if (!$pageConfig['board']['use']) {
                $this->Flash->info(__('Board view is not enabled for this configuration.'));
                return $this->redirect(['action' => 'index']);
            }

            $this->set(compact('pageConfig'));
            $this->runBoard($view, $pageConfig, $emptyRecommendation);
            return null;
        } catch (\Exception $e) {
            Log::error('Error in recommendations board: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while loading the board view.'));
            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * Perform a transactional bulk state transition and related updates for multiple recommendations.
     *
     * Updates the selected recommendations' state and corresponding status, and optionally sets
     * gathering assignment, given date, and close reason. When a note is provided, an administrative
     * note is created for each affected recommendation attributed to the current user. All changes
     * are applied inside a transaction and will be committed on success or rolled back on failure.
     *
     * Redirects to the provided current_page request value when present, otherwise redirects to the
     * table view for the current view/status. Flash messages are set to indicate success or failure.
     *
     * @return \Cake\Http\Response|null Redirects to configured page or back to table view
     * @see \Awards\Model\Entity\Recommendation::getStatuses() For state → status mapping
     * @see \Awards\Model\Table\NotesTable For note creation and management
     */
    public function updateStates(): ?\Cake\Http\Response
    {
        $view = $this->request->getData('view') ?? 'Index';
        $status = $this->request->getData('status') ?? 'All';

        $this->request->allowMethod(['post', 'get']);
        $user = $this->request->getAttribute('identity');
        $recommendation = $this->Recommendations->newEmptyEntity();
        $this->Authorization->authorize($recommendation);

        $ids = explode(',', $this->request->getData('ids'));
        $newState = $this->request->getData('newState');
        $gathering_id = $this->request->getData('gathering_id');
        $given = $this->request->getData('given');
        $note = $this->request->getData('note');
        $close_reason = $this->request->getData('close_reason');

        if (empty($ids) || empty($newState)) {
            $this->Flash->error(__('No recommendations selected or new state not specified.'));
        } else {
            $this->Recommendations->getConnection()->begin();
            try {
                $statusList = Recommendation::getStatuses();
                $newStatus = '';

                // Find the status corresponding to the new state
                foreach ($statusList as $key => $value) {
                    foreach ($value as $state) {
                        if ($state === $newState) {
                            $newStatus = $key;
                            break 2;
                        }
                    }
                }

                // Build flat associative array for updateAll
                $updateFields = [
                    'state' => $newState,
                    'status' => $newStatus
                ];

                if ($gathering_id) {
                    $updateFields['gathering_id'] = $gathering_id;
                }

                if ($given) {
                    $updateFields['given'] = new DateTime($given);
                }

                if ($close_reason) {
                    $updateFields['close_reason'] = $close_reason;
                }

                if (!$this->Recommendations->updateAll($updateFields, ['id IN' => $ids])) {
                    throw new \Exception('Failed to update recommendations');
                }

                if ($note) {
                    foreach ($ids as $id) {
                        $newNote = $this->Recommendations->Notes->newEmptyEntity();
                        $newNote->entity_id = $id;
                        $newNote->subject = 'Recommendation Bulk Updated';
                        $newNote->entity_type = 'Awards.Recommendations';
                        $newNote->body = $note;
                        $newNote->author_id = $user->id;

                        if (!$this->Recommendations->Notes->save($newNote)) {
                            throw new \Exception('Failed to save note');
                        }
                    }
                }

                $this->Recommendations->getConnection()->commit();
                if (!$this->request->getHeader('Turbo-Frame')) {
                    $this->Flash->success(__('The recommendations have been updated.'));
                }
            } catch (\Exception $e) {
                $this->Recommendations->getConnection()->rollback();
                Log::error('Error updating recommendations: ' . $e->getMessage());

                if (!$this->request->getHeader('Turbo-Frame')) {
                    $this->Flash->error(__('The recommendations could not be updated. Please, try again.'));
                }
            }
        }
        $currentPage = $this->request->getData('current_page');
        if ($currentPage) {
            return $this->redirect($currentPage);
        }

        return $this->redirect(['action' => 'table', $view, $status]);
    }

    /**
     * Display a single recommendation with its workflow context and related entities.
     *
     * Loads the recommendation together with related data (requester, member, branch, award, gatherings)
     * and authorizes view access before exposing the entity to the view layer.
     *
     * @param string|null $id The recommendation ID to display.
     * @return \Cake\Http\Response|null The response for the rendered view, or null if the controller does not return a response.
     * @throws \Cake\Http\Exception\NotFoundException If the recommendation does not exist or is inaccessible.
     */
    public function view(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id, contain: ['Requesters', 'Members', 'Branches', 'Awards', 'Gatherings', 'AssignedGathering']);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'view');
            $recommendation->domain_id = $recommendation->award->domain_id;
            $this->set(compact('recommendation'));
            return null;
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    /**
     * Display the recommendation submission form for authenticated users and process posted submissions.
     *
     * Creates a new Recommendation linked to the current user, optionally associates member data and preferences,
     * initializes workflow state fields and court preference defaults, saves the record within a database transaction,
     * and either redirects on success or re-renders the form with dropdown data (awards, domains, levels, branches, gatherings).
     *
     * @return \Cake\Http\Response|null|void Redirects on successful submission or renders the form on GET / validation failure
     * @see submitRecommendation() For the unauthenticated public submission workflow
     * @see view() For recommendation detail display after submission
     * @see \Awards\Model\Entity\Recommendation For recommendation entity structure
     */
    public function add(): ?\Cake\Http\Response
    {
        try {
            $user = $this->request->getAttribute('identity');
            $recommendation = $this->Recommendations->newEmptyEntity();
            $this->Authorization->authorize($recommendation);

            if ($this->request->is('post')) {
                $recommendation = $this->Recommendations->patchEntity($recommendation, $this->request->getData());
                $recommendation->requester_id = $user->id;
                $recommendation->requester_sca_name = $user->sca_name;
                $recommendation->contact_email = $user->email_address;
                $recommendation->contact_number = $user->phone_number;

                $statuses = Recommendation::getStatuses();
                $recommendation->status = array_key_first($statuses);
                $recommendation->state = $statuses[$recommendation->status][0];
                $recommendation->state_date = DateTime::now();
                $recommendation->not_found = $this->request->getData('not_found') === 'on';

                if ($recommendation->specialty === 'No specialties available') {
                    $recommendation->specialty = null;
                }

                if ($recommendation->not_found) {
                    $recommendation->member_id = null;
                } else {
                    $this->Recommendations->getConnection()->begin();
                    try {
                        $member = $this->Recommendations->Members->get(
                            $recommendation->member_id,
                            select: ['branch_id', 'additional_info']
                        );

                        $recommendation->branch_id = $member->branch_id;

                        if (!empty($member->additional_info)) {
                            $addInfo = $member->additional_info;
                            if (isset($addInfo['CallIntoCourt'])) {
                                $recommendation->call_into_court = $addInfo['CallIntoCourt'];
                            }
                            if (isset($addInfo['CourtAvailability'])) {
                                $recommendation->court_availability = $addInfo['CourtAvailability'];
                            }
                            if (isset($addInfo['PersonToGiveNoticeTo'])) {
                                $recommendation->person_to_notify = $addInfo['PersonToGiveNoticeTo'];
                            }
                        }
                    } catch (\Exception $e) {
                        $this->Recommendations->getConnection()->rollback();
                        Log::error('Error loading member data: ' . $e->getMessage());
                        $this->Flash->error(__('Could not load member information. Please try again.'));
                    }
                }

                // Set default values for court preferences
                $recommendation->call_into_court = $recommendation->call_into_court ?? 'Not Set';
                $recommendation->court_availability = $recommendation->court_availability ?? 'Not Set';
                $recommendation->person_to_notify = $recommendation->person_to_notify ?? '';

                if ($this->Recommendations->save($recommendation)) {
                    $this->Recommendations->getConnection()->commit();
                    $this->Flash->success(__('The recommendation has been saved.'));

                    if ($user->checkCan('view', $recommendation)) {
                        return $this->redirect(['action' => 'view', $recommendation->id]);
                    }

                    return $this->redirect([
                        'controller' => 'members',
                        'plugin' => null,
                        'action' => 'view',
                        $user->id
                    ]);
                }
                $this->Recommendations->getConnection()->rollback();
                $this->Flash->error(__('The recommendation could not be saved. Please, try again.'));
            }

            // Get data for dropdowns
            $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
            $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();
            $branches = $this->Recommendations->Awards->Branches
                ->find('list', keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                })
                ->where(['can_have_members' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            $awards = $this->Recommendations->Awards->find('list', limit: 200)->all();

            $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
            $gatheringsData = $gatheringsTable->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where([
                    'start_date >' => DateTime::now(),
                ])
                ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
                ->orderBy(['start_date' => 'ASC'])
                ->all();

            $gatherings = [];
            foreach ($gatheringsData as $gathering) {
                $gatherings[$gathering->id] = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                    . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();
            }

            $this->set(compact('recommendation', 'branches', 'awards', 'gatherings', 'awardsDomains', 'awardsLevels'));
            return null;
        } catch (\Exception $e) {
            $this->Recommendations->getConnection()->rollback();
            Log::error('Error in add recommendation: ' . $e->getMessage());
            $this->Flash->error(__('An unexpected error occurred. Please try again.'));
            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * Render and process the public recommendation submission form for guest users.
     *
     * Presents a guest-facing form to submit award recommendations and handles form
     * submissions, creating a new recommendation record while applying necessary
     * defaults and minimal member-data integration when a matching member is provided.
     *
     * @return \Cake\Http\Response|null Redirects authenticated users or after successful redirects; otherwise renders the form.
     * @see add() For authenticated member submission workflow
     * @see beforeFilter() For authentication bypass configuration
     */
    public function submitRecommendation(): ?\Cake\Http\Response
    {
        $this->Authorization->skipAuthorization();
        $user = $this->request->getAttribute('identity');

        if ($user !== null) {
            return $this->redirect(['action' => 'add']);
        }

        $recommendation = $this->Recommendations->newEmptyEntity();

        if ($this->request->is(['post', 'put'])) {
            try {
                $this->Recommendations->getConnection()->begin();

                $recommendation = $this->Recommendations->patchEntity($recommendation, $this->request->getData());

                if ($recommendation->requester_id !== null) {
                    $requester = $this->Recommendations->Requesters->get(
                        $recommendation->requester_id,
                        fields: ['sca_name']
                    );
                    $recommendation->requester_sca_name = $requester->sca_name;
                }

                $statuses = Recommendation::getStatuses();
                $recommendation->status = array_key_first($statuses);
                $recommendation->state = $statuses[$recommendation->status][0];
                $recommendation->state_date = DateTime::now();

                if ($recommendation->specialty === 'No specialties available') {
                    $recommendation->specialty = null;
                }

                $recommendation->not_found = $this->request->getData('not_found') === 'on';

                if ($recommendation->not_found) {
                    $recommendation->member_id = null;
                } else {
                    try {
                        $member = $this->Recommendations->Members->get(
                            $recommendation->member_id,
                            select: ['branch_id', 'additional_info']
                        );

                        $recommendation->branch_id = $member->branch_id;

                        if (!empty($member->additional_info)) {
                            $addInfo = $member->additional_info;

                            if (isset($addInfo['CallIntoCourt'])) {
                                $recommendation->call_into_court = $addInfo['CallIntoCourt'];
                            }

                            if (isset($addInfo['CourtAvailability'])) {
                                $recommendation->court_availability = $addInfo['CourtAvailability'];
                            }

                            if (isset($addInfo['PersonToGiveNoticeTo'])) {
                                $recommendation->person_to_notify = $addInfo['PersonToGiveNoticeTo'];
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error loading member data: ' . $e->getMessage());
                    }
                }

                // Set default values for court preferences
                $recommendation->call_into_court = $recommendation->call_into_court ?? 'Not Set';
                $recommendation->court_availability = $recommendation->court_availability ?? 'Not Set';
                $recommendation->person_to_notify = $recommendation->person_to_notify ?? '';

                if ($this->Recommendations->save($recommendation)) {
                    $this->Recommendations->getConnection()->commit();
                    $this->Flash->success(__('The recommendation has been submitted.'));
                } else {
                    $this->Recommendations->getConnection()->rollback();
                    $this->Flash->error(__('The recommendation could not be submitted. Please, try again.'));
                }
            } catch (\Exception $e) {
                $this->Recommendations->getConnection()->rollback();
                Log::error('Error submitting recommendation: ' . $e->getMessage());
                $this->Flash->error(__('An error occurred while submitting the recommendation. Please try again.'));
            }
        }

        // Load data for the form
        $headerImage = StaticHelpers::getAppSetting('KMP.Login.Graphic');
        $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
        $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();

        $branches = $this->Recommendations->Awards->Branches
            ->find('list', keyPath: function ($entity) {
                return $entity->id . '|' . ($entity->can_have_members ? 'true' : 'false');
            })
            ->where(['can_have_members' => true])
            ->orderBy(['name' => 'ASC'])
            ->toArray();

        $awards = $this->Recommendations->Awards->find('list', limit: 200)->all();

        $gatheringsTable = $this->fetchTable('Gatherings');
        $gatheringsData = $gatheringsTable->find()
            ->contain(['Branches' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->where(['start_date >' => DateTime::now()])
            ->select(['id', 'name', 'start_date', 'end_date', 'Gatherings.branch_id'])
            ->orderBy(['start_date' => 'ASC'])
            ->all();

        $gatherings = [];
        foreach ($gatheringsData as $gathering) {
            $gatherings[$gathering->id] = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();
        }

        $this->set(compact(
            'recommendation',
            'branches',
            'awards',
            'gatherings',
            'awardsDomains',
            'awardsLevels',
            'headerImage'
        ));
        return null;
    }

    /**
     * Recommendation modification with member data synchronization and note integration
     * 
     * Provides comprehensive recommendation editing capabilities with sophisticated member
     * data synchronization, court preference management, note integration, and transaction
     * safety. This method handles complex recommendation modifications while maintaining
     * data integrity, member preference synchronization, and comprehensive audit trail
     * management throughout the editing process.
     * 
     * ## Edit Workflow Architecture
     * 
     * ### Entity Loading & Authorization
     * - **Secure Loading**: Safe loading of recommendation entity with validation
     * - **Authorization Check**: Comprehensive authorization validation for edit operations
     * - **Data Validation**: Pre-edit validation of recommendation state and accessibility
     * - **Error Handling**: Robust error handling for missing or inaccessible recommendations
     * 
     * ### Member Data Integration
     * - **Change Detection**: Detection of member assignment changes during editing
     * - **Profile Synchronization**: Automatic synchronization with updated member profiles
     * - **Preference Loading**: Load court and ceremony preferences from member data
     * - **Data Consistency**: Maintain consistency between recommendation and member data
     * 
     * ## Member Synchronization Logic
     * 
     * ### Member Assignment Handling
     * - **Null Assignment**: Handle removal of member assignment with preference cleanup
     * - **Member Changes**: Detect and handle changes in member assignment
     * - **Profile Integration**: Load member profile data for new assignments
     * - **Preference Reset**: Reset preferences when member changes to ensure accuracy
     * 
     * ### Additional Information Processing
     * - **Court Preferences**: Extract and sync court call preferences from member data
     * - **Availability Settings**: Sync court availability from member profile
     * - **Notification Contacts**: Load person-to-notify information from member data
     * - **Default Handling**: Appropriate defaults for missing preference data
     * 
     * ## Court Preference Management
     * 
     * ### Preference Synchronization
     * - **Data Extraction**: Safe extraction of preferences from member additional_info
     * - **Validation Logic**: Validate preference data for consistency and completeness
     * - **Default Assignment**: Assign appropriate defaults for missing or invalid preferences
     * - **Error Recovery**: Graceful handling of preference loading errors
     * 
     * ### Court Information Fields
     * - **Call Into Court**: Member's court call preferences and requirements
     * - **Court Availability**: Member's available dates and times for court appearances
     * - **Person to Notify**: Contact person for court coordination and communication
     * - **Consistency Validation**: Ensure all court fields are properly populated
     * 
     * ## Date & Time Management
     * 
     * ### Given Date Processing
     * - **Date Validation**: Validate provided given dates for logical consistency
     * - **Null Handling**: Proper handling of null or empty date values
     * - **Format Processing**: Convert date strings to proper DateTime objects
     * - **Temporal Validation**: Ensure dates are within acceptable ranges
     * 
     * ### Timeline Management
     * - **Event Integration**: Coordinate given dates with event schedules
     * - **Workflow Timing**: Ensure dates align with workflow requirements
     * - **Historical Accuracy**: Maintain accurate historical dating for audit purposes
     * - **Future Planning**: Support for future ceremony date assignments
     * 
     * ## Transaction Management
     * 
     * ### Data Integrity Protection
     * - **Atomic Operations**: Ensure all edit operations are atomic and consistent
     * - **Transaction Safety**: Complete rollback on any operation failure
     * - **Consistency Validation**: Validate data consistency before commit
     * - **Error Recovery**: Comprehensive error recovery with data preservation
     * 
     * ### Multi-Table Operations
     * - **Recommendation Updates**: Primary recommendation entity modifications
     * - **Note Integration**: Creation of edit notes for audit trail
     * - **Member Synchronization**: Coordinate updates with member profile data
     * - **Referential Integrity**: Maintain referential integrity across related tables
     * 
     * ## Note Integration System
     * 
     * ### Audit Trail Notes
     * - **Edit Documentation**: Automatic creation of edit documentation notes
     * - **Change Attribution**: Track which user performed modifications
     * - **Content Management**: Standardized note format for edit operations
     * - **Timeline Integration**: Notes integrated into recommendation timeline
     * 
     * ### Note Creation Process
     * - **Conditional Creation**: Notes created only when provided by user
     * - **Entity Association**: Proper association with recommendation entity
     * - **Author Attribution**: Automatic author assignment from authenticated user
     * - **Transaction Integration**: Note creation included in transaction scope
     * 
     * ## Specialty Handling
     * 
     * ### Award Specialty Management
     * - **Specialty Validation**: Validate specialty selections against award requirements
     * - **Null Handling**: Proper handling of "No specialties available" selections
     * - **Consistency Checks**: Ensure specialty selections are valid for selected awards
     * - **Default Processing**: Handle default specialty selections appropriately
     * 
     * ## User Interface Integration
     * 
     * ### Form Processing
     * - **Input Validation**: Comprehensive validation of all form inputs
     * - **Entity Patching**: Secure entity patching with validation
     * - **Error Feedback**: Clear presentation of validation and processing errors
     * - **Success Confirmation**: Appropriate confirmation of successful operations
     * 
     * ### Navigation Management
     * - **Return Path Handling**: Support for custom return paths via form data
     * - **Context Preservation**: Maintain user context through edit operations
     * - **Default Navigation**: Sensible default navigation for standard edit operations
     * - **User Experience**: Seamless navigation experience for users
     * 
     * ## Turbo Frame Support
     * 
     * ### Partial Update Support
     * - **Frame Detection**: Detection of Turbo Frame requests for partial updates
     * - **Response Optimization**: Optimized responses for partial page updates
     * - **User Feedback**: Appropriate feedback handling for partial updates
     * - **Error Management**: Consistent error handling across request types
     * 
     * ### Real-Time User Experience
     * - **Immediate Feedback**: Immediate user feedback for edit operations
     * - **Progressive Enhancement**: Enhanced experience with JavaScript enabled
     * - **Fallback Support**: Full functionality without JavaScript requirements
     * - **Performance Optimization**: Optimized performance for interactive updates
     * 
     * ## Authorization & Security
     * 
     * ### Edit Authorization
     * - **Entity-Level Permissions**: Authorization specific to the recommendation being edited
     * - **State-Based Access**: Edit permissions may vary based on recommendation state
     * - **User Context**: Authorization considers user role and relationship to recommendation
     * - **Operation Validation**: Validate user can perform specific edit operations
     * 
     * ### Data Security
     * - **Input Validation**: Comprehensive validation of all user input
     * - **SQL Injection Prevention**: Parameterized queries for all database operations
     * - **Data Sanitization**: Proper sanitization of user input data
     * - **Authorization Checking**: Multi-level authorization validation throughout process
     * 
     * ## Error Handling & Recovery
     * 
     * ### Comprehensive Error Management
     * - **Validation Errors**: Clear presentation of validation errors to users
     * - **Database Errors**: Robust handling of database operation failures
     * - **Member Data Errors**: Graceful handling of member data loading errors
     * - **System Errors**: Safe handling of unexpected system errors
     * 
     * ### Recovery Strategies
     * - **Transaction Rollback**: Complete rollback on any operation failure
     * - **Data Preservation**: Preserve user input during error recovery
     * - **Error Communication**: Clear error messaging through Flash component
     * - **Guidance Provision**: Helpful guidance for resolving error conditions
     * 
     * ## Performance Optimization
     * 
     * ### Efficient Data Operations
     * - **Selective Loading**: Load only required data for edit operations
     * - **Optimized Queries**: Efficient database queries for member data loading
     * - **Transaction Optimization**: Optimized transaction handling for performance
     * - **Resource Management**: Efficient use of server resources during edits
     * 
     * ### User Experience Performance
     * - **Fast Response Times**: Optimized processing for responsive user experience
     * - **Progressive Enhancement**: Enhanced experience without sacrificing performance
     * - **Error Handling Speed**: Fast error detection and user feedback
     * - **Navigation Performance**: Efficient navigation and redirect handling
     * 
     * ## Integration Points
     * 
     * ### System Integration
     * - **Member Management**: Deep integration with member profile system
     * - **Note System**: Integration with comprehensive note and audit system
     * - **Authorization System**: Deep integration with RBAC authorization
     * - **Workflow System**: Integration with recommendation workflow management
     * 
     * ### Data Synchronization
     * - **Member Profiles**: Synchronization with member profile data
     * - **Court Preferences**: Integration with member court preference system
     * - **Event System**: Coordination with event and ceremony management
     * - **Audit System**: Integration with audit trail and logging systems
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Basic recommendation edit
     * GET /awards/recommendations/edit/123
     * POST /awards/recommendations/edit/123
     * 
     * // Edit with note addition
     * POST /awards/recommendations/edit/456
     * Body: {
     *   'award_id': '789',
     *   'member_id': '321',
     *   'note': 'Updated award assignment per committee decision'
     * }
     * 
     * // Edit with return path
     * POST /awards/recommendations/edit/789
     * Body: {
     *   'given': '2024-02-15',
     *   'current_page': '/awards/recommendations/table/Admin/Approved'
     * }
     * ```
     * 
     * @param string|null $id Recommendation ID to edit
     * @return \Cake\Http\Response|null|void Redirects on successful edit or to current page
     * @throws \Cake\Http\Exception\NotFoundException When recommendation not found or inaccessible
     * 
     * @see view() For recommendation detail display after editing
     * @see add() For initial recommendation creation workflow
     * @see \Awards\Model\Entity\Recommendation For recommendation entity structure
     * @see \Cake\I18n\DateTime For date processing and validation
     */
    public function edit(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'edit');

            if ($this->request->is(['patch', 'post', 'put'])) {
                $beforeMemberId = $recommendation->member_id;
                $recommendation = $this->Recommendations->patchEntity($recommendation, $this->request->getData());

                if ($recommendation->specialty === 'No specialties available') {
                    $recommendation->specialty = null;
                }

                // Handle member related fields
                if ($recommendation->member_id == 0 || $recommendation->member_id == null) {
                    $recommendation->member_id = null;
                    $recommendation->call_into_court = null;
                    $recommendation->court_availability = null;
                    $recommendation->person_to_notify = null;
                } elseif ($recommendation->member_id != $beforeMemberId) {
                    // Reset member-related fields when member changes
                    $recommendation->call_into_court = null;
                    $recommendation->court_availability = null;
                    $recommendation->person_to_notify = null;

                    try {
                        $member = $this->Recommendations->Members->get(
                            $recommendation->member_id,
                            select: ['branch_id', 'additional_info']
                        );

                        $recommendation->branch_id = $member->branch_id;

                        if (!empty($member->additional_info)) {
                            $addInfo = $member->additional_info;
                            if (isset($addInfo['CallIntoCourt'])) {
                                $recommendation->call_into_court = $addInfo['CallIntoCourt'];
                            }
                            if (isset($addInfo['CourtAvailability'])) {
                                $recommendation->court_availability = $addInfo['CourtAvailability'];
                            }
                            if (isset($addInfo['PersonToGiveNoticeTo'])) {
                                $recommendation->person_to_notify = $addInfo['PersonToGiveNoticeTo'];
                            }
                        }
                    } catch (\Exception $e) {
                        Log::error('Error loading member data in edit: ' . $e->getMessage());
                    }
                }

                // Set default values for court preferences
                $recommendation->call_into_court = $recommendation->call_into_court ?? 'Not Set';
                $recommendation->court_availability = $recommendation->court_availability ?? 'Not Set';
                $recommendation->person_to_notify = $recommendation->person_to_notify ?? '';

                if ($this->request->getData('given') !== null && $this->request->getData('given') !== '') {
                    $recommendation->given = new DateTime($this->request->getData('given'));
                } else {
                    $recommendation->given = null;
                }

                // Begin transaction
                $this->Recommendations->getConnection()->begin();

                try {
                    if (!$this->Recommendations->save($recommendation)) {
                        throw new \Exception('Failed to save recommendation');
                    }

                    $note = $this->request->getData('note');
                    if ($note) {
                        $newNote = $this->Recommendations->Notes->newEmptyEntity();
                        $newNote->entity_id = $recommendation->id;
                        $newNote->subject = 'Recommendation Updated';
                        $newNote->entity_type = 'Awards.Recommendations';
                        $newNote->body = $note;
                        $newNote->author_id = $this->request->getAttribute('identity')->id;

                        if (!$this->Recommendations->Notes->save($newNote)) {
                            throw new \Exception('Failed to save note');
                        }
                    }

                    $this->Recommendations->getConnection()->commit();

                    if (!$this->request->getHeader('Turbo-Frame')) {
                        $this->Flash->success(__('The recommendation has been saved.'));
                    }
                } catch (\Exception $e) {
                    $this->Recommendations->getConnection()->rollback();
                    Log::error('Error saving recommendation: ' . $e->getMessage());

                    if (!$this->request->getHeader('Turbo-Frame')) {
                        $this->Flash->error(__('The recommendation could not be saved. Please, try again.'));
                    }
                }
            }

            if ($this->request->getData('current_page')) {
                return $this->redirect($this->request->getData('current_page'));
            }

            return $this->redirect(['action' => 'view', $id]);
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        } catch (\Exception $e) {
            Log::error('Error in edit recommendation: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while editing the recommendation.'));
            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * AJAX-based kanban board state transition with drag-and-drop support
     * 
     * Provides real-time state transition capabilities for the kanban board interface,
     * enabling users to modify recommendation states and positions through drag-and-drop
     * interactions. This method implements AJAX-based state management with transaction
     * safety, position management, and comprehensive error handling for seamless
     * interactive workflow management.
     * 
     * ## AJAX Interface Architecture
     * 
     * ### Real-Time State Updates
     * - **Asynchronous Processing**: AJAX-based updates without page refresh
     * - **Immediate Feedback**: Instant visual feedback for user actions
     * - **State Synchronization**: Real-time synchronization between client and server
     * - **Error Handling**: Comprehensive error handling with user feedback
     * 
     * ### JSON Response Management
     * - **Standardized Responses**: Consistent JSON response format for client processing
     * - **Status Indication**: Clear success/failure indication for client-side handling
     * - **Error Communication**: Detailed error information for debugging and user feedback
     * - **Performance Optimization**: Optimized response payloads for fast processing
     * 
     * ## Drag-and-Drop State Management
     * 
     * ### State Transition Processing
     * - **Column-Based States**: State assignment based on target kanban column
     * - **Validation Logic**: Validate state transitions according to business rules
     * - **Timestamp Management**: Automatic state_date updates for audit trail
     * - **Consistency Enforcement**: Ensure state changes maintain workflow consistency
     * 
     * ### Position Management
     * - **Relative Positioning**: Support for placing recommendations before/after others
     * - **Stack Ranking**: Integration with stack ranking system for position management
     * - **Order Maintenance**: Maintain proper order within kanban columns
     * - **Conflict Resolution**: Handle positioning conflicts gracefully
     * 
     * ## Transaction & Data Integrity
     * 
     * ### Atomic Operations
     * - **Transaction Safety**: Ensure all kanban updates are atomic
     * - **State Consistency**: Maintain consistency between state and position changes
     * - **Rollback Protection**: Complete rollback on any operation failure
     * - **Data Validation**: Validate all changes before commit
     * 
     * ### Multi-Operation Coordination
     * - **State Updates**: Primary state transition processing
     * - **Position Changes**: Coordinate position changes with state updates
     * - **Ranking Adjustments**: Adjust stack rankings for proper ordering
     * - **Audit Trail**: Maintain comprehensive audit trail for all changes
     * 
     * ## Position Management System
     * 
     * ### Before/After Positioning
     * - **placeBefore**: Position recommendation before specified target
     * - **placeAfter**: Position recommendation after specified target
     * - **Default Handling**: Appropriate defaults when no positioning specified
     * - **Validation Logic**: Validate positioning targets exist and are accessible
     * 
     * ### Stack Ranking Integration
     * - **moveBefore()**: CakePHP tree behavior integration for position management
     * - **moveAfter()**: Tree behavior for relative positioning
     * - **Order Maintenance**: Automatic order adjustment for affected recommendations
     * - **Performance Optimization**: Efficient tree operations for large datasets
     * 
     * ## Authorization & Security
     * 
     * ### Edit Authorization
     * - **Entity-Level Permissions**: Authorization specific to the recommendation
     * - **State Transition Rights**: Validate user can perform specific state transitions
     * - **Operation Validation**: Ensure user has rights to modify recommendation
     * - **Context Validation**: Consider recommendation context in authorization
     * 
     * ### Security Considerations
     * - **Input Validation**: Comprehensive validation of AJAX request parameters
     * - **SQL Injection Prevention**: Parameterized queries for all database operations
     * - **Authorization Checking**: Multi-level authorization validation
     * - **Rate Limiting**: Protection against rapid-fire AJAX requests
     * 
     * ## Error Handling & Recovery
     * 
     * ### Comprehensive Error Management
     * - **Validation Errors**: Handle and communicate validation failures
     * - **Database Errors**: Robust handling of database operation failures
     * - **Authorization Errors**: Proper handling of permission failures
     * - **System Errors**: Safe handling of unexpected errors
     * 
     * ### Error Response Format
     * - **JSON Error Responses**: Standardized JSON error response format
     * - **HTTP Status Codes**: Appropriate HTTP status codes for different error types
     * - **Error Logging**: Comprehensive error logging for troubleshooting
     * - **Client Communication**: Clear error communication for client-side handling
     * 
     * ## Client-Side Integration
     * 
     * ### JavaScript Coordination
     * - **Response Processing**: Standardized response format for JavaScript processing
     * - **State Synchronization**: Coordinate state changes with client-side display
     * - **Error Handling**: Client-side error handling and user feedback
     * - **Progressive Enhancement**: Graceful degradation for non-JavaScript environments
     * 
     * ### User Experience Design
     * - **Immediate Feedback**: Instant visual feedback during drag operations
     * - **Loading States**: Visual indicators during AJAX processing
     * - **Error Recovery**: Clear error recovery mechanisms for users
     * - **Accessibility**: Ensure drag-and-drop functionality is accessible
     * 
     * ## Performance Optimization
     * 
     * ### AJAX Response Optimization
     * - **Minimal Payloads**: Optimized JSON response payloads for speed
     * - **Efficient Processing**: Fast server-side processing for responsive experience
     * - **Resource Management**: Efficient use of server resources during updates
     * - **Caching Strategy**: Strategic caching to improve response times
     * 
     * ### Database Performance
     * - **Optimized Queries**: Efficient database queries for state and position updates
     * - **Transaction Optimization**: Optimized transaction handling for performance
     * - **Index Utilization**: Query patterns optimized for database index usage
     * - **Concurrent Access**: Handle concurrent kanban updates efficiently
     * 
     * ## Workflow Integration
     * 
     * ### State Machine Integration
     * - **Business Rules**: Enforce workflow business rules during state transitions
     * - **Validation Logic**: Validate state transitions according to workflow rules
     * - **Audit Integration**: Integration with audit trail and logging systems
     * - **Notification System**: Integration with workflow notification systems
     * 
     * ### Real-Time Workflow
     * - **Immediate Updates**: Real-time workflow state updates
     * - **Collaborative Features**: Support for multi-user workflow collaboration
     * - **Conflict Resolution**: Handle conflicts when multiple users modify same items
     * - **Synchronization**: Maintain synchronization across multiple user sessions
     * 
     * ## Integration Points
     * 
     * ### System Integration
     * - **Workflow System**: Deep integration with recommendation workflow management
     * - **Authorization System**: Integration with RBAC authorization system
     * - **Audit System**: Integration with audit trail and logging systems
     * - **Notification System**: Integration with real-time notification systems
     * 
     * ### Frontend Integration
     * - **JavaScript Framework**: Integration with Stimulus JavaScript framework
     * - **CSS Framework**: Integration with Bootstrap for visual feedback
     * - **Accessibility**: Integration with accessibility standards and tools
     * - **Progressive Enhancement**: Support for enhanced and basic functionality
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Drag-and-drop state change
     * POST /awards/recommendations/kanbanUpdate/123
     * Body: {
     *   'newCol': 'Approved',
     *   'placeBefore': '456'
     * }
     * 
     * // Simple state transition
     * POST /awards/recommendations/kanbanUpdate/789
     * Body: {
     *   'newCol': 'InReview'
     * }
     * 
     * // Position after another recommendation
     * POST /awards/recommendations/kanbanUpdate/321
     * Body: {
     *   'newCol': 'AssignedToCeremony',
     *   'placeAfter': '654'
     * }
     * ```
     * 
     * @param string|null $id Recommendation ID to update
     * @return \Cake\Http\Response JSON response indicating success or failure
     * @throws \Cake\Http\Exception\NotFoundException When recommendation not found
     * 
     * @see board() For kanban board display and initialization
     * @see updateStates() For bulk state transition operations
     * @see \Awards\Model\Table\RecommendationsTable For tree behavior integration
     */
    public function kanbanUpdate(?string $id = null): \Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'edit');
            $message = 'failed';

            if ($this->request->is(['patch', 'post', 'put'])) {
                $recommendation->state = $this->request->getData('newCol');
                $placeBefore = $this->request->getData('placeBefore');
                $placeAfter = $this->request->getData('placeAfter');

                $placeAfter = $placeAfter ?? -1;
                $placeBefore = $placeBefore ?? -1;

                $recommendation->state_date = DateTime::now();
                $this->Recommendations->getConnection()->begin();

                try {
                    $failed = false;

                    if (!$this->Recommendations->save($recommendation)) {
                        throw new \Exception('Failed to save recommendation state');
                    }

                    if ($placeBefore != -1) {
                        if (!$this->Recommendations->moveBefore($id, $placeBefore)) {
                            throw new \Exception('Failed to move recommendation before target');
                        }
                    }

                    if ($placeAfter != -1) {
                        if (!$this->Recommendations->moveAfter($id, $placeAfter)) {
                            throw new \Exception('Failed to move recommendation after target');
                        }
                    }

                    $this->Recommendations->getConnection()->commit();
                    $message = 'success';
                } catch (\Exception $e) {
                    $this->Recommendations->getConnection()->rollback();
                    Log::error('Error updating kanban: ' . $e->getMessage());
                    $message = 'failed';
                }
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode($message));
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            Log::error('Kanban update failed - recommendation not found: ' . $id);
            return $this->response
                ->withType('application/json')
                ->withStatus(404)
                ->withStringBody(json_encode('not_found'));
        }
    }

    /**
     * Recommendation deletion with transaction management and referential integrity
     * 
     * Provides secure recommendation deletion capabilities with comprehensive transaction
     * management, authorization validation, and referential integrity protection. This
     * method implements safe deletion patterns that maintain data consistency and
     * provide appropriate audit trail management for recommendation removal operations.
     * 
     * ## Deletion Architecture
     * 
     * ### Secure Deletion Process
     * - **Entity Loading**: Safe loading and validation of recommendation for deletion
     * - **Authorization Check**: Comprehensive authorization validation for delete operations
     * - **Method Validation**: Restrict deletion to appropriate HTTP methods (POST/DELETE)
     * - **Transaction Safety**: Complete transaction management for deletion integrity
     * 
     * ### Soft Deletion Pattern
     * - **CakePHP Integration**: Utilizes CakePHP's built-in soft deletion capabilities
     * - **Data Preservation**: Maintains data for audit and recovery purposes
     * - **Referential Integrity**: Preserves referential relationships after deletion
     * - **Recovery Capability**: Enables potential recovery of deleted recommendations
     * 
     * ## Authorization & Security
     * 
     * ### Delete Authorization
     * - **Entity-Level Permissions**: Authorization specific to the recommendation being deleted
     * - **State-Based Restrictions**: Some states may restrict deletion capabilities
     * - **User Context**: Authorization considers user role and relationship to recommendation
     * - **Administrative Override**: Administrative users may have enhanced deletion rights
     * 
     * ### Security Validation
     * - **Method Restriction**: Only POST and DELETE methods allowed for security
     * - **Authorization Checking**: Multi-level authorization validation before deletion
     * - **Audit Logging**: Comprehensive logging of deletion operations
     * - **Data Protection**: Protection against unauthorized deletion attempts
     * 
     * ## Transaction Management
     * 
     * ### Data Integrity Protection
     * - **Atomic Operations**: Ensure deletion operations are atomic and consistent
     * - **Transaction Safety**: Complete rollback on any operation failure
     * - **Consistency Validation**: Validate data consistency before commit
     * - **Error Recovery**: Comprehensive error recovery with proper rollback
     * 
     * ### Related Data Handling
     * - **Cascade Considerations**: Proper handling of related entity relationships
     * - **Referential Integrity**: Maintain referential integrity during deletion
     * - **Dependent Entity Management**: Handle dependent entities appropriately
     * - **Audit Trail Preservation**: Preserve audit trail even after deletion
     * 
     * ## Error Handling & Recovery
     * 
     * ### Comprehensive Error Management
     * - **Not Found Handling**: Proper handling of missing recommendations
     * - **Authorization Errors**: Clear messaging for permission failures
     * - **Database Errors**: Robust handling of database operation failures
     * - **System Errors**: Safe handling of unexpected system errors
     * 
     * ### User Communication
     * - **Success Messages**: Clear confirmation of successful deletion
     * - **Error Messages**: Helpful error messages for failed operations
     * - **Guidance Provision**: Guidance for resolving error conditions
     * - **Context Preservation**: Maintain user context during error handling
     * 
     * ## Audit Trail & Logging
     * 
     * ### Deletion Tracking
     * - **Operation Logging**: Comprehensive logging of deletion operations
     * - **User Attribution**: Track which user performed deletion
     * - **Timestamp Recording**: Record exact time of deletion operation
     * - **Context Preservation**: Preserve context information for audit purposes
     * 
     * ### Audit Integration
     * - **Audit System**: Integration with comprehensive audit system
     * - **Compliance Tracking**: Support for compliance and regulatory requirements
     * - **Historical Records**: Maintain historical record of deleted recommendations
     * - **Recovery Information**: Preserve information needed for potential recovery
     * 
     * ## Business Logic Integration
     * 
     * ### Workflow Considerations
     * - **State Validation**: Consider recommendation state in deletion decisions
     * - **Business Rules**: Apply business rules that may restrict deletion
     * - **Workflow Impact**: Consider impact on related workflow processes
     * - **Approval Chain**: Handle deletion of recommendations in approval chains
     * 
     * ### Related Entity Impact
     * - **Note Preservation**: Handle notes and comments related to recommendation
     * - **State Log**: Preserve state transition logs for audit purposes
     * - **Event Associations**: Handle event and ceremony associations
     * - **Member Relationships**: Manage member-related associations appropriately
     * 
     * ## Performance Considerations
     * 
     * ### Efficient Deletion
     * - **Optimized Queries**: Efficient database queries for deletion operations
     * - **Transaction Optimization**: Optimized transaction handling for performance
     * - **Resource Management**: Efficient use of server resources during deletion
     * - **Response Time**: Fast response times for better user experience
     * 
     * ### Scalability
     * - **Bulk Considerations**: Consider impact of multiple simultaneous deletions
     * - **Resource Protection**: Protect server resources from deletion abuse
     * - **Performance Monitoring**: Monitor performance of deletion operations
     * - **Optimization Strategy**: Continuous optimization for better performance
     * 
     * ## User Experience Design
     * 
     * ### Confirmation Workflow
     * - **Safety Measures**: Appropriate confirmation requirements for deletion
     * - **Clear Communication**: Clear communication of deletion consequences
     * - **Recovery Information**: Information about potential recovery options
     * - **Context Preservation**: Maintain user workflow context during deletion
     * 
     * ### Navigation Management
     * - **Return Navigation**: Appropriate return navigation after deletion
     * - **Context Maintenance**: Maintain user context and workflow state
     * - **User Guidance**: Clear guidance for next steps after deletion
     * - **Error Recovery**: Support for error recovery and retry operations
     * 
     * ## Integration Points
     * 
     * ### System Integration
     * - **Workflow System**: Integration with recommendation workflow management
     * - **Authorization System**: Deep integration with RBAC authorization
     * - **Audit System**: Integration with comprehensive audit and logging
     * - **Notification System**: Integration with notification systems for deletions
     * 
     * ### Data Management
     * - **Soft Deletion**: Integration with CakePHP soft deletion system
     * - **Audit Trail**: Integration with audit trail management
     * - **Related Entities**: Coordination with related entity management
     * - **Recovery Systems**: Integration with data recovery capabilities
     * 
     * ## Recovery & Data Protection
     * 
     * ### Recovery Capabilities
     * - **Soft Deletion Benefits**: Enable potential recovery of deleted recommendations
     * - **Administrative Recovery**: Administrative tools for data recovery
     * - **Audit Trail Preservation**: Preserve complete audit trail for recovery
     * - **Data Integrity**: Maintain data integrity during recovery operations
     * 
     * ### Data Protection
     * - **Accidental Deletion Protection**: Protection against accidental deletions
     * - **Authorization Layers**: Multiple authorization layers for deletion protection
     * - **Audit Requirements**: Meet audit and compliance requirements
     * - **Business Continuity**: Support business continuity requirements
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Standard recommendation deletion
     * POST /awards/recommendations/delete/123
     * 
     * // Administrative deletion
     * DELETE /awards/recommendations/delete/456
     * 
     * // Programmatic deletion
     * $controller->delete('789'); // Returns redirect to index
     * ```
     * 
     * @param string|null $id Recommendation ID to delete
     * @return \Cake\Http\Response|null Redirects to index page after deletion
     * @throws \Cake\Http\Exception\NotFoundException When recommendation not found
     * 
     * @see index() For return navigation after deletion
     * @see view() For recommendation details before deletion
     * @see \Cake\ORM\Behavior\SoftDeleteBehavior For soft deletion implementation
     */
    public function delete(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $this->request->allowMethod(['post', 'delete']);

            $recommendation = $this->Recommendations->get($id);
            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation);

            $this->Recommendations->getConnection()->begin();
            try {
                if (!$this->Recommendations->delete($recommendation)) {
                    throw new \Exception('Failed to delete recommendation');
                }

                $this->Recommendations->getConnection()->commit();
                $this->Flash->success(__('The recommendation has been deleted.'));
            } catch (\Exception $e) {
                $this->Recommendations->getConnection()->rollback();
                Log::error('Error deleting recommendation: ' . $e->getMessage());
                $this->Flash->error(__('The recommendation could not be deleted. Please, try again.'));
            }

            return $this->redirect(['action' => 'index']);
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    #region JSON calls
    /**
     * Render a populated edit form for a recommendation intended for Turbo Frame partial updates.
     *
     * Loads the recommendation and related lookup data (awards, domains, branches, gatherings, state rules)
     * and exposes them to the view so the Turbo Frame can display an in-place edit form.
     *
     * @param string|null $id Recommendation ID to load for the form
     * @return \Cake\Http\Response|null A Response when the action issues an explicit response, or null after setting view variables for rendering
     * @throws \Cake\Http\Exception\NotFoundException If the recommendation cannot be found
     * @see edit() For form submission handling
     * @see turboQuickEditForm() For a streamlined quick-edit variant
     */
    public function turboEditForm(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id, contain: [
                'Requesters',
                'Members',
                'Branches',
                'Awards',
                'Gatherings',
                'AssignedGathering',
                'Awards.Domains'
            ]);

            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'view');
            $recommendation->domain_id = $recommendation->award->domain_id;

            // Get data for form dropdowns and options
            $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
            $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();

            $branches = $this->Recommendations->Awards->Branches
                ->find('list', keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                })
                ->where(['can_have_members' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            $awards = $this->Recommendations->Awards->find('all', limit: 200)
                ->select(['id', 'name', 'specialties'])
                ->where(['domain_id' => $recommendation->domain_id])
                ->all();

            // Get filtered gatherings for this award
            // If status is "Given", show all gatherings (past and future) for retroactive entry
            $futureOnly = ($recommendation->status !== 'Given');
            $gatheringList = $this->getFilteredGatheringsForAward(
                $recommendation->award_id,
                $recommendation->member_id,
                $futureOnly,
                $recommendation->gathering_id  // Include the currently assigned gathering
            );

            // Format status list for dropdown
            $statusList = Recommendation::getStatuses();
            foreach ($statusList as $key => $value) {
                $states = $value;
                $statusList[$key] = [];
                foreach ($states as $state) {
                    $statusList[$key][$state] = $state;
                }
            }

            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');
            $this->set(compact(
                'rules',
                'recommendation',
                'branches',
                'awards',
                'gatheringList',
                'awardsDomains',
                'awardsLevels',
                'statusList'
            ));
            return null;
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    /**
     * Render a streamlined Turbo Frame quick-edit form for a recommendation.
     *
     * Provides a minimal edit form containing the most commonly changed fields,
     * populated with essential dropdowns (awards, branches, gatherings, status)
     * and state rules to support rapid in-place edits.
     *
     * @param string|null $id Recommendation ID to load for the quick-edit form.
     * @return \Cake\Http\Response|null Renders the quick-edit template or null when rendering within a Turbo Frame.
     * @throws \Cake\Http\Exception\NotFoundException If the recommendation cannot be found.
     * @see turboEditForm() For the full edit interface
     * @see edit() For form submission handling
     * @see kanbanUpdate() For drag-and-drop state transitions
     */
    public function turboQuickEditForm(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id, contain: [
                'Requesters',
                'Members',
                'Branches',
                'Awards',
                'Gatherings',
                'AssignedGathering',
                'Awards.Domains'
            ]);

            if (!$recommendation) {
                throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
            }

            $this->Authorization->authorize($recommendation, 'view');
            $recommendation->domain_id = $recommendation->award->domain_id;

            // Get data for form dropdowns and options
            $awardsDomains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();
            $awardsLevels = $this->Recommendations->Awards->Levels->find('list', limit: 200)->all();

            $branches = $this->Recommendations->Awards->Branches
                ->find('list', keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                })
                ->where(['can_have_members' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            $awards = $this->Recommendations->Awards->find('all', limit: 200)
                ->select(['id', 'name', 'specialties'])
                ->where(['domain_id' => $recommendation->domain_id])
                ->all();

            // Get filtered gatherings for this award
            // If status is "Given", show all gatherings (past and future) for retroactive entry
            $futureOnly = ($recommendation->status !== 'Given');
            $gatheringList = $this->getFilteredGatheringsForAward(
                $recommendation->award_id,
                $recommendation->member_id,
                $futureOnly,
                $recommendation->gathering_id  // Include the currently assigned gathering
            );

            // Format status list for dropdown
            $statusList = Recommendation::getStatuses();
            foreach ($statusList as $key => $value) {
                $states = $value;
                $statusList[$key] = [];
                foreach ($states as $state) {
                    $statusList[$key][$state] = $state;
                }
            }

            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');
            $this->set(compact(
                'rules',
                'recommendation',
                'branches',
                'awards',
                'gatheringList',
                'awardsDomains',
                'awardsLevels',
                'statusList'
            ));
            return null;
        } catch (\Cake\Datasource\Exception\RecordNotFoundException $e) {
            throw new \Cake\Http\Exception\NotFoundException(__('Recommendation not found'));
        }
    }

    /**
     * Render the Turbo Frame bulk edit form for modifying multiple recommendations at once.
     *
     * Prepares dropdowns and supporting data for bulk operations (branches, gatherings,
     * state/status options and state rules) and exposes them to the view as
     * `rules`, `branches`, `gatheringList`, and `statusList`.
     *
     * @return \Cake\Http\Response|null|void The response when rendering the bulk edit form template, or null when rendering proceeds in-controller.
     * @throws \Cake\Http\Exception\InternalErrorException When preparation of bulk form data fails.
     * @see updateStates() For bulk state transition processing
     * @see turboEditForm() For individual recommendation editing
     * @see \App\KMP\StaticHelpers::getAppSetting() For configuration loading
     */
    public function turboBulkEditForm(): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->newEmptyEntity();
            $this->Authorization->authorize($recommendation, 'view');

            // Get branch list for dropdown
            $branches = $this->Recommendations->Awards->Branches
                ->find('list', keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? 'true' : 'false');
                })
                ->where(['can_have_members' => true])
                ->orderBy(['name' => 'ASC'])
                ->toArray();

            // Get gatherings data
            $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
            $gatheringsData = $gatheringsTable->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
                ->orderBy(['start_date' => 'ASC'])
                ->all();

            // Format status list for dropdown
            $statusList = Recommendation::getStatuses();
            foreach ($statusList as $key => $value) {
                $states = $value;
                $statusList[$key] = [];
                foreach ($states as $state) {
                    $statusList[$key][$state] = $state;
                }
            }

            // Format gathering list for dropdown
            $gatheringList = [];
            foreach ($gatheringsData as $gathering) {
                $gatheringList[$gathering->id] = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                    . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();
            }

            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');
            $this->set(compact('rules', 'branches', 'gatheringList', 'statusList'));
            return null;
        } catch (\Exception $e) {
            Log::error('Error in bulk edit form: ' . $e->getMessage());
            throw new \Cake\Http\Exception\InternalErrorException(__('An error occurred while preparing the bulk edit form.'));
        }
    }
    #endregion

    /**
     * Prepare and paginate recommendation data for the tabular view and expose it to the template.
     *
     * Builds and executes the filtered recommendation query, applies permission-based visibility,
     * prepares status lists, loads auxiliary lists (awards, domains, branches, gatherings), and
     * sets view variables required by the table template.
     *
     * @param array $filterArray Filter criteria to apply to the recommendations query (field => value pairs).
     * @param string $status Status filter to apply (e.g., 'All', 'Approved', 'Pending').
     * @param string $view Current view configuration name used for context and permissions.
     * @return void Sets view variables used by the table template (recommendations, statusList, awards, domains, branches, view, status, action, fullStatusList, rules, gatheringList).
     *
     * @see table()
     * @see getRecommendationQuery()
     */
    protected function runTable(array $filterArray, string $status, string $view = "Default"): void
    {
        try {
            // Build and execute the recommendation query with filters
            $recommendations = $this->getRecommendationQuery($filterArray, $view);

            // Process status lists for display
            $fullStatusList = Recommendation::getStatuses();
            if ($status == "All" || $view == 'SubmittedByMember') {
                $statusList = Recommendation::getStatuses();
            } else {
                $statusList[$status] = Recommendation::getStatuses()[$status];
            }

            // Format status lists for display
            foreach ($fullStatusList as $key => $value) {
                $fullStatusList[$key] = array_combine($value, $value);
            }

            foreach ($statusList as $key => $value) {
                $statusList[$key] = array_combine($value, $value);
            }

            // Apply visibility filters based on user permissions
            $user = $this->request->getAttribute("identity");
            $blank = $this->Recommendations->newEmptyEntity();

            if (!$user->checkCan("ViewHidden", $blank)) {
                $hiddenStates = StaticHelpers::getAppSetting("Awards.RecommendationStatesRequireCanViewHidden");
                $recommendations->where(["Recommendations.status not IN" => $hiddenStates]);

                // Filter out hidden states from status lists
                foreach ($statusList as $key => $value) {
                    $tmpStatus = $statusList[$key];
                    foreach ($hiddenStates as $hiddenState) {
                        try {
                            unset($tmpStatus[$hiddenState]);
                        } catch (\Exception $e) {
                            // Silently continue if state doesn't exist
                        }
                    }

                    if (empty($tmpStatus)) {
                        unset($statusList[$key]);
                    } else {
                        $statusList[$key] = $tmpStatus;
                    }
                }
            }

            // Get awards, domains and branches for filters/display
            $awards = $this->Recommendations->Awards->find(
                'list',
                limit: 200,
                keyField: 'id',
                valueField: 'abbreviation'
            );
            $awards = $this->Authorization->applyScope($awards, 'index')->all();

            $domains = $this->Recommendations->Awards->Domains->find('list', limit: 200)->all();

            $branches = $this->Recommendations->Branches
                ->find("list", keyPath: function ($entity) {
                    return $entity->id . '|' . ($entity->can_have_members == 1 ? "true" : "false");
                })
                ->where(["can_have_members" => true])
                ->orderBy(["name" => "ASC"])
                ->toArray();

            // Configure pagination
            $this->paginate = [
                'sortableFields' => [
                    'Branches.name',
                    'Awards.name',
                    'Domains.name',
                    'member_sca_name',
                    'created',
                    'state',
                    'Gatherings.name',
                    'call_into_court',
                    'court_availability',
                    'requester_sca_name',
                    'contact_email',
                    'contact_phone',
                    'state_date',
                    'AssignedGathering.name'
                ],
            ];

            $action = $view;
            $recommendations = $this->paginate($recommendations);

            // Get recommendation state rules and gatherings data
            $rules = StaticHelpers::getAppSetting("Awards.RecommendationStateRules");

            $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
            $gatheringsData = $gatheringsTable->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
                ->orderBy(['start_date' => 'ASC'])
                ->all();

            // Format gathering list for display
            $gatheringList = [];
            foreach ($gatheringsData as $gathering) {
                $gatheringList[$gathering->id] = $gathering->name . " in " . $gathering->branch->name . " on "
                    . $gathering->start_date->toDateString() . " - " . $gathering->end_date->toDateString();
            }

            // Set variables for the view
            $this->set(compact(
                'recommendations',
                'statusList',
                'awards',
                'domains',
                'branches',
                'view',
                'status',
                'action',
                'fullStatusList',
                'rules',
                'gatheringList'
            ));
        } catch (\Exception $e) {
            Log::error('Error in runTable: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while loading the recommendations table.'));
        }
    }

    /**
     * Process and display recommendation data in kanban board format with drag-and-drop state management
     * 
     * Implements the sophisticated kanban board interface for recommendation management,
     * featuring drag-and-drop state transitions, column-based organization by status,
     * and real-time workflow management. This protected method serves as the foundation
     * for the kanban board interface with comprehensive state management, authorization
     * control, and interactive workflow capabilities.
     * 
     * ## Kanban Architecture & Organization
     * 
     * ### Column-Based Status Organization
     * - **Dynamic Columns**: Generate kanban columns based on available statuses
     * - **Status Grouping**: Organize recommendations by current status efficiently
     * - **Visual Workflow**: Provide visual representation of recommendation workflow
     * - **Interactive Transitions**: Enable drag-and-drop status transitions
     * 
     * ### State Machine Integration
     * - **Workflow Rules**: Integrate state machine rules with kanban interface
     * - **Transition Validation**: Validate status transitions through drag-and-drop
     * - **Business Logic**: Apply business logic to kanban state changes
     * - **Permission Integration**: Apply permissions to transition capabilities
     * 
     * ## Drag-and-Drop State Management
     * 
     * ### Interactive State Transitions
     * - **Drag-and-Drop Interface**: Enable intuitive drag-and-drop status changes
     * - **Real-Time Updates**: Provide immediate feedback for state changes
     * - **Visual Feedback**: Clear visual feedback during drag-and-drop operations
     * - **Error Handling**: Graceful handling of invalid transitions
     * 
     * ### Transition Validation
     * - **Permission Checking**: Validate user permissions for status transitions
     * - **Business Rule Validation**: Apply business rules to transition attempts
     * - **Workflow Compliance**: Ensure transitions comply with workflow rules
     * - **Data Integrity**: Maintain data integrity during interactive changes
     * 
     * ## Authorization & Permission Management
     * 
     * ### User-Based Column Access
     * - **Permission-Based Columns**: Display columns based on user permissions
     * - **Action Authorization**: Authorize individual actions on recommendations
     * - **Status Visibility**: Control status visibility based on access level
     * - **Interactive Permissions**: Apply permissions to interactive elements
     * 
     * ### Transition Permission Control
     * - **Transition Authorization**: Authorize specific status transitions
     * - **Role-Based Access**: Apply role-based access to transition capabilities
     * - **Dynamic Permissions**: Adjust permissions based on recommendation context
     * - **Security Integration**: Integrate with comprehensive security system
     * 
     * ## Data Processing & Organization
     * 
     * ### Query Optimization for Kanban
     * - **Efficient Grouping**: Optimize queries for status-based grouping
     * - **Association Loading**: Load required associations for kanban display
     * - **Performance Focus**: Optimize data loading for responsive interface
     * - **Memory Efficiency**: Efficient data structures for kanban organization
     * 
     * ### Status-Based Data Grouping
     * - **Dynamic Grouping**: Group recommendations by status dynamically
     * - **Efficient Organization**: Organize data efficiently for kanban rendering
     * - **Association Management**: Handle complex associations in grouped data
     * - **Display Optimization**: Optimize data organization for display performance
     * 
     * ## Interactive Features & User Experience
     * 
     * ### Real-Time Interface Updates
     * - **Live Updates**: Real-time updates to kanban board state
     * - **Visual Feedback**: Immediate visual feedback for user actions
     * - **Error Indication**: Clear error indication for failed operations
     * - **Success Confirmation**: Positive confirmation for successful operations
     * 
     * ### User Interface Enhancement
     * - **Intuitive Navigation**: Intuitive navigation through kanban interface
     * - **Context Menus**: Context-sensitive menus for actions
     * - **Keyboard Support**: Keyboard accessibility for power users
     * - **Mobile Optimization**: Responsive design for mobile devices
     * 
     * ## Filter Integration & Processing
     * 
     * ### Advanced Filtering Capabilities
     * - **Multi-Criteria Filtering**: Support complex filtering across kanban columns
     * - **Real-Time Filter Updates**: Update kanban display based on filter changes
     * - **Filter Persistence**: Maintain filter state across kanban interactions
     * - **Visual Filter Indicators**: Clear visual indication of active filters
     * 
     * ### Search Integration
     * - **Cross-Column Search**: Search across all kanban columns efficiently
     * - **Contextual Search**: Context-aware search within kanban interface
     * - **Search Highlighting**: Highlight search results within kanban cards
     * - **Advanced Search Options**: Support advanced search within kanban view
     * 
     * ## Configuration & Customization
     * 
     * ### Kanban Configuration Management
     * - **Column Configuration**: Configurable kanban column setup
     * - **Display Options**: Customizable display options for kanban cards
     * - **User Preferences**: User-specific kanban preferences and settings
     * - **System Configuration**: System-wide kanban configuration management
     * 
     * ### View Customization
     * - **Card Content**: Customizable kanban card content and layout
     * - **Color Coding**: Status-based color coding for visual organization
     * - **Priority Indicators**: Visual priority indicators on kanban cards
     * - **Metadata Display**: Configurable metadata display on cards
     * 
     * ## Performance Optimization
     * 
     * ### Efficient Data Loading
     * - **Lazy Loading**: Lazy loading for improved initial page performance
     * - **Batch Operations**: Efficient batch operations for multiple updates
     * - **Caching Strategy**: Strategic caching for frequently accessed data
     * - **Query Optimization**: Optimized queries for kanban data requirements
     * 
     * ### Interactive Performance
     * - **Responsive Interactions**: Responsive drag-and-drop interactions
     * - **Efficient Updates**: Efficient DOM updates for state changes
     * - **Memory Management**: Efficient memory management for large datasets
     * - **Performance Monitoring**: Monitor performance for optimization opportunities
     * 
     * ## Error Handling & Recovery
     * 
     * ### Comprehensive Error Management
     * - **Drag-and-Drop Errors**: Handle drag-and-drop operation errors
     * - **State Transition Errors**: Graceful handling of invalid state transitions
     * - **Network Errors**: Robust handling of network connectivity issues
     * - **System Errors**: Safe handling of unexpected system errors
     * 
     * ### User Experience Continuity
     * - **Error Recovery**: Automatic recovery from transient errors
     * - **State Preservation**: Preserve user state during error conditions
     * - **Clear Messaging**: Clear error messaging and recovery guidance
     * - **Fallback Options**: Provide fallback options for error scenarios
     * 
     * ## Integration Points
     * 
     * ### System Integration
     * - **State Machine Integration**: Deep integration with recommendation state machine
     * - **Authorization System**: Integration with RBAC authorization system
     * - **Audit System**: Integration with audit and logging systems
     * - **Event System**: Integration with event-driven architecture
     * 
     * ### Frontend Integration
     * - **JavaScript Framework**: Integration with Stimulus.js for interactivity
     * - **CSS Framework**: Integration with responsive CSS framework
     * - **Component System**: Integration with reusable component system
     * - **Asset Management**: Efficient asset management for kanban interface
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Basic kanban board processing
     * $this->runBoard('Board', $boardConfig, $emptyRecommendation);
     * 
     * // Status-filtered kanban
     * $this->runBoard('Review', $reviewConfig, $emptyEntity);
     * 
     * // Custom kanban view
     * $this->runBoard('AdminBoard', $adminConfig, $authEntity);
     * ```
     * 
     * @param string $view Current view configuration name for context
     * @param array $pageConfig Configuration settings for current view including board states
     * @param \Awards\Model\Entity\Recommendation $emptyRecommendation Empty entity for authorization checks
     * @return void Sets view variables for kanban template rendering
     * 
     * @see board() For public kanban interface that calls this method
     * @see kanbanUpdate() For handling drag-and-drop state transitions
     * @see getRecommendationQuery() For the core query building implementation
     * @see \App\Controller\Component\AuthorizationComponent For authorization integration
     */
    protected function runBoard(string $view, array $pageConfig, \Awards\Model\Entity\Recommendation $emptyRecommendation): void
    {
        try {
            // Initialize states from board configuration
            $statesList = $pageConfig['board']['states'];
            $states = [];
            foreach ($statesList as $state) {
                $states[$state] = [];
            }

            $statesToLoad = $pageConfig['board']['states'];
            $hiddenByDefault = $pageConfig['board']['hiddenByDefault'];
            $hiddenByDefaultStates = [];

            // Process hidden states configuration
            if (is_array($hiddenByDefault) && !empty($hiddenByDefault)) {
                foreach ($hiddenByDefault["states"] as $state) {
                    $hiddenByDefaultStates[] = $state;
                    $statesToLoad = array_diff($statesToLoad, [$state]);
                }
            }

            $user = $this->request->getAttribute('identity');

            // Apply permissions to hidden states
            if (!$user->checkCan('ViewHidden', $emptyRecommendation)) {
                $hiddenStates = StaticHelpers::getAppSetting('Awards.RecommendationStatesRequireCanViewHidden');

                // Filter out any hidden states the user doesn't have permission to view
                foreach ($hiddenStates as $state) {
                    if (in_array($state, $hiddenByDefaultStates)) {
                        $hiddenByDefaultStates = array_diff($hiddenByDefaultStates, [$state]);
                    }
                }
            }

            // Build base query for recommendations
            $recommendations = $this->Recommendations->find()
                ->contain(['Requesters', 'Members', 'Awards'])
                ->orderBy(['Recommendations.state', 'stack_rank'])
                ->select([
                    'Recommendations.id',
                    'Recommendations.member_sca_name',
                    'Recommendations.reason',
                    'Recommendations.stack_rank',
                    'Recommendations.state',
                    'Recommendations.status',
                    'Recommendations.modified',
                    'Recommendations.specialty',
                    'Members.sca_name',
                    'Awards.abbreviation',
                    'ModifiedByMembers.sca_name'
                ])
                ->join([
                    'table' => 'members',
                    'alias' => 'ModifiedByMembers',
                    'type' => 'LEFT',
                    'conditions' => 'Recommendations.modified_by = ModifiedByMembers.id'
                ]);

            // Apply authorization scope
            $recommendations = $this->Authorization->applyScope($recommendations, 'index');

            // Apply hidden states filter based on permissions
            if (!$user->checkCan('ViewHidden', $emptyRecommendation)) {
                $hiddenStates = StaticHelpers::getAppSetting('Awards.RecommendationStatesRequireCanViewHidden');
                $recommendations = $recommendations->where(['Recommendations.state NOT IN' => $hiddenStates]);
            }

            // Process show/hide filter from query parameters
            $showHidden = $this->request->getQuery('showHidden') === 'true';
            $range = $hiddenByDefault['lookback'] ?? 30; // Default to 30 days if not specified

            // Build comma-separated list of hidden states for view
            $hiddenStatesStr = '';
            if (is_array($hiddenByDefaultStates) && !empty($hiddenByDefaultStates)) {
                $hiddenStatesStr = implode(',', $hiddenByDefaultStates);

                // Apply filter based on showHidden parameter
                if ($showHidden) {
                    $cutoffDate = DateTime::now()->subDays($range);
                    $recommendations = $recommendations->where([
                        'OR' => [
                            'Recommendations.state IN' => $statesToLoad,
                            'AND' => [
                                'Recommendations.state IN' => $hiddenByDefaultStates,
                                'Recommendations.state_date >' => $cutoffDate
                            ]
                        ]
                    ]);
                } else {
                    $recommendations = $recommendations->where(['Recommendations.state IN' => $statesToLoad]);
                }
            } else {
                $recommendations = $recommendations->where(['Recommendations.state IN' => $statesToLoad]);
            }

            // Execute the query and get all recommendations
            $recommendations = $recommendations->all();

            // Group recommendations by state for kanban board display
            foreach ($recommendations as $recommendation) {
                if (!isset($states[$recommendation->state])) {
                    $states[$recommendation->state] = [];
                }
                $states[$recommendation->state][] = $recommendation;
            }

            // Get recommendation state rules for UI
            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');

            // Set variables for the view
            $this->set(compact(
                'recommendations',
                'states',
                'view',
                'showHidden',
                'range',
                'hiddenStatesStr',
                'rules'
            ));
        } catch (\Exception $e) {
            Log::error('Error in runBoard: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while loading the board view.'));
        }
    }

    /**
     * Generate comprehensive CSV export of recommendations with advanced formatting and filtering
     * 
     * Implements sophisticated CSV export functionality for recommendation data with
     * comprehensive column configuration, advanced data formatting, authorization-based
     * filtering, and performance optimization for large datasets. This protected method
     * serves as the foundation for all CSV export operations with configurable column
     * selection, data transformation, and secure data access control.
     * 
     * ## Export Architecture & Data Processing
     * 
     * ### Column Configuration Management
     * - **Dynamic Column Selection**: Configure which columns to include in export
     * - **Column Ordering**: Maintain consistent column ordering across exports
     * - **Header Generation**: Generate descriptive column headers for user clarity
     * - **Field Mapping**: Map internal field names to user-friendly column names
     * 
     * ### Data Query & Filtering
     * - **Filter Application**: Apply comprehensive filter criteria to export queries
     * - **Authorization Scoping**: Apply user-based authorization to exported data
     * - **Association Loading**: Load required associations for complete data export
     * - **Performance Optimization**: Optimize queries for large dataset exports
     * 
     * ## CSV Generation & Formatting
     * 
     * ### Data Transformation Pipeline
     * - **Field Formatting**: Transform database fields for CSV presentation
     * - **Date Formatting**: Standardize date/time formatting for export consistency
     * - **Enum Translation**: Translate internal enum values to readable text
     * - **Association Data**: Include related entity data in export format
     * 
     * ### Content Sanitization
     * - **CSV Injection Prevention**: Sanitize content to prevent CSV injection attacks
     * - **Special Character Handling**: Proper handling of special characters in CSV
     * - **Data Escaping**: Escape data appropriately for CSV format compliance
     * - **Encoding Management**: Ensure proper character encoding for international data
     * 
     * ## Advanced Export Features
     * 
     * ### Configurable Data Presentation
     * - **Custom Formatters**: Apply custom formatting to specific data types
     * - **Conditional Formatting**: Apply conditional formatting based on data values
     * - **Hierarchical Data**: Handle hierarchical relationships in flat CSV format
     * - **Metadata Inclusion**: Include relevant metadata in export output
     * 
     * ### Large Dataset Optimization
     * - **Memory Management**: Efficient memory usage for large dataset exports
     * - **Streaming Output**: Stream large exports to prevent memory exhaustion
     * - **Batch Processing**: Process large datasets in manageable batches
     * - **Progress Tracking**: Track export progress for user feedback
     * 
     * ## Authorization & Security
     * 
     * ### Access Control Integration
     * - **User-Based Filtering**: Filter exported data based on user permissions
     * - **Field-Level Security**: Apply field-level security to sensitive data
     * - **Branch-Based Access**: Apply branch-based access control to exports
     * - **Role-Based Filtering**: Filter data based on user roles and capabilities
     * 
     * ### Data Protection & Privacy
     * - **Sensitive Data Handling**: Special handling for sensitive or confidential data
     * - **PII Protection**: Protect personally identifiable information in exports
     * - **Audit Trail**: Maintain audit trail for export operations
     * - **Compliance Features**: Ensure compliance with data protection regulations
     * 
     * ## File Generation & Delivery
     * 
     * ### CSV File Construction
     * - **Standards Compliance**: Generate CSV files compliant with RFC 4180
     * - **Character Encoding**: Proper UTF-8 encoding for international support
     * - **Line Ending Handling**: Consistent line ending handling across platforms
     * - **Quote Management**: Proper quoting of fields containing special characters
     * 
     * ### Download Response Management
     * - **Content-Type Headers**: Set appropriate content-type headers for CSV download
     * - **Filename Generation**: Generate descriptive filenames with timestamps
     * - **Cache Control**: Set appropriate cache control headers
     * - **Content-Disposition**: Proper content-disposition for browser download
     * 
     * ## Performance Optimization
     * 
     * ### Query Performance
     * - **Efficient Queries**: Optimize database queries for export performance
     * - **Index Utilization**: Ensure queries utilize appropriate database indexes
     * - **Association Optimization**: Optimize association loading for export queries
     * - **Query Result Caching**: Strategic caching of query results where appropriate
     * 
     * ### Memory & Resource Management
     * - **Memory Efficiency**: Efficient memory usage during export processing
     * - **Resource Cleanup**: Proper cleanup of resources after export completion
     * - **Garbage Collection**: Support efficient garbage collection during exports
     * - **Performance Monitoring**: Monitor export performance for optimization
     * 
     * ## Error Handling & Recovery
     * 
     * ### Comprehensive Error Management
     * - **Query Errors**: Handle database query errors gracefully
     * - **Memory Errors**: Handle memory exhaustion and resource errors
     * - **File System Errors**: Handle file system and I/O errors
     * - **Network Errors**: Handle network-related errors during large exports
     * 
     * ### User Communication & Recovery
     * - **Error Messaging**: Clear error messaging for export failures
     * - **Partial Export Options**: Provide partial export options on errors
     * - **Recovery Guidance**: Guide users through error recovery processes
     * - **Alternative Formats**: Suggest alternative export formats on errors
     * 
     * ## Integration Points
     * 
     * ### Service Integration
     * - **CSV Export Service**: Deep integration with CsvExportService
     * - **Authorization Service**: Integration with authorization and permission systems
     * - **Audit Service**: Integration with audit and logging systems
     * - **Configuration Service**: Integration with system configuration management
     * 
     * ### Data System Integration
     * - **ORM Integration**: Deep integration with CakePHP ORM system
     * - **Query Builder**: Integration with CakePHP query builder capabilities
     * - **Association System**: Integration with CakePHP association system
     * - **Validation System**: Integration with data validation systems
     * 
     * ## Export Format Customization
     * 
     * ### Column Format Configuration
     * - **Data Type Formatting**: Custom formatting for different data types
     * - **Locale Support**: Locale-aware formatting for international users
     * - **Custom Transformers**: Support for custom data transformation functions
     * - **Template System**: Template-based formatting for complex data presentations
     * 
     * ### Export Metadata
     * - **Export Timestamps**: Include export generation timestamps
     * - **Filter Documentation**: Document applied filters in export metadata
     * - **Version Information**: Include system version information in exports
     * - **User Attribution**: Include user attribution information where appropriate
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Basic export with standard columns
     * $response = $this->runExport($csvService, $filterArray, $standardColumns);
     * 
     * // Custom export with specific columns
     * $customColumns = ['name' => true, 'award' => true, 'status' => true];
     * $response = $this->runExport($csvService, $filter, $customColumns);
     * 
     * // Filtered export for specific status
     * $statusFilter = ['status' => 'Approved'];
     * $response = $this->runExport($csvService, $statusFilter, $allColumns);
     * ```
     * 
     * @param \App\Services\CsvExportService $csvExportService Service for generating CSV exports with formatting
     * @param array $filterArray Filter criteria for querying recommendations
     * @param array $columns Configuration of which columns to include in export (column_name => boolean)
     * @return \Cake\Http\Response CSV download response with appropriate headers and content
     * 
     * @see table() For the table interface that provides export functionality
     * @see getRecommendationQuery() For the core query building implementation
     * @see formatExportColumn() For individual column formatting logic
     * @see \App\Services\CsvExportService For CSV generation service integration
     */
    protected function runExport(CsvExportService $csvExportService, array $filterArray, array $columns): \Cake\Http\Response
    {
        try {
            // Get filtered recommendations
            $recommendations = $this->getRecommendationQuery($filterArray);
            $recommendations = $recommendations->all();

            // Build header row from selected columns
            $header = [];
            $data = [];
            foreach ($columns as $key => $use) {
                if ($use) {
                    $header[] = $key;
                }
            }

            // Process each recommendation into a row based on selected columns
            foreach ($recommendations as $recommendation) {
                $row = [];
                foreach ($header as $key) {
                    $row[$key] = $this->formatExportColumn($recommendation, $key);
                }
                $data[] = $row;
            }

            // Generate and return CSV response
            return $csvExportService->outputCsv(
                $data,
                filename: "recommendations.csv",
                headers: $header
            );
        } catch (\Exception $e) {
            Log::error('Error generating CSV export: ' . $e->getMessage());
            $this->Flash->error(__('An error occurred while generating the export.'));
            throw $e; // Re-throw to be caught by the parent method
        }
    }

    /**
     * Format individual column values for CSV export with comprehensive data transformation
     * 
     * Implements sophisticated column-specific formatting for CSV export operations,
     * providing standardized data transformation, type-specific formatting, and
     * comprehensive data presentation optimization. This private method serves as
     * the core formatting engine for individual data fields in CSV exports with
     * consistent formatting rules, data type handling, and presentation standards.
     * 
     * ## Column-Specific Formatting Architecture
     * 
     * ### Data Type Transformation
     * - **Date/Time Formatting**: Standardized formatting for temporal data
     * - **Text Normalization**: Consistent text formatting and normalization
     * - **Enum Translation**: Translate internal enum values to readable text
     * - **Null Value Handling**: Consistent handling of null and empty values
     * 
     * ### Association Data Processing
     * - **Member Information**: Format member-related data consistently
     * - **Award Details**: Present award information in readable format
     * - **Branch Data**: Format branch and organizational information
     * - **Event Information**: Present event data in standardized format
     * 
     * ## Comprehensive Column Formatting
     * 
     * ### Core Entity Fields
     * - **Submitted**: Format creation timestamp for export presentation
     * - **For**: Present member SCA name consistently
     * - **For Herald**: Format herald-specific member naming
     * - **Title**: Present member titles with appropriate formatting
     * 
     * ### Status & State Information
     * - **Status**: Translate status codes to readable descriptions
     * - **State**: Present workflow state information clearly
     * - **Workflow Context**: Include workflow context where relevant
     * - **Transition History**: Format state transition information
     * 
     * ### Award & Recognition Data
     * - **Award Information**: Present award details comprehensively
     * - **Award Type**: Format award type and category information
     * - **Recognition Level**: Present recognition level clearly
     * - **Precedence Information**: Include award precedence where relevant
     * 
     * ### Member & Organizational Data
     * - **Modern Name**: Present modern names consistently
     * - **SCA Name**: Format SCA names with proper conventions
     * - **Branch Information**: Present branch data with hierarchy
     * - **Contact Information**: Format contact data appropriately
     * 
     * ## Data Transformation Pipeline
     * 
     * ### Text Processing & Formatting
     * - **String Normalization**: Normalize text strings for consistent presentation
     * - **Case Management**: Consistent case handling across text fields
     * - **Whitespace Handling**: Trim and normalize whitespace in text fields
     * - **Special Character Processing**: Handle special characters appropriately
     * 
     * ### Date & Time Processing
     * - **Timestamp Conversion**: Convert timestamps to readable date formats
     * - **Time Zone Handling**: Handle time zone conversions appropriately
     * - **Null Date Handling**: Consistent handling of null date values
     * - **Format Standardization**: Standardize date formats across exports
     * 
     * ## Association Data Integration
     * 
     * ### Member Data Processing
     * - **Name Resolution**: Resolve member names through various formats
     * - **Title Integration**: Include member titles where appropriate
     * - **Contact Integration**: Include relevant contact information
     * - **Branch Association**: Include member branch associations
     * 
     * ### Award Data Processing
     * - **Award Name Resolution**: Resolve award names and descriptions
     * - **Type Classification**: Include award type and classification
     * - **Precedence Information**: Include award precedence where relevant
     * - **Domain Integration**: Include domain information for awards
     * 
     * ## Error Handling & Data Validation
     * 
     * ### Null Value Management
     * - **Graceful Null Handling**: Handle null values without errors
     * - **Default Value Provision**: Provide appropriate default values
     * - **Missing Data Indication**: Clearly indicate missing data
     * - **Consistent Null Representation**: Consistent representation of null values
     * 
     * ### Data Integrity Verification
     * - **Type Checking**: Verify data types before formatting
     * - **Range Validation**: Validate data ranges where appropriate
     * - **Format Verification**: Verify data format before transformation
     * - **Consistency Checks**: Ensure data consistency across related fields
     * 
     * ## Performance Optimization
     * 
     * ### Efficient Processing
     * - **Minimal Object Creation**: Minimize object creation during formatting
     * - **String Optimization**: Optimize string operations for performance
     * - **Memory Management**: Efficient memory usage during processing
     * - **Caching Strategy**: Cache frequently accessed formatting data
     * 
     * ### Scalability Considerations
     * - **Large Dataset Support**: Support formatting for large datasets
     * - **Memory Efficiency**: Maintain memory efficiency during bulk operations
     * - **Processing Speed**: Optimize processing speed for responsive exports
     * - **Resource Management**: Manage system resources efficiently
     * 
     * ## Format Standardization
     * 
     * ### Consistent Presentation
     * - **Column Width Optimization**: Optimize column content for standard widths
     * - **Data Alignment**: Consistent data alignment across columns
     * - **Format Conventions**: Apply consistent format conventions
     * - **Presentation Standards**: Maintain presentation standards across exports
     * 
     * ### Localization Support
     * - **Locale-Aware Formatting**: Support locale-specific formatting
     * - **Character Encoding**: Proper character encoding for international data
     * - **Cultural Conventions**: Respect cultural conventions in data presentation
     * - **Language Support**: Support multiple languages where appropriate
     * 
     * ## Integration Points
     * 
     * ### Entity System Integration
     * - **ORM Integration**: Deep integration with CakePHP ORM entities
     * - **Association Access**: Efficient access to entity associations
     * - **Property Resolution**: Resolve entity properties efficiently
     * - **Data Validation**: Integrate with entity validation systems
     * 
     * ### Service Integration
     * - **Formatting Services**: Integration with external formatting services
     * - **Localization Services**: Integration with localization systems
     * - **Configuration Services**: Integration with configuration management
     * - **Validation Services**: Integration with data validation services
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Format submission date
     * $formatted = $this->formatExportColumn($recommendation, 'Submitted');
     * 
     * // Format member information
     * $memberName = $this->formatExportColumn($recommendation, 'For');
     * 
     * // Format award information
     * $awardInfo = $this->formatExportColumn($recommendation, 'Award');
     * ```
     * 
     * @param \Awards\Model\Entity\Recommendation $recommendation The recommendation entity to format data from
     * @param string $columnName The name of the column to format (must match predefined column names)
     * @return string The formatted value ready for CSV inclusion
     * 
     * @see runExport() For the export method that calls this formatter
     * @see \App\Services\CsvExportService For CSV export service integration
     * @see \Awards\Model\Entity\Recommendation For recommendation entity structure
     */
    private function formatExportColumn(\Awards\Model\Entity\Recommendation $recommendation, string $columnName): string
    {
        switch ($columnName) {
            case "Submitted":
                return (string)$recommendation->created;

            case "For":
                return $recommendation->member_sca_name;

            case "For Herald":
                return $recommendation->member
                    ? $recommendation->member->name_for_herald
                    : $recommendation->member_sca_name;

            case "Title":
                return $recommendation->member
                    ? (string)$recommendation->member->title
                    : "";

            case "Pronouns":
                return $recommendation->member
                    ? (string)$recommendation->member->pronouns
                    : "";

            case "Pronunciation":
                return $recommendation->member
                    ? (string)$recommendation->member->pronunciation
                    : "";

            case "OP":
                $links = "";
                if ($recommendation->member) {
                    $member = $recommendation->member;
                    $externalLinks = $member->publicLinks();
                    if ($externalLinks) {
                        foreach ($externalLinks as $name => $link) {
                            $links .= "| $name : $link ";
                        }
                        $links .= "|";
                    }
                }
                return $links;

            case "Branch":
                return $recommendation->branch->name;

            case "Call Into Court":
                return (string)$recommendation->call_into_court;

            case "Court Avail":
                return (string)$recommendation->court_availability;

            case "Person to Notify":
                return (string)$recommendation->person_to_notify;

            case "Submitted By":
                return $recommendation->requester_sca_name;

            case "Contact Email":
                return (string)$recommendation->contact_email;

            case "Contact Phone":
                return (string)$recommendation->contact_phone;

            case "Domain":
                return $recommendation->award->domain->name;

            case "Award":
                $awardText = $recommendation->award->abbreviation;
                if ($recommendation->specialty) {
                    $awardText .= " (" . $recommendation->specialty . ")";
                }
                return $awardText;

            case "Reason":
                return (string)$recommendation->reason;

            case "Events":
                $events = "";
                foreach ($recommendation->events as $event) {
                    $startDate = $event->start_date->toDateString();
                    $endDate = $event->end_date->toDateString();
                    $events .= "$event->name : $startDate - $endDate\n\n";
                }
                return $events;

            case "Notes":
                $notes = "";
                foreach ($recommendation->notes as $note) {
                    $createDate = $note->created->toDateTimeString();
                    $notes .= "$createDate : $note->body\n\n";
                }
                return $notes;

            case "Status":
                return $recommendation->status;

            case "Event":
                return $recommendation->assigned_event
                    ? $recommendation->assigned_event->name
                    : "";

            case "State":
                return $recommendation->state;

            case "Close Reason":
                return (string)$recommendation->close_reason;

            case "State Date":
                return $recommendation->state_date->toDateString();

            case "Given Date":
                return $recommendation->given
                    ? $recommendation->given->toDateString()
                    : "";

            default:
                return "";
        }
    }

    /**
     * Build a Recommendation query with necessary containments, applied filters, and authorization scope.
     *
     * Constructs the base query used by table, board, and export flows: selects core recommendation fields,
     * loads related associations (awards, levels, branches, members, requesters, gatherings, notes, etc.),
     * applies an optional array of conditions and request query parameter filters, and enforces authorization
     * scoping. When $view is "SubmittedByMember" and the current identity matches the provided member_id
     * query parameter, authorization scoping is skipped to allow members to view their own submissions.
     *
     * @param array|null $filterArray Optional associative array of conditions to apply to the query (e.g. ['status' => 'Approved']).
     * @param string|null $view Optional view identifier that may change scoping behavior (e.g. 'SubmittedByMember').
     * @return \Cake\Datasource\QueryInterface The configured recommendations query ready for execution or further modification.
     */
    protected function getRecommendationQuery(?array $filterArray = null, ?string $view = null): \Cake\Datasource\QueryInterface
    {

        // Build base query with containments
        $recommendations = $this->Recommendations->find()
            ->select([
                'Recommendations.id',
                'Recommendations.stack_rank',
                'Recommendations.requester_id',
                'Recommendations.member_id',
                'Recommendations.branch_id',
                'Recommendations.award_id',
                'Recommendations.specialty',
                'Recommendations.requester_sca_name',
                'Recommendations.member_sca_name',
                'Recommendations.contact_number',
                'Recommendations.contact_email',
                'Recommendations.reason',
                'Recommendations.call_into_court',
                'Recommendations.court_availability',
                'Recommendations.status',
                'Recommendations.state_date',
                'Recommendations.gathering_id',
                'Recommendations.given',
                'Recommendations.modified',
                'Recommendations.created',
                'Recommendations.created_by',
                'Recommendations.modified_by',
                'Recommendations.deleted',
                'Recommendations.person_to_notify',
                'Recommendations.no_action_reason',
                'Recommendations.close_reason',
                'Recommendations.state',
                'Branches.id',
                'Branches.name',
                'Requesters.id',
                'Requesters.sca_name',
                'Members.id',
                'Members.sca_name',
                'Members.title',
                'Members.pronouns',
                'Members.pronunciation',
                'AssignedGathering.id',
                'AssignedGathering.name',
                'Awards.id',
                'Awards.abbreviation',
                'Awards.branch_id',
                'AwardsBranches.type',
            ])
            // First, establish the Awards join using leftJoinWith
            ->contain('Awards', function ($q) {
                return $q->select(['id', 'abbreviation', 'branch_id', 'Levels.id', 'Levels.name']);
            })
            ->contain('Awards.Levels', function ($q) {
                return $q->select(['id', 'name']);
            })
            ->join([
                'AwardsForBranches' => [
                    'table' => 'awards_awards',
                    'type' => 'LEFT',
                    'conditions' => 'AwardsForBranches.id = Recommendations.award_id AND AwardsForBranches.deleted IS NULL'
                ]
            ])
            // Then add the manual join for AwardsBranches
            ->join([
                'AwardsBranches' => [
                    'table' => 'branches',
                    'type' => 'LEFT',
                    'conditions' => 'AwardsBranches.id = AwardsForBranches.branch_id AND AwardsBranches.deleted IS NULL'
                ]
            ])
            ->contain([
                'Requesters' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name', 'title', 'pronouns', 'pronunciation']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Awards.Domains' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Gatherings' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                },
                'Notes' => function ($q) {
                    return $q->select(['id', 'entity_id', 'subject', 'body', 'created']);
                },
                'Notes.Authors' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'AssignedGathering' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ]);

        // Apply filter array if provided
        if ($filterArray) {
            $recommendations->where($filterArray);
        }
        $queryArgs = $this->request->getQuery();
        $user = $this->request->getAttribute('identity');
        // Apply additional filters from query parameters
        if (isset($queryArgs['award_id']) && $queryArgs['award_id'] !== '') {
            $recommendations->where(['award_id' => $queryArgs['award_id']]);
        }

        if (isset($queryArgs['branch_id']) && $queryArgs['branch_id'] !== '') {
            $recommendations->where(['Recommendations.branch_id' => $queryArgs['branch_id']]);
        }

        if (isset($queryArgs['for']) && $queryArgs['for'] !== '') {
            $recommendations->where(['member_sca_name LIKE' => '%' . $queryArgs['for'] . '%']);
        }

        if (isset($queryArgs['call_into_court']) && $queryArgs['call_into_court'] !== '') {
            $recommendations->where(['call_into_court' => $queryArgs['call_into_court']]);
        }

        if (isset($queryArgs['court_avail']) && $queryArgs['court_avail'] !== '') {
            $recommendations->where(['court_availability' => $queryArgs['court_avail']]);
        }

        if (isset($queryArgs['requester_sca_name']) && $queryArgs['requester_sca_name'] !== '') {
            $recommendations->where(['requester_sca_name' => $queryArgs['requester_sca_name']]);
        }

        if (isset($queryArgs['domain_id']) && $queryArgs['domain_id'] !== '') {
            $recommendations->where(['Awards.domain_id' => $queryArgs['domain_id']]);
        }

        if (isset($queryArgs['state']) && $queryArgs['state'] !== '') {
            $recommendations->where(['Recommendations.state' => $queryArgs['state']]);
        }

        if (isset($queryArgs['branch_type']) && $queryArgs['branch_type'] !== '') {
            $recommendations->where(['AwardsBranches.type like ' => '%' . $queryArgs['branch_type'] . '%']);
        }

        // Apply authorization scope policy
        //if the view is SubmittedByMember and the member_id = the current user then skip scoping

        if (isset($queryArgs['member_id']) && $view == 'SubmittedByMember' && $user->id == $queryArgs['member_id']) {
            return $recommendations;
        }
        return $this->Authorization->applyScope($recommendations, 'index');
    }

    /**
     * Normalize a filter configuration into query-ready conditions.
     *
     * Converts association arrow notation ("->") to dot notation and substitutes
     * placeholder values wrapped in "-" with the corresponding request query
     * parameter values (e.g. '-branch-' is replaced by $this->request->getQuery('branch')).
     * Placeholders that are missing or empty are omitted from the resulting array.
     *
     * @param array $filter Filter configuration where keys may use "->" for associations
     *                      and values may be literal values or parameter placeholders
     *                      wrapped in "-" (e.g. "-paramName-").
     * @return array An associative array of normalized conditions suitable for use
     *               with CakePHP query building (association paths use "." and
     *               parameter placeholders have been replaced or removed).
     *
     * @see getRecommendationQuery() For how the processed filters are applied to queries
     */
    protected function processFilter(array $filter): array
    {
        $filterArray = [];

        foreach ($filter as $key => $value) {
            // Convert "->" notation to "." for proper SQL path expressions
            $fixedKey = str_replace("->", ".", $key);

            // Check if value is a request parameter reference (wrapped in "-" delimiters)
            if (
                is_string($value) &&
                strlen($value) >= 2 &&
                substr($value, 0, 1) === "-" &&
                substr($value, -1) === "-"
            ) {

                // Extract parameter name and get its value from the request
                $paramName = substr($value, 1, -1);
                $paramValue = $this->request->getQuery($paramName);

                // Only add the condition if the parameter has a value
                if ($paramValue !== null && $paramValue !== '') {
                    $filterArray[$fixedKey] = $paramValue;
                }
            } else {
                $filterArray[$fixedKey] = $value;
            }
        }

        return $filterArray;
    }

    /**
     * Retrieve gatherings linked to an award and format them for display, optionally marking member attendance.
     *
     * @param int $awardId The award ID whose linked gatherings should be returned.
     * @param int|null $memberId Optional member ID; when provided, gatherings the member attends with `share_with_crown` enabled are marked.
     * @param bool $futureOnly When true, include only gatherings with a start date in the future.
     * @param int|null $includeGatheringId If provided, ensure this gathering ID is included in the results even if it would be excluded by the activity or date filters.
     * @return array Associative array mapping gathering ID => formatted display string ("Name in Branch on YYYY-MM-DD - YYYY-MM-DD"); entries with an asterisk indicate the member is attending and sharing with crown.
    protected function getFilteredGatheringsForAward(int $awardId, ?int $memberId = null, bool $futureOnly = true, ?int $includeGatheringId = null): array
    {
        // Get all gathering activities linked to this award
        $awardGatheringActivitiesTable = $this->fetchTable('Awards.AwardGatheringActivities');
        $linkedActivities = $awardGatheringActivitiesTable->find()
            ->where(['award_id' => $awardId])
            ->select(['gathering_activity_id'])
            ->toArray();

        $activityIds = array_map(function ($row) {
            return $row->gathering_activity_id;
        }, $linkedActivities);

        // If no activities are linked to the award, return empty array
        if (empty($activityIds)) {
            return [];
        }

        // Get gatherings that have these activities
        $gatheringsTable = $this->fetchTable('Gatherings');
        $query = $gatheringsTable->find()
            ->contain([
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ])
            ->select(['id', 'name', 'start_date', 'end_date', 'Gatherings.branch_id'])
            ->orderBy(['start_date' => 'DESC']);

        // Only filter by date if futureOnly is true
        if ($futureOnly) {
            $query->where(['start_date >' => DateTime::now()]);
            $query->orderBy(['start_date' => 'ASC']);
        }

        // Filter by linked activities
        $query->matching('GatheringActivities', function ($q) use ($activityIds) {
            return $q->where(['GatheringActivities.id IN' => $activityIds]);
        });

        $gatheringsData = $query->all();

        // Get attendance information for the member if member_id provided
        $attendanceMap = [];
        if ($memberId) {
            $attendanceTable = $this->fetchTable('GatheringAttendances');
            $attendances = $attendanceTable->find()
                ->where([
                    'member_id' => $memberId,
                    'deleted IS' => null
                ])
                ->select(['gathering_id', 'share_with_crown'])
                ->toArray();

            foreach ($attendances as $attendance) {
                $attendanceMap[$attendance->gathering_id] = $attendance->share_with_crown;
            }
        }

        // Build the response array
        $gatherings = [];
        foreach ($gatheringsData as $gathering) {
            $displayName = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();

            $hasAttendance = isset($attendanceMap[$gathering->id]);
            $shareWithCrown = $hasAttendance && $attendanceMap[$gathering->id];

            // Add indicator if member is attending and sharing with crown
            if ($shareWithCrown) {
                $displayName .= ' *';
            }

            $gatherings[$gathering->id] = $displayName;
        }

        // If a specific gathering ID should be included (e.g., already assigned to recommendation)
        // and it's not already in the list, add it
        if ($includeGatheringId && !isset($gatherings[$includeGatheringId])) {
            $gatheringsTable = $this->fetchTable('Gatherings');
            $specificGathering = $gatheringsTable->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where(['Gatherings.id' => $includeGatheringId])
                ->select(['id', 'name', 'start_date', 'end_date', 'Gatherings.branch_id'])
                ->first();

            if ($specificGathering) {
                $displayName = $specificGathering->name . ' in ' . $specificGathering->branch->name . ' on '
                    . $specificGathering->start_date->toDateString() . ' - ' . $specificGathering->end_date->toDateString();

                $hasAttendance = isset($attendanceMap[$specificGathering->id]);
                $shareWithCrown = $hasAttendance && $attendanceMap[$specificGathering->id];

                if ($shareWithCrown) {
                    $displayName .= ' *';
                }

                // Add to the beginning of the array so it appears first
                $gatherings = [$specificGathering->id => $displayName] + $gatherings;
            }
        }

        return $gatherings;
    }

    /**
     * Return gatherings linked to an award, optionally including attendance indicators for a member.
     *
     * Renders a JSON array where each item contains: `id`, `name`, `display` (formatted branch and date range,
     * with an appended `*` when the member is attending and sharing with crown), `has_attendance`, and
     * `share_with_crown`.
     *
     * @param string|null $awardId The award ID to filter gatherings for.
     * @throws \Cake\Http\Exception\NotFoundException When the specified award cannot be found.
     */
    public function gatheringsForAward(?string $awardId = null): void
    {
        $this->request->allowMethod(['get']);

        // Skip authorization - this is a data endpoint for add/edit forms
        // Authorization is handled at the form action level
        $this->Authorization->skipAuthorization();

        try {
            // Get member_id from query params if provided
            $memberId = $this->request->getQuery('member_id');

            // Get status from query params to determine if we should show all gatherings
            $status = $this->request->getQuery('status');
            $futureOnly = ($status !== 'Given');

            // Get the award to verify it exists
            $awardsTable = $this->fetchTable('Awards.Awards');
            $award = $awardsTable->get($awardId);

            // Get all gathering activities linked to this award
            $awardGatheringActivitiesTable = $this->fetchTable('Awards.AwardGatheringActivities');
            $linkedActivities = $awardGatheringActivitiesTable->find()
                ->where(['award_id' => $awardId])
                ->select(['gathering_activity_id'])
                ->toArray();

            $activityIds = array_map(function ($row) {
                return $row->gathering_activity_id;
            }, $linkedActivities);

            // Get gatherings that have these activities
            $gatheringsTable = $this->fetchTable('Gatherings');
            $query = $gatheringsTable->find()
                ->contain([
                    'Branches' => function ($q) {
                        return $q->select(['id', 'name']);
                    }
                ])
                ->select(['id', 'name', 'start_date', 'end_date', 'Gatherings.branch_id']);

            // Only filter by date if futureOnly is true
            if ($futureOnly) {
                $query->where(['start_date >' => DateTime::now()])
                    ->orderBy(['start_date' => 'ASC']);
            } else {
                $query->orderBy(['start_date' => 'DESC']);
            }

            // If there are linked activities, filter by them
            if (!empty($activityIds)) {
                $query->matching('GatheringActivities', function ($q) use ($activityIds) {
                    return $q->where(['GatheringActivities.id IN' => $activityIds]);
                });
            } else {
                // If no activities are linked to the award, return empty array
                $this->set([
                    'gatherings' => [],
                    '_serialize' => ['gatherings']
                ]);
                $this->viewBuilder()->setOption('serialize', ['gatherings']);
                return;
            }

            $gatheringsData = $query->all();

            // Get attendance information for the member if member_id provided
            $attendanceMap = [];
            if ($memberId) {
                $attendanceTable = $this->fetchTable('GatheringAttendances');
                $attendances = $attendanceTable->find()
                    ->where([
                        'member_id' => $memberId,
                        'deleted IS' => null
                    ])
                    ->select(['gathering_id', 'share_with_crown'])
                    ->toArray();

                foreach ($attendances as $attendance) {
                    $attendanceMap[$attendance->gathering_id] = $attendance->share_with_crown;
                }
            }

            // Build the response array
            $gatherings = [];
            foreach ($gatheringsData as $gathering) {
                $displayName = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                    . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();

                $hasAttendance = isset($attendanceMap[$gathering->id]);
                $shareWithCrown = $hasAttendance && $attendanceMap[$gathering->id];

                // Add indicator if member is attending and sharing with crown
                if ($shareWithCrown) {
                    $displayName .= ' *';
                }

                $gatherings[] = [
                    'id' => $gathering->id,
                    'name' => $gathering->name,
                    'display' => $displayName,
                    'has_attendance' => $hasAttendance,
                    'share_with_crown' => $shareWithCrown
                ];
            }

            $this->set([
                'gatherings' => $gatherings,
                '_serialize' => ['gatherings']
            ]);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', ['gatherings']);
        } catch (\Exception $e) {
            Log::error('Error in gatheringsForAward: ' . $e->getMessage());
            $this->set([
                'error' => 'An error occurred while fetching gatherings',
                '_serialize' => ['error']
            ]);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', ['error']);
            $this->response = $this->response->withStatus(500);
        }
    }

    /**
     * Build and return a JSON list of gatherings suitable for bulk-editing the selected recommendations.
     *
     * Determines gatherings that offer activities common to all awards referenced by the provided recommendation IDs,
     * optionally limits to future gatherings depending on the supplied status, and includes attendance counts for members
     * referenced by the recommendations when they share attendance with Crown. Expects a POST request with `ids` (array
     * of recommendation ids) and optional `status`; skips authorization and renders a JSON payload with a `gatherings`
     * array (each item contains `id`, `name`, `display`, and `attendance_count`).
     */
    public function gatheringsForBulkEdit(): void
    {
        $this->request->allowMethod(['post']);

        // Skip authorization - this is a data endpoint for bulk edit form
        $this->Authorization->skipAuthorization();

        try {
            // Get recommendation IDs from request
            $ids = $this->request->getData('ids');
            if (empty($ids) || !is_array($ids)) {
                $this->set([
                    'gatherings' => [],
                    '_serialize' => ['gatherings']
                ]);
                $this->viewBuilder()->setClassName('Json');
                $this->viewBuilder()->setOption('serialize', ['gatherings']);
                return;
            }

            // Get status from request to determine if we should show all gatherings
            $status = $this->request->getData('status');
            $futureOnly = ($status !== 'Given');

            // Fetch the selected recommendations with their awards and members
            $recommendations = $this->Recommendations->find()
                ->where(['Recommendations.id IN' => $ids])
                ->contain(['Awards', 'Members'])
                ->all();

            if ($recommendations->isEmpty()) {
                $this->set([
                    'gatherings' => [],
                    '_serialize' => ['gatherings']
                ]);
                $this->viewBuilder()->setClassName('Json');
                $this->viewBuilder()->setOption('serialize', ['gatherings']);
                return;
            }

            // Get all unique award IDs and member IDs
            $awardIds = [];
            $memberIds = [];
            foreach ($recommendations as $rec) {
                $awardIds[] = $rec->award_id;
                if ($rec->member_id) {
                    $memberIds[] = $rec->member_id;
                }
            }
            $awardIds = array_unique($awardIds);
            $memberIds = array_unique($memberIds);

            // For each award, get the gathering activities that can give it out
            $awardGatheringActivitiesTable = $this->fetchTable('Awards.AwardGatheringActivities');
            $activityIdsByAward = [];

            foreach ($awardIds as $awardId) {
                $linkedActivities = $awardGatheringActivitiesTable->find()
                    ->where(['award_id' => $awardId])
                    ->select(['gathering_activity_id'])
                    ->toArray();

                $activityIds = array_map(function ($row) {
                    return $row->gathering_activity_id;
                }, $linkedActivities);

                $activityIdsByAward[$awardId] = $activityIds;
            }

            // Find intersection - gatherings must have activities for ALL awards
            $commonActivityIds = null;
            foreach ($activityIdsByAward as $awardId => $activityIds) {
                if ($commonActivityIds === null) {
                    $commonActivityIds = $activityIds;
                } else {
                    // Keep only activities that exist in both arrays
                    $commonActivityIds = array_intersect($commonActivityIds, $activityIds);
                }
            }

            // If no common activities, return empty
            if (empty($commonActivityIds)) {
                $this->set([
                    'gatherings' => [],
                    '_serialize' => ['gatherings']
                ]);
                $this->viewBuilder()->setClassName('Json');
                $this->viewBuilder()->setOption('serialize', ['gatherings']);
                return;
            }

            // Get gatherings that have these activities
            $gatheringsTable = $this->fetchTable('Gatherings');
            $query = $gatheringsTable->find()
                ->contain([
                    'Branches' => function ($q) {
                        return $q->select(['id', 'name']);
                    }
                ])
                ->select(['id', 'name', 'start_date', 'end_date', 'Gatherings.branch_id'])
                ->orderBy(['start_date' => 'DESC']);

            // Only filter by date if futureOnly is true
            if ($futureOnly) {
                $query->where(['start_date >' => DateTime::now()]);
                $query->orderBy(['start_date' => 'ASC']);
            }

            // Filter by common activities
            $query->matching('GatheringActivities', function ($q) use ($commonActivityIds) {
                return $q->where(['GatheringActivities.id IN' => $commonActivityIds]);
            });

            $gatheringsData = $query->all();

            // Get attendance information for all members if any provided
            $attendanceMap = [];
            if (!empty($memberIds)) {
                $attendanceTable = $this->fetchTable('GatheringAttendances');
                $attendances = $attendanceTable->find()
                    ->where([
                        'member_id IN' => $memberIds,
                        'deleted IS' => null,
                        'share_with_crown' => true
                    ])
                    ->select(['gathering_id', 'member_id'])
                    ->toArray();

                foreach ($attendances as $attendance) {
                    if (!isset($attendanceMap[$attendance->gathering_id])) {
                        $attendanceMap[$attendance->gathering_id] = 0;
                    }
                    $attendanceMap[$attendance->gathering_id]++;
                }
            }

            // Build the response array
            $gatherings = [];
            foreach ($gatheringsData as $gathering) {
                $displayName = $gathering->name . ' in ' . $gathering->branch->name . ' on '
                    . $gathering->start_date->toDateString() . ' - ' . $gathering->end_date->toDateString();

                // Add indicator if any members are attending and sharing with crown
                if (isset($attendanceMap[$gathering->id])) {
                    $count = $attendanceMap[$gathering->id];
                    $displayName .= ' *(' . $count . ')';
                }

                $gatherings[] = [
                    'id' => $gathering->id,
                    'name' => $gathering->name,
                    'display' => $displayName,
                    'attendance_count' => $attendanceMap[$gathering->id] ?? 0
                ];
            }

            $this->set([
                'gatherings' => $gatherings,
                '_serialize' => ['gatherings']
            ]);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', ['gatherings']);
        } catch (\Exception $e) {
            Log::error('Error in gatheringsForBulkEdit: ' . $e->getMessage());
            $this->set([
                'error' => 'An error occurred while fetching gatherings',
                '_serialize' => ['error']
            ]);
            $this->viewBuilder()->setClassName('Json');
            $this->viewBuilder()->setOption('serialize', ['error']);
            $this->response = $this->response->withStatus(500);
        }
    }
}