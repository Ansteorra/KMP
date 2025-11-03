<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * GatheringStaffFixture
 */
class GatheringStaffFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'gathering_staff';

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
                'gathering_id' => 1,
                'member_id' => 1,
                'sca_name' => null,
                'role' => 'Steward',
                'is_steward' => true,
                'email' => 'steward@example.com',
                'phone' => '555-0100',
                'contact_notes' => 'Please text, no calls after 9 PM',
                'sort_order' => 0,
                'created' => '2024-01-01 12:00:00',
                'modified' => '2024-01-01 12:00:00',
                'created_by' => 1,
                'modified_by' => null,
                'deleted' => null,
            ],
            [
                'id' => 2,
                'gathering_id' => 1,
                'member_id' => 2,
                'sca_name' => null,
                'role' => 'Steward',
                'is_steward' => true,
                'email' => 'co-steward@example.com',
                'phone' => null,
                'contact_notes' => 'Email only please',
                'sort_order' => 1,
                'created' => '2024-01-01 12:00:00',
                'modified' => '2024-01-01 12:00:00',
                'created_by' => 1,
                'modified_by' => null,
                'deleted' => null,
            ],
            [
                'id' => 3,
                'gathering_id' => 1,
                'member_id' => 3,
                'sca_name' => null,
                'role' => 'Herald',
                'is_steward' => false,
                'email' => 'herald@example.com',
                'phone' => '555-0102',
                'contact_notes' => null,
                'sort_order' => 100,
                'created' => '2024-01-01 12:00:00',
                'modified' => '2024-01-01 12:00:00',
                'created_by' => 1,
                'modified_by' => null,
                'deleted' => null,
            ],
            [
                'id' => 4,
                'gathering_id' => 1,
                'member_id' => null,
                'sca_name' => 'Jane of Example',
                'role' => 'Water Bearer',
                'is_steward' => false,
                'email' => null,
                'phone' => null,
                'contact_notes' => null,
                'sort_order' => 101,
                'created' => '2024-01-01 12:00:00',
                'modified' => '2024-01-01 12:00:00',
                'created_by' => 1,
                'modified_by' => null,
                'deleted' => null,
            ],
        ];
        parent::init();
    }
}
