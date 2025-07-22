<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Member;
use Cake\Core\StaticConfigTrait;

/**
 * Navigation Registry Service
 * 
 * Centralized registry for managing application navigation items from core and plugin sources.
 * Replaces the event-based navigation system with a more performant and maintainable
 * static registration approach. Handles dynamic navigation generation, caching, and
 * user-specific filtering of navigation items.
 * 
 * ## Architecture Overview
 * 
 * The navigation system uses a registry pattern where:
 * - **Core Application**: Registers base navigation items via CoreNavigationProvider
 * - **Plugins**: Register their navigation items during plugin initialization
 * - **Dynamic Generation**: Callbacks can generate context-specific navigation items
 * - **Session Caching**: Navigation items are cached in session for performance
 * - **Authorization Integration**: Items are filtered based on user permissions
 * 
 * ## Navigation Item Structure
 * 
 * Navigation items follow a standardized format:
 * ```php
 * [
 *     'type' => 'item|parent|child|separator',
 *     'label' => 'Human readable label',
 *     'url' => ['controller' => 'Members', 'action' => 'index'],
 *     'icon' => 'bi-people',
 *     'order' => 10,
 *     'id' => 'unique_nav_id',
 *     'parent' => 'parent_nav_id',
 *     'badge' => ['class' => 'ClassName', 'method' => 'methodName'],
 *     'linkTypeClass' => 'nav-link',
 *     'otherClasses' => 'additional-css-classes'
 * ]
 * ```
 * 
 * ## Performance Optimizations
 * 
 * - **Session Caching**: Navigation items cached in $_SESSION to avoid regeneration
 * - **Lazy Initialization**: Registry only initialized when first accessed
 * - **Static Registry**: Avoids repeated database queries or file system access
 * - **Callback Pattern**: Dynamic items only generated when needed
 * 
 * ## Plugin Integration
 * 
 * Plugins register navigation during bootstrap:
 * ```php
 * // In Plugin::bootstrap()
 * NavigationRegistry::register('Awards', [
 *     ['type' => 'item', 'label' => 'Awards', 'url' => ['plugin' => 'Awards']]
 * ], [$this, 'getDynamicNavItems']);
 * ```
 * 
 * ## Security Considerations
 * 
 * - Navigation items are filtered through Member::canAccessUrl() authorization
 * - Session storage requires proper session security configuration  
 * - Dynamic callbacks should validate user permissions before returning items
 * - Badge value callbacks must sanitize inputs to prevent code injection
 * 
 * @see \App\Services\NavigationService Business logic service layer
 * @see \App\Services\CoreNavigationProvider Core application navigation items
 * @see \App\View\Cell\AppNavCell View cell that renders navigation
 */
class NavigationRegistry
{
    use StaticConfigTrait;

    /**
     * Registry of navigation items organized by source
     * 
     * Structure: [source => ['items' => [...], 'callback' => callable|null]]
     * 
     * @var array
     */
    private static array $navigationItems = [];

    /**
     * Flag indicating if the registry has been initialized
     * 
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * Register navigation items from a source
     * 
     * Registers static navigation items and an optional callback for dynamic item generation.
     * Sources should be unique identifiers (e.g., 'core', plugin names) to avoid conflicts.
     * 
     * Static items are always included, while callback items are generated on-demand based
     * on current user context and request parameters. This allows for flexible navigation
     * that can respond to user permissions, data counts, or application state.
     * 
     * @param string $source Unique source identifier (e.g., 'core', 'Awards', 'Officers')
     * @param array $items Static navigation items following KMP navigation structure
     * @param callable|null $callback Optional function for dynamic item generation
     *                               Signature: function(Member $user, array $params): array
     * 
     * @return void
     * 
     * @example
     * ```php
     * // Register static items only
     * NavigationRegistry::register('Reports', [
     *     ['type' => 'parent', 'label' => 'Reports', 'icon' => 'bi-graph-up']
     * ]);
     * 
     * // Register with dynamic callback
     * NavigationRegistry::register('Awards', $staticItems, function($user, $params) {
     *     if ($user->hasRole('Awards Officer')) {
     *         return [['type' => 'item', 'label' => 'Manage Awards', 'url' => ['action' => 'manage']]];
     *     }
     *     return [];
     * });
     * ```
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
     * Retrieves navigation items from all registered sources, processes dynamic callbacks,
     * and applies session caching for performance. Items are returned in registration order
     * but can be sorted by the view layer using the 'order' property.
     * 
     * The session caching mechanism stores computed navigation items to avoid regenerating
     * them on every request. This cache is cleared when the session is destroyed or when
     * navigation registration changes.
     * 
     * Dynamic callbacks receive the current user and request parameters, allowing them to:
     * - Generate user-specific navigation items
     * - Include data-driven badges (e.g., pending count indicators)
     * - Modify items based on user permissions or roles
     * - Respond to current application context
     * 
     * @param Member $user The current authenticated user
     * @param array $params Request parameters for context (controller, action, etc.)
     * 
     * @return array All navigation items from registered sources
     * 
     * @example
     * ```php
     * $navigationService = new NavigationService();
     * $items = NavigationRegistry::getNavigationItems($currentUser, [
     *     'controller' => 'Members',
     *     'action' => 'index'
     * ]);
     * 
     * // Items structure:
     * // [
     * //     ['type' => 'parent', 'label' => 'Members', 'icon' => 'bi-people'],
     * //     ['type' => 'item', 'label' => 'View All', 'parent' => 'members'],
     * //     ['type' => 'item', 'label' => 'Add New', 'parent' => 'members']
     * // ]
     * ```
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        self::ensureInitialized();

        $allItems = [];
        // Check for cached items in session for performance
        if (isset($_SESSION['navigation_items']) && is_array($_SESSION['navigation_items'])) {
            $allItems = $_SESSION['navigation_items'];
            return $allItems;
        }

        // Process all registered sources
        foreach (self::$navigationItems as $source => $registration) {
            $items = $registration['items'];

            // Execute dynamic callback if provided
            if ($registration['callback'] !== null) {
                $dynamicItems = call_user_func($registration['callback'], $user, $params);
                if (is_array($dynamicItems)) {
                    $items = array_merge($items, $dynamicItems);
                }
            }

            $allItems = array_merge($allItems, $items);
        }

        // Cache processed items in session for performance
        $_SESSION['navigation_items'] = $allItems;
        return $allItems;
    }

    /**
     * Remove navigation items from a specific source
     * 
     * Unregisters all navigation items and callbacks from the specified source.
     * Useful for plugin cleanup or dynamic navigation management. This operation
     * will clear the session cache to ensure stale items are not displayed.
     * 
     * @param string $source Source identifier to remove
     * 
     * @return void
     * 
     * @example
     * ```php
     * // Remove plugin navigation during plugin unload
     * NavigationRegistry::unregister('Awards');
     * 
     * // Clear core navigation (typically not recommended)
     * NavigationRegistry::unregister('core');
     * ```
     */
    public static function unregister(string $source): void
    {
        unset(self::$navigationItems[$source]);
        // Clear session cache since navigation has changed
        if (isset($_SESSION['navigation_items'])) {
            unset($_SESSION['navigation_items']);
        }
    }

    /**
     * Get navigation items from a specific source
     * 
     * Retrieves navigation items from a single registered source, including any
     * dynamic items generated by the source's callback. Useful for debugging,
     * testing, or building specialized navigation displays.
     * 
     * @param string $source Source identifier to retrieve
     * @param Member $user The current authenticated user  
     * @param array $params Request parameters for callback context
     * 
     * @return array Navigation items from the specified source, or empty array if not found
     * 
     * @example
     * ```php
     * // Get only core navigation items
     * $coreItems = NavigationRegistry::getNavigationItemsFromSource('core', $user);
     * 
     * // Get plugin-specific items with context
     * $awardItems = NavigationRegistry::getNavigationItemsFromSource('Awards', $user, [
     *     'current_branch' => $branchId
     * ]);
     * ```
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
