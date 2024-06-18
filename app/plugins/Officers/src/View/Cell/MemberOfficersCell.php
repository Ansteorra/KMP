<?php

declare(strict_types=1);

namespace Officers\View\Cell;

use Cake\View\Cell;
use App\View\Cell\BasePluginCell;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;

/**
 * MemberOfficers cell
 */
class MemberOfficersCell extends BasePluginCell
{
    static protected array $validRoutes = [
        ['controller' => 'Members', 'action' => 'view', 'plugin' => null],
    ];
    static protected array $pluginData = [
        'type' => BasePluginCell::PLUGIN_TYPE_TAB,
        'label' => 'Officers',
        'id' => 'member-officers',
        'order' => 2,
        'tabBtnBadge' => null,
        'cell' => 'Officers.MemberOfficers'
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
    public function display($id)
    {
        $offiersTable = TableRegistry::getTableLocator()->get("Officers.Officers");
        $currentOfficers = $this->addConditions($offiersTable->find('current')->where(['Officers.member_id' => $id]))->toArray();
        $upcomingOfficers = $this->addConditions($offiersTable->find('upcoming')->where(['Officers.member_id' => $id]))->toArray();
        $previousOfficers = $this->addConditions($offiersTable->find('previous')->where(['Officers.member_id' => $id]))->toArray();
        $this->set(compact('currentOfficers', 'upcomingOfficers', 'previousOfficers', 'id'));
    }

    protected function addConditions(SelectQuery $q)
    {
        return $q
            ->select([
                "member_id",
                "office_id",
                "start_on",
                "expires_on",
                'branch_id',
            ])
            ->contain([
                "Offices" => function (SelectQuery $q) {
                    return $q->select(["Offices.name"]);
                },
                "Branches" => function (SelectQuery $q) {
                    return $q->select(["Branches.name"]);
                }
            ]);
    }
}