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
