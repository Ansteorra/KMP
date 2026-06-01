<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Model\Entity\BaseEntity;
use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;
use Cake\ORM\Table;

/**
 * Authorization policy for Bestowal entities in the Awards plugin.
 *
 * @method bool canView(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canEdit(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity, mixed ...$optionalArgs)
 * @see \App\Policy\BasePolicy Base authorization functionality
 */
class BestowalPolicy extends BasePolicy
{
    /**
     * Check if user can view bestowals for a specific gathering.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$args Additional authorization context (typically gathering entity)
     * @return bool True if authorized
     */
    public function canViewGatheringBestowals(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can prepare scrolls for bestowals.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canPrepareScrolls(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can manage court schedule for bestowals.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canManageCourtSchedule(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can view hidden bestowal states.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canViewHidden(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }
}
