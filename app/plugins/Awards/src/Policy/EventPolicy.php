<?php

declare(strict_types=1);

namespace Awards\Policy;

use App\Policy\BasePolicy;
use App\KMP\KmpIdentityInterface;

/**
 * Event Authorization Policy
 * 
 * Provides comprehensive authorization management for award event entities within the Awards plugin.
 * This policy manages temporal event authorization with ceremony coordination, administrative oversight,
 * and integration with the KMP RBAC system. The policy handles event management authorization,
 * temporal access control, and administrative event operations.
 * 
 * ## Authorization Architecture
 * 
 * The EventPolicy implements entity-level authorization through the BasePolicy framework:
 * - **Entity-Level Authorization**: Controls access to individual Event entities based on user permissions
 * - **Warrant Integration**: Validates user authority through warrant-based permission assignments
 * - **Temporal Validation Support**: Manages access to event lifecycle and temporal operations
 * - **Administrative Oversight**: Provides elevated access for administrative event management
 * 
 * ## Event Operations Governance
 * 
 * Authorization is enforced for all event operations:
 * - **Creation**: Controls who can create new award events and set temporal boundaries
 * - **Modification**: Manages access to event editing and temporal adjustment
 * - **Deletion**: Restricts event removal with recommendation state cleanup
 * - **Ceremony Management**: Controls event status transitions and ceremony coordination
 * 
 * ## Custom Authorization Methods
 * 
 * ### canAllEvents()
 * Authorizes access to comprehensive event listing functionality. This method validates
 * user permissions for viewing all events across temporal boundaries and organizational
 * scopes, supporting administrative event oversight and ceremony coordination.
 * 
 * ```php
 * // Usage in EventsController
 * public function allEvents()
 * {
 *     $this->Authorization->authorize($this->Events, 'allEvents');
 *     // Comprehensive event listing logic...
 * }
 * ```
 * 
 * ## Permission Integration
 * 
 * The policy integrates with the KMP permission system:
 * - Inherits standard CRUD operations from BasePolicy (canAdd, canEdit, canDelete, canView, canIndex)
 * - Uses permission-based authorization through _hasPolicy() method
 * - Supports branch-scoped access through organizational hierarchy
 * - Validates warrant-based authority for event management operations
 * 
 * ## Usage Examples
 * 
 * ### Controller Integration
 * ```php
 * // In EventsController
 * public function edit($id = null)
 * {
 *     $event = $this->Events->get($id);
 *     $this->Authorization->authorize($event, 'edit');
 *     // Event editing with temporal validation...
 * }
 * ```
 * 
 * ### Service Layer Authorization
 * ```php
 * // In event management services
 * if ($this->Authorization->can($user, 'add', $this->Events)) {
 *     // Create new event with ceremony coordination...
 * }
 * ```
 * 
 * ### Administrative Event Operations
 * ```php
 * // In administrative interfaces
 * public function delete($id = null)
 * {
 *     $event = $this->Events->get($id);
 *     $this->Authorization->authorize($event, 'delete');
 *     // Event deletion with recommendation cleanup...
 * }
 * ```
 * 
 * ## Business Logic Considerations
 * 
 * - **Temporal Constraints**: Ensures event operations respect temporal boundaries and deadlines
 * - **Ceremony Workflow**: Supports ceremony coordination and event status management
 * - **Administrative Coordination**: Validates administrative event management and oversight
 * - **Integration Requirements**: Coordinates with recommendation system and award workflows
 * 
 * @see \App\Policy\BasePolicy Base authorization functionality
 * @see \Awards\Model\Entity\Event Event entity with temporal management
 * @see \Awards\Controller\EventsController Event management controller
 * @see \Awards\Model\Table\EventsTable Event data management
 */
class EventPolicy extends BasePolicy
{
    /**
     * Authorize access to comprehensive event listing
     * 
     * Validates user permissions for viewing all events across temporal boundaries
     * and organizational scopes. This method supports administrative event oversight
     * and ceremony coordination by providing access to comprehensive event data.
     * 
     * The method uses the standard permission validation framework to check
     * user authority for viewing all events, including past, current, and future
     * events across organizational boundaries.
     * 
     * @param \App\KMP\KmpIdentityInterface $user The user requesting access
     * @param mixed $entity The Events table or related entity
     * @param mixed ...$args Additional arguments for authorization context
     * @return bool True if user can access comprehensive event listing
     */
    public function canAllEvents(KmpIdentityInterface $user, $entity, ...$args)
    {
        $method = __FUNCTION__;
        return $this->_hasPolicy($user, $method, $entity);
    }
}
