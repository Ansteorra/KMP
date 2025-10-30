<?php

declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * DevLoadGatheringActivityWaiversSeed
 *
 * Development seed data linking activities to required waiver types.
 * Defines which waivers are required for which activities.
 */
class DevLoadGatheringActivityWaiversSeed extends BaseSeed
{
    /**
     * Get seed data
     *
     * Links template activities to waiver types:
     * - Combat activities require General Liability waiver
     * - Youth Combat requires Youth Participation waiver
     * - Martial activities (archery, thrown weapons) require General Liability
     * - Arts & Sciences has no waiver requirements
     *
     * @return array<int, array<string, mixed>>
     */
    public function getData(): array
    {
        return [
            // Armored Combat requires General Liability waiver
            [
                'id' => 1,
                'gathering_activity_id' => 1, // Armored Combat
                'waiver_type_id' => 1, // General Liability Waiver
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            // Rapier Combat requires General Liability waiver
            [
                'id' => 2,
                'gathering_activity_id' => 2, // Rapier Combat
                'waiver_type_id' => 1, // General Liability Waiver
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            // Youth Combat requires Youth Participation waiver
            [
                'id' => 3,
                'gathering_activity_id' => 3, // Youth Combat
                'waiver_type_id' => 2, // Youth Participation Waiver
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            // Youth Combat also requires General Liability waiver
            [
                'id' => 4,
                'gathering_activity_id' => 3, // Youth Combat
                'waiver_type_id' => 1, // General Liability Waiver
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            // Archery requires General Liability waiver
            [
                'id' => 5,
                'gathering_activity_id' => 4, // Archery
                'waiver_type_id' => 1, // General Liability Waiver
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            // Thrown Weapons requires General Liability waiver
            [
                'id' => 6,
                'gathering_activity_id' => 5, // Thrown Weapons
                'waiver_type_id' => 1, // General Liability Waiver
                'created' => '2025-01-01 10:00:00',
                'modified' => '2025-01-01 10:00:00',
            ],
            // Arts & Sciences Class has no waiver requirements (not in this seed)
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

        $table = $this->table('waivers_gathering_activity_waivers');
        $table->insert($data)->save();
    }
}
