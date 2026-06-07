<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Awards\Model\Entity\RecommendationApprovalRun;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

class RecommendationApprovalRunsTable extends BaseTable
{
    /**
     * Initialize table associations and behaviors.
     *
     * @param array $config Table configuration
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_recommendation_approval_runs');
        $this->setDisplayField('current_step_label');
        $this->setPrimaryKey('id');

        $this->belongsTo('Recommendations', [
            'className' => 'Awards.Recommendations',
            'foreignKey' => 'recommendation_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('ApprovalProcesses', [
            'className' => 'Awards.ApprovalProcesses',
            'foreignKey' => 'approval_process_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('WorkflowInstances', [
            'foreignKey' => 'workflow_instance_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('ConsumedByBestowals', [
            'className' => 'Awards.Bestowals',
            'foreignKey' => 'consumed_by_bestowal_id',
            'joinType' => 'LEFT',
        ]);
        $this->belongsTo('SupersededByBestowals', [
            'className' => 'Awards.Bestowals',
            'foreignKey' => 'superseded_by_bestowal_id',
            'joinType' => 'LEFT',
        ]);
        $this->belongsTo('RehydratedFromRuns', [
            'className' => 'Awards.RecommendationApprovalRuns',
            'foreignKey' => 'rehydrated_from_run_id',
            'joinType' => 'LEFT',
        ]);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->addBehavior('Muffin/Trash.Trash');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('recommendation_id')
            ->requirePresence('recommendation_id', 'create')
            ->notEmptyString('recommendation_id');

        $validator
            ->integer('approval_process_id')
            ->requirePresence('approval_process_id', 'create')
            ->notEmptyString('approval_process_id');

        $validator
            ->integer('workflow_instance_id')
            ->requirePresence('workflow_instance_id', 'create')
            ->notEmptyString('workflow_instance_id');

        $validator
            ->scalar('status')
            ->inList('status', [
                RecommendationApprovalRun::STATUS_IN_PROGRESS,
                RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                RecommendationApprovalRun::STATUS_APPROVED,
                RecommendationApprovalRun::STATUS_CONSUMED,
                RecommendationApprovalRun::STATUS_CLOSED,
                RecommendationApprovalRun::STATUS_CANCELLED,
            ])
            ->notEmptyString('status');

        $validator
            ->scalar('terminal_reason')
            ->maxLength('terminal_reason', 100)
            ->allowEmptyString('terminal_reason');

        $validator
            ->integer('consumed_by_bestowal_id')
            ->allowEmptyString('consumed_by_bestowal_id');

        $validator
            ->integer('superseded_by_bestowal_id')
            ->allowEmptyString('superseded_by_bestowal_id');

        $validator
            ->integer('rehydrated_from_run_id')
            ->allowEmptyString('rehydrated_from_run_id');

        $validator
            ->scalar('current_step_key')
            ->maxLength('current_step_key', 100)
            ->allowEmptyString('current_step_key');

        $validator
            ->scalar('current_step_label')
            ->maxLength('current_step_label', 255)
            ->allowEmptyString('current_step_label');

        $validator
            ->dateTime('started')
            ->requirePresence('started', 'create')
            ->notEmptyDateTime('started');

        $validator
            ->dateTime('completed')
            ->allowEmptyDateTime('completed');

        return $validator;
    }

    /**
     * Application integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['recommendation_id'], 'Recommendations'), [
            'errorField' => 'recommendation_id',
        ]);
        $rules->add($rules->existsIn(['approval_process_id'], 'ApprovalProcesses'), [
            'errorField' => 'approval_process_id',
        ]);
        $rules->add($rules->existsIn(['workflow_instance_id'], 'WorkflowInstances'), [
            'errorField' => 'workflow_instance_id',
        ]);
        $rules->add($rules->existsIn(['consumed_by_bestowal_id'], 'ConsumedByBestowals'), [
            'errorField' => 'consumed_by_bestowal_id',
        ]);
        $rules->add($rules->existsIn(['superseded_by_bestowal_id'], 'SupersededByBestowals'), [
            'errorField' => 'superseded_by_bestowal_id',
        ]);
        $rules->add($rules->existsIn(['rehydrated_from_run_id'], 'RehydratedFromRuns'), [
            'errorField' => 'rehydrated_from_run_id',
        ]);

        return $rules;
    }
}
