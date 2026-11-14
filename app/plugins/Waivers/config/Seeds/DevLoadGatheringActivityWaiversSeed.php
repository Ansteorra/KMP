<?php

declare(strict_types=1);

use App\Services\GatheringActivitySeedService;
use Migrations\BaseSeed;

/**
 * DevLoadGatheringActivityWaiversSeed
 *
 * Development seed data linking activities to required waiver types.
 * Defines which waivers are required for which activities.
 *
 * Uses name-based lookups rather than hard-coded IDs so the seed remains
 * compatible with PostgreSQL seed refreshes and normal catalogue naming variants.
 * Silently skips link rows for which the activity or waiver type
 * is missing, since waiver_types are not seeded on a clean install.
 */
class DevLoadGatheringActivityWaiversSeed extends BaseSeed
{
    /**
     * Link definitions keyed by activity name and waiver-type name.
     *
     * @return array<int, array{activity:string, waiver_type:string}>
     */
    public function getData(): array
    {
        return [
            ['activity' => 'Armored Combat', 'waiver_type' => 'General Liability Waiver'],
            ['activity' => 'Rapier Combat', 'waiver_type' => 'General Liability Waiver'],
            ['activity' => 'Youth Combat', 'waiver_type' => 'Youth Participation Waiver'],
            ['activity' => 'Youth Combat', 'waiver_type' => 'General Liability Waiver'],
            ['activity' => 'Archery', 'waiver_type' => 'General Liability Waiver'],
            ['activity' => 'Thrown Weapons', 'waiver_type' => 'General Liability Waiver'],
        ];
    }

    /**
     * Look up each activity+waiver-type by name and insert a link row for
     * every pair that resolves. Skips pairs with missing references so the
     * seed succeeds even when waiver_types has not been populated.
     */
    public function run(): void
    {
        $activities = [];
        foreach ($this->fetchAll('SELECT id, name FROM gathering_activities') as $row) {
            $activities[strtolower(GatheringActivitySeedService::canonicalName($row['name']))] = (int)$row['id'];
        }
        $waiverTypes = [];
        foreach ($this->fetchAll('SELECT id, name FROM waivers_waiver_types') as $row) {
            $waiverTypes[$row['name']] = (int)$row['id'];
        }
        $existing = [];
        foreach (
            $this->fetchAll(
                'SELECT gathering_activity_id, waiver_type_id '
                . 'FROM waivers_gathering_activity_waivers WHERE deleted IS NULL',
            ) as $row
        ) {
            $existing[$row['gathering_activity_id'] . ':' . $row['waiver_type_id']] = true;
        }

        $now = '2025-01-01 10:00:00';
        $toInsert = [];
        foreach ($this->getData() as $link) {
            $actId = $activities[strtolower(GatheringActivitySeedService::canonicalName($link['activity']))] ?? null;
            $wtId = $waiverTypes[$link['waiver_type']] ?? null;
            if ($actId === null || $wtId === null || isset($existing[$actId . ':' . $wtId])) {
                continue;
            }
            $existing[$actId . ':' . $wtId] = true;
            $toInsert[] = [
                'gathering_activity_id' => $actId,
                'waiver_type_id' => $wtId,
                'created' => $now,
                'modified' => $now,
            ];
        }
        if (empty($toInsert)) {
            return;
        }
        $table = $this->table('waivers_gathering_activity_waivers');
        $table->insert($toInsert)->save();
    }
}
