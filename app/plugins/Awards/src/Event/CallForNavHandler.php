<?php

namespace Awards\Event;

use Cake\Event\EventListenerInterface;
use App\KMP\StaticHelpers;

class CallForNavHandler implements EventListenerInterface
{
    public function implementedEvents(): array
    {
        return [
            \App\View\Cell\NavigationCell::VIEW_CALL_EVENT => 'callForNav',
        ];
    }

    public function callForNav($event)
    {
        if (StaticHelpers::pluginEnabled('Awards') == false) {
            return null;
        }
        $user = $event->getData('user');
        $results = [];
        if ($event->getResult() && is_array($event->getResult())) {
            $results = $event->getResult();
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
                "mergePath" => ["Award Recs."],
                "label" => "Kanban Board",
                "order" => 30,
                "url" => [
                    "controller" => "Recommendations",
                    "plugin" => "Awards",
                    "action" => "board",
                    "model" => "Awards.Recommendations",
                ],
                "icon" => "bi-kanban",
                "activePaths" => [
                    "awards/Recommendations/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Award Recs.", "Recommendations"],
                "label" => "New Recommendation",
                "order" => 0,
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
                "mergePath" => ["Config"],
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
                "mergePath" => ["Config", "Award Events"],
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
                "otherClasses" => StaticHelpers::getAppSetting("Awards.RecButtonClass", "btn-warning"),
            ]
        ];

        $results = array_merge($results, $appNav);
        return $results;
    }
}