<?php

declare(strict_types=1);

namespace Officers\View\Cell;

use Cake\View\Cell;
use App\View\Cell\BasePluginCell;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;
use Officers\Model\Entity\Officer;
use Cake\Datasource\Paging\NumericPaginator;

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

        $officersTable = $this->fetchTable("Officers.Officers");

        $newOfficer = $officersTable->newEmptyEntity();

        $branch = $this->fetchTable("Branches")
            ->find()->select(['id', 'parent_id', 'type', 'domain'])
            ->where(['id' => $id])->first();
        $officesTbl = $this->fetchTable("Officers.Offices");;
        $officeQuery = $officesTbl->find("all")
            ->contain(["Departments"])
            ->select(["id", "Offices.name", "deputy_to_id", "reports_to_id", "applicable_branch_types", "default_contact_address"])
            ->orderBY(["Offices.name" => "ASC"]);
        $officeSet = $officeQuery->where(['applicable_branch_types like' => '%"' . $branch->type . '"%'])->toArray();
        $user = $this->request->getAttribute("identity");
        $hireAll = false;
        $canHireOffices = [];
        $myOffices = [];
        if ($user->checkCan("assign", "Officers.Officers", $id)) {
            $hireAll = true;
        } else {
            $officersTbl = $this->fetchTable("Officers.Officers");
            $userOffices = $officersTbl->find("current")->where(['member_id' => $user->id])->select(['id', 'office_id', 'branch_id'])->toArray();
            $canHireOffices = [];
            foreach ($userOffices as $userOffice) {
                $myOffices[] = $userOffice->office_id;
                if ($user->checkCan("assignMyDeputies", $userOffice, $id, true)) {
                    $deputies = $officesTbl->find('all')->where(['deputy_to_id' => $userOffice->office_id])->select(['id'])->toArray();
                    // add deputies to the list of offices that can be hired
                    foreach ($deputies as $deputy) {
                        if (!in_array($deputy->id, $canHireOffices)) {
                            $canHireOffices[] = $deputy->id;
                        }
                    }
                }
                if ($user->checkCan("assignMyDirectReports", $userOffice, $id, true)) {
                    $deputies = $officesTbl->find('all')->where(['OR' => ['deputy_to_id' => $userOffice->office_id, 'reports_to_id' => $userOffice->office_id]])->select(['id'])->toArray();
                    // add deputies to the list of offices that can be hired
                    foreach ($deputies as $deputy) {
                        if (!in_array($deputy->id, $canHireOffices)) {
                            $canHireOffices[] = $deputy->id;
                        }
                    }
                }
                if ($user->checkCan("assignMyReportTree", $userOffice, $id, true)) {
                    $addedOffices = 0;
                    $hireThread = [];
                    //Get all of the top level office deputies and reports
                    $reports = $officesTbl->find('all')->where(['OR' => ['deputy_to_id' => $userOffice->office_id, 'reports_to_id' => $userOffice->office_id]])->select(['id', 'reports_to_id'])->toArray();
                    foreach ($reports as $report) {
                        if (!in_array($report->id, $canHireOffices)) {
                            $addedOffices++;
                            $hireThread[] = $report->id;
                        }
                    }
                    // if we added any then we are going to loop back to sql and grab more until we don't add anymore.
                    while ($addedOffices != 0) {
                        $addedOffices = 0;
                        $reports = $officesTbl->find('all')->where(['OR' => ['deputy_to_id in ' => $hireThread, 'reports_to_id in ' => $hireThread]])->select(['id', 'reports_to_id'])->toArray();
                        foreach ($reports as $report) {
                            if (!in_array($report->id, $hireThread)) {
                                $addedOffices++;
                                $hireThread[] = $report->id;
                            }
                        }
                    }
                    // now we can add them all to the collected list.
                    foreach ($hireThread as $office) {
                        if (!in_array($office, $canHireOffices)) {
                            $canHireOffices[] = $office;
                        }
                    }
                }
            }
        }
        $offices = $this->buildOfficeTree($officeSet, $branch,  $hireAll, $myOffices, $canHireOffices, null);
        $this->set(compact('id', 'offices', 'newOfficer'));
    }

    private function buildOfficeTree($offices, $branch, $hireAll, $myOffices, $canHireOffices, $office_id = null)
    {
        $tree = [];
        foreach ($offices as $office) {
            if ($office->deputy_to_id == $office_id || ($office_id == null && in_array($office->id, $myOffices))) {
                $newofficeEmail = "";
                if (isset($office->default_contact_address) && !empty($office->default_contact_address)) {
                    if (isset($branch->domain) && !empty($branch->domain)) {
                        $newofficeEmail = $office->default_contact_address . "@" . $branch->domain;
                    } else if (isset($office->department->domain) && !empty($office->department->domain)) {
                        $newofficeEmail = $office->default_contact_address . "@" . $office->department->domain;
                    } else {
                        $newofficeEmail = $office->default_contact_address . "@no_defaults_found.no_domain";
                    }
                }
                if ($hireAll) {
                    $canHire = true;
                } else {
                    $canHire = in_array($office->id, $canHireOffices);
                }
                if ($canHire) {
                    $newOffice = [
                        'id' => $office->id,
                        'name' => $office->name,
                        'deputy_to_id' => $office->deputy_to_id,
                        'deputies' => [],
                        'email_address' => $newofficeEmail,
                        'enabled' => strpos($office->applicable_branch_types, "\"$branch->type\"") !== false
                    ];
                    $newOffice['deputies'] = $this->buildOfficeTree($offices, $branch, $hireAll, $myOffices, $canHireOffices,  $office->id,);
                    $tree[] = $newOffice;
                } elseif (in_array($office->id, $myOffices)) {
                    $tempDeputies = $this->buildOfficeTree($offices, $branch, $hireAll, $myOffices, $canHireOffices,  $office->id);
                    foreach ($tempDeputies as $tempDeputy) {
                        $tree[] = $tempDeputy;
                    }
                }
            }
        }
        //order the tree by name
        usort($tree, function ($a, $b) {
            return $a['name'] <=> $b['name'];
        });
        return $tree;
    }
}