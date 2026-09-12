<?php
declare(strict_types=1);

namespace App\Services\ActionItems;

use App\Model\Entity\ActionItem;
use App\Services\ServiceResult;

/** Optional owner-specific mutations, executed while the owner and item are locked. */
interface ActionItemLifecycleProviderInterface
{
    /** Apply owner mutations inside the ActionItem transaction; failure rolls it back. */
    public function afterTransition(
        ActionItem $item,
        int $actorId,
        string $fromStatus,
        array $data = [],
    ): ServiceResult;

    /** Describe the effects before a user confirms the transition. */
    public function transitionConfirmation(ActionItem $item, string $operation): string;
}
