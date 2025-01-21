<?php

declare(strict_types=1);

namespace Officers\Controller;

use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use Officers\Services\OfficerManagerInterface;
use App\Model\Entity\Warrant;
use Cake\I18n\DateTime;

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
        $this->Authorization->authorizeModel("index", "add");
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

    public function index() {}

    public function allOfficers($state)
    {

        if ($state != 'current' && $state == 'pending' && $state == 'previous') {
            throw new \Cake\Http\Exception\NotFoundException();
        }
        $securityOfficer = $this->Officers->newEmptyEntity();
        $this->Authorization->authorize($securityOfficer);


        $membersTable = $this->fetchTable('Members');
        $warrantsTable = $this->fetchTable('Warrants');

        $officersQuery = $this->Officers->find()
            ->selectAlso(['sca_name' => 'Members.sca_name', 'office_name' => 'Offices.name'])
            ->selectAlso($warrantsTable)
            ->contain('Offices')
            ->leftJoin(
                ['Members' => 'members'],
                ['Members.id = Officers.member_id']
            )
            ->leftJoin(
                ['Warrants' => 'warrants'],
                ['Members.id = Warrants.member_id']
            );

        $today = new DateTime();
        switch ($state) {
            case 'current':
                $officersQuery = $officersQuery->where(['Warrants.expires_on >=' => $today, 'Warrants.start_on <=' => $today, 'Warrants.status' => Warrant::CURRENT_STATUS]);
                break;
            case 'upcoming':
                $officersQuery = $officersQuery->where(['Warrants.start_on >' => $today, 'Warrants.status' => Warrant::CURRENT_STATUS]);
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