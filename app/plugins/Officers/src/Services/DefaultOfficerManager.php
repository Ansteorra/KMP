<?php
declare(strict_types=1);

namespace Officers\Services;

use App\KMP\StaticHelpers;
use App\KMP\TimezoneHelper;
use App\Mailer\QueuedMailerAwareTrait;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Exception;
use Officers\Model\Entity\Officer;

/**
 * Default implementation of OfficerManagerInterface for officer release management.
 *
 * Handles workflow-shared officer release and recalculation operations with integrated
 * warrant management and notification processing.
 *
 * @package Officers\Services
 * @see \Officers\Services\OfficerManagerInterface
 * @see /docs/5.1.1-officers-services.md for detailed documentation
 */
class DefaultOfficerManager implements OfficerManagerInterface
{
    use QueuedMailerAwareTrait;

    /**
     * @var \App\Services\ActiveWindowManager\ActiveWindowManagerInterface
     */
    private ActiveWindowManagerInterface $activeWindowManager;

    /**
     * @var \App\Services\WarrantManager\WarrantManagerInterface
     */
    private WarrantManagerInterface $warrantManager;

    /**
     * @var \App\Services\WorkflowEngine\TriggerDispatcher
     */
    private TriggerDispatcher $triggerDispatcher;

    /**
     * @param \App\Services\ActiveWindowManager\ActiveWindowManagerInterface $activeWindowManager Temporal assignment management
     * @param \App\Services\WarrantManager\WarrantManagerInterface $warrantManager Warrant lifecycle coordination
     * @param \App\Services\WorkflowEngine\TriggerDispatcher $triggerDispatcher Workflow trigger dispatcher
     */
    public function __construct(ActiveWindowManagerInterface $activeWindowManager, WarrantManagerInterface $warrantManager, TriggerDispatcher $triggerDispatcher)
    {
        $this->activeWindowManager = $activeWindowManager;
        $this->warrantManager = $warrantManager;
        $this->triggerDispatcher = $triggerDispatcher;
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
                        $office->reports_to_id,
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
                            $office->reports_to_id,
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
     * @return \App\Services\ServiceResult Result with updated_count, current_count, upcoming_count on success
     */
    public function recalculateOfficersForOffice(
        int $officeId,
        int $updaterId,
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
                        $today,
                    );
                    if (!$awResult->success) {
                        $member = $memberTable->get($officer->member_id);
                        $branch = $branchTable->get($officer->branch_id);

                        return new ServiceResult(
                            false,
                            "Failed to end role for $member->sca_name ($office->name at $branch->name): {$awResult->reason}",
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
                        $today,
                    );
                    if (!$awResult->success) {
                        $member = $memberTable->get($officer->member_id);
                        $branch = $branchTable->get($officer->branch_id);

                        return new ServiceResult(
                            false,
                            "Failed to end old role for $member->sca_name ($office->name at $branch->name): {$awResult->reason}",
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
                            "Failed to create new role for $member->sca_name ($office->name at $branch->name)",
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
                        "Failed to create role for $member->sca_name ($office->name at $branch->name)",
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
                    "Failed to update officer for $member->sca_name ($office->name at $branch->name)",
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
                        $today,
                    );
                    if (!$awResult->success) {
                        $member = $memberTable->get($officer->member_id);
                        $branch = $branchTable->get($officer->branch_id);

                        return new ServiceResult(
                            false,
                            "Failed to end role for $member->sca_name ($office->name at $branch->name): {$awResult->reason}",
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
                        $today,
                    );
                    if (!$awResult->success) {
                        $member = $memberTable->get($officer->member_id);
                        $branch = $branchTable->get($officer->branch_id);

                        return new ServiceResult(
                            false,
                            "Failed to end old role for $member->sca_name ($office->name at $branch->name): {$awResult->reason}",
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
                            "Failed to create new role for $member->sca_name ($office->name at $branch->name)",
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
                        "Failed to create role for $member->sca_name ($office->name at $branch->name)",
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
                    "Failed to update officer for $member->sca_name ($office->name at $branch->name)",
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
     * @param \Cake\I18n\DateTime $revokedOn Effective release date
     * @param string|null $revokedReason Optional reason for release
     * @param string|null $releaseStatus Status to set (defaults to RELEASED_STATUS)
     * @return \App\Services\ServiceResult Success or failure with reason
     */
    public function release(
        int $officerId,
        int $revokerId,
        DateTime $revokedOn,
        ?string $revokedReason,
        ?string $releaseStatus = Officer::RELEASED_STATUS,
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
            'memberScaName' => $member->sca_name,
            'officeName' => $office->name,
            'branchName' => $branch->name,
            'reason' => $revokedReason,
            'releaseDate' => TimezoneHelper::formatDate($revokedOn),
            'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature', '', null, true),
        ];
        $this->queueMail('KMP', 'sendFromTemplate', $member->email_address, array_merge(
            ['_templateId' => 'officer-release-notification'],
            $vars,
        ));

        try {
            $this->triggerDispatcher->dispatch('Officers.Released', [
                'officerId' => $officer->id,
                'memberId' => $officer->member_id,
                'officeId' => $officer->office_id,
                'reason' => $revokedReason ?? 'Released',
            ]);
        } catch (Exception $e) {
            Log::warning('Workflow trigger dispatch failed for Officers.Released: ' . $e->getMessage());
        }

        return new ServiceResult(true);
    }
}
