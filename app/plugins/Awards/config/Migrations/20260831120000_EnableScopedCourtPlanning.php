<?php
declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Allow scoped Court managers to prepare gathering activities and schedules.
 */
class EnableScopedCourtPlanning extends BaseMigration
{
    private const PERMISSION_NAMES = [
        'Crown Court Management',
        'Principality Court Management',
        'Baronial Court Management',
        'Can Manage Court Schedule',
    ];

    private const POLICY_CLASS = 'App\\Policy\\GatheringPolicy';

    private const POLICY_METHODS = [
        'canAddCourtActivity',
        'canCreateScheduledActivity',
        'canEditScheduledActivity',
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');

        foreach (self::PERMISSION_NAMES as $permissionName) {
            $permission = $permissions->find()->where(['name' => $permissionName])->first();
            if ($permission === null) {
                continue;
            }

            foreach (self::POLICY_METHODS as $policyMethod) {
                $conditions = [
                    'permission_id' => (int)$permission->id,
                    'policy_class' => self::POLICY_CLASS,
                    'policy_method' => $policyMethod,
                ];
                if ($permissionPolicies->exists($conditions)) {
                    continue;
                }

                $permissionPolicies->saveOrFail($permissionPolicies->newEntity($conditions));
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
        $permissionIdsByName = $permissions->find()
            ->select(['id', 'name'])
            ->where(['name IN' => self::PERMISSION_NAMES])
            ->all()
            ->combine('name', 'id')
            ->map(static fn($id): int => (int)$id)
            ->toArray();

        if ($permissionIdsByName === []) {
            return;
        }

        $permissionPolicies->deleteAll([
            'permission_id IN' => array_values($permissionIdsByName),
            'policy_class' => self::POLICY_CLASS,
            'policy_method' => 'canAddCourtActivity',
        ]);

        unset($permissionIdsByName['Can Manage Court Schedule']);
        if ($permissionIdsByName !== []) {
            $permissionPolicies->deleteAll([
                'permission_id IN' => array_values($permissionIdsByName),
                'policy_class' => self::POLICY_CLASS,
                'policy_method IN' => ['canCreateScheduledActivity', 'canEditScheduledActivity'],
            ]);
        }
    }
}
