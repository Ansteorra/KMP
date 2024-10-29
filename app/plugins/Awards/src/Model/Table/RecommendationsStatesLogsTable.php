<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * AwardsRecommendationsStatesLogs Model
 *
 * @property \Awards\Model\Table\AwardsRecommendationsTable&\Cake\ORM\Association\BelongsTo $AwardsRecommendations
 *
 * @method \Awards\Model\Entity\AwardsRecommendationsStatesLog newEmptyEntity()
 * @method \Awards\Model\Entity\AwardsRecommendationsStatesLog newEntity(array $data, array $options = [])
 * @method array<\Awards\Model\Entity\AwardsRecommendationsStatesLog> newEntities(array $data, array $options = [])
 * @method \Awards\Model\Entity\AwardsRecommendationsStatesLog get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\AwardsRecommendationsStatesLog findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \Awards\Model\Entity\AwardsRecommendationsStatesLog patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\Awards\Model\Entity\AwardsRecommendationsStatesLog> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Awards\Model\Entity\AwardsRecommendationsStatesLog|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\AwardsRecommendationsStatesLog saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\Awards\Model\Entity\AwardsRecommendationsStatesLog>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\AwardsRecommendationsStatesLog>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\AwardsRecommendationsStatesLog>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\AwardsRecommendationsStatesLog> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\AwardsRecommendationsStatesLog>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\AwardsRecommendationsStatesLog>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\Awards\Model\Entity\AwardsRecommendationsStatesLog>|\Cake\Datasource\ResultSetInterface<\Awards\Model\Entity\AwardsRecommendationsStatesLog> deleteManyOrFail(iterable $entities, array $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class RecommendationsStatesLogsTable extends Table
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
     * Default validation rules.
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
     * Returns a rules checker object that will be used for validating
     * application integrity.
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
