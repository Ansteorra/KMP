<?php

declare(strict_types=1);

namespace Awards\View\Cell;

use Cake\View\Cell;
use Cake\ORM\TableRegistry;
use App\View\Cell\BasePluginCell;
use Cake\Log\Log;
use Cake\ORM\Table;

/**
 * RecsForMember cell
 */
class RecsForMemberCell extends BasePluginCell
{
    static protected array $validRoutes = [
        ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
    ];
    static protected array $pluginData = [
        'type' => BasePluginCell::PLUGIN_TYPE_TAB,
        'label' => 'Received Award Recs.',
        'id' => 'recs-for-member',
        'order' => 4,
        'tabBtnBadge' => null,
        'cell' => 'Awards.RecsForMember'
    ];
    public static function getViewConfigForRoute($route, $currentUser)
    {
        if ($currentUser == null) {
            return null;
        }
        $pluginData = parent::getRouteEventResponse($route, self::$pluginData, self::$validRoutes);
        if ($pluginData != null && $currentUser != null && ($currentUser->checkCan('view', 'Awards.Recommendations'))) {
            return $pluginData;
        }
        return null;
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
    public function initialize(): void {}

    /**
     * Default display method.
     *
     * @return void
     */
    public function display($id)
    {
        $recommendationsTbl = TableRegistry::getTableLocator()->get("Awards.Recommendations");
        $isEmpty = $recommendationsTbl->find('all')->where(['member_id' => $id])->count() === 0;
        $this->set(compact('isEmpty', 'id'));
    }
}
