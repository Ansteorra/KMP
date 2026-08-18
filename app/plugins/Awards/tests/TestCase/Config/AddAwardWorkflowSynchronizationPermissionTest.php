<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Config;

use AddAwardWorkflowSynchronizationPermission;
use App\Model\Entity\Permission;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use HardenBestowalTodoSecurity;
use RuntimeException;

require_once dirname(__DIR__, 3) . '/config/Migrations/20260714203000_HardenBestowalTodoSecurity.php';
require_once dirname(__DIR__, 3)
    . '/config/Migrations/20260818120000_AddAwardWorkflowSynchronizationPermission.php';

/**
 * Verifies durable authorization for synchronizing open award workflows.
 */
class AddAwardWorkflowSynchronizationPermissionTest extends BaseTestCase
{
    public function testMigrationCreatesLeastPrivilegeMappingsForExistingAdministrators(): void
    {
        (new HardenBestowalTodoSecurity(20260714203000))->up();

        $manageAwardsRoleId = $this->createRoleWithPermission('Can Manage Awards');
        $manageTemplatesRoleId = $this->createRoleWithPermission('Can Manage Bestowal To-Do Templates');
        $migration = new AddAwardWorkflowSynchronizationPermission(20260818120000);
        $migration->up();
        $migration->up();

        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $syncPermission = $permissions->find()
            ->where(['name' => 'Can Synchronize Award Workflows'])
            ->firstOrFail();
        $this->assertSame(Permission::SCOPE_GLOBAL, $syncPermission->scoping_rule);
        $this->assertTrue($syncPermission->require_active_membership);
        $this->assertTrue($syncPermission->requires_warrant);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$syncPermission->created_by);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$syncPermission->modified_by);
        $this->assertSame([
            'Awards\\Policy\\ApprovalProcessesTablePolicy::canGridData',
            'Awards\\Policy\\ApprovalProcessesTablePolicy::canIndex',
            'Awards\\Policy\\ApprovalProcessesTablePolicy::canSyncOpenRecommendations',
            'Awards\\Policy\\BestowalTodoTemplatesTablePolicy::canGridData',
            'Awards\\Policy\\BestowalTodoTemplatesTablePolicy::canIndex',
            'Awards\\Policy\\BestowalTodoTemplatesTablePolicy::canSyncOpenBestowals',
        ], $this->policyMappings((int)$syncPermission->id));

        $this->assertRoleDoesNotHavePermission($manageAwardsRoleId, (int)$syncPermission->id);
        $this->assertRoleDoesNotHavePermission($manageTemplatesRoleId, (int)$syncPermission->id);
        $crownRole = TableRegistry::getTableLocator()->get('Roles')->find()
            ->where(['name' => 'Ansteorran Crown'])
            ->firstOrFail();
        $this->assertRoleHasPermission((int)$crownRole->id, (int)$syncPermission->id);

        $this->assertPermissionHasMapping(
            'Can Manage Awards',
            'Awards\\Policy\\ApprovalProcessesTablePolicy',
            'canSyncOpenRecommendations',
        );
        $this->assertPermissionHasMapping(
            'Can Manage Bestowal To-Do Templates',
            'Awards\\Policy\\BestowalTodoTemplatesTablePolicy',
            'canSyncOpenBestowals',
        );
    }

    public function testMigrationRollbackRefusesToDeleteAdoptedPermissionData(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $preexisting = $permissions->saveOrFail($permissions->newEntity([
            'name' => 'Can Synchronize Award Workflows',
            'scoping_rule' => Permission::SCOPE_GLOBAL,
            'require_active_membership' => true,
            'require_active_background_check' => false,
            'require_min_age' => 0,
            'is_system' => true,
            'is_super_user' => false,
            'requires_warrant' => true,
            'created' => DateTime::now(),
            'modified' => DateTime::now(),
            'created_by' => self::ADMIN_MEMBER_ID,
            'modified_by' => self::ADMIN_MEMBER_ID,
        ]));
        $migration = new AddAwardWorkflowSynchronizationPermission(20260818120000);
        $migration->up();

        try {
            $migration->down();
            $this->fail('The data migration must reject an ownership-unsafe rollback.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('irreversible', $exception->getMessage());
        }

        $this->assertTrue($permissions->exists(['id' => (int)$preexisting->id]));
    }

    public function testMigrationRestoresSoftDeletedPermissionWithoutDuplicatingItsName(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permission = $permissions->newEntity([
            'name' => 'Can Synchronize Award Workflows',
            'scoping_rule' => Permission::SCOPE_BRANCH_ONLY,
            'require_active_membership' => false,
            'require_active_background_check' => false,
            'require_min_age' => 0,
            'is_system' => false,
            'is_super_user' => false,
            'requires_warrant' => false,
        ]);
        $permission->set('created', DateTime::now());
        $permission->set('created_by', self::ADMIN_MEMBER_ID);
        $permissions->saveOrFail($permission);
        $permissionId = (int)$permission->id;
        $this->assertTrue($permissions->delete($permission));

        (new AddAwardWorkflowSynchronizationPermission(20260818120000))->up();

        $restored = $permissions->find()->where(['id' => $permissionId])->firstOrFail();
        $this->assertNull($restored->deleted);
        $this->assertSame(Permission::SCOPE_GLOBAL, $restored->scoping_rule);
        $this->assertTrue($restored->require_active_membership);
        $this->assertTrue($restored->requires_warrant);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$restored->created_by);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$restored->modified_by);
        $this->assertSame(1, $permissions->find('withTrashed')->where([
            'name' => 'Can Synchronize Award Workflows',
        ])->count());
    }

    private function createRoleWithPermission(string $permissionName): int
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permission = $permissions->find()->where(['name' => $permissionName])->firstOrFail();
        $roles = TableRegistry::getTableLocator()->get('Roles');
        $role = $roles->saveOrFail($roles->newEntity([
            'name' => 'Award Workflow Sync Migration Test ' . uniqid('', true),
        ]));
        TableRegistry::getTableLocator()->get('RolesPermissions')->saveOrFail(
            TableRegistry::getTableLocator()->get('RolesPermissions')->newEntity([
                'role_id' => (int)$role->id,
                'permission_id' => (int)$permission->id,
                'created' => DateTime::now(),
                'created_by' => self::ADMIN_MEMBER_ID,
            ]),
        );

        return (int)$role->id;
    }

    /**
     * @return list<string>
     */
    private function policyMappings(int $permissionId): array
    {
        $mappings = TableRegistry::getTableLocator()->get('PermissionPolicies')->find()
            ->where(['permission_id' => $permissionId])
            ->all()
            ->map(static fn($mapping): string => $mapping->policy_class . '::' . $mapping->policy_method)
            ->toList();
        sort($mappings);

        return $mappings;
    }

    private function assertRoleHasPermission(int $roleId, int $permissionId): void
    {
        $this->assertTrue(TableRegistry::getTableLocator()->get('RolesPermissions')->exists([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]));
    }

    private function assertRoleDoesNotHavePermission(int $roleId, int $permissionId): void
    {
        $this->assertFalse(TableRegistry::getTableLocator()->get('RolesPermissions')->exists([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ]));
    }

    private function assertPermissionHasMapping(
        string $permissionName,
        string $policyClass,
        string $policyMethod,
    ): void {
        $permission = TableRegistry::getTableLocator()->get('Permissions')->find()
            ->where(['name' => $permissionName])
            ->firstOrFail();
        $this->assertTrue(TableRegistry::getTableLocator()->get('PermissionPolicies')->exists([
            'permission_id' => (int)$permission->id,
            'policy_class' => $policyClass,
            'policy_method' => $policyMethod,
        ]));
    }
}
