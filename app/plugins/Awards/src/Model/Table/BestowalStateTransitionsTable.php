<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * BestowalStateTransitions Table - Valid bestowal state-to-state transitions.
 *
 * @property \Awards\Model\Table\BestowalStatesTable&\Cake\ORM\Association\BelongsTo $FromStates
 * @property \Awards\Model\Table\BestowalStatesTable&\Cake\ORM\Association\BelongsTo $ToStates
 * @method \Awards\Model\Entity\BestowalStateTransition newEmptyEntity()
 * @method \Awards\Model\Entity\BestowalStateTransition newEntity(array $data, array $options = [])
 * @method \Awards\Model\Entity\BestowalStateTransition get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\BestowalStateTransition patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Awards\Model\Entity\BestowalStateTransition|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\BestowalStateTransition saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 * @mixin \Muffin\Footprint\Model\Behavior\FootprintBehavior
 */
class BestowalStateTransitionsTable extends BaseTable
{
    /**
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_bestowal_state_transitions');
        $this->setPrimaryKey('id');

        $this->belongsTo('FromStates', [
            'foreignKey' => 'from_state_id',
            'joinType' => 'INNER',
            'className' => 'Awards.BestowalStates',
        ]);

        $this->belongsTo('ToStates', [
            'foreignKey' => 'to_state_id',
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
            ->integer('from_state_id')
            ->requirePresence('from_state_id', 'create')
            ->notEmptyString('from_state_id');

        $validator
            ->integer('to_state_id')
            ->requirePresence('to_state_id', 'create')
            ->notEmptyString('to_state_id');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules The rules object
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['from_state_id'], 'FromStates'), [
            'errorField' => 'from_state_id',
            'message' => 'Invalid from state.',
        ]);
        $rules->add($rules->existsIn(['to_state_id'], 'ToStates'), [
            'errorField' => 'to_state_id',
            'message' => 'Invalid to state.',
        ]);
        $rules->add($rules->isUnique(['from_state_id', 'to_state_id']), [
            'errorField' => 'to_state_id',
            'message' => 'This transition already exists.',
        ]);

        return $rules;
    }
}
