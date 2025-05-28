<?php

declare(strict_types=1);

namespace Activities\Services;

use App\KMP\StaticHelpers;
use Activities\View\Cell\PermissionActivitiesCell;
use Activities\View\Cell\MemberAuthorizationsCell;
use Activities\View\Cell\MemberAuthorizationDetailsJSONCell;

/**
 * Activities View Cell Provider
 * 
 * Provides view cell configurations for the Activities plugin
 */
class ActivitiesViewCellProvider
{
    /**
     * Get view cells for the Activities plugin
     *
     * @param array $urlParams URL parameters from request
     * @param mixed $user Current user
     * @return array View cell configurations
     */
    public static function getViewCells(array $urlParams, $user = null): array
    {
        // Check if plugin is enabled
        if (!StaticHelpers::pluginEnabled('Activities')) {
            return [];
        }

        $cells = [];

        // Permission Activities Cell - shows activities related to a permission
        $permissionActivitiesConfig = PermissionActivitiesCell::getViewConfigForRoute($urlParams, $user);
        if ($permissionActivitiesConfig) {
            $cells[] = array_merge($permissionActivitiesConfig, [
                'validRoutes' => [
                    ['controller' => 'Permissions', 'action' => 'view', 'plugin' => null],
                ]
            ]);
        }

        // Member Authorizations Cell - shows authorizations for a member
        $memberAuthorizationsConfig = MemberAuthorizationsCell::getViewConfigForRoute($urlParams, $user);
        if ($memberAuthorizationsConfig) {
            $cells[] = array_merge($memberAuthorizationsConfig, [
                'validRoutes' => [
                    ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
                ]
            ]);
        }

        // Member Authorization Details JSON Cell - provides JSON data for mobile/card views
        $memberAuthDetailsJSONConfig = MemberAuthorizationDetailsJSONCell::getViewConfigForRoute($urlParams, $user);
        if ($memberAuthDetailsJSONConfig) {
            $cells[] = array_merge($memberAuthDetailsJSONConfig, [
                'validRoutes' => [
                    ['controller' => 'Members', 'action' => 'viewCardJson', 'plugin' => null],
                    ['controller' => 'Members', 'action' => 'viewMobileCardJson', 'plugin' => null],
                ]
            ]);
        }

        return $cells;
    }
}
