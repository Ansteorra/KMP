<?php

declare(strict_types=1);

use Migrations\AbstractSeed;
use Cake\I18n\DateTime;

/**
 * Members seed.
 */
class DevLoadMembersSeed extends AbstractSeed
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
                'id' => 200,
                'motified' => DateTime::now(),
                'password' => '42f749ade7f9e195bf475f37a44cafcb',
                'sca_name' => 'Earl Realm',
                'first_name' => 'Kingdom',
                'middle_name' => '',
                'last_name' => 'Marshal',
                'street_address' => 'Fake Data',
                'city' => 'a city',
                'state' => 'TX',
                'zip' => '00000',
                'phone_number' => '',
                'email_address' => 'Earl@test.com',
                'membership_number' => '2345',
                'membership_expires_on' => '2030-03-31',
                'branch_id' => 4,
                'background_check_expires_on' => NULL,
                'status' => 'active',
                'password_token' => NULL,
                'password_token_expires_on' => NULL,
                'last_login' => NULL,
                'last_failed_login' => NULL,
                'failed_login_attempts' => NULL,
                'birth_month' => 4,
                'birth_year' => 1977,
                'deleted' => NULL,
                'created' => DateTime::now(),
            ],
            [
                'id' => 201,
                'motified' => DateTime::now(),
                'password' => '42f749ade7f9e195bf475f37a44cafcb',
                'sca_name' => 'Stabby McStab',
                'first_name' => 'Stan',
                'middle_name' => '',
                'last_name' => 'Rapier',
                'street_address' => 'Fake Data',
                'city' => 'a city',
                'state' => 'TX',
                'zip' => '00000',
                'phone_number' => '',
                'email_address' => 'Stan@test.com',
                'membership_number' => '3456',
                'membership_expires_on' => '2028-12-30',
                'branch_id' => 7,
                'background_check_expires_on' => NULL,
                'status' => 'active',
                'password_token' => NULL,
                'password_token_expires_on' => NULL,
                'last_login' => NULL,
                'last_failed_login' => NULL,
                'failed_login_attempts' => NULL,
                'birth_month' => 4,
                'birth_year' => 1977,
                'deleted' => NULL,
                'created' => DateTime::now(),
            ],
        ];

        $table = $this->table('members');
        $table->insert($data)->save();
    }
}