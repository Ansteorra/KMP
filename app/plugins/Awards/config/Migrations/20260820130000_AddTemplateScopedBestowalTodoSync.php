<?php

declare(strict_types=1);

use Cake\ORM\TableRegistry;
use Migrations\BaseMigration;

/**
 * Track the bestowal to-do template snapshot and authorize template-scoped synchronization.
 */
class AddTemplateScopedBestowalTodoSync extends BaseMigration
{
    private const TEMPLATE_POLICY = 'Awards\\Policy\\BestowalTodoTemplatePolicy';

    private const POLICY_METHOD = 'canSyncOpenBestowals';

    private const PERMISSION_NAMES = [
        'Can Synchronize Award Workflows',
        'Can Manage Bestowal To-Do Templates',
    ];

    /**
     * Add the signature and entity-policy mappings.
     */
    public function up(): void
    {
        $this->table('awards_bestowals')
            ->addColumn('todo_template_signature', 'string', [
                'after' => 'source_approval_run_id',
                'default' => null,
                'limit' => 64,
                'null' => true,
            ])
            ->addIndex(['todo_template_signature'], [
                'name' => 'idx_awards_bestowals_todo_template_signature',
            ])
            ->update();

        $permissions = TableRegistry::getTableLocator()->get('Permissions')->find()
            ->select(['id'])
            ->where(['name IN' => self::PERMISSION_NAMES])
            ->all();
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        foreach ($permissions as $permission) {
            $conditions = [
                'permission_id' => (int)$permission->id,
                'policy_class' => self::TEMPLATE_POLICY,
                'policy_method' => self::POLICY_METHOD,
            ];
            if (!$permissionPolicies->exists($conditions)) {
                $permissionPolicies->saveOrFail($permissionPolicies->newEntity($conditions));
            }
        }
    }

    /**
     * Remove the signature and entity-policy mappings.
     */
    public function down(): void
    {
        $permissionPolicies = TableRegistry::getTableLocator()->get('PermissionPolicies');
        $permissionIds = TableRegistry::getTableLocator()->get('Permissions')->find()
            ->select(['id'])
            ->where(['name IN' => self::PERMISSION_NAMES])
            ->all()
            ->extract('id')
            ->map(static fn($id): int => (int)$id)
            ->toList();
        if ($permissionIds !== []) {
            $permissionPolicies->deleteAll([
                'permission_id IN' => $permissionIds,
                'policy_class' => self::TEMPLATE_POLICY,
                'policy_method' => self::POLICY_METHOD,
            ]);
        }

        $this->table('awards_bestowals')
            ->removeIndexByName('idx_awards_bestowals_todo_template_signature')
            ->removeColumn('todo_template_signature')
            ->update();
    }
}
