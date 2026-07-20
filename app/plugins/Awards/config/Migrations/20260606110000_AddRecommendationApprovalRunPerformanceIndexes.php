<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddRecommendationApprovalRunPerformanceIndexes extends BaseMigration
{
    /**
     * Add composite indexes for recommendation approval authorization queries.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('awards_recommendation_approval_runs')
            ->addIndex(['recommendation_id', 'status'], [
                'name' => 'idx_awards_rec_approval_runs_rec_status',
            ])
            ->addIndex(['workflow_instance_id', 'status'], [
                'name' => 'idx_awards_rec_approval_runs_instance_status',
            ])
            ->update();
    }

    /**
     * Remove composite indexes for recommendation approval authorization queries.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('awards_recommendation_approval_runs')
            ->removeIndexByName('idx_awards_rec_approval_runs_rec_status')
            ->removeIndexByName('idx_awards_rec_approval_runs_instance_status')
            ->update();
    }
}
