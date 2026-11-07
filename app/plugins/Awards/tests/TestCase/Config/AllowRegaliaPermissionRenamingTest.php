<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Config;

use AllowRegaliaPermissionRenaming;
use App\Test\TestCase\BaseTestCase;
use HardenBestowalTodoSecurity;
use Migrations\Migration\IrreversibleMigrationException;

require_once dirname(__DIR__, 3) . '/config/Migrations/20260714203000_HardenBestowalTodoSecurity.php';
require_once dirname(__DIR__, 3) . '/config/Migrations/20261105011000_AllowRegaliaPermissionRenaming.php';

class AllowRegaliaPermissionRenamingTest extends BaseTestCase
{
    /**
     * @return void
     */
    public function testMigrationOnlyUnlocksRegaliaNamesAndPreservesPermissionReferences(): void
    {
        (new HardenBestowalTodoSecurity(20260714203000))->up();
        $permissions = $this->getTableLocator()->get('Permissions');
        $expected = $permissions->find()->orderBy(['id' => 'ASC'])->disableHydration()->toArray();
        $changed = 0;
        foreach ($expected as $index => $permission) {
            if (
                in_array($permission['name'], [
                    'Baronial Regalia Management',
                    'Crown Regalia Management',
                    'Principality Regalia Management',
                ], true)
            ) {
                $this->assertTrue($permission['is_system']);
                $expected[$index]['is_system'] = false;
                $changed += 1;
            }
        }
        $this->assertSame(3, $changed);

        $references = [];
        foreach (['RolesPermissions', 'PermissionPolicies', 'Awards.BestowalTodoTemplateItems', 'ActionItems'] as $alias) {
            $references[$alias] = $this->getTableLocator()->get($alias)
                ->find()->orderBy(['id' => 'ASC'])->disableHydration()->toArray();
        }

        $migration = new AllowRegaliaPermissionRenaming(20261105011000);
        $migration->up();
        $migration->up();

        $this->assertEquals($expected, $permissions->find()->orderBy(['id' => 'ASC'])->disableHydration()->toArray());
        foreach ($references as $alias => $rows) {
            $this->assertEquals($rows, $this->getTableLocator()->get($alias)
                ->find()->orderBy(['id' => 'ASC'])->disableHydration()->toArray());
        }

        $permission = $permissions->find()->where(['name' => 'Crown Regalia Management'])->firstOrFail();
        $permission = $permissions->patchEntity($permission, ['name' => 'Crown Insignia Management']);
        $permissions->saveOrFail($permission);
        $count = $permissions->find()->count();
        $migration->up();
        $this->assertSame($count, $permissions->find()->count());
        $renamed = $permissions->get($permission->id);
        $this->assertSame('Crown Insignia Management', $renamed->name);
        $this->assertFalse($renamed->is_system);
        $this->assertFalse($permissions->exists(['name' => 'Crown Regalia Management']));
    }

    /**
     * @return void
     */
    public function testRollbackRefusesToRelockRenamedPermissions(): void
    {
        $this->expectException(IrreversibleMigrationException::class);
        (new AllowRegaliaPermissionRenaming(20261105011000))->down();
    }
}
