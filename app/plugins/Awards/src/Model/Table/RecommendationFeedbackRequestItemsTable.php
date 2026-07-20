<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

class RecommendationFeedbackRequestItemsTable extends BaseTable
{
    /**
     * Configure associations and table metadata.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_recommendation_feedback_request_items');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('FeedbackRequests', [
            'className' => 'Awards.RecommendationFeedbackRequests',
            'foreignKey' => 'feedback_request_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Recommendations', [
            'className' => 'Awards.Recommendations',
            'foreignKey' => 'recommendation_id',
            'joinType' => 'INNER',
        ]);

        $this->addBehavior('Timestamp');
        $this->getSchema()->setColumnType('snapshot', 'json');
    }

    /**
     * Default validation rules.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('feedback_request_id')
            ->requirePresence('feedback_request_id', 'create')
            ->notEmptyString('feedback_request_id');

        $validator
            ->integer('recommendation_id')
            ->requirePresence('recommendation_id', 'create')
            ->notEmptyString('recommendation_id');

        $validator
            ->requirePresence('snapshot', 'create')
            ->notEmptyArray('snapshot');

        return $validator;
    }

    /**
     * Application integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['feedback_request_id'], 'FeedbackRequests'), [
            'errorField' => 'feedback_request_id',
        ]);
        $rules->add($rules->existsIn(['recommendation_id'], 'Recommendations'), [
            'errorField' => 'recommendation_id',
        ]);

        return $rules;
    }
}
