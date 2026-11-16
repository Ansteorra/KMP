<?php
declare(strict_types=1);

namespace App\Model\Entity;

use App\Services\GatheringActivityOrderingService;

/** Orders loaded activity lists without modifying persisted assignment/schedule order. */
trait OrderedGatheringActivitiesTrait
{
    /**
     * @param array|null $activities Loaded gathering activities
     * @return array|null Sorted activities, or null when the association was not loaded
     */
    protected function _getGatheringActivities(?array $activities): ?array
    {
        return $activities === null ? null : GatheringActivityOrderingService::sort($activities);
    }
}
