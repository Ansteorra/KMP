<?php

declare(strict_types=1);

use App\Migrations\CrossEngineMigrationTrait;
use Cake\Cache\Cache;
use Migrations\BaseMigration;

/**
 * Add the narrowly scoped member-data import permission and policy mapping.
 *
 * The Officers plugin owns the role grant because its migrations run after core
 * migrations on fresh installations.
 */
class AddImportMemberDataPermission extends BaseMigration
{
    use CrossEngineMigrationTrait;

    private const POLICY_CLASS = 'App\\Policy\\MemberPolicy';

    private const POLICY_METHOD = 'canImportExpirationDates';

    private const LEGACY_PERMISSION = 'Can Manage Members';

    /**
     * Create the permission and authorization-policy mapping.
     *
     * @return void
     */
    public function up(): void
    {
        $permission = $this->fetchRow(
            "SELECT id FROM permissions WHERE name = 'Can Import Member Data'",
        );
        if (!$permission) {
            $this->execute(
                "INSERT INTO permissions
                    (name, require_active_membership, require_active_background_check,
                     require_min_age, is_system, is_super_user, requires_warrant,
                     scoping_rule, created, modified, created_by)
                 VALUES
                    ('Can Import Member Data', TRUE, FALSE, 0, TRUE, FALSE, TRUE,
                     'Global', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1)",
            );
            $permission = $this->fetchRow(
                "SELECT id FROM permissions WHERE name = 'Can Import Member Data'",
            );
        }
        if (!$permission) {
            return;
        }

        $this->insertPolicyIfMissing((int)$permission['id']);
        $this->removeLegacyPolicyMapping();
        Cache::clearGroup('security');
    }

    /**
     * Remove the mapping and the now-unreferenced permission.
     *
     * @return void
     */
    public function down(): void
    {
        $this->restoreLegacyPolicyMapping();
        $permission = $this->fetchRow(
            "SELECT id FROM permissions WHERE name = 'Can Import Member Data'",
        );
        if (!$permission) {
            Cache::clearGroup('security');

            return;
        }
        $permissionId = (int)$permission['id'];
        $policyClass = $this->sqlEscape(self::POLICY_CLASS);
        $policyMethod = $this->sqlEscape(self::POLICY_METHOD);

        $this->execute(
            "DELETE FROM permission_policies
             WHERE permission_id = {$permissionId}
               AND policy_class = '{$policyClass}'
               AND policy_method = '{$policyMethod}'",
        );

        $roleGrant = $this->fetchRow(
            "SELECT 1 FROM roles_permissions WHERE permission_id = {$permissionId}",
        );
        $otherPolicy = $this->fetchRow(
            "SELECT 1 FROM permission_policies WHERE permission_id = {$permissionId}",
        );
        if (!$roleGrant && !$otherPolicy) {
            $this->execute("DELETE FROM permissions WHERE id = {$permissionId}");
        }

        Cache::clearGroup('security');
    }

    /**
     * Remove the broad member-management mapping superseded by this permission.
     *
     * @return void
     */
    private function removeLegacyPolicyMapping(): void
    {
        $permission = $this->fetchRow(
            "SELECT id FROM permissions WHERE name = '" . self::LEGACY_PERMISSION . "'",
        );
        if (!$permission) {
            return;
        }

        $permissionId = (int)$permission['id'];
        $policyClass = $this->sqlEscape(self::POLICY_CLASS);
        $policyMethod = $this->sqlEscape(self::POLICY_METHOD);
        $this->execute(
            "DELETE FROM permission_policies
             WHERE permission_id = {$permissionId}
               AND policy_class = '{$policyClass}'
               AND policy_method = '{$policyMethod}'",
        );
    }

    /**
     * Restore the legacy mapping when this migration is rolled back.
     *
     * @return void
     */
    private function restoreLegacyPolicyMapping(): void
    {
        $permission = $this->fetchRow(
            "SELECT id FROM permissions WHERE name = '" . self::LEGACY_PERMISSION . "'",
        );
        if ($permission) {
            $this->insertPolicyIfMissing((int)$permission['id']);
        }
    }

    /**
     * Insert the policy mapping when it is not already present.
     *
     * @param int $permissionId Permission being mapped.
     * @return void
     */
    private function insertPolicyIfMissing(int $permissionId): void
    {
        $policyClass = $this->sqlEscape(self::POLICY_CLASS);
        $policyMethod = $this->sqlEscape(self::POLICY_METHOD);
        $existing = $this->fetchRow(
            "SELECT 1 FROM permission_policies
             WHERE permission_id = {$permissionId}
               AND policy_class = '{$policyClass}'
               AND policy_method = '{$policyMethod}'",
        );
        if ($existing) {
            return;
        }

        $this->execute(
            "INSERT INTO permission_policies (permission_id, policy_class, policy_method)
             VALUES ({$permissionId}, '{$policyClass}', '{$policyMethod}')",
        );
    }
}
