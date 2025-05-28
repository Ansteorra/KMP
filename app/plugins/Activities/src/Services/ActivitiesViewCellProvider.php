<?php

declare(strict_types=1);

namespace Activities\Services;

use App\KMP\StaticHelpers;
use Activities\View\Cell\PermissionActivitiesCell;
use Activities\View\Cell\MemberAuthorizationsCell;
use Activities\View\Cell\MemberAuthorizationDetailsJSONCell;
use App\Services\ViewCellRegistry;
use App\View\Cell\BasePluginCell;

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

        // cell for activities that have permissions
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
            'label' => 'Activities',
            'id' => 'permission-activities',
            'order' => 2,
            'tabBtnBadge' => null,
            'cell' => 'Activities.PermissionActivities',
            'validRoutes' => [
                ['controller' => 'Permissions', 'action' => 'view', 'plugin' => null],
            ]
        ];

        // Cell of activities for member profiles
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
            'label' => 'Authorizations',
            'id' => 'member-authorizations',
            'order' => 1,
            'tabBtnBadge' => null,
            'cell' => 'Activities.MemberAuthorizations',
            'validRoutes' => [
                ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
                ['controller' => 'Members', 'action' => 'profile', 'plugin' => null]
            ]
        ];

        // JSON cell for member authorizations
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_JSON, // 'tab' or 'detail' or 'modal'
            'id' => 'memberAuthorizations',
            'order' => 1,
            'cell' => 'Activities.MemberAuthorizationDetailsJSON',
            'validRoutes' => [
                ['controller' => 'Members', 'action' => 'viewCardJson', 'plugin' => null],
                ['controller' => 'Members', 'action' => 'viewMobileCardJson', 'plugin' => null],
            ]
        ];

        return $cells;
    }
}