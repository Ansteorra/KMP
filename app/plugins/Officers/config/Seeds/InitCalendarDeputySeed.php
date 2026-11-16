<?php
declare(strict_types=1);

use App\Model\Entity\Permission;
use App\Policy\GatheringPolicy;
use App\Policy\GatheringsTablePolicy;
use Cake\ORM\TableRegistry;
use Migrations\BaseSeed;

/** Ensure the Calendar Deputy office and its tenant-wide event preparation role. */
class InitCalendarDeputySeed extends BaseSeed
{
    public const ROLE = 'Calendar Deputy';

    public const MANAGE = 'Can Manage All Gatherings';

    public const PUBLISH = 'Can Publish Gatherings to Kingdom Calendar';

    /** @return void */
    public function run(): void
    {
        $tables = TableRegistry::getTableLocator();
        $roles = $tables->get('Roles');
        $connection = $roles->getConnection();
        $connection->enableSavePoints();
        $connection->transactional(function () use ($tables, $roles): void {
            $creator = $tables->get('Members')->find()->select(['id'])->orderByAsc('id')->firstOrFail();
            $role = $roles->find('withTrashed')->where(['name' => self::ROLE])->first();
            if ($role !== null && $role->deleted !== null) {
                throw new RuntimeException('Resolve the deleted Calendar Deputy role before seeding.');
            }
            if ($role === null) {
                $role = $roles->saveOrFail($roles->newEntity([
                    'name' => self::ROLE, 'is_system' => false, 'created_by' => $creator->id,
                ]));
            }

            $permissions = $tables->get('Permissions');
            $policies = $tables->get('PermissionPolicies');
            $grants = $tables->get('RolesPermissions');
            $bindings = [
                self::MANAGE => ['canAdd', 'canEdit', 'canView', 'canIndex', 'canGridData',
                    'canCalendar', 'canCalendarGridData', 'canQuickView'],
                self::PUBLISH => ['canPublish'],
            ];
            foreach ($bindings as $name => $methods) {
                $permission = $permissions->find('withTrashed')->where(['name' => $name])->first();
                if (
                    $permission !== null
                    && ($permission->deleted !== null || $permission->is_super_user
                        || $permission->scoping_rule !== Permission::SCOPE_GLOBAL)
                ) {
                    throw new RuntimeException(
                        'Resolve the deleted, superuser, or non-global ' . $name . ' permission.',
                    );
                }
                if ($permission === null) {
                    $permission = $permissions->newEntity([
                        'name' => $name, 'scoping_rule' => Permission::SCOPE_GLOBAL,
                        'require_active_membership' => true, 'require_active_background_check' => false,
                        'require_min_age' => 0, 'requires_warrant' => $name === self::MANAGE,
                        'is_system' => true, 'is_super_user' => false,
                    ]);
                    $permission->set('created_by', $creator->id);
                    $permission->set('modified_by', $creator->id);
                    $permissions->saveOrFail($permission);
                }
                foreach ($methods as $method) {
                    $conditions = [
                        'permission_id' => $permission->id,
                        'policy_class' => GatheringPolicy::class, 'policy_method' => $method,
                    ];
                    if (!$policies->exists($conditions)) {
                        $policies->saveOrFail($policies->newEntity($conditions));
                    }
                }
                if ($name === self::MANAGE) {
                    $conditions = [
                        'permission_id' => $permission->id,
                        'policy_class' => GatheringsTablePolicy::class, 'policy_method' => 'canAdd',
                    ];
                    if (!$policies->exists($conditions)) {
                        $policies->saveOrFail($policies->newEntity($conditions));
                    }
                }
                $conditions = ['role_id' => $role->id, 'permission_id' => $permission->id];
                if (!$grants->exists($conditions)) {
                    $grants->saveOrFail($grants->newEntity($conditions + ['created_by' => $creator->id]));
                }
            }

            $offices = $tables->get('Officers.Offices');
            $parent = $offices->find('withTrashed')->where(['name' => 'Kingdom Seneschal'])->first();
            if ($parent !== null && $parent->deleted !== null) {
                throw new RuntimeException('Resolve the deleted Kingdom Seneschal office before seeding.');
            }
            if ($parent === null) {
                $departments = $tables->get('Officers.Departments');
                $department = $departments->find()->where(['name' => 'Seneschal'])->first();
                if ($department !== null && $department->deleted !== null) {
                    throw new RuntimeException('Resolve the deleted Seneschal department before seeding.');
                }
                if ($department === null) {
                    $department = $departments->saveOrFail($departments->newEntity(['name' => 'Seneschal']));
                }
                $parent = $offices->saveOrFail($offices->newEntity([
                    'name' => 'Kingdom Seneschal', 'department_id' => $department->id,
                    'requires_warrant' => true, 'only_one_per_branch' => true,
                    'term_length' => 24, 'branch_types' => ['Kingdom'],
                ]));
            }
            $office = $offices->find('withTrashed')->where(['name' => self::ROLE])->first();
            if ($office !== null && $office->deleted !== null) {
                throw new RuntimeException('Resolve the deleted Calendar Deputy office before seeding.');
            }
            if ($office === null) {
                $office = $offices->newEntity([
                    'name' => self::ROLE, 'department_id' => $parent->department_id,
                    'requires_warrant' => true, 'required_office' => false, 'only_one_per_branch' => true,
                    'term_length' => 24, 'branch_types' => ['Kingdom'], 'deputy_to_id' => $parent->id,
                    'grants_role_id' => $role->id,
                ]);
                $office->set('created_by', $creator->id);
                $offices->saveOrFail($office);
            } elseif (
                (int)$office->deputy_to_id !== (int)$parent->id
                || ($office->grants_role_id !== null && (int)$office->grants_role_id !== (int)$role->id)
            ) {
                throw new RuntimeException(
                    'Resolve the existing Calendar Deputy office hierarchy or role before seeding.',
                );
            } elseif ($office->grants_role_id === null) {
                $office->grants_role_id = $role->id;
                $offices->saveOrFail($office);
            }
        });
    }
}
