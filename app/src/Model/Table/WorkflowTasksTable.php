<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\WorkflowTask;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * WorkflowTasks Model
 *
 * Human task records created by humanTask workflow nodes. Each record
 * represents a form that must be completed by an assigned user before
 * the workflow can resume.
 *
 * @property \App\Model\Table\WorkflowInstancesTable&\Cake\ORM\Association\BelongsTo $WorkflowInstances
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $AssignedMembers
 * @property \App\Model\Table\MembersTable&\Cake\ORM\Association\BelongsTo $CompletingMembers
 * @method \App\Model\Entity\WorkflowTask newEmptyEntity()
 * @method \App\Model\Entity\WorkflowTask newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\WorkflowTask patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class WorkflowTasksTable extends BaseTable
{
    /**
     * Initialize method.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_tasks');
        $this->setDisplayField('task_title');
        $this->setPrimaryKey('id');

        $this->belongsTo('WorkflowInstances', [
            'foreignKey' => 'workflow_instance_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('AssignedMembers', [
            'className' => 'Members',
            'foreignKey' => 'assigned_to',
            'joinType' => 'LEFT',
        ]);
        $this->belongsTo('CompletingMembers', [
            'className' => 'Members',
            'foreignKey' => 'completed_by',
            'joinType' => 'LEFT',
        ]);

        $this->addBehavior('Timestamp');

        // MariaDB stores JSON as longtext; explicitly map JSON columns
        $this->setJsonColumnTypesIfPresent(['form_definition', 'form_data']);
    }

    /**
     * Default validation rules.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('workflow_instance_id')
            ->requirePresence('workflow_instance_id', 'create')
            ->notEmptyString('workflow_instance_id');

        $validator
            ->scalar('node_id')
            ->maxLength('node_id', 100)
            ->requirePresence('node_id', 'create')
            ->notEmptyString('node_id');

        $validator
            ->integer('assigned_to')
            ->allowEmptyString('assigned_to');

        $validator
            ->scalar('assigned_by_role')
            ->maxLength('assigned_by_role', 255)
            ->allowEmptyString('assigned_by_role');

        $validator
            ->scalar('task_title')
            ->maxLength('task_title', 255)
            ->allowEmptyString('task_title');

        $validator
            ->scalar('status')
            ->requirePresence('status', 'create')
            ->notEmptyString('status')
            ->inList('status', [
                WorkflowTask::STATUS_PENDING,
                WorkflowTask::STATUS_COMPLETED,
                WorkflowTask::STATUS_CANCELLED,
                WorkflowTask::STATUS_EXPIRED,
            ]);

        $validator
            ->dateTime('due_date')
            ->allowEmptyDateTime('due_date');

        $validator
            ->dateTime('completed_at')
            ->allowEmptyDateTime('completed_at');

        $validator
            ->integer('completed_by')
            ->allowEmptyString('completed_by');

        return $validator;
    }

    /**
     * Build rules for referential integrity.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['workflow_instance_id'], 'WorkflowInstances'), [
            'errorField' => 'workflow_instance_id',
        ]);

        return $rules;
    }
}
