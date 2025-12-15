<?php

namespace Officers\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * Rosters Controller Authorization Policy
 *
 * Provides URL-based and entity-based authorization control for the RostersController.
 * Implements controller-level access control for roster management operations including
 * roster generation, warrant processing, and organizational reporting.
 *
 * @see /docs/5.1-officers-plugin.md
 */
class RostersControllerPolicy extends BasePolicy
{
    /**
     * Check if user can create roster via URL-based authorization.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param array $urlProps URL properties for context
     * @param mixed ...$optionalArgs Additional parameters
     * @return bool
     */
    public function canCreateRoster(KmpIdentityInterface $user, array $urlProps, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

    /**
     * Check if user can add roster. Handles Table, array, and Entity inputs.
     *
     * @param KmpIdentityInterface $user The authenticated user
     * @param BaseEntity|Table|array $entity The entity, table, or data for validation
     * @param mixed ...$optionalArgs Additional parameters
     * @return bool
     */
    public function canAdd(KmpIdentityInterface $user, BaseEntity|Table|array $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        if ($entity instanceof Table) {
            $entity = $entity->newEntity([]);
        } elseif (is_array($entity)) {
            $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
            $entity = $warrantRosterTable->newEntity($entity);
        }

        return $this->_hasPolicy($user, $method, $entity);
    }
}
