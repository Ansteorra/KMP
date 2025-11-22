<?php

declare(strict_types=1);

namespace App\KMP;

/**
 * GridViewConfig - Configuration Validator and Normalizer for Grid Views
 *
 * This helper class provides validation, normalization, and utility methods for working
 * with grid view configurations. It ensures that view configs follow a consistent schema
 * and provides methods for safely extracting and applying configuration values.
 *
 * ## Config Schema
 *
 * A valid grid view config contains:
 * - **filters**: Array of filter definitions (flat AND conditions - legacy)
 * - **expression**: Nested OR/AND expression tree (preferred for complex logic)
 * - **sort**: Array of sort definitions
 * - **columns**: Array of column visibility/order definitions
 * - **pageSize**: Integer for pagination
 *
 * ### Simple Filters (Flat AND)
 * ```json
 * {
 *   "filters": [
 *     {"field": "status", "operator": "eq", "value": "active"},
 *     {"field": "created", "operator": "gte", "value": "2024-01-01"}
 *   ]
 * }
 * ```
 *
 * ### Complex Expression (Nested OR/AND)
 * ```json
 * {
 *   "expression": {
 *     "type": "OR",
 *     "conditions": [
 *       {"field": "expires_on", "operator": "lt", "value": "2025-11-22"},
 *       {
 *         "type": "AND",
 *         "conditions": [
 *           {"field": "status", "operator": "in", "value": ["Deactivated", "Expired"]},
 *           {"field": "created", "operator": "gte", "value": "2024-01-01"}
 *         ]
 *       }
 *     ]
 *   }
 * }
 * ```
 *
 * ## Supported Filter Operators
 *
 * - **eq**: Equals
 * - **neq**: Not equals
 * - **gt**: Greater than
 * - **gte**: Greater than or equal
 * - **lt**: Less than
 * - **lte**: Less than or equal
 * - **contains**: String contains (LIKE %value%)
 * - **startsWith**: String starts with (LIKE value%)
 * - **endsWith**: String ends with (LIKE %value)
 * - **in**: Value in array
 * - **notIn**: Value not in array
 * - **isNull**: Field is NULL
 * - **isNotNull**: Field is not NULL
 * - **dateRange**: Date range filter with [start, end] array. Either value can be null to create an open-ended range
 *
 * ## Usage Examples
 *
 * ### Normalizing Config
 * ```php
 * $raw = json_decode($view->config, true);
 * $normalized = GridViewConfig::normalize($raw, $availableColumns);
 * ```
 *
 * ### Validating Config
 * ```php
 * $errors = GridViewConfig::validate($config, $availableColumns);
 * if (empty($errors)) {
 *     // Config is valid
 * }
 * ```
 *
 * ### Creating Default Config
 * ```php
 * $defaultConfig = GridViewConfig::createDefault($columnMetadata);
 * ```
 *
 * ### Extracting ORM Conditions
 * ```php
 * $conditions = GridViewConfig::extractFilters($config);
 * // Use in query: $query->where($conditions);
 * ```
 */
class GridViewConfig
{
    /**
     * Valid filter operators
     */
    public const VALID_OPERATORS = [
        'eq',
        'neq',
        'gt',
        'gte',
        'lt',
        'lte',
        'contains',
        'startsWith',
        'endsWith',
        'in',
        'notIn',
        'isNull',
        'isNotNull',
        'dateRange',
    ];

    /**
     * Valid sort directions
     */
    public const VALID_DIRECTIONS = ['asc', 'desc'];

    /**
     * Default page size
     */
    public const DEFAULT_PAGE_SIZE = 25;

    /**
     * Minimum page size
     */
    public const MIN_PAGE_SIZE = 10;

    /**
     * Maximum page size
     */
    public const MAX_PAGE_SIZE = 100;

    /**
     * Normalize a config array to ensure consistent structure
     *
     * @param array<string, mixed> $config Raw config array
     * @param array<string, array<string, mixed>> $availableColumns Available column metadata
     * @return array<string, mixed> Normalized config
     */
    public static function normalize(array $config, array $availableColumns = []): array
    {
        $normalized = [
            'filters' => [],
            'sort' => [],
            'columns' => [],
            'pageSize' => self::DEFAULT_PAGE_SIZE,
        ];

        // Normalize filters
        if (isset($config['filters']) && is_array($config['filters'])) {
            foreach ($config['filters'] as $filter) {
                if (self::isValidFilter($filter, $availableColumns)) {
                    $normalized['filters'][] = [
                        'field' => $filter['field'],
                        'operator' => $filter['operator'],
                        'value' => $filter['value'] ?? null,
                    ];
                }
            }
        }

        // Normalize sort
        if (isset($config['sort']) && is_array($config['sort'])) {
            foreach ($config['sort'] as $sort) {
                if (self::isValidSort($sort, $availableColumns)) {
                    $normalized['sort'][] = [
                        'field' => $sort['field'],
                        'direction' => strtolower($sort['direction']),
                    ];
                }
            }
        }

        // Normalize columns
        if (isset($config['columns']) && is_array($config['columns'])) {
            foreach ($config['columns'] as $index => $column) {
                if (isset($column['key'])) {
                    $normalized['columns'][] = [
                        'key' => $column['key'],
                        'visible' => (bool)($column['visible'] ?? true),
                        'order' => isset($column['order']) ? (int)$column['order'] : $index,
                    ];
                }
            }
        }

        // Normalize page size
        if (isset($config['pageSize']) && is_numeric($config['pageSize'])) {
            $pageSize = (int)$config['pageSize'];
            $normalized['pageSize'] = max(
                self::MIN_PAGE_SIZE,
                min(self::MAX_PAGE_SIZE, $pageSize)
            );
        }

        return $normalized;
    }

    /**
     * Validate a config array
     *
     * @param array<string, mixed> $config Config to validate
     * @param array<string, array<string, mixed>> $availableColumns Available column metadata
     * @return array<string> Array of error messages (empty if valid)
     */
    public static function validate(array $config, array $availableColumns = []): array
    {
        $errors = [];

        // Validate filters
        if (isset($config['filters'])) {
            if (!is_array($config['filters'])) {
                $errors[] = 'Filters must be an array';
            } else {
                foreach ($config['filters'] as $index => $filter) {
                    if (!self::isValidFilter($filter, $availableColumns)) {
                        $errors[] = "Invalid filter at index {$index}";
                    }
                }
            }
        }

        // Validate sort
        if (isset($config['sort'])) {
            if (!is_array($config['sort'])) {
                $errors[] = 'Sort must be an array';
            } else {
                foreach ($config['sort'] as $index => $sort) {
                    if (!self::isValidSort($sort, $availableColumns)) {
                        $errors[] = "Invalid sort at index {$index}";
                    }
                }
            }
        }

        // Validate columns
        if (isset($config['columns'])) {
            if (!is_array($config['columns'])) {
                $errors[] = 'Columns must be an array';
            }
        }

        // Validate page size
        if (isset($config['pageSize'])) {
            if (!is_numeric($config['pageSize'])) {
                $errors[] = 'Page size must be numeric';
            } elseif ($config['pageSize'] < self::MIN_PAGE_SIZE || $config['pageSize'] > self::MAX_PAGE_SIZE) {
                $errors[] = sprintf(
                    'Page size must be between %d and %d',
                    self::MIN_PAGE_SIZE,
                    self::MAX_PAGE_SIZE
                );
            }
        }

        return $errors;
    }

    /**
     * Check if a filter definition is valid
     *
     * @param mixed $filter Filter definition
     * @param array<string, array<string, mixed>> $availableColumns Available columns
     * @return bool
     */
    protected static function isValidFilter($filter, array $availableColumns): bool
    {
        if (!is_array($filter)) {
            return false;
        }

        if (!isset($filter['field']) || !isset($filter['operator'])) {
            return false;
        }

        if (!in_array($filter['operator'], self::VALID_OPERATORS, true)) {
            return false;
        }

        // If available columns provided, validate field exists
        if (!empty($availableColumns) && !isset($availableColumns[$filter['field']])) {
            return false;
        }

        // Operators that require a value
        $requiresValue = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'contains', 'startsWith', 'endsWith', 'in', 'notIn'];
        if (in_array($filter['operator'], $requiresValue, true) && !isset($filter['value'])) {
            return false;
        }

        // dateRange operator requires array value with at least one non-null element
        if ($filter['operator'] === 'dateRange') {
            if (!isset($filter['value']) || !is_array($filter['value']) || count($filter['value']) !== 2) {
                return false;
            }
            // At least one date must be provided
            if (empty($filter['value'][0]) && empty($filter['value'][1])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a sort definition is valid
     *
     * @param mixed $sort Sort definition
     * @param array<string, array<string, mixed>> $availableColumns Available columns
     * @return bool
     */
    protected static function isValidSort($sort, array $availableColumns): bool
    {
        if (!is_array($sort)) {
            return false;
        }

        if (!isset($sort['field']) || !isset($sort['direction'])) {
            return false;
        }

        if (!in_array(strtolower($sort['direction']), self::VALID_DIRECTIONS, true)) {
            return false;
        }

        // If available columns provided, validate field exists and is sortable
        if (!empty($availableColumns)) {
            if (!isset($availableColumns[$sort['field']])) {
                return false;
            }

            $column = $availableColumns[$sort['field']];
            if (isset($column['sortable']) && !$column['sortable']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a default config from column metadata
     *
     * @param array<string, array<string, mixed>> $columnMetadata Column definitions
     * @return array<string, mixed> Default config
     */
    public static function createDefault(array $columnMetadata): array
    {
        $columns = [];
        $order = 0;

        foreach ($columnMetadata as $key => $meta) {
            $columns[] = [
                'key' => $key,
                'visible' => $meta['defaultVisible'] ?? true,
                'order' => $order++,
            ];
        }

        return [
            'filters' => [],
            'sort' => [],
            'columns' => $columns,
            'pageSize' => self::DEFAULT_PAGE_SIZE,
        ];
    }

    /**
     * Extract ORM-compatible filter conditions from config
     *
     * @param array<string, mixed> $config Grid view config
     * @return array<string, mixed> ORM conditions
     */
    public static function extractFilters(array $config): array
    {
        $conditions = [];

        if (!isset($config['filters']) || !is_array($config['filters'])) {
            return $conditions;
        }

        foreach ($config['filters'] as $filter) {
            $field = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'] ?? null;

            // Skip _search pseudo-field - it's handled separately as a URL parameter
            if ($field === '_search') {
                continue;
            }

            switch ($operator) {
                case 'eq':
                    $conditions[$field] = $value;
                    break;
                case 'neq':
                    $conditions[$field . ' !='] = $value;
                    break;
                case 'gt':
                    $conditions[$field . ' >'] = $value;
                    break;
                case 'gte':
                    $conditions[$field . ' >='] = $value;
                    break;
                case 'lt':
                    $conditions[$field . ' <'] = $value;
                    break;
                case 'lte':
                    $conditions[$field . ' <='] = $value;
                    break;
                case 'contains':
                    $conditions[$field . ' LIKE'] = '%' . $value . '%';
                    break;
                case 'startsWith':
                    $conditions[$field . ' LIKE'] = $value . '%';
                    break;
                case 'endsWith':
                    $conditions[$field . ' LIKE'] = '%' . $value;
                    break;
                case 'in':
                    $conditions[$field . ' IN'] = is_array($value) ? $value : [$value];
                    break;
                case 'notIn':
                    $conditions[$field . ' NOT IN'] = is_array($value) ? $value : [$value];
                    break;
                case 'isNull':
                    $conditions[$field . ' IS'] = null;
                    break;
                case 'isNotNull':
                    $conditions[$field . ' IS NOT'] = null;
                    break;
                case 'dateRange':
                    // Value is [start, end] array. Either can be null for open-ended range
                    if (is_array($value) && count($value) === 2) {
                        if ($value[0] !== null && $value[0] !== '') {
                            $conditions[$field . ' >='] = $value[0];
                        }
                        if ($value[1] !== null && $value[1] !== '') {
                            $conditions[$field . ' <='] = $value[1];
                        }
                    }
                    break;
            }
        }

        return $conditions;
    }

    /**
     * Extract nested OR/AND expression tree from config
     *
     * Builds a CakePHP QueryExpression from a nested expression tree. Supports:
     * - Simple field conditions: {"field": "status", "operator": "eq", "value": "active"}
     * - OR groups: {"type": "OR", "conditions": [...]}
     * - AND groups: {"type": "AND", "conditions": [...]}
     * - Nested combinations: Mix OR/AND at any depth
     *
     * Expression format:
     * ```json
     * {
     *   "type": "OR|AND",
     *   "conditions": [
     *     {"field": "status", "operator": "eq", "value": "active"},
     *     {
     *       "type": "AND",
     *       "conditions": [...]
     *     }
     *   ]
     * }
     * ```
     *
     * @param array<string, mixed> $config Grid view config
     * @param \Cake\Database\Expression\QueryExpression $queryExpression Base expression object from query
     * @param string $tableName Table name for field qualification (e.g., 'Warrants')
     * @return \Cake\Database\Expression\QueryExpression|null Built expression tree or null if no expression
     */
    public static function extractExpression(
        array $config,
        $queryExpression,
        string $tableName = ''
    ): ?object {
        if (!isset($config['expression']) || !is_array($config['expression'])) {
            return null;
        }

        return self::buildExpression($config['expression'], $queryExpression, $tableName);
    }

    /**
     * Recursively build QueryExpression from expression tree
     *
     * @param array<string, mixed> $expression Expression node (group or condition)
     * @param \Cake\Database\Expression\QueryExpression $queryExpression Base expression from query
     * @param string $tableName Table name for field qualification
     * @return \Cake\Database\Expression\QueryExpression Built expression
     */
    protected static function buildExpression(
        array $expression,
        $queryExpression,
        string $tableName
    ): object {
        // Check if this is a group (OR/AND) or a single condition
        if (isset($expression['type']) && in_array(strtoupper($expression['type']), ['OR', 'AND'], true)) {
            // This is a group - create new expression with specified conjunction
            $conjunction = strtoupper($expression['type']);
            $conditions = $expression['conditions'] ?? [];

            if (empty($conditions) || !is_array($conditions)) {
                return $queryExpression;
            }

            // Build an array of conditions for this group
            $groupConditions = [];
            foreach ($conditions as $condition) {
                if (!is_array($condition)) {
                    continue;
                }

                // Check if this is a nested group or a leaf condition
                if (isset($condition['type']) && in_array(strtoupper($condition['type']), ['OR', 'AND'], true)) {
                    // Nested group - recurse
                    $nestedExp = $queryExpression->newExpr();
                    $groupConditions[] = self::buildExpression($condition, $nestedExp, $tableName);
                } else {
                    // Leaf condition - convert to CakePHP condition array
                    $leafConditions = self::buildLeafCondition($condition, $tableName);
                    if (!empty($leafConditions)) {
                        $groupConditions = array_merge($groupConditions, $leafConditions);
                    }
                }
            }

            if (empty($groupConditions)) {
                return $queryExpression;
            }

            // Add conditions with specified conjunction
            if ($conjunction === 'OR') {
                return $queryExpression->add([$conjunction => $groupConditions]);
            } else {
                // AND is the default, just add conditions directly
                return $queryExpression->add($groupConditions);
            }
        } else {
            // Single condition - build and add it
            $conditions = self::buildLeafCondition($expression, $tableName);
            if (!empty($conditions)) {
                return $queryExpression->add($conditions);
            }

            return $queryExpression;
        }
    }

    /**
     * Build CakePHP condition array from a leaf condition
     *
     * Converts expression condition format to CakePHP ORM condition format:
     * - {"field": "status", "operator": "eq", "value": "active"}
     * - Becomes: ["Table.status" => "active"]
     *
     * @param array<string, mixed> $condition Condition definition
     * @param string $tableName Table name for field qualification
     * @return array<string, mixed> CakePHP condition array
     */
    protected static function buildLeafCondition(array $condition, string $tableName): array
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;

        if (!$field || !$operator) {
            return [];
        }

        // Skip _search pseudo-field - handled separately
        if ($field === '_search') {
            return [];
        }

        // Qualify field with table name if not already qualified
        if ($tableName && strpos($field, '.') === false) {
            $qualifiedField = $tableName . '.' . $field;
        } else {
            $qualifiedField = $field;
        }

        // Build condition based on operator
        switch ($operator) {
            case 'eq':
                return [$qualifiedField => $value];

            case 'neq':
                return [$qualifiedField . ' !=' => $value];

            case 'gt':
                return [$qualifiedField . ' >' => $value];

            case 'gte':
                return [$qualifiedField . ' >=' => $value];

            case 'lt':
                return [$qualifiedField . ' <' => $value];

            case 'lte':
                return [$qualifiedField . ' <=' => $value];

            case 'contains':
                return [$qualifiedField . ' LIKE' => '%' . $value . '%'];

            case 'startsWith':
                return [$qualifiedField . ' LIKE' => $value . '%'];

            case 'endsWith':
                return [$qualifiedField . ' LIKE' => '%' . $value];

            case 'in':
                return [$qualifiedField . ' IN' => is_array($value) ? $value : [$value]];

            case 'notIn':
                return [$qualifiedField . ' NOT IN' => is_array($value) ? $value : [$value]];

            case 'isNull':
                return [$qualifiedField . ' IS' => null];

            case 'isNotNull':
                return [$qualifiedField . ' IS NOT' => null];

            case 'dateRange':
                // Value is [start, end] array. Either can be null for open-ended range
                $conditions = [];
                if (is_array($value) && count($value) === 2) {
                    if ($value[0] !== null && $value[0] !== '') {
                        $conditions[$qualifiedField . ' >='] = $value[0];
                    }
                    if ($value[1] !== null && $value[1] !== '') {
                        $conditions[$qualifiedField . ' <='] = $value[1];
                    }
                }
                return $conditions;

            default:
                return [];
        }
    }

    /**
     * Extract sort order from config
     *
     * @param array<string, mixed> $config Grid view config
     * @return array<string, string> Sort order for ORM
     */
    public static function extractSort(array $config): array
    {
        $order = [];

        if (!isset($config['sort']) || !is_array($config['sort'])) {
            return $order;
        }

        foreach ($config['sort'] as $sort) {
            $order[$sort['field']] = strtoupper($sort['direction']);
        }

        return $order;
    }

    /**
     * Extract visible columns from config in display order
     *
     * @param array<string, mixed> $config Grid view config
     * @param array<string, array<string, mixed>> $availableColumns Available column metadata
     * @return array<string> Array of visible column keys in order
     */
    public static function extractVisibleColumns(array $config, array $availableColumns = []): array
    {
        $visible = [];

        if (!isset($config['columns']) || !is_array($config['columns'])) {
            return $visible;
        }

        // Collect visible columns with their order
        $orderedColumns = [];
        foreach ($config['columns'] as $column) {
            $isRequired = !empty($availableColumns[$column['key']]['required']);
            if (!empty($column['visible']) || $isRequired) {
                $order = $column['order'] ?? 999;
                $orderedColumns[] = [
                    'key' => $column['key'],
                    'order' => $order,
                ];
            }
        }

        // Sort by order
        usort($orderedColumns, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        // Extract just the keys
        foreach ($orderedColumns as $col) {
            $visible[] = $col['key'];
        }

        return $visible;
    }

    /**
     * Extract all columns from config with their order and visibility
     *
     * @param array<string, mixed> $config Grid view config
     * @return array<string, array{visible: bool, order: int}> Column configuration map
     */
    public static function extractColumnConfiguration(array $config): array
    {
        $columns = [];

        if (!isset($config['columns']) || !is_array($config['columns'])) {
            return $columns;
        }

        foreach ($config['columns'] as $column) {
            if (isset($column['key'])) {
                $columns[$column['key']] = [
                    'visible' => (bool)($column['visible'] ?? true),
                    'order' => (int)($column['order'] ?? 999),
                ];
            }
        }

        return $columns;
    }

    /**
     * Extract page size from config
     *
     * @param array<string, mixed> $config Grid view config
     * @return int Page size
     */
    public static function extractPageSize(array $config): int
    {
        if (!isset($config['pageSize']) || !is_numeric($config['pageSize'])) {
            return self::DEFAULT_PAGE_SIZE;
        }

        $pageSize = (int)$config['pageSize'];

        return max(
            self::MIN_PAGE_SIZE,
            min(self::MAX_PAGE_SIZE, $pageSize)
        );
    }
}
