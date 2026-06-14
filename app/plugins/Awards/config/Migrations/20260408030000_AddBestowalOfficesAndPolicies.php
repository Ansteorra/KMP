<?php

declare(strict_types=1);

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Map bestowal permissions to policies and seed court-staff officer offices.
 */
class AddBestowalOfficesAndPolicies extends BaseMigration
{
    private const POLICY_CLASS = 'Awards\\Policy\\BestowalPolicy';

    private const TABLE_POLICY_CLASS = 'Awards\\Policy\\BestowalsTablePolicy';

    /**
     * @return void
     */
    public function up(): void
    {
        $this->mapBestowalPermissionPolicies();
        $this->seedBestowalOfficerRolesAndOffices();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $roles = TableRegistry::getTableLocator()->get('Roles');
        $rolesPermissions = TableRegistry::getTableLocator()->get('RolesPermissions');
        $offices = TableRegistry::getTableLocator()->get('Officers.Offices');

        $permissionNames = [
            'Can View Bestowals',
            'Can Manage Bestowals',
            'Can Prepare Scrolls',
            'Can Manage Court Schedule',
        ];

        foreach ($permissionNames as $name) {
            $permission = $permissions->find()->where(['name' => $name])->first();
            if ($permission) {
                $permissionPolicies->deleteAll(['permission_id' => $permission->id]);
            }
        }

        $officeNames = ['Golden Staff', 'Stable Scroll', 'Court Herald'];
        foreach ($officeNames as $officeName) {
            $office = $offices->find()->where(['name' => $officeName])->first();
            if ($office) {
                $offices->delete($office);
            }
        }

        $roleNames = [
            'Golden Staff',
            'Stable Scroll',
            'Court Herald',
        ];
        foreach ($roleNames as $roleName) {
            $role = $roles->find()->where(['name' => $roleName])->first();
            if ($role) {
                $rolesPermissions->deleteAll(['role_id' => $role->id]);
                $roles->delete($role);
            }
        }
    }

    /**
     * @return void
     */
    private function mapBestowalPermissionPolicies(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');

        $mappings = [
            'Can View Bestowals' => [
                [self::POLICY_CLASS, 'canView'],
                [self::POLICY_CLASS, 'canIndex'],
                [self::POLICY_CLASS, 'canGatheringBestowalsGridData'],
                [self::POLICY_CLASS, 'canViewGatheringBestowals'],
                [self::TABLE_POLICY_CLASS, 'canIndex'],
                [self::TABLE_POLICY_CLASS, 'canExport'],
            ],
            'Can Manage Bestowals' => [
                [self::POLICY_CLASS, 'canEdit'],
                [self::POLICY_CLASS, 'canUpdateState'],
                [self::POLICY_CLASS, 'canUpdateStates'],
                [self::POLICY_CLASS, 'canTurboEditForm'],
                [self::POLICY_CLASS, 'canTurboBulkEditForm'],
                [self::POLICY_CLASS, 'canCourtSlotsForGathering'],
                [self::POLICY_CLASS, 'canGatheringsForBestowalAutoComplete'],
                [self::POLICY_CLASS, 'canGatheringsForBestowalBulkAutoComplete'],
                [self::POLICY_CLASS, 'canCancel'],
                [self::POLICY_CLASS, 'canAdHoc'],
                [self::POLICY_CLASS, 'canViewHidden'],
            ],
            'Can Prepare Scrolls' => [
                [self::POLICY_CLASS, 'canPrepareScrolls'],
            ],
            'Can Manage Court Schedule' => [
                [self::POLICY_CLASS, 'canManageCourtSchedule'],
            ],
        ];

        foreach ($mappings as $permissionName => $policies) {
            $permission = $permissions->find()->where(['name' => $permissionName])->first();
            if ($permission === null) {
                continue;
            }

            foreach ($policies as [$policyClass, $policyMethod]) {
                $exists = $permissionPolicies->find()
                    ->where([
                        'permission_id' => $permission->id,
                        'policy_class' => $policyClass,
                        'policy_method' => $policyMethod,
                    ])
                    ->first();
                if ($exists) {
                    continue;
                }

                $policy = $permissionPolicies->newEntity([
                    'permission_id' => $permission->id,
                    'policy_class' => $policyClass,
                    'policy_method' => $policyMethod,
                ]);
                $permissionPolicies->save($policy);
            }
        }
    }

    /**
     * @return void
     */
    private function seedBestowalOfficerRolesAndOffices(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $roles = TableRegistry::getTableLocator()->get('Roles');
        $rolesPermissions = TableRegistry::getTableLocator()->get('RolesPermissions');
        $departments = TableRegistry::getTableLocator()->get('Officers.Departments');
        $offices = TableRegistry::getTableLocator()->get('Officers.Offices');

        $nobility = $departments->find()->where(['name' => 'Nobility'])->first();
        if ($nobility === null) {
            return;
        }

        $permissionIds = [];
        foreach (
            [
            'Can View Bestowals',
            'Can Manage Bestowals',
            'Can Prepare Scrolls',
            'Can Manage Court Schedule',
            ] as $name
        ) {
            $permission = $permissions->find()->where(['name' => $name])->first();
            if ($permission) {
                $permissionIds[$name] = (int)$permission->id;
            }
        }

        $roleConfigs = [
            'Golden Staff' => [
                'Can View Bestowals',
                'Can Manage Bestowals',
                'Can Prepare Scrolls',
                'Can Manage Court Schedule',
            ],
            'Stable Scroll' => [
                'Can View Bestowals',
                'Can Prepare Scrolls',
            ],
            'Court Herald' => [
                'Can View Bestowals',
                'Can Manage Court Schedule',
            ],
        ];

        $roleIds = [];
        foreach ($roleConfigs as $roleName => $permissionNames) {
            $role = $roles->find()->where(['name' => $roleName])->first();
            if ($role === null) {
                $role = $roles->newEntity([
                    'name' => $roleName,
                    'require_active_membership' => true,
                    'require_active_background_check' => false,
                    'require_min_age' => 0,
                    'is_system' => true,
                    'is_super_user' => false,
                    'requires_warrant' => true,
                    'created_by' => 1,
                ]);
                $roles->saveOrFail($role);
            }

            $roleIds[$roleName] = (int)$role->id;

            foreach ($permissionNames as $permissionName) {
                if (!isset($permissionIds[$permissionName])) {
                    continue;
                }

                $exists = $rolesPermissions->find()
                    ->where([
                        'role_id' => $role->id,
                        'permission_id' => $permissionIds[$permissionName],
                    ])
                    ->first();
                if ($exists) {
                    continue;
                }

                $rolesPermissions->saveOrFail($rolesPermissions->newEntity([
                    'role_id' => $role->id,
                    'permission_id' => $permissionIds[$permissionName],
                    'created_by' => 1,
                    'created' => DateTime::now(),
                ]));
            }
        }

        $officeConfigs = [
            [
                'name' => 'Golden Staff',
                'role' => 'Golden Staff',
                'term_length' => 24,
            ],
            [
                'name' => 'Stable Scroll',
                'role' => 'Stable Scroll',
                'term_length' => 24,
            ],
            [
                'name' => 'Court Herald',
                'role' => 'Court Herald',
                'term_length' => 24,
            ],
        ];

        foreach ($officeConfigs as $config) {
            $existing = $offices->find()->where(['name' => $config['name']])->first();
            if ($existing) {
                continue;
            }

            $office = $offices->newEntity([
                'name' => $config['name'],
                'department_id' => $nobility->id,
                'requires_warrant' => true,
                'required_office' => false,
                'can_skip_report' => false,
                'only_one_per_branch' => true,
                'grants_role_id' => $roleIds[$config['role']] ?? null,
                'term_length' => $config['term_length'],
                'applicable_branch_types' => '"Kingdom"',
                'created_by' => 1,
            ]);
            $offices->saveOrFail($office);
        }
    }
}
