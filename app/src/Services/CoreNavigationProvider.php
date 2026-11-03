<?php
declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Member;

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
     * @param \App\Model\Entity\Member $user Current user
     * @param array $params Request parameters
     * @return array Navigation items
     */
    public static function getNavigationItems(Member $user, array $params = []): array
    {
        return [
            [
                'type' => 'parent',
                'label' => 'Action Items',
                'icon' => 'users',
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
                    '?' => ['src' => 'action_items'],
                ],
                'badgeClass' => 'bg-danger',
                'badgeValue' => [
                    'class' => "App\Model\Table\WarrantRostersTable",
                    'method' => 'getPendingRosterCount',
                    'argument' => 0,
                ],
                'icon' => 'users',
            ],
            [
                'type' => 'link',
                'mergePath' => ['Action Items'],
                'label' => 'Pending Verifications',
                'order' => 20,
                'url' => [
                    'controller' => 'Members',
                    'action' => 'verifyQueue',
                    '?' => ['src' => 'action_items'],
                ],
                'icon' => 'fingerprint',
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
                'icon' => 'users',
                'id' => 'navheader_members',
                'order' => 10,
            ],
            [
                'type' => 'parent',
                'label' => 'Reports',
                'icon' => 'backpack',
                'id' => 'navheader_reports',
                'order' => 20,
            ],
            [
                'type' => 'parent',
                'label' => 'Gatherings',
                'icon' => 'calendar-event',
                'id' => 'navheader_gatherings',
                'order' => 25,
            ],
            [
                'type' => 'parent',
                'label' => 'Config',
                'icon' => 'database-cog',
                'id' => 'navheader_config',
                'order' => 30,
            ],
            [
                'type' => 'parent',
                'label' => 'Security',
                'icon' => 'home-shield',
                'id' => 'navheader_security',
                'order' => 99999,
            ],
            [
                'type' => 'link',
                'mergePath' => ['Members'],
                'label' => "$user->sca_name",
                'icon' => 'user',
                'order' => 0,
                'url' => [
                    'controller' => 'Members',
                    'action' => 'profile',
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
                'icon' => 'address-book',
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
                'icon' => 'users',
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
                'icon' => 'sitemap',
                'activePaths' => [
                    'Branches/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Members', 'Members'],
                'label' => 'Member Data Import',
                'order' => 50,
                'url' => [
                    'controller' => 'Members',
                    'action' => 'importExpirationDates',
                ],
                'icon' => 'file-type-csv',
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
                'icon' => 'user-check',
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
                'icon' => 'list-check',
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
                'icon' => 'calendar-event',
                'activePaths' => [
                    'Gatherings/view/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Config'],
                'label' => 'All Approvals',
                'order' => 5,
                'url' => [
                    'controller' => 'Approvals',
                    'action' => 'allApprovals',
                ],
                'icon' => 'clipboard-check',
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
                'icon' => 'list-details',
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
                'icon' => 'archive',
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
                'icon' => 'table',
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
                'icon' => 'calendar-week',
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
                'icon' => 'layers-linked',
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
                'icon' => 'list-details',
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
                'icon' => 'mail',
                'activePaths' => [
                    'EmailTemplates/view/*',
                    'EmailTemplates/add',
                    'EmailTemplates/edit/*',
                ],
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
                'icon' => 'id-badge',
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
                'icon' => 'users',
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
                'icon' => 'accessible',
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
                'icon' => 'clipboard-check',
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
                'icon' => 'key',
                'activePaths' => [
                    'ServicePrincipals/view/*',
                    'ServicePrincipals/add',
                    'ServicePrincipals/edit/*',
                ],
            ],
            [
                'type' => 'parent',
                'label' => 'Workflows',
                'icon' => 'sitemap',
                'id' => 'navheader_workflows',
                'order' => 28,
            ],
            [
                'type' => 'link',
                'mergePath' => ['Workflows'],
                'label' => 'Definitions',
                'order' => 0,
                'url' => [
                    'controller' => 'WorkflowDefinitions',
                    'action' => 'index',
                ],
                'icon' => 'list-details',
                'activePaths' => [
                    'WorkflowDefinitions/designer/*',
                    'WorkflowDefinitions/versions/*',
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Action Items'],
                'label' => 'My Approvals',
                'order' => 10,
                'url' => [
                    'controller' => 'Approvals',
                    'action' => 'approvals',
                ],
                'skipAuthorization' => true,
                'icon' => 'checkbox',
                'badgeClass' => 'bg-danger',
                'badgeValue' => [
                    'class' => "App\Model\Table\WorkflowApprovalsTable",
                    'method' => 'getPendingApprovalCountForMember',
                    'argument' => $user->id,
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Action Items'],
                'label' => 'My To-Dos',
                'order' => 11,
                'url' => [
                    'controller' => 'ActionItems',
                    'action' => 'myTasks',
                ],
                'skipAuthorization' => true,
                'icon' => 'checks',
                'badgeClass' => 'bg-danger',
                'badgeValue' => [
                    'class' => "App\Model\Table\ActionItemsTable",
                    'method' => 'getOpenTaskCountForMember',
                    'argument' => $user->id,
                ],
            ],
            [
                'type' => 'link',
                'mergePath' => ['Workflows'],
                'label' => 'Instances',
                'order' => 20,
                'url' => [
                    'controller' => 'WorkflowInstances',
                    'action' => 'instances',
                ],
                'icon' => 'player-play',
                'activePaths' => [
                    'WorkflowInstances/viewInstance/*',
                ],
            ],
        ];
    }
}
