<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Config;

use AddBestowalFieldSecurity;
use App\Model\Entity\Permission;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\TableRegistry;
use HardenBestowalTodoSecurity;

require_once dirname(__DIR__, 3) . '/config/Migrations/20260714203000_HardenBestowalTodoSecurity.php';
require_once dirname(__DIR__, 3) . '/config/Migrations/20260715214500_AddBestowalFieldSecurity.php';

/**
 * Verifies durable protected bestowal field permissions.
 */
class AddBestowalFieldSecurityTest extends BaseTestCase
{
    public function testMigrationCreatesLeastPrivilegeMappingsAndIsIdempotent(): void
    {
        (new HardenBestowalTodoSecurity(20260714203000))->up();
        $migration = new AddBestowalFieldSecurity(20260715214500);
        $migration->up();
        $migration->up();

        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $crownPermission = $permissions->find()
            ->where(['name' => 'Crown Bestowal Field Access'])
            ->firstOrFail();
        $this->assertSame(Permission::SCOPE_BRANCH_ONLY, $crownPermission->scoping_rule);
        $this->assertTrue($crownPermission->require_active_membership);
        $this->assertTrue($crownPermission->requires_warrant);
        $this->assertSame([
            'canAccessCrownFields',
            'canAccessHeraldNotes',
        ], $this->policyMethods((int)$crownPermission->id));

        $courtPermission = $permissions->find()
            ->where(['name' => 'Crown Court Management'])
            ->firstOrFail();
        $this->assertContains(
            'canAccessHeraldNotes',
            $this->policyMethods((int)$courtPermission->id),
        );
        $this->assertNotContains(
            'canAccessCrownFields',
            $this->policyMethods((int)$courtPermission->id),
        );

        $crownRole = TableRegistry::getTableLocator()->get('Roles')->find()
            ->where(['name' => 'Ansteorran Crown'])
            ->firstOrFail();
        $this->assertTrue(TableRegistry::getTableLocator()->get('RolesPermissions')->exists([
            'role_id' => (int)$crownRole->id,
            'permission_id' => (int)$crownPermission->id,
        ]));
    }

    /**
     * @return list<string>
     */
    private function policyMethods(int $permissionId): array
    {
        $methods = TableRegistry::getTableLocator()->get('PermissionPolicies')->find()
            ->select(['policy_method'])
            ->where([
                'permission_id' => $permissionId,
                'policy_class' => 'Awards\\Policy\\BestowalPolicy',
            ])
            ->all()
            ->extract('policy_method')
            ->toList();
        sort($methods);

        return $methods;
    }
}
