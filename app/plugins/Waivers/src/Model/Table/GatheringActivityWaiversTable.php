<?php

declare(strict_types=1);

namespace Waivers\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * GatheringActivityWaivers Model
 *
 * @property \App\Model\Table\GatheringActivitiesTable&\Cake\ORM\Association\BelongsTo $GatheringActivities
 * @property \Waivers\Model\Table\WaiverTypesTable&\Cake\ORM\Association\BelongsTo $WaiverTypes
 *
 * @method \Waivers\Model\Entity\GatheringActivityWaiver newEmptyEntity()
 * @method \Waivers\Model\Entity\GatheringActivityWaiver newEntity(array $data, array $options = [])
 * @method array<\Waivers\Model\Entity\GatheringActivityWaiver> newEntities(array $data, array $options = [])
 * @method \Waivers\Model\Entity\GatheringActivityWaiver get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Waivers\Model\Entity\GatheringActivityWaiver findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Waivers\Model\Entity\GatheringActivityWaiver patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Waivers\Model\Entity\GatheringActivityWaiver> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Waivers\Model\Entity\GatheringActivityWaiver|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Waivers\Model\Entity\GatheringActivityWaiver saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringActivityWaiver>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringActivityWaiver>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringActivityWaiver>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringActivityWaiver> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringActivityWaiver>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringActivityWaiver>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringActivityWaiver>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringActivityWaiver> deleteManyOrFail(iterable $entities, array $options = [])
 */
class GatheringActivityWaiversTable extends Table
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

        $this->setTable('waivers_gathering_activity_waivers');
        $this->setPrimaryKey(['gathering_activity_id', 'waiver_type_id']);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        $this->belongsTo('GatheringActivities', [
            'foreignKey' => 'gathering_activity_id',
            'joinType' => 'INNER',
            'className' => 'GatheringActivities',
        ]);
        $this->belongsTo('WaiverTypes', [
            'foreignKey' => 'waiver_type_id',
            'joinType' => 'INNER',
            'className' => 'Waivers.WaiverTypes',
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
            ->integer('gathering_activity_id')
            ->requirePresence('gathering_activity_id', 'create')
            ->notEmptyString('gathering_activity_id');

        $validator
            ->integer('waiver_type_id')
            ->requirePresence('waiver_type_id', 'create')
            ->notEmptyString('waiver_type_id');

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
        $rules->add($rules->existsIn(['gathering_activity_id'], 'GatheringActivities'), [
            'errorField' => 'gathering_activity_id',
        ]);
        $rules->add($rules->existsIn(['waiver_type_id'], 'WaiverTypes'), [
            'errorField' => 'waiver_type_id',
        ]);

        return $rules;
    }
}
