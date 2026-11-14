<?php
declare(strict_types=1);

namespace App\Services;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\Gathering;
use App\Model\Entity\GatheringActivity;

/** Plugin-owned event activity access; implementations must not follow inherited grants. */
interface GatheringActivityDelegationProviderInterface
{
    /** Resolve current native grantor authority for this exact event and activity. */
    public function canAssign(KmpIdentityInterface $user, GatheringActivity $activity, Gathering $gathering): bool;

    /**
     * Evaluate candidates using fresh operation-local data, including transactional grant locks.
     *
     * @param array<\App\Model\Entity\GatheringActivity> $activities Catalogue candidates.
     */
    public function canAssignAny(KmpIdentityInterface $user, array $activities, Gathering $gathering): bool;
}
