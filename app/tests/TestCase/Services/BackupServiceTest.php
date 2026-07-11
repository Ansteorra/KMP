<?php
declare(strict_types=1);

namespace App\Test\TestCase\Services;

use App\Services\BackupService;
use Cake\Database\Schema\TableSchema;
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
        $this->assertArrayHasKey('platform_values', $payload['schema']['tables']);
        $this->assertSame(1, $result['meta']['table_count']);
        $this->assertSame(1, $result['meta']['row_count']);
    }

    public function testLogicalArchiveExportAndValidationUseCompressedJsonWithoutUserEncryption(): void
    {
        ConnectionManager::setConfig('backup_scope_test', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Sqlite',
            'database' => ':memory:',
        ]);
        $connection = ConnectionManager::get('backup_scope_test');
        $connection->execute('CREATE TABLE platform_values (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $connection->insert('platform_values', ['id' => 1, 'name' => 'Logical archive']);

        $service = new BackupService('backup_scope_test');
        $archive = $service->exportLogicalArchive();
        $service->validateLogicalArchive($archive['data']);
        $protectedArchive = $service->encryptLogicalArchive($archive['data'], 'temporary-restore-key');
        $service->validateImportHeader($protectedArchive, 'temporary-restore-key');

        $json = gzdecode($archive['data']);
        $this->assertNotFalse($json);
        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(2, $payload['meta']['version']);
        $this->assertSame(['platform_values'], $payload['meta']['tables']);
        $this->assertSame('Logical archive', $payload['tables']['platform_values'][0]['name']);
        $this->assertSame(strlen($archive['data']), $archive['meta']['size_bytes']);
        $this->assertNotSame($archive['data'], $protectedArchive);
    }

    public function testHeaderValidationAcceptsArchiveExpandedBeyondPrefixLimit(): void
    {
        $service = new BackupService('backup_scope_test');
        $json = json_encode([
            'meta' => ['version' => 2],
            'padding' => str_repeat('x', 2 * 1024 * 1024),
        ], JSON_THROW_ON_ERROR);
        $archive = gzencode($json, 9);
        $this->assertNotFalse($archive);

        $protectedArchive = $service->encryptLogicalArchive($archive, 'large-archive-key');

        $service->validateImportHeader($protectedArchive, 'large-archive-key');
        $this->addToAssertionCount(1);
    }

    public function testHeaderValidationRejectsCorruptGzipWithoutEmittingWarnings(): void
    {
        $service = new BackupService('backup_scope_test');
        $encrypt = new ReflectionMethod(BackupService::class, 'encrypt');
        $protectedArchive = $encrypt->invoke($service, 'not a gzip archive', 'corrupt-archive-key');
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = sprintf('%d: %s', $severity, $message);

            return true;
        });
        try {
            try {
                $service->validateImportHeader($protectedArchive, 'corrupt-archive-key');
                $this->fail('Expected corrupt gzip validation to fail.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Failed to decompress backup data', $e->getMessage());
            }
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
    }

    public function testValidateImportPayloadRejectsWrongKeyBeforeRestore(): void
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
        $result = $service->export('correct-key');

        $service->validateImportHeader($result['data'], 'correct-key');
        $service->validateImportPayload($result['data'], 'correct-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $service->validateImportHeader($result['data'], 'wrong-key');
    }

    public function testExportPreservesOperationalSchemasWithoutTransientRows(): void
    {
        ConnectionManager::setConfig('backup_scope_test', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Sqlite',
            'database' => ':memory:',
        ]);
        $connection = ConnectionManager::get('backup_scope_test');
        $connection->execute(
            'CREATE TABLE backups (
                id INTEGER PRIMARY KEY,
                filename TEXT NOT NULL,
                status TEXT NOT NULL
            )',
        );
        $connection->execute('CREATE TABLE sessions (id TEXT PRIMARY KEY)');
        $connection->execute(
            'CREATE TABLE queued_jobs (
                id INTEGER PRIMARY KEY,
                job_task TEXT NOT NULL,
                status TEXT NOT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE queue_processes (
                id INTEGER PRIMARY KEY,
                pid INTEGER NOT NULL
            )',
        );
        $connection->insert('backups', [
            'id' => 1,
            'filename' => 'transient.kmpbackup',
            'status' => 'completed',
        ]);
        $connection->insert('queued_jobs', [
            'id' => 1,
            'job_task' => 'BackupRestore',
            'status' => 'Failed',
        ]);

        $service = new BackupService('backup_scope_test');
        $result = $service->export('test-key');

        $method = new ReflectionMethod(BackupService::class, 'decrypt');
        $method->setAccessible(true);
        $compressed = $method->invoke($service, $result['data'], 'test-key');
        $json = gzdecode($compressed);
        $payload = json_decode((string)$json, true);

        $this->assertSame([], $payload['meta']['tables']);
        $this->assertArrayNotHasKey('backups', $payload['tables']);
        $this->assertArrayNotHasKey('queued_jobs', $payload['tables']);
        $this->assertArrayHasKey('backups', $payload['schema']['tables']);
        $this->assertArrayHasKey('queued_jobs', $payload['schema']['tables']);
        $this->assertArrayHasKey('queue_processes', $payload['schema']['tables']);
        $this->assertArrayHasKey('sessions', $payload['schema']['tables']);
        $this->assertArrayHasKey('filename', $payload['schema']['tables']['backups']['columns']);
        $this->assertArrayHasKey('job_task', $payload['schema']['tables']['queued_jobs']['columns']);
    }

    public function testImportAddsOperationalSchemasForOlderPayloadsThatOmittedThem(): void
    {
        ConnectionManager::setConfig('backup_scope_test', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Sqlite',
            'database' => ':memory:',
        ]);
        $connection = ConnectionManager::get('backup_scope_test');
        $connection->execute(
            'CREATE TABLE backups (
                id INTEGER PRIMARY KEY,
                filename TEXT NOT NULL,
                status TEXT NOT NULL
            )',
        );
        $connection->execute('CREATE TABLE sessions (id TEXT PRIMARY KEY)');
        $connection->execute(
            'CREATE TABLE queued_jobs (
                id INTEGER PRIMARY KEY,
                job_task TEXT NOT NULL,
                status TEXT NOT NULL
            )',
        );
        $connection->execute(
            'CREATE TABLE queue_processes (
                id INTEGER PRIMARY KEY,
                pid INTEGER NOT NULL
            )',
        );

        $service = new BackupService('backup_scope_test');
        $method = new ReflectionMethod(BackupService::class, 'ensureOperationalSchemaTables');
        $method->setAccessible(true);
        $payload = $method->invoke($service, [
            'meta' => ['version' => 2],
            'schema' => [
                'version' => 1,
                'tables' => [],
            ],
            'tables' => [],
        ]);

        $this->assertArrayHasKey('backups', $payload['schema']['tables']);
        $this->assertArrayHasKey('queued_jobs', $payload['schema']['tables']);
        $this->assertArrayHasKey('queue_processes', $payload['schema']['tables']);
        $this->assertArrayHasKey('sessions', $payload['schema']['tables']);
        $this->assertArrayHasKey('filename', $payload['schema']['tables']['backups']['columns']);
        $this->assertArrayHasKey('job_task', $payload['schema']['tables']['queued_jobs']['columns']);
        $this->assertArrayNotHasKey('backups', $payload['tables']);
        $this->assertArrayNotHasKey('queued_jobs', $payload['tables']);
    }

    public function testExportUsesDirectQueriesForLongTableColumnAliases(): void
    {
        ConnectionManager::setConfig('backup_scope_test', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Sqlite',
            'database' => ':memory:',
        ]);
        $connection = ConnectionManager::get('backup_scope_test');
        $connection->execute(
            'CREATE TABLE AwardsRecommendationFeedbackRequestRecipients (
                id INTEGER PRIMARY KEY,
                feedback_request_id INTEGER NOT NULL
            )',
        );
        $connection->insert('AwardsRecommendationFeedbackRequestRecipients', [
            'id' => 1,
            'feedback_request_id' => 42,
        ]);

        $service = new BackupService('backup_scope_test');
        $result = $service->export('test-key');

        $method = new ReflectionMethod(BackupService::class, 'decrypt');
        $method->setAccessible(true);
        $compressed = $method->invoke($service, $result['data'], 'test-key');
        $json = gzdecode($compressed);
        $payload = json_decode((string)$json, true);

        $this->assertSame(['AwardsRecommendationFeedbackRequestRecipients'], $payload['meta']['tables']);
        $this->assertSame(
            42,
            $payload['tables']['AwardsRecommendationFeedbackRequestRecipients'][0]['feedback_request_id'],
        );
    }

    public function testExportPreservesMainAndPluginPhinxLogs(): void
    {
        ConnectionManager::setConfig('backup_scope_test', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Sqlite',
            'database' => ':memory:',
        ]);
        $connection = ConnectionManager::get('backup_scope_test');
        $connection->execute(
            'CREATE TABLE phinxlog (
                version INTEGER PRIMARY KEY,
                migration_name TEXT,
                start_time TEXT,
                end_time TEXT,
                breakpoint INTEGER
            )',
        );
        $connection->execute(
            'CREATE TABLE awards_phinxlog (
                version INTEGER PRIMARY KEY,
                migration_name TEXT,
                start_time TEXT,
                end_time TEXT,
                breakpoint INTEGER
            )',
        );
        $connection->execute('CREATE TABLE queued_jobs (id INTEGER PRIMARY KEY)');
        $connection->execute('CREATE TABLE sessions (id TEXT PRIMARY KEY)');
        $connection->insert('phinxlog', [
            'version' => 20260622141930,
            'migration_name' => 'Init',
            'start_time' => '2026-06-22 14:19:30',
            'end_time' => '2026-06-22 14:19:31',
            'breakpoint' => 0,
        ]);
        $connection->insert('awards_phinxlog', [
            'version' => 20260617172000,
            'migration_name' => 'CreateCourtAgendaTables',
            'start_time' => '2026-06-17 17:20:00',
            'end_time' => '2026-06-17 17:20:01',
            'breakpoint' => 0,
        ]);

        $service = new BackupService('backup_scope_test');
        $result = $service->export('test-key');

        $method = new ReflectionMethod(BackupService::class, 'decrypt');
        $method->setAccessible(true);
        $compressed = $method->invoke($service, $result['data'], 'test-key');
        $json = gzdecode($compressed);
        $payload = json_decode((string)$json, true);

        $this->assertSame(['awards_phinxlog', 'phinxlog'], $payload['meta']['tables']);
        $this->assertSame('Init', $payload['tables']['phinxlog'][0]['migration_name']);
        $this->assertSame(
            'CreateCourtAgendaTables',
            $payload['tables']['awards_phinxlog'][0]['migration_name'],
        );
        $this->assertArrayHasKey('phinxlog', $payload['meta']['migration_fingerprint']);
        $this->assertArrayHasKey('awards_phinxlog', $payload['meta']['migration_fingerprint']);
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
            'ALTER TABLE "awards_recommendations_events" ADD CONSTRAINT '
            . '"awards_recommendations_events_recommendation_id_fkey" FOREIGN KEY '
            . '(recommendation_id) REFERENCES awards_recommendations(id) NOT VALID',
            $connection->queries,
        );
    }

    public function testRestorePostgresForeignKeysRethrowsConstraintAddFailures(): void
    {
        $service = new BackupService();
        $connection = new class {
            /**
             * @var array<int, string>
             */
            public array $queries = [];

            public function execute(string $sql): object
            {
                $this->queries[] = $sql;

                if (str_starts_with($sql, 'ALTER TABLE') && str_contains($sql, 'ADD CONSTRAINT')) {
                    throw new RuntimeException('SQLSTATE[42P07]: constraint already exists');
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
        $foreignKeys = [[
            'table' => 'awards_recommendations_events',
            'name' => 'awards_recommendations_events_event_id_fkey',
            'definition' => 'FOREIGN KEY (event_id) REFERENCES awards_events(id)',
        ]];

        $method = new ReflectionMethod(BackupService::class, 'restorePostgresForeignKeys');
        $method->setAccessible(true);

        try {
            $method->invoke($service, $connection, $driver, $foreignKeys);
            $this->fail('Expected the constraint add failure to be rethrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('SQLSTATE[42P07]: constraint already exists', $e->getMessage());
        }

        $this->assertContains('ROLLBACK TO SAVEPOINT kmp_fk_validate_0', $connection->queries);
        $this->assertContains('RELEASE SAVEPOINT kmp_fk_validate_0', $connection->queries);
    }

    public function testImportRejectsObsoleteRowOnlyPayload(): void
    {
        $service = new BackupService();
        $payload = [
            'meta' => ['version' => 1],
            'tables' => [],
        ];
        $json = json_encode($payload);
        $this->assertNotFalse($json);
        $compressed = gzencode($json);
        $this->assertNotFalse($compressed);

        $method = new ReflectionMethod(BackupService::class, 'encrypt');
        $method->setAccessible(true);
        $encrypted = $method->invoke($service, $compressed, 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported backup format. Regenerate the backup with this KMP release.');

        $service->import($encrypted, 'test-key');
    }

    public function testImportRejectsMalformedV2PayloadBeforeSchemaReset(): void
    {
        $service = new BackupService();
        $payload = [
            'meta' => ['version' => 2],
            'tables' => [],
        ];
        $json = json_encode($payload);
        $this->assertNotFalse($json);
        $compressed = gzencode($json);
        $this->assertNotFalse($compressed);

        $method = new ReflectionMethod(BackupService::class, 'encrypt');
        $method->setAccessible(true);
        $encrypted = $method->invoke($service, $compressed, 'test-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid backup file structure');

        $service->import($encrypted, 'test-key');
    }

    public function testImportLogicalArchiveRejectsMalformedPayloadBeforeSchemaReset(): void
    {
        $service = new BackupService();
        $json = json_encode([
            'meta' => ['version' => 2],
            'tables' => [],
        ]);
        $this->assertNotFalse($json);
        $compressed = gzencode($json);
        $this->assertNotFalse($compressed);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid backup file structure');

        $service->importLogicalArchive($compressed);
    }

    public function testBuildRestoreTableMapIncludesCurrentTablesMissingFromBackup(): void
    {
        $service = new BackupService();
        $method = new ReflectionMethod(BackupService::class, 'buildRestoreTableMap');
        $method->setAccessible(true);

        $tables = $method->invoke($service, [
            'members' => [
                ['id' => 1, 'name' => 'Restored Member'],
            ],
            'phinxlog' => [
                ['version' => 20260622141930, 'migration_name' => 'Init'],
            ],
            'awards_phinxlog' => [
                ['version' => 20260617172000, 'migration_name' => 'CreateCourtAgendaTables'],
            ],
        ], [
            'awards_bestowals',
            'awards_phinxlog',
            'members',
            'phinxlog',
            'workflow_definitions',
        ]);

        $this->assertSame([
            'awards_bestowals' => [],
            'awards_phinxlog' => [
                ['version' => 20260617172000, 'migration_name' => 'CreateCourtAgendaTables'],
            ],
            'members' => [
                ['id' => 1, 'name' => 'Restored Member'],
            ],
            'phinxlog' => [
                ['version' => 20260622141930, 'migration_name' => 'Init'],
            ],
            'workflow_definitions' => [],
        ], $tables);
    }

    public function testBuildRestoreTableMapPreservesUnknownBackupTablesForCompatibilityLayer(): void
    {
        $service = new BackupService();
        $method = new ReflectionMethod(BackupService::class, 'buildRestoreTableMap');
        $method->setAccessible(true);

        $tables = $method->invoke($service, [
            'members' => [],
            'removed_table' => [
                ['id' => 1, 'legacy_value' => 'available in decoded payload'],
            ],
        ], [
            'members',
        ]);

        $this->assertSame([
            'members' => [],
        ], $tables);
    }

    public function testNormalizeRowForInsertPreservesLongJsonTextValues(): void
    {
        $service = new BackupService();
        $method = new ReflectionMethod(BackupService::class, 'normalizeRowForInsert');
        $method->setAccessible(true);

        $schema = new TableSchema('members');
        $schema->addColumn('additional_info', [
            'type' => 'text',
            'null' => false,
            'default' => '{}',
        ]);

        $value = [
            'CallIntoCourt' => 'With notice given to another person',
            'CourtAvailability' => str_repeat('Available for any regional court. ', 12),
        ];

        $row = $method->invoke($service, ['additional_info' => $value], ['additional_info'], $schema, true);

        $this->assertIsString($row['additional_info']);
        $this->assertGreaterThan(255, strlen($row['additional_info']));
        $this->assertJsonStringEqualsJsonString(json_encode($value), $row['additional_info']);
    }

    public function testNormalizeRowForInsertRestoresNullAdditionalInfoAsEmptyJsonObject(): void
    {
        $service = new BackupService();
        $method = new ReflectionMethod(BackupService::class, 'normalizeRowForInsert');
        $method->setAccessible(true);

        $schema = new TableSchema('members');
        $schema->addColumn('additional_info', [
            'type' => 'text',
            'null' => false,
            'default' => '{}',
        ]);
        $row = $method->invoke($service, ['additional_info' => null], ['additional_info'], $schema, true);
        $row = $method->invoke($service, ['additional_info' => null], ['additional_info'], $schema, true);
        $this->assertSame('{}', $row['additional_info']);
        $this->assertSame('{}', $row['additional_info']);
    }
}
