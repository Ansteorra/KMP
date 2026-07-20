<?php

declare(strict_types=1);

use Migrations\AbstractMigration;
use App\Migrations\CrossEngineMigrationTrait;

/**
 * Switch Activities authorization approvals from dynamic approver type
 * to the generic permission approver type. This eliminates the need for
 * the Activities-specific AuthorizationApproverResolver.
 */
class SwitchAuthApprovalsToPermissionType extends AbstractMigration
{
    use CrossEngineMigrationTrait;

    public function up(): void
    {
        // Skip if the Activities plugin table doesn't exist. On fresh
        // installs Activities plugin migrations run after core migrations,
        // and on clean Postgres installs there are also no dynamic approvals
        // to rewrite.
        if (!$this->tableExistsInDb('activities_activities')) {
            echo "Skipping: activities_activities table does not exist (fresh install).\n";
            return;
        }

        // 1. Update existing workflow_approvals records:
        //    Change approver_type from 'dynamic' to 'permission' and restructure approver_config
        //    to use {"permission": "<name>"} instead of {"service": "...", "method": "..."}
        $rows = $this->fetchAll(
            "SELECT wa.id, wa.approver_config, wi.entity_type
             FROM workflow_approvals wa
             JOIN workflow_instances wi ON wa.workflow_instance_id = wi.id
             WHERE wi.entity_type = 'Activities.Authorizations'
               AND wa.approver_type = 'dynamic'"
        );

        // Build activity_id → permission_name mapping
        $activities = $this->fetchAll(
            "SELECT a.id, p.name as permission_name
             FROM activities_activities a
             JOIN permissions p ON a.permission_id = p.id"
        );
        $permMap = [];
        foreach ($activities as $act) {
            $permMap[(int)$act['id']] = $act['permission_name'];
        }

        foreach ($rows as $row) {
            $config = json_decode($row['approver_config'], true) ?: [];
            $activityId = (int)($config['activity_id'] ?? 0);
            $permName = $permMap[$activityId] ?? null;

            if (!$permName) {
                continue;
            }

            // Preserve serial_pick_next, current_approver_id, exclude_member_ids, approval_chain
            $newConfig = ['permission' => $permName];
            foreach (['serial_pick_next', 'current_approver_id', 'exclude_member_ids', 'approval_chain'] as $key) {
                if (isset($config[$key])) {
                    $newConfig[$key] = $config[$key];
                }
            }

            $encoded = $this->sqlEscape(json_encode($newConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->execute(sprintf(
                "UPDATE workflow_approvals SET approver_type = 'permission', approver_config = '%s' WHERE id = %d",
                $encoded,
                (int)$row['id']
            ));
        }

        // 2. Update the published workflow definition to use permission type
        $defRows = $this->fetchAll(
            "SELECT wv.id, wv.definition
             FROM workflow_versions wv
             JOIN workflow_definitions wd ON wv.workflow_definition_id = wd.id
             WHERE wd.slug = 'activities-authorization-request'"
        );

        foreach ($defRows as $row) {
            $definition = json_decode($row['definition'], true);
            if (!$definition) {
                continue;
            }

            // Update trigger input mapping
            $trigger = null;
            foreach ($definition['nodes'] as $name => &$node) {
                if ($node['type'] === 'trigger') {
                    $node['config']['inputMapping']['approvalPermission'] = '$.event.approvalPermission';
                    break;
                }
            }
            unset($node);

            // Update approval gate
            if (isset($definition['nodes']['approval-gate'])) {
                $definition['nodes']['approval-gate']['config']['approverType'] = 'permission';
                $definition['nodes']['approval-gate']['config']['approverConfig'] = [
                    'permission' => '$.trigger.approvalPermission',
                ];
            }

            $encoded = $this->sqlEscape(json_encode($definition, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->execute(sprintf(
                "UPDATE workflow_versions SET definition = '%s' WHERE id = %d",
                $encoded,
                (int)$row['id']
            ));
        }
    }

    public function down(): void
    {
        // No rollback — the permission type is the correct generic approach
    }
}
