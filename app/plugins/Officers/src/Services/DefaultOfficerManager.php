<?php

declare(strict_types=1);

namespace Officers\Services;

use App\KMP\TimezoneHelper;
use App\Model\Entity\Warrant;
use Cake\I18n\DateTime;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\WarrantManager\WarrantManagerInterface;
use Cake\ORM\TableRegistry;
use Officers\Model\Entity\Officer;
use App\Services\ServiceResult;
use Cake\Mailer\MailerAwareTrait;
use App\Services\WarrantManager\WarrantRequest;
use App\Mailer\QueuedMailerAwareTrait;

/**
 * Default implementation of OfficerManagerInterface for officer lifecycle management.
 * 
 * Handles officer assignment, release, and recalculation operations with integrated
 * warrant management, role assignment, and notification processing.
 * 
 * @package Officers\Services
 * @see \Officers\Services\OfficerManagerInterface
 * @see /docs/5.1.1-officers-services.md for detailed documentation
 */
class DefaultOfficerManager implements OfficerManagerInterface
{
    use QueuedMailerAwareTrait;
    use MailerAwareTrait;

    /** @var ActiveWindowManagerInterface */
    private ActiveWindowManagerInterface $activeWindowManager;

    /** @var WarrantManagerInterface */
    private WarrantManagerInterface $warrantManager;

    /**
     * @param ActiveWindowManagerInterface $activeWindowManager Temporal assignment management
     * @param WarrantManagerInterface $warrantManager Warrant lifecycle coordination
     */
    public function __construct(ActiveWindowManagerInterface $activeWindowManager, WarrantManagerInterface $warrantManager)
    {
        $this->activeWindowManager = $activeWindowManager;
        $this->warrantManager = $warrantManager;
    }
    /**
     * Assign a member to an office within a branch, persisting the Officer record,
     * establishing reporting/deputy relationships, starting the ActiveWindow lifecycle,
     * optionally requesting a warrant, and queuing a hire notification.
     *
     * @param int $officeId Office identifier for the assignment target.
     * @param int $memberId Member identifier being assigned to the office.
     * @param int $branchId Branch identifier providing organizational context.
     * @param DateTime $startOn Assignment start date.
     * @param DateTime|null $endOn Optional assignment end date; if null, may be derived from the office term length.
     * @param string|null $deputyDescription Optional deputy description for deputy assignments.
     * @param int $approverId Identifier of the approver performing the assignment.
     * @param string|null $emailAddress Optional email address to store on the Officer record and use for notifications.
     * @return ServiceResult ServiceResult with `success === true` on success; on failure `success === false` and `reason` contains an error message.
     */
    public function assign(
        int $officeId,
        int $memberId,
        int $branchId,
        DateTime $startOn,
        ?DateTime $endOn,
        ?string $deputyDescription,
        int $approverId,
        ?string $emailAddress
    ): ServiceResult {
        //get officer table
        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $newOfficer = $officerTable->newEmptyEntity();
        //get office table
        $officeTable = TableRegistry::getTableLocator()->get('Officers.Offices');
        //get the office
        $office = $officeTable->get($officeId);
        if ($office->requires_warrant) {
            $member = TableRegistry::getTableLocator()->get('Members')->get($memberId);
            if (!$member->warrantable) {
                return new ServiceResult(false, "Member is not warrantable");
            }
        }

        if ($endOn === null) {
            if ($office->term_length == 0) {
                $endOn = null;
            } else {
                $endOn = $startOn->addMonths($office->term_length);
            }
        }
        $status = Officer::UPCOMING_STATUS;
        if ($startOn->isToday() || $startOn->isPast()) {
            $status = Officer::CURRENT_STATUS;
        }
        if ($endOn != null && $endOn->isPast()) {
            $status = Officer::EXPIRED_STATUS;
        }
        $newOfficer->member_id = $memberId;
        $newOfficer->office_id = $officeId;
        $newOfficer->branch_id = $branchId;
        $newOfficer->approver_id = $approverId;
        $newOfficer->approval_date = DateTime::now();
        $newOfficer->status = $status;
        $newOfficer->email_address = $emailAddress ? $emailAddress : "";
        $newOfficer->deputy_description = $deputyDescription;

        // Calculate and apply reporting relationships using helper method
        $reportingFields = $this->_calculateOfficerReportingFields($office, $newOfficer);
        $newOfficer->reports_to_office_id = $reportingFields['reports_to_office_id'];
        $newOfficer->reports_to_branch_id = $reportingFields['reports_to_branch_id'];
        $newOfficer->deputy_to_office_id = $reportingFields['deputy_to_office_id'];
        $newOfficer->deputy_to_branch_id = $reportingFields['deputy_to_branch_id'];

        //release current officers if they exist for this office
        if ($office->only_one_per_branch) {
            $currentOfficers = $officerTable->find()
                ->where([
                    'office_id' => $officeId,
                    'branch_id' => $branchId,
                    'status' => Officer::CURRENT_STATUS
                ])
                ->all();
            foreach ($currentOfficers as $currentOfficer) {
                $oResult = $this->release($currentOfficer->id, $approverId, $startOn, "Replaced by new officer", Officer::REPLACED_STATUS);
                if (!$oResult->success) {
                    return new ServiceResult(false, $oResult->reason);
                }
            }
        }
        if (!$officerTable->save($newOfficer)) {
            return new ServiceResult(false, "Failed to save officer");
        }
        $awResult = $this->activeWindowManager->start('Officers.Officers', $newOfficer->id, $approverId, $startOn, $endOn, $office->term_length, $office->grants_role_id, $office->only_one_per_branch, $branchId);
        if (!$awResult->success) {
            return new ServiceResult(false, $awResult->reason);
        }

        $newOfficer = $officerTable->get($newOfficer->id);
        $branchTable = TableRegistry::getTableLocator()->get('Branches');
        $branch = $branchTable->get($branchId);
        $member = TableRegistry::getTableLocator()->get('Members')->get($memberId);
        if ($office->requires_warrant) {

            $officeName = $office->name;
            if ($deputyDescription != null and $deputyDescription != "") {
                $officeName = $officeName . " (" . $deputyDescription . ")";
            }
            $warrantRequest = new WarrantRequest("Hiring Warrant: $branch->name - $officeName", 'Officers.Officers', $newOfficer->id, $approverId, $memberId, $startOn, $endOn, $newOfficer->granted_member_role_id);

            $wmResult = $this->warrantManager->request("$office->name : $member->sca_name", "", [$warrantRequest]);
            if (!$wmResult->success) {
                return new ServiceResult(false, $wmResult->reason);
            }
        }
        $vars = [
            "memberScaName" => $member->sca_name,
            "officeName" => $office->name,
            "branchName" => $branch->name,
            "hireDate" => TimezoneHelper::formatDate($newOfficer->start_on),
            "endDate" => TimezoneHelper::formatDate($newOfficer->expires_on),
            "requiresWarrant" => $office->requires_warrant
        ];
        $this->queueMail("Officers.Officers", "notifyOfHire", $member->email_address, $vars);
        return new ServiceResult(true);
    }

    /**
     * Calculate reporting relationship fields for an officer based on office configuration.
     * 
     * @param object $office The office entity with deputy_to_id, reports_to_id, and can_skip_report fields
     * @param object $officer The officer entity with branch_id
     * @return array Associative array with reports_to_office_id, reports_to_branch_id,
     *               deputy_to_office_id, deputy_to_branch_id, deputy_description
     */
    private function _calculateOfficerReportingFields($office, $officer): array
    {
        $result = [
            'reports_to_office_id' => null,
            'reports_to_branch_id' => null,
            'deputy_to_office_id' => null,
            'deputy_to_branch_id' => null,
            'deputy_description' => $officer->deputy_description ?? null,
        ];

        if ($office->deputy_to_id != null) {
            // Deputy assignment - reports to the same office they're deputy to
            $result['deputy_to_branch_id'] = $officer->branch_id;
            $result['deputy_to_office_id'] = $office->deputy_to_id;
            $result['reports_to_branch_id'] = $officer->branch_id;
            $result['reports_to_office_id'] = $office->deputy_to_id;
        } else {
            // Regular reporting assignment
            $result['reports_to_office_id'] = $office->reports_to_id;
            $branchTable = TableRegistry::getTableLocator()->get('Branches');
            $branch = $branchTable->get($officer->branch_id);

            if ($branch->parent_id != null) {
                // Use the new compatibility checking method to find the right branch
                // This ensures the reports_to_office can actually exist in the reports_to_branch
                $officesTable = TableRegistry::getTableLocator()->get('Officers.Offices');

                if (!$office->can_skip_report) {
                    // For offices that can't skip reporting, find the compatible parent branch
                    // starting from the immediate parent
                    $compatibleBranchId = $officesTable->findCompatibleBranchForOffice(
                        $branch->parent_id,
                        $office->reports_to_id
                    );
                    $result['reports_to_branch_id'] = $compatibleBranchId;
                } else {
                    // For offices that can skip reporting, first try to find a branch
                    // where this office is actually filled
                    $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
                    $currentBranchId = $branch->parent_id;
                    $previousBranchId = $officer->branch_id;
                    $setReportsToBranch = false;

                    while ($currentBranchId != null) {
                        $officersCount = $officerTable->find('Current')
                            ->where(['branch_id' => $currentBranchId, 'office_id' => $office->reports_to_id])
                            ->count();
                        if ($officersCount > 0) {
                            $result['reports_to_branch_id'] = $currentBranchId;
                            $setReportsToBranch = true;
                            break;
                        }
                        $previousBranchId = $currentBranchId;
                        $currentBranch = $branchTable->get($currentBranchId);
                        $currentBranchId = $currentBranch->parent_id;
                    }

                    // If no filled office found, use compatibility checking
                    if (!$setReportsToBranch) {
                        $compatibleBranchId = $officesTable->findCompatibleBranchForOffice(
                            $previousBranchId,
                            $office->reports_to_id
                        );
                        $result['reports_to_branch_id'] = $compatibleBranchId;
                    }
                }
            } else {
                $result['reports_to_branch_id'] = null;
            }
        }

        return $result;
    }

    /**
     * Recalculate reporting relationships and roles for all current/upcoming officers of an office.
     * 
     * Call when office deputy_to_id, reports_to_id, or grants_role_id changes.
     * Uses fail-fast error handling - stops on first failure.
     *
     * @param int $officeId The office ID for officers requiring recalculation
     * @param int $updaterId The updater ID for audit trail and role change tracking
     * @return ServiceResult Result with updated_count, current_count, upcoming_count on success
     */
    public function recalculateOfficersForOffice(
        int $officeId,
        int $updaterId
    ): ServiceResult {
        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $officeTable = TableRegistry::getTableLocator()->get('Officers.Offices');
        $memberTable = TableRegistry::getTableLocator()->get('Members');
        $branchTable = TableRegistry::getTableLocator()->get('Branches');

        // Get the office entity
        $office = $officeTable->get($officeId);

        // Find all current officers
        $currentOfficers = $officerTable->find()
            ->where(['office_id' => $officeId, 'status' => Officer::CURRENT_STATUS])
            ->all();

        // Find all upcoming officers
        $upcomingOfficers = $officerTable->find()
            ->where(['office_id' => $officeId, 'status' => Officer::UPCOMING_STATUS])
            ->all();

        $currentCount = 0;
        $upcomingCount = 0;
        $today = DateTime::now();

        // Process current officers
        foreach ($currentOfficers as $officer) {
            // Calculate new reporting relationships
            $reportingFields = $this->_calculateOfficerReportingFields($office, $officer);

            // Update officer reporting fields
            $officer->reports_to_office_id = $reportingFields['reports_to_office_id'];
            $officer->reports_to_branch_id = $reportingFields['reports_to_branch_id'];
            $officer->deputy_to_office_id = $reportingFields['deputy_to_office_id'];
            $officer->deputy_to_branch_id = $reportingFields['deputy_to_branch_id'];

            // Handle member role synchronization if office role changed
            if ($officer->granted_member_role_id != null) {
                // Officer has an existing role - check if it matches office configuration
                $memberRoleTable = TableRegistry::getTableLocator()->get('MemberRoles');
                $existingRole = $memberRoleTable->get($officer->granted_member_role_id);

                if ($office->grants_role_id == null) {
                    // Office no longer grants a role - end the existing role
                    $awResult = $this->activeWindowManager->stop(
                        'MemberRoles',
                        $officer->granted_member_role_id,
                        $updaterId,
                        'released',
                        'Office no longer grants this role',
                        $today
                    );
                    if (!$awResult->success) {
                        $member = $memberTable->get($officer->member_id);
                        $branch = $branchTable->get($officer->branch_id);
                        return new ServiceResult(
                            false,
                            "Failed to end role for $member->sca_name ($office->name at $branch->name): {$awResult->reason}"
                        );
                    }
                    $officer->granted_member_role_id = null;
                } elseif ($existingRole->role_id != $office->grants_role_id) {
                    // Office grants a different role - end old and create new
                    $awResult = $this->activeWindowManager->stop(
                        'MemberRoles',
                        $officer->granted_member_role_id,
                        $updaterId,
                        'replaced',
                        'Office role configuration changed',
                        $today
                    );
                    if (!$awResult->success) {
                        $member = $memberTable->get($officer->member_id);
                        $branch = $branchTable->get($officer->branch_id);
                        return new ServiceResult(
                            false,
                            "Failed to end old role for $member->sca_name ($office->name at $branch->name): {$awResult->reason}"
                        );
                    }

                    // Create new role starting today
                    $memberRoleTable = TableRegistry::getTableLocator()->get('MemberRoles');
                    $newRole = $memberRoleTable->newEmptyEntity();
                    $newRole->member_id = $officer->member_id;
                    $newRole->role_id = $office->grants_role_id;
                    $newRole->start($today, $officer->expires_on, 0);
                    $newRole->entity_type = 'Officers.Officers';
                    $newRole->entity_id = $officer->id;
                    $newRole->approver_id = $updaterId;
                    $newRole->branch_id = $officer->branch_id;

                    if (!$memberRoleTable->save($newRole)) {
                        $member = $memberTable->get($officer->member_id);
                        $branch = $branchTable->get($officer->branch_id);
                        return new ServiceResult(
                            false,
                            "Failed to create new role for $member->sca_name ($office->name at $branch->name)"
                        );
                    }
                    $officer->granted_member_role_id = $newRole->id;
                }
                // If role_id matches, no change needed
            } elseif ($office->grants_role_id != null) {
                // Officer doesn't have a role but office now grants one - create it
                $memberRoleTable = TableRegistry::getTableLocator()->get('MemberRoles');
                $newRole = $memberRoleTable->newEmptyEntity();
                $newRole->member_id = $officer->member_id;
                $newRole->role_id = $office->grants_role_id;
                $newRole->start($today, $officer->expires_on, 0);
                $newRole->entity_type = 'Officers.Officers';
                $newRole->entity_id = $officer->id;
                $newRole->approver_id = $updaterId;
                $newRole->branch_id = $officer->branch_id;

                if (!$memberRoleTable->save($newRole)) {
                    $member = $memberTable->get($officer->member_id);
                    $branch = $branchTable->get($officer->branch_id);
                    return new ServiceResult(
                        false,
                        "Failed to create role for $member->sca_name ($office->name at $branch->name)"
                    );
                }
                $officer->granted_member_role_id = $newRole->id;
            }

            // Save the updated officer
            if (!$officerTable->save($officer)) {
                $member = $memberTable->get($officer->member_id);
                $branch = $branchTable->get($officer->branch_id);
                return new ServiceResult(
                    false,
                    "Failed to update officer for $member->sca_name ($office->name at $branch->name)"
                );
            }

            $currentCount++;
        }

        // Process upcoming officers
        foreach ($upcomingOfficers as $officer) {
            // Calculate new reporting relationships
            $reportingFields = $this->_calculateOfficerReportingFields($office, $officer);

            // Update officer reporting fields
            $officer->reports_to_office_id = $reportingFields['reports_to_office_id'];
            $officer->reports_to_branch_id = $reportingFields['reports_to_branch_id'];
            $officer->deputy_to_office_id = $reportingFields['deputy_to_office_id'];
            $officer->deputy_to_branch_id = $reportingFields['deputy_to_branch_id'];

            // Handle member role synchronization for upcoming officers
            if ($officer->granted_member_role_id != null) {
                $memberRoleTable = TableRegistry::getTableLocator()->get('MemberRoles');
                $existingRole = $memberRoleTable->get($officer->granted_member_role_id);

                if ($office->grants_role_id == null) {
                    // Office no longer grants a role - end the existing role
                    $awResult = $this->activeWindowManager->stop(
                        'MemberRoles',
                        $officer->granted_member_role_id,
                        $updaterId,
                        'released',
                        'Office no longer grants this role',
                        $today
                    );
                    if (!$awResult->success) {
                        $member = $memberTable->get($officer->member_id);
                        $branch = $branchTable->get($officer->branch_id);
                        return new ServiceResult(
                            false,
                            "Failed to end role for $member->sca_name ($office->name at $branch->name): {$awResult->reason}"
                        );
                    }
                    $officer->granted_member_role_id = null;
                } elseif ($existingRole->role_id != $office->grants_role_id) {
                    // Office grants a different role - end old and create new
                    $awResult = $this->activeWindowManager->stop(
                        'MemberRoles',
                        $officer->granted_member_role_id,
                        $updaterId,
                        'replaced',
                        'Office role configuration changed',
                        $today
                    );
                    if (!$awResult->success) {
                        $member = $memberTable->get($officer->member_id);
                        $branch = $branchTable->get($officer->branch_id);
                        return new ServiceResult(
                            false,
                            "Failed to end old role for $member->sca_name ($office->name at $branch->name): {$awResult->reason}"
                        );
                    }

                    // Create new role with officer's original start date
                    $memberRoleTable = TableRegistry::getTableLocator()->get('MemberRoles');
                    $newRole = $memberRoleTable->newEmptyEntity();
                    $newRole->member_id = $officer->member_id;
                    $newRole->role_id = $office->grants_role_id;
                    $newRole->start($officer->start_on, $officer->expires_on, 0);
                    $newRole->entity_type = 'Officers.Officers';
                    $newRole->entity_id = $officer->id;
                    $newRole->approver_id = $updaterId;
                    $newRole->branch_id = $officer->branch_id;

                    if (!$memberRoleTable->save($newRole)) {
                        $member = $memberTable->get($officer->member_id);
                        $branch = $branchTable->get($officer->branch_id);
                        return new ServiceResult(
                            false,
                            "Failed to create new role for $member->sca_name ($office->name at $branch->name)"
                        );
                    }
                    $officer->granted_member_role_id = $newRole->id;
                }
            } elseif ($office->grants_role_id != null) {
                // Officer doesn't have a role but office now grants one - create it
                $memberRoleTable = TableRegistry::getTableLocator()->get('MemberRoles');
                $newRole = $memberRoleTable->newEmptyEntity();
                $newRole->member_id = $officer->member_id;
                $newRole->role_id = $office->grants_role_id;
                $newRole->start($officer->start_on, $officer->expires_on, 0);
                $newRole->entity_type = 'Officers.Officers';
                $newRole->entity_id = $officer->id;
                $newRole->approver_id = $updaterId;
                $newRole->branch_id = $officer->branch_id;

                if (!$memberRoleTable->save($newRole)) {
                    $member = $memberTable->get($officer->member_id);
                    $branch = $branchTable->get($officer->branch_id);
                    return new ServiceResult(
                        false,
                        "Failed to create role for $member->sca_name ($office->name at $branch->name)"
                    );
                }
                $officer->granted_member_role_id = $newRole->id;
            }

            // Save the updated officer
            if (!$officerTable->save($officer)) {
                $member = $memberTable->get($officer->member_id);
                $branch = $branchTable->get($officer->branch_id);
                return new ServiceResult(
                    false,
                    "Failed to update officer for $member->sca_name ($office->name at $branch->name)"
                );
            }

            $upcomingCount++;
        }

        $totalCount = $currentCount + $upcomingCount;

        return new ServiceResult(true, null, [
            'updated_count' => $totalCount,
            'current_count' => $currentCount,
            'upcoming_count' => $upcomingCount,
        ]);
    }

    /**
     * Release an officer from their position.
     * 
     * Handles ActiveWindow termination, warrant cancellation for warrant-required offices,
     * and sends release notification to the member.
     *
     * @param int $officerId Officer identifier
     * @param int $revokerId Administrator performing the release
     * @param DateTime $revokedOn Effective release date
     * @param string|null $revokedReason Optional reason for release
     * @param string|null $releaseStatus Status to set (defaults to RELEASED_STATUS)
     * @return ServiceResult Success or failure with reason
     */
    public function release(
        int $officerId,
        int $revokerId,
        DateTime $revokedOn,
        ?string $revokedReason,
        ?string $releaseStatus = Officer::RELEASED_STATUS
    ): ServiceResult {
        $awResult = $this->activeWindowManager->stop('Officers.Officers', $officerId, $revokerId, $releaseStatus, $revokedReason, $revokedOn);
        if (!$awResult->success) {
            return new ServiceResult(false, $awResult->reason);
        }
        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $officer = $officerTable->get($officerId, ['contain' => ['Offices']]);
        if ($officer->office->requires_warrant) {
            $wmResult = $this->warrantManager->cancelByEntity('Officers.Officers', $officerId, $revokedReason, $revokerId, $revokedOn);
            if (!$wmResult->success) {
                return new ServiceResult(false, $wmResult->reason);
            }
        }
        $member = TableRegistry::getTableLocator()->get('Members')->get($officer->member_id);
        $office = $officer->office;
        $branch = TableRegistry::getTableLocator()->get('Branches')->get($officer->branch_id);
        $vars = [
            "memberScaName" => $member->sca_name,
            "officeName" => $office->name,
            "branchName" => $branch->name,
            "reason" => $revokedReason,
            "releaseDate" => TimezoneHelper::formatDate($revokedOn),
        ];
        $this->queueMail("Officers.Officers", "notifyOfRelease", $member->email_address, $vars);
        return new ServiceResult(true);
    }
}
