<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Member;
use Cake\Core\StaticConfigTrait;

/**
 * Navigation Registry Service
 * 
 * Manages registration and retrieval of navigation items from core and plugins.
 * Replaces the event-based navigation system for better performance and maintainability.
 */
class NavigationRegistry
{
    use StaticConfigTrait;

    /**
     * @var array Navigation items registry
     */
    private static array $navigationItems = [];

    /**
     * @var bool Whether the registry has been initialized
     */
    private static bool $initialized = false;

    /**
     * Register navigation items from a source (core or plugin)
     *
     * @param string $source Source identifier (e.g., 'core', 'Awards', 'Officers')
     * @param array $items Navigation items array
     * @param callable|null $callback Optional callback for dynamic item generation
     * @return void
     */
    public static function register(string $source, array $items, ?callable $callback = null): void
    {
        self::$navigationItems[$source] = [
            'items' => $items,
            'callback' => $callback,
        ];
    }

    /**
     * Get all registered navigation items for a user
     *
     * @param Member $user The current user
     * @param array $params Request parameters for context
     * @return array All navigation items
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        self::ensureInitialized();

        $allItems = [];
        // lets check if we already have items in the session
        if (isset($_SESSION['navigation_items']) && is_array($_SESSION['navigation_items'])) {
            $allItems = $_SESSION['navigation_items'];
            return $allItems;
        }

        foreach (self::$navigationItems as $source => $registration) {
            $items = $registration['items'];

            // If there's a callback, call it to get dynamic items
            if ($registration['callback'] !== null) {
                $dynamicItems = call_user_func($registration['callback'], $user, $params);
                if (is_array($dynamicItems)) {
                    $items = array_merge($items, $dynamicItems);
                }
            }

            $allItems = array_merge($allItems, $items);
        }
        // Store the items in the session for future requests
        $_SESSION['navigation_items'] = $allItems;
        return $allItems;
    }

    /**
     * Remove navigation items from a specific source
     *
     * @param string $source Source identifier to remove
     * @return void
     */
    public static function unregister(string $source): void
    {
        unset(self::$navigationItems[$source]);
    }

    /**
     * Get navigation items from a specific source
     *
     * @param string $source Source identifier
     * @param Member $user The current user
     * @param array $params Request parameters for context
     * @return array Navigation items from the specified source
     */
    public static function getNavigationItemsFromSource(string $source, Member $user, array $params = []): array
    {
        self::ensureInitialized();

        if (!isset(self::$navigationItems[$source])) {
            return [];
        }

        $registration = self::$navigationItems[$source];
        $items = $registration['items'];

        // If there's a callback, call it to get dynamic items
        if ($registration['callback'] !== null) {
            $dynamicItems = call_user_func($registration['callback'], $user, $params);
            if (is_array($dynamicItems)) {
                $items = array_merge($items, $dynamicItems);
            }
        }

        return $items;
    }

    /**
     * Get all registered sources
     *
     * @return array List of registered source identifiers
     */
    public static function getRegisteredSources(): array
    {
        self::ensureInitialized();
        return array_keys(self::$navigationItems);
    }

    /**
     * Clear all registered navigation items (useful for testing)
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$navigationItems = [];
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
        return isset(self::$navigationItems[$source]);
    }

    /**
     * Get debug information about registered navigation
     *
     * @return array Debug information
     */
    public static function getDebugInfo(): array
    {
        self::ensureInitialized();

        $debug = [
            'sources' => [],
            'total_items' => 0,
        ];

        foreach (self::$navigationItems as $source => $registration) {
            $itemCount = count($registration['items']);
            $hasCallback = $registration['callback'] !== null;

            $debug['sources'][$source] = [
                'static_items' => $itemCount,
                'has_callback' => $hasCallback,
            ];

            $debug['total_items'] += $itemCount;
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