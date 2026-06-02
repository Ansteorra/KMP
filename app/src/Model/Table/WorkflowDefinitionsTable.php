<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\WorkflowDefinition;
use Cake\Database\Exception\DatabaseException;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\I18n\DateTime;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * WorkflowDefinitions Model
 *
 * @property \App\Model\Table\WorkflowVersionsTable&\Cake\ORM\Association\BelongsTo $CurrentVersion
 * @property \App\Model\Table\WorkflowVersionsTable&\Cake\ORM\Association\HasMany $WorkflowVersions
 * @property \App\Model\Table\WorkflowInstancesTable&\Cake\ORM\Association\HasMany $WorkflowInstances
 * @method \App\Model\Entity\WorkflowDefinition newEmptyEntity()
 * @method \App\Model\Entity\WorkflowDefinition newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\WorkflowDefinition patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class WorkflowDefinitionsTable extends BaseTable
{
    /**
     * Initialize method.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_definitions');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsTo('CurrentVersion', [
            'className' => 'WorkflowVersions',
            'foreignKey' => 'current_version_id',
            'joinType' => 'LEFT',
        ]);
        $this->hasMany('WorkflowVersions', [
            'foreignKey' => 'workflow_definition_id',
            'dependent' => true,
        ]);
        $this->hasMany('WorkflowInstances', [
            'foreignKey' => 'workflow_definition_id',
        ]);
        $this->hasMany('WorkflowSchedules', [
            'foreignKey' => 'workflow_definition_id',
            'dependent' => true,
        ]);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');

        // MariaDB stores JSON as longtext; explicitly map JSON columns
        $this->getSchema()->setColumnType('trigger_config', 'json');
    }

    /**
     * Default validation rules.
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('slug')
            ->maxLength('slug', 100)
            ->requirePresence('slug', 'create')
            ->notEmptyString('slug')
            ->regex(
                'slug',
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'Slug must contain only lowercase alphanumeric characters and dashes',
            );

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('trigger_type')
            ->requirePresence('trigger_type', 'create')
            ->notEmptyString('trigger_type')
            ->inList('trigger_type', [
                WorkflowDefinition::TRIGGER_EVENT,
                WorkflowDefinition::TRIGGER_MANUAL,
                WorkflowDefinition::TRIGGER_SCHEDULED,
                WorkflowDefinition::TRIGGER_API,
            ]);

        $validator
            ->scalar('entity_type')
            ->maxLength('entity_type', 100)
            ->allowEmptyString('entity_type');

        $validator
            ->boolean('is_active')
            ->notEmptyString('is_active');

        $validator
            ->integer('current_version_id')
            ->allowEmptyString('current_version_id');

        $validator
            ->scalar('execution_mode')
            ->inList('execution_mode', ['durable', 'ephemeral'], __('Execution mode must be durable or ephemeral'))
            ->notEmptyString('execution_mode');

        return $validator;
    }

    /**
     * Build rules for referential integrity.
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add(
            $rules->isUnique(['slug']),
            ['errorField' => 'slug'],
        );
        $rules->add($rules->existsIn(['current_version_id'], 'CurrentVersion'), [
            'errorField' => 'current_version_id',
        ]);

        return $rules;
    }

    /**
     * Check whether a workflow definition has execution history.
     *
     * @param int $id Workflow definition ID
     * @return bool
     */
    public function hasExecutionHistory(int $id): bool
    {
        return $this->WorkflowInstances->exists(['workflow_definition_id' => $id]);
    }

    /**
     * Archive a workflow definition while preserving versions and run history.
     *
     * @param \App\Model\Entity\WorkflowDefinition $workflow Workflow definition
     * @return bool
     */
    public function archiveDefinition(WorkflowDefinition $workflow): bool
    {
        $workflow->is_active = false;
        $workflow->deleted ??= DateTime::now();

        return (bool)$this->save($workflow);
    }

    /**
     * Delete an unused workflow definition.
     *
     * @param \App\Model\Entity\WorkflowDefinition $workflow Workflow definition
     * @return bool
     */
    public function deleteUnusedDefinition(WorkflowDefinition $workflow): bool
    {
        if ($this->hasExecutionHistory((int)$workflow->id)) {
            return false;
        }

        try {
            return (bool)$this->getConnection()->transactional(function () use ($workflow): bool {
                $workflow->current_version_id = null;
                if (!$this->save($workflow)) {
                    return false;
                }

                return (bool)$this->delete($workflow);
            });
        } catch (DatabaseException | RecordNotFoundException) {
            return false;
        }
    }
}
