<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Log\Log;

/**
 * Reports Controller
 *
 *
 */

use Cake\ORM\TableRegistry;
use Cake\I18n\DateTime;
use Cake\I18n\Date;

class ReportsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        //$this->Authorization->authorizeModel('index','add','searchMembers','addPermission','deletePermission');
    }

    public function rolesList()
    {

        $this->Authorization->authorize($this);
        $rolestbl
            = TableRegistry::getTableLocator()->get('Roles');
        $validOn = Date::now();
        if ($this->request->getQuery('validOn')) {
            $validOn = $this->request->getQuery('validOn');
        }
        $roles = $rolestbl->find("all")
            ->select(['id', 'name'])
            ->contain([
                "MemberRoles" => function ($q) use ($validOn) {
                    return $q->where([
                        "or" => [
                            "start_on <=" => $validOn,
                            "start_on IS" => null
                        ],
                        "or" => [
                            "expires_on >=" => $validOn,
                            "expires_on IS" => null
                        ]
                    ]);
                },
                "MemberRoles.Members" => function ($q) {
                    return $q->select(['membership_number', 'sca_name', 'id', 'membership_expires_on']);
                },
                "MemberRoles.Approved_By" => function ($q) {
                    return $q->select(['sca_name', 'id']);
                },
                "MemberRoles.Members.Branches" => function ($q) {
                    return $q->select(['name']);
                }
            ])
            ->all();
        $this->set(compact('roles', 'validOn'));
    }

    public function warrantsRoster()
    {
        $hide = false;
        $this->Authorization->authorize($this);
        $rolestbl = TableRegistry::getTableLocator()->get('Roles');
        $permissionsTbl = TableRegistry::getTableLocator()->get('Permissions');
        //we need a list of role_ids that are for roles where the permission requires a warrant
        $validOn = Date::now();
        if ($this->request->getQuery('validOn')) {
            $hide = $this->request->getQuery('hide');
            $validOn = $this->request->getQuery('validOn');
        }
        $roles = $rolestbl->find()
            ->select(['id', 'name'])
            ->matching('Permissions', function ($q) {
                return $q->where(['Permissions.requires_warrant' => 1]);
            })
            ->contain([
                "MemberRoles" => function ($q) use ($validOn) {
                    return $q->where([
                        "or" => [
                            "start_on <=" => $validOn,
                            "start_on IS" => null
                        ],
                        "or" => [
                            "expires_on >=" => $validOn,
                            "expires_on IS" => null
                        ]
                    ]);
                },
                "MemberRoles.Members" => function ($q) {
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
                        'zip'
                    ]);
                },
                "MemberRoles.Approved_By" => function ($q) {
                    return $q->select(['sca_name', 'id']);
                },
                "MemberRoles.Members.Branches" => function ($q) {
                    return $q->select(['name']);
                }
            ])
            ->distinct()
            ->all();
        $this->set(compact('roles', 'validOn', "hide"));
    }

    public function authorizations()
    {
        $this->Authorization->authorize($this);
        $distincMemberCount = 0;
        $ActivitiesTbl
            = TableRegistry::getTableLocator()->get('Activities');
        $activitiesList = $ActivitiesTbl->find('list')->orderBy(['name' => 'ASC']);
        $validOn = DateTime::now();
        $memberRollup  = [];
        $memberListQuery = [];
        $authorizations = [];
        if ($this->request->getQuery('validOn')) {
            $authorizations = $this->request->getQuery('activities');
            $validOn = $this->request->getQuery('validOn');
            $authTbl = TableRegistry::getTableLocator()->get('Authorizations');
            $distincMemberCount = $authTbl->find()
                ->select('member_id')
                ->where([
                    "or" => [
                        "start_on <=" => $validOn,
                        "start_on IS" => null
                    ],
                    "expires_on >" => $validOn,
                    "activity_id IN" => $authorizations
                ])
                ->distinct('member_id')
                ->count();
            $memberListQuery = $authTbl->find('all')
                ->contain(['Activities' => function ($q) {
                    return $q->select(['name']);
                }, 'Members' => function ($q) {
                    return $q->select(['membership_number', 'sca_name', 'id']);
                }, "Members.Branches" => function ($q) {
                    return $q->select(['name']);
                }])
                ->where([
                    "or" => [
                        "start_on <=" => $validOn,
                        "start_on IS" => null
                    ],
                    "expires_on >" => $validOn,
                    "activity_id IN" => $authorizations
                ])
                ->order(['Activities.name' => 'ASC', 'Members.sca_name' => 'ASC'])
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
                    "activity_id IN" => $authorizations
                ])
                ->groupBy(['Activities.name'])
                ->all();
        }

        $this->set(compact(
            'activitiesList',
            'distincMemberCount',
            'validOn',
            'memberRollup',
            'memberListQuery',
            'authorizations'
        ));
    }
}
