<?php
declare(strict_types=1);

use App\Services\GatheringActivitySeedService;
use Migrations\BaseSeed;

/** Seeds normal reusable activities with public types, appearance defaults and stable IDs. */
class DevLoadGatheringActivitiesSeed extends BaseSeed
{
    /** @return array<array<string, mixed>> Normal catalogue seed rows */
    public function getData(): array
    {
        $rows = [];
        foreach (GatheringActivitySeedService::definitions() as $name => $defaults) {
            $rows[] = ['name' => $name, 'icon_source' => 'catalog',
                'created' => '2025-01-01 10:00:00', 'modified' => '2025-01-01 10:00:00'] + $defaults;
        }

        return $rows;
    }

    /** @return void */
    public function run(): void
    {
        (new GatheringActivitySeedService())->seed(refreshIcons: true, refreshColors: true);
    }
}
