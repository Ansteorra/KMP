<?php

declare(strict_types=1);

namespace Officers\View\Cell;

use Cake\View\Cell;
use App\View\Cell\BasePluginCell;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;
use Officers\Model\Entity\Officer;

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
        'label' => 'Offices',
        'id' => 'member-officers',
        'order' => 2,
        'tabBtnBadge' => null,
        'cell' => 'Officers.MemberOfficers'
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
        $currentOfficers = $this->addConditions($offiersTable->find('current')->where(['Officers.member_id' => $id]), "current")->toArray();
        $upcomingOfficers = $this->addConditions($offiersTable->find('upcoming')->where(['Officers.member_id' => $id]), "upcoming")->toArray();
        $previousOfficers = $this->addConditions($offiersTable->find('previous')->where(['Officers.member_id' => $id]), "previous")->toArray();
        $this->set(compact('currentOfficers', 'upcomingOfficers', 'previousOfficers', 'id'));
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
            ->when(['DeputyToOffices.id IS NOT NULL'])
            ->then($q->func()->concat([
                "DeputyToOffices.name" => 'identifier',
                " (",
                "Officers.deputy_description" => 'identifier',
                ")"
            ]))
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
                "Not Filed - ",
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
            "Branches" => function ($q) {
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