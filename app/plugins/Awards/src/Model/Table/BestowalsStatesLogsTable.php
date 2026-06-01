<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * Manages audit trail for bestowal state transitions.
 *
 * @property \Awards\Model\Table\BestowalsTable&\Cake\ORM\Association\BelongsTo $Bestowals
 * @method \Awards\Model\Entity\BestowalsStatesLog newEmptyEntity()
 * @method \Awards\Model\Entity\BestowalsStatesLog newEntity(array $data, array $options = [])
 * @method array<\Awards\Model\Entity\BestowalsStatesLog> newEntities(array $data, array $options = [])
 * @method \Awards\Model\Entity\BestowalsStatesLog get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\BestowalsStatesLog findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Awards\Model\Entity\BestowalsStatesLog patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Awards\Model\Entity\BestowalsStatesLog|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\BestowalsStatesLog saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class BestowalsStatesLogsTable extends BaseTable
{
    /**
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_bestowals_states_logs');
        $this->setDisplayField('from_state');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Bestowals', [
            'foreignKey' => 'bestowal_id',
            'joinType' => 'INNER',
            'className' => 'Awards.Bestowals',
        ]);
    }

    /**
     * Default validation rules for state transition logs.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('bestowal_id')
            ->notEmptyString('bestowal_id');

        $validator
            ->scalar('from_state')
            ->maxLength('from_state', 255)
            ->requirePresence('from_state', 'create')
            ->notEmptyString('from_state');

        $validator
            ->scalar('to_state')
            ->maxLength('to_state', 255)
            ->requirePresence('to_state', 'create')
            ->notEmptyString('to_state');

        $validator
            ->scalar('from_status')
            ->maxLength('from_status', 255)
            ->requirePresence('from_status', 'create')
            ->notEmptyString('from_status');

        $validator
            ->scalar('to_status')
            ->maxLength('to_status', 255)
            ->requirePresence('to_status', 'create')
            ->notEmptyString('to_status');

        $validator
            ->integer('created_by')
            ->allowEmptyString('created_by');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules The rules object
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['bestowal_id'], 'Bestowals'), ['errorField' => 'bestowal_id']);

        return $rules;
    }
}
