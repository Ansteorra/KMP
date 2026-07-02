<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\WorkflowInstanceMigration;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

/**
 * WorkflowInstanceMigrations Model
 *
 * @property \App\Model\Table\WorkflowInstancesTable&\Cake\ORM\Association\BelongsTo $WorkflowInstances
 * @property \App\Model\Table\WorkflowVersionsTable&\Cake\ORM\Association\BelongsTo $FromVersion
 * @property \App\Model\Table\WorkflowVersionsTable&\Cake\ORM\Association\BelongsTo $ToVersion
 * @method \App\Model\Entity\WorkflowInstanceMigration newEmptyEntity()
 * @method \App\Model\Entity\WorkflowInstanceMigration newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\WorkflowInstanceMigration patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 */
class WorkflowInstanceMigrationsTable extends BaseTable
{
    /**
     * Initialize method.
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('workflow_instance_migrations');
        $this->setDisplayField('migration_type');
        $this->setPrimaryKey('id');

        $this->belongsTo('WorkflowInstances', [
            'foreignKey' => 'workflow_instance_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('FromVersion', [
            'className' => 'WorkflowVersions',
            'foreignKey' => 'from_version_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('ToVersion', [
            'className' => 'WorkflowVersions',
            'foreignKey' => 'to_version_id',
            'joinType' => 'INNER',
        ]);

        $this->addBehavior('Timestamp');

        // MariaDB stores JSON as longtext; explicitly map JSON columns
        $this->setJsonColumnTypesIfPresent(['node_mapping']);
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
            ->integer('from_version_id')
            ->requirePresence('from_version_id', 'create')
            ->notEmptyString('from_version_id');

        $validator
            ->integer('to_version_id')
            ->requirePresence('to_version_id', 'create')
            ->notEmptyString('to_version_id');

        $validator
            ->scalar('migration_type')
            ->requirePresence('migration_type', 'create')
            ->notEmptyString('migration_type')
            ->inList('migration_type', [
                WorkflowInstanceMigration::MIGRATION_TYPE_AUTOMATIC,
                WorkflowInstanceMigration::MIGRATION_TYPE_MANUAL,
                WorkflowInstanceMigration::MIGRATION_TYPE_ADMIN,
            ]);

        $validator
            ->integer('migrated_by')
            ->allowEmptyString('migrated_by');

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
        $rules->add($rules->existsIn(['from_version_id'], 'FromVersion'), [
            'errorField' => 'from_version_id',
        ]);
        $rules->add($rules->existsIn(['to_version_id'], 'ToVersion'), [
            'errorField' => 'to_version_id',
        ]);

        return $rules;
    }
}
