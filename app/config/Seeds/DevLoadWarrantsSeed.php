<?php

declare(strict_types=1);

use Migrations\AbstractSeed;
use Cake\I18n\DateTime;

/**
 * Roles seed.
 */
class DevLoadWarrantsSeed extends AbstractSeed
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
                'warrant_approval_set_id' => 1,
                'warrant_for_model' => 'Direct Grant',
                'warrant_for_id' => -1,
                'member_role_id' => 200,
                'expires_on' => '2100-10-10 00:00:00',
                'start_on' => '2020-01-01 00:00:00',
                'approved_date' => '2020-01-01 00:00:00',
                'status' => 'active',
                'revoked_reason' => NULL,
                'revoker_id' => NULL,
                'created_by' => 1,
                'created' => DateTime::now(),
            ],
            [
                'id' => 3,
                'member_id' => 201,
                'warrant_approval_set_id' => 1,
                'warrant_for_model' => 'Direct Grant',
                'warrant_for_id' => -1,
                'member_role_id' => 201,
                'expires_on' => '2100-10-10 00:00:00',
                'start_on' => '2020-01-01 00:00:00',
                'approved_date' => '2020-01-01 00:00:00',
                'status' => 'active',
                'revoked_reason' => NULL,
                'revoker_id' => NULL,
                'created_by' => 1,
                'created' => DateTime::now(),
            ],
        ];

        $table = $this->table('warrants');
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