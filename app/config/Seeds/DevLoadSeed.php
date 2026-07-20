<?php

declare(strict_types=1);

use Migrations\BaseSeed;

/**
 * DevLoadSeed
 *
 * Master entrypoint for dev-only fixture data. Invokes each DevLoad* seed
 * in the order required by foreign-key dependencies.
 *
 * Intended for `bin/cake migrations seed --seed DevLoadSeed` after a fresh
 * `migrations migrate` + `updateDatabase`. Each child seed is idempotent
 * so re-runs are safe.
 */
class DevLoadSeed extends BaseSeed
{
    /**
     * Run all development seed data in FK-safe order.
     */
    public function run(): void
    {
        $this->call('DevLoadGatheringTypesSeed', ['source' => 'Seeds']);
        $this->call('DevLoadGatheringActivitiesSeed', ['source' => 'Seeds']);
        $this->call('DevLoadGatheringsSeed', ['source' => 'Seeds']);
        $this->call('DevLoadGatheringsGatheringActivitiesSeed', ['source' => 'Seeds']);
        $this->call('DevLoadGatheringActivityWaiversSeed', [
            'source' => 'Seeds',
            'plugin' => 'Waivers',
        ]);
    }
}
