<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\Config;

use AddCalendarDeputy;
use App\Model\Entity\Permission;
use App\Policy\GatheringPolicy;
use App\Policy\GatheringsTablePolicy;
use App\Test\TestCase\BaseTestCase;
use InitCalendarDeputySeed;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

require_once dirname(__DIR__, 3) . '/config/Migrations/20261111100000_AddCalendarDeputy.php';

class CalendarDeputySeedTest extends BaseTestCase
{
    public function testMigrationCreatesDeputyRoleGlobalPermissionsAndExactPoliciesIdempotently(): void
    {
        $tables = $this->getTableLocator();
        $scoped = $tables->get('Permissions')->find()->where([
            'name' => 'Manage Gatherings For Branch and Branches Below Me',
        ])->firstOrFail();
        $scope = $scoped->scoping_rule;
        $migration = new AddCalendarDeputy();
        $migration->up();
        $first = $this->snapshot();
        $migration->up();
        (new InitCalendarDeputySeed())->run();
        $this->assertSame($first, $this->snapshot());
        $parent = $tables->get('Officers.Offices')->find()->where(['name' => 'Kingdom Seneschal'])->firstOrFail();
        $office = $tables->get('Officers.Offices')->find()->where(['name' => 'Calendar Deputy'])->firstOrFail();
        $role = $tables->get('Roles')->find()->where(['name' => 'Calendar Deputy'])->firstOrFail();
        $this->assertSame($role->id, $office->grants_role_id);
        $this->assertSame($parent->id, $office->deputy_to_id);
        $this->assertSame($parent->id, $office->reports_to_id);
        $this->assertSame($parent->department_id, $office->department_id);
        $this->assertSame(['Kingdom'], $office->branch_types);
        $this->assertTrue($office->requires_warrant);
        $this->assertSame($scope, $tables->get('Permissions')->get($scoped->id)->scoping_rule);
        foreach ([InitCalendarDeputySeed::MANAGE, InitCalendarDeputySeed::PUBLISH] as $name) {
            $permission = $tables->get('Permissions')->find()->where(['name' => $name])->firstOrFail();
            $this->assertSame(Permission::SCOPE_GLOBAL, $permission->scoping_rule);
            $this->assertFalse($permission->is_super_user);
            $this->assertTrue($permission->require_active_membership);
            $this->assertSame(1, $tables->get('RolesPermissions')->find()->where([
                'role_id' => $role->id, 'permission_id' => $permission->id,
            ])->count());
            $methods = $tables->get('PermissionPolicies')->find()->where([
                'permission_id' => $permission->id, 'policy_class' => GatheringPolicy::class,
            ])->all()->extract('policy_method')->toList();
            $expected = $name === InitCalendarDeputySeed::PUBLISH ? ['canPublish'] : [
                'canAdd', 'canEdit', 'canView', 'canIndex', 'canGridData',
                'canCalendar', 'canCalendarGridData', 'canQuickView',
            ];
            $this->assertEqualsCanonicalizing($expected, $methods);
            if ($name === InitCalendarDeputySeed::MANAGE) {
                $this->assertTrue($tables->get('PermissionPolicies')->exists([
                    'permission_id' => $permission->id,
                    'policy_class' => GatheringsTablePolicy::class, 'policy_method' => 'canAdd',
                ]));
            }
        }
    }

    public function testSeedReusesExistingPermissionAndOfficeSettingsAndRepairsMissingPolicies(): void
    {
        $seed = new InitCalendarDeputySeed();
        $seed->run();
        $tables = $this->getTableLocator();
        $office = $tables->get('Officers.Offices')->find()->where(['name' => 'Calendar Deputy'])->firstOrFail();
        $office->term_length = 18;
        $office->default_contact_address = 'calendar@example.test';
        $tables->get('Officers.Offices')->saveOrFail($office);
        $permissions = $tables->get('Permissions');
        $publish = $permissions->find()->where(['name' => InitCalendarDeputySeed::PUBLISH])->firstOrFail();
        $publish->requires_warrant = true;
        $permissions->saveOrFail($publish);
        $tables->get('PermissionPolicies')->deleteAll(['permission_id' => $publish->id]);

        $seed->run();

        $saved = $tables->get('Officers.Offices')->find()->where(['name' => 'Calendar Deputy'])->firstOrFail();
        $this->assertSame($office->id, $saved->id);
        $this->assertSame(18, $saved->term_length);
        $this->assertSame('calendar@example.test', $saved->default_contact_address);
        $this->assertTrue($permissions->get($publish->id)->requires_warrant);
        $this->assertSame(1, $tables->get('PermissionPolicies')->find()->where([
            'permission_id' => $publish->id, 'policy_class' => GatheringPolicy::class, 'policy_method' => 'canPublish',
        ])->count());
    }

    public function testSeedCanCreateTheRequiredParentOfficeForAFreshTenant(): void
    {
        $offices = $this->getTableLocator()->get('Officers.Offices');
        $parent = $offices->find()->where(['name' => 'Kingdom Seneschal'])->firstOrFail();
        $parent->name = 'Previously configured Seneschal';
        $offices->saveOrFail($parent);
        $departments = $this->getTableLocator()->get('Officers.Departments');
        $departments->updateAll(['name' => 'Previously configured Seneschal'], ['name' => 'Seneschal']);
        $existingDeputy = $offices->find()->where(['name' => 'Calendar Deputy'])->first();
        if ($existingDeputy !== null) {
            $existingDeputy->name = 'Previously configured Calendar Deputy';
            $offices->saveOrFail($existingDeputy);
        }
        (new InitCalendarDeputySeed())->run();
        $created = $offices->find()->where(['name' => 'Kingdom Seneschal'])->firstOrFail();
        $deputy = $offices->find()->where(['name' => 'Calendar Deputy'])->firstOrFail();
        $this->assertNotSame($parent->id, $created->id);
        $this->assertSame($created->id, $deputy->deputy_to_id);
        $this->assertSame($parent->id, $offices->get($parent->id)->id);
        $this->assertNotSame($parent->department_id, $created->department_id);
        $this->assertSame('Seneschal', $departments->get($created->department_id)->name);
    }

    public function testSeedCreatesMissingPermissionsWithRequiredPolicies(): void
    {
        $tables = $this->getTableLocator();
        foreach ([InitCalendarDeputySeed::MANAGE, InitCalendarDeputySeed::PUBLISH] as $name) {
            $tables->get('Permissions')->updateAll(['name' => 'Previously configured ' . $name], ['name' => $name]);
        }
        (new InitCalendarDeputySeed())->run();
        foreach ([InitCalendarDeputySeed::MANAGE, InitCalendarDeputySeed::PUBLISH] as $name) {
            $permission = $tables->get('Permissions')->find()->where(['name' => $name])->firstOrFail();
            $this->assertSame(Permission::SCOPE_GLOBAL, $permission->scoping_rule);
            $this->assertTrue($permission->require_active_membership);
            $this->assertSame($name === InitCalendarDeputySeed::MANAGE, $permission->requires_warrant);
            $this->assertSame($name === InitCalendarDeputySeed::MANAGE ? 9 : 1, $tables->get('PermissionPolicies')
                ->find()->where(['permission_id' => $permission->id])->count());
        }
    }

    public static function unsafePermissions(): array
    {
        return [['deleted'], ['superuser'], ['scoped']];
    }

    #[DataProvider('unsafePermissions')]
    public function testSeedRejectsUnsafeExistingManagementPermissionWithoutPartialGrants(string $state): void
    {
        $permissions = $this->getTableLocator()->get('Permissions');
        $permission = $permissions->saveOrFail($permissions->newEntity([
            'name' => InitCalendarDeputySeed::MANAGE,
            'scoping_rule' => $state === 'scoped' ? Permission::SCOPE_BRANCH_ONLY : Permission::SCOPE_GLOBAL,
            'is_super_user' => $state === 'superuser',
        ]));
        if ($state === 'deleted') {
            $permissions->deleteOrFail($permission);
        }
        $before = $this->snapshot();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Resolve the deleted, superuser, or non-global');
        try {
            (new InitCalendarDeputySeed())->run();
        } finally {
            $this->assertFalse($this->getTableLocator()->get('RolesPermissions')->exists([
                'permission_id' => $permission->id,
            ]));
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function testRollbackPreservesAdoptedOfficeAndRoleConfiguration(): void
    {
        $migration = new AddCalendarDeputy();
        $migration->up();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('irreversible');
        $migration->down();
    }

    private function snapshot(): array
    {
        $tables = $this->getTableLocator();

        return [
            $tables->get('Officers.Offices')->find()->where(['name' => 'Calendar Deputy'])->count(),
            $tables->get('Roles')->find()->where(['name' => 'Calendar Deputy'])->count(),
            $tables->get('Permissions')->find()->count(),
            $tables->get('PermissionPolicies')->find()->count(),
            $tables->get('RolesPermissions')->find()->count(),
        ];
    }
}
