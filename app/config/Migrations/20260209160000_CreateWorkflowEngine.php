<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Create workflow engine infrastructure tables.
 *
 * Provides visual workflow design, versioned definitions, execution tracking,
 * approval gates, and instance version migration auditing.
 */
class CreateWorkflowEngine extends AbstractMigration
{
    private const TABLE_DEFINITIONS = 'workflow_definitions';
    private const TABLE_VERSIONS = 'workflow_versions';
    private const TABLE_INSTANCES = 'workflow_instances';
    private const TABLE_EXECUTION_LOGS = 'workflow_execution_logs';
    private const TABLE_APPROVALS = 'workflow_approvals';
    private const TABLE_APPROVAL_RESPONSES = 'workflow_approval_responses';
    private const TABLE_INSTANCE_MIGRATIONS = 'workflow_instance_migrations';

    public function up(): void
    {
        // Archive old state-machine workflow tables if they exist
        $oldTables = [
            'workflow_approval_gates',
            'workflow_approvals',
            'workflow_transition_logs',
            'workflow_visibility_rules',
            'workflow_transitions',
            'workflow_instances',
            'workflow_states',
            'workflow_definitions',
        ];
        foreach ($oldTables as $table) {
            if ($this->hasTable($table)) {
                $legacyName = $table . '_legacy';
                if ($this->hasTable($legacyName)) {
                    $this->table($legacyName)->drop()->save();
                }
                $this->table($table)->rename($legacyName)->update();
            }
        }

        // 1. Workflow definitions — blueprint/template for workflows
        $this->table(self::TABLE_DEFINITIONS)
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Human-readable workflow name',
            ])
            ->addColumn('slug', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'URL-safe unique identifier for the workflow',
            ])
            ->addColumn('description', 'text', [
                'null' => true,
                'comment' => 'Detailed description of the workflow purpose',
            ])
            ->addColumn('trigger_type', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'event',
                'comment' => 'How the workflow is initiated: event, manual, scheduled, api',
            ])
            ->addColumn('trigger_config', 'json', [
                'null' => true,
                'comment' => 'Trigger configuration: event name, cron schedule, etc.',
            ])
            ->addColumn('entity_type', 'string', [
                'limit' => 100,
                'null' => true,
                'comment' => 'Primary entity type this workflow operates on (e.g. Officers)',
            ])
            ->addColumn('is_active', 'boolean', [
                'default' => false,
                'null' => false,
                'comment' => 'Whether this workflow is currently active and can be triggered',
            ])
            ->addColumn('current_version_id', 'integer', [
                'null' => true,
                'comment' => 'FK to workflow_versions — the currently published version',
            ])
            ->addColumn('created_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('modified_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
            ])
            ->addColumn('deleted', 'datetime', [
                'null' => true,
                'comment' => 'Soft delete timestamp',
            ])
            ->addIndex(['slug'], [
                'unique' => true,
                'name' => 'idx_wf_definitions_slug',
            ])
            ->addIndex(['is_active'], [
                'name' => 'idx_wf_definitions_active',
            ])
            ->addIndex(['entity_type'], [
                'name' => 'idx_wf_definitions_entity_type',
            ])
            ->addIndex(['deleted'], [
                'name' => 'idx_wf_definitions_deleted',
            ])
            ->addForeignKey('created_by', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_definitions_created_by',
            ])
            ->addForeignKey('modified_by', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_definitions_modified_by',
            ])
            ->create();

        // 2. Workflow versions — immutable versioned snapshots of workflow designs
        $this->table(self::TABLE_VERSIONS)
            ->addColumn('workflow_definition_id', 'integer', [
                'null' => false,
                'comment' => 'FK to workflow_definitions',
            ])
            ->addColumn('version_number', 'integer', [
                'null' => false,
                'comment' => 'Sequential version number within a workflow definition',
            ])
            ->addColumn('definition', 'json', [
                'null' => false,
                'comment' => 'Full workflow graph: nodes and connections',
            ])
            ->addColumn('canvas_layout', 'json', [
                'null' => true,
                'comment' => 'Drawflow visual positions, separate from execution logic',
            ])
            ->addColumn('status', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'draft',
                'comment' => 'Version lifecycle status: draft, published, archived',
            ])
            ->addColumn('published_at', 'datetime', [
                'null' => true,
                'comment' => 'Timestamp when this version was published',
            ])
            ->addColumn('published_by', 'integer', [
                'null' => true,
                'comment' => 'Member who published this version',
            ])
            ->addColumn('change_notes', 'text', [
                'null' => true,
                'comment' => 'Description of changes in this version',
            ])
            ->addColumn('created_by', 'integer', [
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['workflow_definition_id', 'version_number'], [
                'unique' => true,
                'name' => 'idx_wf_versions_def_version',
            ])
            ->addIndex(['status'], [
                'name' => 'idx_wf_versions_status',
            ])
            ->addForeignKey('workflow_definition_id', self::TABLE_DEFINITIONS, 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_versions_definition',
            ])
            ->addForeignKey('published_by', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_versions_published_by',
            ])
            ->addForeignKey('created_by', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_versions_created_by',
            ])
            ->create();

        // Now add the FK from workflow_definitions.current_version_id to workflow_versions
        $this->table(self::TABLE_DEFINITIONS)
            ->addForeignKey('current_version_id', self::TABLE_VERSIONS, 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_definitions_current_version',
            ])
            ->update();

        // 3. Workflow instances — running/completed workflow executions
        $this->table(self::TABLE_INSTANCES)
            ->addColumn('workflow_definition_id', 'integer', [
                'null' => false,
                'comment' => 'FK to workflow_definitions',
            ])
            ->addColumn('workflow_version_id', 'integer', [
                'null' => false,
                'comment' => 'FK to workflow_versions — pinned version for this execution',
            ])
            ->addColumn('entity_type', 'string', [
                'limit' => 100,
                'null' => true,
                'comment' => 'Entity type being processed (e.g. Officers, WarrantRosters)',
            ])
            ->addColumn('entity_id', 'integer', [
                'null' => true,
                'comment' => 'ID of the entity instance being processed',
            ])
            ->addColumn('status', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'pending',
                'comment' => 'Execution status: pending, running, waiting, completed, failed, cancelled',
            ])
            ->addColumn('context', 'json', [
                'null' => true,
                'comment' => 'Accumulated workflow variables and data',
            ])
            ->addColumn('active_nodes', 'json', [
                'null' => true,
                'comment' => 'Array of currently executing node IDs for parallel support',
            ])
            ->addColumn('error_info', 'json', [
                'null' => true,
                'comment' => 'Error details if the workflow failed',
            ])
            ->addColumn('started_by', 'integer', [
                'null' => true,
                'comment' => 'Member who initiated this workflow execution',
            ])
            ->addColumn('started_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('completed_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['workflow_definition_id'], [
                'name' => 'idx_wf_instances_definition',
            ])
            ->addIndex(['workflow_version_id'], [
                'name' => 'idx_wf_instances_version',
            ])
            ->addIndex(['status'], [
                'name' => 'idx_wf_instances_status',
            ])
            ->addIndex(['entity_type', 'entity_id'], [
                'name' => 'idx_wf_instances_entity',
            ])
            ->addIndex(['started_by'], [
                'name' => 'idx_wf_instances_started_by',
            ])
            ->addForeignKey('workflow_definition_id', self::TABLE_DEFINITIONS, 'id', [
                'delete' => 'NO_ACTION',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_instances_definition',
            ])
            ->addForeignKey('workflow_version_id', self::TABLE_VERSIONS, 'id', [
                'delete' => 'NO_ACTION',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_instances_version',
            ])
            ->addForeignKey('started_by', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_instances_started_by',
            ])
            ->create();

        // 4. Workflow execution logs — per-node execution records
        $this->table(self::TABLE_EXECUTION_LOGS)
            ->addColumn('workflow_instance_id', 'integer', [
                'null' => false,
                'comment' => 'FK to workflow_instances',
            ])
            ->addColumn('node_id', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'References node key in the workflow definition JSON',
            ])
            ->addColumn('node_type', 'string', [
                'limit' => 50,
                'null' => false,
                'comment' => 'Type of node: action, condition, approval, etc.',
            ])
            ->addColumn('attempt_number', 'integer', [
                'null' => false,
                'default' => 1,
                'comment' => 'Retry attempt number for this node execution',
            ])
            ->addColumn('status', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'pending',
                'comment' => 'Node execution status: pending, running, completed, failed, skipped, waiting',
            ])
            ->addColumn('input_data', 'json', [
                'null' => true,
                'comment' => 'Data passed into this node',
            ])
            ->addColumn('output_data', 'json', [
                'null' => true,
                'comment' => 'Data produced by this node',
            ])
            ->addColumn('error_message', 'text', [
                'null' => true,
                'comment' => 'Error message if the node execution failed',
            ])
            ->addColumn('started_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('completed_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['workflow_instance_id'], [
                'name' => 'idx_wf_exec_logs_instance',
            ])
            ->addIndex(['node_id'], [
                'name' => 'idx_wf_exec_logs_node',
            ])
            ->addIndex(['status'], [
                'name' => 'idx_wf_exec_logs_status',
            ])
            ->addIndex(['workflow_instance_id', 'node_id'], [
                'name' => 'idx_wf_exec_logs_instance_node',
            ])
            ->addForeignKey('workflow_instance_id', self::TABLE_INSTANCES, 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_exec_logs_instance',
            ])
            ->create();

        // 5. Workflow approvals — approval gates within running instances
        $this->table(self::TABLE_APPROVALS)
            ->addColumn('workflow_instance_id', 'integer', [
                'null' => false,
                'comment' => 'FK to workflow_instances',
            ])
            ->addColumn('node_id', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'References node key in the workflow definition JSON',
            ])
            ->addColumn('execution_log_id', 'integer', [
                'null' => false,
                'comment' => 'FK to workflow_execution_logs for this approval step',
            ])
            ->addColumn('approver_type', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'permission',
                'comment' => 'How approvers are resolved: permission, role, member, dynamic',
            ])
            ->addColumn('approver_config', 'json', [
                'null' => true,
                'comment' => 'Approver resolution config, e.g. {permission: "can_approve_warrants"}',
            ])
            ->addColumn('required_count', 'integer', [
                'null' => false,
                'default' => 1,
                'comment' => 'Number of approvals required to pass this gate',
            ])
            ->addColumn('approved_count', 'integer', [
                'null' => false,
                'default' => 0,
                'comment' => 'Current number of approvals received',
            ])
            ->addColumn('rejected_count', 'integer', [
                'null' => false,
                'default' => 0,
                'comment' => 'Current number of rejections received',
            ])
            ->addColumn('status', 'string', [
                'limit' => 20,
                'null' => false,
                'default' => 'pending',
                'comment' => 'Approval gate status: pending, approved, rejected, expired, cancelled',
            ])
            ->addColumn('allow_parallel', 'boolean', [
                'default' => true,
                'null' => false,
                'comment' => 'Whether multiple approvers can review simultaneously',
            ])
            ->addColumn('deadline', 'datetime', [
                'null' => true,
                'comment' => 'Deadline for approval responses before escalation',
            ])
            ->addColumn('escalation_config', 'json', [
                'null' => true,
                'comment' => 'Escalation rules when deadline is exceeded',
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['workflow_instance_id'], [
                'name' => 'idx_wf_approvals_instance',
            ])
            ->addIndex(['status'], [
                'name' => 'idx_wf_approvals_status',
            ])
            ->addIndex(['execution_log_id'], [
                'name' => 'idx_wf_approvals_exec_log',
            ])
            ->addForeignKey('workflow_instance_id', self::TABLE_INSTANCES, 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_approvals_instance',
            ])
            ->addForeignKey('execution_log_id', self::TABLE_EXECUTION_LOGS, 'id', [
                'delete' => 'NO_ACTION',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_approvals_exec_log',
            ])
            ->create();

        // 6. Workflow approval responses — individual approval decisions
        $this->table(self::TABLE_APPROVAL_RESPONSES)
            ->addColumn('workflow_approval_id', 'integer', [
                'null' => false,
                'comment' => 'FK to workflow_approvals',
            ])
            ->addColumn('member_id', 'integer', [
                'null' => false,
                'comment' => 'FK to members — the member who responded',
            ])
            ->addColumn('decision', 'string', [
                'limit' => 20,
                'null' => false,
                'comment' => 'Decision: approve, reject, abstain, request_changes',
            ])
            ->addColumn('comment', 'text', [
                'null' => true,
                'comment' => 'Optional comment explaining the decision',
            ])
            ->addColumn('responded_at', 'datetime', [
                'null' => false,
                'comment' => 'When the response was submitted',
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['workflow_approval_id'], [
                'name' => 'idx_wf_approval_resp_approval',
            ])
            ->addIndex(['member_id'], [
                'name' => 'idx_wf_approval_resp_member',
            ])
            ->addIndex(['workflow_approval_id', 'member_id'], [
                'unique' => true,
                'name' => 'idx_wf_approval_resp_unique',
            ])
            ->addForeignKey('workflow_approval_id', self::TABLE_APPROVALS, 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_approval_resp_approval',
            ])
            ->addForeignKey('member_id', 'members', 'id', [
                'delete' => 'NO_ACTION',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_approval_resp_member',
            ])
            ->create();

        // 7. Workflow instance migrations — audit trail for version migrations
        $this->table(self::TABLE_INSTANCE_MIGRATIONS)
            ->addColumn('workflow_instance_id', 'integer', [
                'null' => false,
                'comment' => 'FK to workflow_instances',
            ])
            ->addColumn('from_version_id', 'integer', [
                'null' => false,
                'comment' => 'FK to workflow_versions — version before migration',
            ])
            ->addColumn('to_version_id', 'integer', [
                'null' => false,
                'comment' => 'FK to workflow_versions — version after migration',
            ])
            ->addColumn('migration_type', 'string', [
                'limit' => 20,
                'null' => false,
                'comment' => 'How the migration was triggered: automatic, manual, admin',
            ])
            ->addColumn('node_mapping', 'json', [
                'null' => true,
                'comment' => 'How nodes were mapped between versions',
            ])
            ->addColumn('migrated_by', 'integer', [
                'null' => true,
                'comment' => 'Member who triggered the migration (null for automatic)',
            ])
            ->addColumn('created', 'datetime', [
                'null' => true,
            ])
            ->addIndex(['workflow_instance_id'], [
                'name' => 'idx_wf_inst_migrations_instance',
            ])
            ->addForeignKey('workflow_instance_id', self::TABLE_INSTANCES, 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_inst_migrations_instance',
            ])
            ->addForeignKey('from_version_id', self::TABLE_VERSIONS, 'id', [
                'delete' => 'NO_ACTION',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_inst_migrations_from_ver',
            ])
            ->addForeignKey('to_version_id', self::TABLE_VERSIONS, 'id', [
                'delete' => 'NO_ACTION',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_inst_migrations_to_ver',
            ])
            ->addForeignKey('migrated_by', 'members', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION',
                'constraint' => 'fk_wf_inst_migrations_migrated_by',
            ])
            ->create();
    }

    public function down(): void
    {
        // Drop in reverse order to respect FK constraints
        $this->table(self::TABLE_INSTANCE_MIGRATIONS)->drop()->save();
        $this->table(self::TABLE_APPROVAL_RESPONSES)->drop()->save();
        $this->table(self::TABLE_APPROVALS)->drop()->save();
        $this->table(self::TABLE_EXECUTION_LOGS)->drop()->save();
        $this->table(self::TABLE_INSTANCES)->drop()->save();

        // Remove the FK from definitions to versions before dropping versions
        $this->table(self::TABLE_DEFINITIONS)
            ->dropForeignKey('current_version_id')
            ->update();

        $this->table(self::TABLE_VERSIONS)->drop()->save();
        $this->table(self::TABLE_DEFINITIONS)->drop()->save();

        // Restore old tables from legacy backups
        $legacyTables = [
            'workflow_definitions_legacy' => 'workflow_definitions',
            'workflow_states_legacy' => 'workflow_states',
            'workflow_instances_legacy' => 'workflow_instances',
            'workflow_transitions_legacy' => 'workflow_transitions',
            'workflow_visibility_rules_legacy' => 'workflow_visibility_rules',
            'workflow_transition_logs_legacy' => 'workflow_transition_logs',
            'workflow_approvals_legacy' => 'workflow_approvals',
            'workflow_approval_gates_legacy' => 'workflow_approval_gates',
        ];
        foreach ($legacyTables as $legacyName => $originalName) {
            if ($this->hasTable($legacyName)) {
                $this->table($legacyName)->rename($originalName)->update();
            }
        }
    }
}
