<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use App\Test\Fixture\BaseTestFixture;

/**
 * GatheringActivitiesFixture
 *
 * Provides test data for GatheringActivity entities.
 * Used to test activity configuration within gatherings.
 */
class GatheringActivitiesFixture extends BaseTestFixture
{
    /**
     * Import table schema
     *
     * @var array<string>
     */
    public array $import = ['table' => 'gathering_activities'];

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = $this->getData('DevLoadGatheringActivitiesSeed');
        parent::init();
    }
}
