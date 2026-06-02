<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Awards\Model\Entity\RecommendationFeedbackRequestRecipient;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

class RecommendationFeedbackRequestRecipientsTable extends BaseTable
{
    /**
     * Configure associations and table metadata.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_recommendation_feedback_request_recipients');
        $this->setAlias('FeedbackRecipients');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('FeedbackRequests', [
            'className' => 'Awards.RecommendationFeedbackRequests',
            'foreignKey' => 'feedback_request_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Recipients', [
            'className' => 'Members',
            'foreignKey' => 'recipient_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('RecipientMembers', [
            'className' => 'Members',
            'foreignKey' => 'recipient_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('WorkflowApprovals', [
            'foreignKey' => 'workflow_approval_id',
            'joinType' => 'LEFT',
        ]);
        $this->belongsTo('WorkflowApprovalResponses', [
            'foreignKey' => 'workflow_approval_response_id',
            'joinType' => 'LEFT',
        ]);

        $this->addBehavior('Timestamp');
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
            ->integer('recipient_id')
            ->requirePresence('recipient_id', 'create')
            ->notEmptyString('recipient_id');

        $validator
            ->scalar('status')
            ->requirePresence('status', 'create')
            ->notEmptyString('status')
            ->inList('status', [
                RecommendationFeedbackRequestRecipient::STATUS_PENDING,
                RecommendationFeedbackRequestRecipient::STATUS_RESPONDED,
                RecommendationFeedbackRequestRecipient::STATUS_RETRACTED,
                RecommendationFeedbackRequestRecipient::STATUS_EXPIRED,
            ]);

        $validator->scalar('response_comment')->allowEmptyString('response_comment');
        $validator->dateTime('responded_at')->allowEmptyDateTime('responded_at');
        $validator->dateTime('retracted_at')->allowEmptyDateTime('retracted_at');
        $validator->dateTime('expired_at')->allowEmptyDateTime('expired_at');

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
        $rules->add($rules->existsIn(['recipient_id'], 'Recipients'), [
            'errorField' => 'recipient_id',
        ]);
        $rules->add($rules->isUnique(
            ['feedback_request_id', 'recipient_id'],
            'A member can only receive one feedback request per request.',
        ));

        return $rules;
    }
}
