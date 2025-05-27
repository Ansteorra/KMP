<?php

declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\Member;
use App\KMP\StaticHelpers;
use Awards\Model\Entity\Recommendation;

/**
 * Awards Navigation Provider
 * 
 * Provides Awards plugin navigation items.
 * Replaces the functionality from Awards\Event\CallForNavHandler
 */
class AwardsNavigationProvider
{
    /**
     * Get Awards plugin navigation items
     *
     * @param Member $user Current user
     * @param array $params Request parameters
     * @return array Navigation items
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        if (StaticHelpers::pluginEnabled('Awards') == false) {
            return [];
        }

        $statuses = Recommendation::getStatuses();
        $listLinks = [];
        $order = 0;

        foreach ($statuses as $statusKey => $statusKey) {
            $listLinks[] = [
                "type" => "link",
                "mergePath" => ["Award Recs.", "Recommendations"],
                "label" => $statusKey,
                "order" => $order++,
                "url" => [
                    "controller" => "Recommendations",
                    "plugin" => "Awards",
                    "action" => "index",
                    "model" => "Awards.Recommendations",
                    "?" => [
                        "status" => $statusKey,
                        "view" => $statusKey,
                    ],
                ],
                "icon" => "bi-file-earmark-check",
                "activePaths" => [
                    "awards/Recommendations/view/*",
                ]
            ];
        }

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
                "mergePath" => ["Award Recs.", "Recommendations"],
                "label" => "New Recommendation",
                "order" => 20,
                "url" => [
                    "controller" => "Recommendations",
                    "plugin" => "Awards",
                    "action" => "add",
                    "model" => "Awards.Recommendations",
                ],
                "icon" => "bi-plus",
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
                "mergePath" => ["Config", "Award Domains"],
                "label" => "New Award Domain",
                "order" => 0,
                "url" => [
                    "controller" => "Domains",
                    "plugin" => "Awards",
                    "action" => "add",
                    "model" => "Awards.Domains",
                ],
                "icon" => "bi-plus",
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
                "mergePath" => ["Config", "Award Levels"],
                "label" => "New Award Domain",
                "order" => 0,
                "url" => [
                    "controller" => "Levels",
                    "plugin" => "Awards",
                    "action" => "add",
                    "model" => "Awards.Levels",
                ],
                "icon" => "bi-plus",
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
                "mergePath" => ["Config", "Awards"],
                "label" => "New Award",
                "order" => 0,
                "url" => [
                    "controller" => "Awards",
                    "plugin" => "Awards",
                    "action" => "add",
                    "model" => "Awards.Awards",
                ],
                "icon" => "bi-plus",
            ],
            [
                "type" => "link",
                "mergePath" => ["Award Recs."],
                "label" => "Award Events",
                "order" => 33,
                "url" => [
                    "controller" => "Events",
                    "plugin" => "Awards",
                    "action" => "index",
                    "model" => "Awards.Events",
                ],
                "icon" => "bi-calendar-check",
                "activePaths" => [
                    "awards/Events/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Award Recs.", "Award Events"],
                "label" => "New Award Events",
                "order" => 0,
                "url" => [
                    "controller" => "Events",
                    "plugin" => "Awards",
                    "action" => "add",
                    "model" => "Awards.Events",
                ],
                "icon" => "bi-plus",
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
