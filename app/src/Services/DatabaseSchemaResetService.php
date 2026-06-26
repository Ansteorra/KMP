<?php
declare(strict_types=1);

namespace App\Services;

use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Recreates the database schema from a backup schema manifest.
 */
class DatabaseSchemaResetService
{
    /**
     * @param array<string, mixed> $manifest
     * @param callable(array<string, mixed>):void|null $progressReporter
     */
    public function reset(array $manifest, ?callable $progressReporter = null): void
    {
        if (($manifest['version'] ?? null) !== 1 || !isset($manifest['tables']) || !is_array($manifest['tables'])) {
            throw new InvalidArgumentException('Backup schema manifest is missing or unsupported.');
        }

        $connection = ConnectionManager::get('default');
        $driver = $connection->getDriver();
        if (!$driver instanceof Mysql && !$driver instanceof Postgres) {
            throw new RuntimeException('Backup schema reset currently supports MySQL and PostgreSQL connections.');
        }

        $tables = $manifest['tables'];
        $plan = $this->buildResetPlan($driver, $tables);
        $this->report($progressReporter, 'resetting_schema', 'Dropping current database tables.');
        $this->dropExistingTables($connection, $driver);

        $created = 0;
        $tableCount = count($plan['tables']);
        foreach ($plan['tables'] as $tablePlan) {
            $this->report($progressReporter, 'creating_schema', sprintf(
                'Creating table %s (%d/%d).',
                $tablePlan['name'],
                $created + 1,
                $tableCount,
            ), [
                'current_table' => $tablePlan['name'],
                'table_count' => $tableCount,
                'tables_processed' => $created,
            ]);
            $connection->execute($tablePlan['sql']);
            $created++;
        }

        foreach ($plan['indexes'] as $sql) {
            $connection->execute($sql);
        }
        foreach ($plan['foreignKeys'] as $sql) {
            $connection->execute($sql);
        }
    }

    /**
     * @param array<string, mixed> $tables
     * @return array<string, array<int, mixed>>
     */
    private function buildResetPlan(Mysql|Postgres $driver, array $tables): array
    {
        $tableSql = [];
        foreach ($tables as $tableName => $tableSpec) {
            if (!is_string($tableName) || !is_array($tableSpec)) {
                continue;
            }
            $tableSql[] = [
                'name' => $tableName,
                'sql' => $this->createTableSql($driver, $tableName, $tableSpec),
            ];
        }

        return [
            'tables' => $tableSql,
            'indexes' => $this->indexSql($driver, $tables),
            'foreignKeys' => $this->foreignKeySql($driver, $tables),
        ];
    }

    /**
     * Drop all existing base tables from the current schema/database.
     *
     * @param mixed $connection Database connection
     */
    private function dropExistingTables($connection, Mysql|Postgres $driver): void
    {
        if ($driver instanceof Mysql) {
            $database = $connection->config()['database'] ?? null;
            $rows = $connection->execute(
                'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?',
                [$database, 'BASE TABLE'],
            )->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $connection->execute('SET FOREIGN_KEY_CHECKS = 0');
            try {
                foreach ($rows as $table) {
                    $connection->execute('DROP TABLE IF EXISTS ' . $driver->quoteIdentifier((string)$table));
                }
            } finally {
                $connection->execute('SET FOREIGN_KEY_CHECKS = 1');
            }

            return;
        }

        $rows = $connection->execute(
            'SELECT tablename FROM pg_tables WHERE schemaname = ANY(current_schemas(false))',
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($rows as $table) {
            $connection->execute('DROP TABLE IF EXISTS ' . $driver->quoteIdentifier((string)$table) . ' CASCADE');
        }
    }

    /**
     * @param array<string, mixed> $tableSpec
     */
    private function createTableSql(Mysql|Postgres $driver, string $tableName, array $tableSpec): string
    {
        $columns = $tableSpec['columns'] ?? [];
        if (!is_array($columns) || $columns === []) {
            throw new InvalidArgumentException("Backup schema table {$tableName} has no columns.");
        }

        $definitions = [];
        foreach ($columns as $columnName => $definition) {
            if (!is_string($columnName) || !is_array($definition)) {
                continue;
            }
            $definitions[] = $this->columnSql($driver, $columnName, $definition);
        }

        foreach (($tableSpec['constraints'] ?? []) as $constraint) {
            if (!is_array($constraint) || ($constraint['type'] ?? null) !== 'primary') {
                continue;
            }
            $constraintColumns = $this->columnsSql($driver, (array)($constraint['columns'] ?? []));
            if ($constraintColumns !== '') {
                $definitions[] = 'PRIMARY KEY (' . $constraintColumns . ')';
            }
        }

        return sprintf(
            'CREATE TABLE %s (%s)',
            $driver->quoteIdentifier($tableName),
            implode(', ', $definitions),
        );
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function columnSql(Mysql|Postgres $driver, string $columnName, array $definition): string
    {
        $type = (string)($definition['type'] ?? 'string');
        $autoIncrement = (bool)($definition['autoIncrement'] ?? $definition['auto_increment'] ?? false);
        $sql = $driver->quoteIdentifier($columnName)
            . ' '
            . $this->columnTypeSql($driver, $type, $definition, $autoIncrement);

        if ($autoIncrement && $driver instanceof Mysql) {
            $sql .= ' AUTO_INCREMENT';
        }

        if (!($definition['null'] ?? true)) {
            $sql .= ' NOT NULL';
        }

        if (!$autoIncrement && array_key_exists('default', $definition) && $definition['default'] !== null) {
            $sql .= ' DEFAULT ' . $this->defaultSql($driver, $definition['default'], $type);
        }

        return $sql;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function columnTypeSql(
        Mysql|Postgres $driver,
        string $type,
        array $definition,
        bool $autoIncrement,
    ): string {
        if ($autoIncrement && $driver instanceof Postgres) {
            return $type === 'biginteger' ? 'BIGSERIAL' : 'SERIAL';
        }

        $limit = isset($definition['length'])
            ? (int)$definition['length']
            : (isset($definition['limit']) ? (int)$definition['limit'] : null);

        return match ($type) {
            'biginteger' => 'BIGINT',
            'smallinteger' => 'SMALLINT',
            'tinyinteger' => $driver instanceof Mysql ? 'TINYINT' : 'SMALLINT',
            'integer' => $limit !== null && $limit > 0 && $driver instanceof Mysql ? "INT({$limit})" : 'INTEGER',
            'boolean' => $driver instanceof Mysql ? 'TINYINT(1)' : 'BOOLEAN',
            'string' => 'VARCHAR(' . max(1, $limit ?? 255) . ')',
            'text' => 'TEXT',
            'date' => 'DATE',
            'time' => 'TIME',
            'datetime', 'timestamp' => $driver instanceof Mysql ? 'DATETIME' : 'TIMESTAMP',
            'datetimefractional', 'timestampfractional' => $driver instanceof Mysql
                ? 'DATETIME(6)'
                : 'TIMESTAMP(6)',
            'timefractional' => 'TIME(6)',
            'float' => $driver instanceof Mysql ? 'DOUBLE' : 'DOUBLE PRECISION',
            'decimal' => $this->decimalTypeSql($definition),
            'json' => $driver instanceof Mysql ? 'JSON' : 'JSONB',
            default => throw new RuntimeException("Unsupported backup schema column type: {$type}"),
        };
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function decimalTypeSql(array $definition): string
    {
        $precision = (int)($definition['precision'] ?? $definition['length'] ?? $definition['limit'] ?? 10);
        $scale = (int)($definition['scale'] ?? 0);

        return sprintf('DECIMAL(%d,%d)', max(1, $precision), max(0, $scale));
    }

    /**
     * Format a column default expression for the target database.
     */
    private function defaultSql(Mysql|Postgres $driver, mixed $default, string $type): string
    {
        if (is_bool($default)) {
            return $driver instanceof Mysql ? ($default ? '1' : '0') : ($default ? 'TRUE' : 'FALSE');
        }
        if ($type === 'boolean' && $driver instanceof Postgres) {
            return in_array($default, [1, '1', 'true', 'TRUE'], true) ? 'TRUE' : 'FALSE';
        }
        if (is_int($default) || is_float($default)) {
            return (string)$default;
        }
        if (is_string($default) && strtoupper($default) === 'CURRENT_TIMESTAMP') {
            return 'CURRENT_TIMESTAMP';
        }
        if (is_string($default)) {
            $default = $this->normalizeMysqlStringLiteralDefault($default);
        }
        if ($type === 'json' && $driver instanceof Postgres) {
            $jsonDefault = (string)$default;
            json_decode($jsonDefault);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException(sprintf(
                    'Unsupported JSON default for PostgreSQL restore: %s',
                    json_last_error_msg(),
                ));
            }

            return "'" . str_replace("'", "''", $jsonDefault) . "'::jsonb";
        }

        return "'" . str_replace("'", "''", (string)$default) . "'";
    }

    /**
     * Strip MySQL character-set introducers captured by schema manifests.
     */
    private function normalizeMysqlStringLiteralDefault(string $default): string
    {
        $value = trim($default);
        if (!preg_match('/^_[A-Za-z0-9]+/', $value, $matches)) {
            return $default;
        }

        $literal = substr($value, strlen($matches[0]));
        if (str_starts_with($literal, "\\'") && str_ends_with($literal, "\\'")) {
            return str_replace("\\'", "'", substr($literal, 2, -2));
        }
        if (str_starts_with($literal, "'") && str_ends_with($literal, "'")) {
            return str_replace("\\'", "'", substr($literal, 1, -1));
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $tables
     * @return array<int, string>
     */
    private function indexSql(Mysql|Postgres $driver, array $tables): array
    {
        $sql = [];
        foreach ($tables as $tableName => $tableSpec) {
            if (!is_string($tableName) || !is_array($tableSpec)) {
                continue;
            }
            foreach (($tableSpec['constraints'] ?? []) as $constraintName => $constraint) {
                if (
                    !is_string($constraintName)
                    || !is_array($constraint)
                    || ($constraint['type'] ?? null) !== 'unique'
                ) {
                    continue;
                }
                $columnsSql = $this->columnsSql($driver, (array)($constraint['columns'] ?? []));
                if ($columnsSql === '') {
                    continue;
                }
                $sql[] = sprintf(
                    'CREATE UNIQUE INDEX %s ON %s (%s)',
                    $driver->quoteIdentifier($this->indexIdentifier($driver, $tableName, $constraintName)),
                    $driver->quoteIdentifier($tableName),
                    $columnsSql,
                );
            }
            foreach (($tableSpec['indexes'] ?? []) as $indexName => $index) {
                if (!is_string($indexName) || !is_array($index)) {
                    continue;
                }
                $columnsSql = $this->columnsSql($driver, (array)($index['columns'] ?? []));
                if ($columnsSql === '') {
                    continue;
                }
                $unique = ($index['type'] ?? '') === 'unique' ? 'UNIQUE ' : '';
                $sql[] = sprintf(
                    'CREATE %sINDEX %s ON %s (%s)',
                    $unique,
                    $driver->quoteIdentifier($this->indexIdentifier($driver, $tableName, $indexName)),
                    $driver->quoteIdentifier($tableName),
                    $columnsSql,
                );
            }
        }

        return $sql;
    }

    private function indexIdentifier(Mysql|Postgres $driver, string $tableName, string $indexName): string
    {
        if (!$driver instanceof Postgres) {
            return $indexName;
        }

        $identifier = $tableName . '_' . $indexName;
        if (strlen($identifier) <= 63) {
            return $identifier;
        }

        return substr($identifier, 0, 54) . '_' . substr(sha1($identifier), 0, 8);
    }

    /**
     * @param array<string, mixed> $tables
     * @return array<int, string>
     */
    private function foreignKeySql(Mysql|Postgres $driver, array $tables): array
    {
        $sql = [];
        foreach ($tables as $tableName => $tableSpec) {
            if (!is_string($tableName) || !is_array($tableSpec)) {
                continue;
            }
            foreach (($tableSpec['constraints'] ?? []) as $constraintName => $constraint) {
                if (
                    !is_string($constraintName)
                    || !is_array($constraint)
                    || ($constraint['type'] ?? null) !== 'foreign'
                ) {
                    continue;
                }
                $columnsSql = $this->columnsSql($driver, (array)($constraint['columns'] ?? []));
                $references = (array)($constraint['references'] ?? []);
                $referencedTable = $references[0] ?? null;
                $referencedColumns = $references[1] ?? [];
                $referencedColumnsSql = $this->columnsSql($driver, (array)$referencedColumns);
                if (!is_string($referencedTable) || $columnsSql === '' || $referencedColumnsSql === '') {
                    continue;
                }

                $foreignKeySql = sprintf(
                    'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
                    $driver->quoteIdentifier($tableName),
                    $driver->quoteIdentifier($constraintName),
                    $columnsSql,
                    $driver->quoteIdentifier($referencedTable),
                    $referencedColumnsSql,
                );
                if (!empty($constraint['delete'])) {
                    $foreignKeySql .= ' ON DELETE ' . $this->referentialActionSql($constraint['delete']);
                }
                if (!empty($constraint['update'])) {
                    $foreignKeySql .= ' ON UPDATE ' . $this->referentialActionSql($constraint['update']);
                }
                $sql[] = $foreignKeySql;
            }
        }

        return $sql;
    }

    /**
     * Validate and normalize a foreign key referential action.
     */
    private function referentialActionSql(mixed $action): string
    {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', (string)$action) ?? ''));
        $normalized = match ($normalized) {
            'NOACTION' => 'NO ACTION',
            'SETNULL' => 'SET NULL',
            'SETDEFAULT' => 'SET DEFAULT',
            default => $normalized,
        };
        $allowed = ['CASCADE', 'SET NULL', 'SET DEFAULT', 'RESTRICT', 'NO ACTION'];
        if (!in_array($normalized, $allowed, true)) {
            throw new InvalidArgumentException("Unsupported foreign key referential action: {$normalized}");
        }

        return $normalized;
    }

    /**
     * @param array<int, string> $columns
     */
    private function columnsSql(Mysql|Postgres $driver, array $columns): string
    {
        return implode(', ', array_map(
            fn(string $column): string => $driver->quoteIdentifier($column),
            array_values(array_filter($columns, 'is_string')),
        ));
    }

    /**
     * @param callable(array<string, mixed>):void|null $progressReporter
     * @param array<string, mixed> $context
     */
    private function report(?callable $progressReporter, string $phase, string $message, array $context = []): void
    {
        if ($progressReporter === null) {
            return;
        }

        $progressReporter(array_merge($context, [
            'phase' => $phase,
            'message' => $message,
        ]));
    }
}
