<?php

declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\Member;
use App\KMP\StaticHelpers;
use Awards\Model\Entity\Recommendation;

/**
 * Provides navigation integration for the Awards plugin.
 * 
 * Generates navigation items for award recommendation workflows, administrative tools,
 * configuration management, and reporting. Creates dynamic status-based navigation
 * items for each recommendation workflow state.
 * 
 * @see \App\KMP\StaticHelpers Plugin availability checking
 * @see \Awards\Model\Entity\Recommendation Recommendation status definitions
 * @see /docs/5.2.17-awards-services.md Full documentation
 */
class AwardsNavigationProvider
{
    /**
     * Builds the Awards plugin navigation tree with static sections and per-status recommendation links.
     *
     * The returned structure contains a parent header and core navigation items (Recommendations, Award Domains,
     * Award Levels, Awards, Submit Award Rec.) plus additional links generated for each recommendation status that
     * filter the Recommendations list. Items include mergePath, icon, order, URL, and active path metadata for UI integration.
     *
     * @param \App\Model\Entity\Member $user The current authenticated user used for authorization/context.
     * @param array $params Optional request parameters that may influence active path or contextual navigation.
     * @return array An array of navigation item arrays organized hierarchically, including static items and status-filtered recommendation links.
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        if (StaticHelpers::pluginEnabled('Awards') == false) {
            return [];
        }

        $statuses = Recommendation::getStatuses();
        $listLinks = [];
        $order = 0;

        $appNav = [
            [
                "type" => "parent",
                "label" => "Award Recs.",
                "icon" => "bi-patch-exclamation-fill",
                "id" => "navheader_award_recs",
                "order" => 40,
            ],
            [
                "type" => "link",
                "mergePath" => ["Award Recs."],
                "label" => "Recommendations",
                "order" => 30,
                "url" => [
                    "controller" => "Recommendations",
                    "plugin" => "Awards",
                    "action" => "index",
                    "model" => "Awards.Recommendations",
                ],
                "icon" => "bi-megaphone",
                "activePaths" => [
                    "awards/Recommendations/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Award Domains",
                "order" => 30,
                "url" => [
                    "controller" => "Domains",
                    "plugin" => "Awards",
                    "action" => "index",
                    "model" => "Awards.Domains",
                ],
                "icon" => "bi-compass",
                "activePaths" => [
                    "awards/Domains/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Award Levels",
                "order" => 31,
                "url" => [
                    "controller" => "Levels",
                    "plugin" => "Awards",
                    "action" => "index",
                    "model" => "Awards.Levels",
                ],
                "icon" => "bi-ladder",
                "activePaths" => [
                    "awards/Levels/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Awards",
                "order" => 32,
                "url" => [
                    "controller" => "Awards",
                    "plugin" => "Awards",
                    "action" => "index",
                    "model" => "Awards.Awards",
                ],
                "icon" => "bi-award",
                "activePaths" => [
                    "awards/Awards/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Members"],
                "label" => "Submit Award Rec.",
                "order" => 30,
                "url" => [
                    "controller" => "Recommendations",
                    "plugin" => "Awards",
                    "action" => "add",
                    "model" => "Awards.Recommendations",
                ],
                "icon" => "bi-megaphone-fill",
                "linkTypeClass" => "btn",
                "otherClasses" => StaticHelpers::getAppSetting("Awards.RecButtonClass"),
            ]
        ];

        return array_merge($appNav, $listLinks);
    }
}