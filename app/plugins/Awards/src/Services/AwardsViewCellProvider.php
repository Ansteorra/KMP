<?php

declare(strict_types=1);

namespace Awards\Services;

use App\KMP\StaticHelpers;
use Awards\View\Cell\MemberSubmittedRecsCell;
use Awards\View\Cell\RecsForMemberCell;
use App\Services\ViewCellRegistry;
use Cake\ORM\TableRegistry;

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
        if (!$user) {
            return [];
        }
        // Check if plugin is enabled
        if (!StaticHelpers::pluginEnabled('Awards')) {
            return [];
        }
        if (!$urlParams["controller"] == 'Members') {
            return [];
        }
        //$emptyRecommendation = TableRegistry::getTableLocator()->get('Recommendations')->newEmptyEntity();

        $cells = [];

        // Member Submitted Recs Cell - shows award recommendations submitted by a member
        // only show this if you are allowed to see recommendations OR this is your own profile
        if (
            $urlParams['action'] == 'profile'
            || (
                $urlParams["action"] == 'view'
                && $user->id == $urlParams['pass'][0]
            )
            || ($user->can('ViewSubmittedByMember', 'Awards.Recommendations'))
        ) {
            $cells[] = [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'Submitted Award Recs.',
                'id' => 'member-submitted-recs',
                'order' => 3,
                'tabBtnBadge' => null,
                'cell' => 'Awards.MemberSubmittedRecs',
                'validRoutes' => [
                    ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
                    ['controller' => 'Members', 'action' => 'profile', 'plugin' => null]
                ]
            ];
        }

        // Recs For Member Cell - shows award recommendations received by a member
        // you can't see this if you are looking at your own profile
        if (
            $urlParams['action'] != 'profile'
            && (
                $urlParams["action"] == 'view'
                && $user->id != $urlParams['pass'][0]
            )
            &&
            ($user->can('ViewSubmittedForMember', 'Awards.Recommendations'))
        ) {
            $cells[] = [
                'type' => ViewCellRegistry::PLUGIN_TYPE_TAB,
                'label' => 'Received Award Recs.',
                'id' => 'recs-for-member',
                'order' => 4,
                'tabBtnBadge' => null,
                'cell' => 'Awards.RecsForMember',
                'validRoutes' => [
                    ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
                ]
            ];
        }
        return $cells;
    }
}