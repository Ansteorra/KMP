<?php

declare(strict_types=1);

namespace Activities\Policy;

use App\Policy\BasePolicy;

/**
 * Activities Table Authorization Policy
 *
 * This policy class defines authorization rules for Activities table operations within the KMP Activities plugin.
 * It extends the BasePolicy to inherit core RBAC functionality while providing table-level authorization logic
 * for bulk operations, queries, and administrative access to the activities data layer.
 *
 * ## Purpose
 *
 * - **Table-Level Authorization**: Controls access to Activities table operations and bulk data management
 * - **Query Scoping**: Applies authorization filters to activity queries based on user permissions
 * - **Administrative Operations**: Governs bulk operations, reporting, and administrative data access
 * - **RBAC Integration**: Seamless integration with KMP's role-based access control system
 * - **Permission Validation**: Ensures proper authorization for activities data layer operations
 *
 * ## Authorization Architecture
 *
 * The policy leverages the BasePolicy framework to provide:
 * - **Permission-Based Access**: Uses KMP's permission system for table operation authorization
 * - **Warrant Integration**: Temporal validation through warrant requirements for administrative access
 * - **Branch Scoping**: Organizational hierarchy support for activity data access control
 * - **Policy Framework**: Dynamic policy evaluation through permission policy associations
 * - **Security Cache**: Performance-optimized authorization checking with caching support
 *
 * ## Table Operations Governed
 *
 * ### Query Authorization
 * - **find() Operations**: Authorization filters applied to activity queries
 * - **Search Operations**: Permission-based filtering for activity discovery
 * - **Reporting Queries**: Administrative access control for analytics and reporting
 * - **Bulk Operations**: Authorization for mass activity operations
 *
 * ### Administrative Access
 * - **Data Export**: Permission validation for activity data export operations
 * - **Import Operations**: Authorization for bulk activity data import
 * - **Audit Queries**: Administrative access to activity audit trails
 * - **Statistical Analysis**: Permission-based access to activity analytics
 *
 * ## Security Implementation
 *
 * ### Permission Requirements
 * Table operations typically require permissions such as:
 * - **"Activities.index"**: General activity listing and search access
 * - **"Activities.manage"**: Administrative table operations and bulk management
 * - **"Activities.report"**: Access to activity reporting and analytics queries
 * - **"Activities.export"**: Permission for activity data export operations
 * - **"Activities.audit"**: Access to activity audit trails and historical data
 *
 * ### Authorization Patterns
 * ```php
 * // Table-level authorization for bulk operations
 * $this->Authorization->authorize($activitiesTable, 'bulkEdit');
 * 
 * // Query scoping with authorization filters
 * $authorizedQuery = $this->Authorization->applyScope($activitiesQuery);
 * 
 * // Administrative operation authorization
 * if ($this->Authorization->can($user, 'export', $activitiesTable)) {
 *     // Proceed with data export
 * }
 * ```
 *
 * ## Integration Points
 *
 * - **BasePolicy**: Inherits core RBAC functionality and permission validation
 * - **PermissionsLoader**: Core permission validation engine for warrant checking
 * - **Activities Controllers**: Authorization enforcement for administrative interfaces
 * - **Reporting System**: Table-level authorization for activity reporting and analytics
 * - **Export Services**: Permission validation for data export and import operations
 *
 * ## Branch Scoping
 *
 * The policy supports organizational hierarchy through:
 * - **Branch-Based Filtering**: Activities filtered by user's branch scope and permissions
 * - **Hierarchical Access**: Support for "Branch and Children" permission scoping
 * - **Organizational Security**: Maintains proper data isolation between organizational units
 * - **Administrative Oversight**: Higher-level permissions for cross-branch activity access
 *
 * ## Performance Considerations
 *
 * - **Query Optimization**: Authorization filters applied at database level for efficiency
 * - **Permission Caching**: Leverages security cache for repeated authorization checks
 * - **Selective Loading**: Optimized queries based on user permission scope
 * - **Index Support**: Authorization filters designed to work with database indexes
 *
 * ## Usage Examples
 *
 * ### Controller Integration
 * ```php
 * // In ActivitiesController
 * public function index()
 * {
 *     // Table-level authorization automatically applies scope
 *     $activities = $this->Authorization->applyScope($this->Activities->find());
 *     $this->set(compact('activities'));
 * }
 * ```
 *
 * ### Service Layer Integration
 * ```php
 * // In reporting services
 * public function generateActivityReport($userId)
 * {
 *     $activitiesTable = $this->getTableLocator()->get('Activities.Activities');
 *     $this->Authorization->authorize($activitiesTable, 'report');
 *     
 *     return $this->buildAuthorizedReport($activitiesTable);
 * }
 * ```
 *
 * ### Administrative Operations
 * ```php
 * // Bulk activity operations
 * if ($this->Authorization->can($user, 'bulkDelete', $activitiesTable)) {
 *     $this->Activities->deleteMany($selectedIds);
 * }
 * ```
 *
 * ## Error Handling
 *
 * The policy integrates with CakePHP's authorization framework to provide:
 * - **ForbiddenException**: Clear error messages for unauthorized table operations
 * - **Audit Logging**: Unauthorized access attempts logged for security monitoring
 * - **Graceful Degradation**: Appropriate fallback behavior for limited permissions
 * - **User Feedback**: Clear indication of permission requirements for table operations
 *
 * ## Best Practices
 *
 * - Always authorize table operations before performing bulk activities management
 * - Use scoped queries to automatically apply authorization filters
 * - Implement proper error handling for unauthorized table access
 * - Cache permission results for performance in high-volume operations
 * - Regularly audit table-level permissions and access patterns
 *
 * @package Activities\Policy
 * @see \App\Policy\BasePolicy For inherited RBAC functionality and permission validation
 * @see \Activities\Model\Table\ActivitiesTable For activities data layer operations
 * @see \Activities\Controller\ActivitiesController For administrative interface authorization
 * @see \App\KMP\PermissionsLoader For core permission validation and warrant checking
 */
class ActivitiesTablePolicy extends BasePolicy {}
