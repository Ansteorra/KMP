<?php

declare(strict_types=1);

namespace Awards\Services;

use App\KMP\StaticHelpers;
use Awards\View\Cell\MemberSubmittedRecsCell;
use Awards\View\Cell\RecsForMemberCell;

/**
 * Awards View Cell Provider
 * 
 * Provides view cell configurations for the Awards plugin
 */
class AwardsViewCellProvider
{
    /**
     * Get view cells for the Awards plugin
     *
     * @param array $urlParams URL parameters from request
     * @param mixed $user Current user
     * @return array View cell configurations
     */
    public static function getViewCells(array $urlParams, $user = null): array
    {
        // Check if plugin is enabled
        if (!StaticHelpers::pluginEnabled('Awards')) {
            return [];
        }

        $cells = [];

        // Member Submitted Recs Cell - shows award recommendations submitted by a member
        $memberSubmittedRecsConfig = MemberSubmittedRecsCell::getViewConfigForRoute($urlParams, $user);
        if ($memberSubmittedRecsConfig) {
            $cells[] = array_merge($memberSubmittedRecsConfig, [
                'validRoutes' => [
                    ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
                ]
            ]);
        }

        // Recs For Member Cell - shows award recommendations received by a member
        $recsForMemberConfig = RecsForMemberCell::getViewConfigForRoute($urlParams, $user);
        if ($recsForMemberConfig) {
            $cells[] = array_merge($recsForMemberConfig, [
                'validRoutes' => [
                    ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
                ]
            ]);
        }

        return $cells;
    }
}
