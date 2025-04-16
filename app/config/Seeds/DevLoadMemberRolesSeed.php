<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;

/**
 * MemberRoles seed.
 */
class DevLoadMemberRolesSeed extends BaseSeed
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
        $data = $this->getData();
        $table = $this->table('member_roles');
        $options = $table->getAdapter()->getOptions();
        $options['identity_insert'] = true;
        $table->getAdapter()->setOptions($options);
        $table->insert($data)->save();
    }

    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        return [
            [
                'id' => 200,
                'member_id' => 200,
                'role_id' => 201,
                'expires_on' => NULL,
                'start_on' => '2024-05-30 01:22:55',
                'approver_id' => 1,
                'entity_type' => 'Direct Grant',
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
                'entity_type' => 'Direct Grant',
                'created' => DateTime::now(),
                'created_by' => '1'
            ],
            [
                'id' => 202,
                'member_id' => 202,
                'role_id' => 207,
                'expires_on' => NULL,
                'start_on' => '2024-05-30 12:54:12',
                'approver_id' => 1,
                'entity_type' => 'Direct Grant',
                'created' => DateTime::now(),
                'created_by' => '1',
                'branch_id' => '5'
            ],
        ];
    }
}
