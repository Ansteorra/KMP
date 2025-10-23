<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;

/**
 * GatheringsGatheringActivities Model
 *
 * Join table for many-to-many relationship between Gatherings and GatheringActivities.
 *
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\BelongsTo $Gatherings
 * @property \App\Model\Table\GatheringActivitiesTable&\Cake\ORM\Association\BelongsTo $GatheringActivities
 *
 * @method \App\Model\Entity\GatheringsGatheringActivity newEmptyEntity()
 * @method \App\Model\Entity\GatheringsGatheringActivity newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringsGatheringActivity[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringsGatheringActivity get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\GatheringsGatheringActivity findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\GatheringsGatheringActivity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringsGatheringActivity[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringsGatheringActivity|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\GatheringsGatheringActivity saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class GatheringsGatheringActivitiesTable extends Table
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

        $this->setTable('gatherings_gathering_activities');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');

        $this->belongsTo('Gatherings', [
            'foreignKey' => 'gathering_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('GatheringActivities', [
            'foreignKey' => 'gathering_activity_id',
            'joinType' => 'INNER',
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
            ->nonNegativeInteger('gathering_id')
            ->requirePresence('gathering_id', 'create')
            ->notEmptyString('gathering_id');

        $validator
            ->nonNegativeInteger('gathering_activity_id')
            ->requirePresence('gathering_activity_id', 'create')
            ->notEmptyString('gathering_activity_id');

        $validator
            ->integer('sort_order')
            ->notEmptyString('sort_order');

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
        $rules->add($rules->existsIn(['gathering_id'], 'Gatherings'), ['errorField' => 'gathering_id']);
        $rules->add($rules->existsIn(['gathering_activity_id'], 'GatheringActivities'), ['errorField' => 'gathering_activity_id']);

        // Ensure unique combination of gathering_id and gathering_activity_id
        $rules->add($rules->isUnique(['gathering_id', 'gathering_activity_id'], 'This activity is already assigned to this gathering.'));

        return $rules;
    }
}
