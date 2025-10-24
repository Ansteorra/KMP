<?php

declare(strict_types=1);

namespace Waivers\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * GatheringWaiversFixture
 */
class GatheringWaiversFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'waivers_gathering_waivers';

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
                'waiver_type_id' => 1,
                'member_id' => 2, // Participant who signed the waiver
                'document_id' => 1,
                'retention_date' => '2032-03-15', // 7 years from gathering end date (2025-03-15)
                'status' => 'active',
                'notes' => 'Waiver signed by John Doe for tournament combat',
                'created' => '2025-01-15 10:30:00',
                'modified' => '2025-01-15 10:30:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            [
                'id' => 2,
                'gathering_id' => 1,
                'waiver_type_id' => 1,
                'member_id' => 3, // Another participant
                'document_id' => 2,
                'retention_date' => '2032-03-15', // 7 years from gathering end date
                'status' => 'active',
                'notes' => 'Waiver signed by Jane Smith for tournament combat',
                'created' => '2025-01-20 14:15:00',
                'modified' => '2025-01-20 14:15:00',
                'created_by' => 1,
                'modified_by' => 1,
            ],
            [
                'id' => 3,
                'gathering_id' => 2,
                'waiver_type_id' => 2,
                'member_id' => null, // Minor - no member account
                'document_id' => 3,
                'retention_date' => '2035-04-20', // 10 years from gathering end date (2025-04-20)
                'status' => 'active',
                'notes' => 'Youth participation consent form - parent signature',
                'created' => '2025-02-10 09:45:00',
                'modified' => '2025-02-10 09:45:00',
                'created_by' => 2,
                'modified_by' => 2,
            ],
            [
                'id' => 4,
                'gathering_id' => 1,
                'waiver_type_id' => 3,
                'member_id' => 2,
                'document_id' => null, // Waiver uploaded but document is missing/deleted
                'retention_date' => '2030-03-15', // 5 years from gathering end date
                'status' => 'expired',
                'notes' => 'Photo release - document no longer available',
                'created' => '2025-01-16 11:00:00',
                'modified' => '2025-10-01 00:00:00',
                'created_by' => 1,
                'modified_by' => null,
            ],
        ];
        parent::init();
    }
}
