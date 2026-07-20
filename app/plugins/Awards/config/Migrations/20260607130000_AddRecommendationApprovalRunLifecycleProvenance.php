<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddRecommendationApprovalRunLifecycleProvenance extends BaseMigration
{
    /**
     * Add explicit terminal provenance for workflow-backed recommendation approvals.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('awards_recommendation_approval_runs')
            ->addColumn('terminal_reason', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => true,
                'after' => 'completed',
            ])
            ->addColumn('consumed_by_bestowal_id', 'integer', [
                'default' => null,
                'null' => true,
                'signed' => false,
                'after' => 'terminal_reason',
            ])
            ->addColumn('superseded_by_bestowal_id', 'integer', [
                'default' => null,
                'null' => true,
                'signed' => false,
                'after' => 'consumed_by_bestowal_id',
            ])
            ->addColumn('rehydrated_from_run_id', 'integer', [
                'default' => null,
                'null' => true,
                'signed' => false,
                'after' => 'superseded_by_bestowal_id',
            ])
            ->addForeignKey(
                'consumed_by_bestowal_id',
                'awards_bestowals',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'],
            )
            ->addForeignKey(
                'superseded_by_bestowal_id',
                'awards_bestowals',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'],
            )
            ->addForeignKey(
                'rehydrated_from_run_id',
                'awards_recommendation_approval_runs',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'],
            )
            ->addIndex(['terminal_reason'], [
                'name' => 'idx_awards_rec_approval_runs_terminal_reason',
            ])
            ->addIndex(['consumed_by_bestowal_id'], [
                'name' => 'idx_awards_rec_approval_runs_consumed_bestowal',
            ])
            ->addIndex(['superseded_by_bestowal_id'], [
                'name' => 'idx_awards_rec_approval_runs_superseded_bestowal',
            ])
            ->addIndex(['rehydrated_from_run_id'], [
                'name' => 'idx_awards_rec_approval_runs_rehydrated_from',
            ])
            ->update();
    }

    /**
     * Remove explicit terminal provenance for workflow-backed recommendation approvals.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('awards_recommendation_approval_runs')
            ->dropForeignKey('consumed_by_bestowal_id')
            ->dropForeignKey('superseded_by_bestowal_id')
            ->dropForeignKey('rehydrated_from_run_id')
            ->removeIndexByName('idx_awards_rec_approval_runs_terminal_reason')
            ->removeIndexByName('idx_awards_rec_approval_runs_consumed_bestowal')
            ->removeIndexByName('idx_awards_rec_approval_runs_superseded_bestowal')
            ->removeIndexByName('idx_awards_rec_approval_runs_rehydrated_from')
            ->removeColumn('terminal_reason')
            ->removeColumn('consumed_by_bestowal_id')
            ->removeColumn('superseded_by_bestowal_id')
            ->removeColumn('rehydrated_from_run_id')
            ->update();
    }
}
