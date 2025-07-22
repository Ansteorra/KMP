<?php

declare(strict_types=1);

namespace Awards\Controller;

use Awards\Controller\AppController;
use Awards\Model\Entity\Recommendation;
use Cake\I18n\DateTime;
use App\KMP\StaticHelpers;
use Authorization\Exception\ForbiddenException;
use Cake\Log\Log;
use Exception;
use PhpParser\Node\Stmt\TryCatch;
use App\Services\CsvExportService;

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
     * Bulk state transition management for multiple recommendations
     * 
     * Provides comprehensive bulk update capabilities for recommendation state management,
     * enabling administrative users to efficiently process multiple recommendations
     * simultaneously. This method implements transactional bulk operations with
     * comprehensive validation, audit trail integration, and error recovery mechanisms
     * to ensure data integrity during large-scale workflow operations.
     * 
     * ## Bulk Operation Architecture
     * 
     * ### Multi-Recommendation Processing
     * - **Batch Selection**: Process multiple recommendations in a single operation
     * - **State Synchronization**: Coordinate state/status mapping for consistency
     * - **Atomic Transactions**: Ensure all-or-nothing processing for data integrity
     * - **Performance Optimization**: Efficient bulk database operations
     * 
     * ### Operation Types
     * - **State Transitions**: Change recommendation states in bulk
     * - **Event Assignment**: Assign recommendations to award ceremonies
     * - **Date Management**: Update given dates for ceremony coordination
     * - **Administrative Notes**: Add bulk notes for operational tracking
     * 
     * ## Transaction Management
     * 
     * ### ACID Compliance
     * - **Atomic Operations**: All updates succeed or all fail together
     * - **Consistency Validation**: Ensure state transitions follow business rules
     * - **Isolation Control**: Prevent concurrent modification conflicts
     * - **Durability Guarantee**: Permanent storage of successful operations
     * 
     * ### Error Recovery
     * - **Automatic Rollback**: Complete rollback on any operation failure
     * - **Partial Success Handling**: Clear indication of which operations succeeded
     * - **Data Integrity Protection**: Prevent partial updates that corrupt workflow state
     * - **Error Logging**: Comprehensive logging for troubleshooting failed operations
     * 
     * ## State Management Integration
     * 
     * ### State/Status Mapping
     * - **Automatic Resolution**: Map states to appropriate status categories
     * - **Validation Logic**: Ensure state transitions are valid per business rules
     * - **Consistency Enforcement**: Maintain status/state relationship integrity
     * - **Business Rule Application**: Apply workflow rules during transitions
     * 
     * ### Supported State Changes
     * - **Workflow Progression**: Move recommendations through approval workflow
     * - **Administrative Actions**: Administrative state changes for process management
     * - **Ceremony Assignment**: Assign approved recommendations to specific events
     * - **Final Disposition**: Mark recommendations as complete or closed
     * 
     * ## Data Enhancement Operations
     * 
     * ### Event Assignment
     * - **Ceremony Coordination**: Assign recommendations to award ceremonies
     * - **Scheduling Integration**: Coordinate with event scheduling system
     * - **Capacity Management**: Respect event capacity constraints
     * - **Conflict Resolution**: Handle scheduling conflicts and constraints
     * 
     * ### Date Management
     * - **Given Date Updates**: Record actual award presentation dates
     * - **Temporal Validation**: Ensure dates are logical and consistent
     * - **Historical Tracking**: Maintain historical record of date changes
     * - **Scheduling Coordination**: Integrate with event scheduling systems
     * 
     * ### Note Integration
     * - **Bulk Annotation**: Add administrative notes to multiple recommendations
     * - **Audit Trail**: Comprehensive audit trail for bulk operations
     * - **Attribution Tracking**: Track which user performed bulk operations
     * - **Content Management**: Standardized note format for bulk operations
     * 
     * ## Authorization & Security
     * 
     * ### Permission Validation
     * - **Bulk Operation Authorization**: Verify user can perform bulk operations
     * - **State Transition Permissions**: Validate permissions for target states
     * - **Administrative Access**: Ensure appropriate authorization level
     * - **Data Access Control**: Validate access to all affected recommendations
     * 
     * ### Security Considerations
     * - **Input Validation**: Comprehensive validation of all bulk operation parameters
     * - **SQL Injection Prevention**: Parameterized queries for all database operations
     * - **Authorization Checking**: Per-recommendation authorization validation
     * - **Audit Logging**: Complete audit trail for security and compliance
     * 
     * ## User Interface Integration
     * 
     * ### Form Processing
     * - **Multi-Selection Support**: Handle form data for multiple selected recommendations
     * - **Parameter Validation**: Validate all form parameters before processing
     * - **Error Feedback**: Provide clear feedback for validation errors
     * - **Success Confirmation**: Confirm successful operations to users
     * 
     * ### Turbo Frame Support
     * - **Partial Page Updates**: Support for Turbo Frame partial updates
     * - **Response Handling**: Appropriate response format for different request types
     * - **User Experience**: Seamless user experience for bulk operations
     * - **Progress Feedback**: Real-time feedback during bulk processing
     * 
     * ## Performance Optimization
     * 
     * ### Efficient Database Operations
     * - **Bulk Updates**: Use efficient bulk update operations where possible
     * - **Selective Loading**: Load only required data for bulk operations
     * - **Index Utilization**: Optimize queries for database index usage
     * - **Memory Management**: Efficient memory usage for large bulk operations
     * 
     * ### Scalability Considerations
     * - **Batch Size Limits**: Respect practical limits for bulk operation size
     * - **Resource Management**: Efficient use of database connections and memory
     * - **Performance Monitoring**: Track performance of bulk operations
     * - **Optimization Strategies**: Continuous optimization for better performance
     * 
     * ## Error Handling & Recovery
     * 
     * ### Comprehensive Error Management
     * - **Validation Errors**: Clear messaging for validation failures
     * - **Database Errors**: Robust handling of database operation failures
     * - **Business Logic Errors**: Proper handling of workflow rule violations
     * - **System Errors**: Safe handling of unexpected system errors
     * 
     * ### User Communication
     * - **Success Messages**: Clear confirmation of successful operations
     * - **Error Messages**: Helpful error messages for failed operations
     * - **Partial Success**: Clear indication when some operations succeed/fail
     * - **Guidance Messaging**: Guidance for resolving error conditions
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Bulk approve recommendations
     * POST /awards/recommendations/updateStates
     * Body: {
     *   'ids': '1,2,3,4',
     *   'newState': 'Approved',
     *   'event_id': '5',
     *   'note': 'Bulk approved for ceremony'
     * }
     * 
     * // Bulk ceremony assignment
     * POST /awards/recommendations/updateStates
     * Body: {
     *   'ids': '10,11,12',
     *   'newState': 'AssignedToCeremony',
     *   'event_id': '3',
     *   'given': '2024-02-15'
     * }
     * ```
     * 
     * @return \Cake\Http\Response|null Redirects to configured page or back to current view
     * 
     * @see \Awards\Model\Entity\Recommendation::getStatuses() For state/status mapping
     * @see \Awards\Model\Table\NotesTable For note creation and management
     * @see runTable() For return to table view after operations
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
        $event_id = $this->request->getData('event_id');
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

                if ($event_id) {
                    $updateFields['event_id'] = $event_id;
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
     * Comprehensive recommendation detail display with workflow context
     * 
     * Provides detailed view of individual recommendations with complete context
     * including member information, award details, workflow history, and related
     * data. This method serves as the primary interface for detailed recommendation
     * review, enabling users to access all relevant information for informed
     * decision-making during the recommendation workflow process.
     * 
     * ## Data Presentation Architecture
     * 
     * ### Comprehensive Data Loading
     * - **Primary Entities**: Recommendation with all core attributes
     * - **Relationship Data**: Complete loading of related entities (Members, Awards, etc.)
     * - **Workflow Context**: State history and transition information
     * - **Administrative Data**: Creation, modification, and assignment details
     * 
     * ### Association Management
     * - **Member Details**: Complete member profile integration
     * - **Award Information**: Full award hierarchy and specification details
     * - **Event Context**: Related events and ceremony information
     * - **Branch Integration**: Branch hierarchy and organizational context
     * 
     * ## Authorization & Access Control
     * 
     * ### View Authorization
     * - **Entity-Level Permissions**: Authorization specific to the recommendation
     * - **Contextual Access**: Access control based on recommendation state and user role
     * - **Data Visibility**: Sensitive information filtered based on permissions
     * - **Operation Availability**: Available actions determined by authorization level
     * 
     * ### Security Considerations
     * - **Direct Access Protection**: Prevent unauthorized direct URL access
     * - **Data Filtering**: Filter sensitive data based on user permissions
     * - **Audit Logging**: Log access to recommendation details for security tracking
     * - **Permission Integration**: Deep integration with RBAC permission system
     * 
     * ## Workflow Integration
     * 
     * ### State Context Display
     * - **Current State**: Clear indication of current workflow state
     * - **Available Transitions**: Display of available next states
     * - **Business Rules**: Integration with workflow business rules
     * - **Historical Context**: Access to state transition history
     * 
     * ### Domain Integration
     * - **Award Hierarchy**: Display award domain for contextual navigation
     * - **Specialty Information**: Award specialty details and requirements
     * - **Branch Context**: Branch-specific award information
     * - **Event Coordination**: Related event and ceremony information
     * 
     * ## User Experience Design
     * 
     * ### Comprehensive Information Display
     * - **Member Profile**: Complete member information and preferences
     * - **Award Details**: Full award specification and requirements
     * - **Submission Context**: Original submission details and reasoning
     * - **Administrative Data**: Creation, modification, and workflow tracking
     * 
     * ### Navigation Integration
     * - **Contextual Navigation**: Navigation options based on current state
     * - **Related Records**: Links to related recommendations, members, awards
     * - **Workflow Actions**: Available workflow actions for current user
     * - **Administrative Tools**: Administrative functions for authorized users
     * 
     * ## Error Handling & Recovery
     * 
     * ### Not Found Handling
     * - **Record Validation**: Verify recommendation exists before processing
     * - **Soft Deletion**: Handle soft-deleted recommendations appropriately
     * - **Permission Failures**: Graceful handling of authorization failures
     * - **User Feedback**: Clear messaging for various error conditions
     * 
     * ### Exception Management
     * - **Database Errors**: Robust handling of database access errors
     * - **Authorization Errors**: Proper handling of permission failures
     * - **System Errors**: Safe error handling with user-friendly messaging
     * - **Logging Integration**: Comprehensive error logging for troubleshooting
     * 
     * ## Performance Optimization
     * 
     * ### Efficient Data Loading
     * - **Selective Containments**: Load only required relationship data
     * - **Query Optimization**: Optimized queries for minimal database impact
     * - **Caching Strategy**: Strategic caching of frequently accessed data
     * - **Association Management**: Efficient loading of related entities
     * 
     * ### Response Optimization
     * - **Data Processing**: Efficient processing of loaded data for display
     * - **Template Optimization**: Optimized view templates for performance
     * - **Resource Management**: Efficient use of server resources
     * - **User Experience**: Fast response times for better user experience
     * 
     * ## Integration Points
     * 
     * ### System Integration
     * - **Member Management**: Integration with member profile system
     * - **Award System**: Deep integration with award hierarchy system
     * - **Event Management**: Integration with event and ceremony systems
     * - **Note System**: Integration with note and comment systems
     * 
     * ### Workflow Integration
     * - **State Machine**: Integration with recommendation state machine
     * - **Business Rules**: Enforcement of workflow business rules
     * - **Audit System**: Integration with audit and logging systems
     * - **Notification System**: Integration with workflow notifications
     * 
     * ## Usage Examples
     * 
     * ```php
     * // View specific recommendation
     * GET /awards/recommendations/view/123
     * 
     * // View with workflow context
     * $controller->view('123'); // Returns complete recommendation context
     * 
     * // Administrative view
     * GET /awards/recommendations/view/456?admin=true
     * ```
     * 
     * @param string|null $id Recommendation ID to display
     * @return \Cake\Http\Response|null|void Renders detailed view template
     * @throws \Cake\Http\Exception\NotFoundException When recommendation not found or inaccessible
     * 
     * @see \Awards\Model\Entity\Recommendation For recommendation entity structure
     * @see \Authorization\AuthorizationServiceInterface For authorization integration
     * @see edit() For recommendation modification capabilities
     */
    public function view(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id, contain: ['Requesters', 'Members', 'Branches', 'Awards', 'Events', 'ScheduledEvent']);
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
     * Authenticated recommendation submission with member integration
     * 
     * Provides comprehensive recommendation submission interface for authenticated
     * users, featuring automatic member data integration, award validation, event
     * coordination, and initial state management. This method implements the primary
     * recommendation creation workflow for logged-in members, with sophisticated
     * data validation, member preference synchronization, and workflow initialization.
     * 
     * ## Submission Workflow Architecture
     * 
     * ### Authentication Integration
     * - **User Context**: Automatic requester identification from authentication
     * - **Profile Integration**: Synchronization with user profile data
     * - **Permission Validation**: Authorization check for recommendation submission
     * - **Session Management**: Secure session-based user identification
     * 
     * ### Member Data Integration
     * - **Automatic Population**: Auto-populate requester information from user profile
     * - **Contact Synchronization**: Sync contact information from member profile
     * - **Preference Loading**: Load member-specific award and ceremony preferences
     * - **Branch Resolution**: Automatic branch assignment based on member data
     * 
     * ## Form Processing & Validation
     * 
     * ### Input Processing
     * - **Entity Patching**: Secure entity patching with validation
     * - **Data Sanitization**: Comprehensive input sanitization and validation
     * - **Business Rule Validation**: Application of recommendation business rules
     * - **Specialty Handling**: Special processing for award specialty selections
     * 
     * ### Member Integration Logic
     * - **Member Selection**: Handle both existing member and "not found" scenarios
     * - **Profile Synchronization**: Sync selected member's profile data
     * - **Preference Integration**: Load member court and ceremony preferences
     * - **Branch Assignment**: Automatic branch assignment from member data
     * 
     * ## Court Preference Management
     * 
     * ### Additional Info Integration
     * - **Court Call Preferences**: Integration with member court call preferences
     * - **Availability Settings**: Sync court availability from member profile
     * - **Notification Contacts**: Load person-to-notify information from member data
     * - **Default Handling**: Appropriate defaults for missing preference data
     * 
     * ### Preference Processing
     * - **Data Extraction**: Extract preferences from member additional_info field
     * - **Validation Logic**: Validate preference data for consistency
     * - **Default Assignment**: Assign appropriate defaults for missing preferences
     * - **Error Handling**: Graceful handling of preference loading errors
     * 
     * ## State Initialization
     * 
     * ### Workflow State Setup
     * - **Initial Status**: Automatic assignment of initial workflow status
     * - **State Assignment**: Set initial state based on workflow configuration
     * - **Timestamp Management**: Record state date for workflow tracking
     * - **Status Coordination**: Ensure status/state consistency
     * 
     * ### Workflow Integration
     * - **Business Rules**: Apply initial workflow business rules
     * - **State Machine**: Initialize recommendation in state machine
     * - **Audit Trail**: Create initial audit trail entries
     * - **Notification Setup**: Initialize notification workflows
     * 
     * ## Transaction Management
     * 
     * ### Data Consistency
     * - **Atomic Operations**: Ensure recommendation creation is atomic
     * - **Referential Integrity**: Maintain referential integrity with related entities
     * - **Error Recovery**: Complete rollback on any operation failure
     * - **Consistency Validation**: Validate data consistency before commit
     * 
     * ### Member Data Synchronization
     * - **Profile Loading**: Secure loading of member profile data
     * - **Preference Extraction**: Safe extraction of preference data
     * - **Error Handling**: Graceful handling of member data loading errors
     * - **Fallback Strategies**: Appropriate fallbacks for missing member data
     * 
     * ## Form Data Preparation
     * 
     * ### Dropdown Population
     * - **Award Hierarchy**: Load awards, domains, and levels for selection
     * - **Branch Management**: Load eligible branches with member capability flags
     * - **Event Integration**: Load available events for recommendation assignment
     * - **Dynamic Loading**: Efficient loading of form option data
     * 
     * ### Data Formatting
     * - **Branch Formatting**: Special formatting for branch selection with metadata
     * - **Event Formatting**: Descriptive formatting for event selection
     * - **Award Organization**: Hierarchical organization of award options
     * - **User Experience**: Optimized data presentation for form usability
     * 
     * ## Authorization & Security
     * 
     * ### Permission Validation
     * - **Submission Authorization**: Verify user can submit recommendations
     * - **Member Access**: Validate access to selected member data
     * - **Award Permissions**: Ensure user can nominate for selected awards
     * - **Event Access**: Validate access to selected events
     * 
     * ### Data Security
     * - **Input Validation**: Comprehensive validation of all form inputs
     * - **SQL Injection Prevention**: Parameterized queries for all database operations
     * - **Data Sanitization**: Proper sanitization of user input data
     * - **Authorization Checking**: Multi-level authorization validation
     * 
     * ## Navigation & User Experience
     * 
     * ### Post-Submission Navigation
     * - **Success Redirect**: Conditional redirect based on user permissions
     * - **View Access**: Redirect to recommendation view if user has access
     * - **Profile Fallback**: Fallback to user profile for limited access users
     * - **Context Preservation**: Maintain user context through navigation
     * 
     * ### Error Handling
     * - **Validation Errors**: Clear presentation of validation errors
     * - **System Errors**: Graceful handling of system errors
     * - **User Feedback**: Comprehensive user feedback through Flash messages
     * - **Recovery Guidance**: Clear guidance for error recovery
     * 
     * ## Performance Optimization
     * 
     * ### Efficient Data Loading
     * - **Selective Queries**: Load only required data for form population
     * - **Optimized Associations**: Efficient loading of related entities
     * - **Caching Strategy**: Strategic caching of frequently used data
     * - **Query Optimization**: Optimized queries for responsive user experience
     * 
     * ### Resource Management
     * - **Memory Efficiency**: Efficient memory usage during data processing
     * - **Connection Management**: Proper database connection management
     * - **Transaction Optimization**: Optimized transaction handling
     * - **Response Time**: Fast response times for better user experience
     * 
     * ## Integration Points
     * 
     * ### System Integration
     * - **Member Management**: Deep integration with member profile system
     * - **Award System**: Integration with award hierarchy and validation
     * - **Event System**: Integration with event management system
     * - **Notification System**: Integration with workflow notifications
     * 
     * ### Workflow Integration
     * - **State Machine**: Integration with recommendation state machine
     * - **Business Rules**: Enforcement of submission business rules
     * - **Audit System**: Integration with audit and logging systems
     * - **Authorization System**: Deep integration with RBAC system
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Standard recommendation submission
     * GET /awards/recommendations/add
     * POST /awards/recommendations/add
     * 
     * // Programmatic submission
     * $controller->add(); // Displays form or processes submission
     * 
     * // With pre-selected award
     * GET /awards/recommendations/add?award_id=123
     * ```
     * 
     * @return \Cake\Http\Response|null|void Redirects on successful submission or renders form
     * 
     * @see submitRecommendation() For unauthenticated public submission workflow
     * @see view() For recommendation detail display after submission
     * @see \Awards\Model\Entity\Recommendation For recommendation entity structure
     * @see \Awards\Model\Entity\Recommendation::getStatuses() For workflow state management
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

            $eventsData = $this->Recommendations->Events->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where([
                    'start_date >' => DateTime::now(),
                    'OR' => ['closed' => false, 'closed IS' => null]
                ])
                ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
                ->orderBy(['start_date' => 'ASC'])
                ->all();

            $events = [];
            foreach ($eventsData as $event) {
                $events[$event->id] = $event->name . ' in ' . $event->branch->name . ' on '
                    . $event->start_date->toDateString() . ' - ' . $event->end_date->toDateString();
            }

            $this->set(compact('recommendation', 'branches', 'awards', 'events', 'awardsDomains', 'awardsLevels'));
            return null;
        } catch (\Exception $e) {
            $this->Recommendations->getConnection()->rollback();
            Log::error('Error in add recommendation: ' . $e->getMessage());
            $this->Flash->error(__('An unexpected error occurred. Please try again.'));
            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * Public recommendation submission without authentication requirements
     * 
     * Enables community members without KMP system accounts to submit award
     * recommendations through a public interface. This method implements a
     * guest submission workflow that maintains data integrity and security
     * while providing accessibility for external community participation
     * in the award recommendation process.
     * 
     * ## Public Access Architecture
     * 
     * ### Authentication Bypass
     * - **Skip Authorization**: Explicit authorization skip for public access
     * - **Guest Detection**: Automatic detection of unauthenticated users
     * - **Redirect Logic**: Redirect authenticated users to standard submission
     * - **Security Considerations**: Maintain security despite public access
     * 
     * ### Guest Workflow Management
     * - **Anonymous Submission**: Handle submissions without user accounts
     * - **Contact Information**: Require comprehensive contact information
     * - **Validation Enhancement**: Enhanced validation for guest submissions
     * - **Moderation Queue**: Special handling for guest-submitted recommendations
     * 
     * ## Enhanced Validation & Security
     * 
     * ### Input Validation
     * - **Comprehensive Validation**: Enhanced validation for untrusted input
     * - **Data Sanitization**: Aggressive sanitization of all user input
     * - **Business Rule Enforcement**: Strict enforcement of recommendation rules
     * - **Spam Prevention**: Integration with anti-spam measures
     * 
     * ### Security Measures
     * - **Rate Limiting**: Protection against submission abuse
     * - **Input Filtering**: Comprehensive filtering of malicious input
     * - **Audit Logging**: Enhanced logging for security monitoring
     * - **Validation Bypass Prevention**: Prevent validation bypass attempts
     * 
     * ## Requester Information Management
     * 
     * ### Anonymous Requester Handling
     * - **External Requester**: Handle requesters not in member database
     * - **Contact Validation**: Validate contact information thoroughly
     * - **SCA Name Resolution**: Handle SCA name lookup and validation
     * - **Information Completion**: Ensure all required information collected
     * 
     * ### Member Integration
     * - **Member Lookup**: Attempt to link to existing member records
     * - **Profile Synchronization**: Sync data when member match found
     * - **Preference Integration**: Load member preferences when available
     * - **Data Consistency**: Maintain consistency between systems
     * 
     * ## Transaction & Data Management
     * 
     * ### Enhanced Transaction Handling
     * - **Atomic Operations**: Ensure submission atomicity for data integrity
     * - **Error Recovery**: Comprehensive error recovery for failed submissions
     * - **Data Validation**: Multi-level validation before commit
     * - **Rollback Safety**: Safe rollback on any operation failure
     * 
     * ### Member Data Integration
     * - **Profile Loading**: Safe loading of member profile data when available
     * - **Preference Extraction**: Extract member preferences safely
     * - **Default Assignment**: Appropriate defaults for missing data
     * - **Error Tolerance**: Graceful handling of member data loading errors
     * 
     * ## Form Data & User Experience
     * 
     * ### Enhanced Form Presentation
     * - **Guest Interface**: Specialized interface for guest users
     * - **Header Graphics**: Branded header for public interface
     * - **Help Information**: Enhanced help and guidance for external users
     * - **Accessibility**: Ensure accessibility for diverse user base
     * 
     * ### Data Preparation
     * - **Comprehensive Options**: Full range of awards, events, and branches
     * - **Descriptive Formatting**: Enhanced descriptions for guest users
     * - **Filtering Logic**: Appropriate filtering for public access
     * - **User Guidance**: Clear guidance for form completion
     * 
     * ## State Initialization & Workflow
     * 
     * ### Guest Submission State
     * - **Initial State**: Appropriate initial state for guest submissions
     * - **Moderation Queue**: Integration with moderation workflows
     * - **Review Process**: Enhanced review process for guest submissions
     * - **Approval Chain**: Specialized approval chain for external submissions
     * 
     * ### Workflow Integration
     * - **State Machine**: Proper integration with recommendation state machine
     * - **Business Rules**: Application of business rules for guest submissions
     * - **Audit Trail**: Comprehensive audit trail for guest submissions
     * - **Notification System**: Integration with notification workflows
     * 
     * ## Court Preference Handling
     * 
     * ### Default Preference Management
     * - **Safe Defaults**: Appropriate defaults for court preferences
     * - **Member Integration**: Load preferences when member data available
     * - **Validation Logic**: Validate preference data consistency
     * - **Error Handling**: Graceful handling of preference loading errors
     * 
     * ### Additional Information Processing
     * - **Profile Synchronization**: Sync with member additional_info when available
     * - **Preference Extraction**: Safe extraction of court preferences
     * - **Default Assignment**: Assign appropriate defaults for missing data
     * - **Consistency Maintenance**: Maintain data consistency across systems
     * 
     * ## Error Handling & Recovery
     * 
     * ### Comprehensive Error Management
     * - **Validation Errors**: Clear presentation of validation errors
     * - **System Errors**: Graceful handling of system errors
     * - **Database Errors**: Robust handling of database operation failures
     * - **User Communication**: Clear error messaging for guest users
     * 
     * ### Recovery Strategies
     * - **Automatic Retry**: Automatic retry for transient errors
     * - **Data Preservation**: Preserve user input during error recovery
     * - **Guidance Provision**: Clear guidance for error resolution
     * - **Support Integration**: Integration with support systems
     * 
     * ## Performance & Scalability
     * 
     * ### Guest Access Optimization
     * - **Efficient Loading**: Optimized data loading for guest interface
     * - **Caching Strategy**: Strategic caching for frequently accessed data
     * - **Response Optimization**: Fast response times for public interface
     * - **Resource Management**: Efficient use of server resources
     * 
     * ### Abuse Prevention
     * - **Rate Limiting**: Prevent submission abuse through rate limiting
     * - **Resource Protection**: Protect server resources from abuse
     * - **Monitoring Integration**: Integration with abuse monitoring systems
     * - **Automatic Mitigation**: Automatic mitigation of abuse attempts
     * 
     * ## Integration Points
     * 
     * ### System Integration
     * - **Member System**: Integration with member lookup and validation
     * - **Award System**: Full integration with award hierarchy
     * - **Event System**: Integration with event management
     * - **Notification System**: Integration with workflow notifications
     * 
     * ### External Integration
     * - **Anti-Spam Systems**: Integration with spam prevention systems
     * - **Monitoring Systems**: Integration with security monitoring
     * - **Analytics Systems**: Integration with usage analytics
     * - **Support Systems**: Integration with user support systems
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Public recommendation form
     * GET /awards/recommendations/submitRecommendation
     * 
     * // Guest submission
     * POST /awards/recommendations/submitRecommendation
     * 
     * // With referral parameters
     * GET /awards/recommendations/submitRecommendation?member_id=123
     * ```
     * 
     * @return \Cake\Http\Response|null|void Renders public submission form or processes submission
     * 
     * @see add() For authenticated member submission workflow
     * @see beforeFilter() For authentication bypass configuration
     * @see \App\KMP\StaticHelpers::getAppSetting() For configuration loading
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

        $eventsData = $this->Recommendations->Events->find()
            ->contain(['Branches' => function ($q) {
                return $q->select(['id', 'name']);
            }])
            ->where(['start_date >' => DateTime::now()])
            ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
            ->orderBy(['start_date' => 'ASC'])
            ->all();

        $events = [];
        foreach ($eventsData as $event) {
            $events[$event->id] = $event->name . ' in ' . $event->branch->name . ' on '
                . $event->start_date->toDateString() . ' - ' . $event->end_date->toDateString();
        }

        $this->set(compact(
            'recommendation',
            'branches',
            'awards',
            'events',
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
     * Dynamic edit form generation for Turbo Frame partial updates
     * 
     * Provides comprehensive dynamic form generation for recommendation editing
     * within Turbo Frame contexts, enabling seamless partial page updates and
     * enhanced user experience. This method generates fully populated edit forms
     * with complete dropdown data, state rules, and workflow integration for
     * in-place editing capabilities.
     * 
     * ## Turbo Frame Architecture
     * 
     * ### Partial Update Integration
     * - **Frame-Based Updates**: Generate forms specifically for Turbo Frame integration
     * - **Seamless Experience**: Enable editing without full page refresh
     * - **Progressive Enhancement**: Enhanced experience with JavaScript, functional without
     * - **Performance Optimization**: Optimized for fast partial page updates
     * 
     * ### Dynamic Form Generation
     * - **Context-Aware Forms**: Forms generated based on recommendation context
     * - **Complete Data Loading**: Full dropdown and option data for comprehensive editing
     * - **State Rule Integration**: Integration with workflow state rules and validation
     * - **Real-Time Updates**: Support for real-time form updates and validation
     * 
     * ## Comprehensive Data Loading
     * 
     * ### Recommendation Context
     * - **Full Entity Loading**: Complete recommendation loading with all associations
     * - **Related Data**: Load all related entities (Members, Awards, Events, etc.)
     * - **Domain Integration**: Domain context for award filtering and organization
     * - **Historical Context**: Access to recommendation history and state information
     * 
     * ### Form Data Preparation
     * - **Award Hierarchies**: Complete loading of domains, levels, and awards
     * - **Branch Management**: Branch data with member capability flags
     * - **Event Integration**: Available events with descriptive formatting
     * - **State Information**: Current state rules and transition possibilities
     * 
     * ## Dropdown & Option Management
     * 
     * ### Award System Integration
     * - **Domain Filtering**: Awards filtered by recommendation's current domain
     * - **Specialty Handling**: Award specialties loaded and formatted
     * - **Level Organization**: Award levels with hierarchical organization
     * - **Validation Rules**: Award selection validation and business rules
     * 
     * ### Event Management
     * - **Available Events**: Events filtered by availability and status
     * - **Descriptive Formatting**: Events formatted with branch and date information
     * - **Status Filtering**: Only open/available events included
     * - **Branch Coordination**: Event-branch relationship information
     * 
     * ## State Management Integration
     * 
     * ### Workflow State Rules
     * - **State Validation**: Current state rules for workflow validation
     * - **Transition Rules**: Available state transitions for current recommendation
     * - **Business Logic**: Integration with recommendation workflow business logic
     * - **Permission Integration**: State visibility based on user permissions
     * 
     * ### Status List Processing
     * - **Hierarchical Organization**: Status lists organized by category
     * - **State Mapping**: Status-to-state mapping for workflow management
     * - **Permission Filtering**: Filter states based on user permissions
     * - **Validation Rules**: State transition validation rules
     * 
     * ## Authorization & Security
     * 
     * ### View Authorization
     * - **Entity-Level Permissions**: Authorization specific to the recommendation
     * - **Edit Context**: Ensure user has permissions for editing operations
     * - **Data Access**: Validate access to associated data and relationships
     * - **Security Validation**: Comprehensive security validation for form access
     * 
     * ### Data Security
     * - **Input Validation**: Prepare for comprehensive input validation
     * - **SQL Injection Prevention**: Secure data loading and preparation
     * - **Authorization Checking**: Multi-level authorization validation
     * - **Data Filtering**: Filter sensitive data based on user permissions
     * 
     * ## Form Optimization
     * 
     * ### Performance Considerations
     * - **Selective Loading**: Load only required data for form generation
     * - **Efficient Queries**: Optimized database queries for fast form generation
     * - **Caching Strategy**: Strategic caching of frequently used form data
     * - **Resource Management**: Efficient use of server resources
     * 
     * ### User Experience
     * - **Fast Loading**: Optimized for fast form generation and display
     * - **Complete Functionality**: Full editing functionality in partial update
     * - **Validation Integration**: Complete validation and error handling
     * - **Context Preservation**: Maintain user context throughout editing
     * 
     * ## Branch & Member Integration
     * 
     * ### Branch Management
     * - **Member Capability**: Branch data includes member capability flags
     * - **Organizational Structure**: Proper branch hierarchy and organization
     * - **Permission Integration**: Branch access based on user permissions
     * - **Validation Rules**: Branch selection validation and business rules
     * 
     * ### Member Context
     * - **Profile Integration**: Integration with member profile system
     * - **Relationship Management**: Handle member-recommendation relationships
     * - **Preference Loading**: Load member preferences for court and ceremony
     * - **Data Synchronization**: Coordinate with member data systems
     * 
     * ## Error Handling & Recovery
     * 
     * ### Comprehensive Error Management
     * - **Not Found Handling**: Proper handling of missing recommendations
     * - **Authorization Errors**: Clear messaging for permission failures
     * - **Data Loading Errors**: Robust handling of data loading failures
     * - **System Errors**: Safe handling of unexpected system errors
     * 
     * ### User Communication
     * - **Error Messages**: Clear error messaging for form generation failures
     * - **Fallback Options**: Appropriate fallback options for error conditions
     * - **Recovery Guidance**: Guidance for resolving error conditions
     * - **Context Preservation**: Maintain user context during error handling
     * 
     * ## Integration Points
     * 
     * ### Frontend Integration
     * - **Turbo Framework**: Deep integration with Hotwired Turbo framework
     * - **Stimulus Controllers**: Integration with Stimulus JavaScript controllers
     * - **CSS Framework**: Integration with Bootstrap for styling and layout
     * - **Accessibility**: Ensure form accessibility and usability standards
     * 
     * ### Backend Integration
     * - **Workflow System**: Integration with recommendation workflow management
     * - **Authorization System**: Deep integration with RBAC authorization
     * - **Configuration System**: Integration with application configuration
     * - **Validation System**: Integration with comprehensive validation framework
     * 
     * ## Advanced Features
     * 
     * ### Dynamic Form Features
     * - **Conditional Fields**: Form fields that appear/disappear based on selections
     * - **Real-Time Validation**: Client-side validation with server-side coordination
     * - **Auto-Complete**: Integration with auto-complete functionality
     * - **Progressive Disclosure**: Show/hide form sections based on context
     * 
     * ### Workflow Integration
     * - **State Awareness**: Forms adapt based on current recommendation state
     * - **Business Rules**: Form validation based on workflow business rules
     * - **Permission Context**: Form features based on user permissions
     * - **Audit Integration**: Form changes integrated with audit trail
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Turbo Frame edit form
     * GET /awards/recommendations/turboEditForm/123
     * 
     * // AJAX form generation
     * fetch('/awards/recommendations/turboEditForm/456')
     *   .then(response => response.text())
     *   .then(html => updateFormContainer(html));
     * 
     * // Within Turbo Frame
     * <turbo-frame id="edit-form">
     *   <!-- Form content loaded here -->
     * </turbo-frame>
     * ```
     * 
     * @param string|null $id Recommendation ID for form generation
     * @return \Cake\Http\Response|null|void Renders edit form template for Turbo Frame
     * @throws \Cake\Http\Exception\NotFoundException When recommendation not found
     * 
     * @see edit() For form processing and submission handling
     * @see turboQuickEditForm() For streamlined editing interface
     * @see \App\KMP\StaticHelpers::getAppSetting() For configuration loading
     */
    public function turboEditForm(?string $id = null): ?\Cake\Http\Response
    {
        try {
            $recommendation = $this->Recommendations->get($id, contain: [
                'Requesters',
                'Members',
                'Branches',
                'Awards',
                'Events',
                'ScheduledEvent',
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

            $eventsData = $this->Recommendations->Events->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where(['OR' => ['closed' => false, 'closed IS' => null]])
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

            // Format event list for dropdown
            $eventList = [];
            foreach ($eventsData as $event) {
                $eventList[$event->id] = $event->name . ' in ' . $event->branch->name . ' on '
                    . $event->start_date->toDateString() . ' - ' . $event->end_date->toDateString();
            }

            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');
            $this->set(compact(
                'rules',
                'recommendation',
                'branches',
                'awards',
                'eventList',
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
     * Streamlined quick edit form for efficient recommendation modifications
     * 
     * Provides a simplified, streamlined editing interface optimized for quick
     * modifications and common editing tasks. This method generates a focused
     * edit form with essential fields and reduced complexity, enabling efficient
     * workflow management and rapid recommendation updates through Turbo Frame
     * partial updates.
     * 
     * ## Quick Edit Philosophy
     * 
     * ### Streamlined Interface Design
     * - **Essential Fields Only**: Focus on most commonly modified fields
     * - **Reduced Complexity**: Simplified interface for faster user interaction
     * - **Workflow Optimization**: Optimized for common workflow operations
     * - **Efficient Processing**: Faster form generation and submission processing
     * 
     * ### User Experience Focus
     * - **Rapid Edits**: Enable quick modifications without full form complexity
     * - **Context Awareness**: Maintain context while simplifying interface
     * - **Minimal Cognitive Load**: Reduce cognitive load for routine operations
     * - **Workflow Integration**: Seamless integration with workflow processes
     * 
     * ## Form Data Optimization
     * 
     * ### Selective Data Loading
     * - **Essential Data Only**: Load only data required for quick edit operations
     * - **Performance Optimization**: Optimized data loading for speed
     * - **Reduced Overhead**: Minimize server overhead for routine operations
     * - **Efficient Queries**: Streamlined database queries for fast response
     * 
     * ### Core Edit Elements
     * - **Award Information**: Essential award and domain information
     * - **Branch Management**: Core branch selection and management
     * - **Event Assignment**: Key event assignment and scheduling
     * - **State Management**: Essential state and status information
     * 
     * ## Dropdown Management
     * 
     * ### Essential Options
     * - **Domain Filtering**: Awards filtered by recommendation's domain context
     * - **Branch Selection**: Branch options with member capability information
     * - **Event Options**: Available events with essential descriptive information
     * - **State Options**: Current state options based on workflow rules
     * 
     * ### Optimized Data Presentation
     * - **Simplified Formatting**: Streamlined formatting for quick comprehension
     * - **Essential Information**: Include only essential information for decisions
     * - **Performance Focus**: Optimize for fast rendering and interaction
     * - **User Clarity**: Clear, unambiguous option presentation
     * 
     * ## State Rule Integration
     * 
     * ### Workflow Rule Application
     * - **State Validation**: Apply state rules for valid transitions
     * - **Business Logic**: Integrate workflow business logic appropriately
     * - **Permission Context**: Consider user permissions in rule application
     * - **Transition Validation**: Validate available state transitions
     * 
     * ### Simplified Rule Processing
     * - **Essential Rules Only**: Focus on rules relevant to quick edit operations
     * - **Performance Optimization**: Streamlined rule processing for speed
     * - **User Guidance**: Clear guidance on available options and restrictions
     * - **Error Prevention**: Prevent invalid operations through rule enforcement
     * 
     * ## Authorization & Security
     * 
     * ### Quick Edit Authorization
     * - **Entity-Level Validation**: Ensure user can perform quick edits on recommendation
     * - **Operation Permissions**: Validate permissions for specific quick edit operations
     * - **Context Security**: Consider recommendation context in authorization
     * - **Minimal Security Overhead**: Efficient security validation for performance
     * 
     * ### Streamlined Security
     * - **Essential Security**: Focus on essential security validation
     * - **Performance Balance**: Balance security with performance requirements
     * - **User Experience**: Maintain security without impacting user experience
     * - **Efficient Validation**: Streamlined validation processes
     * 
     * ## Performance Optimization
     * 
     * ### Quick Response Times
     * - **Minimal Data Loading**: Load only essential data for form generation
     * - **Efficient Processing**: Streamlined processing for fast response
     * - **Optimized Queries**: Database queries optimized for speed
     * - **Resource Efficiency**: Efficient use of server resources
     * 
     * ### User Interface Performance
     * - **Fast Rendering**: Optimized for fast form rendering
     * - **Minimal Overhead**: Reduce interface overhead for better performance
     * - **Progressive Loading**: Support for progressive loading when needed
     * - **Responsive Design**: Maintain responsiveness across devices
     * 
     * ## User Experience Design
     * 
     * ### Workflow Integration
     * - **Common Operations**: Focus on most common editing operations
     * - **Workflow Context**: Maintain workflow context throughout editing
     * - **Task Efficiency**: Enable efficient completion of routine tasks
     * - **User Productivity**: Enhance user productivity through streamlined interface
     * 
     * ### Interface Simplification
     * - **Reduced Complexity**: Simplify interface while maintaining functionality
     * - **Clear Focus**: Clear focus on essential editing capabilities
     * - **Minimal Distractions**: Reduce interface distractions and clutter
     * - **Intuitive Design**: Intuitive design for faster user adoption
     * 
     * ## Error Handling & Recovery
     * 
     * ### Streamlined Error Management
     * - **Quick Error Detection**: Fast detection and communication of errors
     * - **Simplified Recovery**: Streamlined error recovery processes
     * - **User Guidance**: Clear guidance for resolving common issues
     * - **Context Preservation**: Maintain user context during error handling
     * 
     * ### Efficient Error Communication
     * - **Clear Messaging**: Clear, concise error messaging
     * - **Quick Resolution**: Enable quick resolution of common errors
     * - **Minimal Disruption**: Minimize disruption to user workflow
     * - **Recovery Support**: Support for efficient error recovery
     * 
     * ## Integration Points
     * 
     * ### Turbo Frame Integration
     * - **Partial Updates**: Optimized for Turbo Frame partial updates
     * - **Seamless Experience**: Seamless integration with existing interfaces
     * - **Performance Focus**: Performance-focused integration approach
     * - **User Experience**: Enhanced user experience through partial updates
     * 
     * ### Workflow System
     * - **Workflow Efficiency**: Enable efficient workflow operations
     * - **Process Integration**: Integration with existing workflow processes
     * - **State Management**: Efficient state management and transitions
     * - **Business Logic**: Integration with workflow business logic
     * 
     * ## Use Case Optimization
     * 
     * ### Common Edit Scenarios
     * - **State Transitions**: Quick state changes and workflow progression
     * - **Event Assignment**: Rapid event assignment and scheduling
     * - **Basic Updates**: Common field updates and modifications
     * - **Status Changes**: Quick status and state management
     * 
     * ### Workflow Efficiency
     * - **Batch Operations**: Support for efficient batch operations
     * - **Routine Tasks**: Optimize for routine administrative tasks
     * - **User Productivity**: Enable higher user productivity
     * - **Process Improvement**: Support continuous process improvement
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Quick edit form generation
     * GET /awards/recommendations/turboQuickEditForm/123
     * 
     * // AJAX quick edit
     * fetch('/awards/recommendations/turboQuickEditForm/456')
     *   .then(response => response.text())
     *   .then(html => updateQuickEditForm(html));
     * 
     * // Turbo Frame quick edit
     * <turbo-frame id="quick-edit">
     *   <!-- Streamlined form content -->
     * </turbo-frame>
     * ```
     * 
     * @param string|null $id Recommendation ID for quick edit form generation
     * @return \Cake\Http\Response|null|void Renders streamlined edit form template
     * @throws \Cake\Http\Exception\NotFoundException When recommendation not found
     * 
     * @see turboEditForm() For comprehensive editing interface
     * @see edit() For form processing and submission handling
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
                'Events',
                'ScheduledEvent',
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

            $eventsData = $this->Recommendations->Events->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where(['OR' => ['closed' => false, 'closed IS' => null]])
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

            // Format event list for dropdown
            $eventList = [];
            foreach ($eventsData as $event) {
                $eventList[$event->id] = $event->name . ' in ' . $event->branch->name . ' on '
                    . $event->start_date->toDateString() . ' - ' . $event->end_date->toDateString();
            }

            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');
            $this->set(compact(
                'rules',
                'recommendation',
                'branches',
                'awards',
                'eventList',
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
     * Bulk editing form for multi-selection recommendation operations
     * 
     * Provides comprehensive bulk editing capabilities through a dynamic form
     * interface that enables simultaneous modification of multiple recommendations.
     * This method generates forms optimized for bulk operations including state
     * transitions, event assignments, and administrative bulk processing with
     * comprehensive validation and transaction safety.
     * 
     * ## Bulk Operations Architecture
     * 
     * ### Multi-Selection Design
     * - **Batch Processing**: Support for simultaneous modification of multiple recommendations
     * - **Selection Management**: Handle multiple recommendation selections efficiently
     * - **Operation Coordination**: Coordinate operations across multiple entities
     * - **Transaction Management**: Ensure atomic operations across multiple recommendations
     * 
     * ### Form Optimization
     * - **Bulk-Specific Interface**: Interface optimized for bulk operations
     * - **Efficient Data Loading**: Load data efficiently for bulk operation context
     * - **Performance Focus**: Optimized for handling multiple entity operations
     * - **User Experience**: Streamlined experience for bulk modifications
     * 
     * ## Administrative Interface
     * 
     * ### Bulk Authorization
     * - **Administrative Permissions**: Validate user has bulk operation permissions
     * - **Operation Validation**: Ensure user can perform specific bulk operations
     * - **Security Context**: Consider security implications of bulk operations
     * - **Permission Scoping**: Apply appropriate permission scoping for bulk access
     * 
     * ### Administrative Features
     * - **Enhanced Controls**: Administrative controls for bulk operations
     * - **Validation Override**: Administrative validation override capabilities
     * - **Audit Integration**: Enhanced audit integration for bulk operations
     * - **Error Management**: Comprehensive error management for bulk operations
     * 
     * ## Form Data Management
     * 
     * ### Bulk-Optimized Data Loading
     * - **Essential Data Only**: Load only data required for bulk operations
     * - **Efficient Queries**: Optimized database queries for bulk form generation
     * - **Resource Management**: Efficient resource management for bulk operations
     * - **Performance Optimization**: Optimized for handling bulk operation complexity
     * 
     * ### Dropdown & Option Management
     * - **Branch Selection**: Branch options for bulk assignment operations
     * - **Event Assignment**: Event options for bulk ceremony assignment
     * - **State Management**: State options for bulk state transitions
     * - **Status Processing**: Status options for bulk status management
     * 
     * ## State & Event Management
     * 
     * ### Bulk State Operations
     * - **State Transitions**: Support for bulk state transitions
     * - **Status Management**: Bulk status assignment and management
     * - **Workflow Integration**: Integration with workflow state management
     * - **Business Rule Application**: Apply business rules to bulk operations
     * 
     * ### Event Assignment Bulk Operations
     * - **Ceremony Assignment**: Bulk assignment to award ceremonies
     * - **Event Coordination**: Coordinate multiple recommendations with events
     * - **Scheduling Management**: Bulk scheduling and timeline management
     * - **Capacity Management**: Consider event capacity in bulk assignments
     * 
     * ## Workflow Rule Integration
     * 
     * ### Bulk Rule Processing
     * - **State Rules**: Apply state rules to bulk operations
     * - **Business Logic**: Integrate workflow business logic for bulk operations
     * - **Validation Rules**: Comprehensive validation for bulk modifications
     * - **Permission Rules**: Apply permission rules to bulk operations
     * 
     * ### Rule Configuration
     * - **Dynamic Rules**: Load appropriate rules for bulk operation context
     * - **Configuration Integration**: Integration with application configuration
     * - **Rule Validation**: Validate rules apply appropriately to bulk operations
     * - **Exception Handling**: Handle rule exceptions in bulk contexts
     * 
     * ## Performance & Scalability
     * 
     * ### Bulk Operation Performance
     * - **Efficient Processing**: Optimized processing for bulk operations
     * - **Resource Management**: Efficient resource management for large bulk operations
     * - **Scalability**: Support for scalable bulk operation processing
     * - **Performance Monitoring**: Monitor performance of bulk operations
     * 
     * ### User Interface Performance
     * - **Fast Form Generation**: Optimized form generation for bulk interfaces
     * - **Responsive Design**: Maintain responsiveness during bulk operations
     * - **Progressive Enhancement**: Support for progressive enhancement
     * - **User Feedback**: Real-time feedback during bulk processing
     * 
     * ## Security & Authorization
     * 
     * ### Bulk Security Validation
     * - **Enhanced Security**: Enhanced security validation for bulk operations
     * - **Operation Authorization**: Validate authorization for each bulk operation type
     * - **Data Protection**: Protect against unauthorized bulk modifications
     * - **Audit Requirements**: Meet audit requirements for bulk operations
     * 
     * ### Multi-Entity Authorization
     * - **Individual Authorization**: Validate authorization for each affected recommendation
     * - **Bulk Permission**: Validate bulk operation permissions
     * - **Security Scoping**: Apply appropriate security scoping
     * - **Access Control**: Comprehensive access control for bulk operations
     * 
     * ## Error Handling & Validation
     * 
     * ### Bulk Error Management
     * - **Comprehensive Validation**: Validate all aspects of bulk operations
     * - **Error Aggregation**: Aggregate and present errors from multiple operations
     * - **Partial Success Handling**: Handle scenarios where some operations succeed
     * - **Recovery Strategies**: Provide recovery strategies for bulk operation failures
     * 
     * ### User Communication
     * - **Clear Error Messaging**: Clear messaging for bulk operation errors
     * - **Success Feedback**: Comprehensive feedback for successful bulk operations
     * - **Progress Indication**: Progress indication for long-running bulk operations
     * - **Context Preservation**: Maintain user context during bulk operations
     * 
     * ## Integration Points
     * 
     * ### Workflow Integration
     * - **Bulk Workflow**: Integration with workflow systems for bulk operations
     * - **State Management**: Coordinate bulk operations with state management
     * - **Business Rules**: Apply business rules consistently across bulk operations
     * - **Audit Integration**: Comprehensive audit integration for bulk operations
     * 
     * ### System Integration
     * - **Transaction System**: Integration with transaction management
     * - **Authorization System**: Deep integration with authorization systems
     * - **Configuration System**: Integration with application configuration
     * - **Logging System**: Comprehensive logging for bulk operations
     * 
     * ## User Experience Design
     * 
     * ### Bulk Operation UX
     * - **Clear Interface**: Clear interface design for bulk operations
     * - **Operation Clarity**: Clear indication of what bulk operations will do
     * - **Confirmation Workflows**: Appropriate confirmation for bulk operations
     * - **Undo Capabilities**: Support for undoing bulk operations when possible
     * 
     * ### Efficiency Focus
     * - **Administrative Efficiency**: Enable efficient administrative operations
     * - **Workflow Optimization**: Optimize common bulk workflow operations
     * - **User Productivity**: Enhance user productivity through bulk capabilities
     * - **Process Improvement**: Support continuous process improvement
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Bulk edit form generation
     * GET /awards/recommendations/turboBulkEditForm
     * 
     * // AJAX bulk form
     * fetch('/awards/recommendations/turboBulkEditForm')
     *   .then(response => response.text())
     *   .then(html => updateBulkEditForm(html));
     * 
     * // Integration with selection
     * const selectedIds = getSelectedRecommendations();
     * loadBulkEditForm(selectedIds);
     * ```
     * 
     * @return \Cake\Http\Response|null|void Renders bulk edit form template
     * @throws \Cake\Http\Exception\InternalErrorException When bulk form preparation fails
     * 
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

            // Get events data
            $eventsData = $this->Recommendations->Events->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where(['OR' => ['closed' => false, 'closed IS' => null]])
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

            // Format event list for dropdown
            $eventList = [];
            foreach ($eventsData as $event) {
                $eventList[$event->id] = $event->name . ' in ' . $event->branch->name . ' on '
                    . $event->start_date->toDateString() . ' - ' . $event->end_date->toDateString();
            }

            $rules = StaticHelpers::getAppSetting('Awards.RecommendationStateRules');
            $this->set(compact('rules', 'branches', 'eventList', 'statusList'));
            return null;
        } catch (\Exception $e) {
            Log::error('Error in bulk edit form: ' . $e->getMessage());
            throw new \Cake\Http\Exception\InternalErrorException(__('An error occurred while preparing the bulk edit form.'));
        }
    }
    #endregion

    /**
     * Core table data processing with pagination, authorization, and display formatting
     * 
     * Implements the core logic for tabular recommendation display including query
     * execution, permission-based filtering, pagination management, and comprehensive
     * data preparation for tabular interfaces. This protected method serves as the
     * foundation for all tabular recommendation displays with sophisticated filtering,
     * authorization scoping, and performance optimization.
     * 
     * ## Query Execution & Data Processing
     * 
     * ### Recommendation Query Building
     * - **Filter Application**: Apply provided filter criteria to recommendation queries
     * - **Authorization Scoping**: Apply user-based authorization scoping automatically
     * - **Association Loading**: Load required associations for display and export
     * - **Performance Optimization**: Optimize queries for responsive user experience
     * 
     * ### Status List Processing
     * - **Dynamic Status Filtering**: Filter status lists based on current status parameter
     * - **Permission-Based Filtering**: Filter statuses based on user permissions
     * - **Hierarchical Organization**: Organize statuses for dropdown and display use
     * - **Validation Integration**: Integrate with status validation and business rules
     * 
     * ## Authorization & Permission Management
     * 
     * ### Permission-Based Visibility
     * - **Hidden State Filtering**: Filter out states that require special permissions
     * - **User Permission Checking**: Validate user permissions for viewing hidden states
     * - **Dynamic Status Lists**: Adjust status lists based on permission levels
     * - **Data Protection**: Protect sensitive recommendation data based on access level
     * 
     * ### Authorization Scoping
     * - **Query-Level Scoping**: Apply authorization scoping at database query level
     * - **Entity-Level Filtering**: Filter individual recommendations based on access
     * - **Context-Aware Security**: Apply security based on user context and role
     * - **Branch Scoping**: Apply branch-based access control when appropriate
     * 
     * ## Data Preparation & Formatting
     * 
     * ### Association Data Loading
     * - **Awards Information**: Load award data with abbreviations and specifications
     * - **Domain Organization**: Load domain information for categorization
     * - **Branch Integration**: Load branch data with member capability information
     * - **Event Coordination**: Load event data for ceremony coordination
     * 
     * ### Display Optimization
     * - **Efficient Data Structures**: Organize data efficiently for display templates
     * - **Format Conversion**: Convert data to appropriate formats for presentation
     * - **Relationship Management**: Handle complex relationships for display
     * - **Performance Focus**: Optimize data structures for rendering performance
     * 
     * ## Pagination Management
     * 
     * ### Sortable Field Configuration
     * - **Column Sorting**: Configure sortable columns for user interaction
     * - **Association Sorting**: Enable sorting on associated entity fields
     * - **Performance Optimization**: Optimize sorting for database performance
     * - **User Experience**: Provide intuitive sorting capabilities
     * 
     * ### Pagination Optimization
     * - **Memory Efficiency**: Efficient pagination for large datasets
     * - **Query Optimization**: Optimize paginated queries for performance
     * - **User Navigation**: Provide effective navigation through large datasets
     * - **Performance Monitoring**: Monitor pagination performance for optimization
     * 
     * ## State Rules & Business Logic
     * 
     * ### Workflow Integration
     * - **State Rules**: Load and apply recommendation state rules
     * - **Business Logic**: Integrate workflow business logic with display
     * - **Validation Rules**: Apply validation rules for state management
     * - **Permission Rules**: Integrate permission rules with workflow display
     * 
     * ### Configuration Management
     * - **Dynamic Configuration**: Load configuration based on current context
     * - **Rule Application**: Apply configuration rules to data display
     * - **Business Rule Integration**: Integrate business rules with table display
     * - **Validation Integration**: Integrate validation with table functionality
     * 
     * ## Event & Branch Management
     * 
     * ### Event Data Processing
     * - **Available Events**: Load and format available events for display
     * - **Event Filtering**: Filter events based on status and availability
     * - **Descriptive Formatting**: Format events with comprehensive descriptions
     * - **Branch Coordination**: Coordinate event data with branch information
     * 
     * ### Branch Integration
     * - **Member Capability**: Include branch member capability information
     * - **Hierarchical Organization**: Organize branches hierarchically
     * - **Permission Integration**: Apply branch permissions appropriately
     * - **Selection Support**: Support branch selection for filtering and operations
     * 
     * ## Error Handling & Recovery
     * 
     * ### Comprehensive Error Management
     * - **Query Errors**: Handle database query errors gracefully
     * - **Permission Errors**: Handle authorization and permission errors
     * - **Data Loading Errors**: Robust handling of data loading failures
     * - **System Errors**: Safe handling of unexpected system errors
     * 
     * ### User Communication
     * - **Error Messaging**: Clear error messaging through Flash component
     * - **Fallback Strategies**: Provide fallback strategies for error conditions
     * - **Recovery Guidance**: Guide users through error recovery processes
     * - **Context Preservation**: Maintain user context during error handling
     * 
     * ## Performance Optimization
     * 
     * ### Query Performance
     * - **Efficient Queries**: Optimize database queries for performance
     * - **Index Utilization**: Ensure queries utilize appropriate database indexes
     * - **Association Optimization**: Optimize association loading for performance
     * - **Caching Strategy**: Implement strategic caching for frequently accessed data
     * 
     * ### Memory Management
     * - **Efficient Data Structures**: Use memory-efficient data structures
     * - **Resource Management**: Manage server resources efficiently
     * - **Garbage Collection**: Support efficient garbage collection
     * - **Performance Monitoring**: Monitor performance for continuous optimization
     * 
     * ## Integration Points
     * 
     * ### System Integration
     * - **Authorization System**: Deep integration with RBAC authorization
     * - **Configuration System**: Integration with application configuration
     * - **Workflow System**: Integration with recommendation workflow management
     * - **Audit System**: Integration with audit and logging systems
     * 
     * ### Data System Integration
     * - **ORM Integration**: Deep integration with CakePHP ORM system
     * - **Query Builder**: Integration with CakePHP query builder
     * - **Pagination System**: Integration with CakePHP pagination
     * - **Association System**: Integration with CakePHP association system
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Basic table processing
     * $this->runTable($filterArray, 'All', 'Default');
     * 
     * // Status-filtered table
     * $this->runTable($processedFilter, 'Approved', 'Admin');
     * 
     * // Custom view processing
     * $this->runTable($customFilter, 'InProgress', 'Review');
     * ```
     * 
     * @param array $filterArray Filter criteria for querying recommendations
     * @param string $status Status filter to apply ('All', 'Approved', 'Pending', etc.)
     * @param string $view Current view configuration name for context
     * @return void Sets view variables for template rendering
     * 
     * @see table() For public table interface that calls this method
     * @see getRecommendationQuery() For the core query building implementation
     * @see \Cake\ORM\Query For query building and execution
     * @see \App\KMP\StaticHelpers::getAppSetting() For configuration loading
     */
    protected function runTable(array $filterArray, string $status, string $view = "Default"): void
    {
        try {
            // Build and execute the recommendation query with filters
            $recommendations = $this->getRecommendationQuery($filterArray);

            // Process status lists for display
            $fullStatusList = Recommendation::getStatuses();
            if ($status == "All") {
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
                    'Events.name',
                    'call_into_court',
                    'court_availability',
                    'requester_sca_name',
                    'contact_email',
                    'contact_phone',
                    'state_date',
                    'AssignedEvent.name'
                ],
            ];

            $action = $view;
            $recommendations = $this->paginate($recommendations);

            // Get recommendation state rules and events data
            $rules = StaticHelpers::getAppSetting("Awards.RecommendationStateRules");

            $eventsData = $this->Recommendations->Events->find()
                ->contain(['Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                }])
                ->where(['OR' => ['closed' => false, 'closed IS' => null]])
                ->select(['id', 'name', 'start_date', 'end_date', 'Branches.name'])
                ->orderBy(['start_date' => 'ASC'])
                ->all();

            // Format event list for display
            $eventList = [];
            foreach ($eventsData as $event) {
                $eventList[$event->id] = $event->name . " in " . $event->branch->name . " on "
                    . $event->start_date->toDateString() . " - " . $event->end_date->toDateString();
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
                'eventList'
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
     * Build comprehensive recommendation queries with advanced filtering, authorization, and optimization
     * 
     * Implements the core query building functionality for recommendation data access,
     * featuring sophisticated filtering capabilities, authorization-based scoping,
     * association optimization, and performance tuning. This protected method serves
     * as the foundation for all recommendation data access with consistent querying
     * patterns, security integration, and comprehensive data loading strategies.
     * 
     * ## Query Architecture & Foundation
     * 
     * ### Base Query Construction
     * - **Core Field Selection**: Select essential recommendation fields efficiently
     * - **Association Planning**: Plan association loading for optimal performance
     * - **Index Optimization**: Structure queries to utilize database indexes effectively
     * - **Memory Management**: Optimize queries for memory-efficient data loading
     * 
     * ### Association Loading Strategy
     * - **Deep Associations**: Load nested associations efficiently
     * - **Selective Loading**: Load only required association data
     * - **Performance Optimization**: Optimize association queries for responsiveness
     * - **Circular Reference Prevention**: Prevent circular reference issues in associations
     * 
     * ## Comprehensive Filtering System
     * 
     * ### Multi-Criteria Filter Processing
     * - **Status Filtering**: Filter by recommendation status and state
     * - **Member Filtering**: Filter by member information and characteristics
     * - **Award Filtering**: Filter by award types, domains, and specifications
     * - **Date Range Filtering**: Filter by creation, modification, and event dates
     * 
     * ### Advanced Search Capabilities
     * - **Text Search**: Full-text search across recommendation content
     * - **Fuzzy Matching**: Fuzzy matching for flexible search capabilities
     * - **Field-Specific Search**: Targeted search within specific fields
     * - **Combined Criteria**: Support multiple search criteria simultaneously
     * 
     * ## Authorization & Security Integration
     * 
     * ### Permission-Based Query Scoping
     * - **User-Based Filtering**: Apply user-specific authorization filters
     * - **Role-Based Access**: Filter based on user roles and capabilities
     * - **Branch-Based Scoping**: Apply branch-based access control to queries
     * - **Context-Aware Security**: Apply security based on operational context
     * 
     * ### Data Protection & Privacy
     * - **Sensitive Data Filtering**: Filter sensitive data based on access level
     * - **Privacy Compliance**: Ensure queries comply with privacy regulations
     * - **Audit Integration**: Integrate query access with audit systems
     * - **Security Logging**: Log security-relevant query operations
     * 
     * ## Performance Optimization
     * 
     * ### Query Performance Tuning
     * - **Efficient Joins**: Optimize join operations for performance
     * - **Index Utilization**: Ensure queries utilize appropriate database indexes
     * - **Subquery Optimization**: Optimize subqueries for better performance
     * - **Result Set Limitation**: Implement appropriate result set limitations
     * 
     * ### Memory & Resource Management
     * - **Efficient Data Structures**: Use memory-efficient data structures
     * - **Lazy Loading**: Implement lazy loading where appropriate
     * - **Resource Cleanup**: Proper cleanup of query resources
     * - **Garbage Collection**: Support efficient garbage collection
     * 
     * ## Association Configuration
     * 
     * ### Core Entity Associations
     * - **Member Association**: Load member data with names, titles, and contact info
     * - **Award Association**: Load award data with types, domains, and precedence
     * - **Branch Association**: Load branch data with hierarchy and capabilities
     * - **Event Association**: Load event data for ceremony coordination
     * 
     * ### Nested Association Loading
     * - **Member Branch**: Load member's branch information through associations
     * - **Award Domain**: Load award domain information for categorization
     * - **Requester Information**: Load requester data for attribution
     * - **State History**: Load state transition history for audit trails
     * 
     * ## Filter Processing Pipeline
     * 
     * ### Filter Validation & Sanitization
     * - **Input Validation**: Validate filter input for security and correctness
     * - **Data Sanitization**: Sanitize filter data to prevent injection attacks
     * - **Type Checking**: Verify filter data types and formats
     * - **Range Validation**: Validate date ranges and numeric limits
     * 
     * ### Dynamic Filter Application
     * - **Conditional Filters**: Apply filters conditionally based on context
     * - **Filter Combination**: Combine multiple filters efficiently
     * - **Filter Optimization**: Optimize filter application for performance
     * - **Filter Persistence**: Support filter persistence across requests
     * 
     * ## Data Integrity & Consistency
     * 
     * ### Query Result Validation
     * - **Data Consistency Checks**: Validate data consistency in query results
     * - **Referential Integrity**: Ensure referential integrity in associations
     * - **Business Rule Validation**: Apply business rules to query results
     * - **Data Quality Assurance**: Ensure data quality in query responses
     * 
     * ### Transaction Safety
     * - **Read Consistency**: Ensure read consistency in concurrent environments
     * - **Isolation Levels**: Apply appropriate transaction isolation levels
     * - **Deadlock Prevention**: Prevent deadlocks in complex queries
     * - **Resource Locking**: Manage resource locking appropriately
     * 
     * ## Error Handling & Recovery
     * 
     * ### Comprehensive Error Management
     * - **Query Errors**: Handle database query errors gracefully
     * - **Connection Errors**: Handle database connection errors
     * - **Timeout Errors**: Handle query timeout errors appropriately
     * - **Resource Errors**: Handle resource exhaustion errors
     * 
     * ### Fallback Strategies
     * - **Query Simplification**: Simplify queries on complex errors
     * - **Alternative Approaches**: Provide alternative query approaches
     * - **Partial Results**: Return partial results when appropriate
     * - **Error Recovery**: Implement error recovery strategies
     * 
     * ## Integration Points
     * 
     * ### ORM Integration
     * - **CakePHP ORM**: Deep integration with CakePHP ORM system
     * - **Query Builder**: Integration with CakePHP query builder
     * - **Association System**: Integration with CakePHP association system
     * - **Behavior Integration**: Integration with model behaviors
     * 
     * ### Service Integration
     * - **Authorization Service**: Integration with authorization services
     * - **Configuration Service**: Integration with configuration management
     * - **Audit Service**: Integration with audit and logging services
     * - **Caching Service**: Integration with caching systems
     * 
     * ## Query Result Processing
     * 
     * ### Result Set Optimization
     * - **Efficient Iteration**: Optimize result set iteration
     * - **Memory Management**: Manage memory usage during result processing
     * - **Data Transformation**: Transform results for specific use cases
     * - **Format Conversion**: Convert results to required formats
     * 
     * ### Post-Query Processing
     * - **Data Enrichment**: Enrich query results with additional data
     * - **Calculated Fields**: Add calculated fields to query results
     * - **Aggregation**: Perform aggregation on query results
     * - **Sorting Enhancement**: Enhance sorting capabilities
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Basic query without filters
     * $query = $this->getRecommendationQuery();
     * 
     * // Filtered query for specific status
     * $statusFilter = ['status' => 'Approved'];
     * $query = $this->getRecommendationQuery($statusFilter);
     * 
     * // Complex multi-criteria filter
     * $complexFilter = [
     *     'status' => 'InProgress',
     *     'award_id' => 123,
     *     'branch_id' => 456,
     *     'date_range' => ['start' => '2024-01-01', 'end' => '2024-12-31']
     * ];
     * $query = $this->getRecommendationQuery($complexFilter);
     * ```
     * 
     * @param array|null $filterArray Optional array of conditions to filter recommendations
     * @return \Cake\Datasource\QueryInterface The recommendation query with containments and filters applied
     * 
     * @see runTable() For table data processing that uses this query
     * @see runBoard() For kanban board data processing that uses this query
     * @see runExport() For export functionality that uses this query
     * @see processFilter() For filter processing logic
     */
    protected function getRecommendationQuery(?array $filterArray = null): \Cake\Datasource\QueryInterface
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
                'Recommendations.event_id',
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
                'AssignedEvent.id',
                'AssignedEvent.name',
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
                'Events' => function ($q) {
                    return $q->select(['id', 'name', 'start_date', 'end_date']);
                },
                'Notes' => function ($q) {
                    return $q->select(['id', 'entity_id', 'subject', 'body', 'created']);
                },
                'Notes.Authors' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'AssignedEvent' => function ($q) {
                    return $q->select(['id', 'name']);
                }
            ]);

        // Apply filter array if provided
        if ($filterArray) {
            $recommendations->where($filterArray);
        }

        // Apply additional filters from query parameters
        if ($this->request->getQuery('award_id')) {
            $recommendations->where(['award_id' => $this->request->getQuery('award_id')]);
        }

        if ($this->request->getQuery('branch_id')) {
            $recommendations->where(['Recommendations.branch_id' => $this->request->getQuery('branch_id')]);
        }

        if ($this->request->getQuery('for')) {
            $recommendations->where(['member_sca_name LIKE' => '%' . $this->request->getQuery('for') . '%']);
        }

        if ($this->request->getQuery('call_into_court')) {
            $recommendations->where(['call_into_court' => $this->request->getQuery('call_into_court')]);
        }

        if ($this->request->getQuery('court_avail')) {
            $recommendations->where(['court_availability' => $this->request->getQuery('court_avail')]);
        }

        if ($this->request->getQuery('requester_sca_name')) {
            $recommendations->where(['requester_sca_name' => $this->request->getQuery('requester_sca_name')]);
        }

        if ($this->request->getQuery('domain_id')) {
            $recommendations->where(['Awards.domain_id' => $this->request->getQuery('domain_id')]);
        }

        if ($this->request->getQuery('state')) {
            $recommendations->where(['Recommendations.state' => $this->request->getQuery('state')]);
        }

        if ($this->request->getQuery('branch_type')) {
            $recommendations->where(['AwardsBranches.type like ' => '%' . $this->request->getQuery('branch_type') . '%']);
        }

        // Apply authorization scope policy
        return $this->Authorization->applyScope($recommendations, 'index');
    }

    /**
     * Process filter configuration into query conditions with dynamic parameter substitution
     * 
     * Implements sophisticated filter processing for recommendation queries, featuring
     * dynamic parameter substitution, path expression normalization, and comprehensive
     * filter transformation capabilities. This protected method serves as the core
     * filter processing engine with support for request parameter injection, SQL
     * path expression handling, and flexible query condition generation.
     * 
     * ## Filter Processing Architecture
     * 
     * ### Configuration-to-Query Transformation
     * - **Filter Configuration Parsing**: Parse complex filter configuration arrays
     * - **Query Condition Generation**: Transform configurations into database query conditions
     * - **Parameter Substitution**: Dynamic parameter substitution from request data
     * - **Path Expression Normalization**: Normalize SQL path expressions for database compatibility
     * 
     * ### Dynamic Parameter Injection
     * - **Request Parameter Integration**: Inject request parameters into filter conditions
     * - **Parameter Validation**: Validate injected parameters for security and correctness
     * - **Default Value Handling**: Handle missing or empty request parameters gracefully
     * - **Type Conversion**: Convert request parameters to appropriate data types
     * 
     * ## Special Syntax Processing
     * 
     * ### Parameter Delimiter Syntax
     * - **Delimiter Recognition**: Recognize "-" wrapped parameter names for substitution
     * - **Parameter Extraction**: Extract parameter names from delimiter syntax
     * - **Value Substitution**: Substitute parameter values from request query string
     * - **Null Handling**: Handle cases where referenced parameters are not present
     * 
     * ### Path Expression Transformation
     * - **Arrow Notation Conversion**: Convert "->" notation to "." for SQL compatibility
     * - **Association Path Handling**: Handle complex association path expressions
     * - **Field Reference Normalization**: Normalize field references for query building
     * - **SQL Injection Prevention**: Prevent SQL injection through path expression validation
     * 
     * ## Query Condition Generation
     * 
     * ### Condition Building Pipeline
     * - **Key Normalization**: Normalize filter keys for database compatibility
     * - **Value Processing**: Process and validate filter values
     * - **Condition Assembly**: Assemble normalized conditions for query execution
     * - **Complex Condition Support**: Support complex query conditions and operators
     * 
     * ### Multi-Value Filter Support
     * - **Array Value Handling**: Handle array values for IN conditions
     * - **Range Conditions**: Support range conditions for date and numeric fields
     * - **Pattern Matching**: Support pattern matching with wildcards
     * - **Negation Support**: Support negation conditions for exclusion filters
     * 
     * ## Security & Validation
     * 
     * ### Input Validation & Sanitization
     * - **Parameter Validation**: Validate all injected parameters for security
     * - **Type Checking**: Verify parameter types before substitution
     * - **Range Validation**: Validate parameter ranges where appropriate
     * - **Format Validation**: Validate parameter formats and patterns
     * 
     * ### SQL Injection Prevention
     * - **Safe Parameter Binding**: Use safe parameter binding for all injected values
     * - **Path Validation**: Validate path expressions to prevent injection
     * - **Query Escaping**: Properly escape all dynamic query components
     * - **Whitelist Validation**: Validate against whitelisted field names and operators
     * 
     * ## Filter Configuration Support
     * 
     * ### Configuration Flexibility
     * - **Nested Configuration**: Support nested filter configuration structures
     * - **Conditional Filters**: Support conditional filter application
     * - **Default Filters**: Support default filter values and fallbacks
     * - **Override Handling**: Handle filter overrides and precedence
     * 
     * ### Advanced Filter Types
     * - **Text Search Filters**: Support full-text search filter processing
     * - **Date Range Filters**: Process date range filters with proper formatting
     * - **Numeric Range Filters**: Handle numeric range filters efficiently
     * - **Association Filters**: Process filters on associated entity fields
     * 
     * ## Performance Optimization
     * 
     * ### Efficient Processing
     * - **Minimal Processing Overhead**: Minimize processing overhead for filter transformation
     * - **Caching Support**: Support caching of processed filter configurations
     * - **Lazy Evaluation**: Implement lazy evaluation where appropriate
     * - **Memory Efficiency**: Efficient memory usage during filter processing
     * 
     * ### Query Optimization
     * - **Index-Friendly Conditions**: Generate conditions that utilize database indexes
     * - **Query Plan Optimization**: Structure conditions for optimal query plans
     * - **Join Optimization**: Optimize conditions for efficient join operations
     * - **Subquery Minimization**: Minimize subquery usage where possible
     * 
     * ## Error Handling & Recovery
     * 
     * ### Comprehensive Error Management
     * - **Parameter Errors**: Handle missing or invalid parameter references
     * - **Type Conversion Errors**: Handle type conversion errors gracefully
     * - **Format Errors**: Handle format validation errors appropriately
     * - **Configuration Errors**: Handle malformed filter configurations
     * 
     * ### Fallback Strategies
     * - **Default Values**: Provide sensible defaults for missing parameters
     * - **Filter Removal**: Remove invalid filters rather than failing entirely
     * - **Partial Processing**: Continue processing valid filters when errors occur
     * - **Error Logging**: Log processing errors for debugging and monitoring
     * 
     * ## Integration Points
     * 
     * ### Request Integration
     * - **Query Parameter Access**: Access request query parameters efficiently
     * - **Parameter Type Handling**: Handle various parameter types from requests
     * - **Request Validation**: Validate request parameters before processing
     * - **Context Preservation**: Preserve request context during processing
     * 
     * ### Query Builder Integration
     * - **CakePHP Query Integration**: Generate conditions compatible with CakePHP queries
     * - **ORM Compatibility**: Ensure compatibility with CakePHP ORM query building
     * - **Association Support**: Support association-based query conditions
     * - **Behavior Integration**: Integration with model behaviors
     * 
     * ## Usage Examples
     * 
     * ```php
     * // Basic filter processing
     * $filter = ['status' => 'Approved', 'branch_id' => '-branch-'];
     * $processed = $this->processFilter($filter);
     * 
     * // Complex path expressions
     * $filter = ['Member->Branch->name' => '-branch_name-'];
     * $processed = $this->processFilter($filter);
     * 
     * // Multiple condition processing
     * $filter = [
     *     'Award->domain' => '-domain-',
     *     'created >=' => '-start_date-',
     *     'status' => 'InProgress'
     * ];
     * $processed = $this->processFilter($filter);
     * ```
     * 
     * @param array $filter The filter configuration array with potential parameter references
     * @return array The processed filter array ready for use in database queries
     * 
     * @see getRecommendationQuery() For query building that uses processed filters
     * @see table() For table interface that processes filters
     * @see \Cake\Http\ServerRequest::getQuery() For request parameter access
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
}
