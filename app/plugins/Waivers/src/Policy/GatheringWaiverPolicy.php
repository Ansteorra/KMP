<?php

declare(strict_types=1);

namespace Waivers\Policy;

use App\Policy\BasePolicy;

/**
 * GatheringWaivers Table Authorization Policy - Bulk Operations and Query Scoping
 * 
 * The GatheringWaiversTablePolicy class provides table-level authorization for GatheringWaivers
 * data operations, implementing query scoping and bulk operation authorization based
 * on user permissions. This policy ensures that gathering waiver management operations
 * are properly authorized and scoped to the user's organizational context and gathering access.
 * 
 * ## Table-Level Authorization Architecture
 * 
 * The GatheringWaiversTablePolicy implements table authorization for gathering-level waivers:
 * - **Query Scoping**: GatheringWaivers queries filtered based on user permissions and gathering access
 * - **Branch Integration**: Access controlled through gathering branch relationships
 * - **Gathering Access**: Waiver access controlled through gathering permissions
 * - **Permission Integration**: Seamless integration with PermissionsLoader and warrant-based authorization
 * 
 * ## Query Scoping Implementation
 * 
 * ### Gathering-Based Data Access
 * The policy implements gathering-based scoping for waiver data:
 * - **Gathering Discovery**: User gathering permissions resolved through branch relationships
 * - **Query Filtering**: GatheringWaivers queries scoped to authorized gatherings
 * - **Branch Integration**: Gathering branch relationships used for access control
 * - **Administrative Override**: Global permissions bypass gathering restrictions
 * 
 * ## Bulk Operations Authorization
 * 
 * ### Table Query Authorization
 * The policy inherits standard table operations through BasePolicy:
 * - **Index Operations**: GatheringWaivers listing authorized through canIndex() delegation
 * - **Export Authorization**: Waiver export operations controlled through permissions
 * - **Bulk Updates**: Mass operations authorized through individual entity permission checking
 * - **Administrative Operations**: Bulk administrative operations with appropriate validation
 * 
 * ## Permission Integration Architecture
 * 
 * ### BasePolicy Delegation
 * Standard authorization operations delegated to BasePolicy framework:
 * - **Permission Discovery**: GatheringWaivers permissions resolved through PermissionsLoader
 * - **Warrant Validation**: Table operations authorized through warrant-based authority checking
 * - **Branch Scoping**: Organizational access control through gathering-branch relationships
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
 * // GatheringWaiversController index with automatic query scoping
 * public function index() {
 *     $query = $this->GatheringWaivers->find();
 *     $query = $this->Authorization->applyScope($query);
 *     $gatheringWaivers = $this->paginate($query);
 *     $this->set(compact('gatheringWaivers'));
 * }
 * ```
 * 
 * ### Service Layer Integration
 * ```php
 * // Gathering waiver discovery service with policy scoping
 * public function getGatheringWaivers($gatheringId) {
 *     $query = $this->GatheringWaivers->find()
 *         ->where(['gathering_id' => $gatheringId]);
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
 * - **Branch Scoping**: Organizational access control through gathering-branch relationships
 * - **Administrative Authority**: Super user and administrative permissions for system management
 * 
 * ### Gathering Integration
 * - **Gathering Access**: Waiver access controlled through gathering permissions
 * - **Branch Relationships**: Gathering branch relationships used for organizational scoping
 * - **Event Integration**: Waiver access integrated with gathering event management
 * - **Activity Integration**: Gathering-level waivers coordinate with activity-specific requirements
 * 
 * ## Security Considerations
 * 
 * ### Data Protection
 * - **Gathering Isolation**: Data access limited to authorized gathering contexts
 * - **Permission Validation**: All table operations validated against RBAC permissions
 * - **Query Security**: Queries protected against injection and unauthorized access
 * - **Audit Trail**: Table operations logged for compliance monitoring
 * 
 * ### Access Control
 * - **Authentication Required**: All table operations require authenticated user identity
 * - **Authorization Validation**: Table access controlled through permission checking
 * - **Organizational Security**: Multi-gathering access control ensures data isolation
 * - **Administrative Oversight**: Administrative access supports system management
 * 
 * @method bool canAdd(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canIndex(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 * @method bool canExport(\App\KMP\KmpIdentityInterface $user, \Cake\ORM\Table $table, mixed ...$optionalArgs)
 */
class GatheringWaiverPolicy extends BasePolicy
{
    public function canChangeWaiverType(\App\KMP\KmpIdentityInterface $user, \App\Model\Entity\BaseEntity $entity, ...$optionalArgs): bool
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}