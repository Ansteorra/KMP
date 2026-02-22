<?php

declare(strict_types=1);

namespace App\Services;

use Cake\Cache\Cache;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Exception;
use RuntimeException;

/**
 * Database-agnostic backup and restore service.
 *
 * Exports all application tables via the ORM as JSON, compresses with gzip,
 * and encrypts with AES-256-GCM. Restore reverses the process.
 */
class BackupService
{
    use LocatorAwareTrait;

    private const CIPHER = 'aes-256-gcm';
    private const PBKDF2_ITERATIONS = 100000;
    private const PBKDF2_ALGO = 'sha256';
    private const SALT_LENGTH = 16;
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    /**
     * Tables excluded from backup (transient or migration-tracking data).
     */
    private const EXCLUDED_TABLES = [
        'queued_jobs',
        'queue_processes',
        'backups',
    ];

    /**
     * Export all application tables to an encrypted backup file.
     *
     * @param string $encryptionKey User-provided encryption key
     * @return array{data: string, meta: array} Encrypted bytes and metadata
     */
    public function export(string $encryptionKey): array
    {
        $connection = ConnectionManager::get('default');
        $schemaCollection = $connection->getSchemaCollection();
        $allTables = $schemaCollection->listTables();

        // Filter out excluded tables (queue jobs are transient)
        $tables = array_values(array_filter($allTables, function (string $table) {
            return !in_array($table, self::EXCLUDED_TABLES, true);
        }));

        sort($tables);

        $payload = [
            'meta' => [
                'version' => 1,
                'created_at' => DateTime::now()->toIso8601String(),
                'table_count' => count($tables),
                'tables' => $tables,
            ],
            'tables' => [],
        ];

        $totalRows = 0;

        foreach ($tables as $tableName) {
            try {
                $tableObj = $this->fetchTable(
                    ucfirst(\Cake\Utility\Inflector::camelize($tableName))
                );
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
     * @return array{table_count: int, row_count: int} Import statistics
     */
    public function import(string $encryptedData, string $encryptionKey, ?callable $progressReporter = null): array
    {
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
        if (!is_array($payload) || !isset($payload['tables']) || !isset($payload['meta'])) {
            throw new RuntimeException('Invalid backup file structure');
        }

        $tablesToRestore = [];
        foreach ($payload['tables'] as $tableName => $rows) {
            if (in_array($tableName, self::EXCLUDED_TABLES, true)) {
                continue;
            }
            $tablesToRestore[$tableName] = is_array($rows) ? $rows : [];
        }

        $tableCount = count($tablesToRestore);
        $this->reportProgress($progressReporter, 'preparing', 'Preparing database restore transaction.', [
            'table_count' => $tableCount,
            'tables_processed' => 0,
            'rows_processed' => 0,
        ]);

        $connection = ConnectionManager::get('default');
        $schemaCollection = $connection->getSchemaCollection();
        $driver = $connection->getDriver();
        $isPostgres = $driver instanceof \Cake\Database\Driver\Postgres;
        $totalRows = 0;
        $processedTables = 0;

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

                    if (!empty($rows)) {
                        $columns = array_keys($rows[0]);
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
                $notValidatedConstraintCount = $this->restorePostgresForeignKeys($connection, $driver, $droppedForeignKeys);
                if ($notValidatedConstraintCount > 0) {
                    $this->reportProgress(
                        $progressReporter,
                        'finalizing_constraints',
                        sprintf(
                            'Restore completed with warnings: %d foreign key constraints were left NOT VALID due existing orphaned data.',
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
                // MySQL: disable FK checks for the duration of the import.
                $connection->execute('SET FOREIGN_KEY_CHECKS = 0');

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

                    if (!empty($rows)) {
                        $columns = array_keys($rows[0]);
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
            }

            $this->reportProgress($progressReporter, 'finalizing', 'Finalizing restore.', [
                'table_count' => $tableCount,
                'tables_processed' => $processedTables,
                'rows_processed' => $totalRows,
            ]);

            if ($isPostgres) {
                // Reset sequences so auto-increment continues from the restored max id.
                foreach ($tablesToRestore as $tableName => $rows) {
                    if (!empty($rows) && isset($rows[0]['id'])) {
                        $maxId = max(array_column($rows, 'id'));
                        $seqName = "{$tableName}_id_seq";
                        $connection->execute("SELECT setval('{$seqName}', {$maxId}, true)");
                    }
                }
            }

            $connection->commit();
            $this->clearApplicationCachesAfterRestore();
            $this->reportProgress($progressReporter, 'completed', 'Restore transaction committed.', [
                'table_count' => $tableCount,
                'tables_processed' => $processedTables,
                'rows_processed' => $totalRows,
            ]);
        } catch (Exception $e) {
            $connection->rollback();
            try {
                if (!$isPostgres) {
                    $connection->execute('SET FOREIGN_KEY_CHECKS = 1');
                }
            } catch (Exception $ignored) {
            }
            throw new RuntimeException('Restore failed: ' . $e->getMessage(), 0, $e);
        }

        return [
            'table_count' => $tableCount,
            'row_count' => $totalRows,
        ];
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
     * @param callable(array<string, mixed>):void|null $progressReporter
     * @param array<string, mixed> $context
     */
    private function reportProgress(?callable $progressReporter, string $phase, string $message, array $context = []): void
    {
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
            } catch (\Throwable $e) {
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
            $parsed = new \DateTimeImmutable($value);
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

        return match ($columnType) {
            'boolean', 'integer', 'biginteger', 'smallinteger', 'tinyinteger' => ['converted' => true, 'value' => 0],
            'float', 'decimal' => ['converted' => true, 'value' => 0.0],
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

        if (is_string($value) && $value !== '' && in_array($columnType, ['float', 'decimal'], true) && is_numeric($value)) {
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

    private function isIntegerLikeType(string $columnType): bool
    {
        return in_array($columnType, ['boolean', 'integer', 'biginteger', 'smallinteger', 'tinyinteger'], true);
    }

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
