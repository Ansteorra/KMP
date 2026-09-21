<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Authorization\IdentityInterface;
use Authorization\Policy\ResultInterface;

class GridSubscriptionPolicy extends BasePolicy
{
    /** Personal subscriptions stay owner-only, including for administrators. */
    public function before(?IdentityInterface $user, mixed $resource, string $action): ResultInterface|bool|null
    {
        return null;
    }

    /** Allow cancellation only by the subscription owner. */
    public function canDelete(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return (int)$user->getIdentifier() === (int)$entity->member_id;
    }
}
