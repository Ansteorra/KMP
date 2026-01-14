<?php

declare(strict_types=1);

namespace Waivers\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * GatheringWaiverClosures Model
 *
 * Tracks when waiver collection is closed for a gathering.
 *
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\BelongsTo $Gatherings
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $ClosedByMembers
 */
class GatheringWaiverClosuresTable extends Table
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

        $this->setTable('waivers_gathering_waiver_closures');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Gatherings', [
            'foreignKey' => 'gathering_id',
            'joinType' => 'INNER',
            'className' => 'Gatherings',
        ]);
        $this->belongsTo('ClosedByMembers', [
            'foreignKey' => 'closed_by',
            'className' => 'Members',
        ]);
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
            ->integer('gathering_id')
            ->requirePresence('gathering_id', 'create')
            ->notEmptyString('gathering_id');

        $validator
            ->dateTime('closed_at')
            ->requirePresence('closed_at', 'create')
            ->notEmptyDateTime('closed_at');

        $validator
            ->integer('closed_by')
            ->requirePresence('closed_by', 'create')
            ->notEmptyString('closed_by');

        $validator
            ->dateTime('created')
            ->allowEmptyDateTime('created');

        $validator
            ->dateTime('modified')
            ->allowEmptyDateTime('modified');

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
        $rules->add($rules->existsIn(['gathering_id'], 'Gatherings'), [
            'errorField' => 'gathering_id',
        ]);
        $rules->add($rules->existsIn(['closed_by'], 'ClosedByMembers'), [
            'errorField' => 'closed_by',
        ]);
        $rules->add($rules->isUnique(['gathering_id']), [
            'errorField' => 'gathering_id',
        ]);

        return $rules;
    }

    /**
     * Check if waiver collection is closed for a gathering.
     *
     * @param int $gatheringId Gathering ID
     * @return bool
     */
    public function isGatheringClosed(int $gatheringId): bool
    {
        return $this->exists(['gathering_id' => $gatheringId]);
    }

    /**
     * Get the closure record for a gathering.
     *
     * @param int $gatheringId Gathering ID
     * @return \Waivers\Model\Entity\GatheringWaiverClosure|null
     */
    public function getClosureForGathering(int $gatheringId)
    {
        return $this->find()
            ->contain(['ClosedByMembers'])
            ->where(['gathering_id' => $gatheringId])
            ->first();
    }

    /**
     * Get closed gathering IDs, optionally filtered to a subset.
     *
     * @param array<int>|null $gatheringIds Optional list of gathering IDs to filter.
     * @return array<int>
     */
    public function getClosedGatheringIds(?array $gatheringIds = null): array
    {
        $query = $this->find()->select(['gathering_id']);
        if ($gatheringIds !== null) {
            if (empty($gatheringIds)) {
                return [];
            }
            $query->where(['gathering_id IN' => $gatheringIds]);
        }

        return $query->all()->extract('gathering_id')->toArray();
    }
}
