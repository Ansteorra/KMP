<?php
declare(strict_types=1);

namespace App\Policy;

use App\KMP\KmpIdentityInterface;
use App\Model\Entity\BaseEntity;
use Cake\ORM\Table;

/**
 * WorkflowApprovalsTable policy — table-level authorization for approvals.
 *
 * Any authenticated user can access their own approvals.
 * Admin actions require super user.
 */
class WorkflowApprovalsTablePolicy extends BasePolicy
{
    /**
     * Allow authenticated users to view their approval queue.
     */
    public function canApprovals(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * Allow authenticated users to respond to approvals; entity eligibility is checked by the controller/service.
     */
    public function canRecordApproval(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * Allow authenticated users to save private triage; the action restricts saves to pending approvals for that user.
     */
    public function canUpdateTriage(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * Allow authenticated users to load their pending approval Kanban lanes.
     */
    public function canApprovalsKanbanLaneData(
        KmpIdentityInterface $user,
        BaseEntity|Table $entity,
        ...$optionalArgs,
    ): bool {
        return $user->getIdentifier() !== null;
    }

    /**
     * Restrict the all-approvals dashboard to super users.
     */
    public function canAllApprovals(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Restrict the all-approvals grid data to super users.
     */
    public function canAllApprovalsGridData(
        KmpIdentityInterface $user,
        BaseEntity|Table $entity,
        ...$optionalArgs,
    ): bool {
        return $this->_isSuperUser($user);
    }

    /**
     * Restrict approval reassignment to super users.
     */
    public function canReassignApproval(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $this->_isSuperUser($user);
    }

    /**
     * Allow authenticated users to view the mobile approval queue.
     */
    public function canMobileApprovals(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * Allow authenticated users to load their mobile approval data.
     */
    public function canMobileApprovalsData(KmpIdentityInterface $user, BaseEntity|Table $entity, ...$optionalArgs): bool
    {
        return $user->getIdentifier() !== null;
    }

    /**
     * Allow authenticated approvers to search future gatherings for approval-created bestowals.
     */
    public function canBestowalGatheringsAutoComplete(
        KmpIdentityInterface $user,
        BaseEntity|Table $entity,
        ...$optionalArgs,
    ): bool {
        return $user->getIdentifier() !== null;
    }
}
