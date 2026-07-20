<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add source_approval_run_id to awards_bestowals for workflow provenance.
 *
 * Records which recommendation approval run was completed when the bestowal
 * was created from a workflow handoff. Null for ad-hoc bestowals or bestowals
 * created before this migration.
 */
class AddSourceApprovalRunIdToBestowals extends AbstractMigration
{
    public function up(): void
    {
        $this->table('awards_bestowals')
            ->addColumn('source_approval_run_id', 'integer', [
                'null' => true,
                'default' => null,
                'signed' => false,
                'after' => 'source',
            ])
            ->addForeignKey(
                'source_approval_run_id',
                'awards_recommendation_approval_runs',
                'id',
                ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'],
            )
            ->update();

        $this->table('awards_bestowals')
            ->addIndex(['source_approval_run_id'], [
                'name' => 'idx_bestowals_source_approval_run_id',
            ])
            ->update();
    }

    public function down(): void
    {
        $this->table('awards_bestowals')
            ->dropForeignKey('source_approval_run_id')
            ->removeIndex(['source_approval_run_id'])
            ->removeColumn('source_approval_run_id')
            ->update();
    }
}
