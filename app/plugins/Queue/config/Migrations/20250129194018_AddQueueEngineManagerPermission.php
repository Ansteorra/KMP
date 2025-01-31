<?php

declare(strict_types=1);

use Migrations\BaseMigration;
use Cake\ORM\TableRegistry;

class AddQueueEngineManagerPermission extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     * @return void
     */
    public function up(): void
    {
        $tbl = TableRegistry::getTableLocator()->get('Permissions');
        $perm = $tbl->newEntity([]);
        //$perm->id = 8;
        $perm->name = "Can Manage Queue Engine";
        $perm->require_active_membership = false;
        $perm->require_active_background_check = false;
        $perm->require_min_age = 0;
        $perm->is_system = true;
        $perm->is_super_user = false;
        $perm->requires_warrant = false;

        $tbl->save($perm);
    }

    public function down(): void
    {
        $tbl = TableRegistry::getTableLocator()->get('Permissions');
        $perm = $tbl->find()->where(['name' => 'Can Manage Queue Engine'])->first();

        if ($perm) {
            $tbl->delete($perm);
        }
    }
}