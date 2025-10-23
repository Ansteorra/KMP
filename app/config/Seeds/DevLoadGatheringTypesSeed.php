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
     * Get seed data
     *
     * @return array<int, array<string, mixed>>
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
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * @return void
     */
    public function run(): void
    {
        $data = $this->getData();

        $table = $this->table('gathering_types');
        $table->insert($data)->save();
    }
}
