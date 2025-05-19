<?php

declare(strict_types=1);

require_once __DIR__ . '/Lib/SeedHelpers.php';

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
        $table->insert($data)->save();
    }

    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {
        // Use email/sca_name and role names for lookups
        $earlId = SeedHelpers::getMemberId('Earl@test.com');
        $stanId = SeedHelpers::getMemberId('Stan@test.com');
        $regId = SeedHelpers::getMemberId('Reg@test.com');
        $adminId = SeedHelpers::getMemberId('admin@test.com');
        $kingdomMarshalRole = SeedHelpers::getRoleId('Kingdom Earl Marshal');
        $rapierMarshalRole = SeedHelpers::getRoleId('Kingdom Rapier Marshal');
        $regionalRole = SeedHelpers::getRoleId('User Manager');
        $adminRole = SeedHelpers::getRoleId('Admin');
        return [
            [
                'member_id' => $earlId,
                'role_id' => $kingdomMarshalRole,
                'expires_on' => NULL,
                'start_on' => '2024-05-30 01:22:55',
                'approver_id' => $adminId,
                'entity_type' => 'Direct Grant',
                'created' => DateTime::now(),
                'created_by' => $adminId
            ],
            [
                'member_id' => $stanId,
                'role_id' => $rapierMarshalRole,
                'expires_on' => NULL,
                'start_on' => '2024-05-30 12:54:12',
                'approver_id' => $adminId,
                'entity_type' => 'Direct Grant',
                'created' => DateTime::now(),
                'created_by' => $adminId
            ],
            [
                'member_id' => $regId,
                'role_id' => $regionalRole,
                'expires_on' => NULL,
                'start_on' => '2024-05-30 12:54:12',
                'approver_id' => $adminId,
                'entity_type' => 'Direct Grant',
                'created' => DateTime::now(),
                'created_by' => $adminId,
                'branch_id' => SeedHelpers::getBranchIdByName('Barony 2')
            ],
        ];
    }
}