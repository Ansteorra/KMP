<?php

declare(strict_types=1);

namespace Activities\Controller;

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

    public function authorizations()
    {
        $this->Authorization->authorize($this);
        $distincMemberCount = 0;
        $ActivitiesTbl
            = TableRegistry::getTableLocator()->get('Activities.Activities');
        $activitiesList = $ActivitiesTbl->find('list')->orderBy(['name' => 'ASC']);
        $branchesTbl = TableRegistry::getTableLocator()->get('Branches');
        $branchesList = $branchesTbl->find('treeList', spacer:'- ')->toArray();
        $validOn = DateTime::now()->addDays(1);
        $memberRollup  = [];
        $memberListQuery = [];
        $activities = [];
        if ($this->request->getQuery('validOn')) {
            $activities = $this->request->getQuery('activities');
            $filter_branch = $this->request->getQuery('branches');
            $valid_branches = $branchesTbl->find('children', for: $filter_branch)->all()->extract('id')->toArray();
            $valid_branches[]=$filter_branch;
            $validOn = (new DateTime($this->request->getQuery('validOn')))->addDays(1);
            $authTbl = TableRegistry::getTableLocator()->get('Activities.Authorizations');
            $distincMemberCount = $authTbl->find()
                ->select('member_id')
                ->where([
                    "or" => [
                        "start_on <=" => $validOn,
                        "start_on IS" => null
                    ],
                    "expires_on >" => $validOn,
                    "activity_id IN" => $activities
                ])
                ->distinct('member_id')
                ->count();
            $memberListQuery = $authTbl->find('all')
                ->contain(['Activities' => function ($q) {
                    return $q->select(['name']);
                }, 'Members' => function ($q) use ($valid_branches){
                    return $q->select(['membership_number', 'sca_name', 'id'])->where(['branch_id IN' => $valid_branches]);
                }, "Members.Branches" => function ($q) {
                    return $q->select(['name']);
                }])
                ->where([
                    "or" => [
                        "start_on <=" => $validOn,
                        "start_on IS" => null
                    ],
                    "expires_on >" => $validOn,
                    "activity_id IN" => $activities
                ])
                ->orderBy(['Activities.name' => 'ASC', 'Members.sca_name' => 'ASC'])
                ->all();
            $authTypes = $authTbl->find('all')->contain('Activities');
            $memberRollup = $authTypes
                ->select(["auth" => 'Activities.name', "count" => $authTypes->func()->count('member_id')])
                ->where(["start_on <" => $validOn])
                ->where([
                    "or" => [
                        "start_on <=" => $validOn,
                        "start_on IS" => null
                    ],
                    "expires_on >" => $validOn,
                    "activity_id IN" => $activities
                ])
                ->groupBy(['Activities.name'])
                ->all();
        }

        $validOn = $validOn->subDays(1);
        $this->set(compact(
            'activitiesList',
            'branchesList',
            'distincMemberCount',
            'validOn',
            'memberRollup',
            'memberListQuery',
            'activities'
        ));
    }

    protected function setValidFilter($q, $validOn)
    {
        return $q->where([
            "OR" => [
                "start_on <=" => $validOn,
                "start_on IS" => null
            ]
        ])->where([
            "OR" => [
                "expires_on >=" => $validOn,
                "expires_on IS" => null
            ]
        ]);
    }
}