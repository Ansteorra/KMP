<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Config;

use AddBestowalPermissions;
use App\Test\TestCase\BaseTestCase;
use Cake\ORM\TableRegistry;
use EnableScopedCourtPlanning;
use HardenBestowalTodoSecurity;

require_once dirname(__DIR__, 3) . '/config/Migrations/20260408020000_AddBestowalPermissions.php';
require_once dirname(__DIR__, 3) . '/config/Migrations/20260714203000_HardenBestowalTodoSecurity.php';
require_once dirname(__DIR__, 3) . '/config/Migrations/20260831120000_EnableScopedCourtPlanning.php';

/**
 * Verifies scoped Court permissions can prepare gathering Courts.
 */
class EnableScopedCourtPlanningTest extends BaseTestCase
{
    public function testMigrationMapsCourtPlanningPoliciesIdempotently(): void
    {
        $this->preparePermissionPrerequisites();
        $migration = new EnableScopedCourtPlanning(20260831120000);
        $migration->up();
        $migration->up();

        foreach (
            [
            'Crown Court Management',
            'Principality Court Management',
            'Baronial Court Management',
            'Can Manage Court Schedule',
            ] as $permissionName
        ) {
            $this->assertSame(
                [
                    'canAddCourtActivity',
                    'canCreateScheduledActivity',
                    'canEditScheduledActivity',
                ],
                $this->gatheringPolicyMethods($permissionName),
            );
        }
    }

    public function testRollbackPreservesLegacyScheduleMappings(): void
    {
        $this->preparePermissionPrerequisites();
        $migration = new EnableScopedCourtPlanning(20260831120000);
        $migration->up();
        $migration->down();

        $this->assertSame(
            ['canCreateScheduledActivity', 'canEditScheduledActivity'],
            $this->gatheringPolicyMethods('Can Manage Court Schedule'),
        );
        foreach (
            [
            'Crown Court Management',
            'Principality Court Management',
            'Baronial Court Management',
            ] as $permissionName
        ) {
            $this->assertSame([], $this->gatheringPolicyMethods($permissionName));
        }
    }

    private function preparePermissionPrerequisites(): void
    {
        (new AddBestowalPermissions(20260408020000))->up();
        (new HardenBestowalTodoSecurity(20260714203000))->up();
    }

    /**
     * @param string $permissionName Permission name
     * @return array<int, string>
     */
    private function gatheringPolicyMethods(string $permissionName): array
    {
        $permission = TableRegistry::getTableLocator()->get('Permissions')->find()
            ->where(['name' => $permissionName])
            ->firstOrFail();
        $methods = TableRegistry::getTableLocator()->get('PermissionPolicies')->find()
            ->select(['policy_method'])
            ->where([
                'permission_id' => (int)$permission->id,
                'policy_class' => 'App\\Policy\\GatheringPolicy',
            ])
            ->all()
            ->extract('policy_method')
            ->toList();
        sort($methods);

        return $methods;
    }
}
