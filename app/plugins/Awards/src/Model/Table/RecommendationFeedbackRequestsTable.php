<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Awards\Model\Entity\RecommendationFeedbackRequest;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

class RecommendationFeedbackRequestsTable extends BaseTable
{
    /**
     * Configure associations and table metadata.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_recommendation_feedback_requests');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Requesters', [
            'className' => 'Members',
            'foreignKey' => 'requester_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('WorkflowInstances', [
            'foreignKey' => 'workflow_instance_id',
            'joinType' => 'LEFT',
        ]);
        $this->hasMany('Items', [
            'className' => 'Awards.RecommendationFeedbackRequestItems',
            'foreignKey' => 'feedback_request_id',
            'dependent' => true,
        ]);
        $this->hasMany('Recipients', [
            'className' => 'Awards.RecommendationFeedbackRequestRecipients',
            'foreignKey' => 'feedback_request_id',
            'dependent' => true,
        ]);

        $this->addBehavior('Timestamp');
    }

    /**
     * Default validation rules.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('requester_id')
            ->requirePresence('requester_id', 'create')
            ->notEmptyString('requester_id');

        $validator
            ->scalar('status')
            ->requirePresence('status', 'create')
            ->notEmptyString('status')
            ->inList('status', [
                RecommendationFeedbackRequest::STATUS_PENDING,
                RecommendationFeedbackRequest::STATUS_COMPLETED,
                RecommendationFeedbackRequest::STATUS_RETRACTED,
                RecommendationFeedbackRequest::STATUS_EXPIRED,
            ]);

        $validator->scalar('message')->allowEmptyString('message');
        $validator->dateTime('deadline')->allowEmptyDateTime('deadline');
        $validator->dateTime('completed_at')->allowEmptyDateTime('completed_at');
        $validator->dateTime('retracted_at')->allowEmptyDateTime('retracted_at');
        $validator->dateTime('expired_at')->allowEmptyDateTime('expired_at');

        return $validator;
    }

    /**
     * Application integrity rules.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['requester_id'], 'Requesters'), [
            'errorField' => 'requester_id',
        ]);
        $rules->add($rules->existsIn(['workflow_instance_id'], 'WorkflowInstances'), [
            'errorField' => 'workflow_instance_id',
        ]);

        return $rules;
    }
}
