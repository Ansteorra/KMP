<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry; // Added

/**
 * Members seed.
 */
class DevLoadMembersSeed extends BaseSeed
{
    private function getBranchIdByName(?string $name): ?int
    {
        if ($name === null) {
            return null;
        }
        $branchesTable = TableRegistry::getTableLocator()->get('Branches');
        // Assuming 'name' is a unique identifier for branches relevant to this seed.
        $branch = $branchesTable->find()->where(['name' => $name])->select(['id'])->firstOrFail();
        return $branch->id;
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
                // 'id' => 200, // Removed
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
                'branch_id' => $this->getBranchIdByName('Barony 2'), // Was ID 4
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
                // 'id' => 201, // Removed
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
                'branch_id' => $this->getBranchIdByName('Shire 1'), // Was ID 7
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
                // 'id' => 202, // Removed
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
                'branch_id' => $this->getBranchIdByName('Shire 1'), // Was ID 7
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
                'mobile_card_token' => '9ffd0d041d4ff102b2c31f3edbd1cf86', // Note: This token was identical to Stabby's in original, might be intentional or a copy-paste.
            ],
        ];
    }

    public function run(): void
    {
        $data = $this->getData();
        $table = $this->table('members');
        // $options = $table->getAdapter()->getOptions(); // Removed
        // $options['identity_insert'] = true; // Removed
        // $table->getAdapter()->setOptions($options); // Removed
        $table->insert($data)->save();
    }
}
