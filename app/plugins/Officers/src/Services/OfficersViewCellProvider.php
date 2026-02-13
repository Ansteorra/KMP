<?php

declare(strict_types=1);

namespace Officers\Services;

use App\KMP\StaticHelpers;
use Officers\View\Cell\BranchOfficersCell;
use Officers\View\Cell\BranchRequiredOfficersCell;
use Officers\View\Cell\MemberOfficersCell;
use App\Services\ViewCellRegistry;

/**
 * View cell provider for the Officers plugin.
 * 
 * Registers view cells for branch officer display, required officers monitoring,
 * and member office tracking within branch and member profile pages.
 * 
 * @package Officers\Services
 * @see /docs/5.1-officers-plugin.md for plugin documentation
 */
class OfficersViewCellProvider
{
    /**
     * Get view cell configurations for the Officers plugin.
     * 
     * Registers:
     * - BranchOfficers: Tab showing officers for a branch (Branches.view)
     * - BranchRequiredOfficers: Detail showing required officers status (Branches.view)
     * - MemberOfficers: Tab showing offices held by a member (Members.view/profile)
     *
     * @param array $urlParams URL parameters for route context
     * @param mixed $user Current user for authorization context
     * @return array View cell configurations for ViewCellRegistry, empty if plugin disabled
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
            ],
            'authCallback' => function (array $urlParams, $user) {
                $passParams = $urlParams['pass'] ?? [];
                if (empty($passParams[0])) {
                    return true;
                }
                $branchesTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Branches');
                $branch = $branchesTable->find('byPublicId', [$passParams[0]])->first();

                return $branch ? (bool) $branch->can_have_officers : true;
            },
        ];

        // Branch Required Officers Cell - shows required officers for a branch
        //$cells[] = [
        //    'type' => ViewCellRegistry::PLUGIN_TYPE_DETAIL,
        //    'label' => 'Officers',
        //    'id' => 'branch-required-officers',
        //    'order' => 1,
        //    'tabBtnBadge' => null,
        //    'cell' => 'Officers.BranchRequiredOfficers',
        //    'validRoutes' => [
        //        ['controller' => 'Branches', 'action' => 'view', 'plugin' => null],
        //    ]
        //];

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