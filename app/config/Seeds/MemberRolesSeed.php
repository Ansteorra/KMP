<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * MemberRoles seed.
 */
class MemberRolesSeed extends AbstractSeed
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
                'id' => 1,
                'member_id' => 1,
                'role_id' => 1,
                'expires_on' => NULL,
                'start_on' => '2024-05-30 01:22:55',
                'approver_id' => 1,
            ],
            [
                'id' => 2,
                'member_id' => 2,
                'role_id' => 3,
                'expires_on' => NULL,
                'start_on' => '2024-05-30 01:22:55',
                'approver_id' => 1,
            ],
            [
                'id' => 3,
                'member_id' => 3,
                'role_id' => 4,
                'expires_on' => NULL,
                'start_on' => '2024-05-30 12:54:12',
                'approver_id' => 1,
            ],
        ];

        $table = $this->table('member_roles');
        $table->insert($data)->save();
    }
}
