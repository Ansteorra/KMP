<?php

declare(strict_types=1);

namespace Waivers\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * WaiverTypesFixture
 */
class WaiverTypesFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public string $table = 'waivers_waiver_types';

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
                'name' => 'General Liability Waiver',
                'description' => 'Standard waiver for general event participation',
                'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
                'convert_to_pdf' => true,
                'is_active' => true,
                'created' => '2025-01-01 12:00:00',
                'modified' => '2025-01-01 12:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Youth Participation Waiver',
                'description' => 'Required for participants under 18',
                'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":10}}',
                'convert_to_pdf' => true,
                'is_active' => true,
                'created' => '2025-01-01 12:00:00',
                'modified' => '2025-01-01 12:00:00',
            ],
            [
                'id' => 3,
                'name' => 'Combat Activities Waiver',
                'description' => 'Required for steel combat and armored combat',
                'retention_policy' => '{"anchor":"gathering_end_date","duration":{"years":7}}',
                'convert_to_pdf' => true,
                'is_active' => true,
                'created' => '2025-01-01 12:00:00',
                'modified' => '2025-01-01 12:00:00',
            ],
            [
                'id' => 4,
                'name' => 'Inactive Test Waiver',
                'description' => 'A test waiver that is inactive',
                'retention_policy' => '{"anchor":"permanent"}',
                'convert_to_pdf' => false,
                'is_active' => false,
                'created' => '2025-01-01 12:00:00',
                'modified' => '2025-01-01 12:00:00',
            ],
        ];

        parent::init();
    }
}
