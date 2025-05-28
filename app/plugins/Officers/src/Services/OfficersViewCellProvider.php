<?php

declare(strict_types=1);

namespace Officers\Services;

use App\KMP\StaticHelpers;
use Officers\View\Cell\BranchOfficersCell;
use Officers\View\Cell\BranchRequiredOfficersCell;
use Officers\View\Cell\MemberOfficersCell;

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
        $branchOfficersConfig = BranchOfficersCell::getViewConfigForRoute($urlParams, $user);
        if ($branchOfficersConfig) {
            $cells[] = array_merge($branchOfficersConfig, [
                'validRoutes' => [
                    ['controller' => 'Branches', 'action' => 'view', 'plugin' => null],
                ]
            ]);
        }

        // Branch Required Officers Cell - shows required officers for a branch
        $branchRequiredOfficersConfig = BranchRequiredOfficersCell::getViewConfigForRoute($urlParams, $user);
        if ($branchRequiredOfficersConfig) {
            $cells[] = array_merge($branchRequiredOfficersConfig, [
                'validRoutes' => [
                    ['controller' => 'Branches', 'action' => 'view', 'plugin' => null],
                ]
            ]);
        }

        // Member Officers Cell - shows offices held by a member
        $memberOfficersConfig = MemberOfficersCell::getViewConfigForRoute($urlParams, $user);
        if ($memberOfficersConfig) {
            $cells[] = array_merge($memberOfficersConfig, [
                'validRoutes' => [
                    ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
                ]
            ]);
        }

        return $cells;
    }
}
