<?php

declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * DevLoadGatheringsGatheringActivitiesSeed
 *
 * Development seed data for gatherings-activities relationships.
 * Links template activities to specific gatherings.
 *
 * This seed file is intentionally minimal - it will be populated
 * when actual gathering test data is needed.
 */
class DevLoadGatheringsGatheringActivitiesSeed extends BaseSeed
{
    /**
     * Get seed data
     *
     * Links gathering activities to gatherings with sort order.
     * Format: [gathering_id, gathering_activity_id, sort_order]
     *
     * Example: If gathering ID 1 has activities "Armored Combat" (1) and "Archery" (4):
     * [
     *     'gathering_id' => 1,
     *     'gathering_activity_id' => 1, // Armored Combat
     *     'sort_order' => 1,
     *     'created' => '2025-01-01 10:00:00',
     * ],
     * [
     *     'gathering_id' => 1,
     *     'gathering_activity_id' => 4, // Archery
     *     'sort_order' => 2,
     *     'created' => '2025-01-01 10:00:00',
     * ]
     *
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        // Empty for now - will be populated when gatherings are created
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
            $table = $this->table('gatherings_gathering_activities');
            $table->insert($data)->save();
        }
    }
}
