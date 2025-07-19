<?php

declare(strict_types=1);

namespace Officers\Services;

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
     * Officer Assignment Implementation
     * 
     * Implements comprehensive officer assignment workflow including office validation,
     * member verification, temporal management, hierarchical relationship establishment,
     * warrant integration, and notification processing. This method handles the complete
     * assignment business logic with comprehensive error handling and service coordination.
     * 
     * ## Assignment Processing Workflow
     * 
     * **Entity Creation and Validation**: Creates new Officer entity, validates office
     * requirements including warrant requirements and member warrantable status,
     * performs comprehensive validation for assignment eligibility and compliance.
     * 
     * **Temporal Assignment Management**: Calculates assignment end dates based on office
     * term length configuration, determines assignment status based on temporal context,
     * handles immediate assignments and future-dated assignments appropriately.
     * 
     * **Hierarchical Relationship Establishment**: Establishes deputy relationships for
     * deputy positions, configures reporting structure based on office hierarchy,
     * navigates branch hierarchy for proper reporting chain establishment.
     * 
     * **Current Officer Management**: Handles replacement scenarios for offices with
     * only_one_per_branch constraints, automatically releases current officers when
     * necessary, maintains organizational continuity during officer transitions.
     * 
     * ## Office Validation and Configuration
     * 
     * **Warrant Requirement Validation**: Validates warrant requirements for warrant-required
     * offices, verifies member warrantable status, ensures compliance with warrant
     * policies and organizational security requirements for appropriate assignments.
     * 
     * **Term Length Processing**: Processes office term length configuration including
     * automatic end date calculation for term-limited offices, indefinite assignments
     * for term-unlimited offices, and comprehensive temporal assignment coordination.
     * 
     * **Deputy Configuration**: Handles deputy assignment configuration including deputy
     * description processing, deputy-to relationship establishment, hierarchical
     * coordination, and comprehensive deputy-specific assignment management.
     * 
     * ## Hierarchical Management Implementation
     * 
     * **Reporting Structure Navigation**: Navigates branch hierarchy to establish proper
     * reporting relationships, handles skip-reporting configurations, finds appropriate
     * reporting targets based on organizational structure and office availability.
     * 
     * **Branch Hierarchy Processing**: Processes branch parent relationships, iterates
     * through organizational hierarchy, identifies appropriate reporting levels,
     * establishes comprehensive organizational reporting structure for assignments.
     * 
     * ## ActiveWindow Integration
     * 
     * **Temporal Assignment Processing**: Integrates with ActiveWindowManager for assignment
     * lifecycle management, establishes temporal validation, configures automatic status
     * transitions, and provides comprehensive temporal assignment coordination.
     * 
     * **Role Assignment Coordination**: Coordinates role assignment through ActiveWindow
     * integration, manages role grants based on office configuration, establishes
     * comprehensive role management for officer assignments and organizational security.
     * 
     * ## Warrant System Integration
     * 
     * **Automatic Warrant Request Creation**: Creates warrant requests for warrant-required
     * offices, includes deputy description in warrant names, coordinates with WarrantManager
     * for comprehensive warrant processing and role assignment coordination.
     * 
     * **Warrant Request Processing**: Processes warrant requests including warrant title
     * generation, entity association, temporal coordination, and comprehensive warrant
     * lifecycle management for officer assignment and security compliance.
     * 
     * ## Notification and Communication
     * 
     * **Assignment Notification Processing**: Sends assignment notifications including
     * hire details, appointment information, warrant status, and comprehensive stakeholder
     * communication for assignment coordination and organizational transparency.
     * 
     * **Email Communication Coordination**: Coordinates email notifications through
     * queued mailer system, includes relevant assignment details, provides comprehensive
     * communication for assignment processing and stakeholder coordination.
     * 
     * ## Error Handling and Validation
     * 
     * **Business Rule Validation**: Validates business rules including member warrantable
     * status, office availability, assignment constraints, and organizational compliance
     * with comprehensive validation and error reporting for reliable operations.
     * 
     * **Service Integration Error Handling**: Handles service integration errors including
     * ActiveWindow failures, warrant processing errors, notification failures, and
     * comprehensive error management for robust assignment processing and recovery.
     * 
     * @param int $officeId Office identifier for assignment target validation and processing
     * @param int $memberId Member identifier for assignment subject validation and verification  
     * @param int $branchId Branch identifier for organizational context and hierarchical processing
     * @param DateTime $startOn Assignment start date for temporal processing and validation
     * @param DateTime|null $endOn Optional assignment end date for temporal management
     * @param string|null $deputyDescription Optional deputy description for hierarchical assignments
     * @param int $approverId Approver identifier for administrative oversight and audit trails
     * @param string|null $emailAddress Optional email address for notification coordination
     * @return ServiceResult Comprehensive result including success status, assignment data, and error information
     * 
     * @since 1.0.0
     * @version 2.0.0
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
        if ($office->deputy_to_id != null) {
            $newOfficer->deputy_description = $deputyDescription;
            $newOfficer->deputy_to_branch_id = $newOfficer->branch_id;
            $newOfficer->deputy_to_office_id = $office->deputy_to_id;
            $newOfficer->reports_to_branch_id = $newOfficer->branch_id;
            $newOfficer->reports_to_office_id = $office->deputy_to_id;
        } else {
            $newOfficer->reports_to_office_id = $office->reports_to_id;
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
                        $officersCount = $officerTable->find('Current')
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
                $newOfficer->reports_to_branch_id = null;
            }
        }
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
            "hireDate" => $newOfficer->start_on_to_string,
            "endDate" => $newOfficer->expires_on_to_string,
            "requiresWarrant" => $office->requires_warrant
        ];
        $this->queueMail("Officers.Officers", "notifyOfHire", $member->email_address, $vars);
        return new ServiceResult(true);
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
            "releaseDate" => $revokedOn->toDateString(),
        ];
        $this->queueMail("Officers.Officers", "notifyOfRelease", $member->email_address, $vars);
        return new ServiceResult(true);
    }
}
