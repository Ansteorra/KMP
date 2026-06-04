<?php

declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * WorkflowApprovalResponses Model
 *
 * @property \App\Model\Table\WorkflowApprovalsTable&\Cake\ORM\Association\BelongsTo $WorkflowApprovals
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $Members
 *
 * @method \App\Model\Entity\WorkflowApprovalResponse newEmptyEntity()
 * @method \App\Model\Entity\WorkflowApprovalResponse newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\WorkflowApprovalResponse patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class WorkflowApprovalResponsesTable extends BaseTable
{
    /**
     * Initialize method.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_approval_responses');
        $this->setDisplayField('decision');
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
    }

    /**
     * Default validation rules.
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
            ->scalar('decision')
            ->requirePresence('decision', 'create')
            ->notEmptyString('decision')
            ->maxLength('decision', 20)
            ->regex('decision', '/^[a-z0-9_]+$/');

        $validator
            ->scalar('comment')
            ->allowEmptyString('comment');

        $validator
            ->dateTime('responded_at')
            ->requirePresence('responded_at', 'create')
            ->notEmptyDateTime('responded_at');

        return $validator;
    }

    /**
     * Build rules for referential integrity.
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
            'A member can only respond once per approval gate.'
        ));

        return $rules;
    }
}
