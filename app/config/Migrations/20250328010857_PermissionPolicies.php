<?php

declare(strict_types=1);

use Migrations\BaseMigration;

class PermissionPolicies extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     * @return void
     */
    public function change(): void
    {
        $this->table('permission_policies')
            ->addColumn('permission_id', 'integer', [
                'default' => null,
                'limit' => 11,
                'null' => false,
            ])
            ->addColumn('policy_class', "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addColumn('policy_method', "string", [
                "default" => null,
                "limit" => 255,
                "null" => false,
            ])
            ->addForeignKey('permission_id', 'permissions', 'id', [
                'delete' => 'CASCADE',
                'update' => 'NO_ACTION',
            ])
            ->create();
    }
}