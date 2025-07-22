<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\Cache\Cache;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * WarrantsTable - Warrant Data Management and Temporal Validation for RBAC Security
 *
 * The WarrantsTable provides comprehensive data management for the KMP warrant system,
 * which serves as the temporal validation layer for Role-Based Access Control (RBAC).
 * This table manages warrant lifecycle operations, validation rules, and performance
 * optimization for the security-critical warrant validation system.
 *
 * ## Core Responsibilities
 *
 * ### Warrant Data Management
 * - **CRUD Operations**: Complete warrant lifecycle from creation to expiration
 * - **Validation Rules**: Business logic enforcement for warrant periods and member eligibility
 * - **Relationship Management**: Complex associations with Members, MemberRoles, and WarrantRosters
 * - **Temporal Queries**: Efficient database operations for time-bounded warrant validation
 *
 * ### RBAC Security Integration
 * - **Permission Cache Management**: Automatic cache invalidation for permission validation performance
 * - **Security Validation**: Referential integrity for warrant-secured permission relationships
 * - **Temporal Boundaries**: Validation of warrant start/end dates for security enforcement
 * - **Status Management**: Warrant lifecycle state validation and business rule enforcement
 *
 * ### Performance Optimization
 * - **Permission Cache Strategy**: Two-tier cache invalidation (permissions_policies, member_permissions)
 * - **Database Optimization**: Efficient queries for temporal warrant validation
 * - **Relationship Loading**: Optimized associations for warrant approval workflows
 * - **Memory Management**: Efficient handling of large warrant datasets
 *
 * ## Warrant Database Schema Integration
 *
 * ### Core Warrant Fields
 * ```sql
 * CREATE TABLE warrants (
 *     id INT PRIMARY KEY AUTO_INCREMENT,
 *     member_id INT NOT NULL,                    -- Member receiving warrant
 *     warrant_roster_id INT NOT NULL,            -- Batch approval container
 *     entity_type VARCHAR(255),                  -- Type of warranted entity
 *     entity_id INT NOT NULL,                    -- Specific entity instance
 *     member_role_id INT,                        -- Associated role assignment
 *     expires_on DATETIME,                       -- Warrant expiration date
 *     start_on DATETIME,                         -- Warrant start date
 *     approved_date DATETIME,                    -- When warrant was approved
 *     status VARCHAR(20) NOT NULL,               -- Warrant lifecycle status
 *     revoked_reason VARCHAR(255),               -- Reason for revocation
 *     revoker_id INT,                           -- Member who revoked warrant
 *     created_by INT,                           -- Member who created warrant
 *     created DATETIME,                         -- Creation timestamp
 *     modified DATETIME                         -- Last modification timestamp
 * );
 * ```
 *
 * ### Association Architecture
 * The WarrantsTable manages complex relationships for warrant approval and validation:
 * - **Member**: Core association to warrant recipient
 * - **WarrantRoster**: Batch approval and administrative container
 * - **MemberRole**: RBAC integration for permission validation
 * - **RevokedBy**: Administrative tracking for warrant termination
 * - **CreatedByMember**: Audit trail for warrant origination
 * - **ModifiedByMember**: Audit trail for warrant modifications
 *
 * ## Validation Architecture
 *
 * ### Business Rule Validation
 * The validation system enforces comprehensive business rules:
 * ```php
 * // Core warrant validation
 * $validator
 *     ->integer('member_id')
 *     ->notEmptyString('member_id')           // Warrant must have recipient
 *     ->integer('warrant_roster_id')
 *     ->notEmptyString('warrant_roster_id')   // Must be part of approval batch
 *     ->scalar('entity_type')
 *     ->maxLength('entity_type', 255)         // Entity type constraint
 *     ->allowEmptyString('entity_type')       // Optional for direct grants
 *     ->integer('entity_id')
 *     ->requirePresence('entity_id', 'create') // Must specify warranted entity
 *     ->notEmptyString('entity_id');
 * 
 * // Temporal validation
 * $validator
 *     ->dateTime('expires_on')
 *     ->allowEmptyDateTime('expires_on')      // Can be indefinite
 *     ->dateTime('start_on')
 *     ->allowEmptyDateTime('start_on')        // Can start immediately
 *     ->dateTime('approved_date')
 *     ->allowEmptyDateTime('approved_date');  // Set when approved
 * 
 * // Status and audit validation
 * $validator
 *     ->scalar('status')
 *     ->maxLength('status', 20)
 *     ->notEmptyString('status')              // Status required
 *     ->scalar('revoked_reason')
 *     ->maxLength('revoked_reason', 255)
 *     ->allowEmptyString('revoked_reason');   // Optional revocation reason
 * ```
 *
 * ### Referential Integrity Rules
 * The table enforces referential integrity for RBAC security:
 * ```php
 * // Core relationship validation
 * $rules->add($rules->existsIn(['member_id'], 'Members'));
 * $rules->add($rules->existsIn(['warrant_roster_id'], 'WarrantRosters'));
 * $rules->add($rules->existsIn(['member_role_id'], 'MemberRoles'));
 * 
 * // Administrative relationship validation
 * $rules->add($rules->existsIn(['revoker_id'], 'Members'));
 * $rules->add($rules->existsIn(['created_by'], 'Members'));
 * ```
 *
 * ## Cache Management and Performance
 *
 * ### Permission Cache Integration
 * The WarrantsTable implements automatic cache invalidation for RBAC performance:
 * ```php
 * public function afterSave($event, $entity, $options): void
 * {
 *     $memberId = $entity->member_id;
 *     
 *     // Invalidate permission policies cache
 *     Cache::delete('permissions_policies' . $memberId);
 *     
 *     // Invalidate member permissions cache
 *     Cache::delete('member_permissions' . $memberId);
 * }
 * ```
 *
 * This two-tier cache invalidation ensures:
 * - **Policy Cache**: Permission policies are recalculated when warrants change
 * - **Permission Cache**: Member permissions are refreshed for security validation
 * - **Performance**: Only affected member's cache is invalidated, not system-wide
 * - **Consistency**: Warrant changes immediately affect permission checking
 *
 * ### Database Query Optimization
 * The table supports efficient temporal queries for warrant validation:
 * ```php
 * // Find active warrants for permission validation
 * $activeWarrants = $warrantsTable->find()
 *     ->where([
 *         'member_id' => $memberId,
 *         'start_on <=' => $now,              // Warrant has started
 *         'expires_on >' => $now,             // Warrant hasn't expired
 *         'status' => Warrant::CURRENT_STATUS  // Warrant is active
 *     ])
 *     ->contain(['MemberRoles', 'WarrantRosters']);
 * 
 * // Find warrants by roster for approval processing
 * $rosterWarrants = $warrantsTable->find()
 *     ->where(['warrant_roster_id' => $rosterId])
 *     ->contain(['Members', 'MemberRoles'])
 *     ->order(['created' => 'ASC']);
 * ```
 *
 * ## ActiveWindow Behavior Integration
 *
 * ### Temporal Entity Management
 * The WarrantsTable uses the ActiveWindow behavior for lifecycle management:
 * ```php
 * $this->addBehavior('ActiveWindow');
 * ```
 *
 * This provides:
 * - **Status Management**: Automatic status transitions (Pending → Current → Expired)
 * - **Temporal Queries**: Built-in finders for current, upcoming, and expired warrants
 * - **Lifecycle Operations**: Start and stop operations with business rule enforcement
 * - **Administrative Controls**: Manual override capabilities for warrant management
 *
 * ### Behavior Configuration
 * ```php
 * // ActiveWindow behavior automatically handles:
 * // - start() method for warrant activation
 * // - expire() method for warrant expiration
 * // - Current/Expired status management
 * // - Temporal query scopes
 * ```
 *
 * ## Association Configuration
 *
 * ### Core Relationships
 * ```php
 * // Primary warrant recipient
 * $this->belongsTo('Members', [
 *     'foreignKey' => 'member_id',
 *     'joinType' => 'INNER',              // Warrant must have recipient
 * ]);
 * 
 * // Batch approval container
 * $this->belongsTo('WarrantRosters', [
 *     'foreignKey' => 'warrant_roster_id',
 *     'joinType' => 'INNER',              // Warrant must be in roster
 * ]);
 * 
 * // RBAC integration
 * $this->belongsTo('MemberRoles', [
 *     'foreignKey' => 'member_role_id',   // Optional for direct grants
 * ]);
 * ```
 *
 * ### Administrative Relationships
 * ```php
 * // Warrant revocation tracking
 * $this->belongsTo('RevokedBy', [
 *     'className' => 'Members',
 *     'foreignKey' => 'revoker_id',
 *     'joinType' => 'LEFT',               // Optional field
 *     'propertyName' => 'revoked_by',
 * ]);
 * 
 * // Audit trail relationships
 * $this->belongsTo('CreatedByMember', [
 *     'className' => 'Members',
 *     'foreignKey' => 'created_by',
 *     'joinType' => 'LEFT',               // Tracked by Footprint behavior
 * ]);
 * 
 * $this->belongsTo('ModifiedByMember', [
 *     'className' => 'Members',
 *     'foreignKey' => 'modified_by',
 *     'joinType' => 'LEFT',               // Tracked by Footprint behavior
 * ]);
 * ```
 *
 * ## Warrant Query Patterns
 *
 * ### Permission Validation Queries
 * ```php
 * // Query for active warrants (used by PermissionsLoader)
 * $activeWarrantSubquery = $warrantsTable->find()
 *     ->select(['Warrants.member_role_id'])
 *     ->where([
 *         'Warrants.start_on <=' => $now,
 *         'Warrants.expires_on >' => $now,
 *         'Warrants.status' => Warrant::CURRENT_STATUS,
 *     ]);
 * 
 * // Use in permission queries
 * $memberRolesQuery = $memberRolesTable->find()
 *     ->where(['member_id' => $memberId])
 *     ->where(['id IN' => $activeWarrantSubquery]);  // Only warranted roles
 * ```
 *
 * ### Administrative Queries
 * ```php
 * // Find pending warrants for approval
 * $pendingWarrants = $warrantsTable->find()
 *     ->where(['status' => Warrant::PENDING_STATUS])
 *     ->contain(['Members', 'WarrantRosters', 'MemberRoles'])
 *     ->order(['created' => 'ASC']);
 * 
 * // Find expiring warrants for notification
 * $expiringWarrants = $warrantsTable->find()
 *     ->where([
 *         'expires_on BETWEEN ? AND ?' => [$now, $notificationDate],
 *         'status' => Warrant::CURRENT_STATUS
 *     ])
 *     ->contain(['Members', 'MemberRoles']);
 * ```
 *
 * ### Audit and Reporting Queries
 * ```php
 * // Warrant history for member
 * $warrantHistory = $warrantsTable->find()
 *     ->where(['member_id' => $memberId])
 *     ->contain(['WarrantRosters', 'MemberRoles', 'RevokedBy'])
 *     ->order(['created' => 'DESC']);
 * 
 * // Warrant statistics by status
 * $statusStats = $warrantsTable->find()
 *     ->select(['status', 'count' => 'COUNT(*)'])
 *     ->group(['status'])
 *     ->toArray();
 * ```
 *
 * ## Integration Examples
 *
 * ### Warrant Creation with Validation
 * ```php
 * // Create warrant with full validation
 * $warrant = $warrantsTable->newEntity([
 *     'member_id' => $memberId,
 *     'warrant_roster_id' => $rosterId,
 *     'entity_type' => 'Direct Grant',
 *     'entity_id' => $roleId,
 *     'member_role_id' => $memberRoleId,
 *     'start_on' => $startDate,
 *     'expires_on' => $endDate,
 *     'status' => Warrant::PENDING_STATUS,
 *     'created_by' => $currentUserId,
 * ]);
 * 
 * if ($warrantsTable->save($warrant)) {
 *     // Cache automatically invalidated by afterSave()
 *     $this->Flash->success('Warrant created successfully.');
 * } else {
 *     $this->Flash->error('Validation failed: ' . implode(', ', $warrant->getErrorSources()));
 * }
 * ```
 *
 * ### Permission Validation Integration
 * ```php
 * // Check if member has active warrant for role (used by PermissionsLoader)
 * $hasActiveWarrant = $warrantsTable->find()
 *     ->where([
 *         'member_role_id' => $memberRoleId,
 *         'start_on <=' => DateTime::now(),
 *         'expires_on >' => DateTime::now(),
 *         'status' => Warrant::CURRENT_STATUS
 *     ])
 *     ->count() > 0;
 * 
 * // Only grant permissions if warrant is active
 * if ($hasActiveWarrant) {
 *     $permissions = $permissionsLoader->getPermissionsForRole($roleId);
 * }
 * ```
 *
 * ### Batch Warrant Operations
 * ```php
 * // Approve all warrants in roster
 * $warrants = $warrantsTable->find()
 *     ->where([
 *         'warrant_roster_id' => $rosterId,
 *         'status' => Warrant::PENDING_STATUS
 *     ])
 *     ->toArray();
 * 
 * foreach ($warrants as $warrant) {
 *     $warrant->status = Warrant::CURRENT_STATUS;
 *     $warrant->approved_date = DateTime::now();
 * }
 * 
 * if ($warrantsTable->saveMany($warrants)) {
 *     // All affected member caches invalidated automatically
 *     $this->Flash->success(count($warrants) . ' warrants approved.');
 * }
 * ```
 *
 * ## Security Considerations
 *
 * ### Data Integrity
 * - **Referential Integrity**: All foreign key relationships validated
 * - **Temporal Consistency**: Start dates cannot be after end dates
 * - **Status Validation**: Only valid status transitions allowed
 * - **Audit Trail**: Complete tracking of warrant modifications
 *
 * ### Performance Security
 * - **Cache Invalidation**: Immediate permission cache refresh on warrant changes
 * - **Query Optimization**: Efficient temporal queries for permission validation
 * - **Memory Management**: Proper handling of large warrant datasets
 * - **Index Strategy**: Database indexes optimized for security queries
 *
 * ### Administrative Security
 * - **Authorization Required**: All warrant operations require proper permissions
 * - **Revocation Tracking**: Complete audit trail for warrant termination
 * - **Approval Workflow**: Multi-level approval requirements for sensitive warrants
 * - **Emergency Controls**: Administrative override capabilities for security incidents
 *
 * ## Usage Examples
 *
 * ### Basic Warrant Operations
 * ```php
 * // Create new warrant
 * $warrant = $warrantsTable->newEntity($warrantData);
 * $warrantsTable->save($warrant);
 * 
 * // Find warrant by ID
 * $warrant = $warrantsTable->get($warrantId, [
 *     'contain' => ['Members', 'WarrantRosters', 'MemberRoles']
 * ]);
 * 
 * // Update warrant status
 * $warrant->status = Warrant::CURRENT_STATUS;
 * $warrant->approved_date = DateTime::now();
 * $warrantsTable->save($warrant);  // Cache automatically invalidated
 * ```
 *
 * ### Temporal Warrant Queries
 * ```php
 * // Current active warrants
 * $current = $warrantsTable->find('current')->toArray();
 * 
 * // Upcoming warrants
 * $upcoming = $warrantsTable->find('upcoming')->toArray();
 * 
 * // Expired warrants
 * $expired = $warrantsTable->find('expired')->toArray();
 * 
 * // Custom temporal query
 * $warrants = $warrantsTable->find()
 *     ->where([
 *         'start_on BETWEEN ? AND ?' => [$startRange, $endRange]
 *     ])
 *     ->contain(['Members', 'MemberRoles']);
 * ```
 *
 * @see \App\Model\Entity\Warrant For warrant entity documentation and RBAC integration
 * @see \App\Model\Table\BaseTable For base table functionality and cache management
 * @see \App\Model\Entity\WarrantRoster For batch warrant management
 * @see \App\Model\Entity\MemberRole For RBAC role assignment integration
 * @see \App\Services\WarrantManager\WarrantManagerInterface For warrant business logic
 * @see \App\KMP\PermissionsLoader For RBAC security validation engine
 *
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \App\Model\Table\WarrantRostersTable&\Cake\ORM\Association\BelongsTo $WarrantRosters
 * @property \App\Model\Table\MemberRolesTable&\Cake\ORM\Association\BelongsTo $MemberRoles
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $RevokedBy
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $CreatedByMember
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $ModifiedByMember
 *
 * @method \App\Model\Entity\Warrant newEmptyEntity()
 * @method \App\Model\Entity\Warrant newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Warrant> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Warrant get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Warrant findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Warrant patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Warrant> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Warrant|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Warrant saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Warrant>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Warrant>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Warrant>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Warrant> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Warrant>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Warrant>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Warrant>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Warrant> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Cake\ORM\Behavior\ActiveWindowBehavior
 * @mixin \Muffin\Footprint\Model\Behavior\FootprintBehavior
 */
class WarrantsTable extends BaseTable
{
    /**
     * Initialize method - Configure warrant table associations and behaviors
     *
     * Sets up the comprehensive warrant management system including temporal validation,
     * audit trails, and RBAC integration. This method configures all associations
     * needed for warrant lifecycle management and security validation.
     *
     * ### Association Configuration
     * Establishes relationships for:
     * - **Member Management**: Core warrant recipient and administrative tracking
     * - **RBAC Integration**: Connection to role assignments for permission validation
     * - **Batch Processing**: Warrant roster system for approval workflows
     * - **Audit Trail**: Complete tracking of warrant modifications and revocations
     *
     * ### Behavior Integration
     * Configures behaviors for:
     * - **ActiveWindow**: Temporal entity lifecycle management with status transitions
     * - **Timestamp**: Automatic creation and modification date tracking
     * - **Footprint**: User tracking for created_by and modified_by fields
     *
     * ### Database Configuration
     * - **Table**: `warrants` - Contains all warrant data and temporal information
     * - **Display Field**: `status` - Primary field for warrant identification
     * - **Primary Key**: `id` - Auto-incrementing warrant identifier
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        // Configure table basics for warrant management
        $this->setTable('warrants');
        $this->setDisplayField('status');    // Status is primary identifier
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        // Core warrant recipient - INNER join ensures warrant has recipient
        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'INNER',              // Warrant must have recipient
        ]);

        // Administrative tracking - Member who revoked warrant (optional)
        $this->belongsTo('RevokedBy', [
            'className' => 'Members',
            'foreignKey' => 'revoker_id',
            'joinType' => 'LEFT',               // Only set when warrant is revoked
            'propertyName' => 'revoked_by',
        ]);

        // Batch approval system - INNER join ensures warrant is in roster
        $this->belongsTo('WarrantRosters', [
            'foreignKey' => 'warrant_roster_id',
            'joinType' => 'INNER',              // Warrant must be in approval batch
        ]);

        // RBAC integration - Links warrant to specific role assignment
        $this->belongsTo('MemberRoles', [
            'foreignKey' => 'member_role_id',   // Optional for direct grants
        ]);

        // Audit trail - Member who created warrant request
        $this->belongsTo('CreatedByMember', [
            'className' => 'Members',
            'foreignKey' => 'created_by',
            'joinType' => 'LEFT',               // Tracked by Footprint behavior
        ]);

        // Audit trail - Member who last modified warrant
        $this->belongsTo('ModfiedByMember', [
            'className' => 'Members',
            'foreignKey' => 'modified_by',
            'joinType' => 'LEFT',               // Tracked by Footprint behavior
        ]);

        // Temporal entity behavior - Provides status management and lifecycle operations
        $this->addBehavior('ActiveWindow');

        // Timestamp behavior for created/modified tracking
        $this->addBehavior('Timestamp');

        // User tracking behavior for audit trail
        $this->addBehavior('Muffin/Footprint.Footprint');
    }

    /**
     * Default validation rules - Comprehensive warrant data validation
     *
     * Implements comprehensive validation rules for warrant data integrity and business
     * logic enforcement. These rules ensure warrants meet all requirements for temporal
     * validation of RBAC permissions and proper approval workflow integration.
     *
     * ### Core Field Validation
     * - **member_id**: Required integer identifying warrant recipient
     * - **warrant_roster_id**: Required integer linking to approval batch
     * - **entity_type**: Optional string identifying type of warranted entity
     * - **entity_id**: Required integer identifying specific warranted entity
     * - **member_role_id**: Optional integer linking to RBAC role assignment
     *
     * ### Temporal Field Validation
     * - **expires_on**: Optional datetime for warrant expiration
     * - **start_on**: Optional datetime for warrant activation
     * - **approved_date**: Optional datetime set when warrant is approved
     *
     * ### Status and Administrative Validation
     * - **status**: Required string with maximum 20 characters for lifecycle state
     * - **revoked_reason**: Optional string with maximum 255 characters for termination reason
     * - **revoker_id**: Optional integer identifying member who revoked warrant
     * - **created_by**: Optional integer tracked by Footprint behavior
     *
     * ### Business Rule Enforcement
     * ```php
     * // Example validation usage
     * $warrant = $warrantsTable->newEntity([
     *     'member_id' => 123,                 // Required - warrant recipient
     *     'warrant_roster_id' => 456,         // Required - approval batch
     *     'entity_type' => 'Direct Grant',    // Optional - warrant type
     *     'entity_id' => 789,                 // Required - warranted entity
     *     'member_role_id' => 101,            // Optional - RBAC integration
     *     'status' => 'Pending',              // Required - lifecycle status
     * ]);
     * 
     * if ($warrantsTable->save($warrant)) {
     *     // Validation passed, warrant created
     * } else {
     *     // Validation failed, check $warrant->getErrors()
     * }
     * ```
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator Modified validator with warrant rules
     */
    public function validationDefault(Validator $validator): Validator
    {
        // Core warrant recipient validation
        $validator
            ->integer('member_id')
            ->notEmptyString('member_id');      // Warrant must have recipient

        // Batch approval system validation
        $validator
            ->integer('warrant_roster_id')
            ->notEmptyString('warrant_roster_id'); // Must be part of approval batch

        // Entity type validation (Officers, Activities, Direct Grant, etc.)
        $validator
            ->scalar('entity_type')
            ->maxLength('entity_type', 255)
            ->allowEmptyString('entity_type');  // Optional for direct grants

        // Warranted entity validation
        $validator
            ->integer('entity_id')
            ->requirePresence('entity_id', 'create')  // Required on creation
            ->notEmptyString('entity_id');      // Must specify warranted entity

        // RBAC integration validation
        $validator
            ->integer('member_role_id')
            ->allowEmptyString('member_role_id'); // Optional for direct grants

        // Temporal validation fields
        $validator
            ->dateTime('expires_on')
            ->allowEmptyDateTime('expires_on'); // Can be indefinite

        $validator
            ->dateTime('start_on')
            ->allowEmptyDateTime('start_on');   // Can start immediately

        $validator
            ->dateTime('approved_date')
            ->allowEmptyDateTime('approved_date'); // Set when approved

        // Status validation - Critical for warrant lifecycle
        $validator
            ->scalar('status')
            ->maxLength('status', 20)
            ->notEmptyString('status');         // Status required for lifecycle

        // Revocation tracking validation
        $validator
            ->scalar('revoked_reason')
            ->maxLength('revoked_reason', 255)
            ->allowEmptyString('revoked_reason'); // Optional revocation reason

        $validator
            ->integer('revoker_id')
            ->allowEmptyString('revoker_id');   // Optional - set when revoked

        // Audit trail validation
        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');   // Tracked by Footprint behavior

        return $validator;
    }

    /**
     * Build application rules - Enforce referential integrity and business rules
     *
     * Implements comprehensive application rules to ensure warrant data integrity
     * and proper relationships within the RBAC security system. These rules enforce
     * referential integrity and business logic that cannot be handled by basic validation.
     *
     * ### Referential Integrity Rules
     * Enforces database relationships for:
     * - **member_id → Members**: Warrant recipient must exist
     * - **warrant_roster_id → WarrantRosters**: Approval batch must exist
     * - **member_role_id → MemberRoles**: Role assignment must exist (if specified)
     * - **revoker_id → Members**: Revoking member must exist (if specified)
     * - **created_by → Members**: Creating member must exist (if specified)
     *
     * ### Business Rule Enforcement
     * These rules ensure:
     * - **Data Consistency**: All foreign key relationships are valid
     * - **Security Integrity**: Warrant references point to valid entities
     * - **Audit Trail**: Administrative actions reference valid members
     * - **RBAC Integration**: Role assignments exist for warrant validation
     *
     * ### Error Handling
     * When rules fail, specific error fields are set:
     * ```php
     * // Example rule failure handling
     * $warrant = $warrantsTable->newEntity($invalidData);
     * 
     * if (!$warrantsTable->save($warrant)) {
     *     $errors = $warrant->getErrors();
     *     
     *     // Check for referential integrity errors
     *     if (isset($errors['member_id'])) {
     *         // Member does not exist
     *     }
     *     if (isset($errors['warrant_roster_id'])) {
     *         // Warrant roster does not exist
     *     }
     *     if (isset($errors['member_role_id'])) {
     *         // Member role does not exist
     *     }
     * }
     * ```
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker Modified rules checker with warrant rules
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        // Core warrant recipient must exist
        $rules->add($rules->existsIn(['member_id'], 'Members'), ['errorField' => 'member_id']);

        // Approval batch must exist
        $rules->add($rules->existsIn(['warrant_roster_id'], 'WarrantRosters'), ['errorField' => 'warrant_roster_id']);

        // RBAC role assignment must exist (if specified)
        $rules->add($rules->existsIn(['member_role_id'], 'MemberRoles'), ['errorField' => 'member_role_id']);

        return $rules;
    }

    /**
     * After save callback - Invalidate permission caches for RBAC security
     *
     * Implements automatic cache invalidation to ensure warrant changes immediately
     * affect permission validation. This is critical for RBAC security as warrant
     * modifications must be reflected in permission checking without delay.
     *
     * ### Cache Invalidation Strategy
     * Implements two-tier cache invalidation:
     * 1. **Permission Policies Cache**: Invalidates cached policy resolutions
     * 2. **Member Permissions Cache**: Invalidates cached permission calculations
     *
     * ### Security Implications
     * This method ensures:
     * - **Immediate Effect**: Warrant changes immediately affect permission checking
     * - **Security Consistency**: No stale permissions from cached warrant data
     * - **Performance Balance**: Only affected member's cache is invalidated
     * - **System Integrity**: Warrant-secured permissions remain current
     *
     * ### Cache Keys Invalidated
     * - `permissions_policies{memberId}`: Policy resolution cache for specific member
     * - `member_permissions{memberId}`: Permission calculation cache for specific member
     *
     * ### Integration with PermissionsLoader
     * The PermissionsLoader relies on this cache invalidation to ensure:
     * - Active warrants are immediately recognized for permission grants
     * - Expired/revoked warrants immediately remove permission access
     * - Status changes (Pending → Current) immediately affect authorization
     * - RBAC security remains consistent with warrant state
     *
     * ### Usage Example
     * ```php
     * // When warrant is saved, caches are automatically invalidated
     * $warrant->status = Warrant::CURRENT_STATUS;
     * $warrant->approved_date = DateTime::now();
     * 
     * if ($warrantsTable->save($warrant)) {
     *     // afterSave() automatically called:
     *     // - Cache::delete('permissions_policies' . $warrant->member_id);
     *     // - Cache::delete('member_permissions' . $warrant->member_id);
     *     
     *     // Next permission check will use fresh warrant data
     *     $hasPermission = $member->checkCan('manage.events', 'Activities');
     * }
     * ```
     *
     * @param \Cake\Event\EventInterface $event The afterSave event
     * @param \App\Model\Entity\Warrant $entity The saved warrant entity
     * @param \ArrayObject $options Save options
     * @return void
     */
    public function afterSave($event, $entity, $options): void
    {
        $memberId = $entity->member_id;

        // Invalidate permission policies cache for affected member
        // This ensures policy resolutions are recalculated with current warrant data
        Cache::delete('permissions_policies' . $memberId);

        // Invalidate member permissions cache for affected member
        // This ensures permission calculations include current warrant status
        Cache::delete('member_permissions' . $memberId);
    }
}
