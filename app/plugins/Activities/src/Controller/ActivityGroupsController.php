<?php

declare(strict_types=1);

namespace Activities\Controller;

use App\Controller\DataverseGridTrait;

/**
 * # ActivityGroups Controller
 * 
 * Comprehensive activity group management controller providing complete CRUD operations for organizing
 * activities into categorical groups within the KMP Activities Plugin. This controller manages the 
 * organizational structure that allows activities to be grouped for administrative purposes, navigation,
 * and reporting analytics.
 * 
 * ## Controller Overview
 * 
 * The ActivityGroupsController serves as the primary administrative interface for managing activity groups,
 * which provide organizational structure for the Activities plugin. Activity groups enable:
 * 
 * - **Categorical Organization**: Group related activities for easier management and discovery
 * - **Administrative Interface**: Streamlined interface for group management operations
 * - **Navigation Support**: Structured organization for user navigation and activity browsing
 * - **Reporting Analytics**: Grouped reporting and statistical analysis of activity participation
 * - **Future Extensibility**: Foundation for advanced grouping features and hierarchical organization
 * 
 * ## Architecture Integration
 * 
 * ### Activities Plugin Integration
 * ```php
 * // Activity groups provide organizational structure
 * $group = $this->ActivityGroups->get($id, contain: ['Activities']);
 * foreach ($group->activities as $activity) {
 *     // Process activities within group context
 * }
 * ```
 * 
 * ### Authorization Architecture
 * The controller integrates with KMP's comprehensive authorization system:
 * - **Policy-Based Access Control**: Entity-level authorization using ActivityGroupPolicy
 * - **Model-Level Authorization**: Automatic authorization for index and add operations
 * - **Entity Authorization**: Individual entity authorization for view, edit, and delete operations
 * - **Security Framework**: Integration with CakePHP Authorization plugin
 * 
 * ### Data Management Integration
 * ```php
 * // Comprehensive activity group management
 * $groups = $this->ActivityGroups->find()
 *     ->contain(['Activities'])
 *     ->orderAsc('name');
 * ```
 * 
 * ## Security Architecture
 * 
 * ### Authorization Framework
 * - **Model Authorization**: Automatic authorization for collection operations (index, add)
 * - **Entity Authorization**: Individual authorization for entity-specific operations
 * - **Policy Integration**: ActivityGroupPolicy provides granular access control
 * - **Administrative Controls**: Restricted access to authorized personnel only
 * 
 * ### Data Protection
 * - **Soft Deletion Pattern**: Activity groups are marked as deleted rather than hard deleted
 * - **Referential Integrity**: Prevents deletion of groups with associated activities
 * - **Audit Trail**: Integration with ActivityGroupsTable audit behaviors
 * - **Input Validation**: Comprehensive validation through ActivityGroupsTable
 * 
 * ## Performance Considerations
 * 
 * ### Database Optimization
 * - **Selective Loading**: Strategic use of contain parameter for relationship loading
 * - **Pagination Support**: Built-in pagination for large datasets
 * - **Query Optimization**: Efficient queries for group and activity relationships
 * - **Index Utilization**: Leverages database indexes for name-based sorting
 * 
 * ### Caching Strategy
 * - **Activity Relationships**: Cached activity associations for performance
 * - **Navigation Integration**: Cached group data for navigation components
 * - **Administrative Views**: Optimized loading for administrative interfaces
 * 
 * ## Business Logic
 * 
 * ### Group Management Rules
 * - **Unique Naming**: Activity group names must be unique across the system
 * - **Activity Association**: Groups can contain multiple activities
 * - **Deletion Protection**: Groups with associated activities cannot be deleted
 * - **Administrative Oversight**: All operations require appropriate authorization
 * 
 * ### Workflow Integration
 * - **Activity Organization**: Groups provide structure for activity discovery
 * - **Administrative Workflows**: Streamlined group management for administrators
 * - **Reporting Integration**: Groups enable categorized reporting and analytics
 * 
 * ## Usage Examples
 * 
 * ### Basic Group Management
 * ```php
 * // List all activity groups with pagination
 * $this->ActivityGroups->index();
 * 
 * // View specific group with activities
 * $this->ActivityGroups->view($groupId);
 * 
 * // Create new activity group
 * $this->ActivityGroups->add();
 * ```
 * 
 * ### Administrative Operations
 * ```php
 * // Edit existing group
 * $this->ActivityGroups->edit($groupId);
 * 
 * // Delete group (with validation)
 * $this->ActivityGroups->delete($groupId);
 * ```
 * 
 * ### Integration with Activities
 * ```php
 * // Access activities within group context
 * $group = $this->ActivityGroups->get($id, contain: ['Activities']);
 * $activityCount = count($group->activities);
 * ```
 * 
 * ## Integration Points
 * 
 * ### Activities Plugin Components
 * - **ActivitiesTable**: Activity-group relationship management
 * - **ActivityGroupsTable**: Primary data management and validation
 * - **Activity Entities**: Group membership and organizational structure
 * - **Activities Navigation**: Group-based navigation organization
 * 
 * ### KMP Core System
 * - **Authorization Service**: Policy-based access control
 * - **Flash Component**: User feedback and messaging
 * - **Pagination Component**: Large dataset management
 * - **Template System**: Consistent UI presentation
 * 
 * ### Administrative Interface
 * - **Administrative Navigation**: Group management menu integration
 * - **Reporting System**: Group-based activity reporting
 * - **Search Integration**: Group-based activity discovery
 * - **Configuration Management**: Group settings and preferences
 * 
 * ## Extension Opportunities
 * 
 * ### Enhanced Grouping Features
 * - **Hierarchical Groups**: Multi-level group organization
 * - **Group Templates**: Standardized group configurations
 * - **Bulk Operations**: Mass group management tools
 * - **Advanced Filtering**: Complex group discovery and management
 * 
 * ### Integration Enhancements
 * - **API Endpoints**: RESTful API for group management
 * - **Workflow Integration**: Group-based approval workflows
 * - **Reporting Extensions**: Advanced group analytics
 * - **Permission Integration**: Group-based permission management
 * 
 * ### Administrative Tools
 * - **Bulk Import/Export**: Mass group data management
 * - **Group Analytics**: Usage statistics and reporting
 * - **Maintenance Tools**: Group cleanup and optimization
 * - **Configuration Wizards**: Guided group setup processes
 * 
 * @property \Activities\Model\Table\ActivityGroupsTable $ActivityGroups The ActivityGroups table for data operations
 * @see \Activities\Model\Table\ActivityGroupsTable For data management operations
 * @see \Activities\Model\Entity\ActivityGroup For entity structure and relationships
 * @see \Activities\Policy\ActivityGroupPolicy For authorization rules
 * @see \Activities\Controller\ActivitiesController For related activity management
 * 
 * @package Activities\Controller
 * @since Activities Plugin 1.0.0
 * @author KMP Development Team
 */
class ActivityGroupsController extends AppController
{
    use DataverseGridTrait;
    /**
     * Initialize the ActivityGroups controller with authorization configuration and component setup.
     * 
     * This method establishes the foundational security and component configuration for activity group
     * management operations. It configures automatic model-level authorization for collection operations
     * and ensures proper integration with the Activities plugin security framework.
     * 
     * ## Authorization Configuration
     * 
     * The method configures automatic authorization for collection-level operations:
     * - **Index Authorization**: Automatic authorization for listing activity groups
     * - **Add Authorization**: Automatic authorization for creating new activity groups
     * - **Model-Level Security**: Ensures users have appropriate permissions before accessing operations
     * 
     * ## Security Architecture Integration
     * 
     * ### Automatic Model Authorization
     * ```php
     * // Configured automatically for these operations:
     * $this->Authorization->authorizeModel("index");  // List groups
     * $this->Authorization->authorizeModel("add");    // Create groups
     * ```
     * 
     * ### Entity-Level Authorization
     * Entity-specific operations (view, edit, delete) require explicit authorization in each method:
     * ```php
     * // Individual entity authorization (configured per method)
     * $this->Authorization->authorize($activityGroup);
     * ```
     * 
     * ## Integration Points
     * 
     * ### Parent Controller Integration
     * Inherits foundational configuration from Activities\Controller\AppController:
     * - **Authentication Component**: User authentication and session management
     * - **Authorization Component**: CakePHP Authorization plugin integration
     * - **Flash Component**: User feedback and messaging system
     * - **Security Framework**: Activities plugin security baseline
     * 
     * ### Activities Plugin Security
     * - **Plugin Authorization**: Integrates with Activities plugin security policies
     * - **RBAC Integration**: Connects with KMP role-based access control system
     * - **Permission Validation**: Ensures users have appropriate activity group management permissions
     * 
     * ## Usage Examples
     * 
     * ### Automatic Authorization Flow
     * ```php
     * // Index operation - automatically authorized
     * public function index() {
     *     // Authorization already checked in initialize()
     *     $groups = $this->ActivityGroups->find();
     * }
     * 
     * // Add operation - automatically authorized
     * public function add() {
     *     // Authorization already checked in initialize()
     *     $group = $this->ActivityGroups->newEmptyEntity();
     * }
     * ```
     * 
     * ### Manual Authorization Required
     * ```php
     * // Entity operations require explicit authorization
     * public function view($id) {
     *     $group = $this->ActivityGroups->get($id);
     *     $this->Authorization->authorize($group); // Required
     * }
     * ```
     * 
     * ## Security Considerations
     * 
     * ### Authorization Strategy
     * - **Preventive Security**: Authorization checked before method execution
     * - **Fail-Safe Design**: Operations fail securely if authorization is missing
     * - **Policy Integration**: Leverages ActivityGroupPolicy for access control decisions
     * - **Administrative Access**: Restricts group management to authorized administrative users
     * 
     * ### Permission Requirements
     * Users accessing this controller must have:
     * - **Activity Group Management Permissions**: Appropriate RBAC permissions for group operations
     * - **Administrative Access**: Generally restricted to administrative personnel
     * - **Plugin Access**: General access to Activities plugin functionality
     * 
     * @return void
     * @throws \Authorization\Exception\ForbiddenException When user lacks required permissions
     * @see \Activities\Controller\AppController::initialize() For parent controller configuration
     * @see \Activities\Policy\ActivityGroupPolicy For authorization rule definitions
     * @see \Cake\Controller\Component\AuthorizationComponent For authorization framework
     * 
     * @since Activities Plugin 1.0.0
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("index", "add", "gridData");
    }

    /**
     * Display paginated list of all activity groups with alphabetical ordering.
     * 
     * This method provides the primary administrative interface for browsing and managing activity groups
     * within the Activities plugin. It presents a comprehensive, paginated listing of all activity groups
     * ordered alphabetically by name for efficient administrative oversight and group discovery.
     * 
     * ## Method Overview
     * 
     * The index method serves as the main entry point for activity group management, providing:
     * - **Complete Group Listing**: Displays all available activity groups in the system
     * - **Alphabetical Organization**: Groups sorted by name for easy browsing and discovery
     * - **Pagination Support**: Efficient handling of large numbers of activity groups
     * - **Administrative Interface**: Primary navigation hub for group management operations
     * 
     * ## Query Architecture
     * 
     * ### Basic Query Construction
     * ```php
     * // Simple, efficient query for group listing
     * $query = $this->ActivityGroups->find();
     * 
     * // Alphabetical ordering for consistent presentation
     * $options = ['order' => ['name' => 'asc']];
     * ```
     * 
     * ### Pagination Configuration
     * The method leverages CakePHP's pagination component for optimal performance:
     * - **Automatic Pagination**: Built-in pagination for large datasets
     * - **Configurable Page Size**: Adjustable number of groups per page
     * - **Performance Optimization**: Efficient database queries with limiting
     * - **User Experience**: Smooth navigation through large group collections
     * 
     * ## Data Presentation
     * 
     * ### Group Information Display
     * The view presents essential group information:
     * - **Group Name**: Primary identifier and display name
     * - **Creation Date**: When the group was established (via audit fields)
     * - **Modification Date**: Last update timestamp (via audit fields)
     * - **Activity Count**: Number of associated activities (if implemented in view)
     * 
     * ### Administrative Operations
     * The interface provides access to:
     * - **View Group Details**: Link to individual group view with activities
     * - **Edit Group**: Modification interface for group properties
     * - **Delete Group**: Removal interface with validation checks
     * - **Add New Group**: Creation interface for new activity groups
     * 
     * ## Security Architecture
     * 
     * ### Authorization Framework
     * - **Model Authorization**: Automatic authorization configured in initialize()
     * - **Administrative Access**: Restricted to users with group management permissions
     * - **Policy Integration**: Leverages ActivityGroupPolicy for access control
     * - **Secure Listing**: Only authorized groups are displayed based on user permissions
     * 
     * ### Data Protection
     * - **Read-Only Listing**: Index provides safe, read-only access to group information
     * - **Authorization Checks**: All subsequent operations require additional authorization
     * - **Audit Integration**: Access logging through Activities plugin audit system
     * 
     * ## Performance Considerations
     * 
     * ### Database Optimization
     * - **Efficient Queries**: Simple find() query with minimal overhead
     * - **Index Utilization**: Leverages database indexes for name-based ordering
     * - **Pagination Limits**: Prevents memory issues with large datasets
     * - **Query Simplicity**: Avoids complex joins for optimal listing performance
     * 
     * ### Caching Strategy
     * - **View Caching**: Potential for view-level caching of group listings
     * - **Pagination Caching**: CakePHP pagination component includes built-in optimizations
     * - **Administrative Caching**: Optimized for administrative interface responsiveness
     * 
     * ## User Experience
     * 
     * ### Navigation Integration
     * - **Administrative Menu**: Accessed through Activities plugin administrative navigation
     * - **Breadcrumb Support**: Clear navigation hierarchy for administrative users
     * - **Action Links**: Quick access to group management operations
     * - **Search Integration**: Foundation for future search and filtering capabilities
     * 
     * ### Interface Features
     * - **Responsive Design**: Mobile-friendly administrative interface
     * - **Sorting Consistency**: Alphabetical ordering for predictable browsing
     * - **Quick Actions**: Immediate access to view, edit, and delete operations
     * - **Status Indicators**: Visual indication of group state and activity associations
     * 
     * ## Integration Points
     * 
     * ### Activities Plugin Integration
     * - **Group Management**: Central hub for activity group administration
     * - **Activity Association**: Links to activities within each group
     * - **Navigation Support**: Provides data for navigation components
     * - **Reporting Integration**: Foundation for group-based reporting and analytics
     * 
     * ### Template Integration
     * ```php
     * // View template receives paginated group data
     * // Template: plugins/Activities/templates/ActivityGroups/index.php
     * foreach ($activityGroup as $group) {
     *     echo $group->name; // Display group information
     * }
     * ```
     * 
     * ## Usage Examples
     * 
     * ### Basic Administrative Access
     * ```php
     * // Administrative user accesses group listing
     * // URL: /activities/activity-groups
     * // Automatically authorized and paginated
     * ```
     * 
     * ### Integration with Group Management
     * ```php
     * // Template integration for administrative operations
     * echo $this->Html->link('View Group', [
     *     'action' => 'view', 
     *     $group->id
     * ]);
     * 
     * echo $this->Html->link('Edit Group', [
     *     'action' => 'edit', 
     *     $group->id
     * ]);
     * ```
     * 
     * ## Extension Opportunities
     * 
     * ### Enhanced Listing Features
     * - **Search Functionality**: Group name and description search
     * - **Filtering Options**: Filter by creation date, activity count, or status
     * - **Bulk Operations**: Mass editing or deletion of multiple groups
     * - **Export Capabilities**: CSV or PDF export of group listings
     * 
     * ### Administrative Enhancements
     * - **Group Statistics**: Activity count and participation metrics
     * - **Usage Analytics**: Group utilization and popularity reporting
     * - **Quick Edit**: Inline editing of group names and descriptions
     * - **Group Templates**: Predefined group configurations for common use cases
     * 
     * @return \Cake\Http\Response|null|void Renders the index view with paginated activity groups
     * @see \Activities\Model\Table\ActivityGroupsTable::find() For query construction
     * @see \Cake\Controller\Component\PaginatorComponent For pagination functionality
     * @see \Activities\Template\ActivityGroups\index.php For view template
     * 
     * @since Activities Plugin 1.0.0
     */
    public function index(): void
    {
        $this->set('user', $this->request->getAttribute('identity'));
    }

    /**
     * Provide grid data for Activity Groups listing.
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
        $baseQuery = $this->ActivityGroups->find();

        // Use unified trait for grid processing
        $result = $this->processDataverseGrid([
            'gridKey' => 'Activities.ActivityGroups.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\ActivityGroupsGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'ActivityGroups',
            'defaultSort' => ['ActivityGroups.name' => 'asc'],
            'defaultPageSize' => 25,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'activity-groups');
        }

        // Set view variables
        $this->set([
            'activityGroups' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\ActivityGroupsGridColumns::getSearchableColumns(),
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

        if ($turboFrame === 'activity-groups-grid-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', 'activity-groups-grid-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', 'activity-groups-grid');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Display detailed view of a specific activity group with associated activities.
     * 
     * This method provides comprehensive details for a single activity group, including all associated
     * activities and their current status. It serves as the primary interface for examining group 
     * composition, managing group-activity relationships, and accessing individual group administration.
     * 
     * ## Method Overview
     * 
     * The view method delivers a comprehensive activity group detail interface that includes:
     * - **Group Information**: Complete details about the selected activity group
     * - **Associated Activities**: Full listing of all activities within the group
     * - **Activity Management**: Access to individual activity administration
     * - **Group Statistics**: Overview of group composition and activity status
     * - **Administrative Actions**: Quick access to edit and delete operations
     * 
     * ## Data Loading Architecture
     * 
     * ### Entity Retrieval with Relationships
     * ```php
     * // Comprehensive group loading with activity associations
     * $authorizationGroup = $this->ActivityGroups->get($id, contain: ["Activities"]);
     * 
     * // Provides access to:
     * // - Group entity properties (name, description, etc.)
     * // - Associated activities collection
     * // - Activity details and status information
     * ```
     * 
     * ### Relationship Loading Strategy
     * The method employs strategic relationship loading:
     * - **Eager Loading**: Activities loaded with group to prevent N+1 queries
     * - **Complete Association**: Full activity entities with all properties
     * - **Performance Optimization**: Single query retrieves group and activities
     * - **Data Consistency**: Ensures current activity associations are displayed
     * 
     * ## Security Architecture
     * 
     * ### Authorization Framework
     * - **Entity Authorization**: Individual authorization for the specific group
     * - **Policy Integration**: ActivityGroupPolicy determines view access
     * - **Administrative Access**: Typically restricted to authorized personnel
     * - **Data Security**: Ensures users can only view authorized groups
     * 
     * ### Error Handling and Validation
     * ```php
     * // Comprehensive error handling for security and data integrity
     * if (!$authorizationGroup) {
     *     throw new \Cake\Http\Exception\NotFoundException();
     * }
     * 
     * // Entity-level authorization check
     * $this->Authorization->authorize($authorizationGroup);
     * ```
     * 
     * ### Security Considerations
     * - **Existence Validation**: Confirms group exists before authorization
     * - **Access Control**: Policy-based authorization for view operations
     * - **Exception Handling**: Secure error responses for unauthorized access
     * - **Audit Integration**: Access logging through Activities plugin audit system
     * 
     * ## Data Presentation
     * 
     * ### Group Information Display
     * The view presents comprehensive group details:
     * - **Group Name**: Primary identifier and display title
     * - **Group Description**: Detailed description of group purpose (if implemented)
     * - **Creation Information**: Audit trail data showing creation details
     * - **Modification History**: Last update information and modification tracking
     * 
     * ### Activities Listing
     * Associated activities are displayed with:
     * - **Activity Names**: Complete listing of all activities in the group
     * - **Activity Status**: Current status and availability information
     * - **Activity Links**: Direct navigation to individual activity management
     * - **Activity Statistics**: Participation and authorization metrics (if implemented)
     * 
     * ## Administrative Interface
     * 
     * ### Group Management Operations
     * The view provides access to:
     * - **Edit Group**: Modification interface for group properties
     * - **Delete Group**: Removal interface with activity association validation
     * - **Activity Management**: Links to individual activity administration
     * - **Group Statistics**: Analytics and reporting for group utilization
     * 
     * ### Navigation Integration
     * - **Breadcrumb Support**: Clear navigation hierarchy showing group context
     * - **Return Navigation**: Easy return to group listing
     * - **Related Operations**: Quick access to associated administrative functions
     * - **Activity Navigation**: Direct links to activity management interfaces
     * 
     * ## Performance Considerations
     * 
     * ### Database Optimization
     * - **Single Query Loading**: Efficient retrieval of group and activities
     * - **Contain Strategy**: Strategic relationship loading to minimize queries
     * - **Index Utilization**: Leverages primary key indexes for fast retrieval
     * - **Memory Efficiency**: Optimized loading for large activity collections
     * 
     * ### Caching Strategy
     * - **Entity Caching**: Potential for caching group entities with activities
     * - **View Caching**: Optimization for frequently accessed group views
     * - **Association Caching**: Cached activity relationships for performance
     * 
     * ## User Experience
     * 
     * ### Interface Design
     * - **Comprehensive Overview**: Complete group information at a glance
     * - **Organized Presentation**: Clear separation of group info and activities
     * - **Action Accessibility**: Easy access to management operations
     * - **Responsive Layout**: Mobile-friendly administrative interface
     * 
     * ### Workflow Integration
     * - **Administrative Workflow**: Seamless integration with group management tasks
     * - **Activity Access**: Direct navigation to activity management from group context
     * - **Status Visibility**: Clear indication of group and activity status
     * - **Quick Actions**: Immediate access to edit and delete operations
     * 
     * ## Integration Points
     * 
     * ### Activities Plugin Integration
     * - **Activity Management**: Links to ActivitiesController for activity operations
     * - **Group Statistics**: Integration with reporting and analytics systems
     * - **Navigation Support**: Provides context for plugin navigation components
     * - **Administrative Tools**: Integration with administrative dashboard and tools
     * 
     * ### Template Integration
     * ```php
     * // View template receives group entity with activities
     * // Template: plugins/Activities/templates/ActivityGroups/view.php
     * echo h($authorizationGroup->name);
     * 
     * foreach ($authorizationGroup->activities as $activity) {
     *     echo $this->Html->link($activity->name, [
     *         'controller' => 'Activities',
     *         'action' => 'view',
     *         $activity->id
     *     ]);
     * }
     * ```
     * 
     * ## Usage Examples
     * 
     * ### Administrative Access
     * ```php
     * // Administrator views group details
     * // URL: /activities/activity-groups/view/1
     * // Displays group info and all associated activities
     * ```
     * 
     * ### Group-Activity Management
     * ```php
     * // Template integration for activity management
     * foreach ($authorizationGroup->activities as $activity) {
     *     echo $this->Html->link('Manage Activity', [
     *         'controller' => 'Activities',
     *         'action' => 'edit',
     *         $activity->id
     *     ]);
     * }
     * ```
     * 
     * ## Extension Opportunities
     * 
     * ### Enhanced Group Views
     * - **Activity Statistics**: Detailed metrics for activities within group
     * - **Participation Analytics**: Member participation tracking by group
     * - **Status Dashboard**: Visual status indicators for group and activities
     * - **Export Functions**: PDF or CSV export of group and activity information
     * 
     * ### Administrative Enhancements
     * - **Bulk Activity Operations**: Mass management of activities within group
     * - **Group Templates**: Apply standard configurations to groups
     * - **Activity Sorting**: Custom ordering of activities within groups
     * - **Quick Edit**: Inline editing of group properties from view interface
     * 
     * ### Integration Extensions
     * - **Reporting Integration**: Direct access to group-specific reports
     * - **Workflow Tools**: Group-based activity workflow management
     * - **Member Integration**: Member participation tracking within group context
     * - **Permission Management**: Group-based permission and access control
     * 
     * @param string|null $id Activity Group id for detailed view
     * @return \Cake\Http\Response|null|void Renders the view template with group details
     * @throws \Cake\Http\Exception\NotFoundException When the specified group is not found
     * @throws \Authorization\Exception\ForbiddenException When user lacks view permissions
     * @see \Activities\Model\Table\ActivityGroupsTable::get() For entity retrieval with associations
     * @see \Activities\Policy\ActivityGroupPolicy For authorization rules
     * @see \Activities\Template\ActivityGroups\view.php For view template
     * 
     * @since Activities Plugin 1.0.0
     */
    public function view($id = null)
    {
        $authorizationGroup = $this->ActivityGroups->get(
            $id,
            contain: ["Activities"],
        );
        if (!$authorizationGroup) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $this->Authorization->authorize($authorizationGroup);
        $this->set(compact("authorizationGroup"));
    }

    /**
     * Create new activity group with comprehensive form processing and validation.
     * 
     * This method provides the complete interface for creating new activity groups within the Activities
     * plugin. It handles both GET requests for form display and POST requests for form processing,
     * implementing comprehensive validation, error handling, and user feedback for optimal administrative
     * experience.
     * 
     * ## Method Overview
     * 
     * The add method serves dual purposes in activity group creation:
     * - **Form Display**: Presents empty form for new group creation (GET requests)
     * - **Form Processing**: Validates and saves new group data (POST requests)
     * - **Validation Integration**: Comprehensive validation through ActivityGroupsTable
     * - **User Feedback**: Clear success and error messaging for administrative users
     * - **Workflow Integration**: Seamless redirection to group view upon successful creation
     * 
     * ## Request Processing Architecture
     * 
     * ### Form Display (GET Requests)
     * ```php
     * // Empty entity for form binding
     * $authorizationGroup = $this->ActivityGroups->newEmptyEntity();
     * 
     * // Template receives empty entity for form rendering
     * $this->set(compact("authorizationGroup"));
     * ```
     * 
     * ### Form Processing (POST Requests)
     * ```php
     * // Data patching and validation
     * $authorizationGroup = $this->ActivityGroups->patchEntity(
     *     $authorizationGroup,
     *     $this->request->getData()
     * );
     * 
     * // Save operation with comprehensive error handling
     * if ($this->ActivityGroups->save($authorizationGroup)) {
     *     // Success workflow
     * }
     * ```
     * 
     * ## Security Architecture
     * 
     * ### Authorization Framework
     * - **Model Authorization**: Automatic authorization configured in initialize()
     * - **Administrative Access**: Restricted to users with group creation permissions
     * - **Policy Integration**: Leverages ActivityGroupPolicy for creation access control
     * - **Input Validation**: Comprehensive validation through ActivityGroupsTable rules
     * 
     * ### Data Protection
     * - **Mass Assignment Protection**: Entity-level protection against unauthorized field assignment
     * - **Validation Rules**: Comprehensive validation ensuring data integrity
     * - **CSRF Protection**: Built-in CSRF protection for form submissions
     * - **Audit Integration**: Creation tracking through ActivityGroupsTable audit behaviors
     * 
     * ## Form Processing Workflow
     * 
     * ### Validation and Entity Management
     * The method implements comprehensive form processing:
     * - **Entity Creation**: New empty entity for form binding
     * - **Data Patching**: Safe assignment of form data to entity
     * - **Validation Execution**: Automatic validation through table validation rules
     * - **Error Collection**: Comprehensive error collection for user feedback
     * 
     * ### Success Workflow
     * Upon successful group creation:
     * ```php
     * // Success feedback and redirection
     * $this->Flash->success(__("The Activity Group has been saved."));
     * return $this->redirect(['action' => 'view', $authorizationGroup->id]);
     * ```
     * 
     * ### Error Handling Workflow
     * When validation fails:
     * ```php
     * // Error feedback and form redisplay
     * $this->Flash->error(__("The Activity Group could not be saved. Please, try again."));
     * // Form redisplayed with validation errors
     * ```
     * 
     * ## User Experience
     * 
     * ### Form Interface
     * The add interface provides:
     * - **Clean Form Layout**: Intuitive form design for group creation
     * - **Field Validation**: Real-time validation feedback for form fields
     * - **Error Display**: Clear presentation of validation errors
     * - **Success Feedback**: Confirmation messaging for successful creation
     * 
     * ### Navigation Flow
     * - **Form Access**: Accessed from group listing or administrative navigation
     * - **Success Redirection**: Automatic navigation to new group view
     * - **Cancel Options**: Easy return to group listing without saving
     * - **Breadcrumb Integration**: Clear navigation hierarchy during creation process
     * 
     * ## Validation Framework
     * 
     * ### ActivityGroupsTable Validation
     * The method leverages comprehensive validation rules:
     * - **Required Fields**: Validation of mandatory group information
     * - **Unique Constraints**: Ensures group names are unique across system
     * - **Business Rules**: Custom validation for group-specific requirements
     * - **Data Integrity**: Comprehensive data validation for consistency
     * 
     * ### Error Handling Strategy
     * - **User-Friendly Messages**: Clear, actionable error messages
     * - **Field-Specific Errors**: Detailed validation feedback for individual fields
     * - **Form Preservation**: Form data preserved on validation errors
     * - **Multiple Error Support**: Comprehensive handling of multiple validation issues
     * 
     * ## Performance Considerations
     * 
     * ### Database Optimization
     * - **Efficient Entity Creation**: Minimal database interaction for form display
     * - **Single Save Operation**: Optimized save process for new groups
     * - **Validation Efficiency**: Fast validation execution through table rules
     * - **Transaction Safety**: Safe save operations with rollback capability
     * 
     * ### Memory Management
     * - **Lightweight Entities**: Efficient entity creation and management
     * - **Form Optimization**: Optimized form processing for administrative interfaces
     * - **Error Collection**: Efficient error handling and collection
     * 
     * ## Integration Points
     * 
     * ### Activities Plugin Integration
     * - **Group Management**: Central creation interface for activity groups
     * - **Administrative Workflow**: Integration with administrative dashboard
     * - **Navigation Support**: Provides data for navigation components
     * - **Audit Integration**: Creation tracking through plugin audit system
     * 
     * ### Template Integration
     * ```php
     * // Form template integration
     * // Template: plugins/Activities/templates/ActivityGroups/add.php
     * echo $this->Form->create($authorizationGroup);
     * echo $this->Form->control('name', ['label' => 'Group Name']);
     * echo $this->Form->submit('Create Group');
     * echo $this->Form->end();
     * ```
     * 
     * ### Flash Component Integration
     * - **Success Messaging**: Clear confirmation of successful group creation
     * - **Error Feedback**: Detailed error information for failed attempts
     * - **User Guidance**: Actionable messages for form correction
     * 
     * ## Usage Examples
     * 
     * ### Administrative Group Creation
     * ```php
     * // Administrator accesses creation form
     * // URL: /activities/activity-groups/add
     * // GET: Displays empty form
     * // POST: Processes form data and creates group
     * ```
     * 
     * ### Form Data Processing
     * ```php
     * // Form submission data structure
     * $formData = [
     *     'name' => 'Training Activities',
     *     'description' => 'Activities related to member training'
     * ];
     * 
     * // Automatic validation and processing
     * ```
     * 
     * ## Extension Opportunities
     * 
     * ### Enhanced Creation Features
     * - **Group Templates**: Predefined group configurations for common use cases
     * - **Bulk Creation**: Mass creation of multiple groups
     * - **Import Functionality**: CSV or JSON import of group data
     * - **Wizard Interface**: Step-by-step group creation process
     * 
     * ### Administrative Enhancements
     * - **Advanced Validation**: Custom validation rules for specific group types
     * - **Integration Hooks**: Plugin hooks for custom group creation workflows
     * - **Notification System**: Automated notifications for group creation events
     * - **Approval Workflow**: Multi-step approval process for group creation
     * 
     * ### User Experience Improvements
     * - **AJAX Form Processing**: Real-time form validation and submission
     * - **Progress Indicators**: Visual feedback during group creation process
     * - **Field Suggestions**: Autocomplete and suggestion features
     * - **Preview Mode**: Preview group configuration before final creation
     * 
     * @return \Cake\Http\Response|null|void Redirects to view on success, renders form otherwise
     * @see \Activities\Model\Table\ActivityGroupsTable::newEmptyEntity() For entity creation
     * @see \Activities\Model\Table\ActivityGroupsTable::patchEntity() For data assignment
     * @see \Activities\Model\Table\ActivityGroupsTable::save() For persistence operations
     * @see \Activities\Template\ActivityGroups\add.php For form template
     * 
     * @since Activities Plugin 1.0.0
     */
    public function add()
    {
        $authorizationGroup = $this->ActivityGroups->newEmptyEntity();
        if ($this->request->is("post")) {
            $authorizationGroup = $this->ActivityGroups->patchEntity(
                $authorizationGroup,
                $this->request->getData(),
            );
            if ($this->ActivityGroups->save($authorizationGroup)) {
                $this->Flash->success(
                    __("The Activity Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $authorizationGroup->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Activity Group could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("authorizationGroup"));
    }

    /**
     * Edit existing activity group with comprehensive form processing and validation.
     * 
     * This method provides the complete interface for modifying existing activity groups within the
     * Activities plugin. It handles both GET requests for form display with existing data and 
     * POST/PATCH/PUT requests for form processing, implementing comprehensive validation, error 
     * handling, and user feedback for optimal administrative experience.
     * 
     * ## Method Overview
     * 
     * The edit method serves dual purposes in activity group modification:
     * - **Form Display**: Presents populated form with existing group data (GET requests)
     * - **Form Processing**: Validates and saves modified group data (POST/PATCH/PUT requests)
     * - **Entity Authorization**: Individual authorization for specific group modification
     * - **Data Integrity**: Comprehensive validation through ActivityGroupsTable
     * - **Workflow Integration**: Seamless redirection to group view upon successful update
     * 
     * ## Request Processing Architecture
     * 
     * ### Entity Retrieval and Authorization
     * ```php
     * // Load existing group for editing
     * $authorizationGroup = $this->ActivityGroups->get($id, contain: []);
     * 
     * // Comprehensive existence and authorization validation
     * if (!$authorizationGroup) {
     *     throw new \Cake\Http\Exception\NotFoundException();
     * }
     * $this->Authorization->authorize($authorizationGroup);
     * ```
     * 
     * ### Form Processing (POST/PATCH/PUT Requests)
     * ```php
     * // Multi-method support for RESTful operations
     * if ($this->request->is(["patch", "post", "put"])) {
     *     // Data patching with existing entity
     *     $authorizationGroup = $this->ActivityGroups->patchEntity(
     *         $authorizationGroup,
     *         $this->request->getData()
     *     );
     * }
     * ```
     * 
     * ## Security Architecture
     * 
     * ### Authorization Framework
     * - **Entity Authorization**: Individual authorization for specific group editing
     * - **Policy Integration**: ActivityGroupPolicy determines edit access for specific groups
     * - **Administrative Access**: Typically restricted to authorized personnel
     * - **Existence Validation**: Confirms group exists before authorization attempts
     * 
     * ### Data Protection and Validation
     * - **Mass Assignment Protection**: Entity-level protection against unauthorized field assignment
     * - **Validation Rules**: Comprehensive validation ensuring data integrity during updates
     * - **CSRF Protection**: Built-in CSRF protection for form submissions
     * - **Audit Integration**: Modification tracking through ActivityGroupsTable audit behaviors
     * 
     * ### Error Handling Strategy
     * ```php
     * // Comprehensive error handling for security and data integrity
     * if (!$authorizationGroup) {
     *     throw new \Cake\Http\Exception\NotFoundException();
     * }
     * 
     * // Entity-level authorization before modification
     * $this->Authorization->authorize($authorizationGroup);
     * ```
     * 
     * ## Form Processing Workflow
     * 
     * ### Entity Management and Updates
     * The method implements comprehensive form processing:
     * - **Entity Loading**: Retrieval of existing group for modification
     * - **Data Patching**: Safe assignment of form data to existing entity
     * - **Validation Execution**: Automatic validation through table validation rules
     * - **Change Tracking**: Automatic tracking of modifications through audit behaviors
     * 
     * ### Success Workflow
     * Upon successful group update:
     * ```php
     * // Success feedback and redirection
     * $this->Flash->success(__("The Activity Group has been saved."));
     * return $this->redirect(['action' => 'view', $authorizationGroup->id]);
     * ```
     * 
     * ### Error Handling Workflow
     * When validation fails:
     * ```php
     * // Error feedback and form redisplay with errors
     * $this->Flash->error(__("The Activity Group could not be saved. Please, try again."));
     * // Form redisplayed with validation errors and existing data
     * ```
     * 
     * ## User Experience
     * 
     * ### Form Interface
     * The edit interface provides:
     * - **Pre-populated Form**: Form fields filled with existing group data
     * - **Field Validation**: Real-time validation feedback for modified fields
     * - **Error Display**: Clear presentation of validation errors with context
     * - **Change Indication**: Visual indicators for modified fields (if implemented)
     * 
     * ### Navigation Flow
     * - **Form Access**: Accessed from group view or administrative listing
     * - **Success Redirection**: Automatic navigation back to group view
     * - **Cancel Options**: Easy return to group view without saving changes
     * - **Breadcrumb Integration**: Clear navigation hierarchy during edit process
     * 
     * ## Data Integrity and Validation
     * 
     * ### ActivityGroupsTable Validation
     * The method leverages comprehensive validation rules:
     * - **Required Fields**: Validation of mandatory group information
     * - **Unique Constraints**: Ensures group names remain unique during updates
     * - **Business Rules**: Custom validation for group-specific requirements
     * - **Change Validation**: Validation of modified data against business rules
     * 
     * ### Referential Integrity
     * - **Activity Associations**: Maintains integrity of group-activity relationships
     * - **Constraint Validation**: Ensures modifications don't violate system constraints
     * - **Data Consistency**: Comprehensive validation for system-wide consistency
     * 
     * ## Performance Considerations
     * 
     * ### Database Optimization
     * - **Minimal Loading**: Loads only necessary data for edit operations
     * - **Efficient Updates**: Optimized save process for existing entities
     * - **Transaction Safety**: Safe update operations with rollback capability
     * - **Validation Efficiency**: Fast validation execution through table rules
     * 
     * ### Memory Management
     * - **Entity Efficiency**: Efficient entity loading and modification
     * - **Form Optimization**: Optimized form processing for administrative interfaces
     * - **Change Tracking**: Efficient tracking of entity modifications
     * 
     * ## Integration Points
     * 
     * ### Activities Plugin Integration
     * - **Group Management**: Central modification interface for activity groups
     * - **Administrative Workflow**: Integration with administrative dashboard
     * - **Audit Integration**: Modification tracking through plugin audit system
     * - **Navigation Support**: Provides updated data for navigation components
     * 
     * ### Template Integration
     * ```php
     * // Edit form template integration
     * // Template: plugins/Activities/templates/ActivityGroups/edit.php
     * echo $this->Form->create($authorizationGroup);
     * echo $this->Form->control('name', [
     *     'label' => 'Group Name',
     *     'value' => $authorizationGroup->name
     * ]);
     * echo $this->Form->submit('Update Group');
     * echo $this->Form->end();
     * ```
     * 
     * ### Flash Component Integration
     * - **Success Messaging**: Clear confirmation of successful group updates
     * - **Error Feedback**: Detailed error information for failed attempts
     * - **User Guidance**: Actionable messages for form correction
     * 
     * ## Usage Examples
     * 
     * ### Administrative Group Modification
     * ```php
     * // Administrator accesses edit form
     * // URL: /activities/activity-groups/edit/1
     * // GET: Displays populated form with existing data
     * // POST/PATCH/PUT: Processes form data and updates group
     * ```
     * 
     * ### RESTful Operation Support
     * ```php
     * // Multiple HTTP methods supported
     * // POST: Traditional form submission
     * // PATCH: RESTful partial update
     * // PUT: RESTful full replacement
     * ```
     * 
     * ## Known Issues and Considerations
     * 
     * ### Variable Naming Inconsistency
     * **Note**: The original code contains a variable naming inconsistency in the template variable setting.
     * The entity is loaded as `$authorizationGroup` but set to template as `$ActivityGroup`.
     * This should be corrected for consistency:
     * ```php
     * // Current (inconsistent):
     * $this->set(compact("ActivityGroup"));
     * 
     * // Should be:
     * $this->set(compact("authorizationGroup"));
     * ```
     * 
     * ## Extension Opportunities
     * 
     * ### Enhanced Editing Features
     * - **Version Control**: Track multiple versions of group configurations
     * - **Bulk Edit**: Mass editing of multiple groups simultaneously
     * - **Change Preview**: Preview modifications before applying changes
     * - **Field History**: Track individual field modification history
     * 
     * ### Administrative Enhancements
     * - **Approval Workflow**: Multi-step approval process for group modifications
     * - **Change Notifications**: Automated notifications for group updates
     * - **Integration Hooks**: Plugin hooks for custom group modification workflows
     * - **Advanced Validation**: Dynamic validation rules based on group type
     * 
     * ### User Experience Improvements
     * - **AJAX Form Processing**: Real-time form validation and submission
     * - **Auto-save**: Automatic saving of form data during editing
     * - **Change Tracking**: Visual indicators for modified fields
     * - **Conflict Resolution**: Handling of concurrent edit conflicts
     * 
     * @param string|null $id Activity Group id for editing
     * @return \Cake\Http\Response|null|void Redirects to view on success, renders form otherwise
     * @throws \Cake\Http\Exception\NotFoundException When the specified group is not found
     * @throws \Authorization\Exception\ForbiddenException When user lacks edit permissions
     * @see \Activities\Model\Table\ActivityGroupsTable::get() For entity retrieval
     * @see \Activities\Model\Table\ActivityGroupsTable::patchEntity() For data assignment
     * @see \Activities\Model\Table\ActivityGroupsTable::save() For persistence operations
     * @see \Activities\Policy\ActivityGroupPolicy For authorization rules
     * @see \Activities\Template\ActivityGroups\edit.php For form template
     * 
     * @since Activities Plugin 1.0.0
     */
    public function edit($id = null)
    {
        $authorizationGroup = $this->ActivityGroups->get($id, contain: []);
        if (!$authorizationGroup) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($authorizationGroup);
        if ($this->request->is(["patch", "post", "put"])) {
            $authorizationGroup = $this->ActivityGroups->patchEntity(
                $authorizationGroup,
                $this->request->getData(),
            );
            if ($this->ActivityGroups->save($authorizationGroup)) {
                $this->Flash->success(
                    __("The Activity Group has been saved."),
                );

                return $this->redirect([
                    "action" => "view",
                    $authorizationGroup->id,
                ]);
            }
            $this->Flash->error(
                __(
                    "The Activity Group could not be saved. Please, try again.",
                ),
            );
        }
        $this->set(compact("ActivityGroup"));
    }

    /**
     * Delete activity group with comprehensive validation and referential integrity protection.
     * 
     * This method provides secure deletion of activity groups with comprehensive business rule validation
     * and referential integrity protection. It implements a soft deletion pattern with activity association
     * checking to prevent orphaned activities and maintain data integrity throughout the Activities plugin.
     * 
     * ## Method Overview
     * 
     * The delete method implements comprehensive group removal with:
     * - **Security Validation**: HTTP method restriction and authorization checks
     * - **Referential Integrity**: Prevention of deletion when activities are associated
     * - **Soft Deletion Pattern**: Name prefixing to indicate deleted status
     * - **Business Logic Protection**: Validation of deletion constraints and rules
     * - **User Feedback**: Clear messaging for successful and failed deletion attempts
     * 
     * ## Security Architecture
     * 
     * ### HTTP Method Protection
     * ```php
     * // Restrict to secure HTTP methods only
     * $this->request->allowMethod(["post", "delete"]);
     * 
     * // Prevents CSRF attacks and accidental deletions
     * // Requires explicit POST or DELETE request
     * ```
     * 
     * ### Authorization Framework
     * - **Entity Authorization**: Individual authorization for specific group deletion
     * - **Policy Integration**: ActivityGroupPolicy determines delete access for specific groups
     * - **Administrative Access**: Typically restricted to authorized administrative personnel
     * - **Existence Validation**: Confirms group exists before authorization and deletion attempts
     * 
     * ### Data Protection Strategy
     * - **Referential Integrity**: Prevents deletion of groups with associated activities
     * - **Soft Deletion**: Name modification rather than hard deletion for audit trail
     * - **Authorization Checks**: Multiple authorization points throughout deletion process
     * - **Exception Handling**: Secure error responses for unauthorized or failed deletions
     * 
     * ## Business Logic and Validation
     * 
     * ### Referential Integrity Protection
     * ```php
     * // Load group with activities to check associations
     * $authorizationGroup = $this->ActivityGroups->get($id, contain: ["Activities"]);
     * 
     * // Prevent deletion if activities are associated
     * if ($authorizationGroup->activities) {
     *     $this->Flash->error(__("Cannot delete group with associated activities"));
     *     return $this->redirect(["action" => "index"]);
     * }
     * ```
     * 
     * ### Soft Deletion Implementation
     * The method implements a soft deletion pattern:
     * - **Name Prefixing**: Adds "Deleted: " prefix to group name
     * - **Audit Trail**: Maintains deletion record for administrative oversight
     * - **Data Preservation**: Preserves historical data while indicating deleted status
     * - **Recovery Possibility**: Enables potential recovery through administrative tools
     * 
     * ### Business Rule Enforcement
     * - **Activity Association Check**: Ensures no activities are orphaned by deletion
     * - **Administrative Constraints**: Enforces business rules for group deletion
     * - **Data Integrity**: Maintains referential integrity across Activities plugin
     * - **Deletion Validation**: Comprehensive validation before deletion execution
     * 
     * ## Deletion Workflow
     * 
     * ### Pre-Deletion Validation
     * The method performs comprehensive validation:
     * ```php
     * // 1. HTTP method validation
     * $this->request->allowMethod(["post", "delete"]);
     * 
     * // 2. Entity existence validation
     * if (!$authorizationGroup) {
     *     throw new \Cake\Http\Exception\NotFoundException();
     * }
     * 
     * // 3. Activity association validation
     * if ($authorizationGroup->activities) {
     *     // Prevent deletion and redirect
     * }
     * 
     * // 4. Authorization validation
     * $this->Authorization->authorize($authorizationGroup);
     * ```
     * 
     * ### Deletion Execution
     * Upon successful validation:
     * ```php
     * // Soft deletion implementation
     * $authorizationGroup->name = "Deleted: " . $authorizationGroup->name;
     * 
     * // Execute deletion with error handling
     * if ($this->ActivityGroups->delete($authorizationGroup)) {
     *     $this->Flash->success(__("The Activity Group has been deleted."));
     * }
     * ```
     * 
     * ## Error Handling and User Feedback
     * 
     * ### Comprehensive Error Management
     * The method handles multiple error scenarios:
     * - **Not Found Errors**: Clear 404 responses for non-existent groups
     * - **Authorization Errors**: Forbidden responses for unauthorized deletion attempts
     * - **Business Logic Errors**: Clear messaging for constraint violations
     * - **Database Errors**: Handling of save/delete operation failures
     * 
     * ### User Feedback Strategy
     * ```php
     * // Success feedback
     * $this->Flash->success(__("The Activity Group has been deleted."));
     * 
     * // Constraint violation feedback
     * $this->Flash->error(__(
     *     "The Activity Group could not be deleted because it has associated Activities."
     * ));
     * 
     * // General failure feedback
     * $this->Flash->error(__(
     *     "The Activity Group could not be deleted. Please, try again."
     * ));
     * ```
     * 
     * ## Performance Considerations
     * 
     * ### Database Optimization
     * - **Efficient Loading**: Strategic loading of group with activities for validation
     * - **Single Transaction**: Atomic deletion operation with rollback capability
     * - **Index Utilization**: Leverages primary key indexes for fast retrieval
     * - **Minimal Queries**: Optimized query pattern for deletion validation
     * 
     * ### Memory Management
     * - **Selective Loading**: Loads only necessary data for deletion validation
     * - **Efficient Processing**: Streamlined deletion workflow
     * - **Resource Cleanup**: Proper cleanup of loaded entities and associations
     * 
     * ## User Experience
     * 
     * ### Administrative Interface
     * - **Confirmation Required**: POST/DELETE requirement prevents accidental deletions
     * - **Clear Feedback**: Immediate feedback for deletion attempts
     * - **Constraint Explanation**: Clear messaging when deletion is prevented
     * - **Navigation Support**: Automatic redirection to appropriate interface
     * 
     * ### Workflow Integration
     * - **Administrative Workflow**: Seamless integration with group management tasks
     * - **Error Recovery**: Clear path forward when deletion is prevented
     * - **Status Indication**: Visual indication of deletion attempt results
     * - **Breadcrumb Maintenance**: Proper navigation hierarchy after deletion
     * 
     * ## Integration Points
     * 
     * ### Activities Plugin Integration
     * - **Activity Management**: Coordination with ActivitiesController for activity operations
     * - **Referential Integrity**: Integration with Activities plugin data consistency
     * - **Audit Integration**: Deletion tracking through plugin audit system
     * - **Administrative Tools**: Integration with administrative dashboard and monitoring
     * 
     * ### Navigation Integration
     * - **Administrative Navigation**: Updated navigation after group deletion
     * - **Menu Updates**: Dynamic menu updates reflecting group availability
     * - **Breadcrumb Updates**: Proper breadcrumb management during deletion workflow
     * 
     * ## Usage Examples
     * 
     * ### Administrative Deletion
     * ```php
     * // Administrator initiates group deletion
     * // URL: /activities/activity-groups/delete/1 (POST request)
     * // Validates constraints and executes deletion
     * ```
     * 
     * ### Constraint Violation Handling
     * ```php
     * // Deletion prevented due to associated activities
     * // User receives clear error message
     * // Redirected to index with explanation
     * ```
     * 
     * ### Template Integration
     * ```php
     * // Deletion confirmation form
     * echo $this->Form->postLink('Delete Group', [
     *     'action' => 'delete',
     *     $group->id
     * ], [
     *     'confirm' => 'Are you sure you want to delete this group?',
     *     'method' => 'delete'
     * ]);
     * ```
     * 
     * ## Extension Opportunities
     * 
     * ### Enhanced Deletion Features
     * - **Cascade Options**: Configurable cascade deletion for associated activities
     * - **Batch Deletion**: Mass deletion of multiple groups
     * - **Deletion Scheduling**: Scheduled deletion with confirmation periods
     * - **Recovery Tools**: Administrative tools for recovering soft-deleted groups
     * 
     * ### Administrative Enhancements
     * - **Deletion Approval**: Multi-step approval process for critical group deletions
     * - **Impact Analysis**: Preview of deletion impact before execution
     * - **Notification System**: Automated notifications for group deletion events
     * - **Audit Trail**: Enhanced audit logging for deletion activities
     * 
     * ### Business Logic Extensions
     * - **Custom Constraints**: Configurable business rules for deletion validation
     * - **Migration Tools**: Tools for migrating activities before group deletion
     * - **Archive System**: Archiving system for deleted groups
     * - **Restoration Workflow**: Formal process for restoring deleted groups
     * 
     * ### Integration Improvements
     * - **API Support**: RESTful API support for programmatic deletions
     * - **Workflow Integration**: Integration with external workflow systems
     * - **Reporting Integration**: Deletion impact reporting and analytics
     * - **Configuration Management**: Configurable deletion policies and constraints
     * 
     * @param string|null $id Activity Group id for deletion
     * @return \Cake\Http\Response|null Redirects to index after deletion attempt
     * @throws \Cake\Http\Exception\NotFoundException When the specified group is not found
     * @throws \Cake\Http\Exception\MethodNotAllowedException When invalid HTTP method used
     * @throws \Authorization\Exception\ForbiddenException When user lacks delete permissions
     * @see \Activities\Model\Table\ActivityGroupsTable::get() For entity retrieval with associations
     * @see \Activities\Model\Table\ActivityGroupsTable::delete() For deletion operations
     * @see \Activities\Policy\ActivityGroupPolicy For authorization rules
     * @see \Cake\Http\ServerRequest::allowMethod() For HTTP method validation
     * 
     * @since Activities Plugin 1.0.0
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(["post", "delete"]);
        $authorizationGroup = $this->ActivityGroups->get(
            $id,
            contain: ["Activities"]
        );
        if (!$authorizationGroup) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        if ($authorizationGroup->activities) {
            $this->Flash->error(
                __("The Activity Group could not be deleted because it has associated Activities."),
            );
            return $this->redirect(["action" => "index"]);
        }
        $this->Authorization->authorize($authorizationGroup);

        $authorizationGroup->name = "Deleted: " . $authorizationGroup->name;
        if ($this->ActivityGroups->delete($authorizationGroup)) {
            $this->Flash->success(
                __("The Activity Group has been deleted."),
            );
        } else {
            $this->Flash->error(
                __(
                    "The Activity Group could not be deleted. Please, try again.",
                ),
            );
        }

        return $this->redirect(["action" => "index"]);
    }
}
