<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Member;

/**
 * Navigation Service
 * Handles navigation-related business logic and integrates with NavigationRegistry
 */
class NavigationService
{
    /**
     * Get all navigation items for a user from the registry
     *
     * @param Member $user Current user
     * @param array $params Request parameters
     * @return array All navigation items
     */
    public function getNavigationItems(Member $user, array $params = []): array
    {
        return NavigationRegistry::getNavigationItems($user, $params);
    }

    /**
     * Process badge value configuration
     */
    public function processBadgeValue($badgeConfig): int
    {
        if (
            is_array($badgeConfig)
            && isset($badgeConfig['class'], $badgeConfig['method'], $badgeConfig['argument'])
        ) {
            return call_user_func(
                [$badgeConfig['class'], $badgeConfig['method']],
                $badgeConfig['argument']
            );
        }

        return (int)$badgeConfig;
    }

    /**
     * Check if navigation item should be displayed
     */
    public function shouldDisplayNavItem(array $navItem, Member $user): bool
    {
        $url = $navItem['url'];
        $url['plugin'] = $url['plugin'] ?? false;

        return $user->canAccessUrl($url);
    }

    /**
     * Process navigation state from session/cookies
     */
    public function processNavBarState(?array $navBarState): array
    {
        return $navBarState ?: [];
    }

    /**
     * Build navigation item classes
     */
    public function buildNavItemClasses(array $item, bool $isActive = false): string
    {
        $linkTypeClass = $item['linkTypeClass'] ?? 'nav-link';
        $otherClasses = $item['otherClasses'] ?? '';
        $activeClass = $isActive ? 'active' : '';

        return trim("{$linkTypeClass} {$otherClasses} {$activeClass}");
    }

    /**
     * Get debug information about registered navigation
     *
     * @return array Debug information
     */
    public function getDebugInfo(): array
    {
        return NavigationRegistry::getDebugInfo();
    }

    /**
     * Get navigation items from a specific source
     *
     * @param string $source Source identifier
     * @param Member $user Current user
     * @param array $params Request parameters
     * @return array Navigation items from the specified source
     */
    public function getNavigationItemsFromSource(string $source, Member $user, array $params = []): array
    {
        return NavigationRegistry::getNavigationItemsFromSource($source, $user, $params);
    }
}
