<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\DatabaseSchemaResetService;
use Cake\Database\Driver\Postgres;
use Cake\TestSuite\TestCase;
use ReflectionMethod;

/**
 * @covers \App\Services\DatabaseSchemaResetService
 */
class DatabaseSchemaResetServiceTest extends TestCase
{
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
