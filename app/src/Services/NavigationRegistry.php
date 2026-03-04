<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Member;
use Cake\Core\StaticConfigTrait;
use Cake\Http\Session;

/**
 * Centralized registry for application navigation items from core and plugins.
 *
 * Uses static registration pattern instead of events. Plugins register navigation
 * during bootstrap. Items are session-cached and filtered via Member::canAccessUrl().
 *
 * @see \App\Services\NavigationService Business logic service layer
 * @see \App\View\Cell\AppNavCell View cell that renders navigation
 */
class NavigationRegistry
{
    use StaticConfigTrait;
    private const NAVIGATION_CACHE_VERSION = 2;

    /**
     * @var array [source => ['items' => [...], 'callback' => callable|null]]
     */
    private static array $navigationItems = [];

    /**
     * @var bool
     */
    private static bool $initialized = false;

    /**
     * Register navigation items from a source.
     *
     * @param string $source Unique source identifier (e.g., 'core', 'Awards')
     * @param array $items Static navigation items
     * @param callable|null $callback Optional dynamic item generator: fn(Member, array): array
     * @return void
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
    public static function getNavigationItems(Member $user, array $params = [], ?Session $session = null): array
    {
        self::ensureInitialized();
        $latestRestoreCompletion = self::getLatestRestoreCompletionTimestamp();
        $hasRegisteredSources = !empty(self::$navigationItems);

        $allItems = [];
        // Check for cached items in session for performance
        $cached = $session?->read('navigation_items');
        if ($cached === null && isset($_SESSION['navigation_items']) && is_array($_SESSION['navigation_items'])) {
            $cached = $_SESSION['navigation_items'];
        }
        if (is_array($cached)) {
            if (
                isset($cached['user_id'], $cached['items'])
                && (int)$cached['user_id'] === (int)$user->id
                && is_array($cached['items'])
                && (int)($cached['nav_version'] ?? 0) === self::NAVIGATION_CACHE_VERSION
                && !self::isCachedNavigationStaleForRestore($cached, $latestRestoreCompletion)
                && (!$hasRegisteredSources || $cached['items'] !== [])
            ) {
                return $cached['items'];
            }
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
        if (!$hasRegisteredSources || $allItems !== []) {
            $payload = [
                'user_id' => (int)$user->id,
                'items' => $allItems,
                'nav_version' => self::NAVIGATION_CACHE_VERSION,
                'generated_at' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
            ];
            if ($session !== null) {
                $session->write('navigation_items', $payload);
            } else {
                $_SESSION['navigation_items'] = $payload;
            }
        } else {
            if ($session !== null) {
                $session->delete('navigation_items');
            } else {
                unset($_SESSION['navigation_items']);
            }
        }
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
        unset($_SESSION['navigation_items']);
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
        if (isset($_SESSION['navigation_items'])) {
            unset($_SESSION['navigation_items']);
        }
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

    /**
     * @param array<string, mixed> $cached
     */
    private static function isCachedNavigationStaleForRestore(array $cached, ?int $latestRestoreCompletion): bool
    {
        if ($latestRestoreCompletion === null) {
            return false;
        }

        $generatedAt = $cached['generated_at'] ?? null;
        if (!is_string($generatedAt) || $generatedAt === '') {
            return true;
        }

        $generatedTs = strtotime($generatedAt);
        if (!is_int($generatedTs)) {
            return true;
        }

        return $generatedTs < $latestRestoreCompletion;
    }

    private static function getLatestRestoreCompletionTimestamp(): ?int
    {
        $status = (new RestoreStatusService())->getStatus();
        $completedAt = $status['completed_at'] ?? null;
        if (!is_string($completedAt) || $completedAt === '') {
            return null;
        }

        $timestamp = strtotime($completedAt);
        if (!is_int($timestamp)) {
            return null;
        }

        return $timestamp;
    }
}
