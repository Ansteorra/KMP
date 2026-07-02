<?php

declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Map bulk gathering assignment to the existing court schedule management permission.
 */
class MapBestowalBulkGatheringPolicy extends BaseMigration
{
    private const PERMISSION_NAMES = [
        'Can Manage Court Schedule',
        'Crown Award Schedule Management',
        'Principality Award Schedule Management',
        'Baronial Award Schedule Management',
    ];

    private const POLICY_CLASS = 'Awards\\Policy\\BestowalPolicy';

    private const POLICY_METHODS = [
        'canManageCourtSchedule',
        'canBulkAssignGathering',
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permissionRows = $permissions->find()
            ->where(['name IN' => self::PERMISSION_NAMES])
            ->all();

        foreach ($permissionRows as $permission) {
            foreach (self::POLICY_METHODS as $policyMethod) {
                $exists = $permissionPolicies->find()
                    ->where([
                        'permission_id' => $permission->id,
                        'policy_class' => self::POLICY_CLASS,
                        'policy_method' => $policyMethod,
                    ])
                    ->first();
                if ($exists !== null) {
                    continue;
                }

                $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                    'permission_id' => $permission->id,
                    'policy_class' => self::POLICY_CLASS,
                    'policy_method' => $policyMethod,
                ]));
            }
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permissionIds = $permissions->find()
            ->select(['id'])
            ->where(['name IN' => self::PERMISSION_NAMES])
            ->all()
            ->extract('id')
            ->toList();
        if ($permissionIds === []) {
            return;
        }

        $permissionPolicies->deleteAll([
            'permission_id IN' => $permissionIds,
            'policy_class' => self::POLICY_CLASS,
            'policy_method IN' => self::POLICY_METHODS,
        ]);
    }
}
