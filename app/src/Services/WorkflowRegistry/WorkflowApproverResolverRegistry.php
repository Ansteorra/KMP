<?php

declare(strict_types=1);

namespace App\Services\WorkflowRegistry;

use Cake\Log\Log;

/**
 * Workflow Approver Resolver Registry
 *
 * Static registry for dynamic approver resolvers. Plugins register resolvers
 * so the designer can show them in a dropdown and the engine can look up
 * serviceClass/method by registry key.
 */
class WorkflowApproverResolverRegistry
{
    private static array $resolvers = [];

    /**
     * Register approver resolvers from a plugin/source.
     *
     * Each resolver item should have:
     * - resolver: unique key (e.g., 'Activities.AuthorizationApproverResolver')
     * - label: human-readable name
     * - description: what this resolver does
     * - serviceClass: fully qualified PHP class name
     * - serviceMethod: method to call (receives WorkflowApproval, returns int[])
     * - configSchema: array of config fields the resolver needs
     *   Each field: ['type' => 'string|integer', 'label' => '...', 'required' => bool, 'description' => '...']
     *
     * @param string $source Source identifier (e.g., 'Activities')
     * @param array $items Array of resolver configurations
     * @return void
     */
    public static function register(string $source, array $items): void
    {
        foreach ($items as $item) {
            $key = $item['resolver'] ?? null;
            if (!$key) {
                Log::warning("WorkflowApproverResolverRegistry: resolver missing 'resolver' key from source '{$source}'");
                continue;
            }
            if (isset(self::$resolvers[$key])) {
                Log::warning("WorkflowApproverResolverRegistry: duplicate resolver '{$key}' from source '{$source}'");
                continue;
            }
            $item['source'] = $source;
            self::$resolvers[$key] = $item;
        }
    }

    /**
     * Get a single resolver by key.
     *
     * @param string $key Resolver identifier
     * @return array|null Resolver configuration or null if not found
     */
    public static function getResolver(string $key): ?array
    {
        return self::$resolvers[$key] ?? null;
    }

    /**
     * Get all registered resolvers.
     *
     * @return array All resolvers keyed by resolver key
     */
    public static function getAllResolvers(): array
    {
        return self::$resolvers;
    }

    /**
     * Returns frontend-safe data — strips serviceClass, keeps method and configSchema.
     *
     * @return array Designer-safe resolver data
     */
    public static function getForDesigner(): array
    {
        $result = [];
        foreach (self::$resolvers as $key => $item) {
            $result[] = [
                'resolver' => $key,
                'label' => $item['label'] ?? $key,
                'description' => $item['description'] ?? '',
                'method' => $item['serviceMethod'] ?? '',
                'configSchema' => $item['configSchema'] ?? [],
                'source' => $item['source'] ?? '',
            ];
        }
        return $result;
    }

    /**
     * Clear all registered resolvers.
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$resolvers = [];
    }
}
