<?php
declare(strict_types=1);

use App\Model\Entity\Permission;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/** Give Crown scheduling roles activity-only authority at locally hosted events. */
class AddCrownPresencePermission extends BaseMigration
{
    /** @return void */
    public function up(): void
    {
        $tables = TableRegistry::getTableLocator();
        $permissions = $tables->get('Permissions');
        $permissions->getConnection()->transactional(function () use ($tables, $permissions): void {
            $creator = $tables->get('Members')->find()->select(['id'])->orderBy(['id' => 'ASC'])->first();
            if ($creator === null) {
                throw new RuntimeException('An existing member is required to configure Crown Presence.');
            }
            $permission = $permissions->find('withTrashed')->where(['name' => 'Can Manage Crown Presence'])->first();
            if ($permission !== null && $permission->deleted !== null) {
                throw new RuntimeException(
                    'Resolve the deleted Can Manage Crown Presence permission before migrating.',
                );
            }
            if ($permission === null) {
                $permission = $permissions->newEntity([
                    'name' => 'Can Manage Crown Presence',
                    'scoping_rule' => Permission::SCOPE_BRANCH_AND_CHILDREN,
                    'require_active_membership' => true,
                    'require_active_background_check' => false,
                    'require_min_age' => 0,
                    'requires_warrant' => true,
                    'is_system' => true,
                    'is_super_user' => false,
                ]);
                $permission->set('created', DateTime::now());
                $permission->set('created_by', $creator->id);
                $permission->set('modified_by', $creator->id);
                $permissions->saveOrFail($permission);
            }

            // Copy role membership, not policy mappings or the source permission's branch-only scope.
            $roleGrants = $tables->get('RolesPermissions');
            $roleIds = $roleGrants->find()->select(['role_id'])->innerJoinWith('Permissions')
                ->innerJoinWith('Roles')
                ->where(['Permissions.name' => 'Crown Award Schedule Management'])->all()->extract('role_id');
            foreach ($roleIds as $roleId) {
                $conditions = ['role_id' => $roleId, 'permission_id' => $permission->id];
                if (!$roleGrants->exists($conditions)) {
                    $roleGrants->saveOrFail($roleGrants->newEntity($conditions + [
                        'created' => DateTime::now(), 'created_by' => $creator->id,
                    ]));
                }
            }

            // Preserve tenant-specific activities and existing assignment rules.
            $rules = $tables->get('GatheringActivityAssignmentPermissions');
            $activities = $tables->get('GatheringActivities')->find()->where([
                'name' => 'Crown Presence', 'activity_type' => 'Royal Progress',
            ]);
            foreach ($activities as $activity) {
                $conditions = ['gathering_activity_id' => $activity->id, 'permission_id' => $permission->id];
                if (!$rules->exists($conditions)) {
                    $rules->saveOrFail($rules->newEntity($conditions));
                }
            }
        });
    }

    /** @return void */
    public function down(): void
    {
        throw new RuntimeException(
            'AddCrownPresencePermission is irreversible because administrators may have adopted these grants.',
        );
    }
}
