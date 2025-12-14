<?php

declare(strict_types=1);

namespace Awards\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use App\Model\Table\BaseTable;

/**
 * Manages audit trail for recommendation state transitions.
 * 
 * Provides immutable state change logging for accountability and compliance.
 * Integrates with RecommendationsTable.afterSave() for automatic logging.
 * 
 * See /docs/5.2.3-awards-recommendations-states-logs-table.md for complete documentation.
 * 
 * @property \Awards\Model\Table\RecommendationsTable&\Cake\ORM\Association\BelongsTo $AwardsRecommendations
 * @property \Cake\ORM\Behavior\TimestampBehavior&\Cake\ORM\Behavior $Timestamp
 *
 * @method \Awards\Model\Entity\RecommendationsStatesLog newEmptyEntity()
 * @method \Awards\Model\Entity\RecommendationsStatesLog newEntity(array $data, array $options = [])
 * @method array<\Awards\Model\Entity\RecommendationsStatesLog> newEntities(array $data, array $options = [])
 * @method \Awards\Model\Entity\RecommendationsStatesLog get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\RecommendationsStatesLog findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Awards\Model\Entity\RecommendationsStatesLog patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Awards\Model\Entity\RecommendationsStatesLog> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Awards\Model\Entity\RecommendationsStatesLog|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\RecommendationsStatesLog saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Awards\Model\Entity\RecommendationsStatesLog>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\RecommendationsStatesLog>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\RecommendationsStatesLog>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\RecommendationsStatesLog> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\RecommendationsStatesLog>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\RecommendationsStatesLog>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\RecommendationsStatesLog>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\RecommendationsStatesLog> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class RecommendationsStatesLogsTable extends BaseTable
{
    /**
     * Initialize table settings, associations, and behaviors.
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_recommendations_states_logs');
        $this->setDisplayField('from_state');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('AwardsRecommendations', [
            'foreignKey' => 'recommendation_id',
            'joinType' => 'INNER',
            'className' => 'Awards.Recommendations',
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
            ->integer('recommendation_id')
            ->notEmptyString('recommendation_id');

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
            ->integer('created_by')
            ->allowEmptyString('created_by');

        return $validator;
    }

    /**
     * Business rules for recommendation reference integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['recommendation_id'], 'AwardsRecommendations'), ['errorField' => 'recommendation_id']);

        return $rules;
    }
}
