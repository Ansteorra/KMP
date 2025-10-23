<?php

declare(strict_types=1);

namespace App\Test\Fixture;

use App\Test\Fixture\BaseTestFixture;

/**
 * GatheringTypesFixture
 *
 * Provides test data for GatheringType entities.
 * Used to test gathering type configuration and management.
 */
class GatheringTypesFixture extends BaseTestFixture
{
    /**
     * Import table schema
     *
     * @var array<string>
     */
    public array $import = ['table' => 'gathering_types'];

    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = $this->getData('DevLoadGatheringTypesSeed');
        parent::init();
    }
}
