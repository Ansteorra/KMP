<?php
declare(strict_types=1);

namespace Officers\Test\TestCase\Config;

use AddLocalCalendarDeputy;
use App\Model\Entity\Permission;
use App\Policy\GatheringPolicy;
use App\Test\TestCase\BaseTestCase;
use InitLocalCalendarDeputySeed;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

require_once dirname(__DIR__, 3) . '/config/Migrations/20261111180000_AddLocalCalendarDeputy.php';

class LocalCalendarDeputySeedTest extends BaseTestCase
{
    public function testMigrationCreatesNonWarrantDeputyWithExactScopedPermissionsIdempotently(): void
    {
        $tables = $this->getTableLocator();
        $seneschalPermission = $tables->get('Permissions')->find()->where([
            'name' => 'Manage Gatherings For Branch and Branches Below Me',
        ])->firstOrFail()->toArray();
        $migration = new AddLocalCalendarDeputy();
        $migration->up();
        $before = $this->snapshot();
        $migration->up();
        (new InitLocalCalendarDeputySeed())->run();
        $this->assertSame($before, $this->snapshot());
        $parent = $tables->get('Officers.Offices')->find()->where(['name' => 'Local Seneschal'])->firstOrFail();
        $office = $tables->get('Officers.Offices')->find()->where(['name' => InitLocalCalendarDeputySeed::ROLE])
            ->firstOrFail();
        $role = $tables->get('Roles')->find()->where(['name' => InitLocalCalendarDeputySeed::ROLE])->firstOrFail();
        $this->assertSame($parent->id, $office->deputy_to_id);
        $this->assertSame($parent->id, $office->reports_to_id);
        $this->assertSame($parent->department_id, $office->department_id);
        $this->assertSame($parent->branch_types, $office->branch_types);
        $this->assertSame($role->id, $office->grants_role_id);
        $this->assertFalse($office->requires_warrant);
        $this->assertTrue($parent->requires_warrant);
        $this->assertEquals($seneschalPermission, $tables->get('Permissions')->get($seneschalPermission['id'])->toArray());
        $this->assertSame(2, $tables->get('RolesPermissions')->find()->where(['role_id' => $role->id])->count());
        foreach ([InitLocalCalendarDeputySeed::MANAGE => 9, InitLocalCalendarDeputySeed::UPLOAD => 8] as $name => $count) {
            $permission = $tables->get('Permissions')->find()->where(['name' => $name])->firstOrFail();
            $this->assertSame(Permission::SCOPE_BRANCH_AND_CHILDREN, $permission->scoping_rule);
            $this->assertFalse($permission->requires_warrant);
            $this->assertFalse($permission->is_super_user);
            $this->assertTrue($permission->require_active_membership);
            $this->assertSame($count, $tables->get('PermissionPolicies')->find()->where([
                'permission_id' => $permission->id,
            ])->count());
            $this->assertFalse($tables->get('PermissionPolicies')->exists([
                'permission_id' => $permission->id, 'policy_class' => GatheringPolicy::class,
                'policy_method IN' => ['canPublish', 'canDelete'],
            ]));
        }
    }

    public function testSeedRepairsMissingMappingsAndGrantsWhilePreservingOfficeContactAndTerm(): void
    {
        $seed = new InitLocalCalendarDeputySeed();
        $seed->run();
        $tables = $this->getTableLocator();
        $office = $tables->get('Officers.Offices')->find()->where(['name' => InitLocalCalendarDeputySeed::ROLE])
            ->firstOrFail();
        $office->term_length = 18;
        $office->default_contact_address = 'local-calendar@example.test';
        $tables->get('Officers.Offices')->saveOrFail($office);
        $permission = $tables->get('Permissions')->find()->where(['name' => InitLocalCalendarDeputySeed::UPLOAD])
            ->firstOrFail();
        $tables->get('PermissionPolicies')->deleteAll(['permission_id' => $permission->id]);
        $tables->get('RolesPermissions')->deleteAll(['permission_id' => $permission->id]);
        $seed->run();
        $this->assertSame(18, $tables->get('Officers.Offices')->get($office->id)->term_length);
        $this->assertSame('local-calendar@example.test', $tables->get('Officers.Offices')
            ->get($office->id)->default_contact_address);
        $this->assertSame(8, $tables->get('PermissionPolicies')->find()->where([
            'permission_id' => $permission->id,
        ])->count());
        $this->assertTrue($tables->get('RolesPermissions')->exists([
            'role_id' => $office->grants_role_id, 'permission_id' => $permission->id,
        ]));
    }

    public function testFreshInstallCanCreateTheParentOfficeAndDepartment(): void
    {
        $tables = $this->getTableLocator();
        $offices = $tables->get('Officers.Offices');
        $parent = $offices->find()->where(['name' => 'Local Seneschal'])->firstOrFail();
        $parent->name = 'Previously configured Local Seneschal';
        $offices->saveOrFail($parent);
        $offices->updateAll(['name' => 'Previously configured Local Calendar Deputy'], [
            'name' => InitLocalCalendarDeputySeed::ROLE,
        ]);
        $tables->get('Officers.Departments')->updateAll(['name' => 'Previously configured Seneschal'], [
            'name' => 'Seneschal',
        ]);
        (new InitLocalCalendarDeputySeed())->run();
        $created = $offices->find()->where(['name' => 'Local Seneschal'])->firstOrFail();
        $deputy = $offices->find()->where(['name' => InitLocalCalendarDeputySeed::ROLE])->firstOrFail();
        $this->assertNotSame($parent->id, $created->id);
        $this->assertSame($created->id, $deputy->deputy_to_id);
        $this->assertTrue($created->requires_warrant);
        $this->assertFalse($deputy->requires_warrant);
        $this->assertSame(['Local Group'], $created->branch_types);
        $this->assertSame('Seneschal', $tables->get('Officers.Departments')->get($created->department_id)->name);
        $this->assertSame($created->department_id, $deputy->department_id);
        $this->assertNotSame($parent->department_id, $created->department_id);
        $regional = $offices->find()->where(['name' => 'Regional Seneschal'])->firstOrFail();
        $this->assertSame($regional->id, $created->reports_to_id);
    }

    public function testDeletedParentIsNotRecreatedOrGrantedNewAuthority(): void
    {
        $offices = $this->getTableLocator()->get('Officers.Offices');
        $parent = $offices->find()->where(['name' => 'Local Seneschal'])->firstOrFail();
        $offices->deleteOrFail($parent);
        $before = $this->snapshot();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('deleted Local Seneschal');
        try {
            (new InitLocalCalendarDeputySeed())->run();
        } finally {
            $this->assertSame($before, $this->snapshot());
        }
    }

    public static function conflicts(): array
    {
        return [['global'], ['superuser'], ['warrant'], ['deleted'], ['policy'], ['role'], ['office']];
    }

    #[DataProvider('conflicts')]
    public function testSeedRejectsUnsafeExistingConfigurationAtomically(string $conflict): void
    {
        $seed = new InitLocalCalendarDeputySeed();
        $seed->run();
        $tables = $this->getTableLocator();
        $permission = $tables->get('Permissions')->find()->where(['name' => InitLocalCalendarDeputySeed::UPLOAD])
            ->firstOrFail();
        $office = $tables->get('Officers.Offices')->find()->where(['name' => InitLocalCalendarDeputySeed::ROLE])
            ->firstOrFail();
        switch ($conflict) {
            case 'global':
                $permission->scoping_rule = Permission::SCOPE_GLOBAL;
                break;
            case 'superuser':
                $permission->is_super_user = true;
                break;
            case 'warrant':
                $permission->requires_warrant = true;
                break;
            case 'deleted':
                $tables->get('Permissions')->deleteOrFail($permission);
                break;
            case 'policy':
                $tables->get('PermissionPolicies')->saveOrFail($tables->get('PermissionPolicies')->newEntity([
                    'permission_id' => $permission->id, 'policy_class' => GatheringPolicy::class,
                    'policy_method' => 'canPublish',
                ]));
                break;
            case 'role':
                $tables->get('RolesPermissions')->saveOrFail($tables->get('RolesPermissions')->newEntity([
                    'role_id' => $office->grants_role_id, 'permission_id' => self::SUPER_USER_PERMISSION_ID,
                    'created_by' => self::ADMIN_MEMBER_ID,
                ]));
                break;
            case 'office':
                $office->requires_warrant = true;
                $tables->get('Officers.Offices')->saveOrFail($office);
                break;
        }
        if ($conflict !== 'deleted') {
            $tables->get('Permissions')->saveOrFail($permission);
        }
        $manage = $tables->get('Permissions')->find()->where(['name' => InitLocalCalendarDeputySeed::MANAGE])
            ->firstOrFail();
        $tables->get('PermissionPolicies')->deleteAll(['permission_id' => $manage->id]);
        $before = $this->snapshot();
        $this->expectException(RuntimeException::class);
        try {
            $seed->run();
        } finally {
            $this->assertSame($before, $this->snapshot());
        }
    }

    public function testRollbackDoesNotRemoveAdoptedConfiguration(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('irreversible');
        (new AddLocalCalendarDeputy())->down();
    }

    private function snapshot(): array
    {
        $tables = $this->getTableLocator();

        return array_map(fn($alias) => $tables->get($alias)->find()->count(), [
            'Roles', 'Permissions', 'PermissionPolicies', 'RolesPermissions', 'Officers.Offices',
        ]);
    }
}
