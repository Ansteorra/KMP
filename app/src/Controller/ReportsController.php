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
                    return $this->setValidFilter($q, $validOn);
                },
                "MemberRoles.Members" => function ($q) {
                    return $q->select(['membership_number', 'sca_name', 'id', 'membership_expires_on']);
                },
                "MemberRoles.ApprovedBy" => function ($q) {
                    return $q->select(['sca_name', 'id']);
                },
                "MemberRoles.Members.Branches" => function ($q) {
                    return $q->select(['name']);
                }
            ])
            ->all();
        $this->set(compact('roles', 'validOn'));
    }

    public function permissionsWarrantsRoster()
    {
        $hide = false;
        $this->Authorization->authorize($this);
        $validOn = Date::now()->addDays(1);
        if ($this->request->getQuery('validOn')) {
            $hide = $this->request->getQuery('hide');
            $validOn = (new DateTime($this->request->getQuery('validOn')))->addDays(1);
        }
        $permissionsTbl = TableRegistry::getTableLocator()->get('Permissions');

        $permissionsQuery = $permissionsTbl->find()
            ->contain(
                [
                    "Roles.MemberRoles" => function ($q) use ($validOn) {
                        return $q->select([
                            'role_id',
                            'member_id',
                            'start_on',
                            'expires_on',
                            'id'
                        ])
                            ->contain([
                                "Members" => function ($q) use ($validOn) {
                                    return $this->setValidFilter(
                                        $q->select([
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
                                        ]),
                                        $validOn
                                    );
                                },
                                "Members.Branches" => function ($q) {
                                    return $q->select(['name']);
                                },
                            ]);
                    }
                ]
            )
            ->where(['requires_warrant' => 1])
            ->orderBy('name')
            ->distinct();
        $permissions = $permissionsQuery->all()->toArray();
        $permissionsRoster = [];
        foreach ($permissions as $permission) {
            foreach ($permission->roles as $role) {
                foreach ($role->member_roles as $memberRole) {
                    $permissionsRoster[$permission->name][$memberRole->member->id] = $memberRole->member;
                }
            }
        }
        foreach ($permissionsRoster as $permissionName => $permissionUser) {
            $show = false;
            //sort by sca_name
            usort($permissionUser, function ($a, $b) {
                return strcmp($a->sca_name, $b->sca_name);
            });
            $permissionsRoster[$permissionName] = $permissionUser;
        }
        $this->set(compact('permissionsRoster', 'validOn', "hide"));
    }

    protected function setValidFilter($q, $validOn)
    {
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
    }
}