<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add "Can Approve Warrant Rosters" permission and assign it to roles
 * that already have "Can Manage Warrants".
 */
class AddApproveWarrantRostersPermission extends AbstractMigration
{
    public function up(): void
    {
        // Create the permission
        $this->execute(
            "INSERT INTO permissions (name, is_system, is_super_user, require_active_membership, created, modified)
             VALUES ('Can Approve Warrant Rosters', TRUE, FALSE, TRUE, NOW(), NOW())"
        );

        // Find the new permission ID
        $row = $this->fetchRow("SELECT id FROM permissions WHERE name = 'Can Approve Warrant Rosters'");
        if (!$row) {
            return;
        }
        $newPermId = $row['id'];

        // Find roles that have "Can Manage Warrants" and grant them approval too
        $manageRow = $this->fetchRow("SELECT id FROM permissions WHERE name = 'Can Manage Warrants'");
        if ($manageRow) {
            $rows = $this->fetchAll(
                "SELECT role_id FROM roles_permissions WHERE permission_id = " . (int)$manageRow['id']
            );
            foreach ($rows as $r) {
                $exists = $this->fetchRow(
                    "SELECT 1 FROM roles_permissions WHERE role_id = {$r['role_id']} AND permission_id = {$newPermId}"
                );
                if (!$exists) {
                    $this->execute(
                        "INSERT INTO roles_permissions (role_id, permission_id, created, created_by)
                         VALUES ({$r['role_id']}, {$newPermId}, CURRENT_TIMESTAMP, 1)"
                    );
                }
            }
        }

        // Map the permission to CakePHP authorization policies
        $this->insertPermissionPolicyIfMissing($newPermId, 'App\\Policy\\WarrantRosterPolicy', 'canApprove');
        $this->insertPermissionPolicyIfMissing($newPermId, 'App\\Policy\\WarrantRostersTablePolicy', 'canApprove');
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
        $row = $this->fetchRow("SELECT id FROM permissions WHERE name = 'Can Approve Warrant Rosters'");
        if ($row) {
            $this->execute("DELETE FROM permission_policies WHERE permission_id = " . (int)$row['id']);
            $this->execute("DELETE FROM roles_permissions WHERE permission_id = " . (int)$row['id']);
            $this->execute("DELETE FROM permissions WHERE id = " . (int)$row['id']);
        }
    }
}
