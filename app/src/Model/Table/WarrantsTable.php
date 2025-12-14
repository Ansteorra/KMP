<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\Cache\Cache;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * WarrantsTable - Warrant Data Management for RBAC Security
 *
 * Manages warrant lifecycle, validation rules, and cache invalidation for the
 * temporal validation layer of Role-Based Access Control.
 *
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \App\Model\Table\WarrantRostersTable&\Cake\ORM\Association\BelongsTo $WarrantRosters
 * @property \App\Model\Table\MemberRolesTable&\Cake\ORM\Association\BelongsTo $MemberRoles
 * @method \App\Model\Entity\Warrant newEmptyEntity()
 * @method \App\Model\Entity\Warrant get(mixed $primaryKey, ...)
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Cake\ORM\Behavior\ActiveWindowBehavior
 * @mixin \Muffin\Footprint\Model\Behavior\FootprintBehavior
 */
class WarrantsTable extends BaseTable
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
     * Define validation rules for warrant data.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
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
     * Build referential integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker.
     * @return \Cake\ORM\RulesChecker
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
     * Invalidate permission caches after save for RBAC security.
     *
     * @param \Cake\Event\EventInterface $event The afterSave event.
     * @param \App\Model\Entity\Warrant $entity Saved warrant entity.
     * @param \ArrayObject $options Save options.
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
