<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * PermissionPoliciesTable - Dynamic Permission Authorization Policies
 *
 * Manages permission-policy associations for dynamic authorization logic.
 * Links permissions to custom policy classes and methods.
 *
 * @property \App\Model\Table\PermissionsTable&\Cake\ORM\Association\BelongsTo $Permissions
 * @method \App\Model\Entity\PermissionPolicy newEmptyEntity()
 * @method \App\Model\Entity\PermissionPolicy get(mixed $primaryKey, ...)
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
