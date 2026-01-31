<?php

declare(strict_types=1);

namespace Waivers\Model\Table;

use Cake\I18n\DateTime;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Waivers\Model\Entity\GatheringWaiverClosure;

/**
 * GatheringWaiverClosures Model
 *
 * Tracks waiver collection status for gatherings. Supports two states:
 * - Ready to close: Event staff signals waivers are complete
 * - Closed: Waiver secretary reviews and confirms closure
 *
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\BelongsTo $Gatherings
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $ClosedByMembers
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $ReadyToCloseByMembers
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
        $this->belongsTo('ReadyToCloseByMembers', [
            'foreignKey' => 'ready_to_close_by',
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
            ->allowEmptyDateTime('closed_at');

        $validator
            ->integer('closed_by')
            ->allowEmptyString('closed_by');

        $validator
            ->dateTime('ready_to_close_at')
            ->allowEmptyDateTime('ready_to_close_at');

        $validator
            ->integer('ready_to_close_by')
            ->allowEmptyString('ready_to_close_by');

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
            'allowNullableNulls' => true,
        ]);
        $rules->add($rules->existsIn(['ready_to_close_by'], 'ReadyToCloseByMembers'), [
            'errorField' => 'ready_to_close_by',
            'allowNullableNulls' => true,
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
        return $this->exists([
            'gathering_id' => $gatheringId,
            'closed_at IS NOT' => null,
        ]);
    }

    /**
     * Check if gathering is marked ready to close.
     *
     * @param int $gatheringId Gathering ID
     * @return bool
     */
    public function isGatheringReadyToClose(int $gatheringId): bool
    {
        return $this->exists([
            'gathering_id' => $gatheringId,
            'ready_to_close_at IS NOT' => null,
        ]);
    }

    /**
     * Get the closure record for a gathering.
     *
     * @param int $gatheringId Gathering ID
     * @return \Waivers\Model\Entity\GatheringWaiverClosure|null
     */
    public function getClosureForGathering(int $gatheringId): ?GatheringWaiverClosure
    {
        return $this->find()
            ->contain(['ClosedByMembers', 'ReadyToCloseByMembers'])
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
        $query = $this->find()
            ->select(['gathering_id'])
            ->where(['closed_at IS NOT' => null]);

        if ($gatheringIds !== null) {
            if (empty($gatheringIds)) {
                return [];
            }
            $query->where(['gathering_id IN' => $gatheringIds]);
        }

        return $query->all()->extract('gathering_id')->toArray();
    }

    /**
     * Get gathering IDs that are marked ready to close but not yet closed.
     *
     * @param array<int>|null $gatheringIds Optional list of gathering IDs to filter.
     * @return array<int>
     */
    public function getReadyToCloseGatheringIds(?array $gatheringIds = null): array
    {
        $query = $this->find()
            ->select(['gathering_id'])
            ->where([
                'ready_to_close_at IS NOT' => null,
                'closed_at IS' => null,
            ]);

        if ($gatheringIds !== null) {
            if (empty($gatheringIds)) {
                return [];
            }
            $query->where(['gathering_id IN' => $gatheringIds]);
        }

        return $query->all()->extract('gathering_id')->toArray();
    }

    /**
     * Mark a gathering as ready to close.
     *
     * @param int $gatheringId Gathering ID
     * @param int $memberId Member marking as ready
     * @return \Waivers\Model\Entity\GatheringWaiverClosure|false
     */
    public function markReadyToClose(int $gatheringId, int $memberId): GatheringWaiverClosure|false
    {
        $closure = $this->find()
            ->where(['gathering_id' => $gatheringId])
            ->first();

        if ($closure) {
            // Update existing record
            $closure->ready_to_close_at = DateTime::now();
            $closure->ready_to_close_by = $memberId;
        } else {
            // Create new record
            $closure = $this->newEntity([
                'gathering_id' => $gatheringId,
                'ready_to_close_at' => DateTime::now(),
                'ready_to_close_by' => $memberId,
            ]);
        }

        return $this->save($closure);
    }

    /**
     * Unmark a gathering as ready to close.
     *
     * @param int $gatheringId Gathering ID
     * @return bool
     */
    public function unmarkReadyToClose(int $gatheringId): bool
    {
        $closure = $this->find()
            ->where(['gathering_id' => $gatheringId])
            ->first();

        if (!$closure) {
            return true; // Nothing to unmark
        }

        // If already closed, cannot unmark
        if ($closure->closed_at !== null) {
            return false;
        }

        // Delete the record entirely if only marked ready
        return (bool)$this->delete($closure);
    }
}
