<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/** Server-owned allowlist of read-only grids suitable for scheduled summaries. */
final class GridSubscriptionRegistry
{
    /**
     * @var array<string, array{label: string, route: array, page: array}>
     */
    private static array $grids = [
        'Workflows.approvals.main' => [
            'label' => 'My Approvals',
            'route' => ['plugin' => null, 'controller' => 'Approvals', 'action' => 'approvalsGridData'],
            'page' => ['plugin' => null, 'controller' => 'Approvals', 'action' => 'approvals'],
        ],
        'Core.actionItems.myTasks' => [
            'label' => 'My To-Dos',
            'route' => ['plugin' => null, 'controller' => 'ActionItems', 'action' => 'myTasksGridData'],
            'page' => ['plugin' => null, 'controller' => 'ActionItems', 'action' => 'myTasks'],
        ],
        'WarrantRosters.index.main' => [
            'label' => 'Warrant Rosters',
            'route' => ['plugin' => null, 'controller' => 'WarrantRosters', 'action' => 'gridData'],
            'page' => ['plugin' => null, 'controller' => 'WarrantRosters', 'action' => 'index'],
        ],
    ];

    /** Register a plugin-owned grid after reviewing its authorization and cell renderers. */
    public static function register(string $key, string $label, array $route, array $page): void
    {
        self::$grids[$key] = compact('label', 'route', 'page');
    }

    /** Whether the grid supports subscriptions. */
    public static function supports(string $key): bool
    {
        return isset(self::$grids[$key]);
    }

    /** Return only server-configured routes, never a client-provided URL or controller. */
    public static function get(string $key): array
    {
        return self::$grids[$key] ?? throw new InvalidArgumentException('This grid does not support subscriptions.');
    }
}
