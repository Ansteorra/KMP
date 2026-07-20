<?php

declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * DevLoadGatheringActivityWaiversSeed
 *
 * Development seed data linking activities to required waiver types.
 * Defines which waivers are required for which activities.
 *
 * Uses name-based lookups rather than hard-coded IDs so the seed remains
 * compatible with both MySQL and Postgres (the latter does not advance
 * auto-increment sequences for explicit-id inserts, so hard-coded IDs are
 * fragile). Silently skips link rows for which the activity or waiver type
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
            ['activity' => 'Armored Combat',   'waiver_type' => 'General Liability Waiver'],
            ['activity' => 'Rapier Combat',    'waiver_type' => 'General Liability Waiver'],
            ['activity' => 'Youth Combat',     'waiver_type' => 'Youth Participation Waiver'],
            ['activity' => 'Youth Combat',     'waiver_type' => 'General Liability Waiver'],
            ['activity' => 'Archery',          'waiver_type' => 'General Liability Waiver'],
            ['activity' => 'Thrown Weapons',   'waiver_type' => 'General Liability Waiver'],
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
        foreach ($this->fetchAll("SELECT id, name FROM gathering_activities") as $row) {
            $activities[$row['name']] = (int)$row['id'];
        }
        $waiverTypes = [];
        foreach ($this->fetchAll("SELECT id, name FROM waivers_waiver_types") as $row) {
            $waiverTypes[$row['name']] = (int)$row['id'];
        }

        $now = '2025-01-01 10:00:00';
        $toInsert = [];
        foreach ($this->getData() as $link) {
            $actId = $activities[$link['activity']] ?? null;
            $wtId = $waiverTypes[$link['waiver_type']] ?? null;
            if ($actId === null || $wtId === null) {
                continue;
            }
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
