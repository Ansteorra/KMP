<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Drop legacy warrant_roster_approvals table.
 * 
 * All approval data has been migrated to workflow_approvals + workflow_approval_responses
 * by the BackfillWarrantRosterApprovalsToWorkflowEngine migration.
 */
class DropWarrantRosterApprovalsTable extends AbstractMigration
{
    public function up(): void
    {
        if ($this->hasTable('warrant_roster_approvals')) {
            $this->table('warrant_roster_approvals')->drop()->save();
        }
    }

    public function down(): void
    {
        $this->table('warrant_roster_approvals', ['id' => false])
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('warrant_roster_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('approver_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('approved_on', 'datetime', [
                'default' => null,
                'null' => true,
            ])
            ->addPrimaryKey(['id'])
            ->addIndex(['approver_id'])
            ->addForeignKey('warrant_roster_id', 'warrant_rosters', 'id', [
                'update' => 'NO_ACTION',
                'delete' => 'NO_ACTION',
            ])
            ->create();
    }
}
