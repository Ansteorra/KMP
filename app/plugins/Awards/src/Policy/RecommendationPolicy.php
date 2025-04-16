<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use Authorization\IdentityInterface;
use Cake\ORM\TableRegistry;

/**
 * DomainPolicy policy
 */
class RecommendationPolicy extends BasePolicy
{

    public function canViewSubmittedByMember(IdentityInterface $user, $entity, ...$args)
    {
        if ($entity->requester_id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canViewSubmittedForMember(IdentityInterface $user, $entity, ...$args)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canViewEventRecommendations(IdentityInterface $user, $entity, ...$args)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canExport(IdentityInterface $user, $entity, ...$args)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canUseBoard(IdentityInterface $user, $entity, ...$args)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canViewHidden(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canViewPrivateNotes(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canAddNote(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canUpdateStates(IdentityInterface $user, $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    public function canAdd(IdentityInterface $user, $entity, ...$optionalArgs)
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