<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\Permission;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use RuntimeException;

/** Configure activity-only Principality access without changing existing domain permissions. */
class PrincipalityActivityAccessService
{
    /** @return void */
    public function ensure(): void
    {
        $tables = TableRegistry::getTableLocator();
        $permissions = $tables->get('Permissions');
        $permissions->getConnection()->transactional(function () use ($tables, $permissions): void {
            $creator = $tables->get('Members')->find()->select(['id'])->orderBy(['id' => 'ASC'])->first();
            if ($creator === null) {
                throw new RuntimeException('An existing member is required to configure Principality activities.');
            }
            $definitions = [
                'Can Manage Coronet Presence' => [
                    'sources' => ['Principality Award Schedule Management'],
                    'activities' => ['activity_type' => 'Royal Progress', 'OR' => [
                        'name' => 'Coronet Presence', 'name LIKE' => '% Coronet Presence',
                    ]],
                ],
                'Can Manage Principality Court' => [
                    'sources' => ['Principality Award Schedule Management', 'Principality Court Management'],
                    'activities' => ['activity_type' => 'Court', 'name' => 'Principality Court'],
                ],
            ];
            foreach ($definitions as $name => $definition) {
                $permission = $permissions->find('withTrashed')->where(['name' => $name])->first();
                if (
                    $permission !== null && ($permission->deleted !== null || $permission->is_super_user
                    || $permission->scoping_rule !== Permission::SCOPE_BRANCH_AND_CHILDREN
                    || !$permission->require_active_membership || !$permission->requires_warrant)
                ) {
                    throw new RuntimeException('Resolve deleted or unsafe ' . $name . ' permission before setup.');
                }
                if ($permission === null) {
                    $permission = $permissions->newEntity([
                        'name' => $name, 'scoping_rule' => Permission::SCOPE_BRANCH_AND_CHILDREN,
                        'require_active_membership' => true, 'requires_warrant' => true,
                        'require_active_background_check' => false, 'require_min_age' => 0,
                        'is_system' => true, 'is_super_user' => false,
                    ]);
                    $permission->set('created', DateTime::now());
                    $permission->set('created_by', $creator->id);
                    $permission->set('modified_by', $creator->id);
                    $permissions->saveOrFail($permission);
                }
                $grants = $tables->get('RolesPermissions');
                $roleIds = $grants->find()->select(['role_id'])->innerJoinWith('Permissions')
                    ->innerJoinWith('Roles')->where(['Permissions.name IN' => $definition['sources']])
                    ->distinct()->all()->extract('role_id');
                foreach ($roleIds as $roleId) {
                    $conditions = ['role_id' => $roleId, 'permission_id' => $permission->id];
                    if (!$grants->exists($conditions)) {
                        $grants->saveOrFail($grants->newEntity($conditions + [
                            'created' => DateTime::now(), 'created_by' => $creator->id,
                        ]));
                    }
                }
                $rules = $tables->get('GatheringActivityAssignmentPermissions');
                foreach ($tables->get('GatheringActivities')->find()->where($definition['activities']) as $activity) {
                    $conditions = ['gathering_activity_id' => $activity->id, 'permission_id' => $permission->id];
                    if (!$rules->exists($conditions)) {
                        $rules->saveOrFail($rules->newEntity($conditions));
                    }
                }
            }
        });
    }
}
