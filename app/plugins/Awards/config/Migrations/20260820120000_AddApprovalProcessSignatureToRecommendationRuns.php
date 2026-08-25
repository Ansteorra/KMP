<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddApprovalProcessSignatureToRecommendationRuns extends BaseMigration
{
    /**
     * Store the exact approval configuration used to start each new run.
     */
    public function up(): void
    {
        $this->table('awards_recommendation_approval_runs')
            ->addColumn('approval_process_signature', 'string', [
                'after' => 'approval_process_id',
                'default' => null,
                'limit' => 64,
                'null' => true,
            ])
            ->addIndex(['approval_process_id', 'approval_process_signature'], [
                'name' => 'idx_awards_rec_runs_process_signature',
            ])
            ->update();
    }

    /**
     * Remove the approval configuration snapshot.
     */
    public function down(): void
    {
        $this->table('awards_recommendation_approval_runs')
            ->removeIndexByName('idx_awards_rec_runs_process_signature')
            ->removeColumn('approval_process_signature')
            ->update();
    }
}
