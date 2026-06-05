<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class CreateAwardApprovalProcesses extends BaseMigration
{
    /**
     * Create approval process configuration tables.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('awards_approval_processes')
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('description', 'text', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('is_active', 'boolean', [
                'default' => true,
                'null' => false,
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
            ->addIndex(['name'], [
                'unique' => true,
                'name' => 'idx_awards_approval_processes_name',
            ])
            ->addIndex(['is_active'], [
                'name' => 'idx_awards_approval_processes_is_active',
            ])
            ->addIndex(['deleted'], [
                'name' => 'idx_awards_approval_processes_deleted',
            ])
            ->create();

        $this->table('awards_approval_process_steps')
            ->addColumn('approval_process_id', 'integer', [
                'null' => false,
            ])
            ->addColumn('step_key', 'string', [
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('label', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('sequence', 'integer', [
                'default' => 1,
                'null' => false,
            ])
            ->addColumn('step_type', 'string', [
                'default' => 'approval',
                'limit' => 30,
                'null' => false,
            ])
            ->addColumn('approver_type', 'string', [
                'limit' => 30,
                'null' => false,
            ])
            ->addColumn('approver_source_id', 'integer', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('approver_source_key', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => true,
            ])
            ->addColumn('branch_mode', 'string', [
                'default' => 'award_branch',
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('branch_type', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('threshold_mode', 'string', [
                'default' => 'any',
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('required_count', 'integer', [
                'default' => null,
                'null' => true,
            ])
            ->addColumn('on_reject', 'string', [
                'default' => 'return_previous',
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('on_request_changes', 'string', [
                'default' => 'return_previous',
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('retain_read_visibility', 'boolean', [
                'default' => true,
                'null' => false,
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
            ->addForeignKey('approval_process_id', 'awards_approval_processes', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_awards_process_steps_process',
            ])
            ->addIndex(['approval_process_id', 'step_key'], [
                'unique' => true,
                'name' => 'idx_awards_process_steps_key',
            ])
            ->addIndex(['approval_process_id', 'sequence'], [
                'name' => 'idx_awards_process_steps_sequence',
            ])
            ->create();

        $this->table('awards_awards')
            ->addColumn('approval_process_id', 'integer', [
                'after' => 'branch_id',
                'default' => null,
                'null' => true,
            ])
            ->addForeignKey('approval_process_id', 'awards_approval_processes', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_awards_awards_approval_process',
            ])
            ->addIndex(['approval_process_id'], [
                'name' => 'idx_awards_awards_approval_process',
            ])
            ->update();
    }

    /**
     * Drop approval process configuration tables.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('awards_awards')
            ->dropForeignKey('approval_process_id')
            ->removeIndexByName('idx_awards_awards_approval_process')
            ->removeColumn('approval_process_id')
            ->update();

        $this->table('awards_approval_process_steps')->drop()->save();
        $this->table('awards_approval_processes')->drop()->save();
    }
}
