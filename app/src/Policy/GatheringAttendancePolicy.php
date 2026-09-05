<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\Member;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * GatheringAttendance policy
 *
 * Extends BasePolicy to leverage the standard RBAC permission system while also
 * allowing members to manage their own gathering attendance records.
 */
class GatheringAttendancePolicy extends BasePolicy
{
    /** Consent and recipient scope for attendance enrichment outside the owner's page. */
    public function canViewShared(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->canManageMemberAttendance($user, (int)$entity->member_id)) {
            return true;
        }
        $member = $entity->member ?? TableRegistry::getTableLocator()->get('Members')
            ->find()->where(['id' => (int)$entity->member_id])->first();
        if (!$member || !$member->branch_id) {
            return false;
        }
        if (
            $entity->share_with_crown && $this->_hasPolicy(
                $user,
                'canViewCrown',
                $entity,
                (int)$member->branch_id,
            )
        ) {
            return true;
        }
        if (
            $entity->share_with_hosting_group && $entity->gathering
            && $user->can('viewAttendance', $entity->gathering)
        ) {
            return true;
        }
        if (!$entity->share_with_kingdom || !$user instanceof Member || !$user->branch_id) {
            return false;
        }
        $branches = TableRegistry::getTableLocator()->get('Branches');
        $memberPath = $branches->getAllParents((int)$member->branch_id);
        $actorPath = $branches->getAllParents((int)$user->branch_id);

        return (int)($memberPath[array_key_first($memberPath)] ?? $member->branch_id)
            === (int)($actorPath[array_key_first($actorPath)] ?? $user->branch_id);
    }

    /** Explicit crown-recipient grant, populated by the Awards permission migration. */
    public function canViewCrown(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $member = $entity->member;

        return $member && $member->branch_id
            && $this->_hasPolicy($user, __FUNCTION__, $entity, (int)$member->branch_id);
    }

    /**
     * Check if user can add gathering attendance
     *
     * Users can register their own attendance or users with appropriate
     * permissions can register attendance for others.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The entity or table
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // If this is a new entity with member_id set, check if it's for the current user
        if ($entity instanceof BaseEntity && isset($entity->member_id)) {
            if ($this->canManageMemberAttendance($user, (int)$entity->member_id)) {
                return true;
            }
        }

        // Otherwise check standard policy permissions
        return parent::canAdd($user, $entity, ...$optionalArgs);
    }

    /**
     * Check if user can edit gathering attendance
     *
     * Users can edit their own attendance or users with appropriate
     * permissions can edit others' attendance.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Users can edit their own attendance
        if ($this->canManageMemberAttendance($user, (int)$entity->member_id)) {
            return true;
        }

        // Otherwise check standard policy permissions
        return parent::canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Check if user can delete gathering attendance
     *
     * Users can delete their own attendance or users with appropriate
     * permissions can delete others' attendance.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity The entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canDelete(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        // Users can delete their own attendance
        if ($this->canManageMemberAttendance($user, (int)$entity->member_id)) {
            return true;
        }

        // Otherwise check standard policy permissions
        return parent::canDelete($user, $entity, ...$optionalArgs);
    }

    /**
     * Check if user can view gathering attendance
     *
     * Users can view their own attendance or users with appropriate
     * permissions can view others' attendance.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The entity or table
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canView(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        // Users can view their own attendance
        if ($entity instanceof BaseEntity && $this->canManageMemberAttendance($user, (int)$entity->member_id)) {
            return true;
        }

        // Otherwise check standard policy permissions
        return parent::canView($user, $entity, ...$optionalArgs);
    }

    /**
     * Determine whether the user can manage attendance for a member.
     *
     * Allows self or parent-of-minor access.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param int $memberId
     * @return bool
     */
    protected function canManageMemberAttendance(KmpIdentityInterface $user, int $memberId): bool
    {
        if ($memberId <= 0) {
            return false;
        }

        if ($user instanceof Member) {
            $target = new Member();
            $target->id = $memberId;

            return $user->canManageMember($target);
        }

        return false;
    }
}
