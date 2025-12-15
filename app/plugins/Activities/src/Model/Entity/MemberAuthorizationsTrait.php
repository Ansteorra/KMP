<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use Cake\ORM\TableRegistry;

/**
 * Provides authorization-related functionality to Member entities.
 * 
 * Extends Member entities with methods for tracking pending approval responsibilities
 * and supporting navigation badges for approval workflow management.
 *
 * @see \Activities\Model\Table\AuthorizationApprovalsTable For approval management
 * @see /docs/5.6.8-authorization-approval-entity-reference.md For usage examples
 */
trait MemberAuthorizationsTrait
{
    /**
     * Get the count of pending authorization approvals for this member.
     * 
     * Counts approvals where this member is the approver and has not yet responded
     * (responded_on is null). Used for navigation badges and approval workflow management.
     *
     * @return int Number of pending authorization approvals
     */
    public function getPendingApprovalsCount(): int
    {
        $count = 0;
        $approvalsTable = TableRegistry::getTableLocator()->get("Activities.AuthorizationApprovals");
        $query = $approvalsTable->find()
            ->where([
                "approver_id" => $this->id,
                "responded_on is" => null,
            ]);
        $count = $query->count();
        return $count;
    }
}
