<?php

declare(strict_types=1);

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Migrations\BaseSeed;

/**
 * Adds dev-only personas for exercising tiered bestowal to-do assignment.
 */
class DevLoadBestowalTodoUsersSeed extends BaseSeed
{
    /**
     * @var list<array{key: string, label: string, office_suffix: string, permission_suffix: string, court_access: bool}>
     */
    private const TODO_AREAS = [
        [
            'key' => 'scroll',
            'label' => 'Scroll',
            'office_suffix' => 'Scroll Deputy',
            'permission_suffix' => 'Scroll Management',
            'court_access' => false,
        ],
        [
            'key' => 'regalia',
            'label' => 'Regalia',
            'office_suffix' => 'Regalia Deputy',
            'permission_suffix' => 'Regalia Management',
            'court_access' => false,
        ],
        [
            'key' => 'schedule',
            'label' => 'Award Schedule',
            'office_suffix' => 'Award Scheduler',
            'permission_suffix' => 'Award Schedule Management',
            'court_access' => true,
        ],
        [
            'key' => 'agenda',
            'label' => 'Court Agenda',
            'office_suffix' => 'Court Agenda Deputy',
            'permission_suffix' => 'Court Management',
            'court_access' => true,
        ],
        [
            'key' => 'reporter',
            'label' => 'Court Reporter',
            'office_suffix' => 'Court Reporter Deputy',
            'permission_suffix' => 'Court Reporter',
            'court_access' => false,
        ],
    ];

    /**
     * @var list<array{key: string, label: string, permission_prefix: string, office_prefix: string, email_prefix: string, branch: string, branch_type: string, parent_office: string}>
     */
    private const TIERS = [
        [
            'key' => 'crown',
            'label' => 'Crown',
            'permission_prefix' => 'Crown',
            'office_prefix' => 'Crown',
            'email_prefix' => 'crown',
            'branch' => 'Ansteorra',
            'branch_type' => 'Kingdom',
            'parent_office' => 'Crown',
        ],
        [
            'key' => 'principality',
            'label' => 'Principality',
            'permission_prefix' => 'Principality',
            'office_prefix' => 'Principality',
            'email_prefix' => 'principality',
            'branch' => 'Vindheim',
            'branch_type' => 'Principality',
            'parent_office' => 'Principality Coronet',
        ],
        [
            'key' => 'baronial',
            'label' => 'Baronial',
            'permission_prefix' => 'Baronial',
            'office_prefix' => 'Baronial',
            'email_prefix' => 'baronial',
            'branch' => 'Barony of Stargate',
            'branch_type' => 'Local Group',
            'parent_office' => 'Landed Nobility',
        ],
    ];

    /**
     * @var array<string, array{email: string, sca_name: string, first_name: string, last_name: string}>
     */
    private const PERSONAS = [
        'crown-scroll' => [
            'email' => 'serena.crown.scroll@ampdemo.com',
            'sca_name' => 'Serena Crown Scrollkeeper',
            'first_name' => 'Serena',
            'last_name' => 'Scrollkeeper',
        ],
        'crown-regalia' => [
            'email' => 'roland.crown.regalia@ampdemo.com',
            'sca_name' => 'Roland Crown Regalia',
            'first_name' => 'Roland',
            'last_name' => 'Regalia',
        ],
        'crown-schedule' => [
            'email' => 'avery.crown.scheduler@ampdemo.com',
            'sca_name' => 'Avery Crown Scheduler',
            'first_name' => 'Avery',
            'last_name' => 'Scheduler',
        ],
        'crown-agenda' => [
            'email' => 'beatrice.crown.court@ampdemo.com',
            'sca_name' => 'Beatrice Crown Court Planner',
            'first_name' => 'Beatrice',
            'last_name' => 'Planner',
        ],
        'crown-reporter' => [
            'email' => 'miles.crown.reporter@ampdemo.com',
            'sca_name' => 'Miles Crown Reporter',
            'first_name' => 'Miles',
            'last_name' => 'Reporter',
        ],
        'principality-scroll' => [
            'email' => 'pippa.principality.scroll@ampdemo.com',
            'sca_name' => 'Pippa Principality Scrollkeeper',
            'first_name' => 'Pippa',
            'last_name' => 'Scrollkeeper',
        ],
        'principality-regalia' => [
            'email' => 'gavin.principality.regalia@ampdemo.com',
            'sca_name' => 'Gavin Principality Regalia',
            'first_name' => 'Gavin',
            'last_name' => 'Regalia',
        ],
        'principality-schedule' => [
            'email' => 'nora.principality.scheduler@ampdemo.com',
            'sca_name' => 'Nora Principality Scheduler',
            'first_name' => 'Nora',
            'last_name' => 'Scheduler',
        ],
        'principality-agenda' => [
            'email' => 'theo.principality.court@ampdemo.com',
            'sca_name' => 'Theo Principality Court Planner',
            'first_name' => 'Theo',
            'last_name' => 'Planner',
        ],
        'principality-reporter' => [
            'email' => 'lydia.principality.reporter@ampdemo.com',
            'sca_name' => 'Lydia Principality Reporter',
            'first_name' => 'Lydia',
            'last_name' => 'Reporter',
        ],
        'baronial-scroll' => [
            'email' => 'bonnie.baronial.scroll@ampdemo.com',
            'sca_name' => 'Bonnie Baronial Scrollkeeper',
            'first_name' => 'Bonnie',
            'last_name' => 'Scrollkeeper',
        ],
        'baronial-regalia' => [
            'email' => 'felix.baronial.regalia@ampdemo.com',
            'sca_name' => 'Felix Baronial Regalia',
            'first_name' => 'Felix',
            'last_name' => 'Regalia',
        ],
        'baronial-schedule' => [
            'email' => 'clara.baronial.scheduler@ampdemo.com',
            'sca_name' => 'Clara Baronial Scheduler',
            'first_name' => 'Clara',
            'last_name' => 'Scheduler',
        ],
        'baronial-agenda' => [
            'email' => 'hugo.baronial.court@ampdemo.com',
            'sca_name' => 'Hugo Baronial Court Planner',
            'first_name' => 'Hugo',
            'last_name' => 'Planner',
        ],
        'baronial-reporter' => [
            'email' => 'tessa.baronial.reporter@ampdemo.com',
            'sca_name' => 'Tessa Baronial Reporter',
            'first_name' => 'Tessa',
            'last_name' => 'Reporter',
        ],
    ];

    /**
     * @return void
     */
    public function run(): void
    {
        $adminId = $this->findId('Members', ['email_address' => 'admin@amp.ansteorra.org'])
            ?? $this->findId('Members', ['email_address' => 'admin@test.com']);
        $nobilityId = $this->findId('Officers.Departments', ['name' => 'Nobility']);
        if ($adminId === null || $nobilityId === null) {
            throw new RuntimeException('Dev bestowal seed requires an admin member and the Nobility department.');
        }
        $now = DateTime::now();

        foreach (self::TIERS as $tier) {
            $branchId = $this->findId('Branches', ['name' => $tier['branch']]);
            $parentOfficeId = $this->findId('Officers.Offices', ['name' => $tier['parent_office']]);
            if ($branchId === null || $parentOfficeId === null) {
                throw new RuntimeException(sprintf(
                    'Dev bestowal seed requires branch "%s" and office "%s".',
                    $tier['branch'],
                    $tier['parent_office'],
                ));
            }

            foreach (self::TODO_AREAS as $area) {
                $permissionNames = [
                    $tier['permission_prefix'] . ' ' . $area['permission_suffix'],
                    'Can View Bestowals',
                ];
                if ($area['court_access']) {
                    $permissionNames[] = 'Can Manage Court Schedule';
                }

                $roleId = $this->ensureRole(
                    $tier['label'] . ' ' . $area['label'] . ' Bestowal Todo',
                    $permissionNames,
                    $adminId,
                    $now,
                );
                $officeId = $this->ensureOffice(
                    $tier['office_prefix'] . ' ' . $area['office_suffix'],
                    $nobilityId,
                    $roleId,
                    (string)$tier['branch_type'],
                    $parentOfficeId,
                    $adminId,
                    $now,
                );
                $personaKey = $tier['key'] . '-' . $area['key'];
                $persona = self::PERSONAS[$personaKey];
                $memberId = $this->ensureMember(
                    $persona,
                    $tier['email_prefix'] . '-' . $area['key'] . '@ampdemo.com',
                    $branchId,
                    $adminId,
                    $now,
                );
                $memberRoleId = $this->ensureMemberRole($memberId, $roleId, $adminId, $now);
                $this->ensureOfficer(
                    $memberId,
                    $branchId,
                    $officeId,
                    $memberRoleId,
                    $parentOfficeId,
                    $adminId,
                    $now,
                );
            }
        }
    }

    /**
     * @param string $tableAlias Table alias.
     * @param array<string, mixed> $conditions Lookup conditions.
     * @return int|null
     */
    private function findId(string $tableAlias, array $conditions): ?int
    {
        $table = TableRegistry::getTableLocator()->get($tableAlias);
        $row = $table->find()
            ->select(['id'])
            ->where($conditions)
            ->first();

        return $row === null ? null : (int)$row->id;
    }

    /**
     * @param string $name Role name.
     * @param list<string> $permissionNames Permission names to attach.
     * @param int $adminId Admin member id.
     * @param \Cake\I18n\DateTime $now Current timestamp.
     * @return int
     */
    private function ensureRole(string $name, array $permissionNames, int $adminId, DateTime $now): int
    {
        $roles = TableRegistry::getTableLocator()->get('Roles');
        $rolesPermissions = TableRegistry::getTableLocator()->get('RolesPermissions');
        $permissions = TableRegistry::getTableLocator()->get('Permissions');

        $role = $roles->find()->where(['name' => $name])->first();
        if ($role === null) {
            $role = $roles->newEntity([
                'name' => $name,
                'is_system' => true,
                'created_by' => $adminId,
                'created' => $now,
            ]);
            $roles->saveOrFail($role);
        }

        foreach ($permissionNames as $permissionName) {
            $permission = $permissions->find()
                ->where(['name' => $permissionName])
                ->first();
            if ($permission === null) {
                throw new RuntimeException(sprintf('Missing bestowal todo permission "%s".', $permissionName));
            }
            if ($permission->requires_warrant) {
                $permission = $permissions->patchEntity($permission, [
                    'requires_warrant' => false,
                    'modified_by' => $adminId,
                ]);
                $permissions->saveOrFail($permission);
            }

            $exists = $rolesPermissions->find()
                ->where([
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                ])
                ->first();
            if ($exists !== null) {
                continue;
            }

            $rolesPermissions->saveOrFail($rolesPermissions->newEntity([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'created_by' => $adminId,
                'created' => $now,
            ]));
        }

        return (int)$role->id;
    }

    /**
     * @param string $name Office name.
     * @param int $departmentId Department id.
     * @param int $roleId Granted role id.
     * @param string $branchType Applicable branch type.
     * @param int $parentOfficeId Parent/deputy office id.
     * @param int $adminId Admin member id.
     * @param \Cake\I18n\DateTime $now Current timestamp.
     * @return int
     */
    private function ensureOffice(
        string $name,
        int $departmentId,
        int $roleId,
        string $branchType,
        int $parentOfficeId,
        int $adminId,
        DateTime $now,
    ): int {
        $offices = TableRegistry::getTableLocator()->get('Officers.Offices');
        $office = $offices->find()->where(['name' => $name])->first();
        $data = [
            'name' => $name,
            'department_id' => $departmentId,
            'requires_warrant' => false,
            'required_office' => false,
            'can_skip_report' => false,
            'only_one_per_branch' => true,
            'grants_role_id' => $roleId,
            'term_length' => 24,
            'branch_types' => [$branchType],
            'reports_to_id' => $parentOfficeId,
            'deputy_to_id' => $parentOfficeId,
            'created_by' => $adminId,
        ];

        if ($office === null) {
            $office = $offices->newEntity($data + ['created' => $now]);
        } else {
            $office = $offices->patchEntity($office, $data + ['modified' => $now]);
        }
        $offices->saveOrFail($office);

        return (int)$office->id;
    }

    /**
     * @param array{email: string, sca_name: string, first_name: string, last_name: string} $persona Persona data.
     * @param string $legacyEmail Previous seed email address.
     * @param int $branchId Branch id.
     * @param int $adminId Admin member id.
     * @param \Cake\I18n\DateTime $now Current timestamp.
     * @return int
     */
    private function ensureMember(
        array $persona,
        string $legacyEmail,
        int $branchId,
        int $adminId,
        DateTime $now,
    ): int {
        $members = TableRegistry::getTableLocator()->get('Members');
        $member = $members->find()->where(['email_address' => $persona['email']])->first()
            ?? $members->find()->where(['email_address' => $legacyEmail])->first();
        $data = [
            'password' => 'TestPassword',
            'sca_name' => $persona['sca_name'],
            'first_name' => $persona['first_name'],
            'middle_name' => '',
            'last_name' => $persona['last_name'],
            'street_address' => 'Bestowal Todo Demo',
            'city' => 'Demo City',
            'state' => 'TX',
            'zip' => '00000',
            'phone_number' => '555-555-0100',
            'email_address' => $persona['email'],
            'membership_number' => (string)random_int(100000, 999999),
            'membership_expires_on' => '2030-01-01',
            'background_check_expires_on' => '2030-01-01',
            'branch_id' => $branchId,
            'status' => 'verified',
            'birth_month' => 1,
            'birth_year' => 1980,
            'created_by' => $adminId,
        ];

        if ($member === null) {
            $member = $members->newEntity($data + [
                'public_id' => bin2hex(random_bytes(4)),
                'created' => $now,
            ]);
        } else {
            unset($data['password']);
            $member = $members->patchEntity($member, $data + ['modified' => $now]);
        }
        $members->saveOrFail($member);

        return (int)$member->id;
    }

    /**
     * @param int $memberId Member id.
     * @param int $roleId Role id.
     * @param int $adminId Admin member id.
     * @param \Cake\I18n\DateTime $now Current timestamp.
     * @return int
     */
    private function ensureMemberRole(int $memberId, int $roleId, int $adminId, DateTime $now): int
    {
        $memberRoles = TableRegistry::getTableLocator()->get('MemberRoles');
        $memberRole = $memberRoles->find()
            ->where([
                'member_id' => $memberId,
                'role_id' => $roleId,
                'expires_on IS' => null,
            ])
            ->first();
        if ($memberRole === null) {
            $memberRole = $memberRoles->newEmptyEntity();
            $memberRole->set([
                'member_id' => $memberId,
                'role_id' => $roleId,
                'start_on' => DateTime::now()->format('Y-m-d'),
                'expires_on' => null,
                'approver_id' => $adminId,
                'granting_model' => 'Direct Grant',
                'created_by' => $adminId,
                'created' => $now,
            ], ['guard' => false]);
            $memberRoles->saveOrFail($memberRole);
        }

        return (int)$memberRole->id;
    }

    /**
     * @param int $memberId Member id.
     * @param int $branchId Branch id.
     * @param int $officeId Office id.
     * @param int $memberRoleId Granted member role id.
     * @param int $parentOfficeId Parent/deputy office id.
     * @param int $adminId Admin member id.
     * @param \Cake\I18n\DateTime $now Current timestamp.
     * @return void
     */
    private function ensureOfficer(
        int $memberId,
        int $branchId,
        int $officeId,
        int $memberRoleId,
        int $parentOfficeId,
        int $adminId,
        DateTime $now,
    ): void {
        $officers = TableRegistry::getTableLocator()->get('Officers.Officers');
        $members = TableRegistry::getTableLocator()->get('Members');
        $member = $members->get($memberId);
        $officer = $officers->find()
            ->where([
                'member_id' => $memberId,
                'branch_id' => $branchId,
                'office_id' => $officeId,
                'status' => 'Current',
            ])
            ->first();

        $data = [
            'member_id' => $memberId,
            'branch_id' => $branchId,
            'office_id' => $officeId,
            'granted_member_role_id' => $memberRoleId,
            'start_on' => $now,
            'expires_on' => null,
            'status' => 'Current',
            'deputy_description' => 'Bestowal to-do demo deputy',
            'approver_id' => $adminId,
            'approval_date' => $now,
            'reports_to_branch_id' => $branchId,
            'reports_to_office_id' => $parentOfficeId,
            'deputy_to_branch_id' => $branchId,
            'deputy_to_office_id' => $parentOfficeId,
            'email_address' => $member->email_address,
            'created_by' => $adminId,
        ];

        if ($officer === null) {
            $officer = $officers->newEntity($data + ['created' => $now]);
        } else {
            $officer = $officers->patchEntity($officer, $data + ['modified' => $now]);
        }
        $officers->saveOrFail($officer);
    }
}
