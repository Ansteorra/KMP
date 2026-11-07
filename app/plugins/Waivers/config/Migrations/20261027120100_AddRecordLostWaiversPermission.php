<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Migrations\BaseMigration;

class AddRecordLostWaiversPermission extends BaseMigration
{
    /** Register the permission and policy; administrators choose which roles receive it. */
    public function up(): void
    {
        $this->execute(<<<'SQL'
            INSERT INTO permissions
                (name, is_system, is_super_user, require_active_membership, scoping_rule, created, modified)
            SELECT 'Can Record Lost Waivers', TRUE, FALSE, TRUE, 'Branch and Children', NOW(), NOW()
            WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE name = 'Can Record Lost Waivers')
            SQL);
        $this->execute(<<<'SQL'
            INSERT INTO permission_policies (permission_id, policy_class, policy_method)
            SELECT p.id, 'Waivers\Policy\GatheringWaiverPolicy', 'canRecordLost'
            FROM permissions p WHERE p.name = 'Can Record Lost Waivers'
                AND NOT EXISTS (
                    SELECT 1 FROM permission_policies WHERE permission_id = p.id
                        AND policy_class = 'Waivers\Policy\GatheringWaiverPolicy' AND policy_method = 'canRecordLost'
                )
            SQL);
        Cache::clearGroup('security');
    }

    /** Remove the dedicated permission and its role bindings. */
    public function down(): void
    {
        $permission = $this->fetchRow("SELECT id FROM permissions WHERE name = 'Can Record Lost Waivers'");
        if ($permission) {
            $permissionId = (int)$permission['id'];
            $this->execute('DELETE FROM permission_policies WHERE permission_id = ' . $permissionId);
            $this->execute('DELETE FROM roles_permissions WHERE permission_id = ' . $permissionId);
            $this->execute('DELETE FROM permissions WHERE id = ' . $permissionId);
            Cache::clearGroup('security');
        }
    }
}
