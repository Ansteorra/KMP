<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateWorkflowApprovalTriageStates extends BaseMigration
{
    /**
     * Create private per-approver workflow approval triage state.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('workflow_approval_triage_states')
            ->addColumn('workflow_approval_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('member_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('state', 'string', [
                'default' => 'new',
                'limit' => 40,
                'null' => false,
            ])
            ->addColumn('note', 'text', [
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
            ->addIndex(['workflow_approval_id', 'member_id'], [
                'unique' => true,
                'name' => 'idx_wf_approval_triage_approval_member',
            ])
            ->addIndex(['member_id', 'state'], [
                'name' => 'idx_wf_approval_triage_member_state',
            ])
            ->addForeignKey('workflow_approval_id', 'workflow_approvals', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_approval_triage_approval',
            ])
            ->addForeignKey('member_id', 'members', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_approval_triage_member',
            ])
            ->addForeignKey('created_by', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_approval_triage_created_by',
            ])
            ->addForeignKey('modified_by', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_approval_triage_modified_by',
            ])
            ->create();
    }

    /**
     * Drop private per-approver triage state.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('workflow_approval_triage_states')->drop()->save();
    }
}
