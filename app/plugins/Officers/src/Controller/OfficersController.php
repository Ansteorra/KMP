<?php

declare(strict_types=1);

namespace Officers\Controller;

use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use Officers\Services\OfficerManagerInterface;
use Cake\I18n\DateTime;
use Officers\Model\Entity\Officer;

use Cake\I18n\Date;

/**
 * Offices Controller
 *
 * @property \App\Model\Table\OfficesTable $Offices
 */
class OfficersController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->authorizeModel("add");
        $this->Authentication->addUnauthenticatedActions(['api']);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add(OfficerManagerInterface $oManager)
    {
        $officer = $this->Officers->newEmptyEntity();
        $this->Authorization->authorize($officer);
        if ($this->request->is('post')) {
            //begin transaction
            $this->Officers->getConnection()->begin();
            $memberId = (int)$this->request->getData('member_id');
            $officeId = (int)$this->request->getData('office_id');
            $branchId = (int)$this->request->getData('branch_id');
            $startOn = new DateTime($this->request->getData('start_on'));
            $endOn = null;
            if ($this->request->getData('end_on') !== null && $this->request->getData('end_on') !== "") {
                $endOn = new DateTime($this->request->getData('end_on'));
            } else {
                $endOn = null;
            }
            $approverId = (int)$this->Authentication->getIdentity()->getIdentifier();
            $deputyDescription = $this->request->getData('deputy_description');
            $omResult = $oManager->assign($officeId, $memberId, $branchId, $startOn, $endOn, $deputyDescription, $approverId);
            if (!$omResult->success) {
                $this->Officers->getConnection()->rollback();
                $this->Flash->error(__($omResult->reason));
                $this->redirect($this->referer());
                return;
            }
            //commit transaction
            $this->Officers->getConnection()->commit();
            $this->Flash->success(__('The officer has been saved.'));
            $this->redirect($this->referer());
        }
    }

    public function release(OfficerManagerInterface $oManager)
    {
        $officer = $this->Officers->get($this->request->getData('id'));
        if (!$officer) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($officer);
        if ($this->request->is('post')) {
            $revokeReason = $this->request->getData('revoked_reason');
            $revokeDate = new DateTime($this->request->getData('revoked_on'));
            $revokerId = $this->Authentication->getIdentity()->getIdentifier();

            //begin transaction
            $this->Officers->getConnection()->begin();
            $omResult = $oManager->release($officer->id, $revokerId, $revokeDate, $revokeReason);
            if (!$omResult->success) {
                $this->Officers->getConnection()->rollback();
                $this->Flash->error(__('The officer could not be released. Please, try again.'));
                $this->redirect($this->referer());
            }
            //commit transaction
            $this->Officers->getConnection()->commit();
            $this->Flash->success(__('The officer has been released.'));
            $this->redirect($this->referer());
        }
    }


    public function branchOfficers($id, $state)
    {



        $newOfficer = $this->Officers->newEmptyEntity();
        $this->Authorization->authorize($newOfficer);

        $officersQuery = $this->Officers->find()

            ->contain(['Offices' => ["Departments"], 'Members', 'Branches'])->where(['Branches.id' => $id])
            ->orderBY(["Officers.id" => "ASC"]);

        switch ($state) {
            case 'current':
                $officersQuery = $this->addConditions($officersQuery->find('current')->where(['Officers.branch_id' => $id]), 'current');
                break;
            case 'upcoming':
                $officersQuery = $this->addConditions($officersQuery->find('upcoming')->where(['Officers.branch_id' => $id]), 'upcoming');
                break;
            case 'previous':
                $officersQuery = $this->addConditions($officersQuery->find('previous')->where(['Officers.branch_id' => $id]), 'previous');
                break;
        }


        $paginate = $this->request->getQueryParams();
        $paginate["limit"] = 5;
        $officers = $this->paginate($officersQuery, $paginate);
        $turboFrameId = $state;

        $this->set(compact('officers', 'newOfficer', 'id', 'state'));
    }

    private function buildOfficeTree($offices, $branchType, $branchId = null)
    {
        $tree = [];
        foreach ($offices as $office) {
            if ($office->deputy_to_id == $branchId) {
                $newOffice = [
                    'id' => $office->id,
                    'name' => $office->name,
                    'deputy_to_id' => $office->deputy_to_id,
                    'deputies' => [],
                    'enabled' => strpos($office->applicable_branch_types, "\"$branchType\"") !== false
                ];
                $newOffice['deputies'] = $this->buildOfficeTree($offices, $branchType, $office->id);
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




    public function api()
    {
        $this->Authorization->skipAuthorization();
        $this->autoRender = false;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=officers-' . date("Y-m-d-h-i-s") . '.csv');
        $output = fopen('php://output', 'w');

        fputcsv($output, array('Office', 'Name', 'Branch', 'Department', 'Start', 'End'));
        $officers = $this->Officers->find()
            ->contain(['Offices' => ["Departments"], 'Members', 'Branches'])
            //where not past start and before expired
            ->toArray();

        if (count($officers) > 0) {
            foreach ($officers as $officer) {

                //DateTime::createFromFormat('yyyy-mm-dd hh:mm:ss', $officer['start_on']);

                $officer_row = [
                    $officer['office']['name'],
                    $officer['member']['sca_name'],
                    $officer['branch']['name'],
                    $officer['office']['department']['name'],
                    $officer['start_on']->i18nFormat('MM-dd-yyyy'),
                    $officer['expires_on']->i18nFormat('MM-dd-yyyy'),


                ];

                fputcsv($output, $officer_row);
            }
        }
        //return ($officers);
    }
}