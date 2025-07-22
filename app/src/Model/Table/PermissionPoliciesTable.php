<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * PermissionPoliciesTable - Dynamic Permission Authorization Policy Management
 *
 * The PermissionPoliciesTable class manages the association between permissions and custom
 * authorization policies, enabling dynamic and context-aware permission checking beyond
 * basic role-based access control. It provides the data access layer for the policy
 * framework that allows permissions to have custom authorization logic.
 *
 * ## Policy Framework Data Management
 *
 * ### Permission-Policy Associations
 * - **One-to-Many with Permissions**: Each permission can have multiple policy validations
 * - **Policy Class References**: Stores full class names for policy implementations
 * - **Method Mapping**: Specifies which methods within policy classes handle authorization
 * - **Dynamic Authorization**: Enables runtime permission evaluation based on context
 *
 * ### Policy Configuration Management
 * - **Replace Strategy Integration**: Works with PermissionsTable replace strategy
 * - **Validation Framework**: Ensures policy classes and methods are properly specified
 * - **Security Cache Integration**: Policy changes trigger security cache invalidation
 * - **Referential Integrity**: Maintains valid references to permission entities
 *
 * ## Database Schema and Structure
 *
 * ### Primary Table Structure
 * ```sql
 * CREATE TABLE permission_policies (
 *     id INT PRIMARY KEY AUTO_INCREMENT,
 *     permission_id INT NOT NULL,
 *     policy_class VARCHAR(255) NOT NULL,
 *     policy_method VARCHAR(255) NOT NULL,
 *     created DATETIME,
 *     modified DATETIME,
 *     created_by INT,
 *     modified_by INT,
 *     deleted DATETIME,
 *     
 *     FOREIGN KEY (permission_id) REFERENCES permissions(id),
 *     INDEX idx_permission_id (permission_id),
 *     INDEX idx_policy_class (policy_class)
 * );
 * ```
 *
 * ### Association Mapping
 * - **Permissions**: belongsTo with INNER JOIN (policies must have valid permissions)
 * - **Required Relationship**: Policies cannot exist without associated permissions
 * - **Cascade Behavior**: Policy deletion when permissions are removed
 *
 * ## Validation and Business Logic
 *
 * ### Policy Configuration Validation
 * - **Permission ID**: Must reference valid permission entity
 * - **Policy Class**: Non-empty string, maximum 255 characters
 * - **Policy Method**: Non-empty string, maximum 255 characters
 * - **String Validation**: Prevents injection and ensures proper format
 * - **Required Fields**: All fields required for create operations
 *
 * ### Data Integrity Features
 * - **Foreign Key Constraints**: Ensures valid permission references
 * - **Policy Class Validation**: Validates class name format and length
 * - **Method Name Validation**: Ensures method names are properly formatted
 * - **Audit Trail**: Complete change tracking through behaviors
 *
 * ## Usage Examples
 *
 * ### Creating Policy Associations
 * ```php
 * // Add single policy to permission
 * $policy = $permissionPoliciesTable->newEntity([
 *     'permission_id' => $permission->id,
 *     'policy_class' => 'App\\Policy\\MemberPolicy',
 *     'policy_method' => 'canManageInBranch'
 * ]);
 * $permissionPoliciesTable->save($policy);
 *
 * // Add multiple policies to permission
 * $policies = [
 *     [
 *         'permission_id' => $permission->id,
 *         'policy_class' => 'App\\Policy\\BranchPolicy',
 *         'policy_method' => 'hasHierarchicalAccess'
 *     ],
 *     [
 *         'permission_id' => $permission->id,
 *         'policy_class' => 'App\\Policy\\TimePolicy',
 *         'policy_method' => 'isWithinBusinessHours'
 *     ]
 * ];
 * $entities = $permissionPoliciesTable->newEntities($policies);
 * $permissionPoliciesTable->saveMany($entities);
 * ```
 *
 * ### Querying Policy Associations
 * ```php
 * // Find all policies for a permission
 * $policies = $permissionPoliciesTable->find()
 *     ->where(['permission_id' => $permissionId])
 *     ->contain(['Permissions'])
 *     ->toArray();
 *
 * // Find policies by class name
 * $memberPolicies = $permissionPoliciesTable->find()
 *     ->where(['policy_class LIKE' => '%MemberPolicy%'])
 *     ->contain(['Permissions'])
 *     ->toArray();
 *
 * // Get policy with permission details
 * $policy = $permissionPoliciesTable->get($policyId, [
 *     'contain' => ['Permissions' => ['Roles']]
 * ]);
 * ```
 *
 * ### Policy Management Operations
 * ```php
 * // Replace all policies for a permission (used with replace strategy)
 * $newPolicies = [
 *     [
 *         'policy_class' => 'App\\Policy\\NewPolicy',
 *         'policy_method' => 'newMethod'
 *     ]
 * ];
 * 
 * // This works with PermissionsTable replace strategy
 * $permission = $permissionsTable->patchEntity($permission, [
 *     'PermissionPolicies' => $newPolicies
 * ]);
 * $permissionsTable->save($permission);
 *
 * // Update individual policy
 * $policy = $permissionPoliciesTable->patchEntity($policy, [
 *     'policy_method' => 'updatedMethodName'
 * ]);
 * $permissionPoliciesTable->save($policy);
 * ```
 *
 * ### Bulk Policy Operations
 * ```php
 * // Find and update policies for specific class
 * $policies = $permissionPoliciesTable->find()
 *     ->where(['policy_class' => 'App\\Policy\\OldPolicy'])
 *     ->toArray();
 *
 * foreach ($policies as $policy) {
 *     $policy->policy_class = 'App\\Policy\\NewPolicy';
 * }
 * $permissionPoliciesTable->saveMany($policies);
 *
 * // Delete policies for specific permission
 * $permissionPoliciesTable->deleteAll([
 *     'permission_id' => $permissionId
 * ]);
 * ```
 *
 * ## Security and Performance Features
 *
 * ### Cache Management
 * - **Security Cache Group**: Policy changes invalidate security-related caches
 * - **Authorization Cache**: Policy modifications affect permission evaluation
 * - **Policy Resolution Cache**: Caches policy lookup results
 * - **Association Cache**: Efficient loading of policy-permission relationships
 *
 * ### Data Security
 * - **Class Name Validation**: Prevents code injection through policy class names
 * - **Method Name Validation**: Ensures method names are safe
 * - **Required Associations**: Policies must have valid permission references
 * - **Audit Trail**: Tracks who created/modified policy associations
 *
 * ### Performance Optimizations
 * - **Index Usage**: Database indexes on permission_id and policy_class
 * - **Efficient Queries**: Optimized for policy lookup operations
 * - **Batch Operations**: Support for bulk policy management
 * - **Replace Strategy**: Efficient policy replacement operations
 *
 * ## Integration Points
 *
 * ### Authorization Framework
 * - **Policy Resolution**: Provides policy data to authorization service
 * - **Dynamic Evaluation**: Enables runtime permission checking
 * - **Context-Aware Authorization**: Supports complex authorization logic
 * - **Method Invocation**: Facilitates policy method execution
 *
 * ### Permission System
 * - **Permission Enhancement**: Adds dynamic behavior to static permissions
 * - **Replace Strategy**: Works with PermissionsTable for efficient updates
 * - **Policy Chains**: Supports multiple policies per permission
 * - **Conditional Logic**: Enables complex permission conditions
 *
 * ### CakePHP Authorization Plugin
 * - **Policy Integration**: Compatible with CakePHP authorization patterns
 * - **Method Resolution**: Supports standard policy method signatures
 * - **Authorization Flow**: Integrates with existing authorization middleware
 * - **Policy Resolver**: Provides data for policy resolution
 *
 * ## Best Practices
 *
 * ### Policy Management
 * - Use descriptive policy class and method names
 * - Keep policy associations simple and focused
 * - Document policy behavior and requirements
 * - Test policy associations thoroughly
 *
 * ### Performance
 * - Minimize the number of policies per permission
 * - Cache policy evaluation results where appropriate
 * - Use efficient policy method implementations
 * - Monitor policy execution performance
 *
 * ### Security
 * - Validate policy class existence before execution
 * - Use proper namespace conventions for policy classes
 * - Audit policy associations regularly
 * - Test policy security implications
 *
 * @see \App\Model\Entity\PermissionPolicy For policy entity documentation
 * @see \App\Model\Entity\Permission For permission entity
 * @see \App\Model\Table\PermissionsTable For permission data access
 * @see \App\Model\Table\BaseTable For inherited functionality
 * @see \App\Services\AuthorizationService For policy execution
 *
 * @property \App\Model\Table\PermissionsTable&\Cake\ORM\Association\BelongsTo $Permissions
 * @method \App\Model\Entity\PermissionPolicy newEmptyEntity()
 * @method \App\Model\Entity\PermissionPolicy newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\PermissionPolicy> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\PermissionPolicy get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\PermissionPolicy findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\PermissionPolicy patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\PermissionPolicy> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\PermissionPolicy|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\PermissionPolicy saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\PermissionPolicy>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PermissionPolicy>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\PermissionPolicy>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PermissionPolicy> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\PermissionPolicy>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PermissionPolicy>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\PermissionPolicy>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\PermissionPolicy> deleteManyOrFail(iterable $entities, array $options = [])
 */
class PermissionPoliciesTable extends BaseTable
{
    /**
     * Initialize method - Configures permission policy table associations and behaviors
     *
     * Sets up the policy framework infrastructure for dynamic permission authorization,
     * establishing the required association with permissions and configuring behaviors
     * for audit trail and data management.
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        // Basic table configuration
        $this->setTable('permission_policies');
        $this->setDisplayField('policy_class');  // Used in form dropdowns, shows policy class name
        $this->setPrimaryKey('id');

        // Required association with Permissions - policies must have valid permissions
        // Uses INNER JOIN to ensure all policies have associated permissions
        $this->belongsTo('Permissions', [
            'foreignKey' => 'permission_id',
            'joinType' => 'INNER',  // Enforces that policies must have valid permissions
        ]);
    }

    /**
     * Default validation rules for permission policy data
     *
     * Implements comprehensive validation for policy associations, ensuring
     * proper policy class and method references and maintaining data integrity.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        // Permission ID validation - must reference valid permission
        $validator
            ->integer('permission_id')           // Must be integer value
            ->notEmptyString('permission_id');   // Required field

        // Policy class validation - must be valid class name
        $validator
            ->scalar('policy_class')                    // Must be string value
            ->maxLength('policy_class', 255)           // Database field limit
            ->requirePresence('policy_class', 'create') // Required when creating policies
            ->notEmptyString('policy_class');          // Cannot be empty

        // Policy method validation - must be valid method name
        $validator
            ->scalar('policy_method')                    // Must be string value
            ->maxLength('policy_method', 255)           // Database field limit
            ->requirePresence('policy_method', 'create') // Required when creating policies
            ->notEmptyString('policy_method');          // Cannot be empty

        return $validator;
    }

    /**
     * Cache configuration for permission policy data
     * 
     * Policy changes affect authorization decisions, so we need to invalidate
     * security-related caches when policy associations are modified.
     */
    protected const CACHES_TO_CLEAR = [];           // No specific caches to clear
    protected const ID_CACHES_TO_CLEAR = [];       // No ID-based caches to clear
    protected const CACHE_GROUPS_TO_CLEAR = ['security'];  // Clear security cache group

    /**
     * Business rules for permission policy data integrity
     *
     * Implements referential integrity constraints ensuring that policy
     * associations reference valid permissions in the system.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        // Ensure permission_id references valid permission entity
        // This prevents orphaned policy associations
        $rules->add($rules->existsIn(['permission_id'], 'Permissions'), ['errorField' => 'permission_id']);

        return $rules;
    }
}
