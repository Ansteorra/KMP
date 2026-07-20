<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use Cake\ORM\TableRegistry;

/**
 * Provides authorization-related functionality to Member entities.
 * 
 * Extends Member entities with methods for tracking pending approval responsibilities
 * and supporting navigation badges for approval workflow management.
 */
trait MemberAuthorizationsTrait
{
    /**
     * Get the count of pending authorization approvals for this member.
     * 
     * Counts workflow approvals where this member is the approver and the
     * approval is still pending. Used for navigation badges.
     *
     * @return int Number of pending authorization approvals
     */
    public function getPendingApprovalsCount(): int
    {
        $wfApprovalsTable = TableRegistry::getTableLocator()->get("WorkflowApprovals");
        $count = $wfApprovalsTable->find()
            ->where([
                "approver_id" => $this->id,
                "status" => "Pending",
            ])
            ->count();

        return $count;
    }
}
