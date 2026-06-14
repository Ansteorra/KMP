<?php
declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

class MapBestowalActionPolicies extends BaseMigration
{
    private const POLICY_CLASS = 'Awards\\Policy\\BestowalPolicy';

    private const VIEW_METHODS = [
        'canGatheringBestowalsGridData',
    ];

    private const MANAGE_METHODS = [
        'canUpdateState',
        'canUpdateStates',
        'canTurboEditForm',
        'canTurboBulkEditForm',
        'canCourtSlotsForGathering',
        'canGatheringsForBestowalAutoComplete',
        'canGatheringsForBestowalBulkAutoComplete',
        'canCancel',
        'canAdHoc',
    ];

    private const STALE_POLICY_CLASSES = [
        'Awards\\Policy\\BestowalsControllerPolicy',
    ];

    private const STALE_TABLE_METHODS = [
        'canEdit',
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->removeStalePolicyRows();
        $this->mapMethods('Can View Bestowals', self::VIEW_METHODS);
        $this->mapMethods('Can Manage Bestowals', self::MANAGE_METHODS);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permissionNames = ['Can View Bestowals', 'Can Manage Bestowals'];

        $permissionIds = $permissions->find()
            ->select(['id'])
            ->where(['name IN' => $permissionNames])
            ->all()
            ->extract('id')
            ->toList();
        if ($permissionIds === []) {
            return;
        }

        $permissionPolicies->deleteAll([
            'permission_id IN' => $permissionIds,
            'policy_class' => self::POLICY_CLASS,
            'policy_method IN' => array_merge(self::VIEW_METHODS, self::MANAGE_METHODS),
        ]);
    }

    /**
     * @param string $permissionName Permission name
     * @param array<int, string> $policyMethods Policy methods to map
     * @return void
     */
    private function mapMethods(string $permissionName, array $policyMethods): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permission = $permissions->find()
            ->where(['name' => $permissionName])
            ->first();
        if ($permission === null) {
            return;
        }

        foreach ($policyMethods as $policyMethod) {
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

            $policy = $permissionPolicies->newEntity([
                'permission_id' => $permission->id,
                'policy_class' => self::POLICY_CLASS,
                'policy_method' => $policyMethod,
            ]);
            $permissionPolicies->saveOrFail($policy);
        }
    }

    /**
     * @return void
     */
    private function removeStalePolicyRows(): void
    {
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permissionPolicies->deleteAll(['policy_class IN' => self::STALE_POLICY_CLASSES]);
        $permissionPolicies->deleteAll([
            'policy_class' => 'Awards\\Policy\\BestowalsTablePolicy',
            'policy_method IN' => self::STALE_TABLE_METHODS,
        ]);
    }
}
