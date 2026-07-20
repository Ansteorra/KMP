<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Awards\Model\Entity\RecommendationMigrationRun;
use Cake\Validation\Validator;

class RecommendationMigrationRunsTable extends BaseTable
{
    /**
     * Initialize migration run table associations and behaviors.
     *
     * @param array<string, mixed> $config Table config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_recommendation_migration_runs');
        $this->setDisplayField('mode');
        $this->setPrimaryKey('id');

        $this->hasMany('Results', [
            'className' => 'Awards.RecommendationMigrationResults',
            'foreignKey' => 'migration_run_id',
            'dependent' => true,
        ]);

        $this->addBehavior('Timestamp');
        $this->addBehavior('Muffin/Footprint.Footprint');
        $this->getSchema()->setColumnType('filters', 'json');
        $this->getSchema()->setColumnType('summary', 'json');
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
            ->scalar('mode')
            ->inList('mode', [
                RecommendationMigrationRun::MODE_DRY_RUN,
                RecommendationMigrationRun::MODE_APPLY,
                RecommendationMigrationRun::MODE_RESUME,
            ])
            ->notEmptyString('mode');

        $validator
            ->scalar('status')
            ->inList('status', [
                RecommendationMigrationRun::STATUS_RUNNING,
                RecommendationMigrationRun::STATUS_COMPLETED,
                RecommendationMigrationRun::STATUS_FAILED,
            ])
            ->notEmptyString('status');

        $validator
            ->dateTime('started')
            ->requirePresence('started', 'create')
            ->notEmptyDateTime('started');

        $validator->dateTime('completed')->allowEmptyDateTime('completed');

        return $validator;
    }
}
