<?php
declare(strict_types=1);

namespace Awards\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Policy\BasePolicy;

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
     * Authorize gathering-scoped bestowal grid data.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canGatheringBestowalsGridData(
        KmpIdentityInterface $user,
        BaseEntity $entity,
        ...$optionalArgs,
    ): bool {
        return $this->canViewGatheringBestowals($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize single bestowal state updates.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canUpdateState(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize bulk bestowal state updates.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canUpdateStates(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize single bestowal edit modal loading.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canTurboEditForm(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize bulk bestowal edit modal loading.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canTurboBulkEditForm(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize court-slot lookup data used by bestowal edit forms.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canCourtSlotsForGathering(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize gathering autocomplete used by single bestowal edit forms.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canGatheringsForBestowalAutoComplete(
        KmpIdentityInterface $user,
        BaseEntity $entity,
        ...$optionalArgs,
    ): bool {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize gathering autocomplete used by bulk bestowal edit forms.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canGatheringsForBestowalBulkAutoComplete(
        KmpIdentityInterface $user,
        BaseEntity $entity,
        ...$optionalArgs,
    ): bool {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize bestowal cancellation.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canCancel(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Authorize ad-hoc bestowal creation.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The bestowal entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canAdHoc(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

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
