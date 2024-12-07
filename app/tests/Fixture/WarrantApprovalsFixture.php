<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * WarrantApprovalsFixture
 */
class WarrantApprovalsFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'warrant_approval_set_id' => 1,
                'approver_id' => 1,
                'authorization_token' => 'Lorem ipsum dolor sit amet',
                'requested_on' => '2024-12-07 15:18:55',
                'responded_on' => '2024-12-07 15:18:55',
                'approved' => 1,
                'approver_notes' => 'Lorem ipsum dolor sit amet',
            ],
        ];
        parent::init();
    }
}
