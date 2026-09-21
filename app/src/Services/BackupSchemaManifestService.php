<?php
declare(strict_types=1);

namespace App\Services;

use Cake\Database\Driver\Postgres;
use Cake\Datasource\ConnectionManager;

/**
 * Builds a database-neutral schema manifest for backup payloads.
 */
class BackupSchemaManifestService
{
    /**
     * @param array<int, string> $excludedTables
     * @param string $connectionName CakePHP connection name to describe.
     * @return array<string, mixed>
     */
    public function export(array $excludedTables = [], string $connectionName = 'default'): array
    {
        $connection = ConnectionManager::get($connectionName);
        $schemaCollection = $connection->getSchemaCollection();
        $tables = array_values(array_filter(
            $schemaCollection->listTables(),
            fn(string $table): bool => !in_array($table, $excludedTables, true),
        ));
        sort($tables);

        $manifestTables = [];
        foreach ($tables as $tableName) {
            $schema = $schemaCollection->describe($tableName);
            $columns = [];
            foreach ($schema->columns() as $column) {
                $definition = $schema->getColumn($column) ?? [];
                $definition['type'] = $schema->getColumnType($column) ?? ($definition['type'] ?? 'string');
                $columns[$column] = $this->normalizeDefinition($definition);
            }

            $constraints = [];
            foreach ($schema->constraints() as $constraintName) {
                $constraints[$constraintName] = $this->normalizeDefinition(
                    $schema->getConstraint($constraintName) ?? [],
                );
            }

            $indexes = [];
            if (method_exists($schema, 'indexes') && method_exists($schema, 'getIndex')) {
                foreach ($schema->indexes() as $indexName) {
                    $indexes[$indexName] = $this->normalizeDefinition($schema->getIndex($indexName) ?? []);
                }
            }

            // Cake's PostgreSQL schema reflection omits partial-index predicates.
            // Preserve them so restore cannot turn conditional uniqueness into a table-wide constraint.
            if ($connection->getDriver() instanceof Postgres) {
                $predicates = $connection->execute(
                    'SELECT c.relname AS name, pg_get_expr(i.indpred, i.indrelid) AS predicate '
                    . 'FROM pg_catalog.pg_index i JOIN pg_catalog.pg_class c ON c.oid = i.indexrelid '
                    . 'WHERE i.indrelid = to_regclass(?) AND i.indpred IS NOT NULL',
                    [$connection->getDriver()->quoteIdentifier($tableName)],
                )->fetchAll('assoc');
                foreach ($predicates as $index) {
                    $name = $index['name'];
                    if (isset($constraints[$name])) {
                        $constraints[$name]['where'] = $index['predicate'];
                    } elseif (isset($indexes[$name])) {
                        $indexes[$name]['where'] = $index['predicate'];
                    }
                }
            }

            $manifestTables[$tableName] = [
                'columns' => $columns,
                'constraints' => $constraints,
                'indexes' => $indexes,
            ];
        }

        return [
            'version' => 1,
            'tables' => $manifestTables,
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function normalizeDefinition(array $definition): array
    {
        foreach ($definition as $key => $value) {
            if (is_object($value) && method_exists($value, '__toString')) {
                $definition[$key] = (string)$value;
            }
        }

        return $definition;
    }
}
