<?php
declare(strict_types=1);

namespace App\Services;

use Cake\Datasource\ConnectionManager;

/**
 * Builds a database-neutral schema manifest for backup payloads.
 */
class BackupSchemaManifestService
{
    /**
     * @param array<int, string> $excludedTables
     * @return array<string, mixed>
     */
    public function export(array $excludedTables = []): array
    {
        $connection = ConnectionManager::get('default');
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
            if (method_exists($schema, 'indexes')) {
                foreach ($schema->indexes() as $indexName) {
                    $indexes[$indexName] = $this->normalizeDefinition($schema->getIndex($indexName) ?? []);
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
