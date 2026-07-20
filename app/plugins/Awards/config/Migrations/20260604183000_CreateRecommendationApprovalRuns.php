<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRecommendationApprovalRuns extends BaseMigration
{
    /**
     * Create recommendation approval run projection table.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('awards_recommendation_approval_runs')
            ->addColumn('recommendation_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('approval_process_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('workflow_instance_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('status', 'string', [
                'default' => 'in_progress',
                'limit' => 40,
                'null' => false,
            ])
            ->addColumn('current_step_key', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => true,
            ])
            ->addColumn('current_step_label', 'string', [
                'default' => null,
                'limit' => 255,
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
            ->addColumn('deleted', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addForeignKey('recommendation_id', 'awards_recommendations', 'id')
            ->addForeignKey('approval_process_id', 'awards_approval_processes', 'id')
            ->addForeignKey('workflow_instance_id', 'workflow_instances', 'id')
            ->addIndex(['recommendation_id'], [
                'name' => 'idx_awards_rec_approval_runs_recommendation',
            ])
            ->addIndex(['workflow_instance_id'], [
                'name' => 'idx_awards_rec_approval_runs_workflow_instance',
            ])
            ->addIndex(['status'], [
                'name' => 'idx_awards_rec_approval_runs_status',
            ])
            ->create();
    }

    /**
     * Drop recommendation approval run projection table.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('awards_recommendation_approval_runs')->drop()->save();
    }
}
