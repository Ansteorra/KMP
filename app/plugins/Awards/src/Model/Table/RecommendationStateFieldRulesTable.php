<?php

declare(strict_types=1);

namespace Awards\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;
use App\Model\Table\BaseTable;

/**
 * RecommendationStateFieldRules Table - Manages field rules per state.
 *
 * @property \Awards\Model\Table\RecommendationStatesTable&\Cake\ORM\Association\BelongsTo $RecommendationStates
 *
 * @method \Awards\Model\Entity\RecommendationStateFieldRule newEmptyEntity()
 * @method \Awards\Model\Entity\RecommendationStateFieldRule newEntity(array $data, array $options = [])
 * @method \Awards\Model\Entity\RecommendationStateFieldRule get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\RecommendationStateFieldRule patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Awards\Model\Entity\RecommendationStateFieldRule|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\RecommendationStateFieldRule saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Muffin\Footprint\Model\Behavior\FootprintBehavior
 */
class RecommendationStateFieldRulesTable extends BaseTable
{
    /**
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_recommendation_state_field_rules');
        $this->setDisplayField('field_target');
        $this->setPrimaryKey('id');

        $this->belongsTo('RecommendationStates', [
            'foreignKey' => 'state_id',
            'joinType' => 'INNER',
            'className' => 'Awards.RecommendationStates',
        ]);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('state_id')
            ->requirePresence('state_id', 'create')
            ->notEmptyString('state_id');

        $validator
            ->scalar('field_target')
            ->maxLength('field_target', 255)
            ->requirePresence('field_target', 'create')
            ->notEmptyString('field_target');

        $validator
            ->scalar('rule_type')
            ->maxLength('rule_type', 50)
            ->requirePresence('rule_type', 'create')
            ->notEmptyString('rule_type')
            ->inList('rule_type', ['Visible', 'Optional', 'Required', 'Disabled', 'Set']);

        $validator
            ->scalar('rule_value')
            ->maxLength('rule_value', 255)
            ->allowEmptyString('rule_value');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules The rules object
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['state_id'], 'RecommendationStates'), [
            'errorField' => 'state_id',
            'message' => 'Invalid state.',
        ]);

        return $rules;
    }
}
