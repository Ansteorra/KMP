<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\WorkflowExecutionLog;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * WorkflowExecutionLogs Model
 *
 * @property \App\Model\Table\WorkflowInstancesTable&\Cake\ORM\Association\BelongsTo $WorkflowInstances
 * @method \App\Model\Entity\WorkflowExecutionLog newEmptyEntity()
 * @method \App\Model\Entity\WorkflowExecutionLog newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\WorkflowExecutionLog patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class WorkflowExecutionLogsTable extends BaseTable
{
    /**
     * Initialize method.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_execution_logs');
        $this->setDisplayField('node_id');
        $this->setPrimaryKey('id');

        $this->belongsTo('WorkflowInstances', [
            'foreignKey' => 'workflow_instance_id',
            'joinType' => 'INNER',
        ]);

        $this->addBehavior('Timestamp');

        // MariaDB stores JSON as longtext; explicitly map JSON columns
        $this->setJsonColumnTypesIfPresent(['input_data', 'output_data']);
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
            ->scalar('node_type')
            ->maxLength('node_type', 50)
            ->requirePresence('node_type', 'create')
            ->notEmptyString('node_type');

        $validator
            ->integer('attempt_number')
            ->notEmptyString('attempt_number');

        $validator
            ->scalar('status')
            ->requirePresence('status', 'create')
            ->notEmptyString('status')
            ->inList('status', [
                WorkflowExecutionLog::STATUS_PENDING,
                WorkflowExecutionLog::STATUS_RUNNING,
                WorkflowExecutionLog::STATUS_COMPLETED,
                WorkflowExecutionLog::STATUS_FAILED,
                WorkflowExecutionLog::STATUS_SKIPPED,
                WorkflowExecutionLog::STATUS_WAITING,
            ]);

        $validator
            ->scalar('error_message')
            ->allowEmptyString('error_message');

        $validator
            ->dateTime('started_at')
            ->allowEmptyDateTime('started_at');

        $validator
            ->dateTime('completed_at')
            ->allowEmptyDateTime('completed_at');

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
