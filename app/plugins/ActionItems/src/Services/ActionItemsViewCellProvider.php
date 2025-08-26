<?php

declare(strict_types=1);

namespace ActionItems\Services;

use App\KMP\StaticHelpers;
use ActionItems\View\Cell\PermissionActionItemsCell;
use ActionItems\View\Cell\MemberAuthorizationsCell;
use ActionItems\View\Cell\MemberAuthorizationDetailsJSONCell;
use App\Services\ViewCellRegistry;
use App\View\Cell\BasePluginCell;

/**
 * ActionItems View Cell Provider
 * 
 * Provides view cell configurations for the ActionItems plugin
 */
class ActionItemsViewCellProvider
{
    /**
     * Get view cells for the ActionItems plugin
     *
     * @param array $urlParams URL parameters from request
     * @param mixed $user Current user
     * @return array View cell configurations
     */
    public static function getViewCells(array $urlParams, $user = null): array
    {
        //disabling this call for now.
        return [];
        // Check if plugin is enabled
        if (!StaticHelpers::pluginEnabled('ActionItems')) {
            return [];
        }

        $cells = [];

        // cell for ActionItems that have permissions
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
            'label' => 'ActionItems',
            'id' => 'permission-ActionItems',
            'order' => 2,
            'tabBtnBadge' => null,
            'cell' => 'ActionItems.PermissionActionItems',
            'validRoutes' => [
                ['controller' => 'Permissions', 'action' => 'view', 'plugin' => null],
            ]
        ];

        // Cell of ActionItems for member profiles
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
            'label' => 'Authorizations',
            'id' => 'member-authorizations',
            'order' => 1,
            'tabBtnBadge' => null,
            'cell' => 'ActionItems.MemberAuthorizations',
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
            'cell' => 'ActionItems.MemberAuthorizationDetailsJSON',
            'validRoutes' => [
                ['controller' => 'Members', 'action' => 'viewCardJson', 'plugin' => null],
                ['controller' => 'Members', 'action' => 'viewMobileCardJson', 'plugin' => null],
            ]
        ];

        return $cells;
    }
}
