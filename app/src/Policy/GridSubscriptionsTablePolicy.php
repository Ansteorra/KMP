<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

class GridSubscriptionsTablePolicy extends BasePolicy
{
    /** Allow members to manage their own subscriptions. */
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /** Allow opt-in; the report service also checks the selected grid’s access. */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /** Limit the list to the authenticated member. */
    public function scopeIndex(KmpIdentityInterface $user, $query)
    {
        return $query->where(['GridSubscriptions.member_id' => (int)$user->getIdentifier()]);
    }
}
