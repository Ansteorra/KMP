<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\Policy\BasePolicy;

/**
 * GatheringActivityWaivers Table Authorization Policy - Bulk Operations and Query Scoping
 * 
 * The GatheringActivityWaiversTablePolicy class provides table-level authorization for
 * GatheringActivityWaivers data operations, implementing query scoping and bulk operation
 * authorization based on user permissions. This policy ensures that activity-specific waiver
 * management operations are properly authorized and scoped to the user's organizational
 * context and gathering activity access.
 * 
 * ## Table-Level Authorization Architecture
 * 
 * The GatheringActivityWaiversTablePolicy implements table authorization for activity waivers:
 * - **Query Scoping**: Activity waiver queries filtered based on user permissions and activity access
 * - **Branch Integration**: Access controlled through gathering and activity branch relationships
 * - **Activity Access**: Waiver access controlled through gathering activity permissions
 * - **Permission Integration**: Seamless integration with PermissionsLoader and warrant-based authorization
 * 
 * ## Query Scoping Implementation
 * 
 * ### Activity-Based Data Access
 * The policy implements activity-based scoping for waiver data:
 * - **Activity Discovery**: User activity permissions resolved through gathering relationships
 * - **Query Filtering**: GatheringActivityWaivers queries scoped to authorized activities
 * - **Gathering Integration**: Activity-gathering relationships used for access control
 * - **Administrative Override**: Global permissions bypass activity restrictions
 * 
 * ## Bulk Operations Authorization
 * 
 * ### Table Query Authorization
 * The policy inherits standard table operations through BasePolicy:
 * - **Index Operations**: Activity waiver listing authorized through canIndex() delegation
 * - **Export Authorization**: Waiver export operations controlled through permissions
 * - **Bulk Updates**: Mass operations authorized through individual entity permission checking
 * - **Administrative Operations**: Bulk administrative operations with appropriate validation
 * 
 * ## Permission Integration Architecture
 * 
 * ### BasePolicy Delegation
 * Standard authorization operations delegated to BasePolicy framework:
 * - **Permission Discovery**: Activity waiver permissions resolved through PermissionsLoader
 * - **Warrant Validation**: Table operations authorized through warrant-based authority checking
 * - **Branch Scoping**: Organizational access control through activity-gathering relationships
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
 * // GatheringActivityWaiversController index with automatic query scoping
 * public function index() {
 *     $query = $this->GatheringActivityWaivers->find();
 *     $query = $this->Authorization->applyScope($query);
 *     $activityWaivers = $this->paginate($query);
 *     $this->set(compact('activityWaivers'));
 * }
 * ```
 * 
 * ### Service Layer Integration
 * ```php
 * // Activity waiver discovery service with policy scoping
 * public function getActivityWaivers($activityId) {
 *     $query = $this->GatheringActivityWaivers->find()
 *         ->where(['gathering_activity_id' => $activityId]);
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
 * - **Branch Scoping**: Organizational access control through activity-gathering relationships
 * - **Administrative Authority**: Super user and administrative permissions for system management
 * 
 * ### Activity Integration
 * - **Activity Access**: Waiver access controlled through gathering activity permissions
 * - **Gathering Relationships**: Activity-gathering relationships used for organizational scoping
 * - **Event Integration**: Activity waiver access integrated with gathering event management
 * - **Safety Requirements**: Activity-specific waiver requirements for risk management
 * 
 * ## Security Considerations
 * 
 * ### Data Protection
 * - **Activity Isolation**: Data access limited to authorized activity contexts
 * - **Permission Validation**: All table operations validated against RBAC permissions
 * - **Query Security**: Queries protected against injection and unauthorized access
 * - **Audit Trail**: Table operations logged for compliance monitoring
 * 
 * ### Access Control
 * - **Authentication Required**: All table operations require authenticated user identity
 * - **Authorization Validation**: Table access controlled through permission checking
 * - **Organizational Security**: Multi-activity access control ensures data isolation
 * - **Administrative Oversight**: Administrative access supports system management
 * 
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */
class GatheringActivityWaiversTablePolicy extends BasePolicy {}
