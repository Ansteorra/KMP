<?php

declare(strict_types=1);

namespace Activities\Services;

use App\Model\Entity\Member;
use App\KMP\StaticHelpers;

/**
 * Provides navigation items for the Activities plugin.
 *
 * Generates navigation structure for activity configuration, management, and reporting.
 * Approval queue navigation is handled by the core unified approvals system.
 *
 * @see StaticHelpers Plugin management utilities
 * @see Member User entity for permission context
 */
class ActivitiesNavigationProvider
{
    /**
     * Build the Activities plugin navigation structure with dynamic badges and permission-aware visibility.
     *
     * When the Activities plugin is disabled this returns an empty array. Otherwise it returns
     * an array of associative navigation item definitions (labels, urls, icons, order, optional
     * badge configuration and active path hints) suitable for rendering the Activities menu.
     *
     * @param Member $user The current authenticated member used for personalization and badge calculations.
     * @param array $params Optional context parameters (currently unused).
     * @return array An array of navigation item definitions for the Activities plugin. 
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        if (StaticHelpers::pluginEnabled('Activities') == false) {
            return [];
        }

        return [
            // "My Auth Queue" and "Pending Auths" removed — unified into top-level "My Approvals"
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Activity Groups",
                "order" => 20,
                "url" => [
                    "controller" => "ActivityGroups",
                    "plugin" => "Activities",
                    "action" => "index",
                    "model" => "Activities.ActivityGroups",
                ],
                "icon" => "bi-archive",
                "activePaths" => [
                    "activities/ActivityGroups/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Activities",
                "order" => 30,
                "url" => [
                    "controller" => "Activities",
                    "action" => "index",
                    "plugin" => "Activities",
                    "model" => "Activities.Activities",
                ],
                "icon" => "bi-collection",
                "activePaths" => [
                    "activities/activities/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Reports"],
                "label" => "Activity Authorizations",
                "order" => 10,
                "url" => [
                    "controller" => "Reports",
                    "action" => "Authorizations",
                    "plugin" => "Activities",
                ],
                "icon" => "bi-person-lines-fill",
            ]
        ];
    }
}