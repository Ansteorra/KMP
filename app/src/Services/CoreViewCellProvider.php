<?php
declare(strict_types=1);

namespace App\Services;

use App\Model\Table\ActionItemsTable;
use App\Model\Table\WorkflowApprovalsTable;
use Cake\ORM\TableRegistry;
use Exception;

/**
 * Core View Cell Provider
 *
 * Provides view cells for core app features (non-plugin functionality).
 * Registers mobile menu items for Calendar, My RSVPs, and other core features.
 */
class CoreViewCellProvider
{
    /**
     * Get view cells for core features
     *
     * @param array $urlParams URL parameters from the current request
     * @param \App\Model\Entity\Member|null $user The current authenticated user
     * @return array Array of view cell configurations
     */
    public static function getViewCells(array $urlParams, $user = null): array
    {
        $cells = [];

        // Only show mobile menu items to authenticated users
        if ($user === null) {
            return $cells;
        }

        // Calendar - Mobile menu item
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
            'label' => 'Calendar',
            'icon' => 'bi-calendar-event',
            'url' => ['controller' => 'Gatherings', 'action' => 'mobileCalendar', 'plugin' => null],
            'order' => 35,
            'color' => 'events', // Section-specific color
            'badge' => null,
            'validRoutes' => [], // Show on all mobile pages
            'authCallback' => function ($urlParams, $user) {
                // Check if user can view gatherings (index permission)
                if ($user === null) {
                    return false;
                }
                try {
                    $gatheringsTable = TableRegistry::getTableLocator()->get('Gatherings');
                    $gathering = $gatheringsTable->newEmptyEntity();

                    return $user->checkCan('index', $gathering);
                } catch (Exception $e) {
                    return true; // Default to showing if permission check fails
                }
            },
        ];

        // My RSVPs - Mobile menu item
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
            'label' => 'My RSVPs',
            'icon' => 'bi-calendar-check',
            'url' => ['controller' => 'GatheringAttendances', 'action' => 'myRsvps', 'plugin' => null],
            'order' => 36,
            'color' => 'rsvps', // Section-specific color
            'badge' => null,
            'validRoutes' => [], // Show on all mobile pages
            'authCallback' => fn($urlParams, $user) => $user !== null,
        ];

        $approvalCount = self::countPendingApprovals((int)$user->id);
        if ($approvalCount > 0) {
            // Approvals - Mobile menu item
            $cells[] = [
                'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
                'label' => 'My Approvals',
                'icon' => 'bi-check2-square',
                'url' => ['controller' => 'Approvals', 'action' => 'mobileApprovals', 'plugin' => null],
                'order' => 32,
                'color' => 'approvals',
                'badge' => $approvalCount,
                'validRoutes' => [],
                'authCallback' => function ($urlParams, $user) {
                    return $user !== null;
                },
            ];
        }

        $todoCount = self::countOpenTodos((int)$user->id);
        if ($todoCount > 0) {
            // My To-Dos - Mobile menu item
            $cells[] = [
                'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
                'label' => 'My To-Dos',
                'icon' => 'bi-check2-all',
                'url' => ['controller' => 'ActionItems', 'action' => 'mobileMyTasks', 'plugin' => null],
                'order' => 33,
                'color' => 'todos',
                'badge' => $todoCount,
                'validRoutes' => [],
                'authCallback' => function ($urlParams, $user) {
                    return $user !== null;
                },
            ];
        }

        return $cells;
    }

    /**
     * @param int $memberId Current member ID
     * @return int Pending approval count
     */
    private static function countPendingApprovals(int $memberId): int
    {
        try {
            return WorkflowApprovalsTable::getPendingApprovalCountForMember($memberId);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @param int $memberId Current member ID
     * @return int Open to-do count
     */
    private static function countOpenTodos(int $memberId): int
    {
        try {
            return ActionItemsTable::getOpenTaskCountForMember($memberId);
        } catch (Exception $e) {
            return 0;
        }
    }
}
