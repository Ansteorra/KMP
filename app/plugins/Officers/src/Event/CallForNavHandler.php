<?php

namespace Officers\Event;

use App\KMP\StaticHelpers;
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
        if (StaticHelpers::pluginEnabled('Officers') == false) {
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
                "mergePath" => ["Reports"],
                "label" => "Officers",
                "order" => 29,
                "url" => [
                    "plugin" => "Officers",
                    "controller" => "Officers",
                    "action" => "index",
                    "model" => "Officers.Officers",
                ],
                "icon" => "bi-building",
                "activePaths" => [
                    "officers/Officers/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Departments",
                "order" => 40,
                "url" => [
                    "plugin" => "Officers",
                    "controller" => "Departments",
                    "action" => "index",
                    "model" => "Officers.Departments",
                ],
                "icon" => "bi-building",
                "activePaths" => [
                    "officers/departments/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config", "Departments"],
                "label" => "New Departments",
                "order" => 0,
                "url" => [
                    "plugin" => "Officers",
                    "controller" => "Departments",
                    "action" => "add",
                    "model" => "Officers.Departments",
                ],
                "icon" => "bi-plus",
            ],
            [
                "type" => "link",
                "mergePath" => ["Config"],
                "label" => "Offices",
                "order" => 50,
                "url" => [
                    "plugin" => "Officers",
                    "controller" => "Offices",
                    "action" => "index",
                    "model" => "Officers.Offices",
                ],
                "icon" => "bi-person-gear",
                "activePaths" => [
                    "officers/offices/view/*",
                ]
            ],
            [
                "type" => "link",
                "mergePath" => ["Config", "Offices"],
                "label" => "New Office",
                "order" => 0,
                "url" => [
                    "plugin" => "Officers",
                    "controller" => "Offices",
                    "action" => "add",
                    "model" => "Officers.Offices",
                ],
                "icon" => "bi-plus",
            ],
            [
                "type" => "link",
                "mergePath" => ["Reports"],
                "label" => "Dept. Officer Roster",
                "order" => 20,
                "url" => [
                    "controller" => "Reports",
                    "action" => "DepartmentOfficersRoster",
                    "plugin" => "Officers",
                ],
                "icon" => "bi-building-fill-check",
            ],
            [
                "type" => "link",
                "mergePath" => ["Security", "Rosters"],
                "label" => "New Officer Roster",
                "order" => 20,
                "url" => [
                    "controller" => "Rosters",
                    "action" => "add",
                    "plugin" => "Officers",
                ],
                "icon" => "bi-plus",
            ],
        ];




        $results = array_merge($results, $appNav);
        return $results;
    }
}