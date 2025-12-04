<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Table\AuthorizationApprovalsTable;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Activities\Model\Table\ActivitiesTable;
use App\Model\Entity\BaseEntity;
use Cake\ORM\TableRegistry;
use Cake\ORM\Table;

/**
 * Table-level authorization policy for AuthorizationApprovals.
 *
 * Implements two-tier access: personal queue (approver_id filter) and
 * administrative access (full visibility). Approvers see their assigned
 * items; administrators see all queues.
 *
 * @package Activities\Policy
 * @see \App\Policy\BasePolicy For inherited RBAC functionality
 * @see /docs/5.6.5-activity-security-patterns.md For approval queue scoping pattern
 */
class AuthorizationApprovalsTablePolicy extends BasePolicy
{
    /**
     * Check if user can access their personal approval queue.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The table entity
     * @param mixed ...$optionalArgs Additional arguments
     * @return bool True if user has approval authority for any activity
     */
    public function canMyQueue(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return ActivitiesTable::canAuhtorizeAnyActivity($user);
    }

    /**
     * Check if user has administrative access to all approval queues.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \App\Model\Entity\BaseEntity|\Cake\ORM\Table $entity The table entity
     * @param mixed ...$optionalArgs Additional arguments
     * @return bool True if user has permission for all-queues access
     */
    public function canAllQueues(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Scope index queries - admins see all, approvers see own.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Cake\ORM\Query $query The base query to scope
     * @return \Cake\ORM\Query Scoped query
     */
    public function scopeIndex(KmpIdentityInterface $user, $query)
    {
        $authorizationApprovalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        $authorizationApproval = $authorizationApprovalsTable->newEmptyEntity();

        if ($this->canAllQueues($user, $authorizationApproval)) {
            return $query;
        }
        return $query->where(["approver_id" => $user->getIdentifier()]);
    }

    /**
     * Scope personal approval queue to user's assigned items.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Cake\ORM\Query $query The base query
     * @return \Cake\ORM\Query Query filtered to user's approver_id
     */
    public function scopeMyQueue(KmpIdentityInterface $user, $query)
    {
        return $query->where(["approver_id" => $user->getIdentifier()]);
    }

    /**
     * Scope mobile approve authorizations to user's assigned items.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Cake\ORM\Query $query The base query
     * @return \Cake\ORM\Query Query filtered to user's approver_id
     */
    public function scopeMobileApproveAuthorizations(KmpIdentityInterface $user, $query)
    {
        return $query->where(["approver_id" => $user->getIdentifier()]);
    }

    /**
     * Scope mobile approve action - admins see all, approvers see own.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Cake\ORM\Query $query The base query
     * @return \Cake\ORM\Query Scoped query
     */
    public function scopeMobileApprove(KmpIdentityInterface $user, $query)
    {
        $authorizationApprovalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        $authorizationApproval = $authorizationApprovalsTable->newEmptyEntity();

        if ($this->canAllQueues($user, $authorizationApproval)) {
            return $query;
        }
        return $query->where(["approver_id" => $user->getIdentifier()]);
    }

    /**
     * Scope mobile deny action - admins see all, approvers see own.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Cake\ORM\Query $query The base query
     * @return \Cake\ORM\Query Scoped query
     */
    public function scopeMobileDeny(KmpIdentityInterface $user, $query)
    {
        $authorizationApprovalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        $authorizationApproval = $authorizationApprovalsTable->newEmptyEntity();

        if ($this->canAllQueues($user, $authorizationApproval)) {
            return $query;
        }
        return $query->where(["approver_id" => $user->getIdentifier()]);
    }

    /**
     * Scope view queries - admins see all, approvers see own.
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param \Cake\ORM\Query $query The base query
     * @return \Cake\ORM\Query Scoped query
     */
    public function scopeView(KmpIdentityInterface $user, $query)
    {
        $authorizationApprovalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        $authorizationApproval = $authorizationApprovalsTable->newEmptyEntity();

        if ($this->canAllQueues($user, $authorizationApproval)) {
            return $query;
        }
        return $query->where(["approver_id" => $user->getIdentifier()]);
    }
}
