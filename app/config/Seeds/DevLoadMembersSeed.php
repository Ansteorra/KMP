<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;

/**
 * Members seed.
 */
class DevLoadMembersSeed extends BaseSeed
{
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
                'modified' => DateTime::now(),
                'password' => '42f749ade7f9e195bf475f37a44cafcb',
                'sca_name' => 'Earl Realm',
                'first_name' => 'Kingdom',
                'middle_name' => '',
                'last_name' => 'Marshal',
                'street_address' => 'Fake Data',
                'city' => 'a city',
                'state' => 'TX',
                'zip' => '00000',
                'phone_number' => '222-222-2222',
                'email_address' => 'Earl@test.com',
                'membership_number' => '2345',
                'membership_expires_on' => '2030-03-31',
                'branch_id' => 4,
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
                'mobile_card_token' => '6519b703451d2d22534d058c456d6133',
            ],
            [
                'id' => 201,
                'modified' => DateTime::now(),
                'password' => '42f749ade7f9e195bf475f37a44cafcb',
                'sca_name' => 'Stabby McStab',
                'first_name' => 'Stan',
                'middle_name' => '',
                'last_name' => 'Rapier',
                'street_address' => 'Fake Data',
                'city' => 'a city',
                'state' => 'TX',
                'zip' => '00000',
                'phone_number' => '333-333-3333',
                'email_address' => 'Stan@test.com',
                'membership_number' => '3456',
                'membership_expires_on' => '2028-12-30',
                'branch_id' => 7,
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
                'mobile_card_token' => '9ffd0d041d4ff102b2c31f3edbd1cf86',
            ],
            [
                'id' => 202,
                'modified' => DateTime::now(),
                'password' => '42f749ade7f9e195bf475f37a44cafcb',
                'sca_name' => 'Reggy Regional',
                'first_name' => 'Reg',
                'middle_name' => '',
                'last_name' => 'Regional',
                'street_address' => 'Fake Data',
                'city' => 'a city',
                'state' => 'TX',
                'zip' => '00000',
                'phone_number' => '333-333-3333',
                'email_address' => 'Reg@test.com',
                'membership_number' => '3456',
                'membership_expires_on' => '2028-12-30',
                'branch_id' => 7,
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
                'mobile_card_token' => '9ffd0d041d4ff102b2c31f3edbd1cf86',
            ],
        ];
    }

    public function run(): void
    {
        $data = $this->getData();
        $table = $this->table('members');
        $options = $table->getAdapter()->getOptions();
        $options['identity_insert'] = true;
        $table->getAdapter()->setOptions($options);
        $table->insert($data)->save();
    }
}
