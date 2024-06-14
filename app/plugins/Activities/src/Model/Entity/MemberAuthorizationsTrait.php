<?php

declare(strict_types=1);

namespace Activities\Model\Entity;

use Cake\ORM\TableRegistry;

trait MemberAuthorizationsTrait
{
    /**
     * Get the number of pending approvals for the user
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