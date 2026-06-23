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
}
