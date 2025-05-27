<?php

declare(strict_types=1);

namespace Officers\Controller;

/**
 * Roster Controller
 *
 *
 */

use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WarrantManager\WarrantRequest;
use App\Services\ServiceResult;
use Officers\Model\Entity\Officer;

class RostersController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        //$this->Authorization->authorizeModel('index','add','searchMembers','addPermission','deletePermission');
    }

    public function add()
    {
        $hide = false;
        $warrantOnly = false;
        $currentUrl = [
            'controller' => $this->request->getParam('controller'),
            'action' => $this->request->getParam('action'),
            'plugin' => $this->request->getParam('plugin'),
            'prefix' => $this->request->getParam('prefix'),
        ];

        $id = $this->request->getParam('pass.0');
        if ($id !== null) {
            $currentUrl[] = $id;
        }

        $queryParams = $this->request->getQueryParams();
        if (!empty($queryParams)) {
            $currentUrl['?'] = $queryParams;
        }
        $this->Authorization->authorize($currentUrl);
        $departmentTbl = TableRegistry::getTableLocator()->get('Officers.Departments');
        $warrantPeriodsQuery = TableRegistry::getTableLocator()->get('WarrantPeriods')
            ->find()
            ->select(['id', 'start_date', 'end_date'])
            ->where(['end_date >=' => DateTime::now()])
            ->all();
        $warrantPeriods = ["-1" => "Select Warrant Period"];
        $warrantPeriod = null;
        $department = null;
        foreach ($warrantPeriodsQuery as $warrantPeriod) {
            $warrantPeriods[$warrantPeriod->id] = $warrantPeriod->name;
        }

        $departmentsData = [];
        $warrantPeriodObj = null;
        if ($this->request->getQuery('warrantPeriod')) {
            $hide = $this->request->getQuery('hide');
            $department = $this->request->getQuery('department');
            $warrantPeriod = $this->request->getQuery('warrantPeriod');
            $warrantPeriodObj = TableRegistry::getTableLocator()->get('WarrantPeriods')->get($warrantPeriod);
            $deptTempQuery = $departmentTbl->find('all')
                ->where(['id ' => $department])
                ->contain([
                    'Offices' => function ($q) use ($warrantOnly) {
                        $q = $q->select(['id', 'name', 'department_id', 'requires_warrant'])
                            ->where(['requires_warrant' => 1]);
                        return $q;
                    },
                    'Offices.Officers' => function ($q) use ($warrantPeriodObj) {
                        return $q->where([
                            'Officers.status IN' => [Officer::CURRENT_STATUS, Officer::UPCOMING_STATUS],
                            "or" => [
                                "Officers.expires_on >=" => $warrantPeriodObj->start_date,
                                "Officers.expires_on IS" => null
                            ]
                        ]);
                    },
                    'Offices.Officers.Members' => function ($q) {
                        return $q->select([
                            'membership_number',
                            'sca_name',
                            'id',
                            'membership_expires_on',
                            'first_name',
                            'last_name',
                            'email_address',
                            'phone_number',
                            'street_address',
                            'city',
                            'state',
                            'zip',
                            'warrantable',
                            'birth_month',
                            'birth_year'
                        ]);
                    },
                    'Offices.Officers.Branches' => function ($q) {
                        return $q->select(['name']);
                    },
                    'Offices.Officers.Offices' => function ($q) {
                        return $q->select(['name']);
                    }
                ]);
            $deptTempData = $deptTempQuery->all();
            //organize the data so we can display it in the view departmentData should have the department name, id, and then an array of officers called dept_officers
            foreach ($deptTempData as $dept) {
                $deptData = new \stdClass();
                $deptData->name = $dept->name;
                $deptData->id = $dept->id;
                $deptData->dept_officers = [];
                $deptData->hasDanger = false;
                foreach ($dept->offices as $office) {
                    foreach ($office->officers as $officer) {
                        $officer->new_warrant_exp_date = $warrantPeriodObj->end_date;
                        $officer->new_warrant_start_date = $warrantPeriodObj->start_date;
                        if ($officer->expires_on < $officer->new_warrant_exp_date) {
                            $officer->new_warrant_exp_date = $officer->expires_on;
                        }
                        $officer->danger = false;
                        $officer->start_date_message = [];
                        $officer->end_date_message = [];
                        if ($officer->member->membership_expires_on < $warrantPeriodObj->start_date) {
                            $officer->danger = true;
                            $officer->start_date_message[] = "Membership will be expired before Warrant Start";
                        }
                        //TODO: Reactiviate when we have reliable membership date
                        //if ($officer->member->membership_expires_on < $officer->new_warrant_exp_date) {
                        //    $officer->danger = true;
                        //   $officer->end_date_message[] = "Membership will be expired before Warrant End";
                        //}
                        if (!$officer->member->warrantable) {
                            $officer->danger = true;
                            $officer->warrant_message = $officer->member->getNonWarrantableReasons();
                        }
                        $deptData->dept_officers[] = $officer;
                        if ($officer->danger) {
                            $deptData->hasDanger = true;
                        }
                    }
                }
                //now lets sort the $deptData->dept_officers by branch name and then office name
                usort($deptData->dept_officers, function ($a, $b) {
                    if ($a->branch->name == $b->branch->name) {
                        return $a->office->name <=> $b->office->name;
                    }
                    return $a->branch->name <=> $b->branch->name;
                });
                $departmentsData[] = $deptData;
            }
        }
        $departmentQuery = $departmentTbl->find()->orderBy(['name' => 'ASC']);
        $departmentList = ["-1" => "Select Department"];
        foreach ($departmentQuery as $dept) {
            $departmentList[$dept->id] = $dept->name;
        }
        $this->set(compact('department', 'departmentList', 'departmentsData', 'hide', 'warrantPeriod', 'warrantPeriods'));
    }
    public function createRoster(WarrantManagerInterface $warrantManager)
    {
        $currentUrl = [
            'controller' => $this->request->getParam('controller'),
            'action' => $this->request->getParam('action'),
            'plugin' => $this->request->getParam('plugin'),
            'prefix' => $this->request->getParam('prefix'),
        ];

        $id = $this->request->getParam('pass.0');
        if ($id !== null) {
            $currentUrl[] = $id;
        }

        $queryParams = $this->request->getQueryParams();
        if (!empty($queryParams)) {
            $currentUrl['?'] = $queryParams;
        }
        $this->Authorization->authorize($currentUrl);
        $this->request->allowMethod(['post']);
        $data = $this->request->getData();
        $officerTbl = TableRegistry::getTableLocator()->get('Officers.Officers');
        $department = TableRegistry::getTableLocator()->get('Officers.Departments')->get($data['department']);
        $warrantPeriod = TableRegistry::getTableLocator()->get('WarrantPeriods')->get($data['warrantPeriod']);
        $officers = $officerTbl->find()
            ->where([
                'Officers.id IN' => $data['check_list']
            ])
            ->contain([
                'Offices',
                'Branches',
                'Members' => function ($q) {
                    return $q->select(['id', 'warrantable', 'membership_expires_on']);
                }
            ])
            ->all();
        $officerData = [];
        $warrants = [];
        $user = $this->Authentication->getIdentity();
        foreach ($officers as $officer) {
            $startOn = new DateTime($warrantPeriod->start_date->toDateTimeString());
            if ($officer->start_on > $startOn) {
                $startOn = $officer->start_on;
            }
            $endOn = new DateTime($warrantPeriod->end_date->toDateTimeString());
            if ($officer->expires_on < $endOn) {
                $endOn = $officer->expires_on;
            }
            $warrants[] = new WarrantRequest(
                "Renewal: " . $officer->branch->name . " " . $officer->office->name,
                'Officers.Officers',
                $officer->id,
                $user->id,
                $officer->member_id,
                $startOn,
                $endOn,
                $officer->granted_member_role_id
            );
        }
        $wmResult = $warrantManager->request("$department->name roster for " . $warrantPeriod->name, "", $warrants);
        if (!$wmResult->success) {
            $this->Flash->error($wmResult->reason);
            return $this->redirect->referer();
        }
        $this->Flash->success("Roster Created");
        return $this->redirect(['plugin' => null, 'controller' => 'warrant-rosters', 'action' => 'view', $wmResult->data]);
    }
}