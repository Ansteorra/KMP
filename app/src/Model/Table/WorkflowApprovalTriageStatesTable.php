<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\WorkflowApprovalTriageState;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * WorkflowApprovalTriageStates Model
 *
 * @property \App\Model\Table\WorkflowApprovalsTable&\Cake\ORM\Association\BelongsTo $WorkflowApprovals
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 */
class WorkflowApprovalTriageStatesTable extends BaseTable
{
    /**
     * Initialize table associations and behaviors.
     *
     * @param array<string, mixed> $config Table config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_approval_triage_states');
        $this->setDisplayField('state');
        $this->setPrimaryKey('id');

        $this->belongsTo('WorkflowApprovals', [
            'foreignKey' => 'workflow_approval_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Members', [
            'foreignKey' => 'member_id',
            'joinType' => 'INNER',
        ]);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
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
            ->integer('workflow_approval_id')
            ->requirePresence('workflow_approval_id', 'create')
            ->notEmptyString('workflow_approval_id');

        $validator
            ->integer('member_id')
            ->requirePresence('member_id', 'create')
            ->notEmptyString('member_id');

        $validator
            ->scalar('state')
            ->maxLength('state', 40)
            ->requirePresence('state', 'create')
            ->notEmptyString('state')
            ->inList('state', WorkflowApprovalTriageState::states());

        $validator
            ->scalar('note')
            ->allowEmptyString('note');

        return $validator;
    }

    /**
     * Application integrity rules.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['workflow_approval_id'], 'WorkflowApprovals'), [
            'errorField' => 'workflow_approval_id',
        ]);
        $rules->add($rules->existsIn(['member_id'], 'Members'), [
            'errorField' => 'member_id',
        ]);
        $rules->add($rules->isUnique(
            ['workflow_approval_id', 'member_id'],
            'A member can only keep one triage state per approval.',
        ));

        return $rules;
    }
}
