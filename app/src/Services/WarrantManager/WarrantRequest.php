<?php

declare(strict_types=1);

namespace App\Services\WarrantManager;

use Cake\I18n\DateTime;

/**
 * Warrant Request Data Structure
 * 
 * Represents a single warrant request within the KMP warrant management system. This class
 * encapsulates all the information needed to create a warrant authorization for a member
 * to hold a specific position or role within an organizational entity.
 * 
 * ## Purpose and Usage
 * 
 * WarrantRequest objects are used to:
 * - Structure warrant application data before database persistence
 * - Validate warrant parameters during the request process
 * - Group related warrant information for batch processing
 * - Maintain type safety and data integrity during warrant creation
 * 
 * ## Entity Relationship Context
 * 
 * - **Entity Types**: The type of organizational unit (Branches, Activities, Offices, etc.)
 * - **Entity IDs**: Specific instance of the organizational unit
 * - **Members**: Individual receiving the warrant authorization
 * - **Member Roles**: Optional specific role within the entity (e.g., "Herald", "Seneschal")
 * - **Requesters**: Member initiating the warrant request (typically an officer)
 * 
 * ## Date Handling
 * 
 * - **Start Date**: When the warrant becomes effective (null = immediate)
 * - **End Date**: When the warrant expires (null = use warrant period default)
 * - **Validation**: Dates are validated against warrant periods and membership expiration
 * 
 * ## Security and Validation
 * 
 * The WarrantManager validates that:
 * - Requesting member has authorization to grant warrants for the entity
 * - Target member is warrantable and has active membership
 * - Requested dates fall within valid warrant periods
 * - Member role (if specified) is appropriate for the entity type
 * 
 * @see \App\Services\WarrantManager\WarrantManagerInterface::request() Primary usage context
 * @see \App\Services\WarrantManager\DefaultWarrantManager Warrant processing implementation
 * @see \App\Model\Entity\Warrant Database entity created from this request
 * @see \App\Model\Entity\WarrantRoster Container for multiple warrant requests
 */
class WarrantRequest
{
    /**
     * Type of organizational entity being warranted for
     * 
     * Common values: 'Branches', 'Activities', 'Offices', 'Direct Grant'
     * Must match entity types defined in KMP's organizational structure.
     * 
     * @var string
     */
    public string $entityType;

    /**
     * Unique identifier of the specific entity instance
     * 
     * References the primary key of the entity table (branches.id, activities.id, etc.)
     * For 'Direct Grant' warrants, this may be 0 or a placeholder value.
     * 
     * @var int
     */
    public int $entityId;

    /**
     * ID of the member requesting this warrant
     * 
     * Must be a member with authorization to grant warrants for the specified entity.
     * This creates an audit trail and enables authorization checking.
     * 
     * @var int
     */
    public int $requester_id;

    /**
     * ID of the member who will receive the warrant
     * 
     * Target member must be warrantable (have warrantable flag set) and have
     * active membership extending through the warrant period.
     * 
     * @var int
     */
    public int $member_id;

    /**
     * Optional specific role within the entity
     * 
     * References member_roles.id for structured roles like "Herald", "Seneschal", etc.
     * Null indicates a general warrant without a specific titled role.
     * 
     * @var int|null
     */
    public ?int $member_role_id;

    /**
     * Optional custom start date for the warrant
     * 
     * If null, the warrant will start at the beginning of the applicable warrant period
     * or immediately if the period has already started. Cannot be before the current date.
     * 
     * @var DateTime|null
     */
    public ?DateTime $start_on;

    /**
     * Optional custom end date for the warrant
     * 
     * If null, the warrant will end at the conclusion of the applicable warrant period.
     * Cannot extend beyond the member's membership expiration date.
     * 
     * @var DateTime|null
     */
    public ?DateTime $expires_on;

    /**
     * Human-readable name/title for the warrant
     * 
     * Descriptive name that will appear in listings and notifications.
     * Examples: "Branch Seneschal", "Activity Deputy", "A&S Officer"
     * 
     * @var string
     */
    public string $name;

    /**
     * Create a new warrant request
     * 
     * @param string $name Human-readable warrant title
     * @param string $entity_type Type of organizational entity
     * @param int $entity_id ID of the specific entity instance  
     * @param int $requester_id ID of the member making the request
     * @param int $member_id ID of the member to receive the warrant
     * @param DateTime|null $start_on Optional custom start date
     * @param DateTime|null $expires_on Optional custom end date
     * @param int|null $member_role_id Optional specific role within entity
     * 
     * @example
     * ```php
     * // Simple branch officer warrant
     * $request = new WarrantRequest(
     *     'Branch Herald', 
     *     'Branches', 
     *     $branchId, 
     *     $requesterId, 
     *     $memberId
     * );
     * 
     * // Activity warrant with specific dates and role
     * $request = new WarrantRequest(
     *     'Event Steward',
     *     'Activities', 
     *     $activityId,
     *     $requesterId,
     *     $memberId,
     *     new DateTime('2025-02-01'),
     *     new DateTime('2025-02-28'),
     *     $stewardRoleId
     * );
     * ```
     */
    public function __construct(string $name, string $entity_type, int $entity_id, int $requester_id, int $member_id, ?DateTime $start_on = null, ?DateTime $expires_on = null, ?int $member_role_id = null)
    {
        $this->entity_type = $entity_type;
        $this->entity_id = $entity_id;
        $this->requester_id = $requester_id;
        $this->member_id = $member_id;
        $this->start_on = $start_on;
        $this->expires_on = $expires_on;
        $this->member_role_id = $member_role_id;
        $this->name = $name;
    }
}
