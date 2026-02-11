<?php

declare(strict_types=1);

namespace Officers\Services;

use App\Model\Entity\Member;
use App\KMP\StaticHelpers;

/**
 * Navigation provider for the Officers plugin.
 * 
 * Generates navigation menu items for officer management, department/office
 * configuration, reporting, and roster operations when the plugin is enabled.
 * 
 * @package Officers\Services
 * @see /docs/5.1-officers-plugin.md for plugin documentation
 */
class OfficersNavigationProvider
{
    /**
     * Builds navigation items for the Officers plugin.
     *
     * Generates the structured navigation definitions for officer management, configuration,
     * reporting, and roster actions. Items are produced only when the Officers plugin is enabled.
     *
     * @param Member $user The current authenticated user (context for visibility/permissions).
     * @param array $params Optional request or context parameters to customize generation.
     * @return array An array of navigation item definitions. Each item includes keys such as
     *               `type`, `mergePath`, `label`, `order`, `url` (plugin/controller/action/model),
     *               `icon`, and optionally `activePaths`.
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        if (StaticHelpers::pluginEnabled('Officers') == false) {
            return [];
        }

        return [
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