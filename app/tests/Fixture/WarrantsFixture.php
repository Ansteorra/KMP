<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * WarrantsFixture
 */
class WarrantsFixture extends TestFixture
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
                'warrant_roster_id' => 1,
                'entity_type' => 'Lorem ipsum dolor sit amet',
                'entity_id' => 1,
                'member_role_id' => 1,
                'expires_on' => '2024-12-07 15:18:05',
                'start_on' => '2024-12-07 15:18:05',
                'approved_date' => '2024-12-07 15:18:05',
                'status' => 'Lorem ipsum dolor ',
                'revoked_reason' => 'Lorem ipsum dolor sit amet',
                'revoker_id' => 1,
                'created_by' => 1,
                'created' => 1733584685,
            ],
        ];
        parent::init();
    }
}
