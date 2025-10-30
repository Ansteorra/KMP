<?php

declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * DevLoadGatheringsSeed
 *
 * Development seed data for gatherings.
 * Provides sample gatherings for testing.
 */
class DevLoadGatheringsSeed extends BaseSeed
{
    /**
     * Provide development seed entries for gatherings.
     *
     * @return array<int, array<string, mixed>> An array of gathering seed records; may be empty when no seeds are defined.
     */
    public function getData(): array
    {
        // For now, return empty array - gatherings will be created in tests
        // This allows the fixture to load without errors
        return [];
    }

    /**
     * Inserts development gathering seed data into the 'gatherings' table when present.
     *
     * Retrieves seed data via getData() and performs a bulk insert into the 'gatherings' table only if the returned array is non-empty.
     */
    public function run(): void
    {
        $data = $this->getData();

        if (!empty($data)) {
            $table = $this->table('gatherings');
            $table->insert($data)->save();
        }
    }
}