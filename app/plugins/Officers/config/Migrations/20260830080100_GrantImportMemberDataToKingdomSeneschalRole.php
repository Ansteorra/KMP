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
    private const TRACKING_TABLE = 'officers_import_member_data_grants';

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
        $this->ensureTrackingTable();

        foreach ($roleIds as $roleId) {
            $grantId = $this->grantPermission($roleId, $permissionId);
            if ($grantId !== null) {
                $this->recordGrant($grantId);
            }
        }

        Cache::clearGroup('security');
    }

    /**
     * Remove only role grants inserted by this migration and preserve all roles.
     *
     * @return void
     */
    public function down(): void
    {
        if (!$this->hasTable(self::TRACKING_TABLE)) {
            Cache::clearGroup('security');

            return;
        }

        $trackedGrants = $this->fetchAll(
            'SELECT role_permission_id FROM ' . self::TRACKING_TABLE,
        );
        foreach ($trackedGrants as $trackedGrant) {
            $grantId = (int)$trackedGrant['role_permission_id'];
            $this->execute("DELETE FROM roles_permissions WHERE id = {$grantId}");
        }
        $this->table(self::TRACKING_TABLE)->drop()->save();

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
     * @return int|null Inserted role-permission id, or null when already granted.
     */
    private function grantPermission(int $roleId, int $permissionId): ?int
    {
        $existing = $this->fetchRow(
            "SELECT id FROM roles_permissions
             WHERE role_id = {$roleId} AND permission_id = {$permissionId}",
        );
        if ($existing) {
            return null;
        }

        $this->execute(
            "INSERT INTO roles_permissions
                (role_id, permission_id, created, created_by)
             VALUES ({$roleId}, {$permissionId}, CURRENT_TIMESTAMP, 1)",
        );

        $grant = $this->fetchRow(
            "SELECT id FROM roles_permissions
             WHERE role_id = {$roleId} AND permission_id = {$permissionId}",
        );
        if (!$grant) {
            throw new RuntimeException('Unable to record the member-data import role grant.');
        }

        return (int)$grant['id'];
    }

    /** Create the rollback bookkeeping table when needed. */
    private function ensureTrackingTable(): void
    {
        if ($this->hasTable(self::TRACKING_TABLE)) {
            return;
        }

        $this->table(self::TRACKING_TABLE, ['id' => false])
            ->addColumn('role_permission_id', 'integer', ['null' => false])
            ->addPrimaryKey(['role_permission_id'])
            ->create();
    }

    /** Record a role grant owned by this migration. */
    private function recordGrant(int $grantId): void
    {
        $this->execute(
            'INSERT INTO ' . self::TRACKING_TABLE
            . " (role_permission_id) VALUES ({$grantId})",
        );
    }
}
