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
 * ActivitiesTable - Activity Data Management and Authorization Framework
 * 
 * Manages the complete lifecycle of activities within the KMP Activities Plugin, providing
 * comprehensive data access, validation, and business logic for activity management.
 * Activities represent specific actions, roles, or participations that require authorization,
 * training, or approval before members can participate.
 * 
 * This table extends BaseTable to inherit branch scoping, caching strategies, and audit
 * trail functionality. It serves as the central data access layer for activities,
 * coordinating with the authorization system, permission framework, and approval workflows.
 * 
 * ## Database Schema Integration
 * - **Table Name**: `activities_activities` (plugin-scoped naming convention)
 * - **Primary Key**: `id` (auto-incrementing integer)
 * - **Display Field**: `name` (human-readable activity identifier)
 * - **Soft Deletion**: Muffin/Trash behavior for logical deletion
 * - **Audit Trail**: Timestamp and Footprint behaviors for change tracking
 * 
 * ## Association Architecture
 * Complex relationship structure supporting authorization workflows:
 * 
 * ### Core Relationships
 * - **belongsTo ActivityGroups**: Categorical organization of activities
 * - **belongsTo Roles**: Optional role granted upon activity completion
 * - **belongsTo Permissions**: Required permission for activity authorization
 * 
 * ### Authorization Relationships
 * Multiple authorization associations providing temporal access:
 * - **hasMany Authorizations**: All authorization records for activity
 * - **hasMany CurrentAuthorizations**: Currently active authorizations
 * - **hasMany PendingAuthorizations**: Awaiting approval authorizations
 * - **hasMany UpcomingAuthorizations**: Future-dated authorizations
 * - **hasMany PreviousAuthorizations**: Expired or revoked authorizations
 * 
 * ## Validation Framework
 * Comprehensive validation ensuring activity data integrity:
 * 
 * ### Required Field Validation
 * - **name**: Unique string identifier (max 255 chars, required)
 * - **term_length**: Authorization duration in months (integer, required)
 * - **activity_group_id**: Category assignment (integer, required)
 * - **num_required_authorizors**: Approval workflow requirements (integer, required)
 * 
 * ### Optional Field Validation
 * - **minimum_age**: Age restriction lower bound (integer, optional)
 * - **maximum_age**: Age restriction upper bound (integer, optional)
 * - **deleted**: Soft deletion timestamp (date, optional)
 * 
 * ## Business Rules Enforcement
 * Data integrity rules ensuring system consistency:
 * - **Name Uniqueness**: Prevents duplicate activity names across system
 * - **ActivityGroup Existence**: Validates activity group references
 * - **Referential Integrity**: Ensures valid foreign key relationships
 * 
 * ## Authorization Integration Methods
 * Static utility methods for authorization checking and workflow management:
 * 
 * ### Permission-Based Authorization
 * - `canAuthorizeActivity()`: Checks if user can authorize specific activity
 * - `canAuhtorizeAnyActivity()`: Checks if user has any authorization permissions
 * - Integration with Member permission system for workflow validation
 * 
 * ## Usage Examples
 * 
 * ### Activity Creation and Management
 * ```php
 * // Create new combat activity with approval requirements
 * $activity = $activitiesTable->newEntity([
 *     'name' => 'Heavy Weapons Combat Authorization',
 *     'activity_group_id' => $combatGroup->id,
 *     'term_length' => 24, // 2 years
 *     'minimum_age' => 18,
 *     'permission_id' => $combatPermission->id,
 *     'num_required_authorizors' => 2, // Requires 2 approvers
 *     'grants_role_id' => $combatantRole->id
 * ]);
 * $activitiesTable->save($activity);
 * ```
 * 
 * ### Authorization Workflow Queries
 * ```php
 * // Get activity with all authorization states
 * $activity = $activitiesTable->get($id, [
 *     'contain' => [
 *         'CurrentAuthorizations.Member',
 *         'PendingAuthorizations.Member',
 *         'UpcomingAuthorizations.Member'
 *     ]
 * ]);
 * 
 * // Find activities requiring approval
 * $activitiesNeedingApproval = $activitiesTable->find()
 *     ->matching('PendingAuthorizations')
 *     ->contain(['ActivityGroups', 'PendingAuthorizations.Member'])
 *     ->distinct(['Activities.id'])
 *     ->all();
 * ```
 * 
 * ### Permission-Based Activity Discovery
 * ```php
 * // Find activities user can authorize
 * $userPermissions = $currentUser->getPermissionIDs();
 * $authorizableActivities = $activitiesTable->find()
 *     ->where(['permission_id IN' => $userPermissions])
 *     ->contain(['ActivityGroups'])
 *     ->orderBy(['ActivityGroups.name', 'Activities.name'])
 *     ->all();
 * ```
 * 
 * ### Administrative Activity Management
 * ```php
 * // Get activity statistics for reporting
 * $activityStats = $activitiesTable->find()
 *     ->select([
 *         'Activities.id',
 *         'Activities.name',
 *         'current_count' => $activitiesTable->CurrentAuthorizations->find()->func()->count('*'),
 *         'pending_count' => $activitiesTable->PendingAuthorizations->find()->func()->count('*'),
 *         'total_count' => $activitiesTable->Authorizations->find()->func()->count('*')
 *     ])
 *     ->leftJoinWith('CurrentAuthorizations')
 *     ->leftJoinWith('PendingAuthorizations') 
 *     ->leftJoinWith('Authorizations')
 *     ->groupBy(['Activities.id'])
 *     ->all();
 * ```
 * 
 * ### Age-Based Activity Filtering
 * ```php
 * // Find age-appropriate activities for member
 * $memberAge = $member->getAge();
 * $ageAppropriateActivities = $activitiesTable->find()
 *     ->where([
 *         'OR' => [
 *             ['minimum_age IS' => null, 'maximum_age IS' => null], // No age restrictions
 *             ['minimum_age <=' => $memberAge, 'maximum_age >=' => $memberAge], // Within range
 *             ['minimum_age <=' => $memberAge, 'maximum_age IS' => null], // Only minimum
 *             ['minimum_age IS' => null, 'maximum_age >=' => $memberAge] // Only maximum
 *         ]
 *     ])
 *     ->contain(['ActivityGroups'])
 *     ->all();
 * ```
 * 
 * ## Integration Points
 * - **Authorization Manager**: Service layer for authorization workflow management
 * - **Permission System**: RBAC integration for activity authorization requirements
 * - **Role Management**: Automatic role granting upon activity completion
 * - **Member Management**: Authorization tracking and member activity participation
 * - **Reporting System**: Activity analytics and participation tracking
 * 
 * ## Performance Optimizations
 * - **Finder Methods**: Custom finders for authorization state queries
 * - **Association Caching**: Optimized containment for authorization relationships
 * - **Index Optimization**: Database indexes on permission_id and activity_group_id
 * - **Query Optimization**: Efficient permission-based activity discovery
 * 
 * ## Security Considerations
 * - **Permission Validation**: All authorization operations validate user permissions
 * - **Branch Scoping**: Inherits branch-based access control from BaseTable
 * - **Audit Trail**: Complete change tracking through Footprint behavior
 * - **Soft Deletion**: Logical deletion preserving authorization history
 * 
 * @property \Activities\Model\Table\ActivityGroupsTable&\Cake\ORM\Association\BelongsTo $ActivityGroups
 * @property \App\Model\Table\PermissionsTable&\Cake\ORM\Association\BelongsTo $Permissions
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsTo $Roles
 * @property \Activities\Model\Table\AuthorizationsTable&\Cake\ORM\Association\HasMany $Authorizations
 * @property \Activities\Model\Table\AuthorizationsTable&\Cake\ORM\Association\HasMany $CurrentAuthorizations
 * @property \Activities\Model\Table\AuthorizationsTable&\Cake\ORM\Association\HasMany $PendingAuthorizations
 * @property \Activities\Model\Table\AuthorizationsTable&\Cake\ORM\Association\HasMany $UpcomingAuthorizations
 * @property \Activities\Model\Table\AuthorizationsTable&\Cake\ORM\Association\HasMany $PreviousAuthorizations
 * 
 * @method \Activities\Model\Entity\Activity newEmptyEntity()
 * @method \Activities\Model\Entity\Activity newEntity(array $data, array $options = [])
 * @method array<\Activities\Model\Entity\Activity> newEntities(array $data, array $options = [])
 * @method \Activities\Model\Entity\Activity get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Activities\Model\Entity\Activity findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Activities\Model\Entity\Activity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Activities\Model\Entity\Activity> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Activities\Model\Entity\Activity|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Activities\Model\Entity\Activity saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Activities\Model\Entity\Activity>|\Cake\Datasource\ResultSetInterface<\Activities\Model\Entity\Activity>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Activities\Model\Entity\Activity>|\Cake\Datasource\ResultSetInterface<\Activities\Model\Entity\Activity> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Activities\Model\Entity\Activity>|\Cake\Datasource\ResultSetInterface<\Activities\Model\Entity\Activity>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Activities\Model\Entity\Activity>|\Cake\Datasource\ResultSetInterface<\Activities\Model\Entity\Activity> deleteManyOrFail(iterable $entities, array $options = [])
 * 
 * @see \Activities\Model\Entity\Activity Activity entity class
 * @see \Activities\Services\AuthorizationManagerInterface Authorization workflow service
 * @see \App\Model\Table\BaseTable Base table with branch scoping and caching
 */
class ActivitiesTable extends BaseTable
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable("activities_activities");
        $this->setDisplayField("name");
        $this->setPrimaryKey("id");

        $this->belongsTo("ActivityGroups", [
            "className" => "Activities.ActivityGroups",
            "foreignKey" => "activity_group_id",
            "joinType" => "INNER",
        ]);
        $this->belongsTo("Roles", [
            "foreignKey" => "grants_role_id",
            "joinType" => "LEFT",
        ]);
        $this->hasMany("Authorizations", [
            "className" => "Activities.Authorizations",
            "foreignKey" => "activity_id",
        ]);
        $this->hasMany("CurrentAuthorizations", [
            "className" => "Activities.Authorizations",
            "foreignKey" => "activity_id",
            "finder" => "current",
        ]);
        $this->hasMany("PendingAuthorizations", [
            "className" => "Activities.Authorizations",
            "foreignKey" => "activity_id",
            "finder" => "pending",
        ]);
        $this->hasMany("UpcomingAuthorizations", [
            "className" => "Activities.Authorizations",
            "foreignKey" => "activity_id",
            "finder" => "upcoming",
        ]);
        $this->hasMany("PreviousAuthorizations", [
            "className" => "Activities.Authorizations",
            "foreignKey" => "activity_id",
            "finder" => "previous",
        ]);
        $this->belongsTo("Permissions", [
            "foreignKey" => "permission_id",
        ]);
        $this->addBehavior("Timestamp");
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior("Muffin/Trash.Trash");
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar("name")
            ->maxLength("name", 255)
            ->requirePresence("name", "create")
            ->notEmptyString("name")
            ->add("name", "unique", [
                "rule" => "validateUnique",
                "provider" => "table",
            ]);

        $validator
            ->integer("term_length")
            ->requirePresence("term_length", "create")
            ->notEmptyString("term_length");

        $validator
            ->integer("activity_group_id")
            ->notEmptyString("activity_group_id");

        $validator->integer("minimum_age")->allowEmptyString("minimum_age");

        $validator->integer("maximum_age")->allowEmptyString("maximum_age");

        $validator
            ->integer("num_required_authorizors")
            ->notEmptyString("num_required_authorizors");

        $validator->date("deleted")->allowEmptyDate("deleted");

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(["name"]), ["errorField" => "name"]);
        $rules->add(
            $rules->existsIn(
                ["activity_group_id"],
                "ActivityGroups",
            ),
            ["errorField" => "activity_group_id"],
        );

        return $rules;
    }

    /**
     * Shortcut query to see if the user can authorize a specific activity
     * 
     * Performs efficient permission-based authorization checking to determine if a user
     * has the necessary permissions to authorize a specific activity. This method provides
     * quick validation for authorization workflow entry points and access control.
     * 
     * The method validates authorization capability by:
     * - Retrieving user's current permission IDs through Member identity system
     * - Querying activity table for activity with matching ID and user's permissions
     * - Returning boolean result for immediate authorization workflow decisions
     * 
     * ## Usage Examples
     * 
     * ### Controller Authorization Checks
     * ```php
     * // Validate user can authorize before showing approval interface
     * if (!ActivitiesTable::canAuthorizeActivity($this->getIdentity(), $activityId)) {
     *     throw new ForbiddenException('Insufficient permissions to authorize this activity');
     * }
     * ```
     * 
     * ### Workflow Entry Validation
     * ```php
     * // Check authorization capability before processing approval request
     * if (ActivitiesTable::canAuthorizeActivity($currentUser, $activity->id)) {
     *     // Proceed with authorization workflow
     *     $authorizationManager->processApproval($authorization, $currentUser);
     * }
     * ```
     * 
     * ### UI Element Conditional Display
     * ```php
     * // Show/hide authorization buttons based on permissions
     * if (ActivitiesTable::canAuthorizeActivity($this->identity, $activity->id)) {
     *     echo $this->Html->link('Authorize', ['action' => 'authorize', $activity->id]);
     * }
     * ```
     * 
     * ## Performance Optimization
     * - Minimal query using select('id') for existence checking only
     * - Direct permission ID comparison avoiding join operations
     * - Efficient boolean return for conditional logic
     * 
     * ## Security Considerations
     * - Validates current user permissions through Member identity system
     * - Prevents unauthorized access to authorization workflows
     * - Integrates with RBAC permission validation architecture
     * 
     * @param \App\Model\Entity\Member $user The user to check authorization permissions for
     * @param int $activityId The activity ID to check authorization permissions against
     * @return bool True if user can authorize the activity, false otherwise
     * 
     * @see \App\Model\Entity\Member::getPermissionIDs() User permission retrieval
     * @see \Activities\Services\AuthorizationManagerInterface Authorization workflow service
     */
    public static function canAuthorizeActivity($user, int $activityId): bool
    {
        $permission = $user->getPermissionIDs();
        $activitiesTable = TableRegistry::getTableLocator()->get("Activities.Activities");
        $activity = $activitiesTable->find()->select("id")->where(["id" => $activityId, "permission_id IN" => $permission])->first();
        return $activity !== null;
    }

    /**
     * Shortcut query to see if the user can authorize anything and there for may have an Auth Queue
     * 
     * Performs efficient permission-based checking to determine if a user has authorization
     * permissions for any activities in the system. This method enables conditional display
     * of authorization queues, navigation badges, and workflow entry points.
     * 
     * The method validates general authorization capability by:
     * - Retrieving user's current permission IDs through Member identity system
     * - Early return if user has no permissions (performance optimization)
     * - Counting activities matching user's permissions for existence validation
     * - Returning boolean result for authorization queue and navigation decisions
     * 
     * ## Usage Examples
     * 
     * ### Navigation Queue Display
     * ```php
     * // Show authorization queue navigation item
     * if (ActivitiesTable::canAuhtorizeAnyActivity($this->getIdentity())) {
     *     $pendingCount = $this->getIdentity()->getPendingApprovalsCount();
     *     echo $this->Html->link(
     *         "Authorization Queue ({$pendingCount})",
     *         ['plugin' => 'Activities', 'controller' => 'AuthorizationApprovals']
     *     );
     * }
     * ```
     * 
     * ### Dashboard Widget Conditional
     * ```php
     * // Display authorization management dashboard widget
     * if (ActivitiesTable::canAuhtorizeAnyActivity($currentUser)) {
     *     echo $this->element('Activities.authorization_queue_widget');
     * }
     * ```
     * 
     * ### Authorization Workflow Routing
     * ```php
     * // Redirect unauthorized users away from authorization interfaces
     * if (!ActivitiesTable::canAuhtorizeAnyActivity($this->getIdentity())) {
     *     $this->Flash->error('You do not have authorization permissions');
     *     return $this->redirect(['controller' => 'Members', 'action' => 'view', $this->getIdentity()->id]);
     * }
     * ```
     * 
     * ### Permission-Based Menu Generation
     * ```php
     * // Build context-sensitive navigation menus
     * $menuItems = [];
     * if (ActivitiesTable::canAuhtorizeAnyActivity($user)) {
     *     $menuItems[] = [
     *         'title' => 'Authorization Management',
     *         'url' => ['plugin' => 'Activities', 'controller' => 'AuthorizationApprovals']
     *     ];
     * }
     * ```
     * 
     * ## Performance Optimization
     * - Early return on empty permissions array avoiding database query
     * - Count query using minimal field selection for existence checking
     * - Efficient permission ID array comparison in WHERE clause
     * - Optimized for navigation and conditional display use cases
     * 
     * ## Security Considerations
     * - Validates current user permissions through Member identity system
     * - Prevents display of authorization interfaces to unauthorized users
     * - Integrates with RBAC permission validation architecture
     * - Supports principle of least privilege by hiding unavailable features
     * 
     * @param \App\Model\Entity\Member $user The user to check general authorization permissions for
     * @return bool True if user can authorize any activities, false otherwise
     * 
     * @see \App\Model\Entity\Member::getPermissionIDs() User permission retrieval
     * @see \Activities\Model\Entity\MemberAuthorizationsTrait::getPendingApprovalsCount() Pending approval counts
     * @see \Activities\Services\AuthorizationManagerInterface Authorization workflow service
     */
    public static function canAuhtorizeAnyActivity($user): bool
    {
        $permission = $user->getPermissionIDs();
        if (empty($permission)) {
            return false;
        }
        $activitiesTable = TableRegistry::getTableLocator()->get("Activities.Activities");
        $activityCount = $activitiesTable->find()->select("id")->where(["permission_id IN" => $permission])->count();
        return $activityCount > 0;
    }
}
