<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add the "Can Publish Gatherings to Kingdom Calendar" permission.
 *
 * Deliberately NOT granted to any role by default: per the kingdom-calendar
 * design (issue #58), publishing to the public calendar is restricted to the
 * Kingdom and Calendar Deputy roles so local groups cannot publish kingdom
 * events before dates are secured. Assign the permission to those roles
 * through the role management UI.
 */
class AddPublishGatheringsPermission extends AbstractMigration
{
    public function up(): void
    {
        $exists = $this->fetchRow(
            "SELECT id FROM permissions WHERE name = 'Can Publish Gatherings to Kingdom Calendar'"
        );
        if (!$exists) {
            $this->execute(
                "INSERT INTO permissions (name, is_system, is_super_user, require_active_membership, created, modified)
                 VALUES ('Can Publish Gatherings to Kingdom Calendar', TRUE, FALSE, TRUE, NOW(), NOW())"
            );
        }

        $row = $this->fetchRow(
            "SELECT id FROM permissions WHERE name = 'Can Publish Gatherings to Kingdom Calendar'"
        );
        if (!$row) {
            return;
        }
        $permId = (int)$row['id'];

        $this->insertPermissionPolicyIfMissing($permId, 'App\\Policy\\GatheringPolicy', 'canPublish');
    }

    /**
     * Cross-engine insert-if-missing for permission_policies.
     */
    private function insertPermissionPolicyIfMissing(int $permId, string $policyClass, string $policyMethod): void
    {
        $esc = fn(string $s) => str_replace("'", "''", $s);
        $classEsc = $esc($policyClass);
        $methodEsc = $esc($policyMethod);
        $exists = $this->fetchRow(
            "SELECT 1 FROM permission_policies
             WHERE permission_id = {$permId}
               AND policy_class = '{$classEsc}'
               AND policy_method = '{$methodEsc}'"
        );
        if (!$exists) {
            $this->execute(
                "INSERT INTO permission_policies (permission_id, policy_class, policy_method)
                 VALUES ({$permId}, '{$classEsc}', '{$methodEsc}')"
            );
        }
    }

    public function down(): void
    {
        $row = $this->fetchRow(
            "SELECT id FROM permissions WHERE name = 'Can Publish Gatherings to Kingdom Calendar'"
        );
        if ($row) {
            $this->execute('DELETE FROM permission_policies WHERE permission_id = ' . (int)$row['id']);
            $this->execute('DELETE FROM roles_permissions WHERE permission_id = ' . (int)$row['id']);
            $this->execute('DELETE FROM permissions WHERE id = ' . (int)$row['id']);
        }
    }
}
