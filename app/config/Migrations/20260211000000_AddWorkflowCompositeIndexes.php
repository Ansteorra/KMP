<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class AddWorkflowCompositeIndexes extends BaseMigration
{
    /**
     * Add composite indexes for workflow tables to optimize common queries.
     *
     * @return void
     */
    public function up(): void
    {
        // Indexes for duplicate detection and migration queries
        $this->table('workflow_instances')
            ->addIndex(['entity_type', 'entity_id', 'status'], ['name' => 'idx_instances_entity_status'])
            ->addIndex(['workflow_definition_id', 'status'], ['name' => 'idx_instances_definition_status'])
            ->update();

        // Indexes for deadline tasks and approval lookups
        $this->table('workflow_approvals')
            ->addIndex(['status', 'deadline'], ['name' => 'idx_approvals_status_deadline'])
            ->addIndex(['workflow_instance_id', 'status'], ['name' => 'idx_approvals_instance_status'])
            ->update();

        // Index for execution history lookups
        $this->table('workflow_execution_logs')
            ->addIndex(['workflow_instance_id', 'node_id'], ['name' => 'idx_exec_logs_instance_node'])
            ->update();
    }

    /**
     * Remove composite indexes added in up().
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('workflow_instances')
            ->removeIndex(['entity_type', 'entity_id', 'status'])
            ->removeIndex(['workflow_definition_id', 'status'])
            ->update();

        $this->table('workflow_approvals')
            ->removeIndex(['status', 'deadline'])
            ->removeIndex(['workflow_instance_id', 'status'])
            ->update();

        $this->table('workflow_execution_logs')
            ->removeIndex(['workflow_instance_id', 'node_id'])
            ->update();
    }
}
