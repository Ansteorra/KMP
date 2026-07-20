<?php

declare(strict_types=1);

namespace App\Services\WorkflowRegistry;

use InvalidArgumentException;

/**
 * Workflow Trigger Registry
 *
 * Static registry for workflow trigger events. Plugins register events
 * that can start workflows (e.g., 'Officers.HireRequested').
 * Follows the ViewCellRegistry pattern for consistency.
 */
class WorkflowTriggerRegistry
{
    private static array $triggers = [];

    private static bool $initialized = false;

    /**
     * Required fields for each trigger registration.
     */
    private const REQUIRED_FIELDS = ['event', 'label', 'description', 'payloadSchema'];

    /**
     * Register triggers from a source plugin.
     *
     * @param string $source Source identifier (e.g., 'Officers', 'Awards')
     * @param array $triggers Array of trigger configurations
     * @return void
     * @throws \InvalidArgumentException If required fields are missing
     */
    public static function register(string $source, array $triggers): void
    {
        foreach ($triggers as $trigger) {
            self::validateRequiredFields($trigger, self::REQUIRED_FIELDS, $source);
        }

        self::$triggers[$source] = $triggers;
    }

    /**
     * Get a single trigger by event name.
     *
     * @param string $eventName Event identifier (e.g., 'Officers.HireRequested')
     * @return array|null Trigger configuration or null if not found
     */
    public static function getTrigger(string $eventName): ?array
    {
        self::ensureInitialized();

        foreach (self::$triggers as $source => $triggers) {
            foreach ($triggers as $trigger) {
                if ($trigger['event'] === $eventName) {
                    $trigger['source'] = $source;
                    return $trigger;
                }
            }
        }

        return null;
    }

    /**
     * Get all registered triggers.
     *
     * @return array All triggers keyed by source
     */
    public static function getAllTriggers(): array
    {
        self::ensureInitialized();

        return self::$triggers;
    }

    /**
     * Get triggers for a specific source.
     *
     * @param string $source Source identifier
     * @return array Triggers from the specified source
     */
    public static function getTriggersBySource(string $source): array
    {
        self::ensureInitialized();

        return self::$triggers[$source] ?? [];
    }

    /**
     * Get all registered source identifiers.
     *
     * @return array List of registered source identifiers
     */
    public static function getRegisteredSources(): array
    {
        self::ensureInitialized();

        return array_keys(self::$triggers);
    }

    /**
     * Remove triggers from a specific source.
     *
     * @param string $source Source identifier to remove
     * @return void
     */
    public static function unregister(string $source): void
    {
        unset(self::$triggers[$source]);
    }

    /**
     * Clear all registered triggers.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$triggers = [];
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

        return isset(self::$triggers[$source]);
    }

    /**
     * Get debug information about registered triggers.
     *
     * @return array Debug information
     */
    public static function getDebugInfo(): array
    {
        self::ensureInitialized();

        $debug = [
            'sources' => [],
            'total_triggers' => 0,
        ];

        foreach (self::$triggers as $source => $triggers) {
            $events = array_column($triggers, 'event');
            $debug['sources'][$source] = [
                'trigger_count' => count($triggers),
                'events' => $events,
            ];
            $debug['total_triggers'] += count($triggers);
        }

        return $debug;
    }

    /**
     * Get a simplified view for the visual designer UI.
     *
     * @return array Designer-safe trigger data (no class names)
     */
    public static function getForDesigner(): array
    {
        self::ensureInitialized();

        $result = [];

        foreach (self::$triggers as $source => $triggers) {
            foreach ($triggers as $trigger) {
                $result[] = [
                    'event' => $trigger['event'],
                    'label' => $trigger['label'],
                    'description' => $trigger['description'],
                    'payloadSchema' => $trigger['payloadSchema'],
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
                    sprintf("Missing required field '%s' in trigger registration from source '%s'.", $field, $source)
                );
            }
        }
    }
}
