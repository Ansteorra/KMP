<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * PermissionsTable - KMP RBAC Permission Management
 *
 * Manages permission data for the RBAC system. Handles permission-role relationships,
 * policy framework integration, and activity linkage.
 *
 * @property \App\Model\Table\RolesTable&\Cake\ORM\Association\BelongsToMany $Roles
 * @property \App\Model\Table\PermissionPoliciesTable&\Cake\ORM\Association\HasMany $PermissionPolicies
 * @method \App\Model\Entity\Permission newEmptyEntity()
 * @method \App\Model\Entity\Permission get(mixed $primaryKey, ...)
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
