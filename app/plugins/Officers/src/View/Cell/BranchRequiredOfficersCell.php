<?php

declare(strict_types=1);

namespace Officers\View\Cell;

use Cake\View\Cell;
use App\View\Cell\BasePluginCell;

/**
 * BranchRequiredOfficers cell
 */
class BranchRequiredOfficersCell extends BasePluginCell
{
    static protected array $validRoutes = [
        ['controller' => 'Branches', 'action' => 'view', 'plugin' => null],
    ];
    static protected array $pluginData = [
        'type' => BasePluginCell::PLUGIN_TYPE_DETAIL,
        'label' => 'Officers',
        'id' => 'branch-required-officers',
        'order' => 1,
        'tabBtnBadge' => null,
        'cell' => 'Officers.BranchRequiredOfficers'
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
        $branch = $this->getTableLocator()->get("Branches")
            ->find()->cache("branch_" . $id . "_id_and_parent")->select(['id', 'parent_id', 'type'])
            ->where(['id' => $id])->first();
        $officesTbl = $this->getTableLocator()->get("Officers.Offices");
        $officesQuery = $officesTbl->find()
            ->contain(["CurrentOfficers" => function ($q) use ($id) {
                return $q
                    ->select(["id", "member_id", "office_id", "start_on", "expires_on", "Members.sca_name", "CurrentOfficers.email_address"])
                    ->contain(["Members"])
                    ->where(['CurrentOfficers.branch_id' => $id]);
            }])
            ->where(['required_office' => true]);
        $officesQuery = $officesQuery->where(['applicable_branch_types like' => '%"' . $branch->type . '"%']);
        $requiredOffices = $officesQuery->toArray();
        $this->set(compact('requiredOffices', 'id'));
    }
}