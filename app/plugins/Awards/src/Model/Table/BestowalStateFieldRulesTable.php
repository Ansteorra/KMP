<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * BestowalStateFieldRules Table - Manages field rules per bestowal state.
 *
 * @property \Awards\Model\Table\BestowalStatesTable&\Cake\ORM\Association\BelongsTo $BestowalStates
 * @method \Awards\Model\Entity\BestowalStateFieldRule newEmptyEntity()
 * @method \Awards\Model\Entity\BestowalStateFieldRule newEntity(array $data, array $options = [])
 * @method \Awards\Model\Entity\BestowalStateFieldRule get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\BestowalStateFieldRule patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Awards\Model\Entity\BestowalStateFieldRule|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\BestowalStateFieldRule saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Muffin\Footprint\Model\Behavior\FootprintBehavior
 */
class BestowalStateFieldRulesTable extends BaseTable
{
    /**
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_bestowal_state_field_rules');
        $this->setDisplayField('field_target');
        $this->setPrimaryKey('id');

        $this->belongsTo('BestowalStates', [
            'foreignKey' => 'state_id',
            'joinType' => 'INNER',
            'className' => 'Awards.BestowalStates',
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
        $rules->add($rules->existsIn(['state_id'], 'BestowalStates'), [
            'errorField' => 'state_id',
            'message' => 'Invalid state.',
        ]);

        return $rules;
    }
}
