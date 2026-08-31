<?php

declare(strict_types=1);

namespace Officers\Test\TestCase\Config;

use AddImportMemberDataPermission;
use App\Migrations\CrossEngineMigrationTrait;
use App\Policy\MemberPolicy;
use App\Test\TestCase\BaseTestCase;
use Cake\Datasource\ConnectionManager;
use GrantImportMemberDataToKingdomSeneschalRole;
use Migrations\Migration\Environment;
use RuntimeException;

class MemberDataImportMigrationsTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once ROOT . '/config/Migrations/20260830080000_AddImportMemberDataPermission.php';
        require_once ROOT
            . '/plugins/Officers/config/Migrations/'
            . '20260830080100_GrantImportMemberDataToKingdomSeneschalRole.php';
    }

    public function testCoreMigrationStoresExactPolicyClass(): void
    {
        $traits = class_uses(AddImportMemberDataPermission::class);
        $this->assertIsArray($traits);
        $this->assertContains(CrossEngineMigrationTrait::class, $traits);

        $this->coreMigration()->up();

        $connection = ConnectionManager::get('test');
        $mapping = $connection->execute(
            "SELECT pp.policy_class, pp.policy_method
               FROM permission_policies pp
               JOIN permissions p ON p.id = pp.permission_id
              WHERE p.name = ?",
            ['Can Import Member Data'],
        )->fetch('assoc');

        $this->assertIsArray($mapping);
        $this->assertSame(MemberPolicy::class, $mapping['policy_class']);
        $this->assertSame('canImportExpirationDates', $mapping['policy_method']);
    }

    public function testPluginMigrationUsesConfiguredKingdomSeneschalRole(): void
    {
        $this->coreMigration()->up();
        $connection = ConnectionManager::get('test');
        $role = $connection->execute(
            'SELECT id FROM roles WHERE name = ?',
            ['Greater Officer of State'],
        )->fetch('assoc');
        $this->assertIsArray($role);
        $roleId = (int)$role['id'];
        $connection->execute(
            'UPDATE roles SET name = ? WHERE id = ?',
            ['Custom Kingdom Seneschal Role', $roleId],
        );
        $connection->execute(
            'UPDATE officers_offices SET grants_role_id = ? WHERE name = ? AND deleted IS NULL',
            [$roleId, 'Kingdom Seneschal'],
        );

        $migration = $this->pluginMigration();
        $migration->up();

        $fallbackRoleCount = (int)$connection->execute(
            'SELECT count(*) FROM roles WHERE name = ?',
            ['Greater Officer of State'],
        )->fetchColumn(0);
        $this->assertSame(0, $fallbackRoleCount);

        $grantCount = (int)$connection->execute(
            "SELECT count(*)
              FROM roles_permissions rp
               JOIN permissions p ON p.id = rp.permission_id
              WHERE rp.role_id = ? AND p.name = ?",
            [$roleId, 'Can Import Member Data'],
        )->fetchColumn(0);
        $this->assertSame(1, $grantCount);

        $migration->down();

        $remainingRoleCount = (int)$connection->execute(
            'SELECT count(*) FROM roles WHERE id = ?',
            [$roleId],
        )->fetchColumn(0);
        $remainingGrantCount = (int)$connection->execute(
            "SELECT count(*)
               FROM roles_permissions rp
               JOIN permissions p ON p.id = rp.permission_id
              WHERE rp.role_id = ? AND p.name = ?",
            [$roleId, 'Can Import Member Data'],
        )->fetchColumn(0);
        $this->assertSame(1, $remainingRoleCount);
        $this->assertSame(0, $remainingGrantCount);
    }

    public function testPluginMigrationCreatesFallbackWithoutChangingOfficeConfiguration(): void
    {
        $this->coreMigration()->up();
        $connection = ConnectionManager::get('test');
        $connection->execute(
            'UPDATE roles SET name = ? WHERE name = ?',
            ['Former Greater Officer Role', 'Greater Officer of State'],
        );
        $connection->execute(
            'UPDATE officers_offices SET grants_role_id = NULL WHERE name = ? AND deleted IS NULL',
            ['Kingdom Seneschal'],
        );

        $migration = $this->pluginMigration();
        $migration->up();

        $role = $connection->execute(
            'SELECT id, is_system FROM roles WHERE name = ?',
            ['Greater Officer of State'],
        )->fetch('assoc');
        $this->assertIsArray($role);
        $this->assertFalse((bool)$role['is_system']);
        $roleId = (int)$role['id'];

        $unassignedOfficeCount = (int)$connection->execute(
            'SELECT count(*) FROM officers_offices'
                . ' WHERE name = ? AND deleted IS NULL AND grants_role_id IS NULL',
            ['Kingdom Seneschal'],
        )->fetchColumn(0);
        $this->assertGreaterThan(0, $unassignedOfficeCount);

        $grantCount = (int)$connection->execute(
            "SELECT count(*)
               FROM roles_permissions rp
               JOIN permissions p ON p.id = rp.permission_id
              WHERE rp.role_id = ? AND p.name = ?",
            [$roleId, 'Can Import Member Data'],
        )->fetchColumn(0);
        $this->assertSame(1, $grantCount);

        $migration->down();

        $remainingRoleCount = (int)$connection->execute(
            'SELECT count(*) FROM roles WHERE id = ?',
            [$roleId],
        )->fetchColumn(0);
        $remainingGrantCount = (int)$connection->execute(
            "SELECT count(*)
               FROM roles_permissions rp
               JOIN permissions p ON p.id = rp.permission_id
              WHERE rp.role_id = ? AND p.name = ?",
            [$roleId, 'Can Import Member Data'],
        )->fetchColumn(0);
        $this->assertSame(1, $remainingRoleCount);
        $this->assertSame(0, $remainingGrantCount);
    }

    public function testPluginMigrationDoesNotRestoreDeletedFallbackRole(): void
    {
        $this->coreMigration()->up();
        $connection = ConnectionManager::get('test');
        $connection->execute(
            'UPDATE roles SET deleted = CURRENT_TIMESTAMP WHERE name = ?',
            ['Greater Officer of State'],
        );
        $connection->execute(
            'UPDATE officers_offices SET grants_role_id = NULL WHERE name = ? AND deleted IS NULL',
            ['Kingdom Seneschal'],
        );

        $exception = null;
        try {
            $this->pluginMigration()->up();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertStringContainsString('role is deleted', $exception->getMessage());
        $deletedAt = $connection->execute(
            'SELECT deleted FROM roles WHERE name = ?',
            ['Greater Officer of State'],
        )->fetchColumn(0);
        $this->assertNotNull($deletedAt);
    }

    private function coreMigration(): AddImportMemberDataPermission
    {
        $environment = new Environment('member-data-import-core-test', [
            'connection' => 'test',
        ]);

        return (new AddImportMemberDataPermission(20260830080000))
            ->setAdapter($environment->getAdapter());
    }

    private function pluginMigration(): GrantImportMemberDataToKingdomSeneschalRole
    {
        $environment = new Environment('member-data-import-officers-test', [
            'connection' => 'test',
        ]);

        return (new GrantImportMemberDataToKingdomSeneschalRole(20260830080100))
            ->setAdapter($environment->getAdapter());
    }
}
