<?php

declare(strict_types=1);

require_once __DIR__ . '/Lib/SeedHelpers.php';

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
     * Provide seed records for gathering types.
     *
     * Rows are inserted without explicit ids. "Kingdom Calendar Event" is
     * pre-seeded by the Awards `RunMigrateAwardEvents` migration and is
     * skipped via name-based duplicate check in run().
     *
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        return [
            [
                'name' => 'Fighter Practice',
                'description' => 'Regular heavy and light armored combat practice',
                'clonable' => true,
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'name' => 'Arts & Sciences Workshop',
                'description' => 'Hands-on workshop for various A&S disciplines',
                'clonable' => true,
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'name' => 'Kingdom Event',
                'description' => 'Major kingdom-level event with multiple activities',
                'clonable' => false,
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'name' => 'Archery Range Day',
                'description' => 'Open archery practice and competitions',
                'clonable' => true,
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
        ];
    }

    /**
     * Inserts gathering type seed records into the database, skipping any that
     * already exist by name.
     */
    public function run(): void
    {
        SeedHelpers::insertIfMissing($this, 'gathering_types', $this->getData());
        SeedHelpers::resetPostgresSequences($this, ['gathering_types']);
    }
}