<?php

declare(strict_types=1);

namespace App\Services\WorkflowRegistry;

use InvalidArgumentException;

/**
 * Workflow Action Registry
 *
 * Static registry for executable workflow actions. Plugins register actions
 * that workflow nodes can invoke (e.g., 'Officers.CreateOfficerRecord').
 * Follows the ViewCellRegistry pattern for consistency.
 */
class WorkflowActionRegistry
{
    private static array $actions = [];

    private static bool $initialized = false;

    /**
     * Output schema for approval nodes.
     * Used by the designer UI variable picker.
     */
    public const APPROVAL_OUTPUT_SCHEMA = [
        'status' => ['type' => 'string', 'label' => 'Approval Status'],
        'approverId' => ['type' => 'integer', 'label' => 'Approver Member ID'],
        'comment' => ['type' => 'string', 'label' => 'Approval Comment'],
        'rejectionComment' => ['type' => 'string', 'label' => 'Rejection Comment'],
        'decision' => ['type' => 'string', 'label' => 'Decision (approve/reject)'],
    ];

    /**
     * Required fields for each action registration.
     */
    private const REQUIRED_FIELDS = [
        'action',
        'label',
        'description',
        'inputSchema',
        'outputSchema',
        'serviceClass',
        'serviceMethod',
    ];

    /**
     * Register actions from a source plugin.
     *
     * @param string $source Source identifier (e.g., 'Officers', 'Awards')
     * @param array $actions Array of action configurations
     * @return void
     * @throws \InvalidArgumentException If required fields are missing
     */
    public static function register(string $source, array $actions): void
    {
        foreach ($actions as $action) {
            self::validateRequiredFields($action, self::REQUIRED_FIELDS, $source);
        }

        self::$actions[$source] = $actions;
    }

    /**
     * Get a single action by action name.
     *
     * @param string $actionName Action identifier (e.g., 'Officers.CreateOfficerRecord')
     * @return array|null Action configuration or null if not found
     */
    public static function getAction(string $actionName): ?array
    {
        self::ensureInitialized();

        foreach (self::$actions as $source => $actions) {
            foreach ($actions as $action) {
                if ($action['action'] === $actionName) {
                    $action['source'] = $source;
                    return $action;
                }
            }
        }

        return null;
    }

    /**
     * Get all registered actions.
     *
     * @return array All actions keyed by source
     */
    public static function getAllActions(): array
    {
        self::ensureInitialized();

        return self::$actions;
    }

    /**
     * Get actions for a specific source.
     *
     * @param string $source Source identifier
     * @return array Actions from the specified source
     */
    public static function getActionsBySource(string $source): array
    {
        self::ensureInitialized();

        return self::$actions[$source] ?? [];
    }

    /**
     * Get all registered source identifiers.
     *
     * @return array List of registered source identifiers
     */
    public static function getRegisteredSources(): array
    {
        self::ensureInitialized();

        return array_keys(self::$actions);
    }

    /**
     * Remove actions from a specific source.
     *
     * @param string $source Source identifier to remove
     * @return void
     */
    public static function unregister(string $source): void
    {
        unset(self::$actions[$source]);
    }

    /**
     * Clear all registered actions.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$actions = [];
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

        return isset(self::$actions[$source]);
    }

    /**
     * Get debug information about registered actions.
     *
     * @return array Debug information
     */
    public static function getDebugInfo(): array
    {
        self::ensureInitialized();

        $debug = [
            'sources' => [],
            'total_actions' => 0,
        ];

        foreach (self::$actions as $source => $actions) {
            $actionNames = array_column($actions, 'action');
            $debug['sources'][$source] = [
                'action_count' => count($actions),
                'actions' => $actionNames,
            ];
            $debug['total_actions'] += count($actions);
        }

        return $debug;
    }

    /**
     * Get a simplified view for the visual designer UI.
     *
     * @return array Designer-safe action data (no class names)
     */
    public static function getForDesigner(): array
    {
        self::ensureInitialized();

        $result = [];

        foreach (self::$actions as $source => $actions) {
            foreach ($actions as $action) {
                $result[] = [
                    'action' => $action['action'],
                    'label' => $action['label'],
                    'description' => $action['description'],
                    'inputSchema' => $action['inputSchema'],
                    'outputSchema' => $action['outputSchema'],
                    'isAsync' => $action['isAsync'] ?? false,
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
                    sprintf("Missing required field '%s' in action registration from source '%s'.", $field, $source)
                );
            }
        }
    }
}
