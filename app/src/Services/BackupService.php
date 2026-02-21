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
                $query = $tableObj->find()->disableHydration();
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
            // Disable FK checks
            if ($isPostgres) {
                $connection->execute('SET session_replication_role = replica');
            } else {
                $connection->execute('SET FOREIGN_KEY_CHECKS = 0');
            }

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

                // Clear table contents.
                // IMPORTANT: In MySQL, TRUNCATE causes implicit commit and breaks rollback safety.
                // Use DELETE for non-Postgres restores so failed imports can roll back cleanly.
                if ($isPostgres) {
                    $connection->execute("TRUNCATE TABLE {$quotedTable} CASCADE");
                } else {
                    $connection->execute("DELETE FROM {$quotedTable}");
                }

                // Insert rows in batches
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $batchSize = 100;
                    foreach (array_chunk($rows, $batchSize) as $batch) {
                        foreach ($batch as $row) {
                            $normalizedRow = $this->normalizeRowForInsert($row, $columns, $tableSchema, $isPostgres);
                            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                            $quotedCols = implode(', ', array_map([$driver, 'quoteIdentifier'], $columns));
                            $sql = "INSERT INTO {$quotedTable} ({$quotedCols}) VALUES ({$placeholders})";
                            $connection->execute(
                                $sql,
                                array_map(
                                    static fn(string $column) => $normalizedRow[$column] ?? null,
                                    $columns,
                                ),
                            );
                        }
                        $totalRows += count($batch);
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

            $this->reportProgress($progressReporter, 'finalizing', 'Finalizing restore and enabling constraints.', [
                'table_count' => $tableCount,
                'tables_processed' => $processedTables,
                'rows_processed' => $totalRows,
            ]);

            // Re-enable FK checks
            if ($isPostgres) {
                $connection->execute('SET session_replication_role = DEFAULT');
                // Reset sequences
                foreach ($tablesToRestore as $tableName => $rows) {
                    if (!empty($rows) && isset($rows[0]['id'])) {
                        $maxId = max(array_column($rows, 'id'));
                        $seqName = "{$tableName}_id_seq";
                        $connection->execute("SELECT setval('{$seqName}', {$maxId}, true)");
                    }
                }
            } else {
                $connection->execute('SET FOREIGN_KEY_CHECKS = 1');
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
            // Re-enable FK checks on failure
            try {
                if ($isPostgres) {
                    $connection->execute('SET session_replication_role = DEFAULT');
                } else {
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

        if (!in_array($columnType, ['json', 'text', 'string', 'char', 'uuid'], true)) {
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
