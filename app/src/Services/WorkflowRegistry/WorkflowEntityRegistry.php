<?php
declare(strict_types=1);

namespace App\Services\WorkflowRegistry;

use Cake\Core\Plugin;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Workflow Entity Registry
 *
 * Static registry for entity types that workflows can operate on.
 * Plugins register their entity types with schema info (e.g., 'Officers.Officers')
 * for use in the workflow variable picker. Follows the ViewCellRegistry pattern.
 */
class WorkflowEntityRegistry
{
    private static array $entities = [];

    private static ?array $schemaEntities = null;

    private static bool $initialized = false;

    /**
     * Column names matching these patterns are not exposed from reflected schemas.
     */
    private const SENSITIVE_FIELD_PATTERNS = [
        '/password/i',
        '/token/i',
        '/secret/i',
        '/api[_-]?key/i',
        '/private[_-]?key/i',
        '/salt/i',
        '/hash/i',
    ];

    /**
     * Auto-discovery skips tables that are usually operational or sensitive.
     */
    private const DISCOVERY_EXCLUDED_ALIAS_PATTERNS = [
        '/(?:^|\\.)Notes$/i',
        '/Log/i',
        '/Audit/i',
        '/Token/i',
        '/Secret/i',
        '/Password/i',
    ];

    /**
     * Required fields for each entity registration.
     */
    private const REQUIRED_FIELDS = [
        'entityType',
        'label',
        'description',
        'tableClass',
        'fields',
    ];

    /**
     * Register entities from a source plugin.
     *
     * @param string $source Source identifier (e.g., 'Officers', 'Awards')
     * @param array $entities Array of entity configurations
     * @return void
     * @throws \InvalidArgumentException If required fields are missing
     */
    public static function register(string $source, array $entities): void
    {
        foreach ($entities as $entity) {
            self::validateRequiredFields($entity, self::REQUIRED_FIELDS, $source);
        }

        self::$entities[$source] = $entities;
    }

    /**
     * Get a single entity by entity type.
     *
     * @param string $entityType Entity type identifier (e.g., 'Officers.Officers')
     * @return array|null Entity configuration or null if not found
     */
    public static function getEntity(string $entityType): ?array
    {
        self::ensureInitialized();

        foreach (self::$entities as $source => $entities) {
            foreach ($entities as $entity) {
                if ($entity['entityType'] === $entityType) {
                    $entity['source'] = $source;

                    return $entity;
                }
            }
        }

        return null;
    }

    /**
     * Get a workflow entity with Cake schema-reflected fields when available.
     *
     * @param string $entityType Entity type identifier
     * @return array|null Entity configuration or null if not found
     */
    public static function getEntityWithSchema(string $entityType): ?array
    {
        $registered = self::getEntity($entityType);
        if ($registered !== null) {
            return self::enrichEntityWithSchema($registered);
        }

        return self::discoverSchemaEntities()[$entityType] ?? null;
    }

    /**
     * Get all registered entities.
     *
     * @return array All entities keyed by source
     */
    public static function getAllEntities(): array
    {
        self::ensureInitialized();

        return self::$entities;
    }

    /**
     * Get entities for a specific source.
     *
     * @param string $source Source identifier
     * @return array Entities from the specified source
     */
    public static function getEntitiesBySource(string $source): array
    {
        self::ensureInitialized();

        return self::$entities[$source] ?? [];
    }

    /**
     * Get all registered source identifiers.
     *
     * @return array List of registered source identifiers
     */
    public static function getRegisteredSources(): array
    {
        self::ensureInitialized();

        return array_keys(self::$entities);
    }

    /**
     * Remove entities from a specific source.
     *
     * @param string $source Source identifier to remove
     * @return void
     */
    public static function unregister(string $source): void
    {
        unset(self::$entities[$source]);
    }

    /**
     * Clear all registered entities.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$entities = [];
        self::$schemaEntities = null;
        self::$initialized = false;
    }

    /**
     * Check if a source is registered.
     *
     * @param string $source Source identifier
     * @return bool True if registered
     */
    public static function isRegistered(string $source): bool
    {
        self::ensureInitialized();

        return isset(self::$entities[$source]);
    }

    /**
     * Get debug information about registered entities.
     *
     * @return array Debug information
     */
    public static function getDebugInfo(): array
    {
        self::ensureInitialized();

        $debug = [
            'sources' => [],
            'total_entities' => 0,
        ];

        foreach (self::$entities as $source => $entities) {
            $entityTypes = array_column($entities, 'entityType');
            $debug['sources'][$source] = [
                'entity_count' => count($entities),
                'entity_types' => $entityTypes,
            ];
            $debug['total_entities'] += count($entities);
        }

        return $debug;
    }

    /**
     * Get a simplified view for the visual designer UI.
     *
     * @return array Designer-safe entity data (no class names)
     */
    public static function getForDesigner(bool $includeDiscovered = false): array
    {
        self::ensureInitialized();

        $result = [];
        $seen = [];

        foreach (self::$entities as $source => $entities) {
            foreach ($entities as $entity) {
                $entity = self::enrichEntityWithSchema($entity);
                $result[] = [
                    'entityType' => $entity['entityType'],
                    'label' => $entity['label'],
                    'description' => $entity['description'],
                    'fields' => $entity['fields'],
                    'source' => $source,
                ];
                $seen[$entity['entityType']] = true;
            }
        }

        if ($includeDiscovered) {
            foreach (self::discoverSchemaEntities() as $entityType => $entity) {
                if (isset($seen[$entityType])) {
                    continue;
                }
                $result[] = [
                    'entityType' => $entity['entityType'],
                    'label' => $entity['label'],
                    'description' => $entity['description'],
                    'fields' => $entity['fields'],
                    'source' => $entity['source'],
                ];
            }
        }

        return $result;
    }

    /**
     * Enrich a registered entity with safe schema fields from its Cake table.
     *
     * @param array $entity Registered entity data
     * @return array Entity data with reflected fields merged in
     */
    private static function enrichEntityWithSchema(array $entity): array
    {
        $table = self::tableForEntity($entity);
        if ($table === null) {
            return self::filterSensitiveFields($entity);
        }

        try {
            $schemaFields = self::schemaFieldsForTable($table);
        } catch (Throwable $e) {
            return self::filterSensitiveFields($entity);
        }
        $entity['fields'] = array_replace($schemaFields, self::filterSensitiveFields($entity)['fields'] ?? []);
        $entity['tableAlias'] = self::tableAliasForEntity($entity);

        return $entity;
    }

    /**
     * Discover workflow-safe Cake table schemas for the designer and lookup action.
     *
     * @return array<string, array>
     */
    private static function discoverSchemaEntities(): array
    {
        if (self::$schemaEntities !== null) {
            return self::$schemaEntities;
        }

        $tableLocator = TableRegistry::getTableLocator();
        $entities = [];

        foreach (self::discoverTableAliases() as $alias) {
            if (self::isDiscoveryExcludedAlias($alias)) {
                continue;
            }

            try {
                $table = $tableLocator->get($alias);
                if (self::isJoinTable($table)) {
                    continue;
                }

                $fields = self::schemaFieldsForTable($table);
                if (empty($fields)) {
                    continue;
                }

                $entityType = self::entityTypeForAlias($alias);
                $entities[$entityType] = [
                    'entityType' => $entityType,
                    'label' => Inflector::humanize(Inflector::underscore($table->getAlias())),
                    'description' => 'Reflected data model object from CakePHP table schema',
                    'tableAlias' => $alias,
                    'tableClass' => get_class($table),
                    'fields' => $fields,
                    'source' => str_contains($alias, '.') ? explode('.', $alias, 2)[0] : 'Core',
                ];
            } catch (Throwable $e) {
                continue;
            }
        }

        ksort($entities);
        self::$schemaEntities = $entities;

        return self::$schemaEntities;
    }

    /**
     * Discover loaded app/plugin table aliases from Table classes.
     *
     * @return array<string>
     */
    private static function discoverTableAliases(): array
    {
        $aliases = self::scanTableDir(APP . 'Model' . DS . 'Table' . DS);

        foreach (Plugin::loaded() as $plugin) {
            $path = Plugin::path($plugin) . 'src' . DS . 'Model' . DS . 'Table' . DS;
            $aliases = array_merge($aliases, self::scanTableDir($path, $plugin));
        }

        return array_values(array_unique($aliases));
    }

    /**
     * Scan a table directory for Cake table aliases.
     *
     * @param string $path Directory path
     * @param string|null $plugin Plugin prefix
     * @return array<string>
     */
    private static function scanTableDir(string $path, ?string $plugin = null): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $aliases = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Table.php')) {
                continue;
            }
            $alias = substr($file->getFilename(), 0, -9);
            if ($alias === '' || $alias === 'Base') {
                continue;
            }
            $aliases[] = $plugin ? "{$plugin}.{$alias}" : $alias;
        }

        return $aliases;
    }

    /**
     * Get safe workflow field metadata from a Cake table schema.
     *
     * @param \Cake\ORM\Table $table Cake table
     * @return array<string, array>
     */
    private static function schemaFieldsForTable(Table $table): array
    {
        $fields = [];
        $schema = $table->getSchema();
        foreach ($schema->columns() as $column) {
            if (self::isSensitiveField($column)) {
                continue;
            }
            $fields[$column] = [
                'type' => self::workflowTypeForSchemaType((string)$schema->getColumnType($column)),
                'label' => Inflector::humanize(str_replace('_id', ' id', $column)),
                'source' => 'schema',
            ];
        }

        return $fields;
    }

    /**
     * Filter sensitive fields from a registered entity.
     *
     * @param array $entity Entity registration
     * @return array
     */
    private static function filterSensitiveFields(array $entity): array
    {
        $fields = [];
        foreach (($entity['fields'] ?? []) as $field => $meta) {
            if (!self::isSensitiveField((string)$field)) {
                $fields[$field] = $meta;
            }
        }
        $entity['fields'] = $fields;

        return $entity;
    }

    /**
     * Resolve a table from registered entity metadata.
     *
     * @param array $entity Entity metadata
     * @return \Cake\ORM\Table|null
     */
    private static function tableForEntity(array $entity): ?Table
    {
        $alias = self::tableAliasForEntity($entity);
        if ($alias === '') {
            return null;
        }

        try {
            return TableRegistry::getTableLocator()->get($alias);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Resolve entity metadata to a Cake table alias.
     *
     * @param array $entity Entity metadata
     * @return string
     */
    private static function tableAliasForEntity(array $entity): string
    {
        if (!empty($entity['tableAlias'])) {
            return (string)$entity['tableAlias'];
        }

        $tableClass = (string)($entity['tableClass'] ?? '');
        if (
            preg_match(
                '/^(?:(?<plugin>[^\\\\]+)\\\\)?Model\\\\Table\\\\(?<table>[^\\\\]+)Table$/',
                ltrim($tableClass, '\\'),
                $matches,
            )
        ) {
            if (($matches['plugin'] ?? '') && $matches['plugin'] !== 'App') {
                return $matches['plugin'] . '.' . $matches['table'];
            }

            return $matches['table'];
        }

        return $tableClass;
    }

    /**
     * Convert a Cake table alias to a workflow entity type.
     *
     * @param string $alias Cake table alias
     * @return string
     */
    private static function entityTypeForAlias(string $alias): string
    {
        if (str_contains($alias, '.')) {
            return $alias;
        }

        return 'Core.' . $alias;
    }

    /**
     * Map Cake schema types to workflow-designer variable types.
     *
     * @param string $schemaType Cake schema column type
     * @return string
     */
    private static function workflowTypeForSchemaType(string $schemaType): string
    {
        return match ($schemaType) {
            'integer', 'biginteger', 'smallinteger', 'tinyinteger' => 'integer',
            'decimal', 'float' => 'number',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            'json' => 'object',
            default => 'string',
        };
    }

    /**
     * Check whether a field should be hidden from reflected workflow data.
     *
     * @param string $field Field name
     * @return bool
     */
    private static function isSensitiveField(string $field): bool
    {
        foreach (self::SENSITIVE_FIELD_PATTERNS as $pattern) {
            if (preg_match($pattern, $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a table should be hidden from reflected workflow objects.
     *
     * @param string $alias Cake table alias
     * @return bool
     */
    private static function isDiscoveryExcludedAlias(string $alias): bool
    {
        foreach (self::DISCOVERY_EXCLUDED_ALIAS_PATTERNS as $pattern) {
            if (preg_match($pattern, $alias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Skip simple many-to-many join tables from generic workflow object lists.
     *
     * @param \Cake\ORM\Table $table Cake table
     * @return bool
     */
    private static function isJoinTable(Table $table): bool
    {
        $primaryKey = (array)$table->getPrimaryKey();
        if (count($primaryKey) < 2) {
            return false;
        }

        $columns = $table->getSchema()->columns();
        $nonKeyColumns = array_diff($columns, $primaryKey);

        return count($nonKeyColumns) <= 2;
    }

    /**
     * Ensure the registry is initialized.
     *
     * @return void
     */
    private static function ensureInitialized(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;
    }

    /**
     * Validate that required fields are present in a registration entry.
     *
     * @param array $entry Registration entry to validate
     * @param array $requiredFields Required field names
     * @param string $source Source identifier for error messages
     * @return void
     * @throws \InvalidArgumentException If required fields are missing
     */
    private static function validateRequiredFields(array $entry, array $requiredFields, string $source): void
    {
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $entry)) {
                throw new InvalidArgumentException(
                    sprintf("Missing required field '%s' in entity registration from source '%s'.", $field, $source),
                );
            }
        }
    }
}
