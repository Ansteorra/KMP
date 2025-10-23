<?php

declare(strict_types=1);

namespace App\Test\Fixture;

/**
 * GatheringsFixture
 *
 * Test fixture for Gatherings table.
 * Provides sample gatherings for testing.
 * 
 * Note: Relies on existing database schema from bin/setup_test_database.sh
 * Uses TruncateStrategy (configured in phpunit.xml.dist) to preserve schema.
 */
class GatheringsFixture extends BaseTestFixture
{
    /**
     * The table name
     *
     * @var string
     */
    public string $table = 'gatherings';

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
