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
     * Get seed data
     *
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        // For now, return empty array - gatherings will be created in tests
        // This allows the fixture to load without errors
        return [];
    }

    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * @return void
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
