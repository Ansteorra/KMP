<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Config;

use App\Model\Entity\Permission;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\TableRegistry;
use HardenBestowalTodoSecurity;

require_once dirname(__DIR__, 3) . '/config/Migrations/20260714203000_HardenBestowalTodoSecurity.php';

/**
 * Verifies the durable bestowal To-Do authorization configuration.
 */
class HardenBestowalTodoSecurityTest extends BaseTestCase
{
    /**
     * @return void
     */
    public function testMigrationCreatesLeastPrivilegePermissionAndRoleConfiguration(): void
    {
        $migration = new HardenBestowalTodoSecurity(20260714203000);
        $migration->up();
        $migration->up();

        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        foreach (['Crown', 'Principality', 'Baronial'] as $tier) {
            $expectedScope = $tier === 'Baronial'
                ? Permission::SCOPE_BRANCH_AND_CHILDREN
                : Permission::SCOPE_BRANCH_ONLY;
            foreach (
                [
                'Scroll Management',
                'Regalia Management',
                'Award Schedule Management',
                'Court Management',
                'Court Reporter',
                ] as $suffix
            ) {
                $permission = $permissions->find()
                    ->where(['name' => $tier . ' ' . $suffix])
                    ->firstOrFail();
                $this->assertSame($expectedScope, $permission->scoping_rule);
                $this->assertTrue($permission->require_active_membership);
                $this->assertTrue($permission->requires_warrant);
            }
        }

        $this->assertPolicyMappings('Crown Regalia Management', [
            'Awards\\Policy\\BestowalPolicy::canGatheringBestowalsGridData',
            'Awards\\Policy\\BestowalPolicy::canIndex',
            'Awards\\Policy\\BestowalPolicy::canView',
            'Awards\\Policy\\BestowalPolicy::canViewGatheringBestowals',
            'Awards\\Policy\\BestowalsTablePolicy::canExport',
            'Awards\\Policy\\BestowalsTablePolicy::canIndex',
        ]);
        $this->assertPolicyMappings('Principality Award Schedule Management', [
            'Awards\\Policy\\BestowalPolicy::canBulkAssignGathering',
            'Awards\\Policy\\BestowalPolicy::canGatheringBestowalsGridData',
            'Awards\\Policy\\BestowalPolicy::canGatheringsForBestowalAutoComplete',
            'Awards\\Policy\\BestowalPolicy::canGatheringsForBestowalBulkAutoComplete',
            'Awards\\Policy\\BestowalPolicy::canIndex',
            'Awards\\Policy\\BestowalPolicy::canManageCourtSchedule',
            'Awards\\Policy\\BestowalPolicy::canView',
            'Awards\\Policy\\BestowalPolicy::canViewGatheringBestowals',
            'Awards\\Policy\\BestowalsTablePolicy::canExport',
            'Awards\\Policy\\BestowalsTablePolicy::canIndex',
        ]);
        $this->assertContains(
            'Awards\\Policy\\CourtAgendaPolicy::canRemoveItem',
            $this->policyMappings('Baronial Court Management'),
        );
        $this->assertContains(
            'Awards\\Policy\\CourtAgendaPolicy::canPrintAgenda',
            $this->policyMappings('Crown Court Reporter'),
        );

        $this->assertPermissionMetadata(
            'Can Administer Bestowals',
            Permission::SCOPE_BRANCH_AND_CHILDREN,
        );
        $this->assertPermissionMetadata(
            'Can Administer Court Agendas',
            Permission::SCOPE_BRANCH_AND_CHILDREN,
        );
        $this->assertPermissionMetadata(
            'Can Manage Bestowal To-Do Templates',
            Permission::SCOPE_GLOBAL,
        );
        $this->assertContains(
            'Awards\\Policy\\BestowalPolicy::canUpdateStates',
            $this->policyMappings('Can Administer Bestowals'),
        );
        $this->assertContains(
            'Awards\\Policy\\BestowalTodoTemplatesTablePolicy::canGridData',
            $this->policyMappings('Can Manage Bestowal To-Do Templates'),
        );

        $this->assertSame(
            ['Crown Regalia Management'],
            $this->managedRolePermissions('Crown Regalia Bestowal Todo'),
        );
        $this->assertSame(
            ['Baronial Court Management'],
            $this->managedRolePermissions('Baronial Court Agenda Bestowal Todo'),
        );

        $this->assertNull(
            $permissions->find()->where(['name' => 'Can View Bestowal (Branch and Children)'])->first(),
        );
        $this->assertNull(
            $permissions->find()->where(['name' => 'Can Manage Bestowals (Branch and Children)'])->first(),
        );
    }

    /**
     * @param string $permissionName Permission name.
     * @param string $scope Expected scope.
     * @return void
     */
    private function assertPermissionMetadata(string $permissionName, string $scope): void
    {
        $permission = TableRegistry::getTableLocator()->get('Permissions')->find()
            ->where(['name' => $permissionName])
            ->firstOrFail();
        $this->assertSame($scope, $permission->scoping_rule);
        $this->assertTrue($permission->require_active_membership);
        $this->assertTrue($permission->requires_warrant);
    }

    /**
     * @param string $permissionName Permission name.
     * @param array<int, string> $expected Expected mappings.
     * @return void
     */
    private function assertPolicyMappings(string $permissionName, array $expected): void
    {
        $this->assertSame($expected, $this->policyMappings($permissionName));
    }

    /**
     * @param string $permissionName Permission name.
     * @return array<int, string>
     */
    private function policyMappings(string $permissionName): array
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permission = $permissions->find()->where(['name' => $permissionName])->firstOrFail();
        $mappings = $permissionPolicies->find()
            ->where(['permission_id' => (int)$permission->id])
            ->all()
            ->map(
                static fn($mapping): string => $mapping->policy_class . '::' . $mapping->policy_method,
            )
            ->toList();
        sort($mappings);

        return $mappings;
    }

    /**
     * @param string $roleName Role name.
     * @return array<int, string>
     */
    private function managedRolePermissions(string $roleName): array
    {
        $roles = TableRegistry::getTableLocator()->get('Roles');
        $role = $roles->find()->where(['name' => $roleName])->firstOrFail();
        $managedNames = array_merge(
            [
                'Can View Bestowals',
                'Can Manage Bestowals',
                'Can Prepare Scrolls',
                'Can Manage Court Schedule',
                'Can Administer Bestowals',
                'Can Administer Court Agendas',
                'Can Manage Bestowal To-Do Templates',
            ],
            array_merge(...array_map(
                static fn(string $tier): array => [
                    $tier . ' Scroll Management',
                    $tier . ' Regalia Management',
                    $tier . ' Award Schedule Management',
                    $tier . ' Court Management',
                    $tier . ' Court Reporter',
                ],
                ['Crown', 'Principality', 'Baronial'],
            )),
        );

        $permissions = TableRegistry::getTableLocator()->get('Permissions')->find()
            ->innerJoinWith('Roles', static function ($query) use ($role) {
                return $query->where(['Roles.id' => (int)$role->id]);
            })
            ->where(['Permissions.name IN' => $managedNames])
            ->all()
            ->extract('name')
            ->toList();
        sort($permissions);

        return $permissions;
    }
}
