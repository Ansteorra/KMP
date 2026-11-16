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
 * @see https://github.com/Ansteorra/KMP/blob/main/docs/5.2.17-awards-services.md Full documentation
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
                'icon' => 'badge-alert',
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
                'icon' => 'speakerphone',
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
                'icon' => 'award',
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
                'icon' => 'compass',
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
                'icon' => 'ladder',
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
                'icon' => 'award',
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
                'icon' => 'list-check',
                'activePaths' => [
                    'awards/approval-processes/view/*',
                    'awards/approval-processes/index*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Bestowal To-Do Templates',
                'order' => 34,
                'url' => [
                    'controller' => 'BestowalTodoTemplates',
                    'plugin' => 'Awards',
                    'action' => 'index',
                    'model' => 'Awards.BestowalTodoTemplates',
                ],
                'icon' => 'list-check',
                'activePaths' => [
                    'awards/bestowal-todo-templates/view/*',
                    'awards/bestowal-todo-templates/index*',
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
                'icon' => 'speakerphone',
                'linkTypeClass' => 'btn',
                'otherClasses' => StaticHelpers::getAppSetting('Awards.RecButtonClass'),
            ],
        ];
    }
}
