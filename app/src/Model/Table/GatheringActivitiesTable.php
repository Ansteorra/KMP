<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;

/**
 * GatheringActivities Model
 *
 * GatheringActivities are configuration/template objects that define types of activities
 * (e.g., "Armored Combat", "Archery"). They can be reused across many gatherings.
 *
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\BelongsToMany $Gatherings
 * @property \App\Model\Table\GatheringTypesTable&\Cake\ORM\Association\BelongsToMany $GatheringTypes
 *
 * @method \App\Model\Entity\GatheringActivity newEmptyEntity()
 * @method \App\Model\Entity\GatheringActivity newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringActivity[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringActivity get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\GatheringActivity findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\GatheringActivity patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringActivity[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringActivity|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\GatheringActivity saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class GatheringActivitiesTable extends Table
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

        $this->setTable('gathering_activities');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        // Many-to-many relationship with Gatherings through join table
        $this->belongsToMany('Gatherings', [
            'foreignKey' => 'gathering_activity_id',
            'targetForeignKey' => 'gathering_id',
            'joinTable' => 'gatherings_gathering_activities',
            'through' => 'GatheringsGatheringActivities',
            'sort' => ['GatheringsGatheringActivities.sort_order' => 'ASC'],
        ]);

        // Many-to-many relationship with GatheringTypes for template activities
        $this->belongsToMany('GatheringTypes', [
            'foreignKey' => 'gathering_activity_id',
            'targetForeignKey' => 'gathering_type_id',
            'joinTable' => 'gathering_type_gathering_activities',
            'through' => 'GatheringTypeGatheringActivities',
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

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
        // No foreign key rules needed here - handled by the join table

        return $rules;
    }
}
