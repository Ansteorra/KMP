<?php

declare(strict_types=1);

namespace App\Test\Fixture;

/**
 * AppSettingsFixture
 */
class WarrantsFixture extends BaseTestFixture
{
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [];
        // Note: InitWarrantsSeed returns warrant_rosters, permissions, and warrant_periods
        // The actual warrants are created in run() method after rosters exist
        // For test fixtures, we start with an empty warrants table
        parent::init();
    }
}
