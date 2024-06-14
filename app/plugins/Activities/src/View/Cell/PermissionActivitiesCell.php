<?php

declare(strict_types=1);

namespace Activities\View\Cell;

use Cake\View\Cell;

/**
 * PermissionActivities cell
 */
class PermissionActivitiesCell extends Cell
{
    static public function getViewConfigForRoute($route)
    {
        if (isset($route['plugin'])) {
            return null;
        }
        if ($route['controller'] != 'Permissions') {
            return null;
        }
        if ($route['action'] != 'view') {
            return null;
        }
        return [
            'type' => 'tab', // 'tab' or 'detail' or 'modal'
            'label' => 'Activities',
            'id' => 'permission-activities',
            'order' => 2,
            'tabBtnBadge' => null,
            'cell' => 'Activities.PermissionActivities'
        ];
    }
    /**
     * List of valid options that can be passed into this
     * cell's constructor.
     *
     * @var array<string, mixed>
     */
    protected array $_validCellOptions = [];

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
    public function display($permissionId)
    {
        $activities = $this->fetchTable("Activities.Activities")->find('all')
            ->contain(['ActivityGroups'])
            ->where(['permission_id' => $permissionId])
            ->toArray();
        $this->set(compact('activities'));
    }
}