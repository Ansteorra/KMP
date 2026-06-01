<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * BestowalRecommendations Table - Join table linking bestowals to recommendations.
 *
 * @property \Awards\Model\Table\BestowalsTable&\Cake\ORM\Association\BelongsTo $Bestowals
 * @property \Awards\Model\Table\RecommendationsTable&\Cake\ORM\Association\BelongsTo $Recommendations
 * @method \Awards\Model\Entity\BestowalRecommendation newEmptyEntity()
 * @method \Awards\Model\Entity\BestowalRecommendation newEntity(array $data, array $options = [])
 * @method \Awards\Model\Entity\BestowalRecommendation get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \Awards\Model\Entity\BestowalRecommendation patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Awards\Model\Entity\BestowalRecommendation|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \Awards\Model\Entity\BestowalRecommendation saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 */
class BestowalRecommendationsTable extends BaseTable
{
    /**
     * @param array<string, mixed> $config Configuration options
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_bestowal_recommendations');
        $this->setPrimaryKey('id');

        $this->belongsTo('Bestowals', [
            'foreignKey' => 'bestowal_id',
            'joinType' => 'INNER',
            'className' => 'Awards.Bestowals',
        ]);

        $this->belongsTo('Recommendations', [
            'foreignKey' => 'recommendation_id',
            'joinType' => 'INNER',
            'className' => 'Awards.Recommendations',
        ]);

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new',
                ],
            ],
        ]);
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
            ->integer('bestowal_id')
            ->requirePresence('bestowal_id', 'create')
            ->notEmptyString('bestowal_id');

        $validator
            ->integer('recommendation_id')
            ->requirePresence('recommendation_id', 'create')
            ->notEmptyString('recommendation_id');

        return $validator;
    }

    /**
     * @param \Cake\ORM\RulesChecker $rules The rules object
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['bestowal_id'], 'Bestowals'), ['errorField' => 'bestowal_id']);
        $rules->add($rules->existsIn(['recommendation_id'], 'Recommendations'), [
            'errorField' => 'recommendation_id',
        ]);
        $rules->add($rules->isUnique(['bestowal_id', 'recommendation_id']), [
            'errorField' => 'recommendation_id',
            'message' => 'This recommendation is already linked to the bestowal.',
        ]);

        return $rules;
    }
}
