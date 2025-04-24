<?php

declare(strict_types=1);

namespace Awards\View\Cell;

use Cake\View\Cell;
use Cake\ORM\TableRegistry;
use App\View\Cell\BasePluginCell;
use Cake\Log\Log;

/**
 * MemberSubmittedRecs cell
 */
class MemberSubmittedRecsCell extends BasePluginCell
{
    static protected array $validRoutes = [
        ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
    ];
    static protected array $pluginData = [
        'type' => BasePluginCell::PLUGIN_TYPE_TAB,
        'label' => 'Submitted Award Recs.',
        'id' => 'member-submitted-recs',
        'order' => 3,
        'tabBtnBadge' => null,
        'cell' => 'Awards.MemberSubmittedRecs'
    ];
    public static function getViewConfigForRoute($route, $currentUser)
    {
        if ($currentUser == null) {
            return null;
        }
        $pluginData = parent::getRouteEventResponse($route, self::$pluginData, self::$validRoutes);
        $memberId = null;
        if (isset($route["0"]) && isset($route["0"][0])) {
            $memberId = $route["0"][0];
        }
        if ($pluginData != null && $currentUser != null && ($currentUser->id == $memberId || $currentUser->checkCan('viewSubmittedByMember', 'Awards.Recommendations'))) {
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
        $currentUser = $this->request->getAttribute('identity');
        if ($currentUser->id != $id && !$currentUser->checkCan('view', 'Awards.Recommendations')) {
            return;
        }
        $recommendationsTbl = TableRegistry::getTableLocator()->get("Awards.Recommendations");
        $isEmpty = $recommendationsTbl->find('all')->where(['requester_id' => $id])->count() === 0;
        $this->set(compact('isEmpty', 'id'));
    }
}