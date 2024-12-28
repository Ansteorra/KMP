<?php

declare(strict_types=1);

use Migrations\AbstractSeed;
use Cake\I18n\DateTime;

/**
 * Roles seed.
 */
class InitWarrantsSeed extends AbstractSeed
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
                'name' => 'System Admin Warrant Set',
                'description' => 'Required for system admin account',
                'approvals_required' => 1,
                'approval_count' => 1,
                'created_by' => 1,
                'status' => 'Approved',
                'created' => DateTime::now(),
            ],
        ];

        $table = $this->table('warrant_rosters');
        $table->insert($data)->save();

        $data = [
            [
                'id' => 1,
                'member_id' => 1,
                'warrant_roster_id' => 1,
                'entity_type' => 'Direct Grant',
                'entity_id' => -1,
                'member_role_id' => 1,
                'expires_on' => '2100-10-10 00:00:00',
                'start_on' => '2020-01-01 00:00:00',
                'approved_date' => '2020-01-01 00:00:00',
                'status' => 'Current',
                'revoked_reason' => NULL,
                'revoker_id' => NULL,
                'created_by' => 1,
                'created' => DateTime::now(),
            ],
        ];

        $table = $this->table('warrants');
        $table->insert($data)->save();

        $data = [
            [
                'id' => 1,
                'warrant_roster_id' => 1,
                'approver_id' => 1,
                'approved_on' => DateTime::now(),
            ],
        ];

        $table = $this->table('warrant_roster_approvals');
        $table->insert($data)->save();
    }
}