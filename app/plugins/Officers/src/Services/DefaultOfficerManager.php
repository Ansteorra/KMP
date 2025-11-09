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
 * Default Officer Manager Service Implementation
 * 
 * Provides comprehensive officer lifecycle management implementing the OfficerManagerInterface
 * with complete assignment, release, and warrant integration capabilities. This service handles
 * the complex business logic for officer assignment workflows including temporal validation,
 * hierarchical management, warrant coordination, and notification processing.
 * 
 * The DefaultOfficerManager integrates with multiple KMP systems including ActiveWindowManager
 * for temporal assignment management, WarrantManager for automatic role assignment, and
 * the notification system for stakeholder communication throughout officer lifecycle operations.
 * 
 * ## Service Architecture
 * 
 * **Dependency Integration**: Integrates with ActiveWindowManagerInterface for temporal
 * assignment management, WarrantManagerInterface for warrant coordination, and mailer
 * services for notification processing with comprehensive service coordination.
 * 
 * **Business Logic Implementation**: Implements complex officer assignment business logic
 * including office validation, member verification, hierarchical relationship establishment,
 * deputy management, and comprehensive workflow coordination for robust operations.
 * 
 * **Data Management**: Coordinates data operations across multiple entities including Officers,
 * Offices, Members, Branches, and Warrants with comprehensive relationship management
 * and data integrity validation for accurate assignment processing.
 * 
 * **Error Handling**: Provides comprehensive error handling including validation failures,
 * business rule violations, system errors, and external service coordination issues
 * with detailed error reporting and recovery capabilities.
 * 
 * ## Assignment Workflow Implementation
 * 
 * **Office Validation**: Validates office requirements including warrant requirements,
 * term length configuration, deputy relationships, reporting structure, and
 * assignment constraints for comprehensive office-based assignment validation.
 * 
 * **Member Verification**: Verifies member eligibility including warrantable status
 * for warrant-required offices, active membership, assignment constraints, and
 * organizational compliance for comprehensive member-based assignment authorization.
 * 
 * **Temporal Management**: Calculates assignment dates including automatic end date
 * calculation based on office term length, status determination based on temporal
 * context, and ActiveWindow integration for comprehensive temporal coordination.
 * 
 * **Hierarchical Assignment**: Establishes hierarchical relationships including deputy
 * assignments, reporting structure determination, branch hierarchy navigation, and
 * organizational chain management for comprehensive hierarchical coordination.
 * 
 * ## Deputy Management
 * 
 * **Deputy Assignment Processing**: Handles deputy-specific assignment including deputy
 * description management, deputy relationship establishment, hierarchical coordination,
 * and comprehensive deputy-specific workflow management for deputy positions.
 * 
 * **Reporting Structure Management**: Establishes reporting relationships including
 * deputy-to relationships, reports-to assignments, branch hierarchy navigation,
 * and comprehensive organizational structure management for accurate reporting chains.
 * 
 * ## Release Workflow Implementation
 * 
 * **Release Processing**: Handles officer release including ActiveWindow coordination,
 * warrant cancellation, status management, and comprehensive release workflow
 * coordination for accurate and complete officer release operations.
 * 
 * **Current Officer Replacement**: Manages replacement scenarios including current
 * officer release when only_one_per_branch constraints apply, automatic replacement
 * processing, and comprehensive succession management for organizational continuity.
 * 
 * ## Warrant System Integration
 * 
 * **Automatic Warrant Requests**: Creates warrant requests for warrant-required offices
 * including warrant request generation, role assignment coordination, temporal
 * validation, and comprehensive warrant lifecycle management integration.
 * 
 * **Warrant Cancellation**: Handles warrant cancellation during officer release
 * including warrant cleanup, role revocation, administrative coordination, and
 * comprehensive warrant termination processing for security and compliance.
 * 
 * ## Notification System Integration
 * 
 * **Assignment Notifications**: Sends assignment notifications including hire
 * notifications, appointment details, warrant information, and comprehensive
 * stakeholder communication for assignment coordination and transparency.
 * 
 * **Release Notifications**: Sends release notifications including termination
 * details, reason documentation, administrative information, and comprehensive
 * stakeholder communication for release coordination and transparency.
 * 
 * ## Performance Considerations
 * 
 * **Database Optimization**: Optimizes database operations including efficient entity
 * loading, relationship management, query optimization, and transaction coordination
 * for scalable performance and resource efficiency in high-volume environments.
 * 
 * **Service Integration**: Efficiently coordinates with external services including
 * ActiveWindow management, warrant processing, notification systems, and
 * comprehensive service integration for optimal performance and reliability.
 * 
 * ## Security and Compliance
 * 
 * **Authorization Validation**: Validates administrative authority for assignment and
 * release operations including approver verification, organizational scope validation,
 * and comprehensive security enforcement for secure operations.
 * 
 * **Audit Trail Management**: Creates comprehensive audit trails including assignment
 * documentation, administrative oversight, temporal tracking, and historical
 * record maintenance for accountability and compliance monitoring.
 * 
 * @package Officers\Services
 * @since 1.0.0
 * @version 2.0.0
 */
class DefaultOfficerManager implements OfficerManagerInterface
{
    use QueuedMailerAwareTrait;
    use MailerAwareTrait;

    /**
     * ActiveWindow Manager Service Instance
     * 
     * Provides temporal assignment management including assignment start/end date processing,
     * automatic status transitions, assignment lifecycle coordination, and comprehensive
     * temporal validation for officer assignment and release operations.
     * 
     * @var ActiveWindowManagerInterface
     */
    private ActiveWindowManagerInterface $activeWindowManager;

    /**
     * Warrant Manager Service Instance
     * 
     * Provides warrant lifecycle management including automatic warrant request creation,
     * role assignment coordination, warrant cancellation processing, and comprehensive
     * warrant integration for officer assignment and release operations.
     * 
     * @var WarrantManagerInterface
     */
    private WarrantManagerInterface $warrantManager;

    /**
     * DefaultOfficerManager Constructor
     * 
     * Initializes the DefaultOfficerManager with required service dependencies including
     * ActiveWindowManager for temporal assignment management and WarrantManager for
     * warrant coordination. Establishes service integration for comprehensive officer
     * lifecycle management with external system coordination.
     * 
     * ## Service Integration
     * 
     * **ActiveWindow Integration**: Establishes integration with ActiveWindowManager for
     * temporal assignment management including assignment lifecycle coordination, automatic
     * status transitions, and comprehensive temporal validation for officer operations.
     * 
     * **Warrant System Integration**: Establishes integration with WarrantManager for
     * warrant lifecycle coordination including automatic warrant requests, role assignment,
     * and comprehensive warrant management for officer assignment and release operations.
     * 
     * ## Dependency Management
     * 
     * **Service Dependencies**: Manages service dependencies through constructor injection
     * ensuring proper service availability, integration coordination, and comprehensive
     * service lifecycle management for reliable officer management operations.
     * 
     * **Mailer Integration**: Inherits mailer capabilities through trait inclusion enabling
     * notification processing, stakeholder communication, and comprehensive notification
     * management for officer assignment and release operations.
     * 
     * @param ActiveWindowManagerInterface $activeWindowManager ActiveWindow service for temporal assignment management
     * @param WarrantManagerInterface $warrantManager Warrant service for warrant lifecycle coordination
     * 
     * @since 1.0.0
     * @version 2.0.0
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
     * Calculate Officer Reporting Relationships
     * 
     * Private helper method that calculates and returns the correct reporting relationship fields
     * for an officer based on the office configuration and branch hierarchy. This method encapsulates
     * the complex logic for determining reports-to and deputy-to relationships including branch
     * hierarchy navigation and skip-reporting rules.
     * 
     * @param object $office The office entity with deputy_to_id, reports_to_id, and can_skip_report fields
     * @param object $officer The officer entity with branch_id that needs reporting fields calculated
     * @return array Associative array with keys: reports_to_office_id, reports_to_branch_id,
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
     * Recalculate Officer Reports-To Relationships Implementation
     * 
     * Implements comprehensive officer recalculation workflow for office configuration changes
     * including reporting relationship updates, member role synchronization, and branch hierarchy
     * navigation. This method processes all current and upcoming officers when office deputy_to_id,
     * reports_to_id, or grants_role_id changes to maintain organizational structure consistency.
     * 
     * ## Recalculation Processing Workflow
     * 
     * **Officer Discovery**: Queries for all current and upcoming officers assigned to the office
     * across all branches, processing each officer to update reporting relationships and role
     * assignments based on current office configuration and organizational structure.
     * 
     * **Reporting Relationship Updates**: Applies helper method to calculate correct reports-to
     * and deputy-to relationships for each officer, updating branch assignments based on hierarchy
     * navigation, skip-reporting rules, and comprehensive organizational structure logic.
     * 
     * **Member Role Synchronization**: Handles office role assignment changes by ending old
     * office-granted roles on today's date and creating new roles as needed, ensuring member
     * permissions align with current office requirements and organizational policies.
     * 
     * **Fail-Fast Error Handling**: Stops processing immediately on first failure with detailed
     * error message identifying specific member, office, and branch to enable targeted
     * troubleshooting and transaction rollback for data consistency.
     * 
     * @param int $officeId The office ID for officers requiring recalculation
     * @param int $updaterId The updater ID for audit trail and role change tracking
     * @return ServiceResult Result with success status and data containing updated_count, current_count, upcoming_count
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
     * Officer Release Implementation
     * 
     * Implements comprehensive officer release workflow including ActiveWindow coordination,
     * warrant cancellation, notification processing, and audit trail management. This method
     * handles the complete release business logic with comprehensive error handling and
     * service coordination for accurate and complete officer release operations.
     * 
     * ## Release Processing Workflow
     * 
     * **ActiveWindow Release Coordination**: Coordinates with ActiveWindowManager for
     * assignment termination, status management, temporal processing, and comprehensive
     * assignment lifecycle closure with proper audit trail and historical record maintenance.
     * 
     * **Officer Entity Management**: Loads officer entity with office relationships,
     * processes release status updates, manages assignment closure, and coordinates
     * comprehensive entity management for accurate release processing and data integrity.
     * 
     * **Warrant System Coordination**: Handles warrant cancellation for warrant-required
     * offices, coordinates with WarrantManager for warrant termination, manages role
     * revocation, and ensures comprehensive security cleanup during release operations.
     * 
     * ## ActiveWindow Integration
     * 
     * **Assignment Termination**: Terminates active assignments through ActiveWindow
     * integration, processes release dates, manages status transitions, and coordinates
     * comprehensive assignment closure with proper temporal validation and management.
     * 
     * **Status Management**: Manages assignment status transitions including release
     * status assignment, temporal coordination, historical record maintenance, and
     * comprehensive status management for accurate assignment lifecycle tracking.
     * 
     * **Administrative Oversight**: Records administrative oversight including revoker
     * identification, release reason documentation, approval workflow, and comprehensive
     * administrative coordination for accountability and compliance management.
     * 
     * ## Warrant Cancellation Processing
     * 
     * **Warrant Requirement Validation**: Validates warrant requirements for the officer's
     * office, determines warrant cancellation necessity, coordinates warrant termination
     * processing, and ensures comprehensive warrant lifecycle management during release.
     * 
     * **Warrant Cleanup Coordination**: Coordinates with WarrantManager for warrant
     * cancellation, manages role revocation, processes warrant termination, and ensures
     * comprehensive security cleanup and compliance during officer release operations.
     * 
     * **Role Revocation Management**: Manages automatic role revocation through warrant
     * cancellation, coordinates permission cleanup, ensures security compliance, and
     * maintains comprehensive role management during officer release and transition.
     * 
     * ## Notification and Communication
     * 
     * **Release Notification Processing**: Sends release notifications including termination
     * details, reason documentation, administrative information, and comprehensive stakeholder
     * communication for release coordination and organizational transparency.
     * 
     * **Email Communication Coordination**: Coordinates email notifications through queued
     * mailer system, includes relevant release details, provides comprehensive communication
     * for release processing and stakeholder coordination and organizational awareness.
     * 
     * **Stakeholder Communication**: Communicates release information to relevant stakeholders
     * including member notification, administrative oversight, organizational coordination,
     * and comprehensive communication for release processing and organizational management.
     * 
     * ## Error Handling and Service Integration
     * 
     * **ActiveWindow Error Management**: Handles ActiveWindow service errors including
     * termination failures, status management issues, temporal validation errors, and
     * comprehensive error recovery for reliable release processing and system coordination.
     * 
     * **Warrant System Error Handling**: Manages warrant system integration errors including
     * cancellation failures, role revocation issues, service coordination problems, and
     * comprehensive error management for reliable warrant cleanup and security compliance.
     * 
     * **Service Coordination Error Recovery**: Provides comprehensive error recovery including
     * service integration failures, notification processing errors, data management issues,
     * and system-level error handling for robust release processing and organizational continuity.
     * 
     * ## Administrative and Audit Features
     * 
     * **Audit Trail Creation**: Creates comprehensive audit trails including release
     * documentation, administrative oversight, reason tracking, and historical record
     * maintenance for accountability and compliance monitoring throughout release processing.
     * 
     * **Administrative Documentation**: Documents administrative actions including revoker
     * identification, approval workflow, release authorization, and comprehensive
     * administrative oversight for accountability and organizational governance.
     * 
     * @param int $officerId Officer identifier for release target validation and processing
     * @param int $revokerId Revoker identifier for administrative authority and audit trail creation
     * @param DateTime $revokedOn Release date for temporal processing and assignment closure
     * @param string|null $revokedReason Optional release reason for documentation and audit trail
     * @param string|null $releaseStatus Optional release status for assignment closure management
     * @return ServiceResult Comprehensive result including success status, release data, and error information
     * 
     * @since 1.0.0
     * @version 2.0.0
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
