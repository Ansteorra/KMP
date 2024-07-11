<?php

namespace Activities\Event;

use Cake\Event\EventListenerInterface;
use App\KMP\StaticHelpers;

class CallForNavHandler implements EventListenerInterface
{
    public function implementedEvents(): array
    {
        return [
            // Custom event names let you design your application events
            // as required.
            \App\View\Cell\NavigationCell::VIEW_CALL_EVENT => 'callForNav',
        ];
    }

    public function callForNav($event)
    {
        if (StaticHelpers::pluginEnabled('Activities') == false) {
            return null;
        }
        $user = $event->getData('user');
        $results = [];
        if ($event->getResult() && is_array($event->getResult())) {
            $results = $event->getResult();
        }
        $appNav = [
            [
                "type" => "link",
                "mergePath" => ["Members", $user->sca_name],
                "label" => "My Auth Queue",
                "order" => 20,
                "url" => [
                    "controller" => "AuthorizationApprovals",
                    "plugin" => "Activities",
                    "model" => "Activities.AuthorizationApprovals",
                    "action" => "myQueue",
                ],
                "icon" => "bi-person-fill-check",
                "badgeClass" => "bg-danger",
                "badgeValue" => [
                    "class" => "Activities\Model\Table\AuthorizationApprovalsTable",
                    "method" => "memberAuthQueueCount",
                    "argument" => $user->id
                ],
            ],
            [
                "type" => "link",
                "mergePath" => ["Members", "Members"],
                "label" => "Auth Queues",
                "order" => 10,
                "url" => [
                    "controller" => "AuthorizationApprovals",
                    "action" => "index",
                    "plugin" => "Activities",
                    "model" => "Activities.AuthorizationApprovals",
                ],
                "icon" => "bi-card-checklist",
                "activePaths" => [
                    "activities/AuthorizationApprovals/view/*",
                ]

            ],
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
                "mergePath" => ["Config", "Activity Groups"],
                "label" => "New Activity Group",
                "order" => 0,
                "url" => [
                    "controller" => "ActivityGroups",
                    "plugin" => "Activities",
                    "action" => "add",
                    "model" => "Activities.ActivityGroups",
                ],
                "icon" => "bi-plus",
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
                "mergePath" => ["Config", "Activities"],
                "label" => "New Activity",
                "order" => 0,
                "url" => [
                    "controller" => "Activities",
                    "action" => "add",
                    "plugin" => "Activities",
                    "model" => "Activities.Activities",
                ],
                "icon" => "bi-plus",
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

        $results = array_merge($results, $appNav);
        return $results;
    }
}