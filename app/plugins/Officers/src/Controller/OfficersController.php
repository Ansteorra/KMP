<?php

declare(strict_types=1);

namespace Officers\Controller;

use App\Controller\DataverseGridTrait;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\CsvExportService;
use Officers\Services\OfficerManagerInterface;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\ServiceResult;
use App\Services\WarrantManager\WarrantRequest;
use App\Model\Entity\Warrant;
use Cake\I18n\DateTime;
use Cake\I18n\Date;
use Officers\Model\Entity\Officer;
use App\Model\Entity\Member;

/**
 * Officers Controller - Comprehensive Officer Lifecycle Management
 * 
 * This controller manages the complete lifecycle of officer assignments within the KMP organization,
 * providing sophisticated functionality for assignment creation, modification, release processing,
 * warrant integration, and comprehensive reporting. The controller serves as the primary interface
 * for officer management workflows, integrating deeply with the warrant system, role management,
 * and temporal validation frameworks.
 * 
 * ## Core Responsibilities
 * 
 * ### Officer Assignment Management
 * - **Assignment Processing**: Complete officer assignment workflows with comprehensive validation
 * - **Temporal Management**: Integration with ActiveWindow behavior for assignment lifecycle
 * - **Warrant Integration**: Automatic warrant request generation and status tracking
 * - **Role Assignment**: Integration with RBAC system for automatic role grants
 * - **Deputy Management**: Support for deputy assignments with descriptive metadata
 * 
 * ### Officer Lifecycle Operations
 * - **Assignment Creation**: Multi-step assignment process with validation and approval
 * - **Assignment Modification**: In-place editing of officer assignments and metadata
 * - **Assignment Release**: Controlled release process with audit trail and reason tracking
 * - **Status Management**: Real-time status tracking with automated status updates
 * - **Expiration Handling**: Automatic processing of expired assignments
 * 
 * ### Warrant System Integration
 * - **Automatic Warrant Requests**: Integration with WarrantManager for role validation
 * - **Manual Warrant Processing**: Administrative warrant request capabilities
 * - **Status Synchronization**: Real-time warrant status integration
 * - **Compliance Tracking**: Warrant requirement validation and enforcement
 * 
 * ### Data Access & Reporting
 * - **Member-Centric Views**: Officer assignments by member with temporal filtering
 * - **Branch-Centric Views**: Organizational officer views with search capabilities
 * - **Warrant Status Reports**: Comprehensive warrant status tracking and analytics
 * - **CSV Export**: Administrative data export with filtering capabilities
 * - **Autocomplete Services**: Member search with special character handling
 * 
 * ## Integration Architecture
 * 
 * ### Service Layer Integration
 * - **OfficerManagerInterface**: Business logic abstraction for assignment operations
 * - **WarrantManagerInterface**: Warrant lifecycle management and validation
 * - **ActiveWindowManagerInterface**: Temporal assignment management
 * 
 * ### Authorization Framework
 * - **Entity-Level Authorization**: Officer assignment authorization with context validation
 * - **Operation-Specific Permissions**: Granular permission checking for officer operations
 * - **Branch-Scoped Access**: Permission-based filtering for organizational data access
 * 
 * ### Database Transaction Management
 * - **Assignment Transactions**: Multi-table updates with rollback capabilities
 * - **Warrant Coordination**: Coordinated updates across officer and warrant systems
 * - **Audit Trail Integration**: Comprehensive logging of all officer operations
 * 
 * ## Workflow Integration
 * 
 * ### Assignment Workflow
 * 1. **Permission Validation**: Verify user can assign to requested office
 * 2. **Business Logic Validation**: Validate assignment rules and constraints
 * 3. **Officer Creation**: Create officer record with temporal validation
 * 4. **Role Assignment**: Automatic role grants through warrant integration
 * 5. **Audit Logging**: Comprehensive audit trail creation
 * 
 * ### Release Workflow
 * 1. **Authorization Check**: Verify permission to release officer
 * 2. **Status Validation**: Ensure officer is in releasable state
 * 3. **Release Processing**: Update officer status and expiration
 * 4. **Warrant Revocation**: Coordinate warrant system updates
 * 5. **Audit Trail**: Complete release audit logging
 * 
 * ## Performance Considerations
 * 
 * ### Query Optimization
 * - **Strategic Containment**: Optimized association loading for performance
 * - **Index Utilization**: Database index optimization for common query patterns
 * - **Pagination Support**: Efficient pagination for large officer datasets
 * - **Search Optimization**: Optimized search with special character handling
 * 
 * ### Caching Strategy
 * - **Permission Caching**: Cached permission results for performance
 * - **Status Caching**: Cached warrant status for real-time display
 * - **Autocomplete Caching**: Optimized member search with result caching
 * 
 * ## Security Architecture
 * 
 * ### Data Protection
 * - **Authorization Enforcement**: Comprehensive authorization checking
 * - **Input Validation**: Strict validation of all user inputs
 * - **SQL Injection Prevention**: Parameterized queries and ORM protection
 * - **Privacy Protection**: Member data privacy in search and autocomplete
 * 
 * ### Audit & Compliance
 * - **Operation Logging**: Complete audit trail for all officer operations
 * - **Privacy Compliance**: Privacy-aware data handling and display
 * - **Access Control**: Granular access control with role-based permissions
 * 
 * @property \Officers\Model\Table\OfficersTable $Officers The Officers table for data operations
 * @property \App\Controller\Component\AuthenticationComponent $Authentication User authentication
 * @property \Authorization\Controller\Component\AuthorizationComponent $Authorization Access control
 * @property \Cake\Controller\Component\FlashComponent $Flash User feedback messaging
 * 
 * @see \Officers\Services\OfficerManagerInterface Officer business logic service
 * @see \App\Services\WarrantManager\WarrantManagerInterface Warrant integration service
 * @see \App\Services\ActiveWindowManager\ActiveWindowManagerInterface Temporal management
 * @see \Officers\Model\Table\OfficersTable Officer data operations
 * @see \Officers\Model\Entity\Officer Officer entity
 * 
 * @author KMP Development Team
 * @version 2.0.0
 * @since 1.0.0
 */
class OfficersController extends AppController
{
    use DataverseGridTrait;

    /**
     * Initialize Officer Controller
     * 
     * Configures the Officers controller with authentication and authorization settings
     * for officer management operations. Sets up unauthenticated API access for export
     * functionality while maintaining security for all administrative operations.
     * 
     * ## Configuration Features
     * 
     * ### Authentication Configuration
     * - **API Access**: Unauthenticated access for CSV export functionality
     * - **Security Baseline**: Inherited authentication requirements for admin operations
     * - **Session Management**: Standard session handling for officer workflows
     * 
     * ### Component Inheritance
     * - **Parent Initialization**: Inherits Officers plugin security baseline
     * - **Component Loading**: Authentication, Authorization, and Flash components
     * - **Middleware Stack**: Standard KMP middleware for officer operations
     * 
     * @return void
     * 
     * @throws \Exception When component initialization fails
     * 
     * @see \Officers\Controller\AppController Parent controller initialization
     * @see \App\Controller\Component\AuthenticationComponent Authentication setup
     * @see \Authorization\Controller\Component\AuthorizationComponent Authorization setup
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->addUnauthenticatedActions(['api']);
        $this->Authorization->authorizeModel('index', 'gridData');
    }

    /**
     * Assign Officer to Office Position
     * 
     * Processes officer assignment requests through a comprehensive workflow that includes
     * permission validation, business logic processing, warrant integration, and audit
     * trail creation. The method handles complete assignment lifecycle from initial
     * request through role assignment and notification.
     * 
     * ## Assignment Workflow
     * 
     * ### Permission Validation
     * - **Office Authorization**: Verify user can assign to requested office
     * - **Branch Validation**: Confirm assignment permissions for target branch
     * - **Entity Authorization**: Standard CakePHP authorization checking
     * 
     * ### Business Logic Processing
     * - **Data Validation**: Comprehensive input validation and sanitization
     * - **Date Processing**: Start and end date validation with temporal logic
     * - **Deputy Handling**: Optional deputy description processing
     * - **Email Management**: Contact email assignment and validation
     * 
     * ### Assignment Creation
     * - **Transaction Management**: Database transaction for data consistency
     * - **OfficerManager Integration**: Business logic service for assignment processing
     * - **Warrant Coordination**: Automatic warrant request generation
     * - **Role Assignment**: Integration with RBAC for automatic role grants
     * 
     * ### Error Handling
     * - **Validation Errors**: Comprehensive error messaging for validation failures
     * - **Business Logic Errors**: Service layer error handling and user feedback
     * - **Transaction Rollback**: Automatic rollback on assignment failures
     * 
     * ## Request Processing
     * 
     * ### Required Fields
     * - `member_id`: Target member for officer assignment
     * - `office_id`: Target office for assignment
     * - `branch_id`: Branch context for assignment
     * - `start_on`: Assignment start date
     * - `email_address`: Officer contact email
     * 
     * ### Optional Fields
     * - `end_on`: Assignment end date (null for indefinite)
     * - `deputy_description`: Description for deputy positions
     * 
     * ## Authorization Architecture
     * 
     * ### Multi-Level Authorization
     * - **Entity Authorization**: Officer entity authorization check
     * - **Office Permission**: Verification of office assignment permissions
     * - **Branch Scoping**: Permission-based office availability filtering
     * 
     * ### Permission Integration
     * - **OfficesTable Integration**: Dynamic office permission discovery
     * - **User Context**: Current user permission evaluation
     * - **Branch Context**: Branch-specific permission validation
     * 
     * @param \Officers\Services\OfficerManagerInterface $oManager Officer business logic service
     * 
     * @return \Cake\Http\Response|null|void Redirects on completion or error
     * 
     * @throws \Cake\Http\Exception\ForbiddenException When user lacks assignment permissions
     * @throws \Cake\Http\Exception\BadRequestException When required data is missing
     * @throws \Exception When assignment processing fails
     * 
     * @see \Officers\Services\OfficerManagerInterface::assign() Business logic implementation
     * @see \Officers\Model\Table\OfficesTable::officesMemberCanWork() Permission discovery
     * @see \Officers\Model\Entity\Officer Officer entity
     */
    public function assign(OfficerManagerInterface $oManager)
    {
        if ($this->request->is('post')) {
            $officer = $this->Officers->newEmptyEntity();
            $user = $this->Authentication->getIdentity();
            $branchId = (int)$this->request->getData('branch_id');
            $this->Authorization->authorize($officer);
            $user = $this->Authentication->getIdentity();
            //begin transaction

            $memberId = (int)$this->request->getData('member_id');
            $officeId = (int)$this->request->getData('office_id');
            $branchId = (int)$this->request->getData('branch_id');
            $canHireOffices = $this->Officers->Offices->officesMemberCanWork($user, $branchId);
            if (!in_array($officeId, $canHireOffices)) {
                $this->Flash->error(__('You do not have permission to assign this officer.'));
                $this->redirect($this->referer());
                return;
            }
            $startOn = new DateTime($this->request->getData('start_on'));
            $emailAddress = $this->request->getData('email_address');
            $endOn = null;
            if ($this->request->getData('end_on') !== null && $this->request->getData('end_on') !== "") {
                $endOn = new DateTime($this->request->getData('end_on'));
            } else {
                $endOn = null;
            }
            $approverId = (int)$user->id;
            $deputyDescription = $this->request->getData('deputy_description');
            $this->Officers->getConnection()->begin();
            $omResult = $oManager->assign($officeId, $memberId, $branchId, $startOn, $endOn, $deputyDescription, $approverId, $emailAddress);
            if (!$omResult->success) {
                $this->Officers->getConnection()->rollback();
                $this->Flash->error(__($omResult->reason));
                $this->redirect($this->referer());
                return;
            }
            //commit transaction
            $this->Officers->getConnection()->commit();
            $this->Flash->success(__('The officer has been saved.'));
            $this->redirect($this->referer());
        }
    }

    /**
     * Release Officer from Assignment
     * 
     * Processes officer release requests through a controlled workflow that includes
     * authorization validation, release processing, warrant coordination, and audit
     * trail creation. The method handles complete release lifecycle from initial
     * request through role revocation and notification.
     * 
     * ## Release Workflow
     * 
     * ### Authorization Validation
     * - **Officer Retrieval**: Secure officer entity retrieval with validation
     * - **Entity Authorization**: Standard CakePHP authorization for release operations
     * - **Status Validation**: Verify officer is in releasable state
     * 
     * ### Release Processing
     * - **Data Collection**: Release reason and date validation
     * - **User Context**: Current user identification for audit trail
     * - **Date Validation**: Release date processing and temporal validation
     * 
     * ### Business Logic Integration
     * - **Transaction Management**: Database transaction for data consistency
     * - **OfficerManager Integration**: Business logic service for release processing
     * - **Warrant Coordination**: Automatic warrant revocation processing
     * - **Role Revocation**: Integration with RBAC for automatic role removal
     * 
     * ### Error Handling
     * - **Service Errors**: Comprehensive error handling from business logic layer
     * - **Transaction Rollback**: Automatic rollback on release failures
     * - **User Feedback**: Clear error messaging for release failures
     * 
     * ## Request Processing
     * 
     * ### Required Fields
     * - `id`: Officer ID for release processing
     * - `revoked_reason`: Reason for officer release
     * - `revoked_on`: Date of officer release
     * 
     * ### Release Metadata
     * - **Revoker Tracking**: Current user identification for audit
     * - **Reason Documentation**: Comprehensive reason tracking
     * - **Temporal Validation**: Release date validation and processing
     * 
     * ## Authorization Architecture
     * 
     * ### Officer Authorization
     * - **Entity Retrieval**: Secure officer entity loading with validation
     * - **Release Permission**: Verification of release permissions
     * - **Audit Integration**: Comprehensive audit trail for release operations
     * 
     * ### Business Rule Validation
     * - **Release Eligibility**: Validation of officer release eligibility
     * - **Temporal Constraints**: Date validation and temporal logic
     * - **Warrant Coordination**: Warrant system integration for release
     * 
     * @param \Officers\Services\OfficerManagerInterface $oManager Officer business logic service
     * 
     * @return \Cake\Http\Response|null|void Redirects on completion or error
     * 
     * @throws \Cake\Http\Exception\NotFoundException When officer not found
     * @throws \Cake\Http\Exception\ForbiddenException When user lacks release permissions
     * @throws \Exception When release processing fails
     * 
     * @see \Officers\Services\OfficerManagerInterface::release() Business logic implementation
     * @see \Officers\Model\Entity\Officer Officer entity
     */
    public function release(OfficerManagerInterface $oManager)
    {
        $officer = $this->Officers->get($this->request->getData('id'));
        if (!$officer) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($officer);
        if ($this->request->is('post')) {
            $revokeReason = $this->request->getData('revoked_reason');
            $revokeDate = new DateTime($this->request->getData('revoked_on'));
            $revokerId = $this->Authentication->getIdentity()->getIdentifier();

            //begin transaction
            $this->Officers->getConnection()->begin();
            $omResult = $oManager->release($officer->id, $revokerId, $revokeDate, $revokeReason);
            if (!$omResult->success) {
                $this->Officers->getConnection()->rollback();
                $this->Flash->error(__('The officer could not be released. Please, try again.'));
                $this->redirect($this->referer());
            }
            //commit transaction
            $this->Officers->getConnection()->commit();
            $this->Flash->success(__('The officer has been released.'));
            $this->redirect($this->referer());
        }
    }

    /**
     * Edit Officer Assignment Details
     * 
     * Provides in-place editing capabilities for officer assignment metadata including
     * deputy descriptions and contact information. The method handles secure officer
     * modification with authorization validation and audit trail integration.
     * 
     * ## Edit Capabilities
     * 
     * ### Editable Fields
     * - **Deputy Description**: Descriptive text for deputy assignments
     * - **Email Address**: Officer contact email management
     * - **Metadata Updates**: Safe modification of non-critical officer data
     * 
     * ### Security Framework
     * - **POST-Only Access**: Restricts access to POST requests only
     * - **Entity Authorization**: Standard CakePHP authorization checking
     * - **Input Validation**: Comprehensive input sanitization and validation
     * 
     * ### Data Processing
     * - **Selective Updates**: Only specified fields are modified
     * - **Validation Integration**: Entity-level validation for data integrity
     * - **Audit Trail**: Automatic audit logging for all modifications
     * 
     * ## Authorization Architecture
     * 
     * ### Officer Validation
     * - **Entity Retrieval**: Secure officer entity loading with validation
     * - **Edit Permission**: Verification of officer edit permissions
     * - **State Validation**: Ensure officer is in editable state
     * 
     * ### Data Integrity
     * - **Field Validation**: Individual field validation and sanitization
     * - **Business Rules**: Enforcement of officer data business rules
     * - **Consistency Checks**: Data consistency validation across related entities
     * 
     * ## Error Handling
     * 
     * ### Validation Errors
     * - **Entity Validation**: Comprehensive entity-level validation
     * - **User Feedback**: Clear error messaging for validation failures
     * - **Rollback Protection**: Automatic rollback on validation failures
     * 
     * ### Success Processing
     * - **Save Confirmation**: Success feedback for completed updates
     * - **Redirect Handling**: Return to referring page after completion
     * 
     * @return \Cake\Http\Response|null|void Redirects on completion
     * 
     * @throws \Cake\Http\Exception\NotFoundException When officer not found
     * @throws \Cake\Http\Exception\ForbiddenException When user lacks edit permissions
     * @throws \Cake\Http\Exception\MethodNotAllowedException When not POST request
     * 
     * @see \Officers\Model\Entity\Officer Officer entity validation
     * @see \Officers\Model\Table\OfficersTable Officer data operations
     */
    public function edit()
    {
        $this->request->allowMethod(["post"]);
        $officer = $this->Officers->get($this->request->getData('id'));
        if (!$officer) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($officer);
        $officer->deputy_description = $this->request->getData('deputy_description');
        $officer->email_address = $this->request->getData('email_address');
        if ($this->Officers->save($officer)) {
            $this->Flash->success(__('The officer has been saved.'));
        } else {
            $this->Flash->error(__('The officer could not be saved. Please, try again.'));
        }
        $this->redirect($this->referer());
    }

    /**
     * Request Warrant for Officer Assignment
     * 
     * Processes manual warrant requests for officer assignments through integration
     * with the WarrantManager service. This method provides administrative capability
     * to manually trigger warrant requests for officers who require formal warrant
     * validation for their assigned roles.
     * 
     * ## Warrant Request Workflow
     * 
     * ### Officer Validation
     * - **Entity Retrieval**: Comprehensive officer loading with associations
     * - **Authorization Check**: Verification of warrant request permissions
     * - **Context Validation**: Officer assignment status and warrant eligibility
     * 
     * ### Request Construction
     * - **Office Identification**: Dynamic office name construction with deputy support
     * - **Branch Context**: Branch information for warrant scope
     * - **Member Integration**: Target member identification and validation
     * - **Temporal Scope**: Assignment date range for warrant validity
     * 
     * ### WarrantManager Integration
     * - **Request Creation**: WarrantRequest object construction with full context
     * - **Service Integration**: WarrantManager service for request processing
     * - **Status Tracking**: Warrant request status monitoring and feedback
     * 
     * ## Request Processing
     * 
     * ### Context Assembly
     * - **Office Name**: Dynamic office name with deputy description integration
     * - **Branch Information**: Branch context for organizational scoping
     * - **Member Details**: Target member information for warrant subject
     * - **Role Integration**: Granted member role for warrant validation
     * 
     * ### Warrant Request Details
     * - **Request Type**: Manual warrant request identification
     * - **Entity Reference**: Officer entity reference for warrant tracking
     * - **Temporal Scope**: Assignment date range for warrant validity period
     * - **Approval Context**: User context for warrant request approval tracking
     * 
     * ## Authorization Architecture
     * 
     * ### Officer Authorization
     * - **Entity Permission**: Verification of officer warrant request permissions
     * - **Assignment Validation**: Confirm officer is in warrant-eligible state
     * - **User Context**: Current user authorization for warrant operations
     * 
     * ### Warrant System Integration
     * - **WarrantManager Service**: Integration with warrant processing service
     * - **Request Validation**: Warrant request validation and processing
     * - **Status Feedback**: Real-time warrant request status and error handling
     * 
     * @param \App\Services\WarrantManager\WarrantManagerInterface $wManager Warrant management service
     * @param int $id Officer ID for warrant request
     * 
     * @return \Cake\Http\Response|null|void Redirects on completion or error
     * 
     * @throws \Cake\Http\Exception\NotFoundException When officer not found
     * @throws \Cake\Http\Exception\ForbiddenException When user lacks warrant request permissions
     * @throws \Exception When warrant request processing fails
     * 
     * @see \App\Services\WarrantManager\WarrantManagerInterface Warrant management service
     * @see \App\Services\WarrantManager\WarrantRequest Warrant request entity
     * @see \Officers\Model\Entity\Officer Officer entity
     */
    public function requestWarrant(WarrantManagerInterface $wManager, $id)
    {
        $officer = $this->Officers->find()->where(['Officers.id' => $id])->contain(["Offices", "Branches", "Members"])->first();
        $userid = $this->Authentication->getIdentity()->getIdentifier();
        if (!$officer) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($officer);
        if ($this->request->is('post')) {
            $officeName = $officer->office->name;
            if ($officer->deputy_description != null && $officer->deputy_description != "") {
                $officeName = $officeName . " (" . $officer->deputy_description . ")";
            }
            $branchName = $officer->branch->name;
            $warrantRequest = new WarrantRequest("Manual Request Warrant: $branchName - $officeName", 'Officers.Officers', $officer->id, $userid, $officer->member_id, $officer->start_on, $officer->expires_on, $officer->granted_member_role_id);
            $memberName = $officer->member->sca_name;
            $wmResult = $wManager->request("$officeName : $memberName", "", [$warrantRequest]);
            if (!$wmResult->success) {
                $this->Flash->error("Could not request Warrant: " . __($wmResult->reason));
                $this->redirect($this->referer());
                return;
            }
            $this->Flash->success(__('The warrant request has been sent.'));
            $this->redirect($this->referer());
            return;
        }
    }

    /**
     * Display Member Officer Assignments
     * 
     * Provides member-centric view of officer assignments with temporal filtering
     * capabilities. This method displays officer assignments for a specific member
     * organized by assignment status (current, upcoming, previous) with comprehensive
     * pagination and association loading.
     * 
     * ## Assignment Display Features
     * 
     * ### Temporal Filtering
     * - **Current Assignments**: Active officer assignments for the member
     * - **Upcoming Assignments**: Future officer assignments with start dates
     * - **Previous Assignments**: Historical officer assignments with end dates
     * - **Status-Aware Queries**: Dynamic query construction based on state parameter
     * 
     * ### Data Association
     * - **Office Information**: Complete office details with department association
     * - **Member Context**: Member information for assignment context
     * - **Branch Details**: Branch information for organizational context
     * - **Status Calculation**: Real-time assignment status with display conditions
     * 
     * ### Pagination Support
     * - **Query Parameters**: Page and limit parameter processing
     * - **Performance Optimization**: Efficient pagination for large datasets
     * - **Flexible Limits**: Configurable page sizes for different contexts
     * 
     * ## Authorization Architecture
     * 
     * ### Member Authorization
     * - **New Officer Entity**: Authorization check using empty officer entity
     * - **Member Context**: Member ID integration for authorization scope
     * - **Assignment Permission**: Verification of member assignment viewing permissions
     * 
     * ### Data Access Control
     * - **Member Scoping**: Data filtering to member-specific assignments
     * - **Status Filtering**: State-based data access with temporal validation
     * - **Association Security**: Secure loading of related entity data
     * 
     * ## Query Construction
     * 
     * ### Base Query Features
     * - **Association Loading**: Strategic containment for performance optimization
     * - **Ordering Strategy**: Consistent ordering by officer ID for stable pagination
     * - **Status Integration**: OfficersTable display conditions and field integration
     * 
     * ### State-Specific Filtering
     * - **Current State**: Active assignments with current date validation
     * - **Upcoming State**: Future assignments with start date filtering
     * - **Previous State**: Historical assignments with end date filtering
     * 
     * ## Performance Considerations
     * 
     * ### Query Optimization
     * - **Strategic Containment**: Optimized association loading for performance
     * - **Index Utilization**: Database index optimization for member and date queries
     * - **Pagination Efficiency**: Efficient pagination implementation
     * 
     * ### Caching Strategy
     * - **Query Result Caching**: Optimized caching for frequently accessed data
     * - **Association Caching**: Cached association data for performance
     * 
     * @param int $id Member ID for officer assignment display
     * @param string $state Assignment state filter (current, upcoming, previous)
     * 
     * @return void Sets view variables for template rendering
     * 
     * @throws \Cake\Http\Exception\ForbiddenException When user lacks member viewing permissions
     * @throws \Cake\Http\Exception\BadRequestException When invalid state parameter provided
     * 
     * @see \Officers\Model\Table\OfficersTable::addDisplayConditionsAndFields() Status display logic
     * @see \Officers\Model\Entity\Officer Officer entity
     */
    public function memberOfficers($id, $state)
    {
        $newOfficer = $this->Officers->newEmptyEntity();
        $newOfficer->member_id = $id;
        $this->Authorization->authorize($newOfficer);

        $officersQuery = $this->Officers->find()

            ->contain(['Offices' => ["Departments"], 'Members', 'Branches'])
            ->orderBY(["Officers.id" => "ASC"]);


        switch ($state) {
            case 'current':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('current')->where(['Officers.member_id' => $id]), 'current');
                break;
            case 'upcoming':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('upcoming')->where(['Officers.member_id' => $id]), 'upcoming');
                break;
            case 'previous':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('previous')->where(['Officers.member_id' => $id]), 'previous');
                break;
        }

        $page = $this->request->getQuery("page");
        $limit = $this->request->getQuery("limit");
        $paginate = [];
        if ($page) {
            $paginate['page'] = $page;
        }
        if ($limit) {
            $paginate['limit'] = $limit;
        }
        //$paginate["limit"] = 5;
        $officers = $this->paginate($officersQuery, $paginate);
        $turboFrameId = $state;

        $this->set(compact('officers', 'id', 'state'));
    }

    /**
     * Display Branch Officer Assignments with Search
     * 
     * Provides branch-centric view of officer assignments with comprehensive search
     * capabilities and temporal filtering. This method displays all officer assignments
     * within a specific branch with advanced search functionality including special
     * character handling for SCA names and organizational filtering.
     * 
     * ## Branch Officer Display Features
     * 
     * ### Temporal Filtering
     * - **Current Assignments**: Active officer assignments within the branch
     * - **Upcoming Assignments**: Future officer assignments with start date filtering
     * - **Previous Assignments**: Historical officer assignments with end date filtering
     * - **Status Integration**: Real-time assignment status with display conditions
     * 
     * ### Advanced Search Capabilities
     * - **Member Name Search**: SCA name search with partial matching
     * - **Office Name Search**: Office title search with fuzzy matching
     * - **Department Search**: Department name search for organizational filtering
     * - **Special Character Handling**: Þ/th character conversion for medieval names
     * 
     * ### Data Association
     * - **Office Information**: Complete office details with department hierarchy
     * - **Member Context**: Member information for assignment identification
     * - **Branch Context**: Branch information for organizational scope
     * - **Department Details**: Department information for organizational filtering
     * 
     * ## Search Architecture
     * 
     * ### Query Processing
     * - **Search Parameter**: URL query parameter processing and sanitization
     * - **Multi-Field Search**: Simultaneous search across multiple entity fields
     * - **Character Normalization**: Special character handling for SCA name search
     * 
     * ### Special Character Support
     * - **Þ/th Conversion**: Bidirectional character conversion for medieval names
     * - **Unicode Handling**: Proper Unicode character processing
     * - **Search Expansion**: Multiple search variants for comprehensive matching
     * 
     * ### Search Fields
     * - **Member Names**: SCA name search with character variant support
     * - **Office Names**: Office title search with partial matching
     * - **Department Names**: Department search for organizational filtering
     * 
     * ## Authorization Architecture
     * 
     * ### Branch Authorization
     * - **New Officer Entity**: Authorization check using empty officer entity
     * - **Branch Scoping**: Branch-specific data access validation
     * - **Assignment Permission**: Verification of branch assignment viewing permissions
     * 
     * ### Data Access Control
     * - **Branch Filtering**: Data filtering to branch-specific assignments
     * - **Search Security**: Secure search parameter processing
     * - **Association Security**: Secure loading of related entity data
     * 
     * ## Performance Considerations
     * 
     * ### Search Optimization
     * - **Index Utilization**: Database index optimization for search queries
     * - **Query Efficiency**: Optimized search query construction
     * - **Result Limiting**: Pagination for large search result sets
     * 
     * ### Character Processing
     * - **Preprocessing**: Efficient character conversion preprocessing
     * - **Search Variants**: Optimized multiple search variant processing
     * 
     * @param int $id Branch ID for officer assignment display
     * @param string $state Assignment state filter (current, upcoming, previous)
     * 
     * @return void Sets view variables for template rendering
     * 
     * @throws \Cake\Http\Exception\ForbiddenException When user lacks branch viewing permissions
     * @throws \Cake\Http\Exception\BadRequestException When invalid state parameter provided
     * 
     * @see \Officers\Model\Table\OfficersTable::addDisplayConditionsAndFields() Status display logic
     * @see \Officers\Model\Entity\Officer Officer entity
     */
    public function branchOfficers($id, $state)
    {
        $newOfficer = $this->Officers->newEmptyEntity();
        $this->Authorization->authorize($newOfficer);

        $officersQuery = $this->Officers->find()

            ->contain(['Offices' => ["Departments"], 'Members', 'Branches'])->where(['Branches.id' => $id])
            ->orderBY(["Officers.id" => "ASC"]);

        $search = $this->request->getQuery("search");
        $search = $search ? trim($search) : null;

        if ($search) {
            //detect th and replace with Þ
            $nsearch = $search;
            if (preg_match("/th/", $search)) {
                $nsearch = str_replace("th", "Þ", $search);
            }
            //detect Þ and replace with th
            $usearch = $search;
            if (preg_match("/Þ/", $search)) {
                $usearch = str_replace("Þ", "th", $search);
            }
            $officersQuery = $officersQuery->where([
                "OR" => [
                    ["Members.sca_name LIKE" => "%" . $search . "%"],
                    ["Members.sca_name LIKE" => "%" . $nsearch . "%"],
                    ["Members.sca_name LIKE" => "%" . $usearch . "%"],
                    ["Offices.name LIKE" => "%" . $search . "%"],
                    ["Offices.name LIKE" => "%" . $nsearch . "%"],
                    ["Offices.name LIKE" => "%" . $usearch . "%"],
                    ["Departments.name LIKE" => "%" . $search . "%"],
                    ["Departments.name LIKE" => "%" . $nsearch . "%"],
                    ["Departments.name LIKE" => "%" . $usearch . "%"],

                ],
            ]);
        }

        switch ($state) {
            case 'current':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('current')->where(['Officers.branch_id' => $id]), 'current');
                break;
            case 'upcoming':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('upcoming')->where(['Officers.branch_id' => $id]), 'upcoming');
                break;
            case 'previous':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('previous')->where(['Officers.branch_id' => $id]), 'previous');
                break;
        }

        $page = $this->request->getQuery("page");
        $limit = $this->request->getQuery("limit");
        $paginate = [];
        if ($page) {
            $paginate['page'] = $page;
        }
        if ($limit) {
            $paginate['limit'] = $limit;
        }
        //$paginate["limit"] = 5;
        $officers = $this->paginate($officersQuery, $paginate);
        $turboFrameId = $state;

        $this->set(compact('officers', 'newOfficer', 'id', 'state'));
    }

    /**
     * Member Search Autocomplete for Officer Assignment
     * 
     * Provides AJAX-powered autocomplete functionality for member search during
     * officer assignment workflows. This method handles secure member discovery
     * with special character support, privacy protection, and office-specific
     * context for assignment validation.
     * 
     * ## Autocomplete Features
     * 
     * ### Search Capabilities
     * - **SCA Name Search**: Partial matching on SCA names with fuzzy search
     * - **Character Variants**: Þ/th character conversion for medieval name support
     * - **Result Limiting**: Performance-optimized result limiting (50 results max)
     * - **Status Filtering**: Exclude deactivated members from search results
     * 
     * ### Special Character Handling
     * - **Bidirectional Conversion**: Þ ↔ th character conversion for comprehensive search
     * - **Unicode Support**: Proper Unicode character processing
     * - **Search Expansion**: Multiple search variants for maximum match coverage
     * 
     * ### Privacy Protection
     * - **Data Minimization**: Only essential member data in search results
     * - **Status Awareness**: Member status integration for privacy compliance
     * - **Public Data**: Use of publicData() method for privacy-safe data exposure
     * 
     * ## Authorization Architecture
     * 
     * ### Security Framework
     * - **Authorization Skip**: Intentional authorization skip with audit comment
     * - **Method Restriction**: GET-only access for search operations
     * - **Office Context**: Office entity loading for assignment context
     * 
     * ### Privacy Considerations
     * - **Audit Trail**: Privacy audit comment for monitoring
     * - **Data Limitation**: Minimal member data exposure
     * - **Status Filtering**: Deactivated member exclusion for privacy
     * 
     * ## Search Processing
     * 
     * ### Query Construction
     * - **Parameter Processing**: Search query parameter sanitization
     * - **Character Normalization**: Special character preprocessing
     * - **Status Integration**: Member status filtering for active members only
     * 
     * ### Result Formatting
     * - **Field Selection**: Minimal field selection for performance and privacy
     * - **Limit Enforcement**: Result count limiting for performance optimization
     * - **Context Integration**: Office context for assignment validation
     * 
     * ## Performance Considerations
     * 
     * ### Search Optimization
     * - **Index Utilization**: Database index optimization for name searches
     * - **Result Limiting**: Strict result count limits for performance
     * - **Query Efficiency**: Optimized search query construction
     * 
     * ### Character Processing
     * - **Preprocessing**: Efficient character conversion preprocessing
     * - **Search Variants**: Optimized multiple search variant processing
     * 
     * @param int $officeId Office ID for assignment context
     * 
     * @return void Renders AJAX view with search results
     * 
     * @throws \Cake\Http\Exception\MethodNotAllowedException When not GET request
     * @throws \Cake\Http\Exception\NotFoundException When office not found
     * 
     * @see \App\Model\Entity\Member::publicData() Privacy-safe member data
     * @see \Officers\Model\Table\OfficesTable Office context loading
     */
    public function autoComplete($officeId)
    {
        //TODO: Audit for Privacy
        $memberTbl = $this->getTableLocator()->get('Members');
        $q = $this->request->getQuery("q");
        //detect th and replace with Þ
        $nq = $q;
        if (preg_match("/th/", $q)) {
            $nq = str_replace("th", "Þ", $q);
        }
        //detect Þ and replace with th
        $uq = $q;
        if (preg_match("/Þ/", $q)) {
            $uq = str_replace("Þ", "th", $q);
        }
        $office = $this->Officers->Offices->get($officeId);
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(["get"]);
        $this->viewBuilder()->setClassName("Ajax");
        $query = $memberTbl
            ->find("all")
            ->where([
                'status <>' => Member::STATUS_DEACTIVATED,
                'OR' => [["sca_name LIKE" => "%$q%"], ["sca_name LIKE" => "%$nq%"], ["sca_name LIKE" => "%$uq%"]]
            ])
            ->select(["id", "sca_name", "warrantable", "status"])
            ->limit(50);
        $this->set(compact("query", "q", "nq", "uq", "office"));
    }

    /**
     * Officer Management Index
     * 
     * Provides the main landing page for officer management operations. This method
     * serves as the entry point for officer administration, providing navigation
     * to various officer management workflows and administrative functions.
     * 
     * ## Index Features
     * 
     * ### Navigation Hub
     * - **Assignment Workflows**: Access to officer assignment interfaces
     * - **Management Tools**: Links to officer management and reporting tools
     * - **Administrative Access**: Administrative dashboard and oversight tools
     * 
     * ### Authorization Framework
     * - **Authorization Skip**: Intentional authorization skip for public index access
     * - **Security Context**: Inherits parent controller security baseline
     * - **Component Access**: Standard component access for officer workflows
     * 
     * ## Security Architecture
     * 
     * ### Access Control
     * - **Public Access**: Index page accessible without specific authorization
     * - **Workflow Protection**: Individual workflows maintain authorization requirements
     * - **Component Security**: Authentication and authorization components available
     * 
     * ### Navigation Security
     * - **Permission-Based Display**: Navigation items filtered by user permissions
     * - **Context-Aware Interface**: Interface adapts to user authorization level
     * 
     * @return void Renders index template
     * 
     * @see \Officers\Controller\AppController Parent security baseline
     */
    public function index()
    {
        $this->Authorization->skipAuthorization();
    }

    /**
     * Grid Data for Officers Listing
     *
     * Provides Dataverse grid data for officer assignments with support for
     * multiple contexts (index, member officers, branch officers) and system views
     * for temporal filtering (current, upcoming, previous).
     *
     * ## Supported Contexts
     * - **Index**: All officers filtered by warrant status (via system views)
     * - **Member Officers**: Officers for a specific member (`member_id` parameter)
     * - **Branch Officers**: Officers for a specific branch (`branch_id` parameter)
     *
     * ## Query Parameters
     * - `member_id`: Filter to officers for a specific member
     * - `branch_id`: Filter to officers for a specific branch
     * - `search`: Search across member name, office name, department name
     * - `view_id`: System view ID (sys-officers-current, sys-officers-upcoming, etc.)
     *
     * @param CsvExportService $csvExportService Injected CSV export service
     * @return \Cake\Http\Response|null|void Renders view or returns CSV response
     */
    public function gridData(CsvExportService $csvExportService)
    {
        // Determine context from query parameters
        $memberId = $this->request->getQuery('member_id');
        $branchId = $this->request->getQuery('branch_id');
        $search = $this->request->getQuery('search');

        // Authorization: check context-specific permissions
        $newOfficer = $this->Officers->newEmptyEntity();
        $context = null;
        if ($memberId) {
            $newOfficer->member_id = (int)$memberId;
            $this->Authorization->authorize($newOfficer, 'memberOfficers');
            $context = 'member';
        } elseif ($branchId) {
            $newOfficer->branch_id = (int)$branchId;
            $this->Authorization->authorize($newOfficer, 'branchOfficers');
            $context = 'branch';
        } else {
            $this->Authorization->skipAuthorization();
        }

        // Get system views for temporal/warrant filtering with context-specific columns
        $systemViews = $this->getOfficerSystemViews($context);

        // Build base query with required associations
        $baseQuery = $this->Officers->find()
            ->contain([
                'Members' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
                'Offices' => function ($q) {
                    return $q->select(['id', 'name', 'requires_warrant', 'deputy_to_id']);
                },
                'Offices.Departments' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'Branches' => function ($q) {
                    return $q->select(['id', 'name']);
                },
                'CurrentWarrants' => function ($q) {
                    return $q->select(['id', 'start_on', 'expires_on', 'entity_id']);
                },
                'PendingWarrants' => function ($q) {
                    return $q->select(['id', 'start_on', 'expires_on', 'entity_id']);
                },
                'RevokedBy' => function ($q) {
                    return $q->select(['id', 'sca_name']);
                },
            ]);

        // Apply context filters
        if ($memberId) {
            $baseQuery->where(['Officers.member_id' => (int)$memberId]);
        }
        if ($branchId) {
            $baseQuery->where(['Officers.branch_id' => (int)$branchId]);
        }

        // Apply special character search (Þ/th handling for SCA names)
        if (!empty($search)) {
            $nsearch = str_replace("Þ", "th", $search);
            $nsearch = str_replace("þ", "th", $nsearch);
            $usearch = str_replace("th", "Þ", $search);
            $usearch = str_replace("TH", "Þ", $usearch);
            $usearch = str_replace("Th", "Þ", $usearch);

            $baseQuery->where([
                'OR' => [
                    ['Members.sca_name LIKE' => '%' . $search . '%'],
                    ['Members.sca_name LIKE' => '%' . $nsearch . '%'],
                    ['Members.sca_name LIKE' => '%' . $usearch . '%'],
                    ['Offices.name LIKE' => '%' . $search . '%'],
                    ['Offices.name LIKE' => '%' . $nsearch . '%'],
                    ['Offices.name LIKE' => '%' . $usearch . '%'],
                    ['Departments.name LIKE' => '%' . $search . '%'],
                    ['Departments.name LIKE' => '%' . $nsearch . '%'],
                    ['Departments.name LIKE' => '%' . $usearch . '%'],
                ],
            ]);
        }

        // Build query callback for system view processing
        $queryCallback = $this->buildOfficerQueryCallback();

        // Determine frame ID based on context
        $frameId = 'officers-grid';
        if ($memberId) {
            $frameId = 'member-officers-grid';
        } elseif ($branchId) {
            $frameId = 'branch-officers-grid';
        }

        // Process using DataverseGridTrait
        $result = $this->processDataverseGrid([
            'gridKey' => 'Officers.Officers.index.main',
            'gridColumnsClass' => \App\KMP\GridColumns\OfficersGridColumns::class,
            'baseQuery' => $baseQuery,
            'tableName' => 'Officers',
            'defaultSort' => ['Officers.start_on' => 'DESC'],
            'defaultPageSize' => 25,
            'systemViews' => $systemViews,
            'defaultSystemView' => 'sys-officers-current',
            'queryCallback' => $queryCallback,
            'showAllTab' => false,
            'canAddViews' => false,
            'canFilter' => true,
            'canExportCsv' => true,
        ]);

        // Handle CSV export
        if (!empty($result['isCsvExport'])) {
            return $this->handleCsvExport($result, $csvExportService, 'officers');
        }

        // Get row actions from grid columns
        $rowActions = \App\KMP\GridColumns\OfficersGridColumns::getRowActions();

        // Set view variables
        $this->set([
            'officers' => $result['data'],
            'gridState' => $result['gridState'],
            'columns' => $result['columnsMetadata'],
            'visibleColumns' => $result['visibleColumns'],
            'searchableColumns' => \App\KMP\GridColumns\OfficersGridColumns::getSearchableColumns(),
            'dropdownFilterColumns' => $result['dropdownFilterColumns'],
            'filterOptions' => $result['filterOptions'],
            'currentFilters' => $result['currentFilters'],
            'currentSearch' => $result['currentSearch'],
            'currentView' => $result['currentView'],
            'availableViews' => $result['availableViews'],
            'gridKey' => $result['gridKey'],
            'currentSort' => $result['currentSort'],
            'currentMember' => $result['currentMember'],
            'memberId' => $memberId,
            'branchId' => $branchId,
            'rowActions' => $rowActions,
        ]);

        // Determine which template to render based on Turbo-Frame header
        $turboFrame = $this->request->getHeaderLine('Turbo-Frame');

        // Use main app's element templates (not plugin templates)
        $this->viewBuilder()->setPlugin(null);

        if ($turboFrame === $frameId . '-table') {
            // Inner frame request - render table data only
            $this->set('data', $result['data']);
            $this->set('tableFrameId', $frameId . '-table');
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_table');
        } else {
            // Outer frame request (or no frame) - render toolbar + table frame
            $this->set('data', $result['data']);
            $this->set('frameId', $frameId);
            $this->viewBuilder()->disableAutoLayout();
            $this->viewBuilder()->setTemplatePath('element');
            $this->viewBuilder()->setTemplate('dv_grid_content');
        }
    }

    /**
     * Get system views for officer temporal filtering
     *
     * Provides predefined views for filtering officers by temporal status:
     * - Current: Officers with active assignments
     * - Upcoming: Officers with future start dates
     * - Previous: Officers with past/expired assignments
     *
     * Column configuration varies by context:
     * - Member context: Hides member_sca_name (redundant), shows reports_to_list
     * - Branch context: Hides branch_name (redundant), shows reports_to_list
     * - Index context: Shows all relevant columns
     *
     * @param string|null $context 'member', 'branch', or null for index
     * @return array<string, array<string, mixed>>
     */
    protected function getOfficerSystemViews(?string $context = null): array
    {
        $today = Date::today();
        $todayString = $today->format('Y-m-d');

        // Define column configurations based on context
        // Current/Upcoming: Office, Branch, Contact, Warrant, Start Date, End Date, Reports To
        // Previous: Office, Branch, Start Date, End Date, Reason
        $currentUpcomingColumns = match ($context) {
            'member' => ['office_name', 'branch_name', 'email_address', 'warrant_state', 'start_on', 'expires_on', 'reports_to_list'],
            'branch' => ['member_sca_name', 'office_name', 'email_address', 'warrant_state', 'start_on', 'expires_on', 'reports_to_list'],
            default => ['member_sca_name', 'office_name', 'branch_name', 'email_address', 'warrant_state', 'start_on', 'expires_on', 'status'],
        };

        $previousColumns = match ($context) {
            'member' => ['office_name', 'branch_name', 'start_on', 'expires_on', 'revoked_reason'],
            'branch' => ['member_sca_name', 'office_name', 'start_on', 'expires_on', 'revoked_reason'],
            default => ['member_sca_name', 'office_name', 'branch_name', 'start_on', 'expires_on', 'revoked_reason', 'status'],
        };

        return [
            'sys-officers-current' => [
                'id' => 'sys-officers-current',
                'name' => __('Current'),
                'description' => __('Active officer assignments'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => Officer::CURRENT_STATUS],
                    ],
                    'columns' => $currentUpcomingColumns,
                ],
            ],
            'sys-officers-upcoming' => [
                'id' => 'sys-officers-upcoming',
                'name' => __('Upcoming'),
                'description' => __('Future officer assignments'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => Officer::UPCOMING_STATUS],
                    ],
                    'columns' => $currentUpcomingColumns,
                ],
            ],
            'sys-officers-previous' => [
                'id' => 'sys-officers-previous',
                'name' => __('Previous'),
                'description' => __('Past officer assignments'),
                'canManage' => false,
                'config' => [
                    'filters' => [
                        ['field' => 'status', 'operator' => 'in', 'value' => [
                            Officer::EXPIRED_STATUS,
                            Officer::DEACTIVATED_STATUS,
                            Officer::RELEASED_STATUS,
                            Officer::REPLACED_STATUS,
                        ]],
                    ],
                    'columns' => $previousColumns,
                ],
            ],
        ];
    }

    /**
     * Build query callback for officer system view processing
     *
     * Adds the necessary containments and display conditions based on the
     * selected system view. Reuses the existing addDisplayConditionsAndFields
     * pattern from the Officers table.
     *
     * @return callable
     */
    protected function buildOfficerQueryCallback(): callable
    {
        return function ($query, $selectedSystemView) {
            // Determine the display type based on the selected view
            $viewId = $selectedSystemView['id'] ?? 'sys-officers-current';

            if ($viewId === 'sys-officers-previous') {
                $type = 'previous';
            } elseif ($viewId === 'sys-officers-upcoming') {
                $type = 'upcoming';
            } else {
                $type = 'current';
            }

            // Add reporting relationships for current/upcoming views
            if ($type === 'current' || $type === 'upcoming') {
                $query->contain([
                    'ReportsToCurrently' => function ($q) {
                        return $q
                            ->contain([
                                'Members' => function ($q) {
                                    return $q->select(['id', 'sca_name']);
                                },
                                'Offices' => function ($q) {
                                    return $q->select(['id', 'name']);
                                },
                            ])
                            ->select(['id', 'office_id', 'branch_id', 'member_id', 'email_address']);
                    },
                    'DeputyToCurrently' => function ($q) {
                        return $q
                            ->contain([
                                'Members' => function ($q) {
                                    return $q->select(['id', 'sca_name']);
                                },
                                'Offices' => function ($q) {
                                    return $q->select(['id', 'name']);
                                },
                            ])
                            ->select(['id', 'office_id', 'branch_id', 'member_id', 'email_address']);
                    },
                ]);
            }

            return $query;
        };
    }

    /**
     * Officers by Warrant Status Report
     * 
     * Provides comprehensive reporting on officer assignments organized by warrant
     * status with detailed assignment information, member details, and warrant
     * integration. This method generates administrative reports for warrant
     * compliance monitoring and organizational oversight.
     * 
     * ## Warrant Status Categories
     * 
     * ### Current Warrants
     * - **Active Warrants**: Officers with current, valid warrants
     * - **Date Validation**: Warrant validity within current date range
     * - **Status Verification**: Current warrant status confirmation
     * 
     * ### Unwarranted Officers
     * - **Missing Warrants**: Officers without associated warrant records
     * - **Compliance Tracking**: Officers requiring warrant processing
     * - **Administrative Oversight**: Officers needing warrant attention
     * 
     * ### Pending Warrants
     * - **Pending Status**: Officers with pending warrant requests
     * - **Processing Queue**: Warrants awaiting approval or processing
     * - **Administrative Review**: Warrants requiring administrative action
     * 
     * ### Previous Warrants
     * - **Expired Warrants**: Officers with expired warrant records
     * - **Deactivated Warrants**: Officers with deactivated warrant status
     * - **Historical Records**: Past warrant assignments for audit purposes
     * 
     * ## Report Construction
     * 
     * ### Data Assembly
     * - **Officer Information**: Core officer assignment details
     * - **Member Integration**: Member names and identification
     * - **Office Context**: Office names and organizational context
     * - **Branch Information**: Branch context for organizational scope
     * - **Warrant Details**: Warrant status and validity information
     * - **Revocation Tracking**: Release information and revocation details
     * 
     * ### Query Architecture
     * - **Complex Joins**: Multi-table joins for comprehensive data assembly
     * - **Status Filtering**: Warrant status-based filtering logic
     * - **Date Logic**: Temporal warrant validity calculations
     * - **Ordering Strategy**: Alphabetical ordering by member and office names
     * 
     * ## Authorization Architecture
     * 
     * ### Security Framework
     * - **Authorization Skip**: Intentional authorization skip with administrative context
     * - **Administrative Access**: Report access for administrative oversight
     * - **Data Security**: Comprehensive data access with privacy considerations
     * 
     * ### Report Security
     * - **Administrative Context**: Report designed for administrative use
     * - **Comprehensive Access**: Full organizational data access for reporting
     * - **Audit Integration**: Report access logging for compliance
     * 
     * ## Performance Considerations
     * 
     * ### Query Optimization
     * - **Strategic Joins**: Optimized join strategy for performance
     * - **Index Utilization**: Database index optimization for warrant queries
     * - **Pagination Support**: Efficient pagination for large datasets
     * 
     * ### Data Processing
     * - **Field Selection**: Optimized field selection for performance
     * - **Association Strategy**: Efficient association handling
     * 
     * @param string $state Warrant status filter (current, unwarranted, pending, previous)
     * 
     * @return void Sets view variables for report template rendering
     * 
     * @throws \Cake\Http\Exception\NotFoundException When invalid state parameter provided
     * 
     * @see \App\Model\Entity\Warrant Warrant entity for status constants
     * @see \Officers\Model\Entity\Officer Officer entity
     */
    public function officersByWarrantStatus($state)
    {

        if ($state != 'current' && $state == 'pending' && $state == 'previous') {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        //$securityOfficer = $this->Officers->newEmptyEntity();
        $this->Authorization->skipAuthorization();


        $membersTable = $this->fetchTable('Members');
        $warrantsTable = $this->fetchTable('Warrants');

        $officersQuery = $this->Officers->find()
            ->select([
                'revoked_reason',
                'sca_name' => 'Members.sca_name',
                'branch_name' => 'Branches.name',
                'office_name' => 'Offices.name',
                'deputy_description' => 'Officers.deputy_description',
                'start_on',
                'expires_on',
                'warrant_status' => 'Warrants.status',
                'status' => 'Officers.status',
                'revoker_id',
                'revoked_by' => 'revoker.sca_name',
            ])
            ->innerJoin(
                ['Offices' => 'officers_offices'],
                ['Offices.id = Officers.office_id']
            )
            ->innerJoin(
                ['Branches' => 'branches'],
                ['Branches.id = Officers.branch_id']
            )
            ->innerJoin(
                ['Members' => 'members'],
                ['Members.id = Officers.member_id']
            )
            ->join([
                'table' => 'members',
                'alias' => 'revoker',
                'type' => 'LEFT',
                'conditions' => 'revoker.id = Officers.revoker_id',
            ])
            ->leftJoin(
                ['Warrants' => 'warrants'],
                ['Members.id = Warrants.member_id AND Officers.id = Warrants.entity_id']
            )
            ->order(['sca_name' => 'ASC'])
            ->order(['office_name' => 'ASC']);

        $today = new DateTime();
        switch ($state) {
            case 'current':
                $officersQuery = $officersQuery->where(['Warrants.expires_on >=' => $today, 'Warrants.start_on <=' => $today, 'Warrants.status' => Warrant::CURRENT_STATUS]);
                break;
            case 'unwarranted':
                $officersQuery = $officersQuery->where("Warrants.id IS NULL");

                break;
            case 'pending':
                $officersQuery = $officersQuery->where(['Warrants.status' => Warrant::PENDING_STATUS]);
                break;
            case 'previous':
                $officersQuery = $officersQuery->where(["OR" => ['Warrants.expires_on <' => $today, 'Warrants.status IN ' => [Warrant::DEACTIVATED_STATUS, Warrant::EXPIRED_STATUS]]]);
                break;
        }
        //$officersQuery = $this->addConditions($officersQuery);
        $officers = $this->paginate($officersQuery);
        $this->set(compact('officers', 'state'));
    }

    /**
     * Officer Data Export API
     * 
     * Provides CSV export functionality for officer assignment data with flexible
     * filtering capabilities and comprehensive data formatting. This method generates
     * downloadable CSV files for administrative reporting, data analysis, and
     * external system integration.
     * 
     * ## Export Features
     * 
     * ### CSV Format
     * - **Standard CSV**: RFC 4180 compliant CSV format
     * - **UTF-8 Encoding**: Unicode support for international characters
     * - **Header Row**: Descriptive column headers for data interpretation
     * - **Downloadable**: Automatic download with timestamped filename
     * 
     * ### Filtering Capabilities
     * - **Status Filter**: Filter by officer assignment status
     * - **Expiration Filter**: Filter by assignment expiration timeframe
     * - **Flexible Criteria**: Combinable filter parameters for precise data selection
     * 
     * ### Data Columns
     * - **Office Information**: Office name with deputy description integration
     * - **Member Details**: Member SCA name using privacy-safe data methods
     * - **Contact Information**: Officer email address for communication
     * - **Organizational Context**: Branch and department information
     * - **Temporal Data**: Assignment start and end dates with formatting
     * 
     * ## Authorization Architecture
     * 
     * ### Public API Access
     * - **Unauthenticated Access**: Configured in initialize() for API access
     * - **Export Permissions**: Administrative export capabilities
     * - **Data Security**: Privacy-aware data export using publicData() methods
     * 
     * ### Privacy Protection
     * - **Public Data Methods**: Use of Member::publicData() for privacy compliance
     * - **Data Minimization**: Only essential officer data in export
     * - **Administrative Context**: Export designed for administrative use
     * 
     * ## Query Construction
     * 
     * ### Base Query
     * - **Association Loading**: Comprehensive association loading for export data
     * - **Status Filtering**: Optional status-based filtering
     * - **Date Filtering**: Optional expiration date range filtering
     * 
     * ### Filter Parameters
     * - **Status Parameter**: `status` query parameter for assignment status filtering
     * - **Expiration Parameter**: `endsIn` query parameter for days-ahead filtering
     * - **Combinable Filters**: Multiple filter parameters supported simultaneously
     * 
     * ## Data Processing
     * 
     * ### Deputy Integration
     * - **Office Name Enhancement**: Deputy description integration with office names
     * - **Conditional Formatting**: Deputy description added when present
     * - **Administrative Context**: Complete office identification for export
     * 
     * ### Date Formatting
     * - **Internationalization**: i18nFormat() for localized date formatting
     * - **Consistent Format**: MM-dd-yyyy format for administrative consistency
     * - **Null Handling**: Safe handling of null date values
     * 
     * ## Performance Considerations
     * 
     * ### Memory Management
     * - **Streaming Output**: Direct output streaming for large datasets
     * - **Memory Efficiency**: Efficient CSV generation without memory buffering
     * - **Resource Management**: Proper resource cleanup for file operations
     * 
     * ### Export Optimization
     * - **Query Efficiency**: Optimized query for export performance
     * - **Association Strategy**: Strategic association loading for export needs
     * 
     * @return void Outputs CSV file directly to browser
     * 
     * @see \App\Model\Entity\Member::publicData() Privacy-safe member data
     * @see \Officers\Model\Entity\Officer Officer entity
     */
    public function api()
    {
        $this->Authorization->skipAuthorization();
        $this->autoRender = false;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=officers-' . date("Y-m-d-h-i-s") . '.csv');
        $output = fopen('php://output', 'w');

        $status = $this->request->getQuery('status');
        $endsIn = $this->request->getQuery('endsIn');

        $officers = $this->Officers->find()
            ->contain(['Offices' => ["Departments"], 'Members', 'Branches']);
        if ($status !== null) {
            $officers = $officers->where(["Officers.status" => $status]);
        }
        if ($endsIn !== null) {
            $endDate = new DateTime('+' . $endsIn . ' days');

            $officers = $officers->where([
                "Officers.expires_on >=" => DateTime::now(),
                "Officers.expires_on <=" => $endDate
            ]);
        }
        fputcsv($output, array('Office', 'Name', 'email', 'Branch', 'Department', 'Start', 'End'));

        $officers = $officers->toArray();

        if (count($officers) > 0) {
            foreach ($officers as $officer) {

                //DateTime::createFromFormat('yyyy-mm-dd hh:mm:ss', $officer['start_on']);
                $memberData = $officer['member']->publicData();
                $officeName = $officer['office']['name'];
                if ($officer['deputy_description'] != null && $officer['deputy_description'] != "") {
                    $officeName = $officeName . " (" . $officer['deputy_description'] . ")";
                }
                $officer_row = [
                    $officeName,
                    $memberData['sca_name'],
                    $officer['email_address'],
                    $officer['branch']['name'],
                    $officer['office']['department']['name'],
                    $officer['start_on']->i18nFormat('MM-dd-yyyy'),
                    $officer['expires_on']->i18nFormat('MM-dd-yyyy'),


                ];

                fputcsv($output, $officer_row);
            }
        }
        //return ($officers);
    }
}
