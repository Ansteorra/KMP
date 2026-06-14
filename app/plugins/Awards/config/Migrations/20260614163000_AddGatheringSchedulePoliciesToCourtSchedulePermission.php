<?php

declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Grants court schedule managers narrow gathering schedule create/edit-own access.
 */
class AddGatheringSchedulePoliciesToCourtSchedulePermission extends BaseMigration
{
    private const PERMISSION_NAME = 'Can Manage Court Schedule';

    private const POLICY_MAPPINGS = [
        ['App\\Policy\\GatheringPolicy', 'canCreateScheduledActivity'],
        ['App\\Policy\\GatheringPolicy', 'canEditScheduledActivity'],
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->addPolicyMappings();
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

        foreach (self::POLICY_MAPPINGS as [$policyClass, $policyMethod]) {
            $permissionPolicies->deleteAll([
                'permission_id' => $permission->id,
                'policy_class' => $policyClass,
                'policy_method' => $policyMethod,
            ]);
        }
    }

    /**
     * @return void
     */
    private function addPolicyMappings(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permission = $permissions->find()->where(['name' => self::PERMISSION_NAME])->first();
        if ($permission === null) {
            return;
        }

        foreach (self::POLICY_MAPPINGS as [$policyClass, $policyMethod]) {
            $exists = $permissionPolicies->find()
                ->where([
                    'permission_id' => $permission->id,
                    'policy_class' => $policyClass,
                    'policy_method' => $policyMethod,
                ])
                ->first();
            if ($exists !== null) {
                continue;
            }

            $policy = $permissionPolicies->newEntity([
                'permission_id' => $permission->id,
                'policy_class' => $policyClass,
                'policy_method' => $policyMethod,
            ]);
            $permissionPolicies->saveOrFail($policy);
        }
    }
}
