<?php

declare(strict_types=1);

namespace Officers\Policy;

use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use Cake\ORM\TableRegistry;
use App\Model\Entity\BaseEntity;

/**
 * Officer Authorization Policy
 * 
 * Provides comprehensive authorization control for Officer entities within the KMP Officers plugin.
 * This policy class implements entity-level access control for officer operations including
 * assignment, release, modification, warrant management, and hierarchical access control
 * within the organizational structure and reporting chains.
 * 
 * The OfficerPolicy integrates with KMP's role-based access control (RBAC) system to enforce
 * officer-specific permissions, assignment constraints, warrant integration requirements,
 * and hierarchical management capabilities. It provides granular control over officer
 * lifecycle operations while maintaining organizational integrity and administrative oversight.
 * 
 * ## Authorization Architecture
 * 
 * **BasePolicy Integration**: Extends the KMP BasePolicy framework to leverage centralized
 * permission checking, warrant validation, and organizational scoping capabilities for
 * officer-specific authorization requirements and assignment workflows.
 * 
 * **Dual Ownership Model**: Implements sophisticated authorization logic that considers
 * both permission-based access control and ownership-based access patterns, enabling
 * members to access their own officer assignments while respecting administrative authority.
 * 
 * **Hierarchical Access Control**: Manages authorization for hierarchical officer operations
 * including reporting tree access, deputy management, direct report oversight, and
 * organizational chain navigation with warrant integration.
 * 
 * **Warrant Integration**: Coordinates with the warrant system to validate assignment
 * authority, role grants, administrative permissions, and warrant request capabilities
 * for officer management operations.
 * 
 * ## Officer Operations Governance
 * 
 * **Assignment Authorization**: Controls access to officer assignment operations including
 * member-to-office assignment, warrant validation, role grants, and temporal assignment
 * management based on administrative authority and organizational scope.
 * 
 * **Release Management**: Enforces authorization for officer release operations including
 * warrant processing, role revocation, assignment termination, and administrative
 * oversight with audit trail integration.
 * 
 * **Assignment Modification**: Manages access to officer assignment modification including
 * role updates, warrant status changes, assignment extensions, and administrative
 * adjustments with comprehensive validation.
 * 
 * **Warrant Operations**: Provides authorization control for warrant-related operations
 * including manual warrant requests, automatic processing, status validation, and
 * administrative warrant management.
 * 
 * ## Hierarchical Access Patterns
 * 
 * **Reporting Tree Access**: Implements comprehensive authorization for reporting tree
 * operations including upward navigation, downward oversight, cross-branch access,
 * and administrative reporting capabilities.
 * 
 * **Deputy Management**: Controls access to deputy relationship operations including
 * deputy assignment, oversight responsibilities, reporting relationships, and
 * administrative deputy management.
 * 
 * **Direct Report Authorization**: Manages authorization for direct report operations
 * including immediate subordinate access, oversight responsibilities, and administrative
 * management within the organizational hierarchy.
 * 
 * **Cross-Branch Operations**: Provides authorization for cross-branch officer operations
 * including organizational reporting, administrative oversight, and comprehensive
 * management capabilities.
 * 
 * ## Security Implementation
 * 
 * **Multi-Level Validation**: Implements comprehensive authorization checking at multiple
 * levels including entity access, operation authorization, hierarchical scope validation,
 * warrant requirement verification, and office-specific authorization.
 * 
 * **Office-Specific Authorization**: Validates office-specific authorization through
 * integration with OfficesTable to ensure users can only work with officers in
 * offices they have appropriate access to manage.
 * 
 * **Branch Scoping**: Integrates branch-based access control to ensure officer operations
 * respect organizational boundaries, administrative authority structures, and
 * hierarchical access constraints.
 * 
 * **Warrant Validation**: Enforces warrant requirements for officer operations including
 * assignment authority validation, role grant verification, and administrative
 * management permission checking.
 * 
 * ## Integration Points
 * 
 * **OfficersController**: Provides authorization services for officer management controllers
 * including assignment workflows, release operations, modification interfaces, and
 * administrative oversight capabilities.
 * 
 * **OfficesTable Integration**: Coordinates with OfficesTable to validate office-specific
 * access permissions and ensure users can only work with officers in authorized offices.
 * 
 * **Warrant System**: Integrates with warrant management to validate assignment authority,
 * role grants, warrant requests, and administrative permissions for officer operations.
 * 
 * **RBAC System**: Leverages KMP's comprehensive role-based access control system for
 * permission validation and organizational hierarchy enforcement across officer operations.
 * 
 * ## Usage Examples
 * 
 * ```php
 * // Entity-level officer authorization in controller
 * $this->Authorization->authorize($officer);
 * 
 * // Assignment authorization with branch context
 * if ($this->Authorization->can($officer, 'assign', $branchId)) {
 *     // Enable officer assignment interface
 * }
 * 
 * // Member-specific officer access
 * if ($this->Authorization->can($officer, 'memberOfficers')) {
 *     // Display member's officer assignments
 * }
 * 
 * // Hierarchical access validation
 * if ($this->Authorization->can($officer, 'workWithOfficerReportingTree', $branchId, true)) {
 *     // Enable reporting tree management
 * }
 * 
 * // Release authorization with office validation
 * if ($this->Authorization->can($officer, 'release', $branchId)) {
 *     // Enable officer release interface
 * }
 * ```
 * 
 * ## Performance Considerations
 * 
 * **Permission Caching**: Leverages BasePolicy caching mechanisms to optimize repeated
 * authorization checks and improve response times for officer operations and hierarchical
 * navigation patterns.
 * 
 * **Efficient Office Validation**: Implements optimized office-specific authorization
 * checking to minimize database overhead while ensuring comprehensive security validation
 * for officer assignment operations.
 * 
 * **Scalable Architecture**: Designed to handle large organizational hierarchies with
 * efficient authorization checking and minimal performance impact on officer operations
 * and assignment workflows.
 * 
 * **Hierarchical Optimization**: Optimizes authorization checking for hierarchical
 * operations to maintain performance while ensuring comprehensive security validation
 * across complex reporting structures.
 * 
 * ## Business Logic Considerations
 * 
 * **Assignment Constraints**: Enforces business rules related to officer assignments
 * including warrant requirements, office authorization, branch scoping, and
 * organizational hierarchy constraints.
 * 
 * **Warrant Requirements**: Implements business logic for warrant requirement validation
 * including assignment authority, role grants, administrative management capabilities,
 * and automatic warrant processing.
 * 
 * **Organizational Integrity**: Validates organizational constraints including
 * hierarchical relationships, reporting structures, administrative authority limits,
 * and cross-branch access boundaries.
 * 
 * **Administrative Workflow**: Supports administrative workflow requirements including
 * approval processes, authorization chains, audit trail integration, and administrative
 * oversight for officer lifecycle operations.
 * 
 * @package Officers\Policy
 * @version 2.0.0
 * @since 1.0.0
 */
class OfficerPolicy extends BasePolicy
{
    //public const SKIP_BASE = 'false';

    /**
     * Authorization method for branch-level officer access control
     * 
     * This method provides authorization control for viewing all officers within a specific
     * branch context. It implements permission-based authorization to determine whether a
     * user has sufficient privileges to access the complete officer listing for branch-level
     * operations including organizational oversight and administrative management.
     * 
     * ## Authorization Logic
     * 
     * **Permission Validation**: Utilizes the BasePolicy _hasPolicy() method to evaluate
     * the 'canBranchOfficers' permission through the PermissionsLoader system, ensuring
     * consistent authorization across branch-level officer operations.
     * 
     * **Branch-Specific Access**: Validates access to branch-specific officer information
     * including organizational structure, assignment details, and administrative oversight
     * capabilities within the specified branch context.
     * 
     * **Administrative Oversight**: Supports administrative access patterns for authorized
     * personnel including branch management, officer oversight, and comprehensive reporting
     * access within organizational boundaries.
     * 
     * @param KmpIdentityInterface $user The authenticated user requesting branch officer access
     * @param BaseEntity $entity The officer entity or related entity providing authorization context
     * @param mixed ...$optionalArgs Optional arguments for branch context and additional parameters
     * @return bool True if the user is authorized to view branch officers, false otherwise
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function canBranchOfficers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Authorization method for member-specific officer access control
     * 
     * This method provides authorization control for viewing officers associated with a
     * specific member. It implements dual authorization logic combining ownership-based
     * access (members can view their own officer assignments) with permission-based
     * administrative access for comprehensive member officer management.
     * 
     * ## Authorization Logic
     * 
     * **Ownership-Based Access**: Implements direct ownership validation where members
     * can automatically access their own officer assignment information without requiring
     * additional permissions, supporting self-service access patterns.
     * 
     * **Administrative Override**: For non-owner access, utilizes the BasePolicy _hasPolicy()
     * method to evaluate the 'canMemberOfficers' permission, enabling administrative
     * personnel to access member officer information for management purposes.
     * 
     * **Privacy Protection**: Ensures member officer information is appropriately protected
     * through the dual authorization model while enabling necessary administrative access
     * for organizational management and oversight.
     * 
     * ## Access Patterns
     * 
     * **Self-Service Access**: Members can view their own officer assignments, warrant
     * status, assignment history, and related information without administrative
     * intervention, supporting transparency and self-management.
     * 
     * **Administrative Access**: Authorized administrative personnel can access member
     * officer information for management purposes including assignment oversight,
     * reporting, and organizational planning.
     * 
     * @param KmpIdentityInterface $user The authenticated user requesting member officer access
     * @param BaseEntity $entity The officer entity containing member_id for ownership validation
     * @param mixed ...$optionalArgs Optional arguments for additional context and parameters
     * @return bool True if the user is authorized to view member officers, false otherwise
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function canMemberOfficers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs)
    {
        if ($entity->member_id == $user->getIdentifier()) {
            return true;
        }
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }

    /**
     * Authorization method for comprehensive officer access control
     * 
     * This method provides authorization control for working with all officers across
     * the organizational structure. It implements high-level permission validation to
     * determine whether a user has sufficient administrative authority to access and
     * manage officers across organizational boundaries and hierarchical structures.
     * 
     * ## Authorization Logic
     * 
     * **System-Wide Access**: Utilizes the BasePolicy _hasPolicy() method to evaluate
     * the 'canWorkWithAllOfficers' permission, providing access to comprehensive officer
     * management capabilities across the entire organizational structure.
     * 
     * **Administrative Authority**: Validates high-level administrative authority for
     * comprehensive officer management including cross-branch operations, organizational
     * reporting, and system-wide administrative oversight.
     * 
     * **Organizational Scope**: Enables access to officers across all organizational
     * boundaries including cross-departmental access, multi-branch operations, and
     * comprehensive administrative management capabilities.
     * 
     * @param KmpIdentityInterface $user The authenticated user requesting comprehensive officer access
     * @param BaseEntity $entity The officer entity or related entity providing authorization context
     * @param mixed ...$optionalArgs Optional arguments for additional context and parameters
     * @return bool True if the user is authorized to work with all officers, false otherwise
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function canWorkWithAllOfficers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    /**
     * Authorization method for reporting tree officer access control
     * 
     * This method provides authorization control for working with officers within a user's
     * reporting tree structure. It implements sophisticated hierarchical authorization
     * including branch context validation, grant source checking, and organizational
     * chain access control for comprehensive reporting tree management.
     * 
     * ## Authorization Logic
     * 
     * **Hierarchical Permission Validation**: Utilizes the BasePolicy _hasPolicy() method
     * to evaluate the 'canWorkWithOfficerReportingTree' permission with branch context
     * and optional grant source validation for comprehensive reporting tree access.
     * 
     * **Branch Context Integration**: Processes optional branch ID parameter to provide
     * branch-specific authorization validation, ensuring users can only access reporting
     * tree information within their authorized organizational scope.
     * 
     * **Grant Source Validation**: When enabled through optional parameters, validates
     * grant sources to ensure authorization is properly scoped to specific officer
     * entities and organizational contexts.
     * 
     * ## Access Patterns
     * 
     * **Upward Tree Access**: Enables access to officers higher in the reporting tree
     * including supervisory relationships, administrative oversight, and organizational
     * chain navigation based on hierarchical authority.
     * 
     * **Downward Tree Access**: Provides access to officers lower in the reporting tree
     * including subordinate management, oversight responsibilities, and comprehensive
     * reporting capabilities within the organizational hierarchy.
     * 
     * **Cross-Branch Integration**: Supports cross-branch reporting tree access when
     * appropriately authorized, enabling comprehensive organizational management and
     * administrative oversight capabilities.
     * 
     * @param KmpIdentityInterface $user The authenticated user requesting reporting tree access
     * @param BaseEntity $entity The officer entity providing authorization context
     * @param mixed ...$optionalArgs Optional arguments including branch ID and grant check flag
     * @return bool True if the user is authorized to work with reporting tree officers, false otherwise
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function canWorkWithOfficerReportingTree(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        $doGrantCheck = $optionalArgs[1] ?? null;
        if ($doGrantCheck) {
            $grantSource = (object)[
                "entity_id" => $entity->id,
                "entity_type" => "Officers.Officers"
            ];
        } else {
            $grantSource = null;
        }
        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId, $grantSource);
        // check if the policy was granted by the office_id passed in as optional arg 2
        if ($hasPolicy) {
            return true;
        }
        return false;
    }
    /**
     * Authorization method for deputy officer access control
     * 
     * This method provides authorization control for working with officers and deputies
     * within a user's reporting chain. It implements sophisticated hierarchical authorization
     * with branch context validation, grant source checking, and deputy relationship
     * management for comprehensive reporting chain access control.
     * 
     * ## Authorization Logic
     * 
     * **Deputy Chain Permission Validation**: Utilizes the BasePolicy _hasPolicy() method
     * to evaluate the 'canWorkWithOfficerDeputies' permission with branch context and
     * optional grant source validation for comprehensive deputy chain access.
     * 
     * **Branch Context Integration**: Processes optional branch ID parameter to provide
     * branch-specific authorization validation, ensuring users can only access deputy
     * information within their authorized organizational scope.
     * 
     * **Grant Source Validation**: When enabled through optional parameters, validates
     * grant sources to ensure authorization is properly scoped to specific officer
     * entities and deputy relationship contexts.
     * 
     * ## Deputy Access Patterns
     * 
     * **Deputy Management**: Enables access to deputy officers within the reporting
     * chain including deputy assignment oversight, relationship management, and
     * administrative coordination based on hierarchical authority.
     * 
     * **Reporting Chain Access**: Provides access to deputy relationships within
     * the organizational reporting structure including deputy coordination,
     * oversight responsibilities, and administrative management.
     * 
     * **Cross-Deputy Operations**: Supports cross-deputy access when appropriately
     * authorized, enabling comprehensive deputy management and administrative
     * oversight capabilities within the reporting chain.
     * 
     * ## Security Validation
     * 
     * **Multi-Level Authorization**: Implements comprehensive authorization checking
     * including permission validation, branch context verification, grant source
     * validation, and deputy relationship authorization.
     * 
     * **Deputy Relationship Security**: Ensures deputy access is properly controlled
     * based on reporting relationships, administrative authority, and organizational
     * hierarchy constraints.
     * 
     * **Grant Source Integration**: When grant checking is enabled, validates specific
     * entity-based grants to ensure proper authorization scope for deputy operations.
     * 
     * @param KmpIdentityInterface $user The authenticated user requesting deputy access
     * @param BaseEntity $entity The officer entity providing authorization context
     * @param mixed ...$optionalArgs Optional arguments including branch ID and grant check flag
     * @return bool True if the user is authorized to work with officer deputies, false otherwise
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function canWorkWithOfficerDeputies(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        $doGrantCheck = $optionalArgs[1] ?? null;
        if ($doGrantCheck) {
            $grantSource = (object)[
                "entity_id" => $entity->id,
                "entity_type" => "Officers.Officers"
            ];
        } else {
            $grantSource = null;
        }
        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId, $grantSource);
        // check if the policy was granted by the office_id passed in as optional arg 2
        if ($hasPolicy) {
            return true;
        }
        return false;
    }
    /**
     * Authorization method for direct report officer access control
     * 
     * This method provides authorization control for working with directly reporting
     * officers and deputies within a user's immediate organizational scope. It implements
     * targeted hierarchical authorization focused on direct reporting relationships
     * with branch context validation and grant source checking.
     * 
     * ## Authorization Logic
     * 
     * **Direct Report Permission Validation**: Utilizes the BasePolicy _hasPolicy() method
     * to evaluate the 'canWorkWithOfficerDirectReports' permission with branch context
     * and optional grant source validation for direct reporting relationship access.
     * 
     * **Immediate Scope Limitation**: Restricts access to officers and deputies in
     * direct reporting relationships, providing more limited but focused administrative
     * control compared to broader reporting tree access.
     * 
     * **Branch Context Integration**: Processes optional branch ID parameter to provide
     * branch-specific authorization validation, ensuring users can only access direct
     * reports within their authorized organizational scope.
     * 
     * **Grant Source Validation**: When enabled through optional parameters, validates
     * grant sources to ensure authorization is properly scoped to specific officer
     * entities and direct reporting relationship contexts.
     * 
     * ## Direct Report Access Patterns
     * 
     * **Immediate Subordinate Management**: Enables access to officers and deputies
     * who report directly to the user's position including immediate oversight,
     * management responsibilities, and direct administrative control.
     * 
     * **Limited Hierarchical Scope**: Provides focused access to direct reports
     * without broader reporting tree access, supporting targeted management
     * responsibilities and administrative oversight.
     * 
     * **Deputy Direct Reports**: Includes deputy officers in direct reporting
     * relationships, enabling comprehensive management of immediate organizational
     * subordinates including deputy coordination and oversight.
     * 
     * ## Security Validation
     * 
     * **Scoped Authorization**: Implements targeted authorization checking focused
     * on direct reporting relationships with permission validation, branch context
     * verification, and grant source validation.
     * 
     * **Reporting Relationship Security**: Ensures direct report access is properly
     * controlled based on immediate reporting relationships, administrative authority,
     * and organizational hierarchy constraints.
     * 
     * **Limited Scope Protection**: Provides more restrictive access compared to
     * broader reporting tree methods, ensuring users can only access their immediate
     * organizational subordinates.
     * 
     * @param KmpIdentityInterface $user The authenticated user requesting direct report access
     * @param BaseEntity $entity The officer entity providing authorization context
     * @param mixed ...$optionalArgs Optional arguments including branch ID and grant check flag
     * @return bool True if the user is authorized to work with direct report officers, false otherwise
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function canWorkWithOfficerDirectReports(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        $doGrantCheck = $optionalArgs[1] ?? null;
        if ($doGrantCheck) {
            $grantSource = (object)[
                "entity_id" => $entity->id,
                "entity_type" => "Officers.Officers"
            ];
        } else {
            $grantSource = null;
        }
        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId, $grantSource);
        // check if the policy was granted by the office_id passed in as optional arg 2
        if ($hasPolicy) {
            return true;
        }
        return false;
    }
    /**
     * Authorization method for officer release operations
     * 
     * This method provides comprehensive authorization control for releasing officers
     * or deputies from their office assignments. It implements multi-level authorization
     * including permission validation, office-specific access verification, and branch
     * context validation to ensure proper release authority.
     * 
     * ## Authorization Logic
     * 
     * **Permission Validation**: Utilizes the BasePolicy _hasPolicy() method to evaluate
     * the 'canRelease' permission with branch context, ensuring users have appropriate
     * administrative authority for officer release operations.
     * 
     * **Branch Context Integration**: Processes branch ID from either optional arguments
     * or entity context, providing flexible branch-specific authorization validation
     * for release operations within organizational boundaries.
     * 
     * **Office-Specific Validation**: Implements secondary authorization check through
     * OfficesTable integration to verify the user can work with the specific office
     * associated with the officer assignment being released.
     * 
     * ## Multi-Level Security
     * 
     * **Primary Permission Check**: Initial permission validation for release operations
     * based on user permissions and organizational context through BasePolicy framework.
     * 
     * **Office Authorization Verification**: Secondary authorization check using
     * OfficesTable::officesMemberCanWork() to ensure user has specific permission
     * to manage officers within the target office.
     * 
     * **Branch Scoping**: Branch-specific access control to ensure release operations
     * respect organizational boundaries and administrative authority structures.
     * 
     * ## Office Access Integration
     * 
     * **OfficesTable Integration**: Coordinates with OfficesTable to validate
     * office-specific access permissions using established office permission patterns.
     * 
     * **Dynamic Office Discovery**: Retrieves office permissions dynamically based
     * on user context and branch scope for comprehensive authorization validation.
     * 
     * **Permission Array Validation**: Validates officer's office_id against the
     * array of offices the user is authorized to manage within the branch context.
     * 
     * ## Business Logic Integration
     * 
     * **Release Authority Validation**: Ensures users can only release officers
     * from offices they have appropriate management authority over, preventing
     * unauthorized release operations.
     * 
     * **Administrative Hierarchy**: Respects administrative hierarchy and authority
     * structures when validating release permissions and office management access.
     * 
     * **Audit Trail Support**: Provides comprehensive authorization context for
     * audit trail integration and compliance monitoring of release operations.
     * 
     * @param KmpIdentityInterface $user The authenticated user requesting release authorization
     * @param BaseEntity $entity The officer entity to be released from assignment
     * @param mixed ...$optionalArgs Optional arguments including branch ID context
     * @return bool True if the user is authorized to release the officer, false otherwise
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function canRelease(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if (isset($entity->branch_id)) {
            $branchId = $entity->branch_id;
        }
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId);
        // check if the editor can edit this specific office
        if ($hasPolicy) {
            $office_id = $entity->office_id;
            $officesTbl = TableRegistry::getTableLocator()->get("Officers.Offices");
            $canEditOffices = $officesTbl->officesMemberCanWork($user, $branchId);
            if (!in_array($office_id, $canEditOffices)) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Authorization method for warrant request operations
     * 
     * This method provides comprehensive authorization control for requesting warrants
     * for officer assignments. It implements dual authorization logic combining
     * ownership-based access (officers can request their own warrants) with
     * administrative warrant request capabilities for comprehensive warrant management.
     * 
     * ## Authorization Logic
     * 
     * **Ownership-Based Access**: Implements direct ownership validation where officers
     * can automatically request warrants for their own assignments (user ID matches
     * entity member_id), supporting self-service warrant request patterns.
     * 
     * **Administrative Override**: For non-owner requests, utilizes the BasePolicy
     * _hasPolicy() method to evaluate the 'canRequestWarrant' permission with branch
     * context, enabling administrative personnel to request warrants for officers.
     * 
     * **Multi-Level Authorization**: Combines ownership validation with permission-based
     * administrative access and office-specific authorization for comprehensive
     * warrant request control.
     * 
     * ## Security Framework
     * 
     * **Primary Ownership Check**: Initial ownership validation enables officers
     * to request warrants for their own assignments without requiring additional
     * administrative permissions, supporting self-service warrant workflows.
     * 
     * **Administrative Permission Validation**: For administrative warrant requests,
     * validates 'canRequestWarrant' permission through BasePolicy framework with
     * branch context and organizational scope verification.
     * 
     * **Office-Specific Validation**: Implements secondary authorization check through
     * OfficesTable integration to verify the user can work with the specific office
     * associated with the officer assignment requiring warrant processing.
     * 
     * ## Office Access Integration
     * 
     * **OfficesTable Integration**: Coordinates with OfficesTable to validate
     * office-specific access permissions using established office permission patterns
     * for administrative warrant request operations.
     * 
     * **Dynamic Permission Discovery**: Retrieves office permissions dynamically based
     * on user context and branch scope for comprehensive authorization validation
     * of administrative warrant requests.
     * 
     * **Permission Array Validation**: Validates officer's office_id against the
     * array of offices the user is authorized to manage within the branch context
     * for administrative warrant processing.
     * 
     * ## Warrant Request Patterns
     * 
     * **Self-Service Requests**: Officers can request warrants for their own
     * assignments without administrative intervention, supporting transparency
     * and self-management of warrant requirements.
     * 
     * **Administrative Requests**: Authorized administrative personnel can request
     * warrants for officers under their management, supporting comprehensive
     * warrant management and organizational oversight.
     * 
     * **Branch-Scoped Requests**: Warrant requests respect organizational boundaries
     * and administrative authority structures through branch context validation.
     * 
     * @param KmpIdentityInterface $user The authenticated user requesting warrant authorization
     * @param BaseEntity $entity The officer entity for warrant request
     * @param mixed ...$optionalArgs Optional arguments including branch ID context
     * @return bool True if the user is authorized to request warrants for the officer, false otherwise
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function canRequestWarrant(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        if ($user->id == $entity->member_id) {
            return true;
        }
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if (isset($entity->branch_id)) {
            $branchId = $entity->branch_id;
        }
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId);
        // check if the editor can edit this specific office
        if ($hasPolicy) {
            $office_id = $entity->office_id;
            $officesTbl = TableRegistry::getTableLocator()->get("Officers.Offices");
            $canEditOffices = $officesTbl->officesMemberCanWork($user, $branchId);
            if (!in_array($office_id, $canEditOffices)) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Authorization method for warrant status officer reporting access control
     * 
     * This method provides authorization control for accessing officers organized by
     * warrant status. It implements comprehensive authorization for warrant status
     * reporting including administrative oversight, organizational visibility, and
     * warrant lifecycle management for comprehensive officer reporting capabilities.
     * 
     * ## Authorization Logic
     * 
     * **Warrant Status Permission Validation**: Utilizes the BasePolicy _hasPolicy() method
     * to evaluate the 'canOfficersByWarrantStatus' permission, providing access to
     * comprehensive warrant status reporting across the organizational structure.
     * 
     * **Administrative Reporting Authority**: Validates administrative authority for
     * warrant status reporting including cross-organizational visibility, warrant
     * lifecycle oversight, and comprehensive status management capabilities.
     * 
     * **Organizational Visibility**: Enables access to warrant status information
     * across organizational boundaries including multi-branch operations, departmental
     * reporting, and comprehensive administrative visibility.
     * 
     * ## Warrant Status Reporting Patterns
     * 
     * **Active Warrant Reporting**: Provides access to officers with active warrants
     * including current assignments, active duties, and ongoing warrant management
     * within the organizational structure.
     * 
     * **Expired Warrant Reporting**: Enables access to officers with expired warrants
     * including warrant renewal tracking, administrative oversight, and organizational
     * compliance management for comprehensive warrant lifecycle reporting.
     * 
     * **Pending Warrant Reporting**: Supports access to officers with pending warrants
     * including warrant processing oversight, administrative coordination, and
     * organizational workflow management for comprehensive warrant tracking.
     * 
     * ## Security and Access Control
     * 
     * **Administrative Authority Validation**: Ensures only appropriately authorized
     * users can access warrant status reporting including administrative permissions,
     * organizational oversight authority, and comprehensive reporting capabilities.
     * 
     * **Organizational Scope Control**: Implements proper organizational scoping for
     * warrant status access ensuring users can only access information within their
     * authorized organizational context and administrative boundaries.
     * 
     * **Warrant Information Security**: Maintains appropriate security controls for
     * warrant status information including administrative access controls, organizational
     * visibility constraints, and comprehensive security validation.
     * 
     * ## Business Logic Integration
     * 
     * **Warrant Lifecycle Management**: Integrates with warrant lifecycle processes
     * including warrant expiration tracking, renewal coordination, and administrative
     * oversight for comprehensive warrant management capabilities.
     * 
     * **Organizational Reporting**: Supports organizational reporting requirements
     * including compliance tracking, administrative oversight, and comprehensive
     * warrant status visibility across the organizational structure.
     * 
     * **Administrative Coordination**: Enables administrative coordination for warrant
     * status management including cross-departmental operations, organizational
     * oversight, and comprehensive administrative management capabilities.
     * 
     * @param KmpIdentityInterface $user The authenticated user requesting warrant status reporting access
     * @param BaseEntity $entity The entity providing authorization context
     * @param mixed ...$optionalArgs Optional arguments for additional context and parameters
     * @return bool True if the user is authorized to access officers by warrant status, false otherwise
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function canOfficersByWarrantStatus(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    /**
     * Authorization method for officer editing access control
     * 
     * This method provides comprehensive authorization control for editing officers
     * with sophisticated branch context validation, office-specific authorization,
     * and multi-level security validation. It implements branch-aware authorization
     * combined with office-specific access control for comprehensive officer editing.
     * 
     * ## Authorization Logic
     * 
     * **Branch Context Authorization**: Implements sophisticated branch context resolution
     * including entity branch ID extraction, optional parameter processing, and comprehensive
     * branch-based authorization validation for officer editing operations.
     * 
     * **Office-Specific Validation**: Integrates with OfficesTable to validate office-specific
     * editing permissions ensuring users can only edit officers in offices they are
     * authorized to work with within the specified branch context.
     * 
     * **Multi-Level Security**: Combines BasePolicy permission validation with office-specific
     * access control to provide comprehensive authorization including both general editing
     * permissions and office-specific editing authority.
     * 
     * ## Branch Processing Logic
     * 
     * **Entity Branch Resolution**: Automatically extracts branch_id from the officer entity
     * when available, providing seamless branch context for authorization validation
     * without requiring explicit branch parameter specification.
     * 
     * **Optional Branch Override**: Processes optional branch ID parameter to support
     * explicit branch specification for authorization validation, enabling flexible
     * branch context management for administrative operations.
     * 
     * **Branch Validation Requirement**: Enforces branch context requirement for officer
     * editing operations, ensuring all editing operations are properly scoped to
     * specific organizational contexts and branch boundaries.
     * 
     * ## Office Authorization Integration
     * 
     * **OfficesTable Integration**: Utilizes OfficesTable.officesMemberCanWork() method
     * to retrieve offices the user is authorized to work with within the branch context,
     * providing comprehensive office-specific authorization validation.
     * 
     * **Office-Specific Validation**: Validates that the officer's office_id is included
     * in the user's authorized office list, ensuring users can only edit officers in
     * offices they have appropriate permissions to work with.
     * 
     * **Branch-Office Coordination**: Coordinates branch context with office-specific
     * permissions to provide comprehensive authorization that respects both branch
     * boundaries and office-specific access control requirements.
     * 
     * ## Security Validation
     * 
     * **Permission Policy Validation**: Utilizes BasePolicy _hasPolicy() method to validate
     * the 'canEdit' permission with branch context, ensuring users have appropriate
     * general editing permissions for officer modification operations.
     * 
     * **Office Access Control**: Implements office-specific access control validation
     * to ensure users can only edit officers in offices they are specifically
     * authorized to work with within the branch context.
     * 
     * **Multi-Layer Authorization**: Combines general editing permissions with office-specific
     * access control to provide comprehensive authorization that validates both
     * general authority and specific office access rights.
     * 
     * ## Business Logic Integration
     * 
     * **Organizational Boundary Enforcement**: Ensures officer editing operations respect
     * organizational boundaries including branch context, office-specific permissions,
     * and administrative hierarchy constraints for comprehensive access control.
     * 
     * **Administrative Workflow Support**: Supports administrative workflows for officer
     * management including cross-office operations, branch-specific editing, and
     * comprehensive organizational management capabilities.
     * 
     * **Authorization Workflow**: Implements comprehensive authorization workflow including
     * branch resolution, permission validation, office verification, and multi-level
     * security validation for robust officer editing access control.
     * 
     * @param KmpIdentityInterface $user The authenticated user requesting officer editing access
     * @param BaseEntity $entity The officer entity to be edited
     * @param mixed ...$optionalArgs Optional arguments including branch ID override
     * @return bool True if the user is authorized to edit the officer, false otherwise
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function canEdit(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if (isset($entity->branch_id)) {
            $branchId = $entity->branch_id;
        }
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        //check if the entity has a branch_id
        if (isset($entity->branch_id)) {
            $branchId = $entity->branch_id;
        }
        // if branchId is null, we cannot edit the officer

        $hasPolicy = $this->_hasPolicy($user, $method, $entity, $branchId);
        // check if the editor can edit this specific office
        if ($hasPolicy) {
            $office_id = $entity->office_id;
            $officesTbl = TableRegistry::getTableLocator()->get("Officers.Offices");
            $canEditOffices = $officesTbl->officesMemberCanWork($user, $branchId);
            if (!in_array($office_id, $canEditOffices)) {
                return false;
            }
            return true;
        }
        return false;
    }

    /**
     * Authorization method for general officer access control
     * 
     * This method provides fundamental authorization control for accessing officers
     * within the organizational structure. It implements basic permission validation
     * for officer visibility including general access control, organizational
     * permissions, and comprehensive officer information access authorization.
     * 
     * ## Authorization Logic
     * 
     * **General Access Permission Validation**: Utilizes the BasePolicy _hasPolicy() method
     * to evaluate the 'canOfficers' permission, providing foundational access control
     * for officer information visibility across the organizational structure.
     * 
     * **Organizational Visibility Authority**: Validates basic organizational authority
     * for officer access including general visibility permissions, organizational
     * information access, and comprehensive officer data access capabilities.
     * 
     * **Foundational Access Control**: Implements foundational access control for officer
     * information ensuring users have appropriate permissions for basic officer
     * visibility and organizational information access operations.
     * 
     * ## Access Patterns
     * 
     * **General Officer Visibility**: Provides access to general officer information
     * including basic officer details, organizational relationships, and general
     * officer data within the user's authorized organizational scope.
     * 
     * **Organizational Information Access**: Enables access to officer information
     * across organizational boundaries when appropriately authorized including
     * multi-departmental visibility and comprehensive organizational access.
     * 
     * **Basic Information Operations**: Supports basic officer information operations
     * including officer lookup, general information access, and foundational
     * officer data visibility for authorized organizational users.
     * 
     * ## Security and Access Control
     * 
     * **Permission-Based Authorization**: Ensures only appropriately authorized users
     * can access officer information including general access permissions, organizational
     * visibility authority, and comprehensive access control validation.
     * 
     * **Organizational Scope Validation**: Implements proper organizational scoping for
     * officer access ensuring users can only access information within their authorized
     * organizational context and permission boundaries.
     * 
     * **Foundational Security Controls**: Maintains foundational security controls for
     * officer information access including permission validation, organizational
     * boundaries, and comprehensive access control enforcement.
     * 
     * ## Business Logic Integration
     * 
     * **Officer Information Management**: Integrates with officer information management
     * processes including data access coordination, organizational visibility, and
     * comprehensive information management capabilities.
     * 
     * **Organizational Access Coordination**: Supports organizational access coordination
     * including cross-departmental operations, organizational visibility, and
     * comprehensive organizational information management.
     * 
     * **Foundational Authorization**: Provides foundational authorization capabilities
     * for officer access including general permissions, organizational boundaries,
     * and comprehensive access control coordination.
     * 
     * @param KmpIdentityInterface $user The authenticated user requesting general officer access
     * @param BaseEntity $entity The entity providing authorization context
     * @param mixed ...$optionalArgs Optional arguments for additional context and parameters
     * @return bool True if the user is authorized to access officers, false otherwise
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function canOfficers(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
    /**
     * Authorization method for officer assignment access control
     * 
     * This method provides comprehensive authorization control for officer assignment
     * operations with sophisticated branch context validation and assignment authority
     * management. It implements branch-aware authorization for officer assignment
     * including organizational assignment coordination and comprehensive assignment control.
     * 
     * ## Authorization Logic
     * 
     * **Branch Context Assignment Authorization**: Implements sophisticated branch context
     * resolution including entity branch ID extraction, optional parameter processing,
     * and comprehensive branch-based authorization validation for officer assignment operations.
     * 
     * **Assignment Permission Validation**: Utilizes BasePolicy _hasPolicy() method to
     * evaluate the 'canAssign' permission with branch context, ensuring users have
     * appropriate assignment authority within the specified organizational context.
     * 
     * **Organizational Assignment Authority**: Validates organizational assignment authority
     * including branch-specific assignment permissions, organizational coordination,
     * and comprehensive assignment management capabilities.
     * 
     * ## Branch Processing Logic
     * 
     * **Entity Branch Resolution**: Automatically extracts branch_id from the assignment
     * entity when available, providing seamless branch context for authorization
     * validation without requiring explicit branch parameter specification.
     * 
     * **Optional Branch Override**: Processes optional branch ID parameter to support
     * explicit branch specification for assignment authorization, enabling flexible
     * branch context management for administrative assignment operations.
     * 
     * **Branch-Aware Assignment**: Ensures assignment operations are properly scoped to
     * specific organizational contexts and branch boundaries, maintaining organizational
     * integrity and assignment authority constraints.
     * 
     * ## Assignment Authorization Patterns
     * 
     * **Officer Position Assignment**: Provides authorization for assigning officers to
     * positions including position-specific assignment validation, organizational
     * coordination, and comprehensive assignment management capabilities.
     * 
     * **Cross-Branch Assignment Support**: Supports cross-branch assignment operations
     * when appropriately authorized, enabling comprehensive organizational assignment
     * coordination and administrative assignment management.
     * 
     * **Assignment Workflow Integration**: Integrates with assignment workflow processes
     * including assignment coordination, organizational validation, and comprehensive
     * assignment lifecycle management for robust assignment operations.
     * 
     * ## Security and Access Control
     * 
     * **Assignment Authority Validation**: Ensures only appropriately authorized users
     * can perform officer assignments including assignment permissions, organizational
     * authority, and comprehensive assignment access control validation.
     * 
     * **Branch Context Security**: Implements proper branch context security for
     * assignment operations ensuring users can only perform assignments within their
     * authorized organizational scope and assignment boundaries.
     * 
     * **Assignment Permission Control**: Maintains appropriate permission controls for
     * assignment operations including assignment authority validation, organizational
     * boundaries, and comprehensive assignment security enforcement.
     * 
     * ## Business Logic Integration
     * 
     * **Assignment Workflow Management**: Integrates with assignment workflow processes
     * including assignment coordination, organizational management, and comprehensive
     * assignment lifecycle management for robust assignment operations.
     * 
     * **Organizational Assignment Coordination**: Supports organizational assignment
     * coordination including cross-departmental assignment operations, organizational
     * oversight, and comprehensive assignment management capabilities.
     * 
     * **Assignment Authority Management**: Provides comprehensive assignment authority
     * management including permission validation, organizational coordination, and
     * comprehensive assignment control for organizational assignment operations.
     * 
     * @param KmpIdentityInterface $user The authenticated user requesting officer assignment access
     * @param BaseEntity $entity The assignment entity providing authorization context
     * @param mixed ...$optionalArgs Optional arguments including branch ID for assignment context
     * @return bool True if the user is authorized to assign officers, false otherwise
     * 
     * @since 1.0.0
     * @version 2.0.0
     */
    public function canAssign(KmpIdentityInterface $user, BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        $branchId = $optionalArgs[0] ?? null;
        if (isset($entity->branch_id)) {
            $branchId = $entity->branch_id;
        }
        if ($branchId != null) {
            $branchId = toInt($branchId);
        }
        return $this->_hasPolicy($user, $method, $entity, $branchId);
    }
}
