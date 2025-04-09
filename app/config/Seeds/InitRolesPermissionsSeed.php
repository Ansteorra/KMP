<?php

declare(strict_types=1);


use Migrations\BaseSeed;

/**
 * RolesPermissions seed.
 */
class InitRolesPermissionsSeed extends BaseSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     *
     * @return void
     */
    public function run(): void
    {
        $data = [
            [
                'permission_id' => 1,
                'role_id' => 1,
                'created_by' => 1,
            ]
        ];

        $table = $this->table('roles_permissions');
        $table->insert($data)->save();
    }
}