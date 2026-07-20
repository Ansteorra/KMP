<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Entity\Permission;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;

/**
 * Branchless grants of roles with branch-scoped permissions are inert
 * (PermissionsLoader resolves those permissions through the assignment
 * branch), so creating one must be rejected while legacy rows stay editable.
 */
class MemberRolesBranchScopeRuleTest extends BaseTestCase
{
    public function testBranchlessGrantOfBranchScopedRoleIsRejected(): void
    {
        $roleId = $this->createRoleWithPermissionScope(Permission::SCOPE_BRANCH_ONLY);
        $memberRoles = $this->getTableLocator()->get('MemberRoles');

        $grant = $memberRoles->newEntity($this->grantData($roleId, null));

        $this->assertFalse($memberRoles->save($grant));
        $this->assertArrayHasKey('branchRequiredForScopedRole', $grant->getError('branch_id'));
    }

    public function testBranchScopedRoleGrantWithBranchSaves(): void
    {
        $roleId = $this->createRoleWithPermissionScope(Permission::SCOPE_BRANCH_AND_CHILDREN);
        $memberRoles = $this->getTableLocator()->get('MemberRoles');

        $grant = $memberRoles->newEntity($this->grantData($roleId, self::KINGDOM_BRANCH_ID));

        $this->assertNotFalse($memberRoles->save($grant));
    }

    public function testGlobalOnlyRoleAllowsBranchlessGrant(): void
    {
        $roleId = $this->createRoleWithPermissionScope(Permission::SCOPE_GLOBAL);
        $memberRoles = $this->getTableLocator()->get('MemberRoles');

        $grant = $memberRoles->newEntity($this->grantData($roleId, null));

        $this->assertNotFalse($memberRoles->save($grant));
    }

    public function testLegacyBranchlessGrantStaysEditable(): void
    {
        $roleId = $this->createRoleWithPermissionScope(Permission::SCOPE_BRANCH_ONLY);
        $memberRoles = $this->getTableLocator()->get('MemberRoles');

        // Simulate a legacy inert row created before the rule existed.
        $legacy = $memberRoles->newEntity($this->grantData($roleId, self::KINGDOM_BRANCH_ID));
        $legacy = $memberRoles->saveOrFail($legacy);
        $memberRoles->updateAll(['branch_id' => null], ['id' => $legacy->id]);
        $legacy = $memberRoles->get($legacy->id);

        // Expiring it (no role/branch change) must still save.
        $legacy->expires_on = DateTime::now();
        $this->assertNotFalse($memberRoles->save($legacy));
    }

    private function createRoleWithPermissionScope(string $scopingRule): int
    {
        $permissions = $this->getTableLocator()->get('Permissions');
        $permission = $permissions->saveOrFail($permissions->newEntity([
            'name' => 'Scope Rule Test Permission ' . uniqid('', true),
            'scoping_rule' => $scopingRule,
            'require_active_membership' => false,
            'require_active_background_check' => false,
            'require_min_age' => 0,
            'is_system' => false,
            'is_super_user' => false,
            'requires_warrant' => false,
        ]));

        $roles = $this->getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity([
            'name' => 'Scope Rule Test Role ' . uniqid('', true),
        ]));

        $junction = $this->getTableLocator()->get('RolesPermissions');
        $junction->saveOrFail($junction->newEntity([
            'role_id' => (int)$role->id,
            'permission_id' => (int)$permission->id,
            'created_by' => self::ADMIN_MEMBER_ID,
        ]));

        return (int)$role->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function grantData(int $roleId, ?int $branchId): array
    {
        return [
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'role_id' => $roleId,
            'branch_id' => $branchId,
            'start_on' => DateTime::now(),
            'approver_id' => self::ADMIN_MEMBER_ID,
        ];
    }
}
