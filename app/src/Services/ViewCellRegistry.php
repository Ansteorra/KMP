<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Member;
use App\View\Cell\BasePluginCell;
use Cake\Core\StaticConfigTrait;

/**
 * View Cell Registry Service
 * 
 * Manages registration and retrieval of view cells from core and plugins.
 * Replaces the event-based cell system for better performance and maintainability.
 */
class ViewCellRegistry
{
    use StaticConfigTrait;

    public const PLUGIN_TYPE_TAB = 'tab';
    public const PLUGIN_TYPE_DETAIL = 'detail';
    public const PLUGIN_TYPE_MODAL = 'modal';
    public const PLUGIN_TYPE_JSON = 'json';

    /**
     * @var array View cells registry
     */
    private static array $viewCells = [];

    /**
     * @var bool Whether the registry has been initialized
     */
    private static bool $initialized = false;

    /**
     * Register view cells from a source (core or plugin)
     *
     * @param string $source Source identifier (e.g., 'core', 'Awards', 'Officers')
     * @param array $cells View cell configurations array
     * @param callable|null $callback Optional callback for dynamic cell generation
     * @return void
     */
    public static function register(string $source, array $cells, ?callable $callback = null): void
    {
        self::$viewCells[$source] = [
            'cells' => $cells,
            'callback' => $callback,
        ];
    }

    /**
     * Get all registered view cells for a URL and user
     *
     * @param array $urlParams URL parameters from request
     * @param Member|null $user The current user
     * @return array All matching view cells organized by type
     */
    public static function getViewCells(array $urlParams, ?Member $user = null): array
    {
        self::ensureInitialized();

        $allCells = [];

        foreach (self::$viewCells as $source => $registration) {
            $cells = $registration['cells'];

            // If there's a callback, call it to get dynamic cells
            if ($registration['callback'] !== null) {
                $dynamicCells = call_user_func($registration['callback'], $urlParams, $user);
                if (is_array($dynamicCells)) {
                    $cells = array_merge($cells, $dynamicCells);
                }
            }

            // Filter cells that match the current route
            foreach ($cells as $cell) {
                if (self::cellMatchesRoute($cell, $urlParams, $user)) {
                    $allCells[] = $cell;
                }
            }
        }

        return self::organizeViewCells($allCells);
    }

    /**
     * Check if a cell configuration matches the current route
     *
     * @param array $cell Cell configuration
     * @param array $urlParams URL parameters from request
     * @param Member|null $user Current user
     * @return bool True if cell should be displayed
     */
    private static function cellMatchesRoute(array $cell, array $urlParams, ?Member $user = null): bool
    {
        // Check if cell has valid routes defined
        if (!isset($cell['validRoutes']) || !is_array($cell['validRoutes'])) {
            return false;
        }

        // Build test route from current route
        $testRoute = [
            'controller' => $urlParams['controller'] ?? null,
            'action' => $urlParams['action'] ?? null,
            'plugin' => $urlParams['plugin'] ?? null,
        ];

        // Check if current route matches any of the cell's valid routes
        $routeMatches = false;
        foreach ($cell['validRoutes'] as $validRoute) {
            if (self::routesMatch($testRoute, $validRoute)) {
                $routeMatches = true;
                break;
            }
        }

        if (!$routeMatches) {
            return false;
        }

        // If cell has custom authorization logic, call it
        if (isset($cell['authCallback']) && is_callable($cell['authCallback'])) {
            return call_user_func($cell['authCallback'], $urlParams, $user);
        }

        return true;
    }

    /**
     * Check if two routes match
     *
     * @param array $current Current route
     * @param array $valid Valid route pattern
     * @return bool True if routes match
     */
    private static function routesMatch(array $current, array $valid): bool
    {
        foreach (['controller', 'action', 'plugin'] as $key) {
            $currentValue = $current[$key] ?? null;
            $validValue = $valid[$key] ?? null;

            if ($currentValue !== $validValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Organize view cells by type and order (same as AppController::organizeViewCells)
     *
     * @param array $viewCells Array of view cell configurations
     * @return array Organized cells by type
     */
    private static function organizeViewCells(array $viewCells): array
    {
        $cells = [];
        foreach ($viewCells as $cell) {
            if (!isset($cell['type']) || !isset($cell['order'])) {
                continue;
            }
            $cells[$cell['type']][$cell['order']] = $cell;
        }

        // Sort each type by order
        foreach ($cells as $type => $typeCells) {
            ksort($cells[$type]);
        }

        return $cells;
    }

    /**
     * Remove view cells from a specific source
     *
     * @param string $source Source identifier to remove
     * @return void
     */
    public static function unregister(string $source): void
    {
        unset(self::$viewCells[$source]);
    }

    /**
     * Get view cells from a specific source
     *
     * @param string $source Source identifier
     * @param array $route Current route information
     * @param Member|null $user The current user
     * @return array View cells from the specified source
     */
    public static function getViewCellsFromSource(string $source, array $route, ?Member $user = null): array
    {
        self::ensureInitialized();

        if (!isset(self::$viewCells[$source])) {
            return [];
        }

        $registration = self::$viewCells[$source];
        $cells = $registration['cells'];

        // If there's a callback, call it to get dynamic cells
        if ($registration['callback'] !== null) {
            $dynamicCells = call_user_func($registration['callback'], $route, $user);
            if (is_array($dynamicCells)) {
                $cells = array_merge($cells, $dynamicCells);
            }
        }

        // Filter cells that match the current route
        $matchingCells = [];
        foreach ($cells as $cell) {
            if (self::cellMatchesRoute($cell, $route, $user)) {
                $matchingCells[] = $cell;
            }
        }

        return self::organizeViewCells($matchingCells);
    }

    /**
     * Get all registered sources
     *
     * @return array List of registered source identifiers
     */
    public static function getRegisteredSources(): array
    {
        self::ensureInitialized();
        return array_keys(self::$viewCells);
    }

    /**
     * Clear all registered view cells (useful for testing)
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$viewCells = [];
        self::$initialized = false;
    }

    /**
     * Check if a source is registered
     *
     * @param string $source Source identifier
     * @return bool True if registered
     */
    public static function isRegistered(string $source): bool
    {
        self::ensureInitialized();
        return isset(self::$viewCells[$source]);
    }

    /**
     * Get debug information about registered view cells
     *
     * @return array Debug information
     */
    public static function getDebugInfo(): array
    {
        self::ensureInitialized();

        $debug = [
            'sources' => [],
            'total_cells' => 0,
        ];

        foreach (self::$viewCells as $source => $registration) {
            $cellCount = count($registration['cells']);
            $hasCallback = $registration['callback'] !== null;

            $debug['sources'][$source] = [
                'static_cells' => $cellCount,
                'has_callback' => $hasCallback,
            ];

            $debug['total_cells'] += $cellCount;
        }

        return $debug;
    }

    /**
     * Ensure the registry is initialized
     * This can be used to set up default configurations if needed
     *
     * @return void
     */
    private static function ensureInitialized(): void
    {
        if (self::$initialized) {
            return;
        }

        // Any initialization logic can go here
        self::$initialized = true;
    }
}