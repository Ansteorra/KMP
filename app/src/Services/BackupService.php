<?php

declare(strict_types=1);

namespace App\Services;

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
    public function import(string $encryptedData, string $encryptionKey): array
    {
        // Decrypt → decompress → JSON
        $compressed = $this->decrypt($encryptedData, $encryptionKey);

        $json = gzdecode($compressed);
        if ($json === false) {
            throw new RuntimeException('Failed to decompress backup data — wrong key or corrupt file');
        }

        $payload = json_decode($json, true);
        if (!is_array($payload) || !isset($payload['tables']) || !isset($payload['meta'])) {
            throw new RuntimeException('Invalid backup file structure');
        }

        $connection = ConnectionManager::get('default');
        $driver = $connection->getDriver();
        $isPostgres = $driver instanceof \Cake\Database\Driver\Postgres;
        $totalRows = 0;

        $connection->begin();
        try {
            // Disable FK checks
            if ($isPostgres) {
                $connection->execute('SET session_replication_role = replica');
            } else {
                $connection->execute('SET FOREIGN_KEY_CHECKS = 0');
            }

            foreach ($payload['tables'] as $tableName => $rows) {
                // Skip excluded tables even if they snuck into an old backup
                if (in_array($tableName, self::EXCLUDED_TABLES, true)) {
                    continue;
                }

                $quotedTable = $driver->quoteIdentifier($tableName);

                // Truncate
                if ($isPostgres) {
                    $connection->execute("TRUNCATE TABLE {$quotedTable} CASCADE");
                } else {
                    $connection->execute("TRUNCATE TABLE {$quotedTable}");
                }

                // Insert rows in batches
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $batchSize = 100;
                    foreach (array_chunk($rows, $batchSize) as $batch) {
                        foreach ($batch as $row) {
                            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                            $quotedCols = implode(', ', array_map([$driver, 'quoteIdentifier'], $columns));
                            $sql = "INSERT INTO {$quotedTable} ({$quotedCols}) VALUES ({$placeholders})";
                            $connection->execute($sql, array_values($row));
                        }
                        $totalRows += count($batch);
                    }
                }
            }

            // Re-enable FK checks
            if ($isPostgres) {
                $connection->execute('SET session_replication_role = DEFAULT');
                // Reset sequences
                foreach ($payload['tables'] as $tableName => $rows) {
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
            'table_count' => count($payload['tables']),
            'row_count' => $totalRows,
        ];
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
