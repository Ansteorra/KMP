<?php

declare(strict_types=1);

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
     * Get seed data
     *
     * These are template activities that can be linked to gatherings via
     * the gatherings_gathering_activities join table.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Armored Combat',
                'description' => 'Heavy armored fighting with rattan weapons. Full armor required. No live steel.',
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'id' => 2,
                'name' => 'Rapier Combat',
                'description' => 'Light armored fighting with rapier and dagger. Gorget and protective gear required.',
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'id' => 3,
                'name' => 'Youth Combat',
                'description' => 'Combat activities for participants under 18. Parent/guardian signature required on waiver.',
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'id' => 4,
                'name' => 'Archery',
                'description' => 'Target archery practice. Range safety briefing required.',
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'id' => 5,
                'name' => 'Thrown Weapons',
                'description' => 'Axe and knife throwing. Safety briefing required.',
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            [
                'id' => 6,
                'name' => 'Arts & Sciences Class',
                'description' => 'Various A&S workshops and demonstrations.',
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

        $table = $this->table('gathering_activities');
        $table->insert($data)->save();
    }
}
