<?php

declare(strict_types=1);


use Migrations\BaseSeed;
use Cake\I18n\DateTime;

/**
 * MemberRoles seed.
 */
class InitMemberRolesSeed extends BaseSeed
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
                'granting_model' => 'Direct Grant',
                'created' => DateTime::now(),
                'created_by' => '1',
            ]
        ];

        $table = $this->table('member_roles');
        $options = $table->getAdapter()->getOptions();
        $options['identity_insert'] = true;
        $table->getAdapter()->setOptions($options);
        $table->insert($data)->save();
    }
}
