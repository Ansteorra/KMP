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
     * Provide seed rows that link gatherings to gathering activities with an explicit sort order.
     *
     * Each returned element is an associative array representing a row for the
     * gatherings_gathering_activities table with the following keys:
     * - `gathering_id` (int): ID of the gathering.
     * - `gathering_activity_id` (int): ID of the activity template.
     * - `sort_order` (int): Position of the activity within the gathering.
     * - `created` (string): Creation timestamp in 'Y-m-d H:i:s' format.
     *
     * Example:
     * [
     *     [
     *         'gathering_id' => 1,
     *         'gathering_activity_id' => 1,
     *         'sort_order' => 1,
     *         'created' => '2025-01-01 10:00:00',
     *     ],
     *     [
     *         'gathering_id' => 1,
     *         'gathering_activity_id' => 4,
     *         'sort_order' => 2,
     *         'created' => '2025-01-01 10:00:00',
     *     ],
     * ]
     *
     * @return array<int, array{gathering_id:int, gathering_activity_id:int, sort_order:int, created:string}>
     */
    public function getData(): array
    {
        // Empty for now - will be populated when gatherings are created
        return [];
    }

    /**
     * Load seed rows into the gatherings_gathering_activities table.
     *
     * Calls getData() and, if it returns a non-empty array, inserts those rows
     * into the 'gatherings_gathering_activities' table; does nothing when no data
     * is provided.
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