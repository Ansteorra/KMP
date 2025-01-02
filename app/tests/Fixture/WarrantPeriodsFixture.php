<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * WarrantPeriodsFixture
 */
class WarrantPeriodsFixture extends TestFixture
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
                'start_date' => '2024-12-23 02:24:22',
                'end_date' => '2024-12-23 02:24:22',
                'created' => '2024-12-23 02:24:22',
                'created_by' => 1,
            ],
        ];
        parent::init();
    }
}
