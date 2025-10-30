<?php

declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * DevLoadGatheringTypesSeed
 *
 * Development seed data for gathering types.
 * Provides sample gathering types for testing and development.
 */
class DevLoadGatheringTypesSeed extends BaseSeed
{
    /**
     * Provide predefined seed records for gathering types.
     *
     * Each element is an associative array representing a gathering type with keys:
     * `id`, `name`, `description`, `clonable`, `created`, and `modified`.
     *
     * @return array<int, array<string, mixed>> Array of gathering type seed records.
     */
    public function getData(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Fighter Practice',
                'description' => 'Regular heavy and light armored combat practice',
                'clonable' => true,
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Arts & Sciences Workshop',
                'description' => 'Hands-on workshop for various A&S disciplines',
                'clonable' => true,
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'id' => 3,
                'name' => 'Kingdom Event',
                'description' => 'Major kingdom-level event with multiple activities',
                'clonable' => false,
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'id' => 4,
                'name' => 'Archery Range Day',
                'description' => 'Open archery practice and competitions',
                'clonable' => true,
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
        ];
    }

    /**
     * Inserts development seed records for gathering types into the database.
     *
     * Retrieves seed data from getData() and inserts it into the 'gathering_types' table.
     */
    public function run(): void
    {
        $data = $this->getData();

        $table = $this->table('gathering_types');
        $table->insert($data)->save();
    }
}