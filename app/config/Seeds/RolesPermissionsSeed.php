<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * RolesPermissions seed.
 */
class RolesPermissionsSeed extends AbstractSeed
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
            ],
            [
                'permission_id' => 2,
                'role_id' => 1,
            ],
            [
                'permission_id' => 3,
                'role_id' => 1,
            ],
            [
                'permission_id' => 10,
                'role_id' => 3,
            ],
            [
                'permission_id' => 11,
                'role_id' => 3,
            ],
            [
                'permission_id' => 12,
                'role_id' => 3,
            ],
            [
                'permission_id' => 12,
                'role_id' => 4,
            ],
            [
                'permission_id' => 12,
                'role_id' => 6,
            ],
            [
                'permission_id' => 13,
                'role_id' => 3,
            ],
            [
                'permission_id' => 13,
                'role_id' => 4,
            ],
            [
                'permission_id' => 13,
                'role_id' => 6,
            ],
            [
                'permission_id' => 19,
                'role_id' => 3,
            ],
            [
                'permission_id' => 19,
                'role_id' => 4,
            ],
        ];

        $table = $this->table('roles_permissions');
        $table->insert($data)->save();
    }
}
