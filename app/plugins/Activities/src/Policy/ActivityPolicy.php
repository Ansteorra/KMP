<?php

declare(strict_types=1);

namespace Activities\Policy;

use Activities\Model\Entity\Activity;
use App\KMP\KmpIdentityInterface;
use App\Policy\BasePolicy;

/**
 * Activity Authorization Policy
 *
 * This policy class defines authorization rules for Activity entity operations within the KMP Activities plugin.
 * It extends the BasePolicy to inherit core RBAC functionality while providing activity-specific authorization logic.
 *
 * **Purpose:**
 * - Controls access to Activity entity CRUD operations through permission-based authorization
 * - Integrates with KMP's RBAC system for activity management and configuration access control
 * - Provides foundation for activity administration and approval workflow management
 * - Ensures proper authorization for activity definition and organizational structure management
 *
 * **Authorization Architecture:**
 * - Inherits BasePolicy functionality for permission-based authorization validation
 * - Integrates with PermissionsLoader for warrant-based temporal validation
 * - Supports branch-scoped authorization for organizational activity management
 * - Provides entity-level authorization for specific activity operations
 *
 * **Key Features:**
 * - **Permission Integration**: Leverages KMP's permission system for activity management access
 * - **RBAC Compliance**: Full integration with role-based access control architecture
 * - **Warrant Validation**: Temporal validation through warrant requirements for administrative operations
 * - **Branch Scoping**: Organizational hierarchy support for activity management permissions
 * - **Policy Framework**: Dynamic policy evaluation through permission policy associations
 *
 * **Common Authorization Patterns:**
 * - Activity listing and search: Requires appropriate administrative permissions
 * - Activity creation: Restricted to users with activity management capabilities
 * - Activity modification: Entity-level authorization with permission validation
 * - Activity deletion: High-level administrative permissions with audit trail requirements
 * - Activity configuration: Specialized permissions for approval workflow management
 *
 * **Integration Points:**
 * - **PermissionsLoader**: Core permission validation and warrant checking
 * - **BasePolicy**: Inherited permission discovery and policy framework integration
 * - **Activities Controllers**: Authorization enforcement for administrative interfaces
 * - **KMP RBAC System**: Role and permission-based access control validation
 *
 * **Security Considerations:**
 * - All activity operations require appropriate permission validation
 * - Entity-level authorization ensures granular access control
 * - Integration with warrant system provides temporal validation for administrative roles
 * - Branch scoping maintains organizational security boundaries
 *
 * **Usage Examples:**
 * ```php
 * // Controller authorization for activity management
 * $this->Authorization->authorize($activity, 'edit');
 * 
 * // Policy-based authorization in services
 * if ($this->Authorization->can($user, 'delete', $activity)) {
 *     // Proceed with activity deletion
 * }
 * 
 * // Model-level authorization for bulk operations
 * $this->Authorization->applyScope($activitiesQuery);
 * ```
 *
 * **Permission Requirements:**
 * Activity operations typically require permissions such as:
 * - "Activities.manage": General activity administration
 * - "Activities.create": Activity creation and configuration
 * - "Activities.delete": Activity removal and audit operations
 * - "Activities.approve": Approval workflow configuration and management
 *
 * @see \App\Policy\BasePolicy Parent policy with core RBAC functionality
 * @see \Activities\Model\Entity\Activity Activity entity with business logic
 * @see \Activities\Controller\ActivitiesController Activity management interface
 * @see \App\KMP\PermissionsLoader Core permission validation engine
 */
class ActivityPolicy extends BasePolicy {}
