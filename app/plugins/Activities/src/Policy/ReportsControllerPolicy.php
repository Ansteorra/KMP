<?php

declare(strict_types=1);

namespace Activities\Policy;

use Authorization\Policy\RequestPolicyInterface;
use Cake\Http\ServerRequest;
use Authorization\Policy\ResultInterface;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;
use App\Model\Entity\BaseEntity;

/**
 * Activities Reports Controller Authorization Policy
 *
 * This policy class defines authorization rules for Activities plugin reporting operations and analytical access
 * within the KMP Activities Plugin ecosystem. It extends the BasePolicy to inherit core RBAC functionality
 * while providing controller-level authorization logic for activity reporting, analytics, and compliance monitoring.
 *
 * ## Purpose
 *
 * - **Reporting Authorization**: Controls access to activity reporting interfaces and analytical operations
 * - **Analytics Access Control**: Manages permissions for activity statistics, metrics, and organizational insights
 * - **Compliance Monitoring**: Governs access to authorization compliance reports and audit data
 * - **Administrative Oversight**: Provides permission-based access to organizational activity reporting
 * - **Data Privacy Protection**: Ensures proper authorization for sensitive organizational and member data
 *
 * ## Authorization Architecture
 *
 * The policy leverages the BasePolicy framework to provide:
 * - **URL-Based Authorization**: Controller action authorization through `_hasPolicyForUrl()`
 * - **Permission Integration**: Uses KMP's permission system for reporting access control
 * - **Warrant Validation**: Temporal validation through warrant requirements for administrative reports
 * - **Branch Scoping**: Organizational hierarchy support for scoped reporting access
 * - **Policy Framework**: Dynamic policy evaluation through permission policy associations
 *
 * ## Reporting Operations Governed
 *
 * ### Activity Authorization Reports
 * - **Member Authorization Status**: Comprehensive reporting on member activity authorization status
 * - **Temporal Analysis**: Historical and current authorization data for compliance monitoring
 * - **Organizational Metrics**: Branch-scoped authorization statistics and participation analytics
 * - **Compliance Tracking**: Authorization compliance monitoring and audit trail reporting
 *
 * ### Warrant Integration Reports
 * - **Activity Warrant Rosters**: Comprehensive warrant-based authorization reporting
 * - **Temporal Validation Reports**: Warrant validity and authorization timeline analysis
 * - **Approval Workflow Analytics**: Multi-level approval process reporting and metrics
 * - **Administrative Oversight**: Cross-organizational warrant and authorization analysis
 *
 * ## Security Implementation
 *
 * ### Permission Requirements
 * Reporting operations typically require permissions such as:
 * - **"Activities.reportAuthorizations"**: Access to member authorization reporting interfaces
 * - **"Activities.reportWarrants"**: Permission for warrant-based reporting and analytics
 * - **"Activities.analyticsAccess"**: General access to activity analytics and organizational metrics
 * - **"Reports.activities"**: Comprehensive reporting permissions for Activities plugin data
 * - **Branch-Scoped Permissions**: Organizational boundaries for reporting data access
 *
 * ### Authorization Patterns
 * ```php
 * // Controller action authorization
 * $this->Authorization->authorize($this->request, 'authorizations');
 * 
 * // URL-based authorization checking
 * if ($this->Authorization->can($user, 'activityWarrantsRoster', $urlParams)) {
 *     // Proceed with warrant roster reporting
 * }
 * ```
 *
 * ## Integration Points
 *
 * - **BasePolicy**: Inherits core RBAC functionality and URL-based authorization
 * - **PermissionsLoader**: Core permission validation engine for warrant checking
 * - **ReportsController**: Authorization enforcement for reporting interfaces
 * - **Activities Plugin**: Integration with activity authorization and warrant systems
 * - **Branch Hierarchy**: Organizational scoping for reporting data access
 *
 * ## Data Privacy and Security
 *
 * ### Sensitive Data Protection
 * - **Member Information**: Authorization reports contain sensitive member qualification data
 * - **Organizational Structure**: Reports reveal organizational hierarchy and activity participation
 * - **Approval Workflows**: Analytics include approval decision history and approver information
 * - **Compliance Data**: Authorization compliance reports contain audit trail information
 *
 * ### Access Control Implementation
 * - **Permission Validation**: All reporting operations require appropriate permission validation
 * - **Branch Scoping**: Organizational boundaries maintained for reporting data access
 * - **Audit Trail Integration**: Reporting access logged for security monitoring and compliance
 * - **Temporal Validation**: Warrant-based authorization for administrative reporting operations
 *
 * ## Performance Considerations
 *
 * - **Permission Caching**: Leverages security cache for repeated authorization checks
 * - **Query Optimization**: Authorization filters integrated with efficient database operations
 * - **Scoped Access**: Branch-based filtering reduces data processing overhead
 * - **Lazy Loading**: Authorization checks performed only when necessary for reporting operations
 *
 * ## Usage Examples
 *
 * ### Controller Integration
 * ```php
 * // In ReportsController actions
 * public function authorizations()
 * {
 *     $this->Authorization->authorize($this->request);
 *     // Proceed with authorization reporting
 * }
 * ```
 *
 * ### Service Layer Integration
 * ```php
 * // In reporting services
 * public function generateActivityReport($reportType, $parameters)
 * {
 *     $urlProps = ['action' => $reportType, 'plugin' => 'Activities'];
 *     if ($this->Authorization->can($user, $reportType, $urlProps)) {
 *         return $this->buildAuthorizedReport($reportType, $parameters);
 *     }
 *     
 *     throw new ForbiddenException('Insufficient permissions for reporting operation');
 * }
 * ```
 *
 * ### Administrative Operations
 * ```php
 * // Administrative reporting with enhanced permissions
 * if ($this->Authorization->can($user, 'activityWarrantsRoster', $urlParams)) {
 *     $comprehensiveReport = $this->generateWarrantRosterReport($parameters);
 * }
 * ```
 *
 * ## Error Handling
 *
 * The policy integrates with CakePHP's authorization framework to provide:
 * - **ForbiddenException**: Clear error messages for unauthorized reporting access
 * - **Audit Logging**: Unauthorized reporting attempts logged for security monitoring
 * - **Graceful Degradation**: Appropriate fallback behavior for limited reporting permissions
 * - **User Feedback**: Clear indication of permission requirements for reporting operations
 *
 * ## Best Practices
 *
 * - Always authorize reporting operations before accessing sensitive organizational data
 * - Implement proper error handling for unauthorized reporting access
 * - Cache permission results for performance in high-volume reporting operations
 * - Regularly audit reporting permissions and access patterns
 * - Consider data sensitivity when designing reporting authorization requirements
 * - Maintain audit trails for all administrative reporting operations
 *
 * @package Activities\Policy
 * @see \App\Policy\BasePolicy For inherited RBAC functionality and URL-based authorization
 * @see \Activities\Controller\ReportsController For reporting interface authorization
 * @see \App\KMP\PermissionsLoader For core permission validation and warrant checking
 * @see \Activities\Model\Table\AuthorizationsTable For authorization data access control
 */
class ReportsControllerPolicy extends BasePolicy
{
    /**
     * Check if the user can access activity warrant roster reports.
     *
     * Determines authorization for accessing comprehensive activity warrant roster reporting
     * functionality, including warrant validation, authorization status, and temporal analysis.
     *
     * **Authorization Logic:**
     * - Delegates to URL-based policy evaluation through BasePolicy framework
     * - Requires administrative permissions for warrant roster reporting access
     * - Integrates with Activities plugin permission policy framework
     * - Supports warrant-based temporal validation for administrative roles
     *
     * **Report Content Access:**
     * Activity warrant roster reports typically include:
     * - Comprehensive warrant status and validation information
     * - Member authorization status across activity types
     * - Temporal analysis of warrant validity and authorization periods
     * - Organizational structure and participation analytics
     * - Approval workflow status and administrative oversight data
     *
     * **Permission Requirements:**
     * Typically requires permissions such as:
     * - "Activities.reportWarrants": Direct access to warrant-based reporting
     * - "Activities.reportAuthorizations": Access to authorization status reporting
     * - "Reports.activities": General reporting permissions for Activities plugin
     * - Branch-scoped permissions: Organizational boundaries for warrant data access
     *
     * **Usage Examples:**
     * ```php
     * // Controller authorization for warrant roster reporting
     * $this->Authorization->authorize($this->request, 'activityWarrantsRoster');
     * 
     * // Service-level authorization check
     * if ($this->Authorization->can($user, 'activityWarrantsRoster', $urlProps)) {
     *     $warrantRoster = $this->generateActivityWarrantRoster($parameters);
     * }
     * ```
     *
     * **Security Considerations:**
     * - Warrant roster reports contain sensitive organizational and member information
     * - Administrative access requires elevated permissions and audit trail logging
     * - Integration with warrant system provides temporal validation for reporting access
     * - Branch scoping maintains organizational privacy boundaries
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param array $urlProps URL properties for authorization context
     * @return bool True if user can access activity warrant roster reports, false otherwise
     * @see \App\Policy\BasePolicy::_hasPolicyForUrl() URL-based authorization validation
     */
    public function canActivityWarrantsRoster(
        KmpIdentityInterface $user,
        array $urlProps,
    ): bool {
        $method = __FUNCTION__;
        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }

    /**
     * Check if the user can access activity authorization reports.
     *
     * Determines authorization for accessing comprehensive activity authorization reporting
     * functionality, including member authorization status, temporal analysis, and organizational metrics.
     *
     * **Authorization Logic:**
     * - Delegates to URL-based policy evaluation through BasePolicy framework
     * - Requires appropriate permissions for authorization reporting access
     * - Integrates with Activities plugin permission policy framework
     * - Supports branch-scoped authorization for organizational reporting boundaries
     *
     * **Report Content Access:**
     * Activity authorization reports typically include:
     * - Member authorization status across all activity types
     * - Temporal analysis of authorization validity and expiration
     * - Organizational participation metrics and statistics
     * - Branch-scoped authorization distribution and analytics
     * - Approval workflow status and processing metrics
     * - Compliance monitoring and audit trail information
     *
     * **Permission Requirements:**
     * Typically requires permissions such as:
     * - "Activities.reportAuthorizations": Direct access to authorization reporting
     * - "Activities.analyticsAccess": Access to activity analytics and organizational metrics
     * - "Reports.activities": General reporting permissions for Activities plugin data
     * - Branch-scoped permissions: Organizational boundaries for authorization data access
     *
     * **Organizational Scoping:**
     * Authorization reports support:
     * - Branch-based filtering for organizational privacy
     * - Hierarchical reporting with "Branch and Children" scoping
     * - Administrative oversight for cross-organizational analysis
     * - Member privacy protection with appropriate access controls
     *
     * **Usage Examples:**
     * ```php
     * // Controller authorization for authorization reporting
     * $this->Authorization->authorize($this->request, 'authorizations');
     * 
     * // Service-level authorization validation
     * if ($this->Authorization->can($user, 'authorizations', $urlProps)) {
     *     $authorizationReport = $this->generateAuthorizationReport($filters);
     * }
     * ```
     *
     * **Analytics and Metrics:**
     * Authorization reports provide access to:
     * - Member participation statistics across activity types
     * - Authorization approval rates and processing times
     * - Organizational activity distribution and trends
     * - Compliance monitoring and regulatory reporting
     * - Administrative oversight and quality assurance metrics
     *
     * **Security Considerations:**
     * - Authorization reports contain sensitive member qualification and participation data
     * - Administrative access requires appropriate permission validation and audit logging
     * - Branch scoping maintains organizational privacy and data isolation
     * - Integration with permission system ensures proper access control validation
     *
     * @param \App\KMP\KmpIdentityInterface $user The requesting user
     * @param array $urlProps URL properties for authorization context
     * @return bool True if user can access activity authorization reports, false otherwise
     * @see \App\Policy\BasePolicy::_hasPolicyForUrl() URL-based authorization validation
     */
    public function canAuthorizations(
        KmpIdentityInterface $user,
        array $urlProps,
    ): bool {
        $method = __FUNCTION__;
        return $this->_hasPolicyForUrl($user, $method, $urlProps);
    }
}
