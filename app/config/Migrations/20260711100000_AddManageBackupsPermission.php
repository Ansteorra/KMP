<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Add the "Can Manage Backups" permission so tenant backup self-service
 * (list, request, download) is grantable to roles instead of being an
 * accidental super-user-only surface.
 */
class AddManageBackupsPermission extends AbstractMigration
{
    private const POLICY_METHODS = [
        'canIndex',
        'canCreate',
        'canDownload',
        'canDownloadRecoveryKey',
        'canLegacyDownload',
    ];

    public function up(): void
    {
        $this->execute(
            "INSERT INTO permissions (name, is_system, is_super_user, require_active_membership, created, modified)
             VALUES ('Can Manage Backups', TRUE, FALSE, TRUE, NOW(), NOW())"
        );

        $row = $this->fetchRow("SELECT id FROM permissions WHERE name = 'Can Manage Backups'");
        if (!$row) {
            return;
        }
        $permId = (int)$row['id'];

        foreach (self::POLICY_METHODS as $method) {
            $this->insertPermissionPolicyIfMissing($permId, 'App\\Policy\\BackupsTablePolicy', $method);
        }
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
        $row = $this->fetchRow("SELECT id FROM permissions WHERE name = 'Can Manage Backups'");
        if ($row) {
            $this->execute("DELETE FROM permission_policies WHERE permission_id = " . (int)$row['id']);
            $this->execute("DELETE FROM roles_permissions WHERE permission_id = " . (int)$row['id']);
            $this->execute("DELETE FROM permissions WHERE id = " . (int)$row['id']);
        }
    }
}
