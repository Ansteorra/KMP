<?php

declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Member;
use App\KMP\StaticHelpers;

/**
 * Core Navigation Provider
 * 
 * Provides core application navigation items.
 * Replaces the functionality from App\Event\CallForNavHandler
 */
class CoreNavigationProvider
{
    /**
     * Get core navigation items
     *
     * @param Member $user Current user
     * @param array $params Request parameters
     * @return array Navigation items
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        return [
            [
                'type' => 'parent',
                'label' => 'Action Items',
                'icon' => 'bi-people',
                'id' => 'navheader_actionitems',
                'order' => 0,
            ],
            [
                'type' => 'link',
                'mergePath' => ['Action Items'],
                'label' => 'Pending Rosters',
                'order' => 1,
                'url' => [
                    'controller' => 'WarrantRosters',
                    'action' => 'index',
                    "?" => ['src' => 'action_items']
                ],
                'badgeClass' => 'bg-danger',
                'badgeValue' => [
                    'class' => "App\Model\Table\WarrantRostersTable",
                    'method' => 'getPendingRosterCount',
                    'argument' => 0,
                ],
                'icon' => 'bi-people',
            ],
            [
                'type' => 'link',
                'mergePath' => ['Action Items'],
                'label' => 'Pending Verifications',
                'order' => 20,
                'url' => [
                    'controller' => 'Members',
                    'action' => 'verifyQueue',
                    "?" => ['src' => 'action_items']
                ],
                'icon' => 'bi-fingerprint',
                'badgeClass' => 'bg-danger',
                'badgeValue' => [
                    'class' => "App\Model\Table\MembersTable",
                    'method' => 'getValidationQueueCount',
                    'argument' => 0,
                ],
            ],
            [
                'type' => 'parent',
                'label' => 'Members',
                'icon' => 'bi-people',
                'id' => 'navheader_members',
                'order' => 10,
            ],
            [
                'type' => 'parent',
                'label' => 'Reports',
                'icon' => 'bi-backpack4',
                'id' => 'navheader_reports',
                'order' => 20,
            ],
            [
                'type' => 'parent',
                'label' => 'Gatherings',
                'icon' => 'bi-calendar-event',
                'id' => 'navheader_gatherings',
                'order' => 25,
            ],
            [
                'type' => 'parent',
                'label' => 'Config',
                'icon' => 'bi-database-gear',
                'id' => 'navheader_config',
                'order' => 30,
            ],
            [
                'type' => 'parent',
                'label' => 'Security',
                'icon' => 'bi-house-lock',
                'id' => 'navheader_security',
                'order' => 99999,
            ],
            [
                'type' => 'link',
                'mergePath' => ['Members'],
                'label' => "$user->sca_name",
                'icon' => 'bi-person-fill',
                'order' => 0,
                'url' => [
                    'controller' => 'Members',
                    'action' => 'profile'
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Members', $user->sca_name],
                'label' => 'My Auth Card',
                'order' => 0,
                'url' => [
                    'controller' => 'Members',
                    'action' => 'viewCard',
                    $user->id,
                    '?' => [
                        'nostack' => '1',
                    ],
                ],
                'icon' => 'bi-person-vcard',
                'linkOptions' => [
                    'target' => '_blank',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Members'],
                'label' => 'Members',
                'order' => 10,
                'url' => [
                    'controller' => 'Members',
                    'action' => 'index',
                ],
                'icon' => 'bi-people',
                'activePaths' => [
                    'Members/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Members'],
                'label' => 'Branches',
                'order' => 20,
                'url' => [
                    'controller' => 'Branches',
                    'action' => 'index',
                ],
                'icon' => 'bi-diagram-3',
                'activePaths' => [
                    'Branches/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Members', 'Members'],
                'label' => 'Import Exp. Dates',
                'order' => 50,
                'url' => [
                    'controller' => 'Members',
                    'action' => 'importExpirationDates',
                ],
                'icon' => 'bi-filetype-csv',
            ],
            [
                'type' => 'link',
                'mergePath' => ['Reports'],
                'label' => 'Permissions Warrant Roster',
                'order' => 0,
                'url' => [
                    'controller' => 'Reports',
                    'action' => 'PermissionsWarrantsRoster',
                ],
                'icon' => 'bi-person-check-fill',
            ],
            [
                'type' => 'link',
                'mergePath' => ['Reports'],
                'label' => 'Role Assignments',
                'order' => 1,
                'url' => [
                    'controller' => 'Reports',
                    'action' => 'rolesList',
                ],
                'icon' => 'bi-ui-checks',
            ],
            [
                'type' => 'link',
                'mergePath' => ['Gatherings'],
                'label' => 'Calendar',
                'order' => 0,
                'url' => [
                    'controller' => 'Gatherings',
                    'action' => 'calendar',
                ],
                'icon' => 'bi-calendar-event',
                'activePaths' => [
                    'Gatherings/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'App Settings',
                'order' => 0,
                'url' => [
                    'controller' => 'AppSettings',
                    'action' => 'index',
                ],
                'icon' => 'bi-card-list',
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Backups',
                'order' => 10,
                'url' => [
                    'controller' => 'Backups',
                    'action' => 'index',
                ],
                'icon' => 'bi-archive',
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Table Admin',
                'order' => 15,
                'url' => [
                    'controller' => 'TableAdmin',
                    'action' => 'index',
                ],
                'icon' => 'bi-table',
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Warrant Periods',
                'order' => 60,
                'url' => [
                    'controller' => 'WarrantPeriods',
                    'action' => 'index',
                ],
                'icon' => 'bi-calendar-range',
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Gathering Types',
                'order' => 70,
                'url' => [
                    'controller' => 'GatheringTypes',
                    'action' => 'index',
                ],
                'icon' => 'bi-collection',
                'activePaths' => [
                    'GatheringTypes/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Gathering Activities',
                'order' => 80,
                'url' => [
                    'controller' => 'GatheringActivities',
                    'action' => 'index',
                ],
                'icon' => 'bi-list-task',
                'activePaths' => [
                    'GatheringActivities/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'Email Templates',
                'order' => 85,
                'url' => [
                    'controller' => 'EmailTemplates',
                    'action' => 'index',
                ],
                'icon' => 'bi-envelope',
                'activePaths' => [
                    'EmailTemplates/view/*',
                    'EmailTemplates/add',
                    'EmailTemplates/edit/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config', 'Email Templates'],
                'label' => 'Discover',
                'order' => 10,
                'url' => [
                    'controller' => 'EmailTemplates',
                    'action' => 'discover',
                ],
                'icon' => 'bi-search',
            ],
            [
                'type' => 'link',
                'mergePath' => ['Security'],
                'label' => 'Warrants',
                'order' => 0,
                'url' => [
                    'controller' => 'Warrants',
                    'action' => 'index',
                ],
                'icon' => 'bi-person-badge',
            ],
            [
                'type' => 'link',
                'mergePath' => ['Security'],
                'label' => 'Rosters',
                'order' => 0,
                'url' => [
                    'controller' => 'WarrantRosters',
                    'action' => 'index',
                ],
                'icon' => 'bi-people',
            ],
            [
                'type' => 'link',
                'mergePath' => ['Security'],
                'label' => 'Roles',
                'order' => 0,
                'url' => [
                    'controller' => 'Roles',
                    'action' => 'index',
                ],
                'icon' => 'bi-universal-access-circle',
                'activePaths' => [
                    'Roles/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Security'],
                'label' => 'Permissions',
                'order' => 10,
                'url' => [
                    'controller' => 'Permissions',
                    'action' => 'index',
                ],
                'icon' => 'bi-clipboard-check',
                'activePaths' => [
                    'Permissions/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Security'],
                'label' => 'API Clients',
                'order' => 20,
                'url' => [
                    'controller' => 'ServicePrincipals',
                    'action' => 'index',
                ],
                'icon' => 'bi-key',
                'activePaths' => [
                    'ServicePrincipals/view/*',
                    'ServicePrincipals/add',
                    'ServicePrincipals/edit/*',
                ],
            ],
        ];
    }
}
