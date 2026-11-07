<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\Gathering;
use App\Model\Entity\GatheringActivity;
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
    /**
     * Check if user can index.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
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
     * @param \App\KMP\KmpIdentityInterface $user Identity
     * @param \App\Model\Entity\BaseEntity $entity Gathering
     * @param mixed ...$optionalArgs Activity entity
     * @return bool
     */
    public function canManageActivity(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $activity = $optionalArgs[0] ?? null;

        return $entity instanceof Gathering && $activity instanceof GatheringActivity
            && (new GatheringActivityPolicy())->canAssign($user, $activity, $entity);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user Identity
     * @param \App\Model\Entity\BaseEntity $entity Gathering
     * @param mixed ...$optionalArgs Unused
     * @return bool
     */
    public function canCreateScheduledActivity(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->canEdit($user, $entity)) {
            return true;
        }
        $activities = $entity->gathering_activities ?? TableRegistry::getTableLocator()->get('GatheringActivities')
            ->find()->innerJoinWith('Gatherings', fn($query) => $query->where(['Gatherings.id' => $entity->id]))
            ->all()->toList();
        foreach ($activities as $activity) {
            if ($this->canManageActivity($user, $entity, $activity)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user Identity
     * @param \App\Model\Entity\BaseEntity $entity Gathering
     * @param mixed ...$optionalArgs Persisted scheduled activity
     * @return bool
     */
    public function canEditScheduledActivity(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $scheduled = $optionalArgs[0] ?? null;
        if (!$scheduled || (int)$scheduled->gathering_id !== (int)$entity->id) {
            return false;
        }
        if (!$scheduled->gathering_activity_id || $scheduled->is_other) {
            return $this->canEdit($user, $entity);
        }
        $activity = TableRegistry::getTableLocator()->get('GatheringActivities')
            ->get((int)$scheduled->gathering_activity_id);

        return $this->canManageActivity($user, $entity, $activity);
    }

    /**
     * @param \App\KMP\KmpIdentityInterface $user Identity
     * @param \App\Model\Entity\BaseEntity $entity Gathering
     * @param mixed ...$optionalArgs Persisted scheduled activity
     * @return bool
     */
    public function canDeleteScheduledActivity(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEditScheduledActivity($user, $entity, ...$optionalArgs);
    }

    /**
     * Check if user can cancel.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canCancel(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Check if user can uncancel.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canUncancel(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        return $this->canEdit($user, $entity, ...$optionalArgs);
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

    /**
     * Check if user can calendar.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @return bool
     */
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
        if ($this->canViewManagement($user, $entity)) {
            return true;
        }

        if ($entity instanceof BaseEntity) {
            foreach (TableRegistry::getTableLocator()->get('GatheringActivities')->find()->all() as $activity) {
                if ($this->canManageActivity($user, $entity, $activity)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Activity access exposes preparation tools, not private staff or general record details. */
    public function canViewManagement(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_hasPolicy($user, 'canView', $entity)
            || ($entity instanceof BaseEntity && $this->canEdit($user, $entity));
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

    /**
     * Check if user can publish the gathering
     *
     * Users can publish the gathering if they have the standard publish permission
     *
     * @param \App\KMP\KmpIdentityInterface $user The user
     * @param \App\Model\Entity\BaseEntity $entity The gathering entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canPublish(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Check standard permission first
        if ($this->_hasPolicy($user, __FUNCTION__, $entity)) {
            return true;
        }

        return false;
    }
}
