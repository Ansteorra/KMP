<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use App\Model\Entity\WorkflowApproval;
use App\Model\Table\WorkflowApprovalsTable;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

/**
 * WorkflowApproval entity-level policy.
 *
 * Current eligible approvers and prior respondents can read their approvals.
 * Reassignment remains restricted to administrators through BasePolicy.
 */
class WorkflowApprovalPolicy extends BasePolicy
{
    /** Read current assignments or approvals the member has already answered. */
    public function canView(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        $memberId = (int)$user->getAsMember()->id;
        if (!$entity instanceof WorkflowApproval || !$entity->id || $memberId <= 0) {
            return false;
        }

        if ($this->canEligibleApprovers($user, $entity)) {
            return true;
        }

        return TableRegistry::getTableLocator()->get('WorkflowApprovalResponses')->exists([
            'workflow_approval_id' => $entity->id,
            'member_id' => $memberId,
        ]);
    }

    /** Candidate discovery requires the same live eligibility as recording a response. */
    public function canEligibleApprovers(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        $memberId = (int)$user->getAsMember()->id;

        return $entity instanceof WorkflowApproval && $entity->id && $memberId > 0
            && WorkflowApprovalsTable::isPendingApprovalForMember((int)$entity->id, $memberId);
    }

    /** BasePolicy grants the administrator exception; other identities cannot reassign. */
    public function canReassignApproval(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return false;
    }

    /** Allow authenticated identities to request the scoped grid. */
    public function canIndex(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /** Allow authenticated identities to request the scoped grid. */
    public function canApprovals(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /** The approval service verifies live eligibility before recording a response. */
    public function canRecordApproval(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }
}
