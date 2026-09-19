<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\BackupSchemaManifestService;
use App\Services\DatabaseSchemaResetService;
use App\Test\TestCase\BaseTestCase;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use ReflectionMethod;
use RuntimeException;

/**
 * @covers \App\Services\DatabaseSchemaResetService
 */
class DatabaseSchemaResetServiceTest extends BaseTestCase
{
    public function testPartialUniqueIndexSurvivesSchemaExportAndRecreation(): void
    {
        $this->assertInstanceOf(Postgres::class, $this->connection->getDriver());
        $table = 'backup_partial_index_test';
        $this->connection->execute("CREATE TABLE $table (id INT PRIMARY KEY, template_id INT, terminal BOOLEAN)");
        $this->connection->execute("CREATE UNIQUE INDEX backup_one_terminal ON $table (template_id) WHERE terminal");
        $manifest = (new BackupSchemaManifestService())->export([], 'test');
        $plan = new ReflectionMethod(DatabaseSchemaResetService::class, 'buildResetPlan');
        $sql = $plan->invoke(new DatabaseSchemaResetService(), $this->connection->getDriver(), [
            $table => $manifest['tables'][$table],
        ]);
        $this->connection->execute("DROP TABLE $table");
        $this->connection->execute($sql['tables'][0]['sql']);
        foreach ($sql['indexes'] as $indexSql) {
            $this->connection->execute($indexSql);
        }
        $this->connection->execute("INSERT INTO $table VALUES (1, 1, FALSE), (2, 1, FALSE), (3, 1, TRUE)");
        $this->assertSame(3, (int)$this->connection->execute("SELECT COUNT(*) FROM $table")->fetchColumn(0));
        // A duplicate terminal is still rejected, without aborting the enclosing test transaction.
        $this->connection->execute("INSERT INTO $table VALUES (4, 1, TRUE) ON CONFLICT DO NOTHING");
        $this->assertSame(3, (int)$this->connection->execute("SELECT COUNT(*) FROM $table")->fetchColumn(0));
    }

    public function testPartialIndexesRejectUnsupportedTargetBeforeReset(): void
    {
        $plan = new ReflectionMethod(DatabaseSchemaResetService::class, 'buildResetPlan');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot restore PostgreSQL partial indexes to MySQL');
        $plan->invoke(new DatabaseSchemaResetService(), new Mysql([]), [
            'items' => [
                'columns' => ['id' => ['type' => 'integer']],
                'indexes' => ['active_items' => ['columns' => ['id'], 'where' => 'id > 0']],
            ],
        ]);
    }

    public function testColumnTypeSqlSupportsFractionalTemporalTypes(): void
    {
        $service = new DatabaseSchemaResetService();
        $method = new ReflectionMethod(DatabaseSchemaResetService::class, 'columnTypeSql');
        $method->setAccessible(true);
        $driver = new Postgres([]);

        $this->assertSame(
            'TIMESTAMP(6)',
            $method->invoke($service, $driver, 'timestampfractional', [], false),
        );
        $this->assertSame(
            'TIMESTAMP(6)',
            $method->invoke($service, $driver, 'datetimefractional', [], false),
        );
        $this->assertSame(
            'TIME(6)',
            $method->invoke($service, $driver, 'timefractional', [], false),
        );
    }

    public function testColumnTypeSqlSupportsSmallInteger(): void
    {
        $service = new DatabaseSchemaResetService();
        $method = new ReflectionMethod(DatabaseSchemaResetService::class, 'columnTypeSql');
        $method->setAccessible(true);
        $driver = new Postgres([]);

        $this->assertSame(
            'SMALLINT',
            $method->invoke($service, $driver, 'smallinteger', [], false),
        );
    }

    public function testColumnTypeSqlPreservesCitextAcrossDatabaseEngines(): void
    {
        $service = new DatabaseSchemaResetService();
        $method = new ReflectionMethod(DatabaseSchemaResetService::class, 'columnTypeSql');
        $method->setAccessible(true);

        $this->assertSame(
            'CITEXT',
            $method->invoke($service, new Postgres([]), 'citext', [], false),
        );
        $this->assertSame(
            'TEXT',
            $method->invoke($service, new Mysql([]), 'citext', [], false),
        );
        $this->assertSame(
            'VARCHAR(255)',
            $method->invoke($service, new Mysql([]), 'citext', [], false, true),
        );
    }

    public function testReferentialActionSqlNormalizesCompactVariants(): void
    {
        $service = new DatabaseSchemaResetService();
        $method = new ReflectionMethod(DatabaseSchemaResetService::class, 'referentialActionSql');
        $method->setAccessible(true);

        $this->assertSame('NO ACTION', $method->invoke($service, 'NOACTION'));
        $this->assertSame('SET NULL', $method->invoke($service, 'setNull'));
        $this->assertSame('SET DEFAULT', $method->invoke($service, 'setDefault'));
    }

    public function testDefaultSqlNormalizesPostgresBooleanIntegerDefaults(): void
    {
        $service = new DatabaseSchemaResetService();
        $method = new ReflectionMethod(DatabaseSchemaResetService::class, 'defaultSql');
        $method->setAccessible(true);
        $driver = new Postgres([]);

        $this->assertSame('FALSE', $method->invoke($service, $driver, 0, 'boolean'));
        $this->assertSame('TRUE', $method->invoke($service, $driver, 1, 'boolean'));
    }

    public function testDefaultSqlNormalizesMysqlCharsetJsonDefaultForPostgres(): void
    {
        $service = new DatabaseSchemaResetService();
        $method = new ReflectionMethod(DatabaseSchemaResetService::class, 'defaultSql');
        $method->setAccessible(true);
        $driver = new Postgres([]);

        $this->assertSame(
            "'{}'::jsonb",
            $method->invoke($service, $driver, "_utf8mb4\\'{}\\'", 'json'),
        );
    }

    public function testIndexSqlPrefixesPostgresIndexNamesWithTableName(): void
    {
        $service = new DatabaseSchemaResetService();
        $method = new ReflectionMethod(DatabaseSchemaResetService::class, 'indexSql');
        $method->setAccessible(true);
        $driver = new Postgres([]);

        $sql = $method->invoke($service, $driver, [
            'activities_activity_groups' => [
                'constraints' => [
                    'name' => [
                        'type' => 'unique',
                        'columns' => ['name'],
                    ],
                ],
            ],
            'awards_domains' => [
                'constraints' => [
                    'name' => [
                        'type' => 'unique',
                        'columns' => ['name'],
                    ],
                ],
            ],
        ]);

        $this->assertSame(
            'CREATE UNIQUE INDEX "activities_activity_groups_name" ON "activities_activity_groups" ("name")',
            $sql[0],
        );
        $this->assertSame(
            'CREATE UNIQUE INDEX "awards_domains_name" ON "awards_domains" ("name")',
            $sql[1],
        );
    }
}
