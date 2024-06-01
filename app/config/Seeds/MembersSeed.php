<?php

declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Members seed.
 */
class MembersSeed extends AbstractSeed
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
                'last_updated' => '2021-11-18 20:02:26',
                'password' => '42f749ade7f9e195bf475f37a44cafcb',
                'sca_name' => 'Admin von Admin',
                'first_name' => 'Addy',
                'middle_name' => '',
                'last_name' => 'Min',
                'street_address' => 'Fake Data',
                'city' => 'a city',
                'state' => 'TX',
                'zip' => '00000',
                'phone_number' => '',
                'email_address' => 'admin@test.com',
                'membership_number' => '1234',
                'membership_expires_on' => '2030-01-01',
                'branch_id' => 3,
                'parent_name' => '',
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
            ],
            [
                'id' => 2,
                'last_updated' => '2021-11-18 20:02:26',
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
                'parent_name' => '',
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
            ],
            [
                'id' => 3,
                'last_updated' => '2021-11-18 20:02:26',
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
                'parent_name' => '',
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
            ],
        ];

        $table = $this->table('members');
        $table->insert($data)->save();
    }
}