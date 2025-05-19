<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Model\Entity\BaseEntity;
use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;
use Cake\ORM\TableRegistry;
use Cake\ORM\Table;

/**
 * DomainPolicy policy
 */
class RecommendationPolicy extends BasePolicy
{
    /**
     * Check if $user can view RolesPermissions
     *
     * @param \App\KMP\KmpIdentityInterface $user The user.
     * @param \App\Model\Entity\BaseEntity $entity
     * @return bool
     */
    public function canViewSubmittedByMember(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        if ($entity->requester_id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canViewSubmittedForMember(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canViewEventRecommendations(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canExport(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canUseBoard(KmpIdentityInterface $user, BaseEntity $entity, ...$args): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canViewHidden(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canViewPrivateNotes(KmpIdentityInterface $user, BaseEntity  $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canAddNote(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canUpdateStates(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return true;
    }

    /**
     * Magic method to handle calls to dynamic methods based on level names
     *
     * @param string $name The method name
     * @param array $arguments The arguments passed to the method
     * @return bool
     */
    public function __call($name, $arguments)
    {
        // Check if this is a level approval method (canApproveLevelX)
        if (strpos($name, 'canApproveLevel') === 0) {
            $user = $arguments[0] ?? null;
            $entity = $arguments[1] ?? null;
            return $this->_hasPolicy($user, $name, $entity);
        }

        throw new \BadMethodCallException("Method {$name} does not exist");
    }

    /**
     * Returns names of dynamically generated methods based on level names
     * Used for policy discovery via reflection
     * 
     * @return array List of dynamic method names
     */
    public static function getDynamicMethods(): array
    {
        $dynamicMethods = [];

        // Get all level names from the LevelsTable
        $levelsTable = TableRegistry::getTableLocator()->get('Awards.Levels');
        $levelNames = $levelsTable->getAllLevelNames();

        // Create method names for each level
        foreach ($levelNames as $levelName) {
            $methodName = 'canApproveLevel' . $levelName;
            $dynamicMethods[] = $methodName;
        }

        return $dynamicMethods;
    }
}