<?php

declare(strict_types=1);

namespace Officers\Services;

use App\Model\Entity\Member;
use App\KMP\StaticHelpers;

/**
 * Officers Navigation Provider
 * 
 * Provides Officers plugin navigation items.
 * Replaces the functionality from Officers\Event\CallForNavHandler
 */
class OfficersNavigationProvider
{
    /**
     * Get Officers plugin navigation items
     *
     * @param Member $user Current user
     * @param array $params Request parameters
     * @return array Navigation items
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        if (StaticHelpers::pluginEnabled('Officers') == false) {
            return [];
        }

        return [
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
    }
}
