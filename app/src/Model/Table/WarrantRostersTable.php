<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\WarrantRoster;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

/**
 * WarrantRostersTable - Batch Warrant Management
 *
 * Manages warrant roster batches with multi-level approval workflow.
 * Tracks approval status and provides pending counts for dashboard integration.
 *
 * @property \App\Model\Table\WarrantRosterApprovalsTable&\Cake\ORM\Association\HasMany $WarrantRosterApprovals
 * @property \App\Model\Table\WarrantsTable&\Cake\ORM\Association\HasMany $Warrants
 * @method \App\Model\Entity\WarrantRoster newEmptyEntity()
 * @method \App\Model\Entity\WarrantRoster get(mixed $primaryKey, ...)
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Muffin\Footprint\Model\Behavior\FootprintBehavior
 */
class WarrantRostersTable extends BaseTable
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

        // Basic table configuration for warrant roster management
        $this->setTable('warrant_rosters');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        // Audit trail behavior for automatic timestamp management
        $this->addBehavior('Timestamp');

        // Association with approval tracking entities for multi-level approval workflow
        $this->hasMany('WarrantRosterApprovals', [
            'foreignKey' => 'warrant_roster_id',
        ]);
        // Association with warrant entities created from this roster batch
        $this->hasMany('Warrants', [
            'foreignKey' => 'warrant_roster_id',
        ]);

        // Audit trail associations for tracking user accountability
        $this->belongsTo('CreatedByMember', [
            'className' => 'Members',
            'foreignKey' => 'created_by',
            'joinType' => 'LEFT',  // LEFT JOIN allows rosters without creator tracking
        ]);

        $this->belongsTo('ModfiedByMember', [
            'className' => 'Members',
            'foreignKey' => 'modified_by',
            'joinType' => 'LEFT',  // LEFT JOIN allows rosters without modifier tracking
        ]);

        // Duplicate behavior setup (appears to be redundant - consider removing one)
        $this->addBehavior('Timestamp');

        // Footprint behavior for automatic user tracking in audit trail
        $this->addBehavior('Muffin/Footprint.Footprint');
    }

    /**
     * Define validation rules for warrant roster data.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        // Warrant roster name validation - required descriptive identifier
        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        // Warrant roster description validation - detailed purpose explanation
        $validator
            ->scalar('description')
            ->maxLength('description', 255)
            ->requirePresence('description', 'create')
            ->notEmptyString('description');

        // Planned expiration date validation - warrant lifecycle end planning
        $validator
            ->dateTime('planned_expires_on')
            ->requirePresence('planned_expires_on', 'create')
            ->notEmptyDateTime('planned_expires_on');

        // Planned start date validation - warrant lifecycle begin planning
        $validator
            ->dateTime('planned_start_on')
            ->requirePresence('planned_start_on', 'create')
            ->notEmptyDateTime('planned_start_on');

        // Approval requirements validation - workflow configuration
        $validator
            ->integer('approvals_required')
            ->requirePresence('approvals_required', 'create')
            ->notEmptyString('approvals_required');

        // Current approval count validation - automatic tracking field
        $validator
            ->integer('approval_count')
            ->allowEmptyString('approval_count');

        // Audit trail validation - creator tracking (optional)
        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');

        return $validator;
    }

    /**
     * Get count of pending warrant rosters for dashboard badges.
     *
     * @return int Count of pending rosters.
     */
    public static function getPendingRosterCount(): int
    {
        // Get table instance through registry for clean database access
        $warrantRostersTable = TableRegistry::getTableLocator()->get('WarrantRosters');

        // Execute optimized count query for pending rosters only
        return $warrantRostersTable->find()
            ->where([
                'status' => WarrantRoster::STATUS_PENDING,  // Filter to pending status only
            ])
            ->count();  // Use count() for performance optimization
    }
}
