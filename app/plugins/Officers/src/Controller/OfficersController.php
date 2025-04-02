<?php

declare(strict_types=1);

namespace Officers\Controller;

use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use Officers\Services\OfficerManagerInterface;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\ServiceResult;
use App\Services\WarrantManager\WarrantRequest;
use App\Model\Entity\Warrant;
use Cake\I18n\DateTime;
use Officers\Model\Entity\Officer;
use App\Model\Entity\Member;

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
        $this->Authentication->addUnauthenticatedActions(['api']);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function assign(OfficerManagerInterface $oManager)
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
            $emailAddress = $this->request->getData('email_address');
            $endOn = null;
            if ($this->request->getData('end_on') !== null && $this->request->getData('end_on') !== "") {
                $endOn = new DateTime($this->request->getData('end_on'));
            } else {
                $endOn = null;
            }
            $approverId = (int)$this->Authentication->getIdentity()->getIdentifier();
            $deputyDescription = $this->request->getData('deputy_description');
            $omResult = $oManager->assign($officeId, $memberId, $branchId, $startOn, $endOn, $deputyDescription, $approverId, $emailAddress);
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
    public function edit()
    {
        $this->request->allowMethod(["post"]);
        $officer = $this->Officers->get($this->request->getData('id'));
        if (!$officer) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($officer);
        $officer->deputy_description = $this->request->getData('deputy_description');
        $officer->email_address = $this->request->getData('email_address');
        if ($this->Officers->save($officer)) {
            $this->Flash->success(__('The officer has been saved.'));
        } else {
            $this->Flash->error(__('The officer could not be saved. Please, try again.'));
        }
        $this->redirect($this->referer());
    }

    public function requestWarrant(WarrantManagerInterface $wManager, $id)
    {
        $officer = $this->Officers->find()->where(['Officers.id' => $id])->contain(["Offices", "Branches", "Members"])->first();
        $userid = $this->Authentication->getIdentity()->getIdentifier();
        if (!$officer) {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $this->Authorization->authorize($officer);
        if ($this->request->is('post')) {
            $officeName = $officer->office->name;
            if ($officer->deputy_description != null && $officer->deputy_description != "") {
                $officeName = $officeName . " (" . $officer->deputy_description . ")";
            }
            $branchName = $officer->branch->name;
            $warrantRequest = new WarrantRequest("Manual Request Warrant: $branchName - $officeName", 'Officers.Officers', $officer->id, $userid, $officer->member_id, $officer->start_on, $officer->expires_on, $officer->granted_member_role_id);
            $memberName = $officer->member->sca_name;
            $wmResult = $wManager->request("$officeName : $memberName", "", [$warrantRequest]);
            if (!$wmResult->success) {
                $this->Flash->error("Could not request Warrant: " . __($wmResult->reason));
                $this->redirect($this->referer());
                return;
            }
            $this->Flash->success(__('The warrant request has been sent.'));
            $this->redirect($this->referer());
            return;
        }
    }

    public function branchOfficers($id, $state)
    {
        $newOfficer = $this->Officers->newEmptyEntity();
        $this->Authorization->authorize($newOfficer);

        $officersQuery = $this->Officers->find()

            ->contain(['Offices' => ["Departments"], 'Members', 'Branches'])->where(['Branches.id' => $id])
            ->orderBY(["Officers.id" => "ASC"]);

        $search = $this->request->getQuery("search");
        $search = $search ? trim($search) : null;

        if ($search) {
            //detect th and replace with Þ
            $nsearch = $search;
            if (preg_match("/th/", $search)) {
                $nsearch = str_replace("th", "Þ", $search);
            }
            //detect Þ and replace with th
            $usearch = $search;
            if (preg_match("/Þ/", $search)) {
                $usearch = str_replace("Þ", "th", $search);
            }
            $officersQuery = $officersQuery->where([
                "OR" => [
                    ["Members.sca_name LIKE" => "%" . $search . "%"],
                    ["Members.sca_name LIKE" => "%" . $nsearch . "%"],
                    ["Members.sca_name LIKE" => "%" . $usearch . "%"],
                    ["Offices.name LIKE" => "%" . $search . "%"],
                    ["Offices.name LIKE" => "%" . $nsearch . "%"],
                    ["Offices.name LIKE" => "%" . $usearch . "%"],
                    ["Departments.name LIKE" => "%" . $search . "%"],
                    ["Departments.name LIKE" => "%" . $nsearch . "%"],
                    ["Departments.name LIKE" => "%" . $usearch . "%"],

                ],
            ]);
        }

        switch ($state) {
            case 'current':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('current')->where(['Officers.branch_id' => $id]), 'current');
                break;
            case 'upcoming':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('upcoming')->where(['Officers.branch_id' => $id]), 'upcoming');
                break;
            case 'previous':
                $officersQuery = $this->Officers->addDisplayConditionsAndFields($officersQuery->find('previous')->where(['Officers.branch_id' => $id]), 'previous');
                break;
        }

        $page = $this->request->getQuery("page");
        $limit = $this->request->getQuery("limit");
        $paginate = [];
        if ($page) {
            $paginate['page'] = $page;
        }
        if ($limit) {
            $paginate['limit'] = $limit;
        }
        //$paginate["limit"] = 5;
        $officers = $this->paginate($officersQuery, $paginate);
        $turboFrameId = $state;

        $this->set(compact('officers', 'newOfficer', 'id', 'state'));
    }

    public function autoComplete($officeId)
    {
        //TODO: Audit for Privacy
        $memberTbl = $this->getTableLocator()->get('Members');
        $q = $this->request->getQuery("q");
        //detect th and replace with Þ
        $nq = $q;
        if (preg_match("/th/", $q)) {
            $nq = str_replace("th", "Þ", $q);
        }
        //detect Þ and replace with th
        $uq = $q;
        if (preg_match("/Þ/", $q)) {
            $uq = str_replace("Þ", "th", $q);
        }
        $office = $this->Officers->Offices->get($officeId);
        $this->Authorization->skipAuthorization();
        $this->request->allowMethod(["get"]);
        $this->viewBuilder()->setClassName("Ajax");
        $query = $memberTbl
            ->find("all")
            ->where([
                'status <>' => Member::STATUS_DEACTIVATED,
                'OR' => [["sca_name LIKE" => "%$q%"], ["sca_name LIKE" => "%$nq%"], ["sca_name LIKE" => "%$uq%"]]
            ])
            ->select(["id", "sca_name", "warrantable", "status"])
            ->limit(50);
        $this->set(compact("query", "q", "nq", "uq", "office"));
    }

    public function index()
    {
        $this->Authorization->skipAuthorization();
    }

    public function officersByWarrantStatus($state)
    {

        if ($state != 'current' && $state == 'pending' && $state == 'previous') {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        //$securityOfficer = $this->Officers->newEmptyEntity();
        $this->Authorization->skipAuthorization();


        $membersTable = $this->fetchTable('Members');
        $warrantsTable = $this->fetchTable('Warrants');

        $officersQuery = $this->Officers->find()
            ->select([
                'revoked_reason',
                'sca_name' => 'Members.sca_name',
                'branch_name' => 'Branches.name',
                'office_name' => 'Offices.name',
                'deputy_description' => 'Officers.deputy_description',
                'start_on',
                'expires_on',
                'warrant_status' => 'Warrants.status',
                'status' => 'Officers.status',
                'revoker_id',
                'revoked_by' => 'revoker.sca_name',
            ])
            ->innerJoin(
                ['Offices' => 'officers_offices'],
                ['Offices.id = Officers.office_id']
            )
            ->innerJoin(
                ['Branches' => 'branches'],
                ['Branches.id = Officers.branch_id']
            )
            ->innerJoin(
                ['Members' => 'members'],
                ['Members.id = Officers.member_id']
            )
            ->join([
                'table' => 'members',
                'alias' => 'revoker',
                'type' => 'LEFT',
                'conditions' => 'revoker.id = Officers.revoker_id',
            ])
            ->leftJoin(
                ['Warrants' => 'warrants'],
                ['Members.id = Warrants.member_id AND Officers.id = Warrants.entity_id']
            )
            ->order(['sca_name' => 'ASC'])
            ->order(['office_name' => 'ASC']);

        $today = new DateTime();
        switch ($state) {
            case 'current':
                $officersQuery = $officersQuery->where(['Warrants.expires_on >=' => $today, 'Warrants.start_on <=' => $today, 'Warrants.status' => Warrant::CURRENT_STATUS]);
                break;
            case 'unwarranted':
                $officersQuery = $officersQuery->where("Warrants.id IS NULL");

                break;
            case 'pending':
                $officersQuery = $officersQuery->where(['Warrants.status' => Warrant::PENDING_STATUS]);
                break;
            case 'previous':
                $officersQuery = $officersQuery->where(["OR" => ['Warrants.expires_on <' => $today, 'Warrants.status IN ' => [Warrant::DEACTIVATED_STATUS, Warrant::EXPIRED_STATUS]]]);
                break;
        }
        //$officersQuery = $this->addConditions($officersQuery);
        $officers = $this->paginate($officersQuery);
        $this->set(compact('officers', 'state'));
    }

    public function api()
    {
        $this->Authorization->skipAuthorization();
        $this->autoRender = false;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=officers-' . date("Y-m-d-h-i-s") . '.csv');
        $output = fopen('php://output', 'w');

        $status = $this->request->getQuery('status');
        $endsIn = $this->request->getQuery('endsIn');

        $officers = $this->Officers->find()
            ->contain(['Offices' => ["Departments"], 'Members', 'Branches']);
        if ($status !== null) {
            $officers = $officers->where(["Officers.status" => $status]);
        }
        if ($endsIn !== null) {
            $endDate = new DateTime('+' . $endsIn . ' days');

            $officers = $officers->where([
                "Officers.expires_on >=" => DateTime::now(),
                "Officers.expires_on <=" => $endDate
            ]);
        }
        fputcsv($output, array('Office', 'Name', 'Branch', 'Department', 'Start', 'End'));

        $officers = $officers->toArray();

        if (count($officers) > 0) {
            foreach ($officers as $officer) {

                //DateTime::createFromFormat('yyyy-mm-dd hh:mm:ss', $officer['start_on']);
                $memberData = $officer['member']->publicData();
                $officeName = $officer['office']['name'];
                if ($officer['deputy_description'] != null && $officer['deputy_description'] != "") {
                    $officeName = $officeName . " (" . $officer['deputy_description'] . ")";
                }
                $officer_row = [
                    $officeName,
                    $memberData['sca_name'],
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