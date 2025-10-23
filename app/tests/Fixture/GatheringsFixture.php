<?php

declare(strict_types=1);

namespace App\Test\Fixture;

/**
 * GatheringsFixture
 *
 * Test fixture for Gatherings table.
 * Provides sample gatherings for testing.
 */
class GatheringsFixture extends BaseTestFixture
{
    /**
     * Import table definition from database
     *
     * @var array<string, mixed>
     */
    public array $import = ['table' => 'gatherings'];

    /**
     * Initialize the fixture.
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = $this->getData('DevLoadGatheringsSeed', null, true);
        parent::init();
    }
}
