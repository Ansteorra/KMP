<?php

namespace Officers\Services;

use Cake\I18n\DateTime;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use Cake\ORM\TableRegistry;
use Officers\Model\Entity\Officer;

class DefaultOfficerManager implements OfficerManagerInterface
{
    /**
     * Assigns a member to an office - Make sure to create a transaction before calling this service
     *
     * @param ActiveWindowManagerInterface $activeWindowManager
     * @param int $officeId
     * @param int $memberId
     * @param int $branchId
     * @param DateTime $startOn
     * @param string $deputyDescription
     * @param int $approverId
     * @return bool
     */
    public function assign(
        ActiveWindowManagerInterface $activeWindowManager,
        int $officeId,
        int $memberId,
        int $branchId,
        DateTime $startOn,
        ?DateTime $endOn,
        ?string $deputyDescription,
        int $approverId,
    ): bool {
        //get officer table
        $officerTable = TableRegistry::getTableLocator()->get('Officers.Officers');
        $newOfficer = $officerTable->newEmptyEntity();
        //get office table
        $officeTable = TableRegistry::getTableLocator()->get('Officers.Offices');
        //get the office
        $office = $officeTable->get($officeId);

        if ($endOn === null) {
            $endOn = $startOn->addYears($office->term_length);
        }
        $status = Officer::UPCOMING_STATUS;
        if ($startOn->isToday() || $startOn->isPast()) {
            $status = Officer::CURRENT_STATUS;
        }
        if ($endOn->isPast()) {
            $status = Officer::EXPIRED_STATUS;
        }
        $newOfficer->member_id = $memberId;
        $newOfficer->office_id = $officeId;
        $newOfficer->branch_id = $branchId;
        $newOfficer->approver_id = $approverId;
        $newOfficer->approval_date = DateTime::now();
        $newOfficer->status = $status;
        $newOfficer->reports_to_office_id = $officeId;
        if ($office->deputy_to_id != null) {
            $newOfficer->deputy_description = $deputyDescription;
            $newOfficer->reports_to_branch_id = $newOfficer->branch_id;
            $newOfficer->reports_to_office_id = $office->deputy_to_id;
        } else {
            $branchTable = TableRegistry::getTableLocator()->get('Branches');
            $branch = $branchTable->get($branchId);
            if ($branch->parent_id != null) {
                if (!$office->can_skip_report) {
                    $newOfficer->reports_to_branch_id = $branch->parent_id;
                } else {
                    //iterate through the parents till we find one that has this office or the root
                    $currentBranchId = $branch->parent_id;
                    $previousBranchId = $branchId;
                    $setReportsToBranch = false;
                    while ($currentBranchId != null) {
                        $officersCount = $branchTable->CurrentOfficers->find()
                            ->where(['branch_id' => $currentBranchId, 'office_id' => $officeId])
                            ->count();
                        if ($officersCount > 0) {
                            $newOfficer->reports_to_branch_id = $currentBranchId;
                            $setReportsToBranch = true;
                            break;
                        }
                        $previousBranchId = $currentBranchId;
                        $currentBranch = $branchTable->get($currentBranchId);
                        $currentBranchId = $currentBranch->parent_id;
                    }
                    if (!$setReportsToBranch) {
                        $newOfficer->reports_to_branch_id = $previousBranchId;
                    }
                }
            } else {
                $newOfficer->reports_to_branch_id = $branch->id;
            }
        }
        if (!$officerTable->save($newOfficer)) {
            return false;
        }
        if (!$activeWindowManager->start('Officers.Officers', $newOfficer->id, $approverId, $startOn, $endOn, $office->term_length, $office->grants_role_id, $office->only_one_per_branch)) {
            return false;
        }
        return true;
    }

    /**
     * Releases an officer from their office - Make sure to create a transaction before calling this service
     *
     * @param ActiveWindowManagerInterface $activeWindowManager
     * @param int $officerId
     * @param int $revokerId
     * @param DateTime $revokedOn
     * @param string $revokedReason
     * @return bool
     */
    public function release(
        ActiveWindowManagerInterface $activeWindowManager,
        int $officerId,
        int $revokerId,
        DateTime $revokedOn,
        ?string $revokedReason
    ): bool {
        if (!$activeWindowManager->stop('Officers.Officers', $officerId, $revokerId, 'released', $revokedReason, $revokedOn)) {
            return false;
        }
        return true;
    }
}