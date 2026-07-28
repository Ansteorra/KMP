<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class ExpandCaseInsensitiveHumanText extends BaseMigration
{
    private const COLUMNS = [
        'action_item_logs' => [
            'from_status' => 'varchar(20)',
            'to_status' => 'varchar(20)',
            'note' => 'text',
        ],
        'action_items' => [
            'title' => 'varchar(255)',
            'description' => 'text',
            'assignee_type' => 'varchar(30)',
            'status' => 'varchar(20)',
            'assignee_lookup_type' => 'varchar(30)',
            'assignee_lookup_name' => 'varchar(255)',
        ],
        'app_settings' => [
            'type' => 'varchar(255)',
        ],
        'backups' => [
            'notes' => 'text',
            'status' => 'varchar(20)',
            'storage_type' => 'varchar(20)',
        ],
        'branches' => [
            'location' => 'varchar(128)',
            'domain' => 'varchar(255)',
            'type' => 'varchar(255)',
        ],
        'documents' => [
            'mime_type' => 'varchar(100)',
        ],
        'email_templates' => [
            'name' => 'varchar(255)',
            'description' => 'text',
        ],
        'gathering_activities' => [
            'description' => 'text',
        ],
        'gathering_attendances' => [
            'progress_branch_name' => 'varchar(255)',
            'progress_office_name' => 'varchar(255)',
            'public_note' => 'text',
        ],
        'gathering_scheduled_activities' => [
            'display_title' => 'varchar(255)',
            'description' => 'text',
        ],
        'gathering_staff' => [
            'email' => 'varchar(255)',
            'phone' => 'varchar(50)',
            'contact_notes' => 'text',
        ],
        'gathering_types' => [
            'description' => 'text',
            'color' => 'varchar(7)',
        ],
        'gatherings' => [
            'location' => 'varchar(255)',
            'description' => 'text',
            'cancellation_reason' => 'text',
        ],
        'gatherings_gathering_activities' => [
            'custom_description' => 'text',
        ],
        'grid_views' => [
            'name' => 'varchar(100)',
        ],
        'impersonation_action_logs' => [
            'operation' => 'varchar(20)',
            'ip_address' => 'varchar(45)',
        ],
        'impersonation_session_logs' => [
            'event' => 'varchar(16)',
            'ip_address' => 'varchar(45)',
        ],
        'member_quick_login_devices' => [
            'configured_browser' => 'varchar(120)',
            'configured_ip_address' => 'varchar(45)',
            'configured_location_hint' => 'varchar(120)',
            'configured_os' => 'varchar(120)',
            'last_used_ip_address' => 'varchar(45)',
            'last_used_location_hint' => 'varchar(120)',
        ],
        'members' => [
            'street_address' => 'varchar(75)',
            'city' => 'varchar(30)',
            'state' => 'varchar(2)',
            'zip' => 'varchar(5)',
            'phone_number' => 'varchar(15)',
            'status' => 'varchar(20)',
        ],
        'notes' => [
            'body' => 'text',
        ],
        'permissions' => [
            'scoping_rule' => 'varchar(255)',
        ],
        'service_principal_audit_logs' => [
            'action' => 'varchar(50)',
            'ip_address' => 'varchar(45)',
            'request_summary' => 'text',
        ],
        'service_principal_tokens' => [
            'name' => 'varchar(100)',
        ],
        'service_principals' => [
            'name' => 'varchar(255)',
            'description' => 'text',
        ],
        'warrant_rosters' => [
            'name' => 'varchar(255)',
            'status' => 'varchar(20)',
        ],
        'warrants' => [
            'name' => 'varchar(255)',
            'status' => 'varchar(20)',
            'revoked_reason' => 'varchar(255)',
        ],
        'workflow_approval_responses' => [
            'comment' => 'text',
            'decision' => 'varchar(20)',
        ],
        'workflow_approval_triage_states' => [
            'state' => 'varchar(40)',
            'note' => 'text',
        ],
        'workflow_approvals' => [
            'approver_lookup_branch_mode' => 'varchar(50)',
            'approver_lookup_branch_type' => 'varchar(50)',
            'approver_lookup_name' => 'varchar(255)',
            'approver_lookup_type' => 'varchar(30)',
            'approver_type' => 'varchar(20)',
            'request_title' => 'varchar(255)',
            'status' => 'varchar(20)',
        ],
        'workflow_definitions' => [
            'description' => 'text',
            'execution_mode' => 'varchar(20)',
            'name' => 'varchar(255)',
            'trigger_type' => 'varchar(20)',
        ],
        'workflow_execution_logs' => [
            'error_message' => 'text',
            'node_type' => 'varchar(50)',
            'status' => 'varchar(20)',
        ],
        'workflow_instance_migrations' => [
            'migration_type' => 'varchar(20)',
        ],
        'workflow_instances' => [
            'status' => 'varchar(20)',
        ],
        'workflow_tasks' => [
            'assigned_by_role' => 'varchar(255)',
            'status' => 'varchar(20)',
            'task_title' => 'varchar(255)',
        ],
        'workflow_versions' => [
            'change_notes' => 'text',
            'status' => 'varchar(20)',
        ],
    ];

    /**
     * Convert non-key human and lifecycle text to citext.
     */
    public function up(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        $this->execute('CREATE EXTENSION IF NOT EXISTS citext');
        foreach (self::COLUMNS as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                $this->execute(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE citext USING "%s"::citext',
                    $table,
                    $column,
                    $column,
                ));
            }
        }
    }

    /**
     * Restore original varchar and text types.
     */
    public function down(): void
    {
        if (!$this->isPostgres()) {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column => $type) {
                $this->execute(sprintf(
                    'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE %s USING "%s"::text',
                    $table,
                    $column,
                    $type,
                    $column,
                ));
            }
        }
    }

    /**
     * Check whether the active migration adapter is PostgreSQL.
     */
    private function isPostgres(): bool
    {
        return $this->getAdapter()->getAdapterType() === 'pgsql';
    }
}
