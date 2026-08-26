<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services\Platform;

use App\Services\Platform\TenantMigrationCatalog;
use App\Test\TestCase\BaseTestCase;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;

class TenantMigrationCatalogTest extends BaseTestCase
{
    private Connection $catalogConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalogConnection = new Connection([
            'driver' => Sqlite::class,
            'database' => ':memory:',
        ]);
        $this->catalogConnection->execute(
            'CREATE TABLE phinxlog (version TEXT PRIMARY KEY, migration_name TEXT)',
        );
        $this->catalogConnection->execute(
            'CREATE TABLE awards_phinxlog (version TEXT PRIMARY KEY, migration_name TEXT)',
        );
    }

    public function testInspectFindsPendingVersionsAcrossAppAndPlugins(): void
    {
        $this->catalogConnection->insert('phinxlog', [
            'version' => '20260101000000',
            'migration_name' => 'Initial',
        ]);
        $this->catalogConnection->insert('awards_phinxlog', [
            'version' => '20260102000000',
            'migration_name' => 'AwardsInitial',
        ]);
        $catalog = $this->catalog();

        $state = $catalog->inspect($this->catalogConnection);

        $this->assertFalse($state->isCurrent());
        $this->assertSame('20260103000000', $state->targetVersion);
        $this->assertSame('20260102000000', $state->currentVersion);
        $this->assertSame([
            'app' => ['20260103000000'],
            'Awards' => ['20260102100000'],
        ], $state->pendingVersions);
        $this->assertSame([], $state->unexpectedVersions);
    }

    public function testInspectReportsCurrentOnlyWhenEveryScopeIsComplete(): void
    {
        foreach (['20260101000000', '20260103000000'] as $version) {
            $this->catalogConnection->insert('phinxlog', ['version' => $version, 'migration_name' => $version]);
        }
        foreach (['20260102000000', '20260102100000'] as $version) {
            $this->catalogConnection->insert('awards_phinxlog', ['version' => $version, 'migration_name' => $version]);
        }

        $state = $this->catalog()->inspect($this->catalogConnection);

        $this->assertTrue($state->isCurrent());
        $this->assertSame('20260103000000', $state->currentVersion);
    }

    public function testInspectFailsClosedOnUnexpectedAppliedHistory(): void
    {
        $this->catalogConnection->insert('phinxlog', [
            'version' => '20270101000000',
            'migration_name' => 'FutureMigration',
        ]);

        $state = $this->catalog()->inspect($this->catalogConnection);

        $this->assertFalse($state->isCurrent());
        $this->assertSame(['app' => ['20270101000000']], $state->unexpectedVersions);
    }

    private function catalog(): TenantMigrationCatalog
    {
        return new TenantMigrationCatalog([
            'app' => ['20260101000000', '20260103000000'],
            'Awards' => ['20260102000000', '20260102100000'],
        ]);
    }
}
