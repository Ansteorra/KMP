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
            ->select(["id", "Offices.name", "deputy_to_id", "applicable_branch_types", "default_contact_address"])
            ->orderBY(["Offices.name" => "ASC"]);
        $officeSet = $officeQuery->where(['applicable_branch_types like' => '%"' . $branch->type . '"%'])->toArray();
        $offices = $this->buildOfficeTree($officeSet, $branch, null);
        $this->set(compact('id', 'offices', 'newOfficer'));
    }

    private function buildOfficeTree($offices, $branch, $office_id = null)
    {
        $tree = [];
        foreach ($offices as $office) {
            if ($office->deputy_to_id == $office_id) {
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
                $newOffice = [
                    'id' => $office->id,
                    'name' => $office->name,
                    'deputy_to_id' => $office->deputy_to_id,
                    'deputies' => [],
                    'email_address' => $newofficeEmail,
                    'enabled' => strpos($office->applicable_branch_types, "\"$branch->type\"") !== false
                ];
                $newOffice['deputies'] = $this->buildOfficeTree($offices, $branch, $office->id);
                $tree[] = $newOffice;
            }
        }
        //order the tree by name
        usort($tree, function ($a, $b) {
            return $a['name'] <=> $b['name'];
        });
        return $tree;
    }

    protected function addConditions($q, $type)
    {

        $rejectFragment = $q->func()->concat([
            'Released by ',
            "RevokedBy.sca_name" => 'identifier',
            " on ",
            "Officers.expires_on" => 'identifier',
            " note: ",
            "Officers.revoked_reason" => 'identifier'
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


        $reportsToCase = $q->newExpr()
            ->case()
            ->when(['ReportsToOffices.id IS NULL'])
            ->then("Society")
            ->when(['current_report_to.id IS NOT NULL'])
            ->then($q->func()->concat([
                "ReportsToOffices.name" => 'identifier',
                " : ",
                "current_report_to.sca_name" => 'identifier',
            ]))
            ->when(['ReportsToOffices.id IS NOT NULL'])
            ->then($q->func()->concat([
                "Not Filled - ",
                "ReportsToBranches.name" => 'identifier',
                " : ",
                "ReportsToOffices.name" => 'identifier'
            ]))
            ->else("None");

        $fields = [
            "id",
            "member_id",
            "office_id",
            "branch_id",
            "Officers.start_on",
            "Officers.expires_on",
            "Officers.deputy_description",
            "status",
        ];

        $contain = [
            "Members" => function ($q) {
                return $q
                    ->select(["id", "sca_name"])
                    ->order(["sca_name" => "ASC"]);
            },
            "Offices" => function ($q) {
                return $q
                    ->select(["id", "name"]);
            },

            "RevokedBy" => function ($q) {
                return $q
                    ->select(["id", "sca_name"]);
            },
        ];

        if ($type === 'current' || $type === 'upcoming') {
            $fields['reports_to'] = $reportsToCase;
            $fields[] = "ReportsToBranches.name";
            $fields[] = "ReportsToOffices.name";
            $contain["ReportsToBranches"] = function ($q) {
                return $q
                    ->select(["id", "name"]);
            };
            $contain["ReportsToOffices"] = function ($q) {
                return $q
                    ->select(["id", "name"]);
            };
            $contain["DeputyToOffices"] = function ($q) {
                return $q
                    ->select(["id", "name"]);
            };
        }

        if ($type === 'previous') {
            $fields['revoked_reason'] = $revokeReasonCase;
        }

        $query = $q
            ->select($fields);

        $query->contain($contain);
        if ($type === 'current' || $type === 'upcoming') {
            $query->join(
                [
                    'table' => 'officers_officers',
                    'alias' => 'current_report_to_officer',
                    'type' => 'LEFT',
                    'conditions' => [
                        'Officers.reports_to_office_id = current_report_to_officer.office_id',
                        'Officers.reports_to_branch_id = current_report_to_officer.branch_id',
                        'current_report_to_officer.start_on <=' => DateTime::now(),
                        'current_report_to_officer.expires_on >=' => DateTime::now(),
                        'current_report_to_officer.status' => Officer::CURRENT_STATUS
                    ]
                ]
            );
            $query->join(
                [
                    'table' => 'members',
                    'alias' => 'current_report_to',
                    'type' => 'LEFT',
                    'conditions' => [
                        'current_report_to_officer.member_id = current_report_to.id',
                    ]
                ]
            );
        }
        $query->order(["Officers.start_on" => "DESC", "Offices.name" => "ASC"]);

        return $query;
    }
}