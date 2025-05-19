<?php

declare(strict_types=1);

use Migrations\BaseSeed;
require_once __DIR__ . '/Lib/SeedHelpers.php';

/**
 * DevLoadPoliciesSeed
 *
 * Assigns all application policies to the 'Can Do All But Is Not A Super User' permission.
 */
class DevLoadPoliciesSeed extends BaseSeed
{
    /**
     * Run Method.
     *
     * @return void
     */
    public function run(): void
    {
        // Get the permission ID for 'Can Do All But Is Not A Super User'
        $permissionId = SeedHelpers::getPermissionId('Can Do All But Is Not A Super User');
        // Get all application policies (requires plugins to be initialized)
        $policies = \App\KMP\PermissionsLoader::getApplicationPolicies();
        $policyData = [];
        foreach ($policies as $policyClass => $methods) {
            foreach ($methods as $method) {
                $policyData[] = [
                    'permission_id' => $permissionId,
                    'policy_class' => $policyClass,
                    'policy_method' => $method,
                ];
            }
        }
        if (!empty($policyData)) {
            $permissionPoliciesTable = \Cake\ORM\TableRegistry::getTableLocator()->get('PermissionPolicies');
            foreach ($policyData as $row) {
                $entity = $permissionPoliciesTable->newEntity($row);
                $permissionPoliciesTable->saveOrFail($entity);
            }
        }
    }
}
