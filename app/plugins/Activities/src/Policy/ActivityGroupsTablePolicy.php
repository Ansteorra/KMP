<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Table\ActivityGroupsTable;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

/**
 * Activity Groups Table Authorization Policy
 *
 * This policy class defines authorization rules for ActivityGroups table operations within the KMP Activities plugin.
 * It extends the BasePolicy to inherit core RBAC functionality while providing table-level authorization logic
 * for bulk operations, queries, and administrative access to the activity groups data layer.
 *
 * ## Purpose
 *
 * - **Table-Level Authorization**: Controls access to ActivityGroups table operations and bulk group management
 * - **Query Scoping**: Applies authorization filters to activity group queries based on user permissions
 * - **Administrative Operations**: Governs bulk operations, organizational management, and administrative data access
 * - **RBAC Integration**: Seamless integration with KMP's role-based access control system
 * - **Organizational Structure**: Ensures proper authorization for activity categorization data operations
 *
 * ## Authorization Architecture
 *
 * The policy leverages the BasePolicy framework to provide:
 * - **Permission-Based Access**: Uses KMP's permission system for table operation authorization
 * - **Warrant Integration**: Temporal validation through warrant requirements for administrative access
 * - **Branch Scoping**: Organizational hierarchy support for activity group data access control
 * - **Policy Framework**: Dynamic policy evaluation through permission policy associations
 * - **Security Cache**: Performance-optimized authorization checking with caching support
 *
 * ## Table Operations Governed
 *
 * ### Query Authorization
 * - **find() Operations**: Authorization filters applied to activity group queries
 * - **Search Operations**: Permission-based filtering for group discovery and management
 * - **Administrative Queries**: Access control for group management and organizational operations
 * - **Bulk Operations**: Authorization for mass activity group operations and reorganization
 *
 * ### Organizational Management
 * - **Group Structure Queries**: Permission validation for organizational structure analysis
 * - **Category Management**: Authorization for activity categorization and group assignment operations
 * - **Navigation Queries**: Access control for activity group navigation and menu generation
 * - **Reporting Operations**: Administrative access to group analytics and organizational reporting
 *
 * ## Security Implementation
 *
 * ### Permission Requirements
 * Table operations typically require permissions such as:
 * - **"Activities.indexGroups"**: General activity group listing and discovery access
 * - **"Activities.manageGroups"**: Administrative table operations and bulk group management
 * - **"Activities.organizeActivities"**: Permission for organizational structure operations
 * - **"Activities.reportGroups"**: Access to activity group reporting and analytics queries
 * - **"Activities.configureGroups"**: Permission for group configuration and settings operations
 *
 * ### Authorization Patterns
 * ```php
 * // Table-level authorization for bulk group operations
 * $this->Authorization->authorize($activityGroupsTable, 'bulkEdit');
 * 
 * // Query scoping with authorization filters
 * $authorizedQuery = $this->Authorization->applyScope($groupsQuery);
 * 
 * // Administrative operation authorization
 * if ($this->Authorization->can($user, 'reorganize', $activityGroupsTable)) {
 *     // Proceed with organizational restructuring
 * }
 * ```
 *
 * ## Integration Points
 *
 * - **BasePolicy**: Inherits core RBAC functionality and permission validation
 * - **PermissionsLoader**: Core permission validation engine for warrant checking
 * - **ActivityGroupsController**: Authorization enforcement for group management interfaces
 * - **Activities Management**: Integration with activity assignment and organizational operations
 * - **Navigation System**: Table-level authorization for activity group navigation generation
 *
 * ## Organizational Structure Management
 *
 * The policy supports comprehensive organizational management through:
 * - **Category Authorization**: Permission-based access to activity categorization operations
 * - **Structure Validation**: Authorization for organizational hierarchy modifications
 * - **Navigation Control**: Access control for activity group navigation and menu structure
 * - **Administrative Oversight**: Higher-level permissions for cross-organizational group management
 *
 * ## Performance Considerations
 *
 * - **Query Optimization**: Authorization filters applied at database level for efficiency
 * - **Permission Caching**: Leverages security cache for repeated authorization checks
 * - **Selective Loading**: Optimized queries based on user permission scope and organizational access
 * - **Index Support**: Authorization filters designed to work with database indexes
 * - **Batch Processing**: Optimized permission checking for bulk organizational operations
 *
 * ## Usage Examples
 *
 * ### Controller Integration
 * ```php
 * // In ActivityGroupsController
 * public function index()
 * {
 *     // Table-level authorization automatically applies scope
 *     $groups = $this->Authorization->applyScope($this->ActivityGroups->find());
 *     $this->set(compact('groups'));
 * }
 * ```
 *
 * ### Service Layer Integration
 * ```php
 * // In organizational management services
 * public function reorganizeActivityStructure($newStructure)
 * {
 *     $groupsTable = $this->getTableLocator()->get('Activities.ActivityGroups');
 *     $this->Authorization->authorize($groupsTable, 'reorganize');
 *     
 *     return $this->processStructuralReorganization($groupsTable, $newStructure);
 * }
 * ```
 *
 * ### Administrative Operations
 * ```php
 * // Bulk group management operations
 * public function bulkUpdateActivityGroups($updateData)
 * {
 *     if ($this->Authorization->can($user, 'bulkEdit', $groupsTable)) {
 *         return $this->ActivityGroups->updateMany($updateData);
 *     }
 *     
 *     throw new ForbiddenException('Insufficient permissions for bulk group operations');
 * }
 * ```
 *
 * ### Organizational Analytics
 * ```php
 * // Group analytics and reporting
 * public function generateGroupAnalytics()
 * {
 *     $groupsTable = $this->getTableLocator()->get('Activities.ActivityGroups');
 *     $this->Authorization->authorize($groupsTable, 'analytics');
 *     
 *     return $this->buildGroupAnalyticsReport($groupsTable);
 * }
 * ```
 *
 * ## Business Logic Integration
 *
 * ### Organizational Constraints
 * The policy considers organizational business rules:
 * - **Activity Dependencies**: Groups with assigned activities require special management permissions
 * - **Navigation Integration**: Groups used in navigation require additional authorization for modifications
 * - **System Groups**: Built-in organizational groups may have enhanced protection requirements
 * - **Audit Requirements**: Administrative operations maintain comprehensive audit trails
 *
 * ### Workflow Integration
 * ```php
 * // Comprehensive organizational workflow
 * public function processOrganizationalChange($changeRequest)
 * {
 *     $groupsTable = $this->getTableLocator()->get('Activities.ActivityGroups');
 *     
 *     // Table-level authorization for organizational changes
 *     $this->Authorization->authorize($groupsTable, 'structuralModification');
 *     
 *     // Validate impact on dependent systems
 *     if ($this->hasNavigationImpact($changeRequest)) {
 *         $this->Authorization->authorize($groupsTable, 'navigationStructure');
 *     }
 *     
 *     return $this->executeOrganizationalChange($groupsTable, $changeRequest);
 * }
 * ```
 *
 * ## Error Handling
 *
 * The policy integrates with CakePHP's authorization framework to provide:
 * - **ForbiddenException**: Clear error messages for unauthorized table operations
 * - **Audit Logging**: Unauthorized access attempts logged for security monitoring
 * - **Graceful Degradation**: Appropriate fallback behavior for limited organizational permissions
 * - **User Feedback**: Clear indication of permission requirements for group management operations
 *
 * ## Best Practices
 *
 * - Always authorize table operations before performing bulk activity group management
 * - Use scoped queries to automatically apply authorization filters for organizational data
 * - Implement proper error handling for unauthorized organizational structure access
 * - Cache permission results for performance in administrative interfaces
 * - Regularly audit table-level permissions and organizational access patterns
 * - Consider organizational impact when authorizing structural modifications
 *
 * @package Activities\Policy
 * @see \App\Policy\BasePolicy For inherited RBAC functionality and permission validation
 * @see \Activities\Model\Table\ActivityGroupsTable For activity groups data layer operations
 * @see \Activities\Controller\ActivityGroupsController For group management interface authorization
 * @see \App\KMP\PermissionsLoader For core permission validation and warrant checking
 */
class ActivityGroupsTablePolicy extends BasePolicy
{
    /**
     * Check if user can access gridData scope (Dataverse grid data endpoint)
     * Uses the same authorization scope as the standard index action
     *
     * @param \App\KMP\KmpIdentityInterface $user User
     * @param mixed $query Query
     * @return mixed
     */
    public function scopeGridData(KmpIdentityInterface $user, mixed $query): mixed
    {
        return $this->scopeIndex($user, $query);
    }
}
