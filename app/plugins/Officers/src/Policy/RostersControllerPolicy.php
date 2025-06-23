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

class RostersControllerPolicy extends BasePolicy
{
    /**
     * Check if the user can view the reports
     * @param KmpIdentityInterface $user The user
     * @param Array $entity The entity
     * @return bool
     */
    public function canCreateRoster(KmpIdentityInterface $user, array $urlProps, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

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