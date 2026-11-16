<?php
declare(strict_types=1);

use App\Model\Entity\Permission;
use App\Policy\GatheringPolicy;
use App\Policy\GatheringsTablePolicy;
use Cake\ORM\TableRegistry;
use Migrations\BaseSeed;
use Waivers\Policy\GatheringWaiverPolicy;
use Waivers\Policy\GatheringWaiversControllerPolicy;
use Waivers\Policy\GatheringWaiversTablePolicy;

/** Provision scoped event preparation and waiver uploads without a personal warrant. */
class InitLocalCalendarDeputySeed extends BaseSeed
{
    public const ROLE = 'Local Calendar Deputy';

    public const MANAGE = 'Manage Scoped Gatherings Without Warrant';

    public const UPLOAD = 'Upload Scoped Gathering Waivers Without Warrant';

    /** @return void */
    public function run(): void
    {
        $tables = TableRegistry::getTableLocator();
        $connection = $tables->get('Roles')->getConnection();
        $connection->enableSavePoints();
        $connection->transactional(function () use ($tables): void {
            $creator = $tables->get('Members')->find()->select(['id'])->orderByAsc('id')->firstOrFail()->id;
            $offices = $tables->get('Officers.Offices');
            $parent = $offices->find('withTrashed')->where(['name' => 'Local Seneschal'])->first();
            if ($parent !== null && $parent->deleted !== null) {
                throw new RuntimeException('Resolve the deleted Local Seneschal office before seeding.');
            }
            if ($parent === null) {
                $departments = $tables->get('Officers.Departments');
                $department = $departments->find()->where(['name' => 'Seneschal'])->first();
                if ($department !== null && $department->deleted !== null) {
                    throw new RuntimeException('Resolve the deleted Seneschal department before seeding.');
                }
                $department ??= $departments->saveOrFail($departments->newEntity(['name' => 'Seneschal']));
                $regional = $offices->find()->where(['name' => 'Regional Seneschal'])->first();
                $parent = $offices->saveOrFail($offices->newEntity([
                    'name' => 'Local Seneschal', 'department_id' => $department->id,
                    'reports_to_id' => $regional?->id, 'requires_warrant' => true,
                    'only_one_per_branch' => true, 'term_length' => 24, 'branch_types' => ['Local Group'],
                ]));
            }
            $roles = $tables->get('Roles');
            $role = $roles->find('withTrashed')->where(['name' => self::ROLE])->first();
            if ($role !== null && $role->deleted !== null) {
                throw new RuntimeException('Resolve the deleted Local Calendar Deputy role before seeding.');
            }
            $role ??= $roles->saveOrFail($roles->newEntity([
                'name' => self::ROLE, 'is_system' => false, 'created_by' => $creator,
            ]));
            $permissions = $tables->get('Permissions');
            $policies = $tables->get('PermissionPolicies');
            $grants = $tables->get('RolesPermissions');
            $bindings = [
                self::MANAGE => [
                    GatheringPolicy::class => ['canAdd', 'canEdit', 'canView', 'canIndex', 'canGridData',
                        'canCalendar', 'canCalendarGridData', 'canQuickView'],
                    GatheringsTablePolicy::class => ['canAdd'],
                ],
                self::UPLOAD => [
                    GatheringWaiverPolicy::class => [
                        'canNeedingWaivers', 'canUploadWaivers', 'canViewGatheringWaivers',
                    ],
                    GatheringWaiversControllerPolicy::class => ['canNeedingWaivers', 'canUpload',
                        'canMobileSelectGathering', 'canMobileUpload'],
                    GatheringWaiversTablePolicy::class => ['canNeedingWaivers'],
                ],
            ];
            $permissionIds = [];
            foreach ($bindings as $name => $classes) {
                $permission = $permissions->find('withTrashed')->where(['name' => $name])->first();
                if (
                    $permission !== null
                    && ($permission->deleted !== null || $permission->is_super_user || $permission->requires_warrant
                        || !$permission->require_active_membership
                        || $permission->scoping_rule !== Permission::SCOPE_BRANCH_AND_CHILDREN)
                ) {
                    throw new RuntimeException('Resolve the incompatible scoped permission: ' . $name);
                }
                if ($permission === null) {
                    $permission = $permissions->newEntity([
                        'name' => $name, 'scoping_rule' => Permission::SCOPE_BRANCH_AND_CHILDREN,
                        'require_active_membership' => true, 'require_active_background_check' => false,
                        'require_min_age' => 0, 'requires_warrant' => false,
                        'is_system' => true, 'is_super_user' => false,
                    ]);
                    $permission->set('created_by', $creator);
                    $permissions->saveOrFail($permission);
                }
                foreach ($policies->find()->where(['permission_id' => $permission->id]) as $policy) {
                    if (!in_array($policy->policy_method, $classes[$policy->policy_class] ?? [], true)) {
                        throw new RuntimeException('Resolve unexpected Local Calendar Deputy policy bindings.');
                    }
                }
                foreach ($classes as $class => $methods) {
                    foreach ($methods as $method) {
                        $conditions = ['permission_id' => $permission->id,
                            'policy_class' => $class, 'policy_method' => $method];
                        if (!$policies->exists($conditions)) {
                            $policies->saveOrFail($policies->newEntity($conditions));
                        }
                    }
                }
                $conditions = ['role_id' => $role->id, 'permission_id' => $permission->id];
                if (!$grants->exists($conditions)) {
                    $grants->saveOrFail($grants->newEntity($conditions + ['created_by' => $creator]));
                }
                $permissionIds[] = $permission->id;
            }
            if ($grants->exists(['role_id' => $role->id, 'permission_id NOT IN' => $permissionIds])) {
                throw new RuntimeException('Resolve unexpected Local Calendar Deputy role permissions.');
            }

            $office = $offices->find('withTrashed')->where(['name' => self::ROLE])->first();
            if ($office === null) {
                $office = $offices->newEntity([
                    'name' => self::ROLE, 'department_id' => $parent->department_id,
                    'deputy_to_id' => $parent->id, 'grants_role_id' => $role->id,
                    'requires_warrant' => false, 'required_office' => false, 'only_one_per_branch' => true,
                    'term_length' => 24, 'branch_types' => $parent->branch_types,
                ]);
                $office->set('created_by', $creator);
                $offices->saveOrFail($office);
            } elseif (
                $office->deleted !== null || $office->requires_warrant
                || (int)$office->deputy_to_id !== (int)$parent->id
                || (int)$office->department_id !== (int)$parent->department_id
                || ($office->grants_role_id !== null && (int)$office->grants_role_id !== (int)$role->id)
            ) {
                throw new RuntimeException('Resolve the incompatible Local Calendar Deputy office configuration.');
            } elseif ($office->grants_role_id === null) {
                $office->grants_role_id = $role->id;
                $offices->saveOrFail($office);
            }
        });
    }
}
