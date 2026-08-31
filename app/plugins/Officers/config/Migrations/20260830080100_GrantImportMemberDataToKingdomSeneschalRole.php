<?php

declare(strict_types=1);

use Cake\Cache\Cache;
use Migrations\BaseMigration;

/**
 * Grant member-data import access to the role used by the Kingdom Seneschal office.
 *
 * MemberPolicy applies the final Kingdom Seneschal office-assignment check so
 * other offices that share the configured role do not receive effective access.
 */
class GrantImportMemberDataToKingdomSeneschalRole extends BaseMigration
{
    /**
     * Grant the permission to configured roles, creating a fresh-install fallback.
     *
     * @return void
     */
    public function up(): void
    {
        $permission = $this->fetchRow(
            "SELECT id FROM permissions WHERE name = 'Can Import Member Data'",
        );
        if (!$permission) {
            throw new RuntimeException('Run core migrations before Officers migrations.');
        }
        $permissionId = (int)$permission['id'];
        $roleIds = $this->configuredKingdomSeneschalRoleIds();
        if ($roleIds === []) {
            $roleIds = [$this->ensureFallbackRole()];
        }

        foreach ($roleIds as $roleId) {
            $this->grantPermission($roleId, $permissionId);
        }

        Cache::clearGroup('security');
    }

    /**
     * Remove grants for this plugin-owned permission and preserve all roles.
     *
     * @return void
     */
    public function down(): void
    {
        $permission = $this->fetchRow(
            "SELECT id FROM permissions WHERE name = 'Can Import Member Data'",
        );
        if (!$permission) {
            return;
        }

        $permissionId = (int)$permission['id'];
        $this->execute(
            "DELETE FROM roles_permissions WHERE permission_id = {$permissionId}",
        );

        Cache::clearGroup('security');
    }

    /**
     * Return active roles configured on active Kingdom Seneschal offices.
     *
     * @return array<int>
     */
    private function configuredKingdomSeneschalRoleIds(): array
    {
        $rows = $this->fetchAll(
            "SELECT DISTINCT r.id
               FROM officers_offices o
               JOIN roles r ON r.id = o.grants_role_id
              WHERE o.name = 'Kingdom Seneschal'
                AND o.deleted IS NULL
                AND r.deleted IS NULL",
        );

        return array_values(array_unique(array_map(
            static fn(array $row): int => (int)$row['id'],
            $rows,
        )));
    }

    /**
     * Return the conventional shared role, creating it for a fresh tenant.
     *
     * @return int
     */
    private function ensureFallbackRole(): int
    {
        $role = $this->fetchRow(
            "SELECT id FROM roles
              WHERE name = 'Greater Officer of State' AND deleted IS NULL",
        );
        if (!$role) {
            $deletedRole = $this->fetchRow(
                "SELECT id FROM roles WHERE name = 'Greater Officer of State'",
            );
            if ($deletedRole) {
                throw new RuntimeException(
                    'The Greater Officer of State role is deleted. Restore it or configure '
                    . 'an active Kingdom Seneschal role before running this migration.',
                );
            }

            $this->execute(
                "INSERT INTO roles
                    (name, is_system, created, modified, created_by, modified_by)
                 VALUES
                    ('Greater Officer of State', FALSE, CURRENT_TIMESTAMP,
                     CURRENT_TIMESTAMP, 1, 1)",
            );
            $role = $this->fetchRow(
                "SELECT id FROM roles
                  WHERE name = 'Greater Officer of State' AND deleted IS NULL",
            );
        }
        if (!$role) {
            throw new RuntimeException('Unable to create the Greater Officer of State role.');
        }

        return (int)$role['id'];
    }

    /**
     * Add the permission to a role when the grant is not already present.
     *
     * @param int $roleId Role receiving the permission.
     * @param int $permissionId Permission being granted.
     * @return void
     */
    private function grantPermission(int $roleId, int $permissionId): void
    {
        $existing = $this->fetchRow(
            "SELECT 1 FROM roles_permissions
             WHERE role_id = {$roleId} AND permission_id = {$permissionId}",
        );
        if ($existing) {
            return;
        }

        $this->execute(
            "INSERT INTO roles_permissions
                (role_id, permission_id, created, created_by)
             VALUES ({$roleId}, {$permissionId}, CURRENT_TIMESTAMP, 1)",
        );
    }
}
