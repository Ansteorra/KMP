<?php
declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Map bestowal to-do template admin actions to the existing "Can Manage Awards"
 * permission.
 *
 * The bestowal to-do templates replace the old Bestowal Statuses / States admin
 * configuration, which were already governed by "Can Manage Awards"; the
 * replacement is gated the same way so existing Awards administrators retain
 * access without a new role assignment. Per-check "who can complete it" gating
 * is enforced separately by each to-do item's assignee configuration.
 */
class MapBestowalTodoTemplatePolicies extends BaseMigration
{
    private const PERMISSION_NAME = 'Can Manage Awards';

    private const ENTITY_POLICY_CLASS = 'Awards\\Policy\\BestowalTodoTemplatePolicy';

    private const TABLE_POLICY_CLASS = 'Awards\\Policy\\BestowalTodoTemplatesTablePolicy';

    private const ENTITY_METHODS = [
        'canIndex',
        'canView',
        'canEdit',
        'canDelete',
    ];

    private const TABLE_METHODS = [
        'canIndex',
        'canAdd',
        'canGridData',
    ];

    /**
     * @return void
     */
    public function up(): void
    {
        $this->mapMethods(self::ENTITY_POLICY_CLASS, self::ENTITY_METHODS);
        $this->mapMethods(self::TABLE_POLICY_CLASS, self::TABLE_METHODS);
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permission = $permissions->find()
            ->where(['name' => self::PERMISSION_NAME])
            ->first();
        if ($permission === null) {
            return;
        }

        $permissionPolicies->deleteAll([
            'permission_id' => $permission->id,
            'policy_class IN' => [self::ENTITY_POLICY_CLASS, self::TABLE_POLICY_CLASS],
        ]);
    }

    /**
     * @param string $policyClass Policy class.
     * @param array<int, string> $policyMethods Policy methods.
     * @return void
     */
    private function mapMethods(string $policyClass, array $policyMethods): void
    {
        $permissions = TableRegistry::getTableLocator()->get('Permissions');
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permission = $permissions->find()
            ->where(['name' => self::PERMISSION_NAME])
            ->first();
        if ($permission === null) {
            return;
        }

        foreach ($policyMethods as $policyMethod) {
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

            $permissionPolicies->saveOrFail($permissionPolicies->newEntity([
                'permission_id' => $permission->id,
                'policy_class' => $policyClass,
                'policy_method' => $policyMethod,
            ]));
        }
    }
}
