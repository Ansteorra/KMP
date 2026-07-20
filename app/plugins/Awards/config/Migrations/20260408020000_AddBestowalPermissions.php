<?php

declare(strict_types=1);

use Migrations\BaseMigration;
use Cake\ORM\TableRegistry;

class AddBestowalPermissions extends BaseMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        $tbl = TableRegistry::getTableLocator()->get('Permissions');

        $permissions = [
            'Can View Bestowals',
            'Can Manage Bestowals',
            'Can Prepare Scrolls',
            'Can Manage Court Schedule',
        ];

        foreach ($permissions as $name) {
            $existing = $tbl->find()->where(['name' => $name])->first();
            if ($existing) {
                continue;
            }

            $perm = $tbl->newEntity([
                'name' => $name,
                'require_active_membership' => true,
                'require_active_background_check' => false,
                'require_min_age' => 0,
                'is_system' => true,
                'is_super_user' => false,
                'requires_warrant' => true,
            ]);
            $tbl->save($perm);
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        $tbl = TableRegistry::getTableLocator()->get('Permissions');
        $names = [
            'Can View Bestowals',
            'Can Manage Bestowals',
            'Can Prepare Scrolls',
            'Can Manage Court Schedule',
        ];

        foreach ($names as $name) {
            $perm = $tbl->find()->where(['name' => $name])->first();
            if ($perm) {
                $tbl->delete($perm);
            }
        }
    }
}
