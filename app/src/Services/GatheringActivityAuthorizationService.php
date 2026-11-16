<?php
declare(strict_types=1);

namespace App\Services;

use App\Model\Entity\Gathering;
use App\Policy\GatheringActivityPolicy;
use App\Policy\GatheringPolicy;
use Cake\Http\Exception\ForbiddenException;
use Cake\ORM\Locator\LocatorAwareTrait;

/** Rechecks live event activity authority inside mutation transactions. */
class GatheringActivityAuthorizationService
{
    use LocatorAwareTrait;

    /** Reload persisted context; callers hold the transaction through their write. */
    public function assertActivity(int $gatheringId, int $activityId, int $actorId): Gathering
    {
        $gathering = $this->fetchTable('Gatherings')->get($gatheringId);
        $activity = $this->fetchTable('GatheringActivities')->get($activityId);
        $actor = $this->fetchTable('Members')->get($actorId);
        if (!(new GatheringActivityPolicy())->canAssign($actor, $activity, $gathering)) {
            throw new ForbiddenException(__('You are not authorized to manage this activity at this gathering.'));
        }

        return $gathering;
    }

    /** Validate both typed activity ownership and the editor-only Other schedule path. */
    public function assertSchedule(int $gatheringId, array $data, int $actorId): Gathering
    {
        if (filter_var($data['is_other'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $gathering = $this->fetchTable('Gatherings')->get($gatheringId);
            $actor = $this->fetchTable('Members')->get($actorId);
            if (!$actor->isSuperUser() && !(new GatheringPolicy())->canEdit($actor, $gathering)) {
                throw new ForbiddenException(__('You are not authorized to schedule Other activities.'));
            }

            return $gathering;
        }
        $activityId = (int)($data['gathering_activity_id'] ?? 0);
        $gathering = $this->assertActivity($gatheringId, $activityId, $actorId);
        if (
            !$this->fetchTable('GatheringsGatheringActivities')->exists([
                'gathering_id' => $gatheringId, 'gathering_activity_id' => $activityId,
            ])
        ) {
            throw new ForbiddenException(__('Only activities attached to this gathering may be scheduled.'));
        }

        return $gathering;
    }

    /** Both the stored schedule row and any replacement activity must be authorized. */
    public function assertScheduledOwner(int $gatheringId, int $scheduledId, int $actorId): void
    {
        $gathering = $this->fetchTable('Gatherings')->get($gatheringId);
        $scheduled = $this->fetchTable('GatheringScheduledActivities')->get($scheduledId);
        $actor = $this->fetchTable('Members')->get($actorId);
        if (
            (int)$scheduled->gathering_id !== $gatheringId
            || (!$actor->isSuperUser()
                && !(new GatheringPolicy())->canEditScheduledActivity($actor, $gathering, $scheduled))
        ) {
            throw new ForbiddenException(__('You are not authorized to manage this scheduled activity.'));
        }
    }
}
