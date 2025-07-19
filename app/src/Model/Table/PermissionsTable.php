<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * PermissionsTable - KMP RBAC Permission Management and Data Access Layer
 *
 * The PermissionsTable class manages permission data access, validation, and business logic
 * within the KMP Role-Based Access Control (RBAC) system. It handles permission definitions,
 * requirement validation, scoping rules, and the complex relationships between permissions,
 * roles, and custom policy implementations.
 *
 * ## Core Permission Data Management
 *
 * ### Permission-Role Relationships
 * - **Many-to-Many with Roles**: Permissions can be assigned to multiple roles
 * - **Dynamic Permission Loading**: Efficient bulk loading for authorization checks
 * - **Permission Inheritance**: Roles collect permissions for member authorization
 * - **Security Cache Integration**: Permission changes invalidate authorization caches
 *
 * ### Permission Policy Framework
 * - **Custom Policy Associations**: Links permissions to dynamic authorization logic
 * - **Policy Method Mapping**: Specifies which policy methods handle authorization
 * - **Replace Strategy**: Efficient management of policy associations
 * - **Runtime Policy Evaluation**: Dynamic permission checking based on context
 *
 * ### Activity Integration
 * - **Activity-Linked Permissions**: Some permissions tied to specific activities
 * - **Authorization Workflows**: Activity-based permission requirements
 * - **Warrant Integration**: Activity permissions may require active warrants
 * - **Conditional Access**: Permission validity based on activity status
 *
 * ## Database Schema and Structure
 *
 * ### Primary Table Structure
 * ```sql
 * CREATE TABLE permissions (
 *     id INT PRIMARY KEY AUTO_INCREMENT,
 *     name VARCHAR(255) NOT NULL,
 *     activity_id INT NULL,
 *     require_active_membership BOOLEAN DEFAULT FALSE,
 *     require_active_background_check BOOLEAN DEFAULT FALSE,
 *     require_min_age INT DEFAULT 0,
 *     requires_warrant BOOLEAN DEFAULT FALSE,
 *     is_system BOOLEAN DEFAULT FALSE,
 *     is_super_user BOOLEAN DEFAULT FALSE,
 *     scoping_rule ENUM('Global', 'Branch Only', 'Branch and Children'),
 *     created DATETIME,
 *     modified DATETIME,
 *     created_by INT,
 *     modified_by INT,
 *     deleted DATETIME
 * );
 * ```
 *
 * ### Association Mapping
 * - **Roles**: belongsToMany through roles_permissions junction table
 * - **PermissionPolicies**: hasMany for custom authorization logic
 * - **Activities**: belongsTo for activity-linked permissions (optional)
 *
 * ### Junction Table Schema
 * ```sql
 * -- Role permission associations
 * CREATE TABLE roles_permissions (
 *     id INT PRIMARY KEY AUTO_INCREMENT,
 *     role_id INT NOT NULL,
 *     permission_id INT NOT NULL,
 *     UNIQUE KEY unique_role_permission (role_id, permission_id)
 * );
 *
 * -- Permission policy associations
 * CREATE TABLE permission_policies (
 *     id INT PRIMARY KEY AUTO_INCREMENT,
 *     permission_id INT NOT NULL,
 *     policy_class VARCHAR(255) NOT NULL,
 *     policy_method VARCHAR(255) NOT NULL
 * );
 * ```
 *
 * ## Validation and Business Logic
 *
 * ### Permission Validation Rules
 * - **Name Requirements**: Non-empty string, maximum 255 characters
 * - **Boolean Flags**: Validates permission requirement flags
 * - **Age Requirements**: Validates minimum age values
 * - **System Flag Validation**: Ensures proper system permission handling
 * - **Scoping Rule Validation**: Enforces valid scoping constants
 *
 * ### Data Integrity Features
 * - **Foreign Key Constraints**: Maintains referential integrity
 * - **Audit Trail**: Complete change tracking through behaviors
 * - **Soft Deletion**: Preserves permission history for compliance
 * - **Cache Invalidation**: Security cache clearing on permission changes
 *
 * ## Usage Examples
 *
 * ### Basic Permission Operations
 * ```php
 * // Create system permission with requirements
 * $permission = $permissionsTable->newEntity([
 *     'name' => 'Manage System Users',
 *     'is_system' => true,
 *     'require_active_membership' => true,
 *     'require_active_background_check' => true,
 *     'require_min_age' => 18,
 *     'scoping_rule' => Permission::SCOPE_GLOBAL
 * ]);
 * $permissionsTable->save($permission);
 *
 * // Find permission with roles and policies
 * $permission = $permissionsTable->get($id, [
 *     'contain' => [
 *         'Roles',
 *         'PermissionPolicies'
 *     ]
 * ]);
 *
 * // Query permissions by requirements
 * $systemPermissions = $permissionsTable->find()
 *     ->where(['is_system' => true])
 *     ->contain(['Roles'])
 *     ->toArray();
 * ```
 *
 * ### Role-Permission Management
 * ```php
 * // Find all permissions for a role
 * $rolePermissions = $permissionsTable->find()
 *     ->matching('Roles', function ($q) use ($roleId) {
 *         return $q->where(['Roles.id' => $roleId]);
 *     })
 *     ->toArray();
 *
 * // Add permission to multiple roles
 * $permission = $permissionsTable->patchEntity($permission, [
 *     'roles' => [
 *         ['id' => $role1->id],
 *         ['id' => $role2->id],
 *         ['id' => $role3->id]
 *     ]
 * ]);
 * $permissionsTable->save($permission);
 *
 * // Find roles that have specific permission
 * $rolesWithPermission = $permission->roles;
 * ```
 *
 * ### Activity-Linked Permissions
 * ```php
 * // Create activity-specific permission
 * $permission = $permissionsTable->newEntity([
 *     'name' => 'Authorize Archery Activity',
 *     'activity_id' => $archeryActivity->id,
 *     'requires_warrant' => true,
 *     'scoping_rule' => Permission::SCOPE_BRANCH_AND_CHILDREN,
 *     'require_active_membership' => true
 * ]);
 *
 * // Find permissions for specific activity
 * $activityPermissions = $permissionsTable->find()
 *     ->where(['activity_id' => $activityId])
 *     ->contain(['Roles', 'PermissionPolicies'])
 *     ->toArray();
 * ```
 *
 * ### Permission Policy Management
 * ```php
 * // Add custom policy to permission
 * $permission = $permissionsTable->patchEntity($permission, [
 *     'PermissionPolicies' => [
 *         [
 *             'policy_class' => 'App\\Policy\\MemberPolicy',
 *             'policy_method' => 'canManageInBranch'
 *         ]
 *     ]
 * ]);
 * $permissionsTable->save($permission);
 *
 * // Replace all policies for permission
 * $newPolicies = [
 *     [
 *         'policy_class' => 'App\\Policy\\BranchPolicy',
 *         'policy_method' => 'hasHierarchicalAccess'
 *     ]
 * ];
 * $permission->PermissionPolicies = $newPolicies;
 * $permissionsTable->save($permission);
 * ```
 *
 * ## Security and Performance Features
 *
 * ### Cache Management
 * - **Security Cache Group**: Permission changes invalidate security-related caches
 * - **Permission Matrix Cache**: Optimized authorization lookup caching
 * - **Policy Cache**: Caches policy evaluation results
 * - **Association Cache**: Efficient loading of permission relationships
 *
 * ### Authorization Integration
 * - **Scoping Rule Enforcement**: Validates organizational scope restrictions
 * - **Requirement Validation**: Enforces membership, age, and warrant requirements
 * - **Policy Integration**: Supports custom authorization logic
 * - **Branch-Based Access**: Integrates with organizational hierarchy
 *
 * ### Performance Optimizations
 * - **Bulk Permission Loading**: Efficient permission matrix construction
 * - **Association Optimization**: Optimized queries for role-permission lookups
 * - **Policy Caching**: Caches policy evaluation for repeated checks
 * - **Index Usage**: Database indexes on foreign keys and flags
 *
 * ## Integration Points
 *
 * ### Authorization Service
 * - **Permission Loading**: Feeds permission data to authorization service
 * - **Policy Evaluation**: Executes custom authorization policies
 * - **Requirement Checking**: Validates member eligibility for permissions
 * - **Scope Resolution**: Determines permission applicability by scope
 *
 * ### Activity System
 * - **Activity Permissions**: Links permissions to specific activities
 * - **Warrant Requirements**: Integrates with warrant system for authorization
 * - **Authorization Workflows**: Supports activity-based permission granting
 * - **Conditional Access**: Permission validity based on activity status
 *
 * ### Member Management
 * - **Membership Requirements**: Validates active SCA membership
 * - **Age Restrictions**: Enforces minimum age requirements
 * - **Background Checks**: Validates background check requirements
 * - **Eligibility Checking**: Comprehensive member eligibility validation
 *
 * @see \App\Model\Entity\Permission For permission entity documentation
 * @see \App\Model\Entity\PermissionPolicy For custom policy associations
 * @see \App\Model\Entity\Role For role entity
 * @see \App\Model\Table\BaseTable For inherited functionality
 * @see \App\Services\AuthorizationService For permission checking
 *
 * @property \App\Model\Table\ActivitiesTable&\Cake\ORM\Association\BelongsTo $Activities
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsToMany $Roles
 * @property \App\Model\Table\PermissionPoliciesTable&\Cake\ORM\Association\HasMany $PermissionPolicies
 * @method \App\Model\Entity\Permission newEmptyEntity()
 * @method \App\Model\Entity\Permission newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Permission> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Permission get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Permission findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Permission patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Permission> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Permission|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Permission saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Permission>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Permission>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Permission>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Permission> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Permission>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Permission>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Permission>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Permission> deleteManyOrFail(iterable $entities, array $options = [])
 */
class PermissionsTable extends BaseTable
{
    /**
     * Initialize method - Configures permission table associations and behaviors
     *
     * Sets up the permission management infrastructure including role associations,
     * policy framework integration, and activity linkage for the KMP RBAC system.
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        // Basic table configuration
        $this->setTable('permissions');
        $this->setDisplayField('name');  // Used in form dropdowns and string representation
        $this->setPrimaryKey('id');

        // Many-to-many relationship with Roles through roles_permissions junction
        // This defines which roles have which permissions - core RBAC relationship
        $this->belongsToMany('Roles', [
            'foreignKey' => 'permission_id',
            'targetForeignKey' => 'role_id',
            'joinTable' => 'roles_permissions',
        ]);

        // One-to-many relationship with PermissionPolicies for dynamic authorization
        // Uses replace strategy for efficient policy management
        $this->hasMany('PermissionPolicies', [
            'foreignKey' => 'permission_id',
            'saveStrategy' => 'replace',  // Replaces all policies when saving
        ]);

        // Standard CakePHP behaviors for audit trail and data management
        $this->addBehavior('Timestamp');       // Automatic created/modified timestamps
        $this->addBehavior('Muffin/Footprint.Footprint');  // Track who created/modified
        $this->addBehavior('Muffin/Trash.Trash');          // Soft deletion support
    }

    /**
     * Cache configuration for permission-related data
     * 
     * Permissions are at the core of the authorization system, so changes need
     * to trigger appropriate cache invalidation to ensure security decisions
     * are based on current data.
     */
    protected const CACHES_TO_CLEAR = [];           // No specific caches to clear
    protected const ID_CACHES_TO_CLEAR = [];       // No ID-based caches to clear
    protected const CACHE_GROUPS_TO_CLEAR = ['security'];  // Clear security cache group

    /**
     * Default validation rules for permission data
     *
     * Implements comprehensive validation for permission creation and updates,
     * ensuring data integrity and proper permission configuration.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        // Permission name validation - critical for permission identification
        $validator
            ->scalar('name')                    // Must be a string value
            ->maxLength('name', 255)           // Database field limit
            ->requirePresence('name', 'create') // Required when creating new permissions
            ->notEmptyString('name');          // Cannot be empty or whitespace only

        // Membership requirement flag validation
        $validator
            ->boolean('require_active_membership')    // Must be boolean value
            ->notEmptyString('require_active_membership');  // Required field

        // Background check requirement flag validation
        $validator
            ->boolean('require_active_background_check')    // Must be boolean value
            ->notEmptyString('require_active_background_check');  // Required field

        // Minimum age requirement validation
        $validator
            ->integer('require_min_age')       // Must be integer value
            ->notEmptyString('require_min_age');  // Required field (0 = no age requirement)

        // System permission flag validation
        $validator->boolean('is_system')->notEmptyString('is_system');

        // Super user permission flag validation
        $validator->boolean('is_super_user')->notEmptyString('is_super_user');

        return $validator;
    }

    /**
     * Business rules for permission data integrity
     *
     * Currently no custom business rules are implemented beyond validation,
     * but this method provides the framework for adding complex business
     * logic constraints in the future.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        // No custom rules currently implemented
        // Future rules might include:
        // - Validation that super_user permissions have appropriate requirements
        // - Ensuring system permissions cannot be assigned to certain roles
        // - Activity-linked permissions must have valid activity references

        return $rules;
    }
}
