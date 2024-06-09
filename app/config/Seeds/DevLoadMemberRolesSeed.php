<?php

declare(strict_types=1);

use Migrations\AbstractSeed;
use Cake\I18n\DateTime;

/**
 * MemberRoles seed.
 */
class DevLoadMemberRolesSeed extends AbstractSeed
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
                'id' => 2,
                'member_id' => 200,
                'role_id' => 201,
                'expires_on' => NULL,
                'start_on' => '2024-05-30 01:22:55',
                'approver_id' => 1,
                'granting_model' => 'Direct Grant',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 201,
                'member_id' => 201,
                'role_id' => 202,
                'expires_on' => NULL,
                'start_on' => '2024-05-30 12:54:12',
                'approver_id' => 1,
                'granting_model' => 'Direct Grant',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
        ];

        $table = $this->table('member_roles');
        $table->insert($data)->save();
    }
}