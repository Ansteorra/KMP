<?php

declare(strict_types=1);

namespace Officers\Services;

use App\KMP\StaticHelpers;
use Officers\View\Cell\BranchOfficersCell;
use Officers\View\Cell\BranchRequiredOfficersCell;
use Officers\View\Cell\MemberOfficersCell;
use App\Services\ViewCellRegistry;

/**
 * Officers View Cell Provider
 * 
 * Provides view cell configurations for the Officers plugin
 */
class OfficersViewCellProvider
{
    /**
     * Get view cells for the Officers plugin
     *
     * @param array $urlParams URL parameters from request
     * @param mixed $user Current user
     * @return array View cell configurations
     */
    public static function getViewCells(array $urlParams, $user = null): array
    {
        // Check if plugin is enabled
        if (!StaticHelpers::pluginEnabled('Officers')) {
            return [];
        }

        $cells = [];

        // Branch Officers Cell - shows officers for a branch
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
            'label' => 'Officers',
            'id' => 'branch-officers',
            'order' => 1,
            'tabBtnBadge' => null,
            'cell' => 'Officers.BranchOfficers',
            'validRoutes' => [
                ['controller' => 'Branches', 'action' => 'view', 'plugin' => null],
            ]
        ];

        // Branch Required Officers Cell - shows required officers for a branch
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_DETAIL,
            'label' => 'Officers',
            'id' => 'branch-required-officers',
            'order' => 1,
            'tabBtnBadge' => null,
            'cell' => 'Officers.BranchRequiredOfficers',
            'validRoutes' => [
                ['controller' => 'Branches', 'action' => 'view', 'plugin' => null],
            ]
        ];

        // Member Officers Cell - shows offices held by a member
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
            'label' => 'Offices',
            'id' => 'member-officers',
            'order' => 2,
            'tabBtnBadge' => null,
            'cell' => 'Officers.MemberOfficers',
            'validRoutes' => [
                ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
                ['controller' => 'Members', 'action' => 'profile', 'plugin' => null]
            ]
        ];

        return $cells;
    }
}