<?php

declare(strict_types=1);

require_once __DIR__ . '/Lib/SeedHelpers.php';

use Migrations\BaseSeed;

/**
 * DevLoadGatheringActivitiesSeed
 *
 * Development seed data for gathering activities.
 * Provides sample template activities for testing and development.
 * 
 * GatheringActivities are configuration/template objects that define types of
 * activities (e.g., "Armored Combat", "Archery"). They can be reused across
 * many gatherings through the gatherings_gathering_activities join table.
 */
class DevLoadGatheringActivitiesSeed extends BaseSeed
{
    /**
     * Provide template gathering activity seed records.
     *
     * Rows are inserted without explicit ids so the underlying sequence
     * assigns them. A "Kingdom Court" activity is already created by the
     * Awards `RunMigrateAwardEvents` migration and is skipped here via a
     * name-based duplicate check in run().
     *
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        return [
            [
                'name' => 'Armored Combat',
                'description' => 'Heavy armored fighting with rattan weapons. Full armor required. No live steel.',
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'name' => 'Rapier Combat',
                'description' => 'Light armored fighting with rapier and dagger. Gorget and protective gear required.',
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'name' => 'Youth Combat',
                'description' => 'Combat activities for participants under 18. Parent/guardian signature required on waiver.',
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'name' => 'Archery',
                'description' => 'Target archery practice. Range safety briefing required.',
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'name' => 'Thrown Weapons',
                'description' => 'Axe and knife throwing. Safety briefing required.',
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'name' => 'Arts & Sciences Class',
                'description' => 'Various A&S workshops and demonstrations.',
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
        ];
    }

    /**
     * Inserts predefined gathering activity seed records, skipping any that
     * already exist by name.
     */
    public function run(): void
    {
        SeedHelpers::insertIfMissing($this, 'gathering_activities', $this->getData());
        SeedHelpers::resetPostgresSequences($this, ['gathering_activities']);
    }
}