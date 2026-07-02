<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\WorkflowInstance;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * WorkflowInstances Model
 *
 * @property \App\Model\Table\WorkflowDefinitionsTable&\Cake\ORM\Association\BelongsTo $WorkflowDefinitions
 * @property \App\Model\Table\WorkflowVersionsTable&\Cake\ORM\Association\BelongsTo $WorkflowVersions
 * @property \App\Model\Table\WorkflowExecutionLogsTable&\Cake\ORM\Association\HasMany $WorkflowExecutionLogs
 * @property \App\Model\Table\WorkflowApprovalsTable&\Cake\ORM\Association\HasMany $WorkflowApprovals
 * @method \App\Model\Entity\WorkflowInstance newEmptyEntity()
 * @method \App\Model\Entity\WorkflowInstance newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\WorkflowInstance patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class WorkflowInstancesTable extends BaseTable
{
    /**
     * Initialize method.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_instances');
        $this->setDisplayField('status');
        $this->setPrimaryKey('id');

        $this->belongsTo('WorkflowDefinitions', [
            'foreignKey' => 'workflow_definition_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('WorkflowVersions', [
            'foreignKey' => 'workflow_version_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('WorkflowExecutionLogs', [
            'foreignKey' => 'workflow_instance_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
        $this->hasMany('WorkflowApprovals', [
            'foreignKey' => 'workflow_instance_id',
            'dependent' => true,
        ]);
        $this->hasMany('WorkflowTasks', [
            'foreignKey' => 'workflow_instance_id',
            'dependent' => true,
        ]);

        $this->addBehavior('Timestamp');

        // MariaDB stores JSON as longtext; explicitly map JSON columns
        $this->setJsonColumnTypesIfPresent(['context', 'active_nodes', 'error_info']);
    }

    /**
     * Default validation rules.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('workflow_definition_id')
            ->requirePresence('workflow_definition_id', 'create')
            ->notEmptyString('workflow_definition_id');

        $validator
            ->integer('workflow_version_id')
            ->requirePresence('workflow_version_id', 'create')
            ->notEmptyString('workflow_version_id');

        $validator
            ->scalar('entity_type')
            ->maxLength('entity_type', 100)
            ->allowEmptyString('entity_type');

        $validator
            ->integer('entity_id')
            ->allowEmptyString('entity_id');

        $validator
            ->scalar('status')
            ->requirePresence('status', 'create')
            ->notEmptyString('status')
            ->inList('status', [
                WorkflowInstance::STATUS_PENDING,
                WorkflowInstance::STATUS_RUNNING,
                WorkflowInstance::STATUS_WAITING,
                WorkflowInstance::STATUS_COMPLETED,
                WorkflowInstance::STATUS_FAILED,
                WorkflowInstance::STATUS_CANCELLED,
            ]);

        $validator
            ->integer('started_by')
            ->allowEmptyString('started_by');

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
        $rules->add($rules->existsIn(['workflow_definition_id'], 'WorkflowDefinitions'), [
            'errorField' => 'workflow_definition_id',
        ]);
        $rules->add($rules->existsIn(['workflow_version_id'], 'WorkflowVersions'), [
            'errorField' => 'workflow_version_id',
        ]);

        return $rules;
    }
}
