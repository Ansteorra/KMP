<?php

declare(strict_types=1);


use Migrations\AbstractSeed;
use Cake\I18n\DateTime;

/**
 * Members seed.
 */
class InitMembersSeed extends AbstractSeed
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
                'modified' => DateTime::now(),
                'password' => md5('Password123'),
                'sca_name' => 'Admin von Admin',
                'first_name' => 'Addy',
                'middle_name' => '',
                'last_name' => 'Min',
                'street_address' => 'Fake Data',
                'city' => 'a city',
                'state' => 'TX',
                'zip' => '00000',
                'phone_number' => '555-555-5555',
                'email_address' => 'admin@test.com',
                'membership_number' => 'AdminAccount',
                'membership_expires_on' => '2100-01-01',
                'branch_id' => 1,
                'background_check_expires_on' => NULL,
                'status' => 'verified',
                'password_token' => NULL,
                'password_token_expires_on' => NULL,
                'last_login' => NULL,
                'last_failed_login' => NULL,
                'failed_login_attempts' => NULL,
                'birth_month' => 4,
                'birth_year' => 1977,
                'deleted' => NULL,
                'created' => DateTime::now(),
                'mobile_card_token' => '9cf9fd5c389304f85d5ade102a9c9119',
            ]
        ];

        $table = $this->table('members');
        $table->insert($data)->save();
    }
}