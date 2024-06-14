<?php

namespace App\Event;

use Cake\Event\EventListenerInterface;

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
        $user = $event->getData('user');
        $results = [];
        if ($event->getResult() && is_array($event->getResult())) {
            $results = $event->getResult();
        }
        $appNav = [
            [
                "type" => "parent",
                "label" => "Members",
                "icon" => "bi-people",
                "order" => 0,
            ],
            [
                "type" => "parent",
                "label" => "Reports",
                "icon" => "bi-backpack4",
                "order" => 10,
            ],
            [
                "type" => "parent",
                "label" => "Config",
                "icon" => "bi-database-gear",
                "order" => 20,
            ],
            [
                "type" => "parent",
                "label" => "Security",
                "icon" => "bi-house-lock",
                "order" => 30,
            ],
            [
                "type" => "link",
                "mergePath" => ["Members"],
                "label" => "$user->sca_name",
                "icon" => "bi-person-fill",
                "order" => 0,
                "url" => [
                    "controller" => "Members",
                    "action" => "view",
                    $user->id,
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Members", $user->sca_name],
                "label" => "My Auth Card",
                "order" => 0,
                "url" => [
                    "controller" => "Members",
                    "action" => "viewCard",
                    $user->id,
                ],
                "icon" => "bi-person-vcard",
                "linkOptions" => [
                    "target" => "_blank",
                ],
            ],
            [
                "type" => "link",
                "mergePath" => ["Members"],
                "label" => "Members",
                "order" => 10,
                "url" => [
                    "controller" => "Members",
                    "action" => "index",
                ],
                "icon" => "bi-people",
            ],
            [
                "type" => "link",
                "mergePath" => ["Members", "Members"],
                "label" => "New Member",
                "order" => 0,
                "url" => [
                    "controller" => "Members",
                    "action" => "add",
                ],
                "icon" => "bi-person-plus",
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
            ],
            [
                "type" => "link",
                "mergePath" => ["Members", "Members"],
                "label" => "Verification Queues",
                "order" => 20,
                "url" => [
                    "controller" =>
                    "Members",
                    "action" => "verifyQueue",
                ],
                "icon" => "bi-fingerprint",
                "badgeClass" => "bg-danger",
                "badgeValue" => $event->getData('validationQueueCount'),
            ],
            [
                "type" => "link",
                "mergePath" => ["Members", "Members"],
                "label" => "Import Exp. Dates",
                "order" => 30,
                "url" => [
                    "controller" => "Members",
                    "action" =>
                    "importExpirationDates",
                ],
                "icon" => "bi-filetype-csv",
            ],
            [
                "type" => "link",
                "mergePath" => ["Reports"],
                "label" => "Activity Warrant Roster",
                "order" => 0,
                "url" => [
                    "controller" => "Reports",
                    "action" => "ActivityWarrantsRoster",
                ],
                "icon" => "bi-person-check-fill",
            ],
            [
                "type" => "link",
                "mergePath" => ["Reports"],
                "label" => "Activity Authorizations",
                "order" => 10,
                "url" => [
                    "controller" => "Reports",
                    "action" => "Authorizations",
                ],
                "icon" => "bi-person-lines-fill",
            ],
            [
                "type" => "link",
                "mergePath" => ["Reports"],
                "label" => "Dept. Officer Roster",
                "order" => 20,
                "url" => [
                    "controller" => "Reports",
                    "action" => "DepartmentOfficersRoster",
                ],
                "icon" => "bi-building-fill-check",
            ],
            [
                "type" => "link",
                "mergePath" => ["Reports"],
                "label" => "Role Assignments",
                "order" => 30,
                "url" => [
                    "controller" => "Reports",
                    "action" => "rolesList",
                ],
                "icon" => "bi-ui-checks",
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "App Settings",
                "order" => 0,
                "url" => [
                    "controller" => "AppSettings",
                    "action" => "index",
                ],
                "icon" => "bi-card-list",
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Branches",
                "order" => 10,
                "url" => [
                    "controller" => "Branches",
                    "action" => "index",
                ],
                "icon" => "bi-diagram-3",
            ],

            [
                "type" => "link",
                "mergePath" => ["Config", "Branches"],
                "label" => "New Branch",
                "order" => 0,
                "url" => [
                    "controller" => "Branches",
                    "action" => "add",
                ],
                "icon" => "bi-plus",
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
            ], [
                "type" => "link",
                "mergePath" => ["Config", "ActivityGroups"],
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
                "mergePath" => ["Config"],
                "label" => "Departments",
                "order" => 40,
                "url" => [
                    "controller" => "Departments",
                    "action" => "index",
                ],
                "icon" => "bi-building",
            ],
            [
                "type" => "link",
                "mergePath" => ["Config", "Departments"],
                "label" => "New Departments",
                "order" => 0,
                "url" => [
                    "controller" => "Departments",
                    "action" => "add",
                ],
                "icon" => "bi-plus",
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Offices",
                "order" => 50,
                "url" => [
                    "controller" => "Offices",
                    "action" => "index",
                ],
                "icon" => "bi-person-gear",
            ],
            [
                "type" => "link",
                "mergePath" => ["Config", "Offices"],
                "label" => "New Office",
                "order" => 0,
                "url" => [
                    "controller" => "Offices",
                    "action" => "add",
                ],
                "icon" => "bi-plus",
            ],
            [
                "type" => "link",
                "mergePath" => ["Security"],
                "label" => "Roles",
                "order" => 0,
                "url" => [
                    "controller" => "Roles",
                    "action" => "index",
                ],
                "icon" => "bi-universal-access-circle",
            ],
            [
                "type" => "link",
                "mergePath" => ["Security", "Roles"],
                "label" => "New Role",
                "order" => 0,
                "url" => [
                    "controller" => "Roles",
                    "action" => "add",
                ],
                "icon" => "bi-plus",
            ],
            [
                "type" => "link",
                "mergePath" => ["Security"],
                "label" => "Permissions",
                "order" => 10,
                "url" => [
                    "controller" => "Permissions",
                    "action" => "index",
                ],
                "icon" => "bi-clipboard-check",
            ],
            [
                "type" => "link",
                "mergePath" => ["Security", "Permissions"],
                "label" => "New Permission",
                "order" => 0,
                "url" => [
                    "controller" => "Permissions",
                    "action" => "add",
                ],
                "icon" => "bi-plus",
            ]
        ];

        $results = array_merge($results, $appNav);
        return $results;
    }
}