<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * GatheringAttendancesFixture
 */
class GatheringAttendancesFixture extends TestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            // Example attendance records
            [
                'id' => 1,
                'gathering_id' => 1,
                'member_id' => 1,
                'public_note' => 'Looking forward to the tournament!',
                'share_with_kingdom' => true,
                'share_with_hosting_group' => true,
                'share_with_crown' => false,
                'is_public' => true,
                'created' => '2025-10-23 12:00:00',
                'modified' => '2025-10-23 12:00:00',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => null,
            ],
            [
                'id' => 2,
                'gathering_id' => 1,
                'member_id' => 2,
                'public_note' => null,
                'share_with_kingdom' => false,
                'share_with_hosting_group' => true,
                'share_with_crown' => false,
                'is_public' => false,
                'created' => '2025-10-23 13:00:00',
                'modified' => '2025-10-23 13:00:00',
                'created_by' => 2,
                'modified_by' => 2,
                'deleted' => null,
            ],
            [
                'id' => 3,
                'gathering_id' => 2,
                'member_id' => 1,
                'public_note' => 'Planning to help with setup',
                'share_with_kingdom' => true,
                'share_with_hosting_group' => true,
                'share_with_crown' => true,
                'is_public' => true,
                'created' => '2025-10-24 10:00:00',
                'modified' => '2025-10-24 10:00:00',
                'created_by' => 1,
                'modified_by' => 1,
                'deleted' => null,
            ],
        ];
        parent::init();
    }
}
