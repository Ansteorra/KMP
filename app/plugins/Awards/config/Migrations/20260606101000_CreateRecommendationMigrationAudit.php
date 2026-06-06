<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRecommendationMigrationAudit extends BaseMigration
{
    /**
     * Create recommendation migration audit run/result tables.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('awards_recommendation_migration_runs')
            ->addColumn('mode', 'string', [
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('status', 'string', [
                'default' => 'running',
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('filters', 'json', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('summary', 'json', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('started', 'datetime', [
                'null' => false,
            ])
            ->addColumn('completed', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('created_by', 'integer', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('modified_by', 'integer', [
                'default' => null,
                'null' => true,
            ])
            ->addIndex(['mode', 'status'], [
                'name' => 'idx_awards_rec_migration_runs_mode_status',
            ])
            ->create();

        $this->table('awards_recommendation_migration_results')
            ->addColumn('migration_run_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('recommendation_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('original_state', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('original_status', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('target_action', 'string', [
                'limit' => 60,
                'null' => false,
            ])
            ->addColumn('result_status', 'string', [
                'limit' => 30,
                'null' => false,
            ])
            ->addColumn('reason', 'text', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('bestowal_id', 'integer', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('workflow_instance_id', 'integer', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('approval_run_id', 'integer', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('details', 'json', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addForeignKey('migration_run_id', 'awards_recommendation_migration_runs', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_awards_rec_migration_results_run',
            ])
            ->addForeignKey('recommendation_id', 'awards_recommendations', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_awards_rec_migration_results_rec',
            ])
            ->addIndex(['migration_run_id', 'recommendation_id'], [
                'unique' => true,
                'name' => 'idx_awards_rec_migration_results_run_rec',
            ])
            ->addIndex(['target_action', 'result_status'], [
                'name' => 'idx_awards_rec_migration_results_action_status',
            ])
            ->create();
    }

    /**
     * Drop recommendation migration audit run/result tables.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('awards_recommendation_migration_results')->drop()->save();
        $this->table('awards_recommendation_migration_runs')->drop()->save();
    }
}
