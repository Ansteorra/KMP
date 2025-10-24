<?php

declare(strict_types=1);

namespace Waivers\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * GatheringWaiverActivities Model
 *
 * @property \Waivers\Model\Table\GatheringWaiversTable&\Cake\ORM\Association\BelongsTo $GatheringWaivers
 * @property \App\Model\Table\GatheringActivitiesTable&\Cake\ORM\Association\BelongsTo $GatheringActivities
 *
 * @method \Waivers\Model\Entity\GatheringWaiverActivity newEmptyEntity()
 * @method \Waivers\Model\Entity\GatheringWaiverActivity newEntity(array $data, array $options = [])
 * @method array<\Waivers\Model\Entity\GatheringWaiverActivity> newEntities(array $data, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiverActivity get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Waivers\Model\Entity\GatheringWaiverActivity findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiverActivity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Waivers\Model\Entity\GatheringWaiverActivity> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiverActivity|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Waivers\Model\Entity\GatheringWaiverActivity saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiverActivity>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiverActivity>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiverActivity>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiverActivity> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiverActivity>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiverActivity>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Waivers\Model\Entity\GatheringWaiverActivity>|\Cake\Datasource\ResultSetInterface<\Waivers\Model\Entity\GatheringWaiverActivity> deleteManyOrFail(iterable $entities, array $options = [])
 */
class GatheringWaiverActivitiesTable extends Table
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

        $this->setTable('waivers_gathering_waiver_activities');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        $this->belongsTo('GatheringWaivers', [
            'foreignKey' => 'gathering_waiver_id',
            'joinType' => 'INNER',
            'className' => 'Waivers.GatheringWaivers',
        ]);
        $this->belongsTo('GatheringActivities', [
            'foreignKey' => 'gathering_activity_id',
            'joinType' => 'INNER',
            'className' => 'GatheringActivities',
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
            ->integer('gathering_waiver_id')
            ->requirePresence('gathering_waiver_id', 'create')
            ->notEmptyString('gathering_waiver_id');

        $validator
            ->integer('gathering_activity_id')
            ->requirePresence('gathering_activity_id', 'create')
            ->notEmptyString('gathering_activity_id');

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
        $rules->add($rules->existsIn(['gathering_waiver_id'], 'GatheringWaivers'), [
            'errorField' => 'gathering_waiver_id',
        ]);
        $rules->add($rules->existsIn(['gathering_activity_id'], 'GatheringActivities'), [
            'errorField' => 'gathering_activity_id',
        ]);

        return $rules;
    }
}
