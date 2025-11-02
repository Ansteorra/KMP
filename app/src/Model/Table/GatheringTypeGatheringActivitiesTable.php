<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;

/**
 * GatheringTypeGatheringActivities Model
 *
 * Join table for many-to-many relationship between GatheringTypes and GatheringActivities.
 * This defines template activities that will be automatically added to gatherings of a specific type.
 *
 * @property \App\Model\Table\GatheringTypesTable&\Cake\ORM\Association\BelongsTo $GatheringTypes
 * @property \App\Model\Table\GatheringActivitiesTable&\Cake\ORM\Association\BelongsTo $GatheringActivities
 *
 * @method \App\Model\Entity\GatheringTypeGatheringActivity newEmptyEntity()
 * @method \App\Model\Entity\GatheringTypeGatheringActivity newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringTypeGatheringActivity[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringTypeGatheringActivity get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\GatheringTypeGatheringActivity findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\GatheringTypeGatheringActivity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringTypeGatheringActivity[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringTypeGatheringActivity|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\GatheringTypeGatheringActivity saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class GatheringTypeGatheringActivitiesTable extends Table
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

        $this->setTable('gathering_type_gathering_activities');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');

        $this->belongsTo('GatheringTypes', [
            'foreignKey' => 'gathering_type_id',
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
            ->nonNegativeInteger('gathering_type_id')
            ->requirePresence('gathering_type_id', 'create')
            ->notEmptyString('gathering_type_id');

        $validator
            ->nonNegativeInteger('gathering_activity_id')
            ->requirePresence('gathering_activity_id', 'create')
            ->notEmptyString('gathering_activity_id');

        $validator
            ->boolean('not_removable')
            ->notEmptyString('not_removable');

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
        $rules->add($rules->existsIn(['gathering_type_id'], 'GatheringTypes'), ['errorField' => 'gathering_type_id']);
        $rules->add($rules->existsIn(['gathering_activity_id'], 'GatheringActivities'), ['errorField' => 'gathering_activity_id']);

        // Ensure unique combination of gathering_type_id and gathering_activity_id
        $rules->add($rules->isUnique(['gathering_type_id', 'gathering_activity_id'], 'This activity is already assigned to this gathering type.'));

        return $rules;
    }
}
