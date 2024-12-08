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
                "id" => "navheader_members",
                "order" => 0,
            ],
            [
                "type" => "parent",
                "label" => "Reports",
                "icon" => "bi-backpack4",
                "id" => "navheader_reports",
                "order" => 10,
            ],
            [
                "type" => "parent",
                "label" => "Config",
                "icon" => "bi-database-gear",
                "id" => "navheader_config",
                "order" => 20,
            ],
            [
                "type" => "parent",
                "label" => "Warrants",
                "icon" => "bi-shield-lock",
                "id" => "navheader_warrants",
                "order" => 30,
            ],
            [
                "type" => "parent",
                "label" => "Security",
                "icon" => "bi-house-lock",
                "id" => "navheader_security",
                "order" => 99999,
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
                    '?' => [
                        'nostack' => '1',
                    ],
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
                "activePaths" => [
                    "Members/view/*",
                ]
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
                "label" => "Verification Queues",
                "order" => 20,
                "url" => [
                    "controller" => "Members",
                    "action" => "verifyQueue",
                ],
                "icon" => "bi-fingerprint",
                "badgeClass" => "bg-danger",
                "badgeValue" =>   [
                    "class" => "App\Model\Table\MembersTable",
                    "method" => "getValidationQueueCount",
                    "argument" => 0
                ],
            ],
            [
                "type" => "link",
                "mergePath" => ["Members", "Members"],
                "label" => "Import Exp. Dates",
                "order" => 30,
                "url" => [
                    "controller" => "Members",
                    "action" => "importExpirationDates",
                ],
                "icon" => "bi-filetype-csv",
            ],
            [
                "type" => "link",
                "mergePath" => ["Reports"],
                "label" => "Permissions Warrant Roster",
                "order" => 0,
                "url" => [
                    "controller" => "Reports",
                    "action" => "PermissionsWarrantsRoster",
                ],
                "icon" => "bi-person-check-fill",
            ],
            [
                "type" => "link",
                "mergePath" => ["Reports"],
                "label" => "Role Assignments",
                "order" => 1,
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
                "activePaths" => [
                    "Branches/view/*",
                ]
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
                "mergePath" => ["Warrants"],
                "label" => "Roster",
                "order" => 0,
                "url" => [
                    "controller" => "Warrants",
                    "action" => "index",
                ],
                "icon" => "bi-people",
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
                "activePaths" => [
                    "Roles/view/*",
                ]
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
                "activePaths" => [
                    "Permissions/view/*",
                ]
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