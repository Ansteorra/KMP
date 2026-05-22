<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\BackupService;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * @covers \App\Services\BackupService
 */
class BackupServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        ConnectionManager::drop('backup_scope_test');
        parent::tearDown();
    }

    public function testExportCanTargetNonDefaultConnection(): void
    {
        ConnectionManager::setConfig('backup_scope_test', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Sqlite',
            'database' => ':memory:',
        ]);
        $connection = ConnectionManager::get('backup_scope_test');
        $connection->execute('CREATE TABLE platform_values (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $connection->insert('platform_values', ['id' => 1, 'name' => 'Example']);

        $service = new BackupService('backup_scope_test');
        $result = $service->export('test-key');

        $method = new ReflectionMethod(BackupService::class, 'decrypt');
        $method->setAccessible(true);
        $compressed = $method->invoke($service, $result['data'], 'test-key');
        $json = gzdecode($compressed);
        $payload = json_decode((string)$json, true);

        $this->assertSame(['platform_values'], $payload['meta']['tables']);
        $this->assertSame('Example', $payload['tables']['platform_values'][0]['name']);
        $this->assertSame(1, $result['meta']['table_count']);
        $this->assertSame(1, $result['meta']['row_count']);
    }

    public function testRestorePostgresForeignKeysUsesSavepointForValidateFailures(): void
    {
        $service = new BackupService();
        $connection = new class {
            /**
             * @var array<int, string>
             */
            public array $queries = [];

            private bool $abortedTransaction = false;

            private int $validateCalls = 0;

            public function execute(string $sql): object
            {
                $this->queries[] = $sql;

                if ($this->abortedTransaction && !str_starts_with($sql, 'ROLLBACK TO SAVEPOINT')) {
                    throw new RuntimeException('SQLSTATE[25P02]: In failed sql transaction');
                }

                if (str_starts_with($sql, 'ALTER TABLE') && str_contains($sql, 'VALIDATE CONSTRAINT')) {
                    $this->validateCalls++;
                    if ($this->validateCalls === 1) {
                        $this->abortedTransaction = true;

                        throw new RuntimeException('SQLSTATE[23503]: foreign key violation');
                    }
                }

                if (str_starts_with($sql, 'ROLLBACK TO SAVEPOINT')) {
                    $this->abortedTransaction = false;
                }

                return new class {
                };
            }
        };
        $driver = new class {
            public function quoteIdentifier(string $identifier): string
            {
                return "\"{$identifier}\"";
            }
        };
        $foreignKeys = [
            [
                'table' => 'awards_recommendations_events',
                'name' => 'awards_recommendations_events_event_id_fkey',
                'definition' => 'FOREIGN KEY (event_id) REFERENCES awards_events(id)',
            ],
            [
                'table' => 'awards_recommendations_events',
                'name' => 'awards_recommendations_events_recommendation_id_fkey',
                'definition' => 'FOREIGN KEY (recommendation_id) REFERENCES awards_recommendations(id)',
            ],
        ];

        $method = new ReflectionMethod(BackupService::class, 'restorePostgresForeignKeys');
        $method->setAccessible(true);
        $notValidatedConstraintCount = $method->invoke($service, $connection, $driver, $foreignKeys);

        $this->assertSame(1, $notValidatedConstraintCount);
        $this->assertContains('ROLLBACK TO SAVEPOINT kmp_fk_validate_0', $connection->queries);
        $this->assertContains(
            'ALTER TABLE "awards_recommendations_events" ADD CONSTRAINT "awards_recommendations_events_recommendation_id_fkey" FOREIGN KEY (recommendation_id) REFERENCES awards_recommendations(id) NOT VALID',
            $connection->queries,
        );
    }
}
