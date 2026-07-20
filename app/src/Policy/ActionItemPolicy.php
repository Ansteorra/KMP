<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\ActionItem;
use App\Model\Entity\BaseEntity;
use App\Services\ActionItems\ActionItemAssigneeResolver;
use Cake\ORM\Table;

/**
 * ActionItem entity policy.
 *
 * Super users bypass via BasePolicy::before(). For everyone else, the ability
 * to complete/reopen a to-do is gated by assignee eligibility (the same "who
 * can act" resolution used to populate the My To-Dos queue).
 */
class ActionItemPolicy extends BasePolicy
{
    /**
     * @var \App\Services\ActionItems\ActionItemAssigneeResolver
     */
    protected ActionItemAssigneeResolver $resolver;

    /**
     * @param \App\Services\ActionItems\ActionItemAssigneeResolver|null $resolver Eligibility resolver
     */
    public function __construct(?ActionItemAssigneeResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new ActionItemAssigneeResolver();
    }

    /**
     * View is allowed to any authenticated user; listings are already scoped.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The action item
     * @param mixed ...$optionalArgs Context
     * @return bool
     */
    public function canView(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * Complete a to-do — gated by assignee eligibility.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The action item
     * @param mixed ...$optionalArgs Context
     * @return bool
     */
    public function canComplete(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->isEligible($user, $entity);
    }

    /**
     * Reopen a to-do — gated by assignee eligibility.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The action item
     * @param mixed ...$optionalArgs Context
     * @return bool
     */
    public function canReopen(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->isEligible($user, $entity);
    }

    /**
     * Resolve assignee eligibility for the acting member.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The action item
     * @return bool
     */
    protected function isEligible(KmpIdentityInterface $user, BaseEntity|Table $entity): bool
    {
        if (!$entity instanceof ActionItem) {
            return false;
        }
        $memberId = (int)$user->getIdentifier();

        return $memberId > 0 && $this->resolver->isMemberEligible($entity, $memberId);
    }
}
