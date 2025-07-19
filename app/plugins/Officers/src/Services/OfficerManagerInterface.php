<?php

declare(strict_types=1);

namespace Officers\Services;

use Cake\I18n\DateTime;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;

/**
 * Officer Manager Service Interface
 * 
 * Defines the comprehensive service contract for officer lifecycle management within the KMP
 * Officers plugin. This interface establishes the core business logic requirements for officer
 * assignment, release, and warrant integration operations including temporal validation,
 * role management, and organizational workflow coordination.
 * 
 * The OfficerManagerInterface provides a standardized contract for officer management services
 * ensuring consistency across different implementations while supporting complex business
 * requirements including ActiveWindow integration, warrant system coordination, and
 * comprehensive assignment workflow management.
 * 
 * ## Service Architecture
 * 
 * **Business Logic Abstraction**: Defines standardized methods for officer lifecycle operations
 * including assignment creation, release processing, warrant management, and temporal
 * validation to ensure consistent implementation across service providers.
 * 
 * **ActiveWindow Integration**: Establishes integration requirements with ActiveWindowManager
 * for temporal officer assignment management including start/end date validation,
 * automatic status transitions, and assignment lifecycle coordination.
 * 
 * **Warrant System Coordination**: Defines warrant integration requirements for automatic
 * role assignment, warrant validation, role grant management, and administrative
 * oversight coordination throughout officer lifecycle operations.
 * 
 * **Transaction Management**: Requires external transaction management for assignment and
 * release operations to ensure data consistency, rollback capabilities, and
 * comprehensive error handling across complex multi-table operations.
 * 
 * ## Officer Assignment Workflow
 * 
 * **Assignment Creation**: Defines requirements for officer assignment operations including
 * office validation, member verification, branch context validation, temporal assignment
 * processing, and warrant integration for comprehensive assignment workflow.
 * 
 * **Deputy Management**: Establishes deputy assignment capabilities including deputy
 * description processing, hierarchical relationship management, and organizational
 * structure coordination for comprehensive deputy assignment operations.
 * 
 * **Temporal Validation**: Requires temporal assignment validation including start date
 * processing, optional end date management, assignment overlap detection, and
 * ActiveWindow integration for comprehensive temporal management.
 * 
 * **Administrative Oversight**: Defines administrative approval requirements including
 * approver validation, administrative authority verification, and audit trail
 * creation for comprehensive assignment oversight and accountability.
 * 
 * ## Officer Release Management
 * 
 * **Release Processing**: Establishes requirements for officer release operations including
 * officer validation, release date processing, reason documentation, and warrant
 * coordination for comprehensive release workflow management.
 * 
 * **Administrative Authority**: Defines administrative requirements for release operations
 * including revoker authority validation, administrative oversight, and approval
 * workflow coordination for comprehensive release authorization.
 * 
 * **Audit Trail Management**: Requires comprehensive audit trail creation including release
 * documentation, reason tracking, administrative oversight, and historical record
 * maintenance for comprehensive accountability and compliance.
 * 
 * **Role Revocation**: Establishes warrant integration requirements for automatic role
 * revocation, warrant processing, role grant cleanup, and administrative coordination
 * during officer release operations.
 * 
 * ## Implementation Requirements
 * 
 * **Error Handling**: Implementations must provide comprehensive error handling including
 * validation error reporting, business rule violation detection, system error management,
 * and detailed error messaging for robust service operation.
 * 
 * **Performance Considerations**: Service implementations should optimize database operations,
 * minimize transaction scope, implement efficient validation, and provide scalable
 * performance for high-volume officer management operations.
 * 
 * **Security Validation**: Implementations must enforce security requirements including
 * authorization validation, data integrity verification, administrative authority
 * checking, and comprehensive security audit trail maintenance.
 * 
 * **Integration Compatibility**: Service implementations must maintain compatibility with
 * ActiveWindow management, warrant system integration, role management, and
 * organizational hierarchy systems for comprehensive ecosystem integration.
 * 
 * ## Service Result Patterns
 * 
 * **Success Indicators**: Service methods return ServiceResult objects indicating operation
 * success, failure conditions, validation errors, and detailed result information
 * for comprehensive operation feedback and error handling.
 * 
 * **Data Return**: Successful operations should return relevant entity data, assignment
 * information, warrant details, and operation metadata for comprehensive result
 * processing and integration with calling systems.
 * 
 * **Error Reporting**: Failed operations must provide detailed error information including
 * validation failures, business rule violations, system errors, and actionable
 * error messages for comprehensive error handling and user feedback.
 * 
 * @package Officers\Services
 * @since 1.0.0
 * @version 2.0.0
 */
interface OfficerManagerInterface
{
    /**
     * Officer Assignment Service Method
     * 
     * Assigns a member to an office position with comprehensive validation, warrant integration,
     * and temporal management. This method handles the complete officer assignment workflow
     * including office validation, member verification, branch context processing, deputy
     * management, and ActiveWindow integration for robust assignment operations.
     * 
     * ## Assignment Workflow
     * 
     * **Office Validation**: Validates office existence, availability, warrant requirements,
     * hierarchical constraints, and assignment eligibility to ensure valid assignment
     * target and compliance with organizational structure requirements.
     * 
     * **Member Verification**: Verifies member eligibility including active status, branch
     * membership, assignment constraints, conflict detection, and eligibility requirements
     * for comprehensive member validation and assignment authorization.
     * 
     * **Branch Context Processing**: Validates branch context including branch existence,
     * office-branch compatibility, member branch association, and organizational scope
     * to ensure proper organizational context for assignment operations.
     * 
     * **Temporal Management**: Processes assignment dates including start date validation,
     * optional end date processing, overlap detection, ActiveWindow integration, and
     * temporal constraint validation for comprehensive assignment scheduling.
     * 
     * ## Deputy Assignment Support
     * 
     * **Deputy Description Processing**: Handles optional deputy description including
     * deputy role definition, hierarchical relationship establishment, reporting
     * structure coordination, and deputy-specific assignment configuration.
     * 
     * **Hierarchical Integration**: Establishes hierarchical relationships including
     * deputy chain management, reporting structure updates, organizational hierarchy
     * coordination, and comprehensive hierarchical assignment integration.
     * 
     * ## Administrative Oversight
     * 
     * **Approver Validation**: Validates approver authority including administrative
     * permissions, assignment authority, organizational scope, and approval workflow
     * requirements for comprehensive administrative oversight and accountability.
     * 
     * **Audit Trail Creation**: Creates comprehensive audit trail including assignment
     * details, administrative approval, temporal information, and historical record
     * maintenance for accountability and compliance tracking.
     * 
     * ## Warrant Integration
     * 
     * **Automatic Role Assignment**: Coordinates with warrant system for automatic role
     * grants, permission assignment, warrant processing, and role management based
     * on office requirements and organizational policies.
     * 
     * **Warrant Validation**: Validates warrant requirements including warrant eligibility,
     * role prerequisites, administrative authority, and warrant system integration
     * for comprehensive warrant coordination and compliance.
     * 
     * ## Transaction Requirements
     * 
     * **External Transaction Management**: Requires calling code to establish database
     * transaction before method invocation to ensure data consistency, rollback
     * capabilities, and comprehensive error handling across multi-table operations.
     * 
     * **Data Consistency**: Ensures atomic operations across multiple entities including
     * officer creation, warrant processing, role assignment, and ActiveWindow
     * management for comprehensive data integrity and consistency.
     * 
     * ## Error Handling
     * 
     * **Validation Errors**: Returns detailed validation error information including
     * field-specific errors, business rule violations, constraint failures, and
     * actionable error messages for comprehensive error reporting and user feedback.
     * 
     * **Business Logic Errors**: Handles business rule violations including assignment
     * conflicts, eligibility failures, warrant violations, and organizational
     * constraint violations for robust business logic enforcement.
     * 
     * **System Errors**: Manages system-level errors including database failures,
     * service integration issues, warrant system errors, and external service
     * coordination problems for comprehensive error management.
     * 
     * @param int $officeId The office ID for assignment target validation and processing
     * @param int $memberId The member ID for assignment subject validation and verification
     * @param int $branchId The branch ID for organizational context validation and scope management
     * @param DateTime $startOn The assignment start date for temporal validation and ActiveWindow integration
     * @param DateTime|null $endOn Optional assignment end date for temporal management and scheduling
     * @param string|null $deputyDescription Optional deputy description for hierarchical assignment coordination
     * @param int $approverId The approver ID for administrative oversight and audit trail creation
     * @param string|null $emailAddress Optional email address for notification and communication coordination
     * @return ServiceResult Comprehensive service result including success/failure status, assignment data, and error information
     * 
     * @throws \InvalidArgumentException When required parameters are invalid or missing
     * @throws \RuntimeException When system-level errors prevent assignment processing
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
    ): ServiceResult;

    /**
     * Officer Release Service Method
     * 
     * Releases a member from an office position with comprehensive validation, warrant coordination,
     * and administrative oversight. This method handles the complete officer release workflow
     * including officer validation, release processing, warrant cleanup, role revocation,
     * and audit trail management for robust release operations.
     * 
     * ## Release Workflow
     * 
     * **Officer Validation**: Validates officer existence, active status, release eligibility,
     * assignment verification, and release constraints to ensure valid release target
     * and compliance with organizational policies and business rules.
     * 
     * **Release Date Processing**: Processes release date including date validation, temporal
     * consistency checking, ActiveWindow coordination, assignment closure, and
     * comprehensive temporal management for accurate release scheduling.
     * 
     * **Administrative Authorization**: Validates release authority including revoker permissions,
     * administrative oversight, organizational scope, and release approval workflow
     * to ensure proper administrative control and accountability.
     * 
     * ## Warrant System Coordination
     * 
     * **Automatic Role Revocation**: Coordinates with warrant system for automatic role
     * cleanup, permission revocation, warrant processing, and role management during
     * officer release to maintain security and organizational integrity.
     * 
     * **Warrant Processing**: Handles warrant coordination including warrant closure,
     * role grant cleanup, permission revocation, and warrant system integration
     * for comprehensive warrant lifecycle management during release operations.
     * 
     * ## Administrative Oversight
     * 
     * **Revoker Authority Validation**: Validates revoker administrative authority including
     * release permissions, organizational scope, administrative oversight, and
     * approval workflow requirements for comprehensive release authorization.
     * 
     * **Reason Documentation**: Processes optional release reason including reason
     * validation, documentation requirements, audit trail integration, and
     * comprehensive release documentation for accountability and compliance.
     * 
     * ## Audit Trail Management
     * 
     * **Release Documentation**: Creates comprehensive release documentation including
     * release details, administrative authority, reason documentation, and temporal
     * information for complete audit trail and historical record maintenance.
     * 
     * **Historical Record Maintenance**: Maintains historical assignment records including
     * release processing, administrative oversight, reason documentation, and
     * comprehensive historical tracking for accountability and compliance monitoring.
     * 
     * ## ActiveWindow Integration
     * 
     * **Assignment Closure**: Coordinates with ActiveWindow system for assignment closure,
     * temporal validation, status transitions, and comprehensive assignment lifecycle
     * management during release processing and organizational coordination.
     * 
     * **Status Management**: Handles assignment status transitions including closure
     * processing, temporal coordination, status validation, and comprehensive
     * status management for accurate assignment lifecycle tracking.
     * 
     * ## Transaction Requirements
     * 
     * **External Transaction Management**: Requires calling code to establish database
     * transaction before method invocation to ensure data consistency, rollback
     * capabilities, and comprehensive error handling across multi-table operations.
     * 
     * **Data Consistency**: Ensures atomic operations across multiple entities including
     * officer release, warrant cleanup, role revocation, and ActiveWindow management
     * for comprehensive data integrity and consistency during release operations.
     * 
     * ## Error Handling
     * 
     * **Validation Errors**: Returns detailed validation error information including
     * officer validation failures, authorization errors, date validation issues,
     * and actionable error messages for comprehensive error reporting and feedback.
     * 
     * **Business Logic Errors**: Handles business rule violations including release
     * constraints, authorization failures, warrant violations, and organizational
     * policy violations for robust business logic enforcement and compliance.
     * 
     * **System Errors**: Manages system-level errors including database failures,
     * warrant system integration issues, ActiveWindow coordination problems, and
     * external service errors for comprehensive error management and recovery.
     * 
     * @param int $officerId The officer ID for release target validation and processing
     * @param int $revokerId The revoker ID for administrative authority validation and audit trail creation
     * @param DateTime $revokedOn The release date for temporal processing and ActiveWindow coordination
     * @param string|null $revokedReason Optional release reason for documentation and audit trail management
     * @return ServiceResult Comprehensive service result including success/failure status, release data, and error information
     * 
     * @throws \InvalidArgumentException When required parameters are invalid or missing
     * @throws \RuntimeException When system-level errors prevent release processing
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function release(
        int $officerId,
        int $revokerId,
        DateTime $revokedOn,
        ?string $revokedReason
    ): ServiceResult;
}
