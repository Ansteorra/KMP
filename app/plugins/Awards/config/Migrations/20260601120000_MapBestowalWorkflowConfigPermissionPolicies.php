<?php

declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Wire bestowal workflow config admin policies to Can Manage Awards via permission_policies.
 */
class MapBestowalWorkflowConfigPermissionPolicies extends BaseMigration
{
    private const PERMISSION_NAME = 'Can Manage Awards';

    private const POLICY_CLASSES = [
        'Awards\\Policy\\BestowalStatusPolicy',
        'Awards\\Policy\\BestowalStatusesTablePolicy',
        'Awards\\Policy\\BestowalStatePolicy',
        'Awards\\Policy\\BestowalStatesTablePolicy',
    ];

    private const POLICY_METHODS = [
        'canIndex',
        'canGridData',
        'canView',
        'canAdd',
        'canEdit',
        'canDelete',
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');

        $permission = $permissions->find()->where(['name' => self::PERMISSION_NAME])->first();
        if ($permission === null) {
            return;
        }

        foreach (self::POLICY_CLASSES as $policyClass) {
            foreach (self::POLICY_METHODS as $policyMethod) {
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
    public function down(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');

        $permission = $permissions->find()->where(['name' => self::PERMISSION_NAME])->first();
        if ($permission === null) {
            return;
        }

        foreach (self::POLICY_CLASSES as $policyClass) {
            $permissionPolicies->deleteAll([
                'permission_id' => $permission->id,
                'policy_class' => $policyClass,
            ]);
        }
    }
}
