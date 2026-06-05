<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\StaticHelpers;
use App\Model\Entity\Member;

/**
 * Provides navigation integration for the Awards plugin.
 *
 * Generates navigation items for award recommendations, bestowals, administrative tools,
 * configuration management, and reporting. Status filtering for list pages uses in-page
 * grid tabs (same pattern as Recommendations and Bestowals index).
 *
 * @see \App\KMP\StaticHelpers Plugin availability checking
 * @see /docs/5.2.17-awards-services.md Full documentation
 */
class AwardsNavigationProvider
{
    /**
     * Builds the Awards plugin navigation tree.
     *
     * @param \App\Model\Entity\Member $user The current authenticated user used for authorization/context.
     * @param array $params Optional request parameters that may influence active path or contextual navigation.
     * @return array Navigation item arrays with mergePath, icon, order, URL, and active path metadata.
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        if (StaticHelpers::pluginEnabled('Awards') == false) {
            return [];
        }

        return [
            [
                'type' => 'parent',
                'label' => 'Award Recs.',
                'icon' => 'bi-patch-exclamation-fill',
                'id' => 'navheader_award_recs',
                'order' => 40,
            ],
            [
                'type' => 'link',
                'mergePath' => ['Award Recs.'],
                'label' => 'Recommendations',
                'order' => 30,
                'url' => [
                    'controller' => 'Recommendations',
                    'plugin' => 'Awards',
                    'action' => 'index',
                    'model' => 'Awards.Recommendations',
                ],
                'icon' => 'bi-megaphone',
                'activePaths' => [
                    'awards/Recommendations/view/*',
                    'awards/Recommendations/index*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Award Recs.'],
                'label' => 'Bestowals',
                'order' => 31,
                'url' => [
                    'controller' => 'Bestowals',
                    'plugin' => 'Awards',
                    'action' => 'index',
                    'model' => 'Awards.Bestowals',
                ],
                'icon' => 'bi-award',
                'activePaths' => [
                    'awards/Bestowals/view/*',
                    'awards/Bestowals/index*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Award Domains',
                'order' => 30,
                'url' => [
                    'controller' => 'Domains',
                    'plugin' => 'Awards',
                    'action' => 'index',
                    'model' => 'Awards.Domains',
                ],
                'icon' => 'bi-compass',
                'activePaths' => [
                    'awards/Domains/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Award Levels',
                'order' => 31,
                'url' => [
                    'controller' => 'Levels',
                    'plugin' => 'Awards',
                    'action' => 'index',
                    'model' => 'Awards.Levels',
                ],
                'icon' => 'bi-ladder',
                'activePaths' => [
                    'awards/Levels/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Awards',
                'order' => 32,
                'url' => [
                    'controller' => 'Awards',
                    'plugin' => 'Awards',
                    'action' => 'index',
                    'model' => 'Awards.Awards',
                ],
                'icon' => 'bi-award',
                'activePaths' => [
                    'awards/Awards/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Award Approval Processes',
                'order' => 33,
                'url' => [
                    'controller' => 'ApprovalProcesses',
                    'plugin' => 'Awards',
                    'action' => 'index',
                    'model' => 'Awards.ApprovalProcesses',
                ],
                'icon' => 'bi-ui-checks-grid',
                'activePaths' => [
                    'awards/approval-processes/view/*',
                    'awards/approval-processes/index*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Bestowal Statuses',
                'order' => 36,
                'url' => [
                    'controller' => 'BestowalStatuses',
                    'plugin' => 'Awards',
                    'action' => 'index',
                    'model' => 'Awards.BestowalStatuses',
                ],
                'icon' => 'bi-diagram-3-fill',
                'activePaths' => [
                    'awards/bestowal-statuses/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Bestowal States',
                'order' => 37,
                'url' => [
                    'controller' => 'BestowalStates',
                    'plugin' => 'Awards',
                    'action' => 'index',
                    'model' => 'Awards.BestowalStates',
                ],
                'icon' => 'bi-signpost-2-fill',
                'activePaths' => [
                    'awards/bestowal-states/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Members'],
                'label' => 'Submit Award Rec.',
                'order' => 30,
                'url' => [
                    'controller' => 'Recommendations',
                    'plugin' => 'Awards',
                    'action' => 'add',
                    'model' => 'Awards.Recommendations',
                ],
                'icon' => 'bi-megaphone-fill',
                'linkTypeClass' => 'btn',
                'otherClasses' => StaticHelpers::getAppSetting('Awards.RecButtonClass'),
            ],
        ];
    }
}
