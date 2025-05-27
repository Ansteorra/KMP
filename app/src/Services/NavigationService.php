<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Member;

/**
 * Navigation Service
 * Handles navigation-related business logic
 */
class NavigationService
{
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
}
