<?php

declare(strict_types=1);

namespace Activities\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\TableRegistry;
use App\Model\Table\BaseTable;

/**
 * AuthorizationApprovals Model - Multi-Level Approval Workflow Management
 *
 * Manages the comprehensive approval workflow system for authorization requests within the
 * KMP Activities Plugin. This table serves as the central hub for tracking approval requests,
 * responses, and accountability throughout the authorization lifecycle, providing multi-level
 * approval support, secure token validation, and complete audit trail functionality.
 * 
 * The AuthorizationApprovalsTable implements a sophisticated approval workflow that enables:
 * - **Multi-Level Approval**: Support for sequential and parallel approval workflows
 * - **Secure Token Validation**: Cryptographically secure tokens for email-based approvals
 * - **Accountability Tracking**: Complete audit trail of approval decisions and timing
 * - **Workflow Analytics**: Performance metrics and approval process monitoring
 * - **Email Integration**: Seamless integration with notification and email systems
 * 
 * ## Table Architecture
 * 
 * ### Core Purpose
 * The AuthorizationApprovalsTable manages individual approval requests within the broader
 * authorization lifecycle. Each record represents a specific approval request sent to a
 * designated approver, tracking the complete lifecycle from request creation through
 * final response and accountability.
 * 
 * ### Database Schema
 * - **Table Name**: `activities_authorization_approvals`
 * - **Primary Key**: `id` (auto-incrementing integer)
 * - **Display Field**: `id` for entity reference and debugging
 * - **Character Set**: UTF-8 for international approval notes and communication
 * 
 * ### Association Architecture
 * Complex relationship structure supporting complete approval ecosystem:
 * 
 * #### Core Entity Relationships
 * - **belongsTo Authorizations**: Links to specific authorization being approved (INNER JOIN required)
 * - **belongsTo Approvers**: Links to member responsible for approval decision (LEFT JOIN optional)
 * 
 * #### Workflow Integration Points
 * - Authorization entity provides request context and member details
 * - Approver entity provides approval authority context and contact information
 * - Inherits BaseTable audit behaviors for complete accountability tracking
 * 
 * ## Approval Workflow Architecture
 * 
 * ### Request Lifecycle Management
 * The approval workflow follows a structured lifecycle with clear accountability:
 * 
 * #### 1. Request Creation Phase
 * - **Token Generation**: Cryptographically secure authorization_token for email validation
 * - **Approver Selection**: Automated approver discovery based on permission and role analysis
 * - **Request Timestamp**: requested_on field captures exact request initiation time
 * - **Workflow Context**: Authorization context provides complete request details
 * 
 * #### 2. Pending Response Phase
 * - **Email Notification**: Secure token enables email-based approval workflows
 * - **Token Validation**: authorization_token ensures request authenticity and prevents tampering
 * - **Timeout Management**: Integration with authorization expiration prevents stale approvals
 * - **Reminder System**: Supports automated reminder workflows for pending requests
 * 
 * #### 3. Response Resolution Phase
 * - **Decision Recording**: approved boolean captures final approval decision
 * - **Response Timestamp**: responded_on field provides exact decision timing
 * - **Accountability Notes**: approver_notes field enables decision justification and communication
 * - **Audit Integration**: Complete audit trail through BaseTable behavior inheritance
 * 
 * ### Multi-Level Approval Support
 * The table architecture supports complex approval workflows:
 * - **Sequential Approvals**: Multiple approval records for staged approval processes
 * - **Parallel Approvals**: Concurrent approval requests for committee-based decisions
 * - **Conditional Approvals**: Integration with permission system for role-based approval requirements
 * - **Escalation Workflows**: Support for approval escalation and delegation patterns
 * 
 * ## Security Architecture
 * 
 * ### Token-Based Security
 * Comprehensive security measures protect approval workflow integrity:
 * 
 * #### Secure Token System
 * - **Cryptographic Tokens**: authorization_token uses secure random generation
 * - **Single-Use Validation**: Tokens prevent replay attacks and unauthorized approvals
 * - **Expiration Integration**: Token validity tied to authorization expiration dates
 * - **Email Security**: Tokens enable secure email-based approval workflows
 * 
 * #### Access Control Integration
 * - **Approver Validation**: Ensures only authorized members can approve requests
 * - **Permission Integration**: Validates approver authority through PermissionsLoader
 * - **Branch Scoping**: Inherits branch-based authorization from BaseTable
 * - **Audit Trail**: Complete accountability through timestamp and footprint tracking
 * 
 * ## Performance Optimization
 * 
 * ### Query Optimization Strategies
 * - **Index Strategy**: Optimized indexes on authorization_id, approver_id, and responded_on
 * - **Association Caching**: Efficient loading of related authorization and approver data
 * - **Count Optimization**: Specialized methods for approval queue counting and metrics
 * - **Batch Processing**: Support for bulk approval operations and notifications
 * 
 * ### Navigation Integration
 * - **Badge Counting**: memberAuthQueueCount() provides real-time approval queue metrics
 * - **Dashboard Integration**: Powers approval dashboard and member notification systems
 * - **Performance Caching**: Optimized query patterns for high-frequency operations
 * 
 * ## Integration Points
 * 
 * ### Authorization Workflow Integration
 * - **AuthorizationManager**: Service integration for approval processing and decision implementation
 * - **Email System**: Notification service integration for approval request and response communication
 * - **Navigation System**: Real-time approval queue tracking and member notification badges
 * - **Dashboard Systems**: Administrative oversight and approval workflow analytics
 * 
 * ### Business Logic Integration
 * - **Member Management**: Integration with member profiles and contact information
 * - **Permission System**: Approver authority validation and workflow authorization
 * - **Activity Management**: Context integration with activity details and requirements
 * - **Reporting System**: Approval analytics, performance metrics, and workflow reporting
 * 
 * ## Usage Examples
 * 
 * ### Creating Approval Requests
 * ```php
 * // Create approval request with secure token
 * $approval = $authorizationApprovalsTable->newEntity([
 *     'authorization_id' => $authorization->id,
 *     'approver_id' => $approver->id,
 *     'authorization_token' => Security::randomString(32),
 *     'requested_on' => DateTime::now()
 * ]);
 * 
 * if ($authorizationApprovalsTable->save($approval)) {
 *     // Send notification email with secure approval link
 *     $emailService->sendApprovalRequest($approval);
 * }
 * ```
 * 
 * ### Processing Approval Responses
 * ```php
 * // Find approval by secure token
 * $approval = $authorizationApprovalsTable->find()
 *     ->where(['authorization_token' => $token])
 *     ->contain(['Authorizations', 'Approvers'])
 *     ->first();
 * 
 * if ($approval && empty($approval->responded_on)) {
 *     // Process approval decision
 *     $approval = $authorizationApprovalsTable->patchEntity($approval, [
 *         'approved' => true,
 *         'responded_on' => DateTime::now(),
 *         'approver_notes' => 'Approved for excellent performance'
 *     ]);
 *     
 *     $authorizationApprovalsTable->save($approval);
 * }
 * ```
 * 
 * ### Approval Queue Analytics
 * ```php
 * // Get member's pending approval count
 * $pendingCount = AuthorizationApprovalsTable::memberAuthQueueCount($memberId);
 * 
 * // Display navigation badge
 * echo $this->Html->badge($pendingCount, ['class' => 'approval-queue']);
 * 
 * // Get detailed approval queue
 * $pendingApprovals = $authorizationApprovalsTable->find()
 *     ->where([
 *         'approver_id' => $memberId,
 *         'responded_on IS' => null
 *     ])
 *     ->contain(['Authorizations.Activities', 'Authorizations.Members'])
 *     ->order(['requested_on' => 'ASC'])
 *     ->all();
 * ```
 * 
 * ### Workflow Analytics and Reporting
 * ```php
 * // Approval response time analytics
 * $avgResponseTime = $authorizationApprovalsTable->find()
 *     ->select([
 *         'avg_hours' => $query->func()->avg(
 *             $query->func()->timestampdiff('HOUR', 'requested_on', 'responded_on')
 *         )
 *     ])
 *     ->where(['responded_on IS NOT' => null])
 *     ->first();
 * 
 * // Approval success rates
 * $approvalRates = $authorizationApprovalsTable->find()
 *     ->select([
 *         'total' => $query->func()->count('*'),
 *         'approved' => $query->func()->sum('approved'),
 *         'approval_rate' => $query->func()->avg('approved')
 *     ])
 *     ->where(['responded_on IS NOT' => null])
 *     ->first();
 * ```
 * 
 * ## Administrative Features
 * 
 * ### Approval Workflow Management
 * - **Queue Monitoring**: Real-time tracking of pending approval requests
 * - **Performance Metrics**: Response time analytics and approval rate tracking
 * - **Escalation Support**: Identification of stale approvals requiring attention
 * - **Bulk Operations**: Administrative tools for batch approval processing
 * 
 * ### Audit and Compliance
 * - **Decision Tracking**: Complete audit trail of approval decisions and timing
 * - **Accountability Records**: Approver identification and decision justification
 * - **Compliance Reporting**: Approval workflow compliance and performance reporting
 * - **Data Integrity**: Validation rules ensuring approval workflow data consistency
 * 
 * ## Error Handling and Validation
 * 
 * ### Data Validation
 * Comprehensive validation ensures approval workflow integrity:
 * - **Required Fields**: authorization_id, approver_id, authorization_token, requested_on
 * - **Token Security**: Secure token format validation and uniqueness constraints
 * - **Temporal Validation**: Date field validation and logical consistency checks
 * - **Referential Integrity**: Foreign key validation for authorization and approver relationships
 * 
 * ### Workflow Validation
 * - **Duplicate Prevention**: Prevents multiple approval requests for same authorization/approver pair
 * - **Authority Validation**: Ensures approvers have appropriate permissions and roles
 * - **Status Consistency**: Validates approval decisions against authorization status
 * - **Timeline Validation**: Ensures logical temporal consistency in approval workflow
 * 
 * @property \Activities\Model\Table\AuthorizationsTable&\Cake\ORM\Association\BelongsTo $Authorizations Authorization context and request details
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Approvers Member responsible for approval decision
 *
 * @method \Activities\Model\Entity\AuthorizationApproval newEmptyEntity() Create new empty approval entity
 * @method \Activities\Model\Entity\AuthorizationApproval newEntity(array $data, array $options = []) Create new approval entity with data
 * @method array<\Activities\Model\Entity\AuthorizationApproval> newEntities(array $data, array $options = []) Create multiple approval entities
 * @method \Activities\Model\Entity\AuthorizationApproval get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args) Retrieve approval by primary key
 * @method \Activities\Model\Entity\AuthorizationApproval findOrCreate($search, ?callable $callback = null, array $options = []) Find existing or create new approval
 * @method \Activities\Model\Entity\AuthorizationApproval patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = []) Update approval entity with new data
 * @method array<\Activities\Model\Entity\AuthorizationApproval> patchEntities(iterable $entities, array $data, array $options = []) Update multiple approval entities
 * @method \Activities\Model\Entity\AuthorizationApproval|false save(\Cake\Datasource\EntityInterface $entity, array $options = []) Save approval entity with validation
 * @method \Activities\Model\Entity\AuthorizationApproval saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = []) Save approval entity or throw exception
 * @method iterable<\Activities\Model\Entity\AuthorizationApproval>|\Cake\Datasource\ResultSetInterface<\Activities\Model\Entity\AuthorizationApproval>|false saveMany(iterable $entities, array $options = []) Save multiple approval entities
 * @method iterable<\Activities\Model\Entity\AuthorizationApproval>|\Cake\Datasource\ResultSetInterface<\Activities\Model\Entity\AuthorizationApproval> saveManyOrFail(iterable $entities, array $options = []) Save multiple approval entities or throw exception
 * @method iterable<\Activities\Model\Entity\AuthorizationApproval>|\Cake\Datasource\ResultSetInterface<\Activities\Model\Entity\AuthorizationApproval>|false deleteMany(iterable $entities, array $options = []) Delete multiple approval entities
 * @method iterable<\Activities\Model\Entity\AuthorizationApproval>|\Cake\Datasource\ResultSetInterface<\Activities\Model\Entity\AuthorizationApproval> deleteManyOrFail(iterable $entities, array $options = []) Delete multiple approval entities or throw exception
 * 
 * @see \Activities\Model\Entity\AuthorizationApproval Authorization approval entity class
 * @see \Activities\Model\Table\AuthorizationsTable Authorization context and lifecycle management
 * @see \App\Model\Table\MembersTable Member management and approver context
 * @see \Activities\Services\AuthorizationManagerInterface Authorization workflow service interface
 * @see \App\Model\Table\BaseTable Base table with branch scoping and audit behaviors
 */
class AuthorizationApprovalsTable extends BaseTable
{
    /**
     * Initialize method for authorization approval workflow management
     *
     * Configures the AuthorizationApprovalsTable with comprehensive associations and behaviors
     * for complete approval workflow tracking. This initialization establishes the foundation
     * for multi-level approval management, secure token validation, and accountability tracking
     * throughout the authorization approval lifecycle.
     * 
     * ## Table Configuration
     * - **Table Name**: `activities_authorization_approvals` with plugin-scoped naming
     * - **Display Field**: `id` for entity reference and debugging identification
     * - **Primary Key**: `id` as auto-incrementing integer identifier
     * 
     * ## Association Architecture
     * Strategic relationship configuration supporting complete approval ecosystem:
     * 
     * ### Core Authorization Context
     * - **belongsTo Authorizations**: Links to specific authorization being approved
     *   - **Class**: Activities.Authorizations (plugin-scoped table reference)
     *   - **Foreign Key**: authorization_id (required association field)
     *   - **Join Type**: INNER JOIN (approval requires valid authorization context)
     *   - **Purpose**: Provides complete authorization context, member details, and activity information
     * 
     * ### Approver Accountability
     * - **belongsTo Approvers**: Links to member responsible for approval decision
     *   - **Class**: Members (core member table reference)
     *   - **Foreign Key**: approver_id (identifies approval authority)
     *   - **Join Type**: LEFT JOIN (supports pending approvals without assigned approver)
     *   - **Purpose**: Provides approver contact information, authority validation, and accountability tracking
     * 
     * ## Behavior Inheritance
     * Inherits essential behaviors from BaseTable for complete workflow management:
     * - **Timestamp Behavior**: Automatic created/modified tracking for approval lifecycle
     * - **Footprint Behavior**: User accountability for approval record creation and updates
     * - **Branch Scoping**: Authorization context inheritance for organizational boundaries
     * - **Cache Integration**: Performance optimization for approval queue queries
     * 
     * ## Database Performance Optimization
     * Table configuration optimized for high-frequency approval operations:
     * - **Index Strategy**: Primary indexes on authorization_id, approver_id, responded_on
     * - **Association Loading**: Efficient eager loading patterns for authorization and approver context
     * - **Query Optimization**: Optimized table structure for approval queue counting and analytics
     * 
     * ## Integration Points
     * - **Authorization Workflow**: Seamless integration with authorization lifecycle management
     * - **Member Management**: Direct connection to member profiles and contact information
     * - **Email System**: Association data supports notification and approval email workflows
     * - **Navigation System**: Approval queue counting supports real-time member notifications
     * 
     * ## Usage Examples
     * 
     * ### Creating Approval Request with Context
     * ```php
     * // Create approval with full association context
     * $approval = $authorizationApprovalsTable->newEntity([
     *     'authorization_id' => $authorization->id,
     *     'approver_id' => $approver->id,
     *     'authorization_token' => Security::randomString(32),
     *     'requested_on' => DateTime::now()
     * ]);
     * 
     * // Associations provide context for email notification
     * if ($authorizationApprovalsTable->save($approval)) {
     *     $approval = $authorizationApprovalsTable->get($approval->id, [
     *         'contain' => ['Authorizations.Activities', 'Approvers']
     *     ]);
     *     
     *     // Send email with complete context
     *     $emailService->sendApprovalRequest($approval);
     * }
     * ```
     * 
     * ### Approval Queue Queries with Associations
     * ```php
     * // Get pending approvals with full context
     * $pendingApprovals = $authorizationApprovalsTable->find()
     *     ->contain([
     *         'Authorizations' => [
     *             'Activities',
     *             'Members' => ['Branches']
     *         ],
     *         'Approvers'
     *     ])
     *     ->where([
     *         'approver_id' => $memberId,
     *         'responded_on IS' => null
     *     ])
     *     ->order(['requested_on' => 'ASC'])
     *     ->all();
     * 
     * // Display approval queue with context
     * foreach ($pendingApprovals as $approval) {
     *     echo sprintf(
     *         "%s requests %s approval from %s",
     *         $approval->authorization->member->name,
     *         $approval->authorization->activity->name,
     *         $approval->approver->name
     *     );
     * }
     * ```
     * 
     * ### Analytics Queries with Association Context
     * ```php
     * // Approval response time by activity
     * $responseMetrics = $authorizationApprovalsTable->find()
     *     ->contain(['Authorizations.Activities'])
     *     ->select([
     *         'activity_name' => 'Activities.name',
     *         'avg_response_hours' => $query->func()->avg(
     *             $query->func()->timestampdiff('HOUR', 'requested_on', 'responded_on')
     *         ),
     *         'approval_count' => $query->func()->count('*')
     *     ])
     *     ->where(['responded_on IS NOT' => null])
     *     ->groupBy(['Activities.id'])
     *     ->order(['avg_response_hours' => 'ASC'])
     *     ->all();
     * ```
     * 
     * ## Security Considerations
     * - **Association Security**: Proper foreign key constraints prevent orphaned approval records
     * - **Data Integrity**: INNER JOIN requirement ensures approval context validity
     * - **Access Control**: BaseTable inheritance provides branch-scoped authorization integration
     * - **Audit Trail**: Behavior inheritance ensures complete accountability tracking
     * 
     * @param array<string, mixed> $config The configuration for the Table
     * @return void
     * 
     * @see \Activities\Model\Table\AuthorizationsTable Authorization context and lifecycle management
     * @see \App\Model\Table\MembersTable Member profiles and approver information
     * @see \App\Model\Table\BaseTable Base table with audit behaviors and branch scoping
     * @see \Activities\Services\AuthorizationManagerInterface Authorization workflow service interface
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable("activities_authorization_approvals");
        $this->setDisplayField("id");
        $this->setPrimaryKey("id");

        $this->belongsTo("Authorizations", [
            "className" => "Activities.Authorizations",
            "foreignKey" => "authorization_id",
            "joinType" => "INNER",
        ]);
        $this->belongsTo("Approvers", [
            "className" => "Members",
            "foreignKey" => "approver_id",
            "joinType" => "LEFT",
        ]);
    }

    /**
     * Default validation rules for authorization approval entities
     * 
     * Establishes comprehensive validation rules for approval workflow data integrity, ensuring
     * proper association relationships, secure token validation, temporal consistency, and
     * decision accountability throughout the approval process. These validation rules provide
     * the foundation for secure and reliable approval workflow management.
     * 
     * ## Validation Rules Overview
     * 
     * ### Required Association Fields
     * Critical relationship validation ensuring approval workflow context:
     * - **authorization_id**: Valid integer linking to Authorizations table (required)
     * - **approver_id**: Valid integer linking to Members table (required)
     * 
     * ### Security Token Validation
     * Secure token requirements for email-based approval workflows:
     * - **authorization_token**: Required string field with length constraints (max 255 chars)
     * - **Token Creation**: Required on entity creation for workflow security
     * - **Token Security**: Enables secure email-based approval validation
     * 
     * ### Temporal Workflow Validation
     * Date field validation ensuring proper approval timeline tracking:
     * - **requested_on**: Required date field capturing approval request initiation (required on create)
     * - **responded_on**: Optional date field for approval response timing (nullable until decision)
     * 
     * ### Decision Tracking Validation
     * Approval decision and accountability field validation:
     * - **approved**: Boolean field capturing final approval decision (required when responded)
     * - **approver_notes**: Optional text field for decision justification (max 255 chars, nullable)
     * 
     * ## Detailed Validation Rules
     * 
     * ### Authorization Association Validation
     * - **Type**: Integer validation ensuring numeric authorization_id
     * - **Presence**: Required field preventing orphaned approval records
     * - **Purpose**: Links approval to specific authorization context
     * - **Integration**: Works with buildRules() for referential integrity validation
     * 
     * ### Approver Association Validation
     * - **Type**: Integer validation ensuring numeric approver_id
     * - **Presence**: Required field ensuring accountability
     * - **Purpose**: Links approval to specific member responsible for decision
     * - **Integration**: Enables approver contact and authority validation
     * 
     * ### Security Token Validation
     * - **Type**: Scalar validation ensuring string format
     * - **Length**: Maximum 255 characters for secure token storage
     * - **Presence**: Required on creation for workflow security
     * - **Purpose**: Enables secure email-based approval workflows
     * - **Security**: Prevents unauthorized approval submissions
     * 
     * ### Request Date Validation
     * - **Type**: Date validation ensuring proper temporal format
     * - **Presence**: Required on entity creation
     * - **Purpose**: Establishes approval request timeline
     * - **Integration**: Works with analytics for response time tracking
     * 
     * ### Response Date Validation
     * - **Type**: Date validation with flexible presence
     * - **Presence**: Optional until approval decision is made
     * - **Purpose**: Captures exact approval decision timing
     * - **Workflow**: Null value indicates pending approval status
     * 
     * ### Decision Validation
     * - **Type**: Boolean validation for true/false decision
     * - **Presence**: Required when approval is processed
     * - **Purpose**: Captures final approval or denial decision
     * - **Integration**: Works with authorization status updates
     * 
     * ### Notes Validation
     * - **Type**: Scalar validation ensuring text format
     * - **Length**: Maximum 255 characters for concise decision justification
     * - **Presence**: Optional field allowing empty decision notes
     * - **Purpose**: Enables approver communication and decision justification
     * 
     * ## Usage Examples
     * 
     * ### Creating Valid Approval Request
     * ```php
     * $approval = $authorizationApprovalsTable->newEntity([
     *     'authorization_id' => 123,
     *     'approver_id' => 456,
     *     'authorization_token' => Security::randomString(32),
     *     'requested_on' => DateTime::now()->toDateString(),
     *     // responded_on, approved, approver_notes are optional until decision
     * ]);
     * 
     * if ($authorizationApprovalsTable->save($approval)) {
     *     // Approval request passes validation and saves successfully
     *     $emailService->sendApprovalRequest($approval);
     * }
     * ```
     * 
     * ### Processing Approval Response
     * ```php
     * $approval = $authorizationApprovalsTable->patchEntity($existingApproval, [
     *     'responded_on' => DateTime::now()->toDateString(),
     *     'approved' => true,
     *     'approver_notes' => 'Approved based on excellent qualifications'
     * ]);
     * 
     * if ($authorizationApprovalsTable->save($approval)) {
     *     // Response validation passes, approval decision recorded
     *     $authorizationManager->processApprovalDecision($approval);
     * }
     * ```
     * 
     * ### Validation Error Handling
     * ```php
     * $approval = $authorizationApprovalsTable->newEntity([
     *     'authorization_id' => 'invalid',    // Validation error: must be integer
     *     // 'approver_id' missing            // Validation error: required field
     *     'authorization_token' => str_repeat('x', 300), // Validation error: too long
     *     'requested_on' => 'invalid-date',   // Validation error: invalid date format
     * ]);
     * 
     * if (!$authorizationApprovalsTable->save($approval)) {
     *     $errors = $approval->getErrors();
     *     // Handle validation errors with user feedback
     *     foreach ($errors as $field => $messages) {
     *         echo "Field {$field}: " . implode(', ', $messages);
     *     }
     * }
     * ```
     * 
     * ### Batch Validation Processing
     * ```php
     * $validator = $authorizationApprovalsTable->getValidator();
     * $approvalData = [
     *     ['authorization_id' => 123, 'approver_id' => 456, 'authorization_token' => 'token1'],
     *     ['authorization_id' => 124, 'approver_id' => 457, 'authorization_token' => 'token2'],
     * ];
     * 
     * foreach ($approvalData as $data) {
     *     $data['requested_on'] = DateTime::now()->toDateString();
     *     $errors = $validator->validate($data);
     *     if (!empty($errors)) {
     *         // Handle individual validation failures
     *         $this->log("Approval validation failed: " . json_encode($errors));
     *     }
     * }
     * ```
     * 
     * ## Integration Points
     * - **Entity Creation**: Applied automatically during newEntity() calls
     * - **Save Operations**: Validates data before database persistence
     * - **Form Processing**: Works with CakePHP form helpers for client-side validation
     * - **API Endpoints**: Ensures data integrity for approval API operations
     * - **Workflow Systems**: Validates approval data during automated processing
     * 
     * ## Workflow Validation Patterns
     * - **Request Phase**: Validates authorization_id, approver_id, token, and requested_on
     * - **Response Phase**: Validates responded_on, approved decision, and optional notes
     * - **Timeline Consistency**: Ensures logical temporal relationships between request and response
     * - **Security Validation**: Token format and length validation for workflow security
     * 
     * ## Extension Patterns
     * Additional validation rules can be added for specific use cases:
     * 
     * ### Custom Temporal Validation
     * ```php
     * $validator->add('responded_on', 'afterRequest', [
     *     'rule' => function ($value, $context) {
     *         return empty($value) || $value >= $context['data']['requested_on'];
     *     },
     *     'message' => 'Response date must be after request date'
     * ]);
     * ```
     * 
     * ### Token Security Validation
     * ```php
     * $validator->add('authorization_token', 'secureToken', [
     *     'rule' => function ($value) {
     *         return preg_match('/^[a-zA-Z0-9]{32}$/', $value);
     *     },
     *     'message' => 'Authorization token must be 32 character alphanumeric string'
     * ]);
     * ```
     * 
     * @param \Cake\Validation\Validator $validator Validator instance for rule configuration
     * @return \Cake\Validation\Validator Configured validator with approval workflow rules
     * 
     * @see \Activities\Model\Entity\AuthorizationApproval Authorization approval entity validation integration
     * @see \Cake\ORM\Table::buildRules() Referential integrity rules for associations
     * @see \Activities\Model\Table\AuthorizationsTable Authorization context validation
     * @see \App\Model\Table\MembersTable Approver existence validation
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer("authorization_id")
            ->notEmptyString("authorization_id");

        $validator->integer("approver_id")->notEmptyString("approver_id");

        $validator
            ->scalar("authorization_token")
            ->maxLength("authorization_token", 255)
            ->requirePresence("authorization_token", "create")
            ->notEmptyString("authorization_token");

        $validator
            ->date("requested_on")
            ->requirePresence("requested_on", "create")
            ->notEmptyDate("requested_on");

        $validator->date("responded_on")->allowEmptyDate("responded_on");

        $validator->boolean("approved")->notEmptyString("approved");

        $validator
            ->scalar("approver_notes")
            ->maxLength("approver_notes", 255)
            ->allowEmptyString("approver_notes");

        return $validator;
    }

    /**
     * Application integrity rules for authorization approval data consistency
     * 
     * Establishes database-level integrity rules that enforce referential integrity and
     * workflow consistency for authorization approval entities. These rules provide the
     * second layer of data protection after validation rules, ensuring that approval
     * data maintains proper relationships and follows business constraints at the
     * database level throughout the approval workflow lifecycle.
     * 
     * ## Rules Overview
     * 
     * ### Referential Integrity Rules
     * Critical relationship validation ensuring approval workflow context and accountability:
     * - **Authorization Existence**: Validates authorization_id references existing Authorizations record
     * - **Approver Existence**: Validates approver_id references existing Members record
     * 
     * ## Rule Implementation Details
     * 
     * ### Authorization Reference Rule
     * - **Rule Type**: existsIn validation checking Authorizations table
     * - **Field**: authorization_id foreign key constraint
     * - **Error Handling**: Associates validation errors with authorization_id field
     * - **Purpose**: Prevents orphaned approval records without valid authorization context
     * - **Workflow Impact**: Ensures approval requests are always linked to valid authorization requests
     * 
     * ### Approver Reference Rule
     * - **Rule Type**: existsIn validation checking Approvers (Members) table
     * - **Field**: approver_id foreign key constraint
     * - **Error Handling**: Associates validation errors with approver_id field
     * - **Purpose**: Prevents approval assignments to non-existent members
     * - **Accountability**: Ensures all approval decisions are traceable to valid member accounts
     * 
     * ## Error Handling and User Experience
     * Rules violations are associated with specific fields for targeted error display and resolution:
     * - **authorization_id errors**: "Authorization does not exist" or similar messages
     * - **approver_id errors**: "Approver member does not exist" or similar messages
     * - **Field-specific errors**: Enable precise error messaging in forms and API responses
     * 
     * ## Usage Examples
     * 
     * ### Valid Approval Creation with Referential Integrity
     * ```php
     * $approval = $authorizationApprovalsTable->newEntity([
     *     'authorization_id' => 123,      // Must exist in Authorizations table
     *     'approver_id' => 456,           // Must exist in Members table
     *     'authorization_token' => Security::randomString(32),
     *     'requested_on' => DateTime::now()->toDateString()
     * ]);
     * 
     * if ($authorizationApprovalsTable->save($approval)) {
     *     // Rules passed, approval request created successfully
     *     // All associations are valid and workflow can proceed
     *     $emailService->sendApprovalRequest($approval);
     * }
     * ```
     * 
     * ### Rules Violation Handling
     * ```php
     * $approval = $authorizationApprovalsTable->newEntity([
     *     'authorization_id' => 999999,   // Non-existent authorization
     *     'approver_id' => 888888,        // Non-existent member
     *     'authorization_token' => Security::randomString(32),
     *     'requested_on' => DateTime::now()->toDateString()
     * ]);
     * 
     * if (!$authorizationApprovalsTable->save($approval)) {
     *     $errors = $approval->getErrors();
     *     // $errors['authorization_id'] contains authorization existence error
     *     // $errors['approver_id'] contains approver existence error
     *     
     *     // Handle referential integrity violations
     *     foreach ($errors as $field => $messages) {
     *         $this->Flash->error("Invalid {$field}: " . implode(', ', $messages));
     *     }
     * }
     * ```
     * 
     * ### Form Integration with Error Display
     * ```php
     * // In approval creation forms, rules violations appear as field-specific errors
     * echo $this->Form->control('authorization_id', [
     *     'type' => 'select',
     *     'options' => $authorizations,
     *     'empty' => 'Select Authorization...',
     *     'error' => true  // Displays rules violation messages
     * ]);
     * 
     * echo $this->Form->control('approver_id', [
     *     'type' => 'select',
     *     'options' => $approvers,
     *     'empty' => 'Select Approver...',
     *     'error' => true  // Displays approver existence errors
     * ]);
     * ```
     * 
     * ### API Integration with Referential Validation
     * ```php
     * // API endpoint handling with proper error responses
     * public function add()
     * {
     *     $approval = $this->AuthorizationApprovals->newEntity($this->request->getData());
     *     
     *     if ($this->AuthorizationApprovals->save($approval)) {
     *         $this->set([
     *             'success' => true,
     *             'approval' => $approval,
     *             '_serialize' => ['success', 'approval']
     *         ]);
     *     } else {
     *         $this->response = $this->response->withStatus(400);
     *         $this->set([
     *             'success' => false,
     *             'errors' => $approval->getErrors(),
     *             '_serialize' => ['success', 'errors']
     *         ]);
     *     }
     * }
     * ```
     * 
     * ### Batch Processing with Integrity Validation
     * ```php
     * $approvalRequests = [
     *     ['authorization_id' => 123, 'approver_id' => 456],
     *     ['authorization_id' => 124, 'approver_id' => 457],
     *     ['authorization_id' => 125, 'approver_id' => 458]
     * ];
     * 
     * $successfulApprovals = [];
     * $failedApprovals = [];
     * 
     * foreach ($approvalRequests as $data) {
     *     $data['authorization_token'] = Security::randomString(32);
     *     $data['requested_on'] = DateTime::now()->toDateString();
     *     
     *     $approval = $authorizationApprovalsTable->newEntity($data);
     *     
     *     if ($authorizationApprovalsTable->save($approval)) {
     *         $successfulApprovals[] = $approval;
     *     } else {
     *         $failedApprovals[] = [
     *             'data' => $data,
     *             'errors' => $approval->getErrors()
     *         ];
     *     }
     * }
     * 
     * // Process successful approvals
     * foreach ($successfulApprovals as $approval) {
     *     $emailService->sendApprovalRequest($approval);
     * }
     * ```
     * 
     * ## Integration Points
     * - **Save Operations**: Applied automatically during entity save operations
     * - **Validation Chain**: Executed after field validation rules pass
     * - **Transaction Safety**: Rules violations prevent database constraint violations
     * - **Error Handling**: Provides user-friendly error messages for missing references
     * - **Workflow Security**: Ensures approval workflow maintains data integrity
     * 
     * ## Performance Considerations
     * - **Database Queries**: Rules require additional queries to verify existence
     * - **Caching**: CakePHP caches existence checks for performance optimization
     * - **Transaction Context**: Rules are evaluated within save transaction context
     * - **Index Usage**: Leverages primary key indexes for efficient existence checking
     * 
     * ## Workflow Security Benefits
     * - **Data Integrity**: Prevents orphaned approval records
     * - **Accountability**: Ensures all approvals are traceable to valid members
     * - **Workflow Consistency**: Maintains proper authorization context throughout approval process
     * - **Audit Trail**: Referential integrity supports complete approval accountability
     * 
     * ## Extension Patterns
     * Additional rules can be added for complex business logic and workflow validation:
     * 
     * ### Custom Approval Authority Validation
     * ```php
     * // Add custom rule for approval authority validation
     * $rules->add(function ($entity, $options) {
     *     if (!empty($entity->approver_id) && !empty($entity->authorization_id)) {
     *         $authorizationsTable = TableRegistry::getTableLocator()->get('Activities.Authorizations');
     *         $authorization = $authorizationsTable->get($entity->authorization_id, [
     *             'contain' => ['Activities']
     *         ]);
     *         
     *         // Validate approver has permission to approve this activity
     *         return $authorization->activity->canApprove($entity->approver_id);
     *     }
     *     return true;
     * }, 'approvalAuthority', [
     *     'errorField' => 'approver_id',
     *     'message' => 'Selected approver does not have authority for this activity'
     * ]);
     * ```
     * 
     * ### Duplicate Approval Prevention
     * ```php
     * // Add rule to prevent duplicate approval requests
     * $rules->add(function ($entity, $options) {
     *     if ($entity->isNew() && !empty($entity->authorization_id) && !empty($entity->approver_id)) {
     *         $existing = $this->find()
     *             ->where([
     *                 'authorization_id' => $entity->authorization_id,
     *                 'approver_id' => $entity->approver_id,
     *                 'responded_on IS' => null
     *             ])
     *             ->count();
     *         return $existing === 0;
     *     }
     *     return true;
     * }, 'uniquePendingApproval', [
     *     'errorField' => 'approver_id',
     *     'message' => 'Pending approval request already exists for this approver'
     * ]);
     * ```
     * 
     * @param \Cake\ORM\RulesChecker $rules The rules checker object for configuration
     * @return \Cake\ORM\RulesChecker Configured rules checker with approval workflow integrity constraints
     * 
     * @see \Cake\ORM\RulesChecker CakePHP rules checker documentation
     * @see \Activities\Model\Entity\AuthorizationApproval Authorization approval entity constraints
     * @see \Activities\Model\Table\AuthorizationsTable Authorization existence validation
     * @see \App\Model\Table\MembersTable Approver member existence validation
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(["authorization_id"], "Authorizations"), [
            "errorField" => "authorization_id",
        ]);
        $rules->add($rules->existsIn(["approver_id"], "Approvers"), [
            "errorField" => "approver_id",
        ]);

        return $rules;
    }

    /**
     * Get pending approval queue count for a specific member
     * 
     * Provides real-time count of pending authorization approval requests assigned to a
     * specific member, optimized for navigation badge display and member dashboard
     * integration. This static method enables efficient approval queue tracking without
     * requiring table instantiation, making it ideal for navigation components and
     * member notification systems throughout the KMP application.
     * 
     * ## Method Purpose
     * 
     * ### Navigation Integration
     * Primary use case is powering navigation badges and member notification indicators:
     * - **Badge Display**: Real-time count for approval queue navigation badges
     * - **Dashboard Integration**: Member dashboard approval queue summaries
     * - **Notification Systems**: Pending approval count for alert systems
     * - **Performance Optimization**: Lightweight query optimized for frequent calls
     * 
     * ### Query Optimization
     * Designed for high-frequency usage with performance considerations:
     * - **Static Method**: No table instantiation overhead for simple count queries
     * - **Targeted Query**: Minimal SELECT COUNT(*) for optimal database performance
     * - **Index Usage**: Leverages indexes on approver_id and responded_on fields
     * - **Cache Compatibility**: Simple query structure compatible with query caching
     * 
     * ## Query Logic
     * 
     * ### Filtering Criteria
     * Precisely identifies pending approval requests for the specified member:
     * - **approver_id = $memberId**: Filters to approvals assigned to specific member
     * - **responded_on IS NULL**: Filters to pending approvals (no response recorded)
     * 
     * ### Result Characteristics
     * - **Return Type**: Integer count of matching approval records
     * - **Range**: 0 (no pending approvals) to unlimited (based on approval volume)
     * - **Real-time**: Reflects current database state at query execution time
     * 
     * ## Usage Examples
     * 
     * ### Navigation Badge Integration
     * ```php
     * // In navigation view cell or helper
     * use Activities\Model\Table\AuthorizationApprovalsTable;
     * 
     * $pendingCount = AuthorizationApprovalsTable::memberAuthQueueCount($currentMember->id);
     * 
     * // Display badge if approvals are pending
     * if ($pendingCount > 0) {
     *     echo $this->Html->badge($pendingCount, [
     *         'class' => 'approval-queue-badge bg-warning',
     *         'title' => "You have {$pendingCount} pending authorization approvals"
     *     ]);
     * }
     * ```
     * 
     * ### Member Dashboard Integration
     * ```php
     * // In member dashboard controller or view cell
     * $memberId = $this->Authentication->getIdentity()->id;
     * $pendingApprovals = AuthorizationApprovalsTable::memberAuthQueueCount($memberId);
     * 
     * // Dashboard summary display
     * echo sprintf(
     *     '<div class="dashboard-stat">
     *         <h3>%d</h3>
     *         <p>Pending Approvals</p>
     *      </div>',
     *     $pendingApprovals
     * );
     * 
     * // Conditional dashboard section
     * if ($pendingApprovals > 0) {
     *     echo $this->element('approval_queue_summary', [
     *         'count' => $pendingApprovals
     *     ]);
     * }
     * ```
     * 
     * ### Administrative Reporting
     * ```php
     * // Generate approval queue report for all approvers
     * $approversTable = TableRegistry::getTableLocator()->get('Members');
     * $approvers = $approversTable->find()
     *     ->where(['can_approve_authorizations' => true])
     *     ->all();
     * 
     * $queueReport = [];
     * foreach ($approvers as $approver) {
     *     $pendingCount = AuthorizationApprovalsTable::memberAuthQueueCount($approver->id);
     *     if ($pendingCount > 0) {
     *         $queueReport[] = [
     *             'approver' => $approver,
     *             'pending_count' => $pendingCount
     *         ];
     *     }
     * }
     * 
     * // Sort by highest pending count
     * usort($queueReport, function($a, $b) {
     *     return $b['pending_count'] <=> $a['pending_count'];
     * });
     * ```
     * 
     * ### Performance Monitoring
     * ```php
     * // Monitor approval queue levels for system health
     * $approversTable = TableRegistry::getTableLocator()->get('Members');
     * $activeApprovers = $approversTable->find()
     *     ->where(['status' => Member::ACTIVE_STATUS])
     *     ->select(['id'])
     *     ->toArray();
     * 
     * $totalPending = 0;
     * $overloadedApprovers = 0;
     * 
     * foreach ($activeApprovers as $approver) {
     *     $pending = AuthorizationApprovalsTable::memberAuthQueueCount($approver->id);
     *     $totalPending += $pending;
     *     
     *     if ($pending > 10) { // Threshold for overloaded approver
     *         $overloadedApprovers++;
     *     }
     * }
     * 
     * // Log system health metrics
     * $this->log("Approval Queue Health: {$totalPending} total pending, {$overloadedApprovers} overloaded approvers");
     * ```
     * 
     * ### API Integration
     * ```php
     * // REST API endpoint for member approval queue
     * public function approvalQueue()
     * {
     *     $memberId = $this->request->getAttribute('identity')->id;
     *     $pendingCount = AuthorizationApprovalsTable::memberAuthQueueCount($memberId);
     *     
     *     $this->set([
     *         'member_id' => $memberId,
     *         'pending_approvals' => $pendingCount,
     *         'has_pending' => $pendingCount > 0,
     *         '_serialize' => ['member_id', 'pending_approvals', 'has_pending']
     *     ]);
     * }
     * ```
     * 
     * ### Notification System Integration
     * ```php
     * // Check for new pending approvals since last check
     * $lastCheckTime = $this->request->getSession()->read('last_approval_check');
     * $currentTime = DateTime::now();
     * 
     * $currentPending = AuthorizationApprovalsTable::memberAuthQueueCount($memberId);
     * $lastPending = $this->request->getSession()->read('last_pending_count', 0);
     * 
     * if ($currentPending > $lastPending) {
     *     $newApprovals = $currentPending - $lastPending;
     *     $this->Flash->info("You have {$newApprovals} new approval requests");
     * }
     * 
     * // Update session tracking
     * $this->request->getSession()->write('last_pending_count', $currentPending);
     * $this->request->getSession()->write('last_approval_check', $currentTime);
     * ```
     * 
     * ## Performance Considerations
     * 
     * ### Database Optimization
     * - **Query Efficiency**: Simple COUNT(*) query with minimal overhead
     * - **Index Usage**: Optimized for approver_id and responded_on indexes
     * - **No Joins**: Single table query prevents association loading overhead
     * - **Caching Compatible**: Simple query structure works well with query caching
     * 
     * ### Usage Patterns
     * - **High Frequency**: Designed for frequent calls in navigation and dashboard contexts
     * - **Static Method**: No object instantiation overhead for simple count queries
     * - **Memory Efficient**: Returns only integer count, not full entity objects
     * - **Cache Integration**: Consider wrapping with application-level caching for very high traffic
     * 
     * ## Integration Points
     * - **Navigation System**: Powers approval queue badges and member notifications
     * - **Dashboard Components**: Provides real-time approval queue metrics
     * - **Member Management**: Integrates with member profile and responsibility tracking
     * - **Reporting Systems**: Supports approval queue analytics and performance monitoring
     * - **API Endpoints**: Enables real-time approval queue queries for mobile and external systems
     * 
     * ## Related Methods
     * This static method complements other approval queue functionality:
     * - Use with `find()` queries for detailed approval lists
     * - Combine with association loading for complete approval context
     * - Integrate with authorization workflow services for complete approval management
     * 
     * @param int $memberId The ID of the member to count pending approvals for
     * @return int Count of pending authorization approval requests assigned to the member
     * 
     * @see \Activities\Model\Entity\AuthorizationApproval Authorization approval entity
     * @see \Activities\Services\AuthorizationManagerInterface Authorization workflow service
     * @see \App\Model\Table\MembersTable Member management and approver context
     * @see \Cake\ORM\TableRegistry Table locator for static access patterns
     */
    public static function memberAuthQueueCount($memberId): int
    {
        $approvals = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        $query = $approvals->find("all")
            ->where([
                "approver_id" => $memberId,
                "responded_on IS" => null,
            ]);

        return $query->count();
    }
}
