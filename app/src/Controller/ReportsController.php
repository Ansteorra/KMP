<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

class ReportsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        //$this->Authorization->authorizeModel('index','add','searchMembers','addPermission','deletePermission');
    }

    public function rolesList()
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
        $rolestbl
            = TableRegistry::getTableLocator()->get('Roles');
        $validOn = DateTime::now()->addDays(1);
        if ($this->request->getQuery('validOn')) {
            $validOn = (new DateTime($this->request->getQuery('validOn')))->addDays(1);
        }
        $roles = $rolestbl->find('all')
            ->select(['id', 'name'])
            ->contain([
                'MemberRoles' => function ($q) use ($validOn) {
                    return $this->setValidFilter($q, $validOn);
                },
                'MemberRoles.Members' => function ($q) {
                    return $q->select(['membership_number', 'sca_name', 'id', 'membership_expires_on']);
                },
                'MemberRoles.ApprovedBy' => function ($q) {
                    return $q->select(['sca_name', 'id']);
                },
                'MemberRoles.Members.Branches' => function ($q) {
                    return $q->select(['name']);
                },
            ])
            ->all();
        $validOn = $validOn->subDays(1);
        $this->set(compact('roles', 'validOn'));
    }

    public function permissionsWarrantsRoster()
    {
        $hide = false;
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
        $validOn = DateTime::now()->addDays(1);
        if ($this->request->getQuery('validOn')) {
            $hide = $this->request->getQuery('hide');
            $validOn = (new DateTime($this->request->getQuery('validOn')))->addDays(1);
        }
        $permissionsTbl = TableRegistry::getTableLocator()->get('Permissions');

        $permissionsQuery = $permissionsTbl->find()
            ->contain(
                [
                    'Roles.MemberRoles' => function ($q) use ($validOn) {
                        return $q->select([
                            'role_id',
                            'member_id',
                            'start_on',
                            'expires_on',
                            'id',
                        ])
                            ->contain([
                                'Members' => function ($q) use ($validOn) {
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
                                            'zip',
                                        ]),
                                        $validOn,
                                    );
                                },
                                'Members.Branches' => function ($q) {
                                    return $q->select(['name']);
                                },
                            ]);
                    },
                ],
            )
            ->where(['requires_warrant' => 1])
            ->orderBy('name')
            ->distinct();
        //Log::debug($permissionsQuery);
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
        $validOn = $validOn->subDays(1);
        $this->set(compact('permissionsRoster', 'validOn', 'hide'));
    }

    protected function setValidFilter($q, $validOn)
    {
        return $q->where([
            'OR' => [
                'start_on <=' => $validOn,
                'start_on IS' => null,
            ],
        ])->where([
            'OR' => [
                'expires_on >=' => $validOn,
                'expires_on IS' => null,
            ],
        ]);
    }
}