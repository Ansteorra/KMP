<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Model\Entity\BaseEntity;
use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;
use Cake\ORM\TableRegistry;
use Cake\ORM\Table;

/**
 * Authorization policy for Recommendation entities in the Awards plugin.
 *
 * Implements state machine management, workflow authorization, and dynamic approval
 * level validation. Supports dynamic canApproveLevel* methods based on award levels.
 *
 * @method bool canView(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canEdit(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canDelete(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity, mixed ...$optionalArgs)
 * @see \App\Policy\BasePolicy Base authorization functionality
 * @see /docs/5.2.13-awards-recommendation-policy.md Full documentation
 */
class RecommendationPolicy extends BasePolicy
{
    /**
     * Check if user can edit a recommendation.
     *
     * Denies edit when the recommendation is locked to an active bestowal workflow.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->isLockedByBestowal($entity)) {
            return false;
        }

        return parent::canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * @inheritdoc
     */
    public function canDelete(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->isLockedByBestowal($entity)) {
            return false;
        }

        return parent::canDelete($user, $entity, ...$optionalArgs);
    }

    /**
     * @inheritdoc
     */
    public function canUpdateStates(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->isLockedByBestowal($entity)) {
            return false;
        }

        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Determine whether a recommendation is locked by a linked bestowal.
     *
     * @param \App\Model\Entity\BaseEntity $entity Recommendation entity
     * @return bool
     */
    protected function isLockedByBestowal(BaseEntity $entity): bool
    {
        if (!$entity instanceof \Awards\Model\Entity\Recommendation) {
            return false;
        }

        return $entity->isLockedByBestowal();
    }

    /**
     * Check if user can view recommendations they submitted.
     *
     * Grants direct access if user is the requester, otherwise delegates to permission check.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$args Additional authorization context
     * @return bool True if authorized
     */
    public function canViewSubmittedByMember(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        if ($this->canManageRecommendationMember($user, (int)$entity->requester_id)) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Determine whether the user can manage recommendation actions for a member.
     *
     * Allows self or parent-of-minor access.
     *
     * @param \App\KMP\KmpIdentityInterface $user
     * @param int $memberId
     * @return bool
     */
    protected function canManageRecommendationMember(KmpIdentityInterface $user, int $memberId): bool
    {
        if ($memberId <= 0) {
            return false;
        }

        if ($user instanceof \App\Model\Entity\Member) {
            $target = new \App\Model\Entity\Member();
            $target->id = $memberId;
            return $user->canManageMember($target);
        }

        return false;
    }

    /**
     * Check if user can view recommendations submitted for a specific member.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$args Additional authorization context
     * @return bool True if authorized
     */
    public function canViewSubmittedForMember(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can view recommendations for a specific event.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$args Additional authorization context
     * @return bool True if authorized
     */
    public function canViewEventRecommendations(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can view recommendations for a specific gathering.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$args Additional authorization context (typically gathering entity)
     * @return bool True if authorized
     */
    public function canViewGatheringRecommendations(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can export recommendation data.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$args Additional authorization context
     * @return bool True if authorized
     */
    public function canExport(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can view hidden recommendations.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canViewHidden(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can view private notes on recommendations.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canViewPrivateNotes(KmpIdentityInterface $user, BaseEntity  $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can add notes to recommendations.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canAddNote(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->isLockedByBestowal($entity)) {
            return false;
        }

        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Check if user can add new recommendations.
     *
     * Open authorization - any authenticated user can submit recommendations.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The recommendation context
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool Always true for open submission
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Check if user can group/ungroup recommendations.
     *
     * Delegates to edit permission — same users who can edit can group.
     *
     * @param \App\KMP\KmpIdentityInterface $user The authenticated user
     * @param \App\Model\Entity\BaseEntity $entity The recommendation entity
     * @param mixed ...$optionalArgs Additional authorization context
     * @return bool True if authorized
     */
    public function canGroup(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($this->isLockedByBestowal($entity)) {
            return false;
        }

        return $this->canEdit($user, $entity, ...$optionalArgs);
    }

    /**
     * Handle dynamic approval authority methods (canApproveLevel*).
     *
     * Resolves level-specific approval methods dynamically based on award levels.
     *
     * @param string $name The method name (e.g., 'canApproveLevelAoA')
     * @param array $arguments [$user, $entity, ...args]
     * @return bool True if user has approval authority for the level
     * @throws \BadMethodCallException When method is not a recognized dynamic method
     */
    public function __call($name, $arguments)
    {
        if (strpos($name, 'canApproveLevel') === 0) {
            $user = $arguments[0] ?? null;
            $entity = $arguments[1] ?? null;
            return $this->_hasPolicy($user, $name, $entity);
        }

        throw new \BadMethodCallException("Method {$name} does not exist");
    }

    /**
     * Get names of dynamically generated approval methods.
     *
     * Returns canApproveLevel* method names based on current award levels.
     *
     * @return array List of dynamic method names
     */
    public static function getDynamicMethods(): array
    {
        $dynamicMethods = [];

        $levelsTable = TableRegistry::getTableLocator()->get('Awards.Levels');
        $levelNames = $levelsTable->getAllLevelNames();

        foreach ($levelNames as $levelName) {
            $methodName = 'canApproveLevel' . $levelName;
            $dynamicMethods[] = $methodName;
        }

        return $dynamicMethods;
    }
}
