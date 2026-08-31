<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\ActiveWindowBaseEntity;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\Member;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Throwable;

/**
 * role policy
 */
class MemberPolicy extends BasePolicy
{
    private const IMPORT_MEMBER_DATA_PERMISSION = 'Can Import Member Data';

    private const KINGDOM_SENESCHAL_OFFICE = 'Kingdom Seneschal';

    /**
     * Check if $user can view Member
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canView(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        if ($entity instanceof Member && $user instanceof Member && $user->canManageMember($entity)) {
            return true;
        }

        return parent::canView($user, $entity);
    }

    /**
     * Check if $user can view PII for a Member
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canViewPii(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        if ($entity instanceof Member && $user instanceof Member && $user->canManageMember($entity)) {
            return true;
        }

        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can view their own profile
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canProfile(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Check if $user can partial edit Member
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canPartialEdit(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity instanceof Member && $user instanceof Member && $user->canManageMember($entity)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can submit sca member info.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canSubmitScaMemberInfo(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity instanceof Member && $user instanceof Member && $user->canManageMember($entity)) {
            return true;
        }

        return false;
    }

    /**
     * Check if $user can view card
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canViewCard(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity instanceof Member && $user instanceof Member && $user->canManageMember($entity)) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can send mobile card email
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canSendMobileCardEmail(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity instanceof Member && $user instanceof Member && $user->canManageMember($entity)) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can add note
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canAddNote(KmpIdentityInterface $user, BaseEntity|Table $entity, mixed ...$optionalArgs): bool
    {
        if ($entity instanceof Member && $user instanceof Member && $user->canManageMember($entity)) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can view additional information for a Member
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canViewAdditionalInformation(
        KmpIdentityInterface $user,
        BaseEntity|Table $entity,
        ...$optionalArgs,
    ): bool {
        if ($entity instanceof Member && $user instanceof Member && $user->canManageMember($entity)) {
            return true;
        }

        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can change password
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canChangePassword(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity instanceof Member && $user instanceof Member && $user->canManageMember($entity)) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can view card json
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canViewCardJson(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity instanceof Member && $user instanceof Member && $user->canManageMember($entity)) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can delete Member
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canDelete(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        //only super users can delete and they should never get hear because of the before policy check.
        return false;
    }

    /**
     * Check if $user can import expiration dates
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canImportExpirationDates(
        KmpIdentityInterface $user,
        BaseEntity $entity,
        mixed ...$optionalArgs,
    ): bool {
        $method = __FUNCTION__;

        if ($this->_isSuperUser($user)) {
            return true;
        }
        if (!$this->_hasPolicy($user, $method, $entity)) {
            return false;
        }

        $hasNarrowImportPermission = false;
        foreach ($this->_getPermissions($user) ?? [] as $permission) {
            if (
                ($permission->name ?? null) !== self::IMPORT_MEMBER_DATA_PERMISSION
                || !isset($permission->policies[self::class][$method])
            ) {
                continue;
            }
            $hasNarrowImportPermission = true;
            break;
        }

        return $hasNarrowImportPermission && $this->isCurrentKingdomSeneschal($user);
    }

    /**
     * Confirm the narrow import grant belongs to the current Kingdom Seneschal.
     *
     * @param \App\KMP\KmpIdentityInterface $user The user being authorized.
     * @return bool
     */
    private function isCurrentKingdomSeneschal(KmpIdentityInterface $user): bool
    {
        try {
            $member = $user->getAsMember();
            $now = DateTime::now();
            $officers = TableRegistry::getTableLocator()->get('Officers.Officers');

            return $officers->find()
                ->innerJoinWith('Offices')
                ->where([
                    'Officers.member_id' => (int)$member->id,
                    'Officers.status' => ActiveWindowBaseEntity::CURRENT_STATUS,
                    'Officers.start_on <=' => $now,
                    'OR' => [
                        'Officers.expires_on IS' => null,
                        'Officers.expires_on >=' => $now,
                    ],
                    'Officers.revoker_id IS' => null,
                    'Offices.name' => self::KINGDOM_SENESCHAL_OFFICE,
                    'Offices.applicable_branch_types LIKE' => '%"Kingdom"%',
                    'Offices.deleted IS' => null,
                ])
                ->count() > 0;
        } catch (Throwable $exception) {
            Log::warning(
                'Unable to validate the Kingdom Seneschal member-data import grant: '
                . $exception->getMessage(),
            );

            return false;
        }
    }

    /**
     * Check if $user can verify membership
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canVerifyMembership(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can verify queue
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canVerifyQueue(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if $user can edit additional info
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @param mixed ...$optionalArgs Optional arguments
     * @return bool
     */
    public function canEditAdditionalInfo(KmpIdentityInterface $user, BaseEntity $entity, mixed ...$optionalArgs): bool
    {
        if ($entity instanceof Member && $user instanceof Member && $user->canManageMember($entity)) {
            return true;
        }
        $method = __FUNCTION__;

        return $this->_hasPolicy($user, $method, $entity);
    }
}
