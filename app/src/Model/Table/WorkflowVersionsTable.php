<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\WorkflowVersion;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * WorkflowVersions Model
 *
 * @property \App\Model\Table\WorkflowDefinitionsTable&\Cake\ORM\Association\BelongsTo $WorkflowDefinitions
 * @method \App\Model\Entity\WorkflowVersion newEmptyEntity()
 * @method \App\Model\Entity\WorkflowVersion newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\WorkflowVersion patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class WorkflowVersionsTable extends BaseTable
{
    /**
     * Initialize method.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_versions');
        $this->setDisplayField('version_number');
        $this->setPrimaryKey('id');

        $this->belongsTo('WorkflowDefinitions', [
            'foreignKey' => 'workflow_definition_id',
            'joinType' => 'INNER',
        ]);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');

        $this->setJsonColumnTypesIfPresent(['definition', 'canvas_layout']);
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
            ->integer('version_number')
            ->requirePresence('version_number', 'create')
            ->notEmptyString('version_number');

        $validator
            ->requirePresence('definition', 'create')
            ->notEmptyString('definition');

        $validator
            ->scalar('status')
            ->requirePresence('status', 'create')
            ->notEmptyString('status')
            ->inList('status', [
                WorkflowVersion::STATUS_DRAFT,
                WorkflowVersion::STATUS_PUBLISHED,
                WorkflowVersion::STATUS_ARCHIVED,
            ]);

        $validator
            ->scalar('change_notes')
            ->allowEmptyString('change_notes');

        $validator
            ->dateTime('published_at')
            ->allowEmptyDateTime('published_at');

        $validator
            ->integer('published_by')
            ->allowEmptyString('published_by');

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
        $rules->add($rules->isUnique(
            ['workflow_definition_id', 'version_number'],
            'This version number already exists for this workflow definition.',
        ));

        return $rules;
    }
}
