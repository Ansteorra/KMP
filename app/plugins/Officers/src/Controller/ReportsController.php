<?php

declare(strict_types=1);

namespace Officers\Controller;

use Cake\Log\Log;

/**
 * Reports Controller
 *
 *
 */

use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;

class ReportsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        //$this->Authorization->authorizeModel('index','add','searchMembers','addPermission','deletePermission');
    }

    public function departmentOfficersRoster()
    {
        $hide = false;
        $warrantOnly = false;
        $this->authorizeCurrentUrl();
        $departmentTbl = TableRegistry::getTableLocator()->get('Officers.Departments');
        $validOn = DateTime::now()->addDays(1);
        $departments = [];
        $departmentsData = [];
        if ($this->request->getQuery('validOn')) {
            $hide = $this->request->getQuery('hide');
            $validOn = (new DateTime($this->request->getQuery('validOn')))->addDays(1);
            $departments = $this->request->getQuery('departments');
            $warrantOnly = $this->request->getQuery('warranted');
            $deptTempQuery = $departmentTbl->find('all')
                ->where(['id IN' => $departments])
                ->contain([
                    'Offices' => function ($q) use ($warrantOnly) {
                        $q = $q->select(['id', 'name', 'department_id', 'requires_warrant']);
                        if ($warrantOnly) {
                            $q = $q->where(['requires_warrant' => 1]);
                        }
                        return $q;
                    },
                    'Offices.Officers' => function ($q) use ($validOn) {
                        return $this->setValidFilter($q, $validOn);
                    },
                    'Offices.Officers.CurrentWarrants',
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
                        return $q->select(['name', 'requires_warrant']);
                    }
                ]);
            $deptTempData = $deptTempQuery->all();
            //organize the data so we can display it in the view departmentData should have the department name, id, and then an array of officers called dept_officers
            foreach ($deptTempData as $dept) {
                $deptData = new \stdClass();
                $deptData->name = $dept->name;
                $deptData->id = $dept->id;
                $deptData->dept_officers = [];
                foreach ($dept->offices as $office) {
                    foreach ($office->officers as $officer) {
                        $deptData->dept_officers[] = $officer;
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
        $validOn = $validOn->subDays(1);
        $user = $this->request->getAttribute('identity');
        $departmentList = $departmentTbl->departmentsMemberCanWork($user);
        $this->set(compact('validOn', 'departments', 'departmentList', 'departmentsData', 'hide', 'warrantOnly'));
    }

    protected function setValidFilter($q, $validOn)
    {
        return $q->where([
            "or" => [
                "Officers.expires_on >=" => $validOn,
                "Officers.expires_on IS" => null
            ]
        ])
            ->where([
                "or" => [
                    "Officers.start_on <=" => $validOn,
                    "Officers.start_on IS" => null
                ],
            ]);
    }
}
