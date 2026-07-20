<?php

declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Add denormalized eligibility lookup fields for approval and to-do badge/list queries.
 *
 * The JSON config columns remain the workflow/to-do assignment snapshot. These
 * lookup columns mirror the hot read keys so member-centric badge queries do
 * not need to scan and parse every JSON payload.
 */
class AddEligibilityLookupColumns extends BaseMigration
{
    /**
     * Add lookup columns and indexes.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('workflow_approvals')
            ->addColumn('approver_lookup_type', 'string', [
                'limit' => 30,
                'default' => null,
                'null' => true,
                'after' => 'current_approver_id',
                'comment' => 'Denormalized approver target type: member, role, permission, office, policy',
            ])
            ->addColumn('approver_lookup_id', 'integer', [
                'default' => null,
                'null' => true,
                'after' => 'approver_lookup_type',
                'comment' => 'Denormalized approver source ID for member, role, permission, or office targets',
            ])
            ->addColumn('approver_lookup_name', 'string', [
                'limit' => 255,
                'default' => null,
                'null' => true,
                'after' => 'approver_lookup_id',
                'comment' => 'Denormalized approver source name for legacy role/permission configs',
            ])
            ->addColumn('approver_lookup_branch_id', 'integer', [
                'default' => null,
                'null' => true,
                'after' => 'approver_lookup_name',
                'comment' => 'Denormalized explicit branch scope for dynamic approvers',
            ])
            ->addColumn('approver_lookup_branch_mode', 'string', [
                'limit' => 50,
                'default' => null,
                'null' => true,
                'after' => 'approver_lookup_branch_id',
                'comment' => 'Denormalized dynamic approver branch mode',
            ])
            ->addColumn('approver_lookup_branch_type', 'string', [
                'limit' => 50,
                'default' => null,
                'null' => true,
                'after' => 'approver_lookup_branch_mode',
                'comment' => 'Denormalized ancestor branch type for dynamic approvers',
            ])
            ->addColumn('approver_lookup_context_id', 'integer', [
                'default' => null,
                'null' => true,
                'after' => 'approver_lookup_branch_type',
                'comment' => 'Denormalized context ID such as award approval run ID',
            ])
            ->addIndex(['status', 'current_approver_id'], [
                'name' => 'idx_wa_status_current_approver',
            ])
            ->addIndex(['status', 'approver_lookup_type', 'approver_lookup_id'], [
                'name' => 'idx_wa_status_lookup',
            ])
            ->addIndex(['status', 'approver_lookup_type', 'approver_lookup_branch_id'], [
                'name' => 'idx_wa_status_lookup_branch',
            ])
            ->update();

        $this->table('action_items')
            ->addColumn('assignee_lookup_type', 'string', [
                'limit' => 30,
                'default' => null,
                'null' => true,
                'after' => 'assignee_config',
                'comment' => 'Denormalized assignee target type: member, role, permission, office, dynamic',
            ])
            ->addColumn('assignee_lookup_id', 'integer', [
                'default' => null,
                'null' => true,
                'after' => 'assignee_lookup_type',
                'comment' => 'Denormalized assignee source ID for member, role, permission, or office targets',
            ])
            ->addColumn('assignee_lookup_name', 'string', [
                'limit' => 255,
                'default' => null,
                'null' => true,
                'after' => 'assignee_lookup_id',
                'comment' => 'Denormalized assignee source name for role/permission configs',
            ])
            ->addColumn('assignee_lookup_branch_id', 'integer', [
                'default' => null,
                'null' => true,
                'after' => 'assignee_lookup_name',
                'comment' => 'Denormalized branch scope used for assignee eligibility lookups',
            ])
            ->addIndex(['status', 'assignee_lookup_type', 'assignee_lookup_id'], [
                'name' => 'idx_action_items_status_lookup',
            ])
            ->addIndex(['status', 'assignee_lookup_type', 'assignee_lookup_branch_id'], [
                'name' => 'idx_action_items_status_lookup_branch',
            ])
            ->update();
    }

    /**
     * Remove lookup columns and indexes.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('action_items')
            ->removeIndexByName('idx_action_items_status_lookup')
            ->removeIndexByName('idx_action_items_status_lookup_branch')
            ->removeColumn('assignee_lookup_type')
            ->removeColumn('assignee_lookup_id')
            ->removeColumn('assignee_lookup_name')
            ->removeColumn('assignee_lookup_branch_id')
            ->update();

        $this->table('workflow_approvals')
            ->removeIndexByName('idx_wa_status_current_approver')
            ->removeIndexByName('idx_wa_status_lookup')
            ->removeIndexByName('idx_wa_status_lookup_branch')
            ->removeColumn('approver_lookup_type')
            ->removeColumn('approver_lookup_id')
            ->removeColumn('approver_lookup_name')
            ->removeColumn('approver_lookup_branch_id')
            ->removeColumn('approver_lookup_branch_mode')
            ->removeColumn('approver_lookup_branch_type')
            ->removeColumn('approver_lookup_context_id')
            ->update();
    }
}
