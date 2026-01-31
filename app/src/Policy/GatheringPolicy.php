<?php

declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\Gathering;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * Gathering Policy
 *
 * Manages authorization for gathering operations.
 * Authorization is driven by the Roles → Permissions → Policies system,
 * with additional permissions for stewards (event staff marked as stewards).
 */
class GatheringPolicy extends BasePolicy
{
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Check if user can edit a gathering
     *
     * Users can edit if they have the standard edit permission OR
     * if they are a steward for the gathering.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The gathering entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Check standard permission first
        if ($this->_hasPolicy($user, __FUNCTION__, $entity)) {
            return true;
        }

        // Check if user is a steward for this gathering
        return $this->_isGatheringSteward($user, $entity);
    }

    /**
     * Check if user can view attendance information for a gathering
     *
     * Users with appropriate permissions can view attendance details including
     * total count and list of attendees who have shared with the hosting group.
     * Stewards can also view attendance for gatherings they manage.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The gathering entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canViewAttendance(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Check standard permission first
        if ($this->_hasPolicy($user, __FUNCTION__, $entity)) {
            return true;
        }

        // Check if user is a steward for this gathering
        return $this->_isGatheringSteward($user, $entity);
    }

    /**
     * Check if user can quick view a gathering
     *
     * All authenticated users can quick view any gathering.
     * This provides basic gathering information without requiring special permissions.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The gathering entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool Always returns true for authenticated users
     */
    public function canQuickView(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return true;
    }

    public function canCalendar(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Check if user can view the full gathering details
     *
     * Users can view the full gathering details if they have the standard view permission
     * OR if they are a steward for the gathering.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The gathering entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canView(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Check standard permission first
        if ($this->_hasPolicy($user, __FUNCTION__, $entity)) {
            return true;
        }

        // Check if user is a steward for this gathering
        if ($entity instanceof BaseEntity) {
            return $this->_isGatheringSteward($user, $entity);
        }

        return false;
    }

    /**
     * Check if user is a steward for the given gathering
     *
     * Queries the gathering_staff table to see if the user is assigned
     * as a steward (is_steward = true) for this gathering.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The gathering entity
     * @return bool True if user is a steward for this gathering
     */
    protected function _isGatheringSteward(KmpIdentityInterface $user, BaseEntity $entity): bool
    {
        // Entity must be a Gathering with an ID
        if (!($entity instanceof Gathering) || empty($entity->id)) {
            return false;
        }

        $userId = $user->getIdentifier();
        if (empty($userId)) {
            return false;
        }

        $gatheringStaffTable = TableRegistry::getTableLocator()->get('GatheringStaff');

        $stewardRecord = $gatheringStaffTable->find()
            ->where([
                'GatheringStaff.gathering_id' => $entity->id,
                'GatheringStaff.member_id' => $userId,
                'GatheringStaff.is_steward' => true,
            ])
            ->first();

        return $stewardRecord !== null;
    }
}