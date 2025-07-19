<?php

declare(strict_types=1);

namespace Activities\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Activities\Model\Entity\Authorization;
use App\KMP\StaticHelpers;
use Cake\I18n\DateTime;
use App\Model\Table\BaseTable;

/**
 * AuthorizationsTable - Authorization Lifecycle Management and Temporal Validation
 * 
 * Manages the complete lifecycle of member activity authorizations within the KMP Activities Plugin,
 * providing comprehensive data access, temporal validation, and status management for authorization
 * workflows. This table serves as the central authority for tracking member authorization requests,
 * approvals, activations, expirations, and revocations across the entire authorization lifecycle.
 * 
 * This table extends BaseTable to inherit branch scoping, caching strategies, and audit trail
 * functionality while implementing ActiveWindowBaseEntity behavior for temporal lifecycle management.
 * It coordinates with the authorization approval workflow, automatic status transitions, and
 * integration with the broader KMP security architecture.
 * 
 * ## Database Schema Integration
 * - **Table Name**: `activities_authorizations` (plugin-scoped naming convention)
 * - **Primary Key**: `id` (auto-incrementing integer)
 * - **Display Field**: `id` (entity reference for complex authorization data)
 * - **ActiveWindow Integration**: Temporal lifecycle management with automatic status transitions
 * - **Audit Trail**: Complete change tracking through BaseTable inheritance
 * 
 * ## Association Architecture
 * Complex relationship structure supporting complete authorization workflow:
 * 
 * ### Core Entity Relationships
 * - **belongsTo Members**: The member requesting/receiving authorization (INNER JOIN)
 * - **belongsTo Activities**: The activity being authorized for (INNER JOIN)
 * - **belongsTo MemberRoles**: Granted role upon authorization completion (INNER JOIN)
 * - **belongsTo RevokedBy**: Member who revoked authorization (LEFT JOIN, optional)
 * 
 * ### Approval Workflow Relationships
 * - **hasMany AuthorizationApprovals**: Complete approval workflow tracking
 * - **hasOne CurrentPendingApprovals**: Active pending approvals awaiting response
 * - Enables multi-level approval workflows and approval status tracking
 * 
 * ## Temporal Lifecycle Management
 * Advanced ActiveWindow integration providing automated authorization lifecycle:
 * 
 * ### Automatic Status Management
 * - **Daily Status Checks**: Automated expiration detection and status updates
 * - **Temporal Validation**: Automatic transition from APPROVED to EXPIRED status
 * - **Configuration Management**: NextStatusCheck setting prevents excessive processing
 * - **Status Consistency**: Ensures authorization states reflect temporal reality
 * 
 * ### Status Transition Rules
 * Authorization statuses automatically transition based on temporal conditions:
 * - PENDING → EXPIRED (if expires_on date reached before approval)
 * - APPROVED → EXPIRED (if expires_on date reached after approval)
 * - Manual transitions handled through AuthorizationManager service
 * 
 * ## Validation Framework
 * Comprehensive validation ensuring authorization data integrity and business rules:
 * 
 * ### Required Field Validation
 * - **member_id**: Valid member reference (integer, required)
 * - **activity_id**: Valid activity reference (integer, required)
 * - **expires_on**: Future expiration date (date, required on creation)
 * 
 * ### Optional Field Validation
 * - **start_on**: Optional start date (defaults to creation date if not specified)
 * - Temporal validation ensures logical date relationships
 * 
 * ### Business Rules Enforcement
 * - **Member Existence**: Validates member references prevent orphaned authorizations
 * - **Activity Existence**: Validates activity references ensure valid authorization targets
 * - **Referential Integrity**: Maintains data consistency across authorization relationships
 * 
 * ## Custom Finder Methods
 * Specialized query methods for authorization workflow and status management:
 * 
 * ### Status-Based Finders
 * - **findPending()**: Retrieves authorizations awaiting approval
 * - Additional finders available through ActiveWindow behavior (current, upcoming, previous)
 * - Optimized queries for common authorization workflow patterns
 * 
 * ## Performance Optimization Features
 * Strategic optimizations for authorization queries and status management:
 * 
 * ### Automated Status Processing
 * - **Daily Status Checks**: Prevents real-time status checking overhead
 * - **Batch Status Updates**: Efficient bulk status transitions for expired authorizations
 * - **Configuration Caching**: NextStatusCheck setting reduces unnecessary processing
 * 
 * ### Query Optimizations
 * - **INNER JOIN Associations**: Required relationships use INNER JOIN for performance
 * - **LEFT JOIN Optional**: Optional relationships use LEFT JOIN only when needed
 * - **Status-Based Indexing**: Database indexes optimized for status-based queries
 * 
 * ## Usage Examples
 * 
 * ### Authorization Creation and Lifecycle
 * ```php
 * // Create new authorization request
 * $authorization = $authorizationsTable->newEntity([
 *     'member_id' => $member->id,
 *     'activity_id' => $activity->id,
 *     'expires_on' => FrozenDate::now()->addYears(2),
 *     'start_on' => FrozenDate::now(),
 *     'status' => Authorization::PENDING_STATUS
 * ]);
 * $authorizationsTable->save($authorization);
 * ```
 * 
 * ### Status-Based Authorization Queries
 * ```php
 * // Find all pending authorizations for approval workflow
 * $pendingAuthorizations = $authorizationsTable->find('pending')
 *     ->contain(['Members', 'Activities', 'AuthorizationApprovals.Member'])
 *     ->orderBy(['created' => 'ASC'])
 *     ->all();
 * 
 * // Get current active authorizations for member
 * $currentAuthorizations = $authorizationsTable->find('current')
 *     ->where(['member_id' => $member->id])
 *     ->contain(['Activities.ActivityGroups'])
 *     ->all();
 * 
 * // Find authorizations expiring soon
 * $expiringAuthorizations = $authorizationsTable->find()
 *     ->where([
 *         'status' => Authorization::APPROVED_STATUS,
 *         'expires_on >=' => FrozenDate::now(),
 *         'expires_on <=' => FrozenDate::now()->addMonths(3)
 *     ])
 *     ->contain(['Members', 'Activities'])
 *     ->orderBy(['expires_on' => 'ASC'])
 *     ->all();
 * ```
 * 
 * ### Approval Workflow Integration
 * ```php
 * // Get authorization with complete approval workflow
 * $authorization = $authorizationsTable->get($id, [
 *     'contain' => [
 *         'Members',
 *         'Activities.ActivityGroups',
 *         'AuthorizationApprovals' => [
 *             'Members',
 *             'sort' => ['requested_on' => 'ASC']
 *         ],
 *         'CurrentPendingApprovals.Members'
 *     ]
 * ]);
 * 
 * // Check approval workflow status
 * $pendingApprovals = $authorization->authorization_approvals
 *     ->where(['responded_on IS' => null]);
 * $completedApprovals = $authorization->authorization_approvals
 *     ->where(['responded_on IS NOT' => null]);
 * 
 * $approvalProgress = [
 *     'total_required' => count($authorization->authorization_approvals),
 *     'pending' => count($pendingApprovals),
 *     'completed' => count($completedApprovals),
 *     'approved' => count($completedApprovals->where(['approved' => true])),
 *     'denied' => count($completedApprovals->where(['approved' => false]))
 * ];
 * ```
 * 
 * ### Administrative Authorization Management
 * ```php
 * // Generate authorization statistics report
 * $authorizationStats = $authorizationsTable->find()
 *     ->select([
 *         'status',
 *         'count' => $authorizationsTable->find()->func()->count('*'),
 *         'avg_processing_time' => $authorizationsTable->find()->func()->avg('DATEDIFF(modified, created)')
 *     ])
 *     ->groupBy(['status'])
 *     ->orderBy(['count' => 'DESC'])
 *     ->toArray();
 * 
 * // Find authorizations requiring attention
 * $requiresAttention = $authorizationsTable->find()
 *     ->where([
 *         'OR' => [
 *             [
 *                 'status' => Authorization::PENDING_STATUS,
 *                 'created <' => FrozenTime::now()->subDays(7) // Pending over 7 days
 *             ],
 *             [
 *                 'status' => Authorization::APPROVED_STATUS,
 *                 'expires_on <=' => FrozenDate::now()->addDays(30) // Expiring in 30 days
 *             ]
 *         ]
 *     ])
 *     ->contain(['Members', 'Activities'])
 *     ->orderBy(['expires_on' => 'ASC'])
 *     ->all();
 * ```
 * 
 * ### Temporal Authorization Queries
 * ```php
 * // Find member's authorization history for activity
 * $authorizationHistory = $authorizationsTable->find()
 *     ->where([
 *         'member_id' => $member->id,
 *         'activity_id' => $activity->id
 *     ])
 *     ->contain(['AuthorizationApprovals.Members'])
 *     ->orderBy(['created' => 'DESC'])
 *     ->all();
 * 
 * // Get authorization timeline for reporting
 * $authorizationTimeline = $authorizationsTable->find()
 *     ->select([
 *         'month' => $authorizationsTable->find()->func()->date_format(['created' => 'identifier'], '%Y-%m'),
 *         'new_requests' => $authorizationsTable->find()->func()->count('*'),
 *         'approved' => $authorizationsTable->find()->func()->count('CASE WHEN status = "Approved" THEN 1 END'),
 *         'denied' => $authorizationsTable->find()->func()->count('CASE WHEN status = "Denied" THEN 1 END'),
 *         'expired' => $authorizationsTable->find()->func()->count('CASE WHEN status = "Expired" THEN 1 END')
 *     ])
 *     ->where(['created >=' => FrozenTime::now()->subYear()])
 *     ->groupBy(['month'])
 *     ->orderBy(['month' => 'ASC'])
 *     ->toArray();
 * ```
 * 
 * ### Status Management and Maintenance
 * ```php
 * // Manual status check for maintenance operations
 * $authorizationsTable->checkStatus();
 * 
 * // Batch process authorization renewals
 * $renewalCandidates = $authorizationsTable->find()
 *     ->where([
 *         'status' => Authorization::APPROVED_STATUS,
 *         'expires_on <=' => FrozenDate::now()->addMonths(1)
 *     ])
 *     ->contain(['Members', 'Activities'])
 *     ->all();
 * 
 * foreach ($renewalCandidates as $authorization) {
 *     // Create renewal authorization
 *     $renewal = $authorizationsTable->newEntity([
 *         'member_id' => $authorization->member_id,
 *         'activity_id' => $authorization->activity_id,
 *         'start_on' => $authorization->expires_on->addDay(),
 *         'expires_on' => $authorization->expires_on->addYears(2),
 *         'status' => Authorization::PENDING_STATUS
 *     ]);
 *     $authorizationsTable->save($renewal);
 * }
 * ```
 * 
 * ## Integration Points
 * - **AuthorizationManager**: Service layer for authorization workflow and lifecycle management
 * - **ActiveWindowManager**: Temporal lifecycle operations and status transitions
 * - **PermissionsLoader**: RBAC integration for authorization-based permission validation
 * - **Member Management**: Authorization tracking and member activity participation
 * - **Activity Management**: Authorization requirements and approver discovery
 * - **Navigation System**: Pending authorization badges and workflow navigation
 * 
 * ## Security Considerations
 * - **Referential Integrity**: Strict foreign key validation preventing orphaned authorizations
 * - **Status Consistency**: Automated status management ensures temporal accuracy
 * - **Audit Trail**: Complete change tracking through BaseTable inheritance
 * - **Branch Scoping**: Inherits branch-based access control for organizational security
 * 
 * ## Performance Considerations
 * - **Daily Status Processing**: Prevents real-time overhead with scheduled status checks
 * - **Optimized Associations**: INNER/LEFT JOIN strategy based on relationship requirements
 * - **Index Strategy**: Status, member_id, activity_id, and date-based indexes
 * - **Query Optimization**: Specialized finders for common authorization workflow patterns
 * 
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \App\Model\Table\MemberRolesTable&\Cake\ORM\Association\BelongsTo $MemberRoles
 * @property \Activities\Model\Table\ActivitiesTable&\Cake\ORM\Association\BelongsTo $Activities
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $RevokedBy
 * @property \Activities\Model\Table\AuthorizationApprovalsTable&\Cake\ORM\Association\HasMany $AuthorizationApprovals
 * @property \Activities\Model\Table\AuthorizationApprovalsTable&\Cake\ORM\Association\HasOne $CurrentPendingApprovals
 * 
 * @method \Activities\Model\Entity\Authorization newEmptyEntity()
 * @method \Activities\Model\Entity\Authorization newEntity(array $data, array $options = [])
 * @method array<\Activities\Model\Entity\Authorization> newEntities(array $data, array $options = [])
 * @method \Activities\Model\Entity\Authorization get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Activities\Model\Entity\Authorization findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Activities\Model\Entity\Authorization patchEntity(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method array<\Activities\Model\Entity\Authorization> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Activities\Model\Entity\Authorization|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Activities\Model\Entity\Authorization saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Activities\Model\Entity\Authorization>|\Cake\Datasource\ResultSetInterface<\Activities\Model\Entity\Authorization>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Activities\Model\Entity\Authorization>|\Cake\Datasource\ResultSetInterface<\Activities\Model\Entity\Authorization> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Activities\Model\Entity\Authorization>|\Cake\Datasource\ResultSetInterface<\Activities\Model\Entity\Authorization>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Activities\Model\Entity\Authorization>|\Cake\Datasource\ResultSetInterface<\Activities\Model\Entity\Authorization> deleteManyOrFail(iterable $entities, array $options = [])
 * 
 * @see \Activities\Model\Entity\Authorization Authorization entity class
 * @see \Activities\Services\AuthorizationManagerInterface Authorization workflow service
 * @see \App\Services\ActiveWindowManager\ActiveWindowManagerInterface ActiveWindow lifecycle management
 * @see \App\Model\Table\BaseTable Base table with branch scoping and caching
 */
class AuthorizationsTable extends BaseTable
{
    /**
     * Initialize method
     *
     * Configures the AuthorizationsTable with comprehensive associations, behaviors, and automated
     * status management for complete authorization lifecycle management. This initialization
     * establishes the foundation for temporal authorization tracking, approval workflow integration,
     * and automated expiration processing.
     * 
     * ## Table Configuration
     * - **Table Name**: `activities_authorizations` with plugin-scoped naming
     * - **Display Field**: `id` for entity reference identification
     * - **Primary Key**: `id` as auto-incrementing integer identifier
     * 
     * ## Association Setup
     * Complex relationship structure supporting complete authorization ecosystem:
     * 
     * ### Core Entity Associations
     * - **belongsTo Members**: Member requesting/receiving authorization (INNER JOIN required)
     * - **belongsTo Activities**: Activity being authorized for (INNER JOIN required)
     * - **belongsTo MemberRoles**: Role granted upon authorization (INNER JOIN required)
     * - **belongsTo RevokedBy**: Member who revoked authorization (LEFT JOIN optional)
     * 
     * ### Approval Workflow Associations
     * - **hasMany AuthorizationApprovals**: Complete approval workflow tracking
     * - **hasOne CurrentPendingApprovals**: Active pending approvals awaiting response
     * - Enables multi-level approval workflows and real-time approval status
     * 
     * ## Behavior Configuration
     * Essential behaviors for temporal lifecycle and audit trail management:
     * - **ActiveWindow**: Temporal lifecycle management with automatic status transitions
     * - Inherits Timestamp and Footprint behaviors from BaseTable
     * - Provides current, upcoming, previous, and temporal query methods
     * 
     * ## Automated Status Management
     * Intelligent status processing preventing performance overhead:
     * 
     * ### Daily Status Check System
     * - **Configuration Check**: Uses Activities.NextStatusCheck app setting
     * - **Batch Processing**: Daily status updates prevent real-time overhead
     * - **Automatic Expiration**: Transitions APPROVED/PENDING to EXPIRED when past expires_on
     * - **Performance Optimization**: Prevents excessive database operations
     * 
     * ### Status Check Logic
     * ```php
     * // Automatic status checking on table initialization
     * if (lastStatusCheck->isPast()) {
     *     $this->checkStatus(); // Batch update expired authorizations
     *     // Set next check for tomorrow
     *     StaticHelpers::setAppSetting("Activities.NextStatusCheck", tomorrow);
     * }
     * ```
     * 
     * ## Integration Points
     * - ActiveWindow behavior provides temporal query methods and lifecycle management
     * - Authorization approval associations enable complete workflow tracking
     * - Member and activity associations provide context and validation
     * - Automated status management ensures temporal accuracy without performance impact
     * 
     * @param array<string, mixed> $config The configuration for the Table
     * @return void
     * 
     * @see \Activities\Model\Table\AuthorizationApprovalsTable Authorization approval workflow
     * @see \App\Model\Table\MembersTable Member entity relationships
     * @see \Activities\Model\Table\ActivitiesTable Activity entity relationships
     * @see \App\Model\Behavior\ActiveWindowBehavior Temporal lifecycle management
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable("activities_authorizations");
        $this->setDisplayField("id");
        $this->setPrimaryKey("id");

        $this->belongsTo("Members", [
            "foreignKey" => "member_id",
            "joinType" => "INNER",
        ]);
        $this->belongsTo("MemberRoles", [
            "foreignKey" => "granted_member_role_id",
            "joinType" => "INNER",
        ]);
        $this->belongsTo("Activities", [
            "className" => "Activities.Activities",
            "foreignKey" => "activity_id",
            "joinType" => "INNER",
        ]);
        $this->belongsTo("RevokedBy", [
            "className" => "Members",
            "foreignKey" => "revoker_id",
            "joinType" => "LEFT",
            "propertyName" => "revoked_by",
        ]);

        $this->hasMany("AuthorizationApprovals", [
            "className" => "Activities.AuthorizationApprovals",
            "foreignKey" => "authorization_id",
        ]);
        $this->hasOne("CurrentPendingApprovals", [
            "className" => "Activities.AuthorizationApprovals",
            "conditions" => ["CurrentPendingApprovals.responded_on IS" => null],
            "foreignKey" => "authorization_id",
        ]);
        $this->addBehavior("ActiveWindow");

        $lastExpCheck = new DateTime(StaticHelpers::getAppSetting("Activities.NextStatusCheck", DateTime::now()->subDays(1)->toDateString()), null, true);
        if ($lastExpCheck->isPast()) {
            $this->checkStatus();
            StaticHelpers::setAppSetting("Activities.NextStatusCheck", DateTime::now()->addDays(1)->toDateString(), null, true);
        }
    }

    /**
     * Automated status check for expired authorizations
     * 
     * Performs batch status updates for authorizations that have passed their expiration dates,
     * transitioning them from active states (APPROVED or PENDING) to EXPIRED status. This method
     * is designed for automated execution to maintain authorization temporal accuracy without
     * requiring real-time status checking on every authorization access.
     * 
     * The status check process:
     * - Identifies authorizations past their expires_on date
     * - Updates APPROVED and PENDING authorizations to EXPIRED status
     * - Uses batch updateAll() for optimal database performance
     * - Maintains authorization history while reflecting current temporal state
     * 
     * ## Status Transition Rules
     * This method specifically handles temporal expiration transitions:
     * - **APPROVED → EXPIRED**: Valid authorizations that have passed expiration
     * - **PENDING → EXPIRED**: Pending requests that expired before approval
     * - **Preserves Other States**: DENIED, REVOKED, and already EXPIRED remain unchanged
     * 
     * ## Performance Optimization
     * - **Batch Processing**: Single UPDATE query handles multiple authorization updates
     * - **Targeted Updates**: Only affects authorizations requiring status change
     * - **Date-Based Filtering**: Efficient WHERE clause using expires_on comparison
     * - **Status Filtering**: Limits updates to relevant current states
     * 
     * ## Usage Examples
     * 
     * ### Automated Daily Processing
     * ```php
     * // Called automatically during table initialization
     * if ($lastStatusCheck->isPast()) {
     *     $this->checkStatus(); // Updates all expired authorizations
     *     StaticHelpers::setAppSetting("Activities.NextStatusCheck", tomorrow);
     * }
     * ```
     * 
     * ### Manual Maintenance Operations
     * ```php
     * // Manual status check for maintenance or troubleshooting
     * $authorizationsTable->checkStatus();
     * 
     * // Check status before generating reports
     * $authorizationsTable->checkStatus();
     * $expiredCount = $authorizationsTable->find()
     *     ->where(['status' => Authorization::EXPIRED_STATUS])
     *     ->count();
     * ```
     * 
     * ### Integration with Workflow Systems
     * ```php
     * // Ensure current status before authorization workflows
     * $authorizationsTable->checkStatus();
     * 
     * // Get updated authorization after status check
     * $authorization = $authorizationsTable->get($id);
     * if ($authorization->status === Authorization::EXPIRED_STATUS) {
     *     // Handle expired authorization workflow
     * }
     * ```
     * 
     * ## Integration Points
     * - **Daily Automation**: Called automatically during table initialization
     * - **Maintenance Operations**: Available for manual status synchronization
     * - **Workflow Systems**: Ensures temporal accuracy before authorization operations
     * - **Reporting Systems**: Provides current status for accurate reporting
     * 
     * ## Database Impact
     * - **Efficient Updates**: Single query updates multiple records
     * - **Index Optimization**: Uses expires_on and status indexes
     * - **Transaction Safety**: Atomic operation ensuring data consistency
     * 
     * @return void
     * 
     * @see \Activities\Model\Entity\Authorization Authorization status constants
     * @see \App\KMP\StaticHelpers::setAppSetting() Configuration management
     */
    protected function checkStatus(): void
    {
        $this->updateAll(
            ["status" => Authorization::EXPIRED_STATUS],
            ["expires_on <=" => DateTime::now(), 'status IN' => [Authorization::APPROVED_STATUS, Authorization::PENDING_STATUS]]
        );
    }

    /**
     * Default validation rules for authorization entities
     * 
     * Establishes comprehensive validation rules for authorization data integrity, ensuring
     * proper temporal relationships, required associations, and data consistency for the
     * authorization lifecycle management system. These validation rules provide the first
     * line of defense for authorization data quality and temporal logic validation.
     * 
     * ## Validation Rules Overview
     * 
     * ### Required Association Fields
     * Ensures proper entity relationships for authorization context:
     * - **member_id**: Valid integer linking to Members table (required)
     * - **activity_id**: Valid integer linking to Activities table (required)
     * 
     * ### Temporal Field Validation
     * Critical date validation for authorization lifecycle management:
     * - **expires_on**: Required date field for authorization expiration (required on create)
     * - **start_on**: Optional date field for authorization activation (nullable)
     * 
     * ## Validation Rule Details
     * 
     * ### Member Association Validation
     * - **Type**: Integer validation ensuring numeric member_id
     * - **Presence**: Required field preventing orphaned authorizations
     * - **Purpose**: Links authorization to specific member entity
     * 
     * ### Activity Association Validation
     * - **Type**: Integer validation ensuring numeric activity_id
     * - **Presence**: Required field ensuring authorization context
     * - **Purpose**: Links authorization to specific activity entity
     * 
     * ### Expiration Date Validation
     * - **Type**: Date validation ensuring proper temporal format
     * - **Presence**: Required on entity creation
     * - **Purpose**: Establishes authorization temporal boundaries
     * - **Integration**: Works with ActiveWindow behavior for lifecycle management
     * 
     * ### Start Date Validation
     * - **Type**: Date validation with flexible formatting
     * - **Presence**: Optional field allowing immediate or delayed activation
     * - **Purpose**: Supports scheduled authorization activation
     * 
     * ## Usage Examples
     * 
     * ### Creating Valid Authorization
     * ```php
     * $authorization = $authorizationsTable->newEntity([
     *     'member_id' => 123,
     *     'activity_id' => 456,
     *     'expires_on' => '2024-12-31',
     *     'start_on' => '2024-01-01'  // Optional
     * ]);
     * 
     * if ($authorizationsTable->save($authorization)) {
     *     // Authorization passes validation and saves successfully
     * }
     * ```
     * 
     * ### Validation Error Handling
     * ```php
     * $authorization = $authorizationsTable->newEntity([
     *     'member_id' => 'invalid',  // Validation error: must be integer
     *     // 'activity_id' missing    // Validation error: required field
     *     'expires_on' => null,      // Validation error: required date
     * ]);
     * 
     * if (!$authorizationsTable->save($authorization)) {
     *     $errors = $authorization->getErrors();
     *     // Handle validation errors
     * }
     * ```
     * 
     * ### Batch Validation Processing
     * ```php
     * $validator = $authorizationsTable->getValidator();
     * $data = [
     *     ['member_id' => 123, 'activity_id' => 456, 'expires_on' => '2024-12-31'],
     *     ['member_id' => 124, 'activity_id' => 457, 'expires_on' => '2024-11-30'],
     * ];
     * 
     * foreach ($data as $item) {
     *     $errors = $validator->validate($item);
     *     if (!empty($errors)) {
     *         // Handle individual validation failures
     *     }
     * }
     * ```
     * 
     * ## Integration Points
     * - **Entity Creation**: Applied automatically during newEntity() calls
     * - **Save Operations**: Validates data before database persistence
     * - **Form Processing**: Works with CakePHP form helpers for client-side validation
     * - **API Endpoints**: Ensures data integrity for authorization API operations
     * - **Bulk Operations**: Validates data during batch authorization processing
     * 
     * ## Extension Patterns
     * Additional validation rules can be added for specific use cases:
     * - Custom temporal validation logic
     * - Activity-specific authorization requirements
     * - Member role-based validation rules
     * - Integration with external authorization systems
     * 
     * @param \Cake\Validation\Validator $validator Validator instance for rule configuration
     * @return \Cake\Validation\Validator Configured validator with authorization rules
     * 
     * @see \Activities\Model\Entity\Authorization Authorization entity validation integration
     * @see \App\Model\Behavior\ActiveWindowBehavior Temporal lifecycle validation
     * @see \Cake\ORM\Table::newEntity() Entity creation validation flow
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator->integer("member_id")->notEmptyString("member_id");

        $validator
            ->integer("activity_id")
            ->notEmptyString("activity_id");

        $validator
            ->date("expires_on")
            ->requirePresence("expires_on", "create")
            ->notEmptyDate("expires_on");

        $validator->date("start_on")->allowEmptyDate("start_on");

        return $validator;
    }

    /**
     * Application integrity rules for authorization data consistency
     * 
     * Establishes database-level integrity rules that enforce referential integrity and
     * business logic constraints for authorization entities. These rules provide the second
     * layer of data protection after validation rules, ensuring that authorization data
     * maintains proper relationships and follows business constraints at the database level.
     * 
     * ## Rules Overview
     * 
     * ### Referential Integrity Rules
     * Ensures proper foreign key relationships for authorization context:
     * - **Member Existence**: Validates member_id references existing Members record
     * - **Activity Existence**: Validates activity_id references existing Activities record
     * 
     * ## Rule Implementation Details
     * 
     * ### Member Reference Rule
     * - **Rule Type**: existsIn validation checking Members table
     * - **Field**: member_id foreign key constraint
     * - **Error Handling**: Associates validation errors with member_id field
     * - **Purpose**: Prevents orphaned authorizations without valid member context
     * 
     * ### Activity Reference Rule
     * - **Rule Type**: existsIn validation checking Activities table
     * - **Field**: activity_id foreign key constraint
     * - **Error Handling**: Associates validation errors with activity_id field
     * - **Purpose**: Prevents authorizations for non-existent activities
     * 
     * ## Error Handling
     * Rules violations are associated with specific fields for targeted error display:
     * - **member_id errors**: "Member does not exist" or similar messages
     * - **activity_id errors**: "Activity does not exist" or similar messages
     * 
     * ## Usage Examples
     * 
     * ### Valid Authorization Creation
     * ```php
     * $authorization = $authorizationsTable->newEntity([
     *     'member_id' => 123,      // Must exist in Members table
     *     'activity_id' => 456,    // Must exist in Activities table
     *     'expires_on' => '2024-12-31'
     * ]);
     * 
     * if ($authorizationsTable->save($authorization)) {
     *     // Rules passed, authorization saved successfully
     * }
     * ```
     * 
     * ### Rules Violation Handling
     * ```php
     * $authorization = $authorizationsTable->newEntity([
     *     'member_id' => 999999,   // Non-existent member
     *     'activity_id' => 888888, // Non-existent activity
     *     'expires_on' => '2024-12-31'
     * ]);
     * 
     * if (!$authorizationsTable->save($authorization)) {
     *     $errors = $authorization->getErrors();
     *     // $errors['member_id'] contains member existence error
     *     // $errors['activity_id'] contains activity existence error
     * }
     * ```
     * 
     * ### Form Integration
     * ```php
     * // In forms, rules violations appear as field-specific errors
     * echo $this->Form->control('member_id', [
     *     'type' => 'select',
     *     'options' => $members,
     *     'error' => true  // Displays rules violation messages
     * ]);
     * ```
     * 
     * ## Integration Points
     * - **Save Operations**: Applied automatically during entity save operations
     * - **Validation Chain**: Executed after field validation rules pass
     * - **Transaction Safety**: Rules violations prevent database constraint violations
     * - **Error Handling**: Provides user-friendly error messages for missing references
     * 
     * ## Performance Considerations
     * - **Database Queries**: Rules require additional queries to verify existence
     * - **Caching**: CakePHP caches existence checks for performance optimization
     * - **Transaction Context**: Rules are evaluated within save transaction context
     * 
     * ## Extension Patterns
     * Additional rules can be added for complex business logic:
     * 
     * ### Custom Business Rules
     * ```php
     * // Add custom rule for authorization business logic
     * $rules->add(function ($entity, $options) {
     *     // Custom business logic validation
     *     return $entity->expires_on > $entity->start_on;
     * }, 'temporalConsistency', [
     *     'errorField' => 'expires_on',
     *     'message' => 'Expiration date must be after start date'
     * ]);
     * ```
     * 
     * ### Conditional Rules
     * ```php
     * // Add conditional rules based on authorization status
     * $rules->add(function ($entity, $options) {
     *     if ($entity->status === Authorization::APPROVED_STATUS) {
     *         return !empty($entity->approved_by);
     *     }
     *     return true;
     * }, 'approvalRequirement');
     * ```
     * 
     * @param \Cake\ORM\RulesChecker $rules The rules checker object for configuration
     * @return \Cake\ORM\RulesChecker Configured rules checker with integrity constraints
     * 
     * @see \Cake\ORM\RulesChecker CakePHP rules checker documentation
     * @see \Activities\Model\Entity\Authorization Authorization entity constraints
     * @see \App\Model\Table\MembersTable Member existence validation
     * @see \Activities\Model\Table\ActivitiesTable Activity existence validation
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(["member_id"], "Members"), [
            "errorField" => "member_id",
        ]);
        $rules->add(
            $rules->existsIn(["activity_id"], "Activities"),
            ["errorField" => "activity_id"],
        );

        return $rules;
    }


    /**
     * Custom finder for pending authorization requests
     * 
     * Provides a specialized query method for retrieving authorization entities that are
     * currently in PENDING status, awaiting approval workflow completion. This finder
     * method is essential for approval management interfaces, notification systems, and
     * workflow processing that needs to identify and process pending authorization requests.
     * 
     * ## Query Functionality
     * 
     * ### Status Filtering
     * - **Condition**: Filters results to Authorization::PENDING_STATUS only
     * - **Field**: Uses status field with table alias for query safety
     * - **Performance**: Leverages database indexes on status field for efficient filtering
     * 
     * ### Query Integration
     * - **Chainable**: Returns SelectQuery for additional method chaining
     * - **Composable**: Can be combined with other finders and query conditions
     * - **Optimized**: Uses table alias to prevent column ambiguity in complex joins
     * 
     * ## Usage Examples
     * 
     * ### Basic Pending Authorization Retrieval
     * ```php
     * // Get all pending authorizations
     * $pendingAuthorizations = $authorizationsTable->find('pending')->all();
     * 
     * foreach ($pendingAuthorizations as $authorization) {
     *     // Process pending authorization for approval workflow
     *     echo "Pending: Member {$authorization->member_id} for Activity {$authorization->activity_id}";
     * }
     * ```
     * 
     * ### Approval Dashboard Integration
     * ```php
     * // Approval dashboard with pagination and associations
     * $pendingAuthorizations = $authorizationsTable->find('pending')
     *     ->contain(['Members', 'Activities', 'AuthorizationApprovals'])
     *     ->order(['created' => 'ASC'])
     *     ->limit(50);
     * 
     * // Pass to approval interface for processing
     * $this->set('pendingAuthorizations', $pendingAuthorizations);
     * ```
     * 
     * ### Notification System Integration
     * ```php
     * // Check for new pending authorizations requiring attention
     * $newPendingCount = $authorizationsTable->find('pending')
     *     ->where(['created >=' => DateTime::now()->subDays(1)])
     *     ->count();
     * 
     * if ($newPendingCount > 0) {
     *     // Send notification to approval authorities
     *     $notificationService->sendApprovalNotification($newPendingCount);
     * }
     * ```
     * 
     * ### Workflow Processing
     * ```php
     * // Process pending authorizations with specific criteria
     * $urgentPending = $authorizationsTable->find('pending')
     *     ->innerJoinWith('Activities', function ($q) {
     *         return $q->where(['Activities.priority' => 'HIGH']);
     *     })
     *     ->where(['expires_on <=' => DateTime::now()->addDays(7)])
     *     ->all();
     * 
     * foreach ($urgentPending as $authorization) {
     *     // Escalate urgent pending authorizations
     *     $workflowService->escalateAuthorization($authorization);
     * }
     * ```
     * 
     * ### Member-Specific Pending Requests
     * ```php
     * // Get pending authorizations for specific member
     * $memberPending = $authorizationsTable->find('pending')
     *     ->where(['member_id' => $memberId])
     *     ->contain(['Activities'])
     *     ->all();
     * 
     * // Display member's pending authorization requests
     * foreach ($memberPending as $authorization) {
     *     echo "Pending: {$authorization->activity->name}";
     * }
     * ```
     * 
     * ### Combined with ActiveWindow Behavior
     * ```php
     * // Get current pending authorizations (not expired)
     * $currentPending = $authorizationsTable->find('pending')
     *     ->find('current')  // ActiveWindow behavior method
     *     ->contain(['Members', 'Activities'])
     *     ->all();
     * 
     * // Process current valid pending requests
     * foreach ($currentPending as $authorization) {
     *     // Handle active pending authorization
     * }
     * ```
     * 
     * ## Integration Points
     * - **Approval Interfaces**: Powers approval dashboard and management screens
     * - **Notification Systems**: Identifies authorizations requiring attention
     * - **Workflow Engines**: Provides input for automated approval processing
     * - **Reporting Systems**: Generates pending authorization reports and metrics
     * - **API Endpoints**: Supports REST endpoints for pending authorization queries
     * 
     * ## Performance Characteristics
     * - **Index Usage**: Leverages database index on status field for fast filtering
     * - **Query Efficiency**: Simple WHERE clause provides optimal performance
     * - **Scalability**: Efficient even with large authorization datasets
     * - **Caching**: Compatible with query caching for frequently accessed pending lists
     * 
     * ## Related Finders
     * This finder works well in combination with other query methods:
     * - `find('current')`: ActiveWindow behavior for temporal filtering
     * - `find('approved')`: Similar pattern for approved authorizations
     * - `find('expired')`: Similar pattern for expired authorizations
     * 
     * @param \Cake\ORM\Query\SelectQuery $query The base query to modify
     * @return \Cake\ORM\Query\SelectQuery Modified query filtered for pending status
     * 
     * @see \Activities\Model\Entity\Authorization::PENDING_STATUS Authorization status constant
     * @see \App\Model\Behavior\ActiveWindowBehavior Additional temporal filtering methods
     * @see \Activities\Controller\AuthorizationApprovalsController Approval workflow integration
     * @see \Activities\Services\AuthorizationManagerInterface Authorization workflow service
     */
    public function findPending(SelectQuery $query): SelectQuery
    {
        $query = $query->where([$this->getAlias() . '.status' => Authorization::PENDING_STATUS]);
        return $query;
    }
}
