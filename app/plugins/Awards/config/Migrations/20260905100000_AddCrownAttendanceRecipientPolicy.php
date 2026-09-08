<?php
declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/** Give existing crown-field recipients the equivalent consent-bound attendance grant. */
class AddCrownAttendanceRecipientPolicy extends BaseMigration
{
    /** Copy existing crown recipients to the consent-aware attendance policy. */
    public function up(): void
    {
        $table = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $source = $table->find()->where([
            'policy_class' => 'Awards\\Policy\\BestowalPolicy',
            'policy_method' => 'canAccessCrownFields',
        ])->all();
        foreach ($source as $policy) {
            $data = [
                'permission_id' => $policy->permission_id,
                'policy_class' => 'App\\Policy\\GatheringAttendancePolicy',
                'policy_method' => 'canViewCrown',
            ];
            if (!$table->exists($data)) {
                $table->saveOrFail($table->newEntity($data));
            }
        }
    }

    /** Remove the added attendance mapping. */
    public function down(): void
    {
        TableRegistry::getTableLocator()->get('PermissionPolicies')->deleteAll([
            'policy_class' => 'App\\Policy\\GatheringAttendancePolicy',
            'policy_method' => 'canViewCrown',
        ]);
    }
}
