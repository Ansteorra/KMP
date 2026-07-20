<?php

declare(strict_types=1);

namespace App\Services\WorkflowRegistry;

use InvalidArgumentException;

/**
 * Workflow Condition Registry
 *
 * Static registry for condition evaluators used in workflow branching decisions.
 * Plugins register conditions (e.g., 'Officers.OfficeRequiresWarrant') that
 * workflow nodes use for conditional logic. Follows the ViewCellRegistry pattern.
 */
class WorkflowConditionRegistry
{
    private static array $conditions = [];

    private static bool $initialized = false;

    /**
     * Required fields for each condition registration.
     */
    private const REQUIRED_FIELDS = [
        'condition',
        'label',
        'description',
        'inputSchema',
        'evaluatorClass',
        'evaluatorMethod',
    ];

    /**
     * Register conditions from a source plugin.
     *
     * @param string $source Source identifier (e.g., 'Officers', 'Awards')
     * @param array $conditions Array of condition configurations
     * @return void
     * @throws \InvalidArgumentException If required fields are missing
     */
    public static function register(string $source, array $conditions): void
    {
        foreach ($conditions as $condition) {
            self::validateRequiredFields($condition, self::REQUIRED_FIELDS, $source);
        }

        self::$conditions[$source] = $conditions;
    }

    /**
     * Get a single condition by condition name.
     *
     * @param string $conditionName Condition identifier
     * @return array|null Condition configuration or null if not found
     */
    public static function getCondition(string $conditionName): ?array
    {
        self::ensureInitialized();

        foreach (self::$conditions as $source => $conditions) {
            foreach ($conditions as $condition) {
                if ($condition['condition'] === $conditionName) {
                    $condition['source'] = $source;
                    return $condition;
                }
            }
        }

        return null;
    }

    /**
     * Get all registered conditions.
     *
     * @return array All conditions keyed by source
     */
    public static function getAllConditions(): array
    {
        self::ensureInitialized();

        return self::$conditions;
    }

    /**
     * Get conditions for a specific source.
     *
     * @param string $source Source identifier
     * @return array Conditions from the specified source
     */
    public static function getConditionsBySource(string $source): array
    {
        self::ensureInitialized();

        return self::$conditions[$source] ?? [];
    }

    /**
     * Get all registered source identifiers.
     *
     * @return array List of registered source identifiers
     */
    public static function getRegisteredSources(): array
    {
        self::ensureInitialized();

        return array_keys(self::$conditions);
    }

    /**
     * Remove conditions from a specific source.
     *
     * @param string $source Source identifier to remove
     * @return void
     */
    public static function unregister(string $source): void
    {
        unset(self::$conditions[$source]);
    }

    /**
     * Clear all registered conditions.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$conditions = [];
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

        return isset(self::$conditions[$source]);
    }

    /**
     * Get debug information about registered conditions.
     *
     * @return array Debug information
     */
    public static function getDebugInfo(): array
    {
        self::ensureInitialized();

        $debug = [
            'sources' => [],
            'total_conditions' => 0,
        ];

        foreach (self::$conditions as $source => $conditions) {
            $conditionNames = array_column($conditions, 'condition');
            $debug['sources'][$source] = [
                'condition_count' => count($conditions),
                'conditions' => $conditionNames,
            ];
            $debug['total_conditions'] += count($conditions);
        }

        return $debug;
    }

    /**
     * Get a simplified view for the visual designer UI.
     *
     * @return array Designer-safe condition data (no class names)
     */
    public static function getForDesigner(): array
    {
        self::ensureInitialized();

        $result = [];

        foreach (self::$conditions as $source => $conditions) {
            foreach ($conditions as $condition) {
                $result[] = [
                    'condition' => $condition['condition'],
                    'label' => $condition['label'],
                    'description' => $condition['description'],
                    'inputSchema' => $condition['inputSchema'],
                    'source' => $source,
                ];
            }
        }

        return $result;
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
                    sprintf("Missing required field '%s' in condition registration from source '%s'.", $field, $source)
                );
            }
        }
    }
}
