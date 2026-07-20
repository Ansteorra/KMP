<?php
declare(strict_types=1);

namespace Awards\Model\Table;

use App\Model\Table\BaseTable;
use Awards\Model\Entity\RecommendationMigrationResult;
use Cake\ORM\RulesChecker;
use Cake\Validation\Validator;

class RecommendationMigrationResultsTable extends BaseTable
{
    /**
     * Initialize migration result table associations and behaviors.
     *
     * @param array<string, mixed> $config Table config
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('awards_recommendation_migration_results');
        $this->setDisplayField('target_action');
        $this->setPrimaryKey('id');

        $this->belongsTo('MigrationRuns', [
            'className' => 'Awards.RecommendationMigrationRuns',
            'foreignKey' => 'migration_run_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Recommendations', [
            'className' => 'Awards.Recommendations',
            'foreignKey' => 'recommendation_id',
            'joinType' => 'INNER',
        ]);

        $this->addBehavior('Timestamp');
        $this->getSchema()->setColumnType('details', 'json');
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
            ->integer('migration_run_id')
            ->requirePresence('migration_run_id', 'create')
            ->notEmptyString('migration_run_id');

        $validator
            ->integer('recommendation_id')
            ->requirePresence('recommendation_id', 'create')
            ->notEmptyString('recommendation_id');

        $validator
            ->scalar('target_action')
            ->inList('target_action', [
                RecommendationMigrationResult::TARGET_CLOSED,
                RecommendationMigrationResult::TARGET_BESTOWAL,
                RecommendationMigrationResult::TARGET_APPROVAL_WORKFLOW,
                RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
                RecommendationMigrationResult::TARGET_SKIPPED,
            ])
            ->notEmptyString('target_action');

        $validator
            ->scalar('result_status')
            ->inList('result_status', [
                RecommendationMigrationResult::STATUS_PLANNED,
                RecommendationMigrationResult::STATUS_APPLIED,
                RecommendationMigrationResult::STATUS_SKIPPED,
                RecommendationMigrationResult::STATUS_ERROR,
            ])
            ->notEmptyString('result_status');

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
        $rules->add($rules->existsIn(['migration_run_id'], 'MigrationRuns'), [
            'errorField' => 'migration_run_id',
        ]);
        $rules->add($rules->existsIn(['recommendation_id'], 'Recommendations'), [
            'errorField' => 'recommendation_id',
        ]);
        $rules->add($rules->isUnique(
            ['migration_run_id', 'recommendation_id'],
            'A recommendation can only appear once per migration run.',
        ));

        return $rules;
    }
}
