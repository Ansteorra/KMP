<?php

declare(strict_types=1);

namespace Waivers\Model\Table;

use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * GatheringWaivers Model
 *
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\BelongsTo $Gatherings
 * @property \Waivers\Model\Table\WaiverTypesTable&\Cake\ORM\Association\BelongsTo $WaiverTypes
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 * @property \App\Model\Table\DocumentsTable&\Cake\ORM\Association\BelongsTo $Documents
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $CreatedByMembers
 * @property \Waivers\Model\Table\GatheringWaiverActivitiesTable&\Cake\ORM\Association\HasMany $GatheringWaiverActivities
 *
 * @method \Waivers\Model\Entity\GatheringWaiver newEmptyEntity()
 * @method \Waivers\Model\Entity\GatheringWaiver newEntity(array $data, array $options = [])
 * @method array<\Waivers\Model\Entity\GatheringWaiver> newEntities(array $data, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiver get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Waivers\Model\Entity\GatheringWaiver findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiver patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Waivers\Model\Entity\GatheringWaiver> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiver|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiver saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiver>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiver>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiver>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiver> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiver>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiver>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiver>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiver> deleteManyOrFail(iterable $entities, array $options = [])
 */
class GatheringWaiversTable extends Table
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

        $this->setTable('gathering_waivers');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        $this->belongsTo('Gatherings', [
            'foreignKey' => 'gathering_id',
            'joinType' => 'INNER',
            'className' => 'Gatherings',
        ]);
        $this->belongsTo('WaiverTypes', [
            'foreignKey' => 'waiver_type_id',
            'joinType' => 'INNER',
            'className' => 'Waivers.WaiverTypes',
        ]);
        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'className' => 'Members',
        ]);
        $this->belongsTo('Documents', [
            'foreignKey' => 'document_id',
            'joinType' => 'INNER',
            'className' => 'Documents',
        ]);
        $this->belongsTo('CreatedByMembers', [
            'foreignKey' => 'created_by',
            'className' => 'Members',
        ]);
        $this->hasMany('GatheringWaiverActivities', [
            'foreignKey' => 'gathering_waiver_id',
            'className' => 'Waivers.GatheringWaiverActivities',
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
            ->integer('waiver_type_id')
            ->requirePresence('waiver_type_id', 'create')
            ->notEmptyString('waiver_type_id');

        $validator
            ->integer('member_id')
            ->allowEmptyString('member_id');

        $validator
            ->integer('document_id')
            ->requirePresence('document_id', 'create')
            ->notEmptyString('document_id');

        $validator
            ->scalar('status')
            ->inList('status', ['pending', 'active', 'deleted'])
            ->requirePresence('status', 'create')
            ->notEmptyString('status');

        $validator
            ->date('retention_date')
            ->requirePresence('retention_date', 'create')
            ->notEmptyDate('retention_date');

        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');

        $validator
            ->integer('modified_by')
            ->allowEmptyString('modified_by');

        $validator
            ->dateTime('deleted')
            ->allowEmptyDateTime('deleted');

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
        $rules->add($rules->existsIn(['waiver_type_id'], 'WaiverTypes'), [
            'errorField' => 'waiver_type_id',
        ]);
        $rules->add($rules->existsIn(['member_id'], 'Members'), [
            'errorField' => 'member_id',
        ]);
        $rules->add($rules->existsIn(['document_id'], 'Documents'), [
            'errorField' => 'document_id',
        ]);
        $rules->add($rules->existsIn(['created_by'], 'CreatedByMembers'), [
            'errorField' => 'created_by',
        ]);

        return $rules;
    }

    /**
     * Find expired waivers
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query object
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findExpired(SelectQuery $query): SelectQuery
    {
        return $query->where([
            'GatheringWaivers.status' => 'active',
            'GatheringWaivers.retention_date <' => DateTime::now(),
        ]);
    }

    /**
     * Find waivers by status
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query object
     * @param string $status The status to filter by
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByStatus(SelectQuery $query, string $status): SelectQuery
    {
        return $query->where(['GatheringWaivers.status' => $status]);
    }

    /**
     * Find waivers for a specific gathering
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query object
     * @param int $gatheringId The gathering ID
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByGathering(SelectQuery $query, int $gatheringId): SelectQuery
    {
        return $query->where(['GatheringWaivers.gathering_id' => $gatheringId]);
    }

    /**
     * Find waivers for a specific member
     *
     * @param \Cake\ORM\Query\SelectQuery $query The query object
     * @param int $memberId The member ID
     * @return \Cake\ORM\Query\SelectQuery
     */
    public function findByMember(SelectQuery $query, int $memberId): SelectQuery
    {
        return $query->where(['GatheringWaivers.member_id' => $memberId]);
    }
}