<?php
declare(strict_types=1);

namespace App\Services;

use Cake\Cache\Cache;
use Cake\Database\Connection;
use Cake\Database\Driver\Postgres;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Utility\Inflector;
use DateTimeImmutable;
use Exception;
use RuntimeException;
use Throwable;

/**
 * Database-agnostic backup and restore service.
 *
 * Exports all application tables via the ORM as JSON, compresses with gzip,
 * and encrypts with AES-256-GCM. Restore reverses the process.
 */
class BackupService
{
    use LocatorAwareTrait;

    private const FORMAT_VERSION = 2;
    private const CIPHER = 'aes-256-gcm';
    private const PBKDF2_ITERATIONS = 100000;
    private const PBKDF2_ALGO = 'sha256';
    private const SALT_LENGTH = 16;
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    /**
     * Tables excluded from backup (transient or migration-tracking data).
     *
     * Transient runtime state (queue, backup metadata) shouldn't cross
     * environments. Session tokens are per-environment and must not leak
     * across. Migration history (`phinxlog` + `*_phinxlog`) is excluded
     * via matching in isExcludedTable(); restoring it would replace the
     * target DB's migration state with the backup's state and cause
     * mis-skipped/re-run migrations on next startup.
     */
    private const EXCLUDED_TABLES = [
        'queued_jobs',
        'queue_processes',
        'backups',
        'sessions',
    ];

    /**
     * @param string $connectionName CakePHP connection name to back up or restore.
     */
    public function __construct(private readonly string $connectionName = 'default')
    {
    }

    /**
     * Return true when the table should never appear in a backup payload.
     * Matches exact names in EXCLUDED_TABLES plus any phinxlog-style
     * migration tracking tables ("phinxlog" and "<plugin>_phinxlog").
     */
    private static function isExcludedTable(string $tableName): bool
    {
        if (in_array($tableName, self::EXCLUDED_TABLES, true)) {
            return true;
        }
        // Matches the core `phinxlog` table and every plugin-scoped variant
        // (e.g. `activities_phinxlog`, `awards_phinxlog`, `officers_phinxlog`).
        return (bool)preg_match('/(?:^|_)phinxlog$/i', $tableName);
    }

    /**
     * Snapshot migration state for fingerprinting a backup.
     *
     * Returns a map of phinxlog-table-name → array of {version, migration_name}
     * rows sorted by version. The structure stays stable across engines so the
     * fingerprint can be compared between the bake environment (e.g. MySQL)
     * and the restore environment (e.g. Postgres).
     *
     * @return array<string, array<int, array{version: string, migration_name: string}>>
     */
    public function getMigrationFingerprint(): array
    {
        $connection = $this->connection();
        $schemaCollection = $connection->getSchemaCollection();
        $fingerprint = [];
        foreach ($schemaCollection->listTables() as $tableName) {
            if (!preg_match('/(?:^|_)phinxlog$/i', $tableName)) {
                continue;
            }
            try {
                $quoted = $connection->getDriver()->quoteIdentifier($tableName);
                $stmt = $connection->execute(
                    "SELECT version, migration_name FROM {$quoted} ORDER BY version ASC",
                );
                $rows = $stmt->fetchAll('assoc') ?: [];
            } catch (Exception $e) {
                // If a phinxlog variant has an unexpected column shape, skip it
                // rather than fail the whole export.
                Log::warning(sprintf(
                    'BackupService: skipping phinxlog "%s" in fingerprint (%s)',
                    $tableName,
                    $e->getMessage(),
                ));
                continue;
            }
            $fingerprint[$tableName] = array_map(static function ($row): array {
                return [
                    'version' => (string)($row['version'] ?? ''),
                    'migration_name' => (string)($row['migration_name'] ?? ''),
                ];
            }, $rows);
        }
        ksort($fingerprint);

        return $fingerprint;
    }

    /**
     * Export all application tables to an encrypted backup file.
     *
     * @param string $encryptionKey User-provided encryption key
     * @return array{data: string, meta: array} Encrypted bytes and metadata
     */
    public function export(string $encryptionKey): array
    {
        $connection = $this->connection();
        $schemaCollection = $connection->getSchemaCollection();
        $allTables = $schemaCollection->listTables();

        // Filter out excluded tables (queue jobs are transient)
        $tables = array_values(array_filter($allTables, function (string $table) {
            return !self::isExcludedTable($table);
        }));

        sort($tables);

        $migrationFingerprint = $this->getMigrationFingerprint();
        $schemaManifest = (new BackupSchemaManifestService())->export(self::EXCLUDED_TABLES);
        $payload = [
            'meta' => [
                'version' => self::FORMAT_VERSION,
                'created_at' => DateTime::now()->toIso8601String(),
                'table_count' => count($tables),
                'tables' => $tables,
                'migration_fingerprint' => $migrationFingerprint,
            ],
            'schema' => $schemaManifest,
            'tables' => [],
        ];

        $totalRows = 0;

        foreach ($tables as $tableName) {
            try {
                $tableObj = $this->connectionName === 'default'
                    ? $this->fetchTable(ucfirst(Inflector::camelize($tableName)))
                    : null;
            } catch (Exception $e) {
                // Fallback: query directly
                $tableObj = null;
            }

            $rows = [];
            if ($tableObj !== null) {
                // Include soft-deleted rows so backups are complete
                $finder = $tableObj->hasBehavior('Trash') ? 'withTrashed' : 'all';
                $query = $tableObj->find($finder)->disableHydration();
                foreach ($query as $row) {
                    $rows[] = $row;
                }
            } else {
                // Direct query fallback for tables without a Table class
                $stmt = $connection->execute("SELECT * FROM {$connection->getDriver()->quoteIdentifier($tableName)}");
                $rows = $stmt->fetchAll('assoc') ?: [];
            }

            $payload['tables'][$tableName] = $rows;
            $totalRows += count($rows);
        }

        $payload['meta']['row_count'] = $totalRows;

        // JSON → gzip → encrypt
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Failed to encode backup data as JSON');
        }

        $compressed = gzencode($json, 9);
        if ($compressed === false) {
            throw new RuntimeException('Failed to compress backup data');
        }

        $encrypted = $this->encrypt($compressed, $encryptionKey);

        return [
            'data' => $encrypted,
            'meta' => [
                'table_count' => count($tables),
                'row_count' => $totalRows,
                'size_bytes' => strlen($encrypted),
            ],
        ];
    }

    /**
     * Import (restore) from an encrypted backup.
     *
     * @param string $encryptedData Raw encrypted backup bytes
     * @param string $encryptionKey User-provided encryption key
     * @param callable(array<string, mixed>):void|null $progressReporter Restore progress callback
     * @param array{ignoreSchemaMismatch?: bool}|callable|null $options Restore options, or migration runner for BC
     * @param callable():void|null $migrationRunner Post-import migration callback
     * @return array{table_count: int, row_count: int, constraints_not_valid?: int,
     *   payload_upgrade?: array<string, mixed>, post_restore?: array<string, int>} Import statistics
     */
    public function import(
        string $encryptedData,
        string $encryptionKey,
        ?callable $progressReporter = null,
        array|callable|null $options = [],
        ?callable $migrationRunner = null,
    ): array {
        if (is_callable($options) && $migrationRunner === null) {
            $migrationRunner = $options;
            $options = [];
        }
        $ignoreSchemaMismatch = (bool)(is_array($options) ? ($options['ignoreSchemaMismatch'] ?? false) : false);

        $payload = $this->decodePayload($encryptedData, $encryptionKey, $progressReporter);

        $this->reportSchemaMismatch($payload, $ignoreSchemaMismatch, $progressReporter);

        $this->reportProgress($progressReporter, 'resetting_schema', 'Resetting database schema to backup structure.');
        (new DatabaseSchemaResetService())->reset($payload['schema'], $progressReporter);

        $stats = $this->importPayloadRows($payload, $progressReporter);

        if ($migrationRunner !== null) {
            $this->reportProgress($progressReporter, 'migrating', 'Applying application migrations.');
            $migrationRunner();
            $this->clearApplicationCachesAfterRestore();
            $this->reportProgress($progressReporter, 'migrated', 'Application migrations completed.', [
                'table_count' => $stats['table_count'],
                'tables_processed' => $stats['table_count'],
                'row_count' => $stats['row_count'],
                'rows_processed' => $stats['row_count'],
            ]);
        }

        $postRestoreStats = (new BackupRestoreCompatibilityService())->reconcile(
            $this->connection(),
            $payload,
            $progressReporter,
        );
        $this->clearApplicationCachesAfterRestore();
        $this->reportProgress($progressReporter, 'completed', 'Restore transaction committed.', [
            'table_count' => $stats['table_count'],
            'tables_processed' => $stats['table_count'],
            'row_count' => $stats['row_count'],
            'rows_processed' => $stats['row_count'],
            'post_restore' => $postRestoreStats,
        ]);

        $stats['post_restore'] = $postRestoreStats;

        return $stats;
    }

    /**
     * @param callable(array<string, mixed>):void|null $progressReporter
     * @return array{meta: array<string, mixed>, schema: array<string, mixed>, tables: array<string, mixed>}
     */
    private function decodePayload(
        string $encryptedData,
        string $encryptionKey,
        ?callable $progressReporter = null,
    ): array {
        // Decrypt → decompress → JSON
        $this->reportProgress($progressReporter, 'decrypting', 'Decrypting backup file.');
        $compressed = $this->decrypt($encryptedData, $encryptionKey);

        $this->reportProgress($progressReporter, 'decompressing', 'Decompressing backup payload.');
        $json = gzdecode($compressed);
        if ($json === false) {
            throw new RuntimeException('Failed to decompress backup data — wrong key or corrupt file');
        }

        $this->reportProgress($progressReporter, 'validating', 'Validating backup payload.');
        $payload = json_decode($json, true);
        if (!is_array($payload) || !isset($payload['meta']) || !is_array($payload['meta'])) {
            throw new RuntimeException('Invalid backup file structure');
        }
        if (($payload['meta']['version'] ?? null) !== self::FORMAT_VERSION) {
            throw new RuntimeException('Unsupported backup format. Regenerate the backup with this KMP release.');
        }
        if (
            !isset($payload['tables'], $payload['schema'])
            || !is_array($payload['schema'])
            || !is_array($payload['tables'])
        ) {
            throw new RuntimeException('Invalid backup file structure');
        }

        return $payload;
    }

    /**
     * @param array{tables: array<string, mixed>} $payload
     * @param callable(array<string, mixed>):void|null $progressReporter
     * @return array{table_count: int, row_count: int}
     */
    private function importPayloadRows(array $payload, ?callable $progressReporter = null): array
    {
        $tablesToRestore = [];
        foreach ($payload['tables'] as $tableName => $rows) {
            if (self::isExcludedTable($tableName)) {
                continue;
            }
            $tablesToRestore[$tableName] = is_array($rows) ? $rows : [];
        }

        $connection = $this->connection();
        $schemaCollection = $connection->getSchemaCollection();
        $tablesToRestore = $this->buildRestoreTableMap($tablesToRestore, $schemaCollection->listTables());
        $legacyPayloadTables = array_values(array_diff(array_keys($payload['tables']), array_keys($tablesToRestore)));
        $tableCount = count($tablesToRestore);
        $driver = $connection->getDriver();
        $isPostgres = $driver instanceof Postgres;
        $totalRows = 0;
        $processedTables = 0;
        $notValidatedConstraintCount = 0;

        $this->reportProgress($progressReporter, 'preparing', 'Preparing database restore transaction.', [
            'table_count' => $tableCount,
            'tables_processed' => 0,
            'rows_processed' => 0,
            'tables_from_backup' => count($payload['tables']),
            'tables_cleared' => count(array_diff(array_keys($tablesToRestore), array_keys($payload['tables']))),
            'legacy_tables_available' => count($legacyPayloadTables),
        ]);

        $connection->begin();
        try {
            if ($isPostgres) {
                // Managed Postgres blocks session_replication_role and DISABLE TRIGGER ALL.
                // Drop FK constraints transactionally, restore data, then re-add constraints.
                $this->reportProgress(
                    $progressReporter,
                    'preparing_constraints',
                    'Dropping foreign key constraints for restore.',
                    [
                        'table_count' => $tableCount,
                        'tables_processed' => $processedTables,
                        'rows_processed' => $totalRows,
                    ],
                );
                $droppedForeignKeys = $this->dropPostgresForeignKeys(
                    $connection,
                    $schemaCollection,
                    $driver,
                    array_keys($tablesToRestore),
                );
                $this->reportProgress(
                    $progressReporter,
                    'preparing_constraints',
                    sprintf('Dropped %d foreign key constraints. Starting table restore.', count($droppedForeignKeys)),
                    [
                        'table_count' => $tableCount,
                        'tables_processed' => $processedTables,
                        'rows_processed' => $totalRows,
                    ],
                );

                foreach ($tablesToRestore as $tableName => $rows) {
                    $this->reportProgress($progressReporter, 'restoring_table', sprintf(
                        'Restoring table %s (%d/%d).',
                        $tableName,
                        $processedTables + 1,
                        $tableCount,
                    ), [
                        'current_table' => $tableName,
                        'table_count' => $tableCount,
                        'tables_processed' => $processedTables,
                        'rows_processed' => $totalRows,
                    ]);

                    $tableSchema = $schemaCollection->describe($tableName);
                    $quotedTable = $driver->quoteIdentifier($tableName);
                    // Use transactional TRUNCATE on Postgres to avoid long-running DELETEs on large tables.
                    // CASCADE is required because FK constraints may reference this table.
                    $connection->execute("TRUNCATE TABLE {$quotedTable} RESTART IDENTITY CASCADE");

                    $rows = $this->filterRowsToCurrentColumns($rows, $tableSchema);
                    if (!empty($rows)) {
                        $columns = array_keys($rows[0]);
                        if ($columns === []) {
                            $rows = [];
                        } else {
                            $tableRowCount = count($rows);
                            $tableRowsProcessed = 0;
                            $nextProgressAt = 100;
                            $insertBatchSize = $this->getInsertBatchSize(count($columns), $isPostgres);
                            foreach (array_chunk($rows, $insertBatchSize) as $batch) {
                                $this->insertBatchRows(
                                    $connection,
                                    $driver,
                                    $quotedTable,
                                    $columns,
                                    $batch,
                                    $tableSchema,
                                    $isPostgres,
                                );

                                $insertedRows = count($batch);
                                $totalRows += $insertedRows;
                                $tableRowsProcessed += $insertedRows;

                                if ($tableRowsProcessed >= $nextProgressAt || $tableRowsProcessed >= $tableRowCount) {
                                    $this->reportProgress($progressReporter, 'restoring_table', sprintf(
                                        'Restoring table %s (%d/%d): %d/%d rows.',
                                        $tableName,
                                        $processedTables + 1,
                                        $tableCount,
                                        $tableRowsProcessed,
                                        $tableRowCount,
                                    ), [
                                        'current_table' => $tableName,
                                        'current_table_rows_processed' => $tableRowsProcessed,
                                        'current_table_row_count' => $tableRowCount,
                                        'table_count' => $tableCount,
                                        'tables_processed' => $processedTables,
                                        'rows_processed' => $totalRows,
                                    ]);
                                    $nextProgressAt += 100;
                                }
                            }
                        }
                    }

                    $processedTables++;
                    $this->reportProgress($progressReporter, 'table_restored', sprintf(
                        'Restored table %s (%d/%d).',
                        $tableName,
                        $processedTables,
                        $tableCount,
                    ), [
                        'current_table' => $tableName,
                        'table_count' => $tableCount,
                        'tables_processed' => $processedTables,
                        'rows_processed' => $totalRows,
                    ]);
                }

                $this->reportProgress(
                    $progressReporter,
                    'finalizing_constraints',
                    sprintf('Recreating %d foreign key constraints.', count($droppedForeignKeys)),
                    [
                        'table_count' => $tableCount,
                        'tables_processed' => $processedTables,
                        'rows_processed' => $totalRows,
                    ],
                );
                $notValidatedConstraintCount = $this->restorePostgresForeignKeys(
                    $connection,
                    $driver,
                    $droppedForeignKeys,
                );
                if ($notValidatedConstraintCount > 0) {
                    $this->reportProgress(
                        $progressReporter,
                        'finalizing_constraints',
                        sprintf(
                            'Restore completed with warnings: %d foreign '
                            . 'key constraints were left NOT VALID due '
                            . 'existing orphaned data.',
                            $notValidatedConstraintCount,
                        ),
                        [
                            'table_count' => $tableCount,
                            'tables_processed' => $processedTables,
                            'rows_processed' => $totalRows,
                            'constraints_not_valid' => $notValidatedConstraintCount,
                        ],
                    );
                }
            } else {
                // MySQL: disable FK and CHECK constraints for the duration of the import.
                $connection->execute('SET FOREIGN_KEY_CHECKS = 0');
                $connection->execute('SET check_constraint_checks = 0');

                foreach ($tablesToRestore as $tableName => $rows) {
                    $this->reportProgress($progressReporter, 'restoring_table', sprintf(
                        'Restoring table %s (%d/%d).',
                        $tableName,
                        $processedTables + 1,
                        $tableCount,
                    ), [
                        'current_table' => $tableName,
                        'table_count' => $tableCount,
                        'tables_processed' => $processedTables,
                        'rows_processed' => $totalRows,
                    ]);

                    $tableSchema = $schemaCollection->describe($tableName);
                    $quotedTable = $driver->quoteIdentifier($tableName);
                    $connection->execute("DELETE FROM {$quotedTable}");

                    $rows = $this->filterRowsToCurrentColumns($rows, $tableSchema);
                    if (!empty($rows)) {
                        $columns = array_keys($rows[0]);
                        if ($columns === []) {
                            $rows = [];
                        } else {
                            $tableRowCount = count($rows);
                            $tableRowsProcessed = 0;
                            $nextProgressAt = 100;
                            $insertBatchSize = $this->getInsertBatchSize(count($columns), $isPostgres);
                            foreach (array_chunk($rows, $insertBatchSize) as $batch) {
                                $this->insertBatchRows(
                                    $connection,
                                    $driver,
                                    $quotedTable,
                                    $columns,
                                    $batch,
                                    $tableSchema,
                                    $isPostgres,
                                );

                                $insertedRows = count($batch);
                                $totalRows += $insertedRows;
                                $tableRowsProcessed += $insertedRows;

                                if ($tableRowsProcessed >= $nextProgressAt || $tableRowsProcessed >= $tableRowCount) {
                                    $this->reportProgress($progressReporter, 'restoring_table', sprintf(
                                        'Restoring table %s (%d/%d): %d/%d rows.',
                                        $tableName,
                                        $processedTables + 1,
                                        $tableCount,
                                        $tableRowsProcessed,
                                        $tableRowCount,
                                    ), [
                                        'current_table' => $tableName,
                                        'current_table_rows_processed' => $tableRowsProcessed,
                                        'current_table_row_count' => $tableRowCount,
                                        'table_count' => $tableCount,
                                        'tables_processed' => $processedTables,
                                        'rows_processed' => $totalRows,
                                    ]);
                                    $nextProgressAt += 100;
                                }
                            }
                        }
                    }

                    $processedTables++;
                    $this->reportProgress($progressReporter, 'table_restored', sprintf(
                        'Restored table %s (%d/%d).',
                        $tableName,
                        $processedTables,
                        $tableCount,
                    ), [
                        'current_table' => $tableName,
                        'table_count' => $tableCount,
                        'tables_processed' => $processedTables,
                        'rows_processed' => $totalRows,
                    ]);
                }

                $connection->execute('SET FOREIGN_KEY_CHECKS = 1');
                $connection->execute('SET check_constraint_checks = 1');
            }

            $this->reportProgress($progressReporter, 'finalizing', 'Finalizing restore.', [
                'table_count' => $tableCount,
                'tables_processed' => $processedTables,
                'rows_processed' => $totalRows,
            ]);

            if ($isPostgres) {
                $this->resetPostgresSequences($connection, $schemaCollection, $driver, array_keys($tablesToRestore));
            }

            $connection->commit();
            $this->reportProgress($progressReporter, 'data_imported', 'Backup data import committed.', [
                'table_count' => $tableCount,
                'tables_processed' => $processedTables,
                'row_count' => $totalRows,
                'rows_processed' => $totalRows,
            ]);
        } catch (Exception $e) {
            $connection->rollback();
            try {
                if (!$isPostgres) {
                    $connection->execute('SET FOREIGN_KEY_CHECKS = 1');
                    $connection->execute('SET check_constraint_checks = 1');
                }
            } catch (Exception $ignored) {
            }
            throw new RuntimeException('Restore failed: ' . $e->getMessage(), 0, $e);
        }

        return [
            'table_count' => $tableCount,
            'row_count' => $totalRows,
            'constraints_not_valid' => $notValidatedConstraintCount,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param callable(array<string, mixed>):void|null $progressReporter
     */
    private function reportSchemaMismatch(
        array $payload,
        bool $ignoreSchemaMismatch,
        ?callable $progressReporter = null,
    ): void {
        if ($ignoreSchemaMismatch) {
            return;
        }

        $backupFingerprint = $payload['meta']['migration_fingerprint'] ?? null;
        if (!is_array($backupFingerprint) || empty($backupFingerprint)) {
            return;
        }

        $currentFingerprint = $this->getMigrationFingerprint();
        if ($currentFingerprint === $backupFingerprint) {
            return;
        }

        $diff = $this->describeFingerprintDiff($backupFingerprint, $currentFingerprint);
        $this->reportProgress(
            $progressReporter,
            'schema_mismatch',
            'Backup schema differs from the current database; applying schema-aware restore.',
            ['migration_fingerprint_diff' => $diff],
        );
    }

    /**
     * Build restore table map for the current schema.
     *
     * A backup is a full logical snapshot. When an older backup is restored into
     * a newer schema, tables introduced after the backup was taken must be
     * emptied so stale current-environment data cannot survive the restore.
     * Tables that no longer exist in the current schema are left in the decoded
     * payload for compatibility migrators, but they are not inserted directly.
     *
     * @param array<string, array<int, array<string, mixed>>> $payloadTables
     * @param array<int, string> $currentTables
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildRestoreTableMap(array $payloadTables, array $currentTables): array
    {
        $payloadTables = array_filter(
            $payloadTables,
            static fn(string $tableName): bool => !self::isExcludedTable($tableName),
            ARRAY_FILTER_USE_KEY,
        );

        $currentRestoreTables = [];
        foreach ($currentTables as $tableName) {
            if (!self::isExcludedTable($tableName)) {
                $currentRestoreTables[] = $tableName;
            }
        }
        sort($currentRestoreTables);

        $tablesToRestore = [];
        foreach ($currentRestoreTables as $tableName) {
            $tablesToRestore[$tableName] = $payloadTables[$tableName] ?? [];
        }

        return $tablesToRestore;
    }

    /**
     * Drop columns that existed in the backup environment but no longer exist
     * in the current schema.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterRowsToCurrentColumns(array $rows, TableSchemaInterface $tableSchema): array
    {
        if ($rows === []) {
            return $rows;
        }

        $currentColumns = array_flip($tableSchema->columns());

        return array_map(
            static fn(array $row): array => array_intersect_key($row, $currentColumns),
            $rows,
        );
    }

    /**
     * Return the configured backup target connection.
     */
    private function connection(): Connection
    {
        /** @var \Cake\Database\Connection $connection */
        $connection = ConnectionManager::get($this->connectionName);

        return $connection;
    }

    /**
     * Drop FK constraints for Postgres tables and return definitions for later re-add.
     *
     * @param array<int, string> $tableNames
     * @return array<int, array{table: string, name: string, definition: string}>
     */
    private function dropPostgresForeignKeys(
        $connection,
        $schemaCollection,
        $driver,
        array $tableNames,
    ): array {
        $dropped = [];

        foreach ($tableNames as $tableName) {
            $schema = $schemaCollection->describe($tableName);
            foreach ($schema->constraints() as $constraintName) {
                $constraint = $schema->getConstraint($constraintName);
                if (($constraint['type'] ?? '') !== 'foreign') {
                    continue;
                }

                $definition = $this->fetchPostgresForeignKeyDefinition($connection, $tableName, $constraintName);
                if ($definition === null) {
                    continue;
                }

                $quotedTable = $driver->quoteIdentifier($tableName);
                $quotedConstraint = $driver->quoteIdentifier($constraintName);
                $connection->execute("ALTER TABLE {$quotedTable} DROP CONSTRAINT {$quotedConstraint}");

                $dropped[] = [
                    'table' => $tableName,
                    'name' => $constraintName,
                    'definition' => $definition,
                ];
            }
        }

        return $dropped;
    }

    /**
     * Re-add previously dropped Postgres FK constraints.
     *
     * @param array<int, array{table: string, name: string, definition: string}> $droppedForeignKeys
     */
    private function restorePostgresForeignKeys($connection, $driver, array $droppedForeignKeys): int
    {
        $notValidatedConstraintCount = 0;

        foreach ($droppedForeignKeys as $index => $foreignKey) {
            $quotedTable = $driver->quoteIdentifier($foreignKey['table']);
            $quotedConstraint = $driver->quoteIdentifier($foreignKey['name']);
            $constraintDefinition = stripos($foreignKey['definition'], 'NOT VALID') === false
                ? $foreignKey['definition'] . ' NOT VALID'
                : $foreignKey['definition'];
            $connection->execute(
                "ALTER TABLE {$quotedTable} ADD CONSTRAINT {$quotedConstraint} {$constraintDefinition}",
            );

            $savepoint = sprintf('kmp_fk_validate_%d', $index);
            $connection->execute("SAVEPOINT {$savepoint}");
            try {
                $connection->execute(
                    "ALTER TABLE {$quotedTable} VALIDATE CONSTRAINT {$quotedConstraint}",
                );
                $connection->execute("RELEASE SAVEPOINT {$savepoint}");
            } catch (Exception $e) {
                // Postgres marks the transaction failed after VALIDATE errors; rollback to savepoint
                // so remaining constraints can still be recreated as NOT VALID.
                $connection->execute("ROLLBACK TO SAVEPOINT {$savepoint}");
                $connection->execute("RELEASE SAVEPOINT {$savepoint}");
                // Keep the constraint as NOT VALID when legacy/orphaned rows are present.
                $notValidatedConstraintCount++;
                Log::warning(sprintf(
                    'Restore warning: FK %s on %s left NOT VALID: %s',
                    $foreignKey['name'],
                    $foreignKey['table'],
                    $e->getMessage(),
                ));
            }
        }

        return $notValidatedConstraintCount;
    }

    /**
     * Fetch postgres foreign key definition.
     *
     * @param mixed $connection
     * @param string $tableName
     * @param string $constraintName
     * @return ?string
     */
    private function fetchPostgresForeignKeyDefinition($connection, string $tableName, string $constraintName): ?string
    {
        $sql = <<<'SQL'
SELECT pg_get_constraintdef(c.oid) AS definition
FROM pg_catalog.pg_constraint c
INNER JOIN pg_catalog.pg_class t ON t.oid = c.conrelid
INNER JOIN pg_catalog.pg_namespace n ON n.oid = t.relnamespace
WHERE c.contype = 'f'
  AND t.relname = ?
  AND c.conname = ?
  AND n.nspname = ANY(current_schemas(false))
LIMIT 1
SQL;

        $row = $connection->execute($sql, [$tableName, $constraintName])->fetch('assoc');
        if (!is_array($row)) {
            return null;
        }

        $definition = $row['definition'] ?? null;
        if (!is_string($definition) || $definition === '') {
            return null;
        }

        return $definition;
    }

    /**
     * Get insert batch size.
     *
     * @param int $columnCount
     * @param bool $isPostgres
     * @return int
     */
    private function getInsertBatchSize(int $columnCount, bool $isPostgres): int
    {
        if (!$isPostgres) {
            return 200;
        }

        // Keep a safety buffer under Postgres' 65535 parameter limit.
        $maxRowsByParams = intdiv(60000, max(1, $columnCount));

        return max(1, min(200, $maxRowsByParams));
    }

    /**
     * Reset Postgres sequences so new rows follow restored primary keys.
     *
     * @param array<int, string> $tableNames
     */
    private function resetPostgresSequences(
        Connection $connection,
        $schemaCollection,
        $driver,
        array $tableNames,
    ): void {
        foreach ($tableNames as $tableName) {
            $tableSchema = $schemaCollection->describe($tableName);
            if (!in_array('id', $tableSchema->columns(), true)) {
                continue;
            }

            $quotedTable = $driver->quoteIdentifier($tableName);
            $connection->execute(
                "SELECT setval(seq.sequence_name, COALESCE((SELECT MAX(id) FROM {$quotedTable}), 1), "
                . "COALESCE((SELECT MAX(id) FROM {$quotedTable}), 0) > 0)
                FROM (SELECT pg_get_serial_sequence(?, 'id') AS sequence_name) seq
                WHERE seq.sequence_name IS NOT NULL",
                [$tableName],
            );
        }
    }

    /**
     * @param array<int, string> $columns
     * @param array<int, array<string, mixed>> $batch
     */
    private function insertBatchRows(
        $connection,
        $driver,
        string $quotedTable,
        array $columns,
        array $batch,
        TableSchemaInterface $tableSchema,
        bool $isPostgres,
    ): void {
        if ($batch === []) {
            return;
        }

        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $valueSql = [];
        $params = [];

        foreach ($batch as $row) {
            $normalizedRow = $this->normalizeRowForInsert($row, $columns, $tableSchema, $isPostgres);
            $valueSql[] = $rowPlaceholder;
            foreach ($columns as $column) {
                $params[] = $normalizedRow[$column] ?? null;
            }
        }

        $quotedCols = implode(', ', array_map([$driver, 'quoteIdentifier'], $columns));
        $connection->execute(
            "INSERT INTO {$quotedTable} ({$quotedCols}) VALUES " . implode(', ', $valueSql),
            $params,
        );
    }

    /**
     * Build a short human-readable diff between backup and current
     * migration fingerprints for error messages.
     *
     * @param array<string, array<int, array{version: string, migration_name: string}>> $backup
     * @param array<string, array<int, array{version: string, migration_name: string}>> $current
     */
    private function describeFingerprintDiff(array $backup, array $current): string
    {
        $lines = [];
        $allTables = array_unique(array_merge(array_keys($backup), array_keys($current)));
        sort($allTables);
        foreach ($allTables as $table) {
            $backupVersions = array_column($backup[$table] ?? [], 'version');
            $currentVersions = array_column($current[$table] ?? [], 'version');
            $onlyInBackup = array_values(array_diff($backupVersions, $currentVersions));
            $onlyInCurrent = array_values(array_diff($currentVersions, $backupVersions));
            if ($onlyInBackup === [] && $onlyInCurrent === []) {
                continue;
            }
            $lines[] = sprintf('  %s:', $table);
            if ($onlyInBackup !== []) {
                $lines[] = sprintf(
                    '    only in backup  : %s',
                    implode(', ', array_slice($onlyInBackup, 0, 5))
                    . (count($onlyInBackup) > 5 ? sprintf(' (+%d more)', count($onlyInBackup) - 5) : ''),
                );
            }
            if ($onlyInCurrent !== []) {
                $lines[] = sprintf(
                    '    only in current : %s',
                    implode(', ', array_slice($onlyInCurrent, 0, 5))
                    . (count($onlyInCurrent) > 5 ? sprintf(' (+%d more)', count($onlyInCurrent) - 5) : ''),
                );
            }
        }

        return $lines === []
            ? '  (fingerprints differ in structure but no version-level diff)'
            : implode("\n", $lines);
    }

    /**
     * @param callable(array<string, mixed>):void|null $progressReporter
     * @param array<string, mixed> $context
     */
    private function reportProgress(
        ?callable $progressReporter,
        string $phase,
        string $message,
        array $context = [],
    ): void {
        if ($progressReporter === null) {
            return;
        }

        $progressReporter(array_merge($context, [
            'phase' => $phase,
            'message' => $message,
        ]));
    }

    /**
     * Clear Cake cache pools after successful restore so runtime state matches DB.
     */
    private function clearApplicationCachesAfterRestore(): void
    {
        foreach (Cache::configured() as $cacheConfig) {
            if (in_array($cacheConfig, ['restore_status', '_cake_core_', '_cake_routes_'], true)) {
                continue;
            }

            try {
                if (!Cache::clear($cacheConfig)) {
                    Log::warning(sprintf('Failed to clear cache config "%s" after restore.', $cacheConfig));
                }
            } catch (Throwable $e) {
                Log::warning(sprintf(
                    'Failed to clear cache config "%s" after restore: %s',
                    $cacheConfig,
                    $e->getMessage(),
                ));
            }
        }
    }

    /**
     * Normalize row values for MySQL inserts, coercing temporal values from
     * ISO-8601 JSON forms to DB-friendly SQL literal formats.
     *
     * @param array<string, mixed> $row
     * @param array<int, string> $columns
     * @return array<string, mixed>
     */
    private function normalizeRowForInsert(
        array $row,
        array $columns,
        TableSchemaInterface $tableSchema,
        bool $isPostgres,
    ): array {
        if ($isPostgres) {
            // Postgres rejects empty strings for non-text types.
            // Reuse MySQL empty-string coercion rules so non-nullable boolean/numeric columns
            // get a valid fallback (for example required => 0) instead of ''.
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row)) {
                    continue;
                }

                $columnType = $tableSchema->getColumnType($column);
                if ($columnType === null) {
                    continue;
                }

                if ($row[$column] === null) {
                    $normalizedNull = $this->normalizeNullForMysql($tableSchema, $column, $columnType);
                    if ($normalizedNull['converted']) {
                        $row[$column] = $normalizedNull['value'];
                    }

                    continue;
                }

                $normalizedComplex = $this->normalizeComplexValueForMysql($row[$column], $columnType);
                if ($normalizedComplex['converted']) {
                    $row[$column] = $normalizedComplex['value'];

                    continue;
                }

                if ($columnType === 'boolean') {
                    $row[$column] = $this->normalizeBooleanForPostgres($tableSchema, $column, $row[$column]);

                    continue;
                }

                if (!is_string($row[$column]) || trim($row[$column]) !== '') {
                    continue;
                }

                if (in_array($columnType, ['string', 'text', 'char', 'uuid'], true)) {
                    continue;
                }

                $normalizedEmpty = $this->normalizeEmptyStringForMysql($tableSchema, $column, $columnType);
                if ($normalizedEmpty['converted']) {
                    $row[$column] = $normalizedEmpty['value'];

                    continue;
                }

                $row[$column] = null;
            }

            return $row;
        }

        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                continue;
            }

            $columnType = $tableSchema->getColumnType($column);
            if ($columnType === null) {
                continue;
            }

            if ($row[$column] === null) {
                $normalizedNull = $this->normalizeNullForMysql($tableSchema, $column, $columnType);
                if ($normalizedNull['converted']) {
                    $row[$column] = $normalizedNull['value'];
                }
                continue;
            }

            $normalizedComplex = $this->normalizeComplexValueForMysql($row[$column], $columnType);
            if ($normalizedComplex['converted']) {
                $row[$column] = $normalizedComplex['value'];
                continue;
            }

            $normalizedScalar = $this->normalizeColumnScalarForMysql($row[$column], $columnType);
            if ($normalizedScalar !== $row[$column]) {
                $row[$column] = $normalizedScalar;
                continue;
            }

            if (is_string($row[$column]) && $row[$column] === '') {
                $normalizedEmpty = $this->normalizeEmptyStringForMysql($tableSchema, $column, $columnType);
                if ($normalizedEmpty['converted']) {
                    $row[$column] = $normalizedEmpty['value'];
                }
                continue;
            }

            if (!is_string($row[$column])) {
                continue;
            }

            $normalizedTemporal = $this->normalizeTemporalValueForMysql($row[$column], $columnType);
            if ($normalizedTemporal !== null) {
                $row[$column] = $normalizedTemporal;
            }
        }

        return $row;
    }

    /**
     * Normalize Postgres boolean column values from mixed JSON payload forms.
     */
    private function normalizeBooleanForPostgres(TableSchemaInterface $tableSchema, string $column, mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value) || is_float($value)) {
            return ((int)$value) === 0 ? 0 : 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '') {
                $fallback = $this->normalizeEmptyStringForMysql($tableSchema, $column, 'boolean');

                return $fallback['converted'] ? $fallback['value'] : null;
            }
            if (in_array($normalized, ['1', 'true', 't', 'yes', 'y', 'on'], true)) {
                return 1;
            }
            if (in_array($normalized, ['0', 'false', 'f', 'no', 'n', 'off'], true)) {
                return 0;
            }
        }

        if ($value === null) {
            $fallback = $this->normalizeNullForMysql($tableSchema, $column, 'boolean');
            if ($fallback['converted']) {
                return $fallback['value'];
            }
        }

        return $value;
    }

    /**
     * Convert ISO-8601 temporal strings to MySQL-compatible temporal formats.
     */
    private function normalizeTemporalValueForMysql(string $value, string $columnType): ?string
    {
        $formatMap = [
            'datetime' => 'Y-m-d H:i:s',
            'datetimefractional' => 'Y-m-d H:i:s.u',
            'timestamp' => 'Y-m-d H:i:s',
            'timestampfractional' => 'Y-m-d H:i:s.u',
            'date' => 'Y-m-d',
            'time' => 'H:i:s',
            'timefractional' => 'H:i:s.u',
        ];

        if (!isset($formatMap[$columnType])) {
            return null;
        }

        try {
            $parsed = new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }

        $normalized = $parsed->format($formatMap[$columnType]);
        if (str_ends_with($formatMap[$columnType], '.u')) {
            return rtrim(rtrim($normalized, '0'), '.');
        }

        return $normalized;
    }

    /**
     * Coerce empty-string values for MySQL numeric/boolean/temporal columns.
     *
     * @return array{converted: bool, value: mixed}
     */
    private function normalizeEmptyStringForMysql(
        TableSchemaInterface $tableSchema,
        string $column,
        string $columnType,
    ): array {
        $columnDefinition = $tableSchema->getColumn($column) ?? [];
        if (array_key_exists('default', $columnDefinition) && $columnDefinition['default'] !== null) {
            return [
                'converted' => true,
                'value' => $this->normalizeColumnScalarForMysql($columnDefinition['default'], $columnType),
            ];
        }

        if ($tableSchema->isNullable($column)) {
            return ['converted' => true, 'value' => null];
        }

        return match ($columnType) {
            'boolean', 'integer', 'biginteger', 'smallinteger', 'tinyinteger' => ['converted' => true, 'value' => 0],
            'float', 'decimal' => ['converted' => true, 'value' => 0.0],
            default => ['converted' => false, 'value' => ''],
        };
    }

    /**
     * Coerce nulls for non-nullable MySQL columns.
     *
     * @return array{converted: bool, value: mixed}
     */
    private function normalizeNullForMysql(
        TableSchemaInterface $tableSchema,
        string $column,
        string $columnType,
    ): array {
        if ($tableSchema->isNullable($column)) {
            return ['converted' => false, 'value' => null];
        }

        $columnDefinition = $tableSchema->getColumn($column) ?? [];
        if (array_key_exists('default', $columnDefinition) && $columnDefinition['default'] !== null) {
            return [
                'converted' => true,
                'value' => $this->normalizeColumnScalarForMysql($columnDefinition['default'], $columnType),
            ];
        }

        if ($column === 'additional_info' && in_array($columnType, ['json', 'jsonb', 'text', 'string'], true)) {
            return ['converted' => true, 'value' => '{}'];
        }

        return match ($columnType) {
            'boolean', 'integer', 'biginteger', 'smallinteger', 'tinyinteger' => ['converted' => true, 'value' => 0],
            'float', 'decimal' => ['converted' => true, 'value' => 0.0],
            'json', 'jsonb' => ['converted' => true, 'value' => '{}'],
            'text', 'string', 'char', 'uuid' => ['converted' => true, 'value' => ''],
            default => ['converted' => false, 'value' => null],
        };
    }

    /**
     * Coerce scalar values into DB-safe representations for MySQL inserts.
     */
    private function normalizeColumnScalarForMysql(mixed $value, string $columnType): mixed
    {
        if (is_bool($value) && $this->isNumericColumnType($columnType)) {
            return $value ? 1 : 0;
        }

        if ((is_int($value) || is_float($value)) && $columnType === 'boolean') {
            return ((int)$value) === 0 ? 0 : 1;
        }

        if (is_string($value) && $value !== '' && $this->isIntegerLikeType($columnType) && is_numeric($value)) {
            return (int)$value;
        }

        if (
            is_string($value) && $value !== '' && in_array(
                $columnType,
                ['float', 'decimal'],
                true,
            ) && is_numeric($value)
        ) {
            return (float)$value;
        }

        return $value;
    }

    /**
     * Coerce array/object values for MySQL inserts.
     *
     * @return array{converted: bool, value: mixed}
     */
    private function normalizeComplexValueForMysql(mixed $value, string $columnType): array
    {
        if (!is_array($value) && !is_object($value)) {
            return ['converted' => false, 'value' => $value];
        }

        if (!in_array($columnType, ['json', 'jsonb', 'text', 'string', 'char', 'uuid'], true)) {
            return ['converted' => false, 'value' => $value];
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return ['converted' => true, 'value' => ''];
        }

        return ['converted' => true, 'value' => $encoded];
    }

    /**
     * Check if integer like type.
     *
     * @param string $columnType
     * @return bool
     */
    private function isIntegerLikeType(string $columnType): bool
    {
        return in_array($columnType, ['boolean', 'integer', 'biginteger', 'smallinteger', 'tinyinteger'], true);
    }

    /**
     * Check if numeric column type.
     *
     * @param string $columnType
     * @return bool
     */
    private function isNumericColumnType(string $columnType): bool
    {
        return $this->isIntegerLikeType($columnType) || in_array($columnType, ['float', 'decimal'], true);
    }

    /**
     * Encrypt data with AES-256-GCM using a PBKDF2-derived key.
     *
     * Output format: salt(16) + iv(12) + tag(16) + ciphertext
     */
    private function encrypt(string $data, string $passphrase): string
    {
        $salt = random_bytes(self::SALT_LENGTH);
        $key = $this->deriveKey($passphrase, $salt);
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $data,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed');
        }

        return $salt . $iv . $tag . $ciphertext;
    }

    /**
     * Decrypt data encrypted by encrypt().
     */
    private function decrypt(string $data, string $passphrase): string
    {
        $minLen = self::SALT_LENGTH + self::IV_LENGTH + self::TAG_LENGTH + 1;
        if (strlen($data) < $minLen) {
            throw new RuntimeException('Invalid encrypted data — too short');
        }

        $offset = 0;
        $salt = substr($data, $offset, self::SALT_LENGTH);
        $offset += self::SALT_LENGTH;
        $iv = substr($data, $offset, self::IV_LENGTH);
        $offset += self::IV_LENGTH;
        $tag = substr($data, $offset, self::TAG_LENGTH);
        $offset += self::TAG_LENGTH;
        $ciphertext = substr($data, $offset);

        $key = $this->deriveKey($passphrase, $salt);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed — wrong encryption key or corrupt file');
        }

        return $plaintext;
    }

    /**
     * Derive a 256-bit key from a passphrase using PBKDF2.
     */
    private function deriveKey(string $passphrase, string $salt): string
    {
        return hash_pbkdf2(self::PBKDF2_ALGO, $passphrase, $salt, self::PBKDF2_ITERATIONS, 32, true);
    }
}
