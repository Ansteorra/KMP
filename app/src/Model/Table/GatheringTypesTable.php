<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\ORM\RulesChecker;

/**
 * GatheringTypes Model
 *
 * @property \App\Model\Table\GatheringsTable&\Cake\ORM\Association\HasMany $Gatherings
 *
 * @method \App\Model\Entity\GatheringType newEmptyEntity()
 * @method \App\Model\Entity\GatheringType newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringType[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\GatheringType get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\GatheringType findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\GatheringType patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringType[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\GatheringType|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\GatheringType saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class GatheringTypesTable extends Table
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

        $this->setTable('gathering_types');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');

        $this->hasMany('Gatherings', [
            'foreignKey' => 'gathering_type_id',
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
            ->notEmptyString('name')
            ->add('name', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->boolean('clonable')
            ->notEmptyString('clonable');

        $validator
            ->scalar('color')
            ->maxLength('color', 7)
            ->notEmptyString('color')
            ->add('color', 'validHexColor', [
                'rule' => function ($value) {
                    return (bool)preg_match('/^#[0-9A-Fa-f]{6}$/', $value);
                },
                'message' => 'Color must be a valid hex color code (e.g., #0d6efd)'
            ]);

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
        $rules->add($rules->isUnique(['name'], __('This gathering type name already exists')));

        return $rules;
    }
}