<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * OfficersFixture
 */
class OfficersFixture extends TestFixture
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
                'member_id' => 1,
                'branch_id' => 1,
                'office_id' => 1,
                'granted_member_role_id' => 1,
                'expires_on' => '2024-06-05',
                'start_on' => '2024-06-05',
                'status' => 'Lorem ipsum dolor ',
                'revoked_reason' => 'Lorem ipsum dolor sit amet',
                'revoker_id' => 1,
            ],
        ];
        parent::init();
    }
}
