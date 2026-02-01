<?php

declare(strict_types=1);

namespace App\Services;

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
            'color' => 'success',
            'badge' => null,
            'validRoutes' => [], // Show on all mobile pages
            'authCallback' => function ($urlParams, $user) {
                // Check if user can view gatherings (index permission)
                if ($user === null) {
                    return false;
                }
                try {
                    $gatheringsTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Gatherings');
                    $gathering = $gatheringsTable->newEmptyEntity();
                    return $user->checkCan('index', $gathering);
                } catch (\Exception $e) {
                    return true; // Default to showing if permission check fails
                }
            }
        ];

        // My RSVPs - Mobile menu item
        $cells[] = [
            'type' => ViewCellRegistry::PLUGIN_TYPE_MOBILE_MENU,
            'label' => 'My RSVPs',
            'icon' => 'bi-calendar-check',
            'url' => ['controller' => 'GatheringAttendances', 'action' => 'myRsvps', 'plugin' => null],
            'order' => 36,
            'color' => 'primary',
            'badge' => null,
            'validRoutes' => [], // Show on all mobile pages
            'authCallback' => fn($urlParams, $user) => $user !== null
        ];

        return $cells;
    }
}
