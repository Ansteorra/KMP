<?php

declare(strict_types=1);

namespace Officers\View\Cell;

use Cake\View\Cell;
use App\View\Cell\BasePluginCell;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use Officers\Model\Entity\Officer;

/**
 * BranchOfficers cell
 */
class BranchOfficersCell extends BasePluginCell
{
    static protected array $validRoutes = [
        ['controller' => 'Branches', 'action' => 'view', 'plugin' => null],
    ];
    static protected array $pluginData = [
        'type' => BasePluginCell::PLUGIN_TYPE_TAB,
        'label' => 'Officers',
        'id' => 'branch-officers',
        'order' => 1,
        'tabBtnBadge' => null,
        'cell' => 'Officers.BranchOfficers'
    ];
    public static function getViewConfigForRoute($route, $currentUser)
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
    public function initialize(): void {}

    /**
     * Default display method.
     *
     * @return void
     */
    public function display($id)
    {
        $offiersTable = TableRegistry::getTableLocator()->get("Officers.Officers");
        $currentOfficers = $this->addConditions($offiersTable->find('current')->where(['Officers.branch_id' => $id]))->toArray();
        $upcomingOfficers = $this->addConditions($offiersTable->find('upcoming')->where(['Officers.branch_id' => $id]))->toArray();
        $previousOfficers = $this->addConditions($offiersTable->find('previous')->where(['Officers.branch_id' => $id]))->toArray();
        $newOfficer = $offiersTable->newEmptyEntity();
        $branch = $this->getTableLocator()->get("Branches")
            ->find()->cache("branch_" . $id . "_id_and_parent")->select(['id', 'parent_id'])
            ->where(['id' => $id])->first();
        $officesTbl = TableRegistry::getTableLocator()->get("Officers.Offices");;
        $officeQuery = $officesTbl->find("all")
            ->contain(["Deputies" => function ($q) {
                return $q
                    ->select(["id", "name", "deputy_to_id"]);
            }])
            ->select(["id", "name", "deputy_to_id"])
            ->orderBY(["name" => "ASC"]);
        if ($branch->parent_id != null) {
            $officeQuery = $officeQuery->where(['kingdom_only' => false]);
        }
        $offices = $officeQuery->toArray();
        $this->set(compact('currentOfficers', 'upcomingOfficers', 'previousOfficers', 'newOfficer', 'offices', 'id'));
    }

    protected function addConditions($q)
    {

        $rejectFragment = $q->func()->concat([
            'Released by ',
            "RevokedBy.sca_name" => 'identifier',
            " on ",
            "expires_on" => 'identifier',
            " note: ",
            "revoked_reason" => 'identifier'
        ]);

        $revokeReasonCase = $q->newExpr()
            ->case()
            ->when(['Officers.status' => Officer::RELEASED_STATUS])
            ->then($rejectFragment)
            ->when(['Officers.status' => Officer::REPLACED_STATUS])
            ->then("New Officer Took Over.")
            ->when(['Officers.status' => Officer::EXPIRED_STATUS])
            ->then("Officer Term Expired.")
            ->else($rejectFragment);

        return $q
            ->select([
                "id",
                "member_id",
                "office_id",
                "branch_id",
                "start_on",
                "expires_on",
                'revoked_reason' => $revokeReasonCase,
                "ReportsToBranches.name",
                "ReportsToOffices.name",
                "deputy_description",
                "status",
            ])
            ->contain([
                "Members" => function ($q) {
                    return $q
                        ->select(["id", "sca_name"])
                        ->order(["sca_name" => "ASC"]);
                },
                "Offices" => function ($q) {
                    return $q
                        ->select(["id", "name"]);
                },
                "ReportsToBranches" => function ($q) {
                    return $q
                        ->select(["id", "name"]);
                },
                "ReportsToOffices" => function ($q) {
                    return $q
                        ->select(["id", "name"]);
                },
                "RevokedBy" => function ($q) {
                    return $q
                        ->select(["id", "sca_name"]);
                },
            ])
            ->order(["start_on" => "DESC", "Offices.name" => "ASC"]);
    }
}