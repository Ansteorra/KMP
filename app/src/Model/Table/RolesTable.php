<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * RolesTable - KMP RBAC Role Management
 *
 * Manages role data, member assignments, and permission associations. Supports
 * temporal role queries through MemberRoles and permission inheritance.
 *
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsToMany $Members
 * @property \App\Model\Table\PermissionsTable&\Cake\ORM\Association\BelongsToMany $Permissions
 * @property \App\Model\Table\MemberRolesTable&\Cake\ORM\Association\HasMany $MemberRoles
 * @method \App\Model\Entity\Role newEmptyEntity()
 * @method \App\Model\Entity\Role get(mixed $primaryKey, ...)
 */
class RolesTable extends BaseTable
{
    /**
     * Initialize table configuration and associations.
     *
     * @param array<string, mixed> $config Table configuration.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        // Basic table configuration
        $this->setTable('roles');
        $this->setDisplayField('name');  // Used in form dropdowns and string representation
        $this->setPrimaryKey('id');

        // Primary many-to-many relationship with Members through MemberRoles junction
        // This provides the main interface for role-member associations
        $this->belongsToMany('Members', [
            'through' => 'MemberRoles',
        ]);

        // Direct access to MemberRole junction records for detailed management
        // Includes all role assignments regardless of time status
        $this->hasMany('MemberRoles', [
            'foreignKey' => 'role_id',
            'bindingKey' => 'id',
            'joinType' => 'LEFT',  // Preserves roles even if no assignments exist
        ]);

        // Specialized temporal associations using custom finders
        // These leverage the ActiveWindow pattern for time-bounded role assignments

        // Currently active role assignments (within start_on and expires_on dates)
        $this->hasMany('CurrentMemberRoles', [
            'className' => 'MemberRoles',
            'finder' => 'current',  // Uses custom finder to filter by date
            'foreignKey' => 'role_id',
        ]);

        // Future role assignments (start_on date in the future)
        $this->hasMany('UpcomingMemberRoles', [
            'className' => 'MemberRoles',
            'finder' => 'upcoming',  // Filters for future start dates
            'foreignKey' => 'role_id',
        ]);

        // Expired or completed role assignments
        $this->hasMany('PreviousMemberRoles', [
            'className' => 'MemberRoles',
            'finder' => 'previous',  // Filters for past/expired assignments
            'foreignKey' => 'role_id',
        ]);

        // Service Principal role assignments for API clients
        $this->hasMany('ServicePrincipalRoles', [
            'className' => 'ServicePrincipalRoles',
            'foreignKey' => 'role_id',
        ]);

        // Currently active service principal role assignments
        $this->hasMany('CurrentServicePrincipalRoles', [
            'className' => 'ServicePrincipalRoles',
            'finder' => 'current',
            'foreignKey' => 'role_id',
        ]);

        // Many-to-many relationship with Permissions through roles_permissions junction
        // This defines what permissions each role grants
        $this->belongsToMany('Permissions', [
            'foreignKey' => 'role_id',
            'targetForeignKey' => 'permission_id',
            'joinTable' => 'roles_permissions',
        ]);

        // Standard CakePHP behaviors for audit trail and data management
        $this->addBehavior('Timestamp');       // Automatic created/modified timestamps
        $this->addBehavior('Muffin/Footprint.Footprint');  // Track who created/modified
        $this->addBehavior('Muffin/Trash.Trash');          // Soft deletion support
    }

    /**
     * Cache invalidation configuration for security-related caches
     * 
     * Role changes affect authorization decisions across the system, so we need
     * to invalidate security-related caches when roles are modified. This ensures
     * permission changes take effect immediately.
     */
    protected const CACHE_GROUPS_TO_CLEAR = ['security'];

    /**
     * Default validation rules for role data
     *
     * Implements comprehensive validation for role creation and updates,
     * focusing on data integrity and security requirements.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        // Role name validation - critical for role identification and security
        $validator
            ->scalar('name')                    // Must be a string value
            ->maxLength('name', 255)           // Database field limit
            ->requirePresence('name', 'create') // Required when creating new roles
            ->notEmptyString('name')           // Cannot be empty or whitespace only
            ->add('name', 'unique', [          // Must be unique across all roles
                'rule' => 'validateUnique',
                'provider' => 'table',
            ]);

        return $validator;
    }

    /**
     * Business rules for role data integrity
     *
     * Implements database-level business rules that go beyond basic validation,
     * ensuring referential integrity and business logic constraints.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        // Enforce unique role names at the database level
        // This provides an additional layer of protection beyond validation
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);

        return $rules;
    }
}
