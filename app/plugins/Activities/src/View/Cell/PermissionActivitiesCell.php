<?php

declare(strict_types=1);

namespace Activities\View\Cell;

use Cake\View\Cell;
use App\View\Cell\BasePluginCell;

/**
 * PermissionActivities cell
 */
class PermissionActivitiesCell extends BasePluginCell
{
    static protected array $validRoutes = [
        ['controller' => 'Permissions', 'action' => 'view', 'plugin' => null],
    ];
    static protected array $pluginData = [
        'type' => 'tab', // 'tab' or 'detail' or 'modal'
        'label' => 'Activities',
        'id' => 'permission-activities',
        'order' => 2,
        'tabBtnBadge' => null,
        'cell' => 'Activities.PermissionActivities'
    ];
    public static function getViewConfigForRoute($route)
    {
        return parent::getRouteEventResponse($route, self::$pluginData, self::$validRoutes);
    }
    /**
     * List of valid options that can be passed into this
     * cell's constructor.
     *
     * @var array<string, mixed>
     */

    /**
     * Initialization logic run at the end of object construction.
     *
     * @return void
     */
    public function initialize(): void
    {
    }

    /**
     * Default display method.
     *
     * @return void
     */
    public function display($id)
    {
        $activities = $this->fetchTable("Activities.Activities")->find('all')
            ->contain(['ActivityGroups'])
            ->where(['permission_id' => $id])
            ->toArray();
        $this->set(compact('activities'));
    }
}