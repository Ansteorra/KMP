<?php
declare(strict_types=1);

namespace App\Services\ActionItems;

use App\Model\Entity\ActionItem;

/** Optional owner-specific, non-transitive ActionItem delegation. */
interface ActionItemDelegationProviderInterface
{
    /** Check a delegated assignment without following delegated grantors. */
    public function isMemberEligible(ActionItem $item, int $memberId, ActionItemAssigneeResolver $resolver): bool;

    /** @return list<int> */
    public function getEligibleMemberIds(ActionItem $item, ActionItemAssigneeResolver $resolver): array;

    /** Candidate conditions must still be checked by the live eligibility resolver. */
    public function candidateConditions(int $memberId): array;
}
