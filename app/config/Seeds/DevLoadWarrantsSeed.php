<?php

declare(strict_types=1);

use Migrations\BaseSeed;
use Cake\I18n\DateTime;

require_once __DIR__ . '/Lib/SeedHelpers.php';

/**
 * Roles seed.
 */
class DevLoadWarrantsSeed extends BaseSeed
{
    /**
     * Get data for seeding.
     *
     * @return array
     */
    public function getData(): array
    {


        $adminId = SeedHelpers::getMemberId('admin@test.com');
        return [
            [
                //'id' => 2,
                'name' => 'Developer Warrant',
                'member_id' => SeedHelpers::getMemberId('Earl@test.com'), //200,
                'warrant_roster_id' => 1,
                'entity_type' => 'Direct Grant',
                'entity_id' => -1,
                'member_role_id' => SeedHelpers::getMemberRoleByMemberAndRoleName(SeedHelpers::getMemberId('Earl@test.com'), 'Kingdom Earl Marshal'), //200
                'expires_on' => '2100-10-10 00:00:00',
                'start_on' => '2020-01-01 00:00:00',
                'approved_date' => '2020-01-01 00:00:00',
                'status' => 'Current',
                'revoked_reason' => NULL,
                'revoker_id' => NULL,
                'created_by' => $adminId,
                'created' => DateTime::now(),
            ],
            [
                //'id' => 3,
                'name' => 'Developer Warrant',
                'member_id' => SeedHelpers::getMemberId('Stan@test.com'), //201,
                'warrant_roster_id' => 1,
                'entity_type' => 'Direct Grant',
                'entity_id' => -1,
                'member_role_id' => SeedHelpers::getMemberRoleByMemberAndRoleName(SeedHelpers::getMemberId('Earl@test.com'), 'Kingdom Rapier Marshal'), //201,
                'expires_on' => '2100-10-10 00:00:00',
                'start_on' => '2020-01-01 00:00:00',
                'approved_date' => '2020-01-01 00:00:00',
                'status' => 'Current',
                'revoked_reason' => NULL,
                'revoker_id' => NULL,
                'created_by' => $adminId,
                'created' => DateTime::now(),
            ],
        ];
    }

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
        $table = $this->table('warrants');
        $options = $table->getAdapter()->getOptions();
        $options['identity_insert'] = true;
        $table->getAdapter()->setOptions($options);
        $table->insert($data)->save();

        // Load the MembersTable
        $membersTable = \Cake\ORM\TableRegistry::getTableLocator()->get('Members');

        // Fetch all members
        $members = $membersTable->find('all');

        foreach ($members as $member) {
            // Compute warrantable status
            $member->warrantableReview();
            // Save without triggering beforeSave to avoid recursion
            $membersTable->save($member, ['checkRules' => false, 'callbacks' => false]);
        }
    }
}
