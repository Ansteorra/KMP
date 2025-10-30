<?php

declare(strict_types=1);

namespace Waivers\Test\Fixture;

use App\Test\Fixture\BaseTestFixture;

/**
 * GatheringActivityWaiversFixture
 *
 * Provides test data for GatheringActivityWaiver entities.
 * Links activities to their required waiver types.
 * Used to test waiver requirement configuration.
 */
class GatheringActivityWaiversFixture extends BaseTestFixture
{
    /**
     * Import table schema
     *
     * @var array<string>
     */
    public array $import = ['table' => 'waivers_gathering_activity_waivers'];

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = $this->getData('DevLoadGatheringActivityWaiversSeed', 'Waivers');
        parent::init();
    }
}
