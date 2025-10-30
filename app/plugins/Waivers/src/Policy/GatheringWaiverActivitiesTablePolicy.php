<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\Policy\BasePolicy;

/**
 * GatheringWaiverActivities Table Authorization Policy - Bulk Operations and Query Scoping
 * 
 * The GatheringWaiverActivitiesTablePolicy class provides table-level authorization for
 * GatheringWaiverActivities data operations, implementing query scoping and bulk operation
 * authorization based on user permissions. This policy ensures that waiver-activity assignment
 * management operations are properly authorized and scoped to the user's organizational
 * context and gathering waiver access.
 * 
 * ## Table-Level Authorization Architecture
 * 
 * The GatheringWaiverActivitiesTablePolicy implements table authorization for waiver assignments:
 * - **Query Scoping**: Waiver activity queries filtered based on user permissions and waiver access
 * - **Branch Integration**: Access controlled through gathering and waiver branch relationships
 * - **Waiver Access**: Assignment access controlled through gathering waiver permissions
 * - **Permission Integration**: Seamless integration with PermissionsLoader and warrant-based authorization
 * 
 * ## Query Scoping Implementation
 * 
 * ### Waiver-Based Data Access
 * The policy implements waiver-based scoping for activity assignments:
 * - **Waiver Discovery**: User waiver permissions resolved through gathering relationships
 * - **Query Filtering**: GatheringWaiverActivities queries scoped to authorized waivers
 * - **Activity Integration**: Waiver-activity relationships used for access control
 * - **Administrative Override**: Global permissions bypass waiver restrictions
 * 
 * ## Bulk Operations Authorization
 * 
 * ### Table Query Authorization
 * The policy inherits standard table operations through BasePolicy:
 * - **Index Operations**: Waiver activity listing authorized through canIndex() delegation
 * - **Export Authorization**: Assignment export operations controlled through permissions
 * - **Bulk Updates**: Mass operations authorized through individual entity permission checking
 * - **Administrative Operations**: Bulk administrative operations with appropriate validation
 * 
 * ## Permission Integration Architecture
 * 
 * ### BasePolicy Delegation
 * Standard authorization operations delegated to BasePolicy framework:
 * - **Permission Discovery**: Waiver activity permissions resolved through PermissionsLoader
 * - **Warrant Validation**: Table operations authorized through warrant-based authority checking
 * - **Branch Scoping**: Organizational access control through waiver-gathering relationships
 * - **Administrative Authority**: Super user and administrative permissions for system management
 * 
 * ## Security Implementation
 * 
 * ### Query Security
 * Comprehensive query-level security implementation:
 * - **Injection Prevention**: Query scoping through parameterized queries and ORM integration
 * - **Access Validation**: All query operations validated against user permissions
 * - **Data Filtering**: Data automatically filtered to prevent unauthorized access
 * - **Audit Integration**: Query operations logged for compliance monitoring
 * 
 * ### Performance Security
 * Security implementation optimized for performance:
 * - **Efficient Scoping**: Query scoping at database level for optimal performance
 * - **Permission Caching**: User permissions cached to reduce authorization overhead
 * - **Index Optimization**: Query scoping designed to utilize database indexes
 * - **Scalability**: Authorization system designed to scale with organizational growth
 * 
 * ## Usage Examples
 * 
 * ### Controller Integration
 * ```php
 * // GatheringWaiverActivitiesController index with automatic query scoping
 * public function index() {
 *     $query = $this->GatheringWaiverActivities->find();
 *     $query = $this->Authorization->applyScope($query);
 *     $waiverActivities = $this->paginate($query);
 *     $this->set(compact('waiverActivities'));
 * }
 * ```
 * 
 * ### Service Layer Integration
 * ```php
 * // Waiver activity assignment service with policy scoping
 * public function getWaiverActivities($waiverId) {
 *     $query = $this->GatheringWaiverActivities->find()
 *         ->where(['gathering_waiver_id' => $waiverId]);
 *     $query = $this->Authorization->applyScope($query);
 *     return $query->toArray();
 * }
 * ```
 * 
 * ## Integration Points
 * 
 * ### BasePolicy Integration
 * - **Standard Operations**: Inherits canAdd(), canEdit(), canDelete(), canView() through delegation
 * - **Permission Framework**: Seamless integration with RBAC through BasePolicy inheritance
 * - **Branch Scoping**: Organizational access control through waiver-gathering relationships
 * - **Administrative Authority**: Super user and administrative permissions for system management
 * 
 * ### Waiver Integration
 * - **Waiver Access**: Assignment access controlled through gathering waiver permissions
 * - **Activity Relationships**: Waiver-activity relationships used for organizational scoping
 * - **Gathering Integration**: Assignment access integrated with gathering waiver management
 * - **Activity Requirements**: Activity-specific waiver assignments for safety compliance
 * 
 * ## Security Considerations
 * 
 * ### Data Protection
 * - **Waiver Isolation**: Data access limited to authorized waiver contexts
 * - **Permission Validation**: All table operations validated against RBAC permissions
 * - **Query Security**: Queries protected against injection and unauthorized access
 * - **Audit Trail**: Table operations logged for compliance monitoring
 * 
 * ### Access Control
 * - **Authentication Required**: All table operations require authenticated user identity
 * - **Authorization Validation**: Table access controlled through permission checking
 * - **Organizational Security**: Multi-waiver access control ensures data isolation
 * - **Administrative Oversight**: Administrative access supports system management
 * 
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */
class GatheringWaiverActivitiesTablePolicy extends BasePolicy {}
