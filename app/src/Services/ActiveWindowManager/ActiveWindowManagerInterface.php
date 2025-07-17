<?php

declare(strict_types=1);

namespace App\Services\ActiveWindowManager;

use App\Services\ServiceResult;
use Cake\I18n\DateTime;

/**
 * Active Window Manager Interface
 * 
 * Defines the contract for managing date-bounded entities in the KMP system. Active windows
 * represent time periods during which entities (member roles, activities, authorizations)
 * are considered "active" and effective. This interface standardizes the lifecycle management
 * of any entity that has start dates, end dates, and status tracking.
 * 
 * ## Active Window Concept
 * 
 * An "active window" is a time-bounded period where an entity is considered effective:
 * - **Start Date**: When the entity becomes active/effective
 * - **End Date**: When the entity expires or is terminated
 * - **Status**: Current state (active, expired, replaced, deactivated, etc.)
 * - **Lifecycle Management**: Automated handling of overlapping periods and transitions
 * 
 * ## Entities Using Active Windows
 * 
 * - **Member Roles**: Time-bounded role assignments (Officer terms, etc.)
 * - **Activities**: Events with defined start/end periods
 * - **Authorizations**: Temporary permissions or access grants
 * - **Memberships**: Organizational membership periods
 * - **Warrants**: Official position appointments
 * 
 * ## Business Rules
 * 
 * 1. **Exclusive Periods**: Only one active window per entity type/member combination
 * 2. **Automatic Replacement**: Starting new windows closes overlapping existing ones
 * 3. **Status Tracking**: Clear audit trail of why windows were closed
 * 4. **Role Granting**: Can automatically assign roles when windows start
 * 5. **Transactional**: All operations must be wrapped in database transactions
 * 
 * ## Status Values
 * 
 * Defined in `ActiveWindowBaseEntity`:
 * - `CURRENT_STATUS`: Currently active and effective
 * - `EXPIRED_STATUS`: Naturally expired at end date
 * - `REPLACED_STATUS`: Closed due to new window starting
 * - `DEACTIVATED_STATUS`: Manually terminated before natural expiry
 * 
 * ## Integration Points
 * 
 * - **WarrantManager**: Uses ActiveWindowManager to handle warrant-dependent entities
 * - **BaseEntity**: Entities extend ActiveWindowBaseEntity for window functionality
 * - **Authorization System**: Checks active windows for permission validation
 * - **Role Management**: Automatically grants/revokes roles based on windows
 * 
 * @see \App\Services\ActiveWindowManager\DefaultActiveWindowManager Default implementation
 * @see \App\Model\Entity\ActiveWindowBaseEntity Base class for window-enabled entities
 * @see \App\Services\WarrantManager\DefaultWarrantManager Example consumer of this service
 */
interface ActiveWindowManagerInterface
{
    /**
     * Start an active window for an entity
     * 
     * Creates or activates a time-bounded entity with the specified parameters. This method
     * handles the complex logic of starting new active windows while properly managing
     * any existing overlapping windows. Critical for maintaining data integrity in
     * time-sensitive organizational structures.
     * 
     * ## Key Behaviors
     * 
     * 1. **Existing Window Management**: If closeExisting=true, finds and replaces overlapping windows
     * 2. **Entity Activation**: Sets start/end dates and activates the entity
     * 3. **Role Assignment**: Optionally grants roles when the window becomes active  
     * 4. **Type-Based Logic**: Uses entity's typeIdField for entity-specific replacement rules
     * 5. **Transaction Requirement**: Caller MUST wrap in database transaction
     * 
     * ## Business Logic Examples
     * 
     * - **Member Roles**: Starting "Branch Seneschal" role closes any existing "Branch Officer" roles
     * - **Activities**: Starting new event period closes overlapping events of same type
     * - **Authorizations**: New authorization replaces previous one for same scope
     * 
     * @param string $entityType Table name of the entity type (e.g., 'MemberRoles', 'Activities')
     * @param int $entityId Primary key ID of the specific entity instance
     * @param int $memberId ID of the member performing this action (for audit trail)
     * @param DateTime $startOn When the active window should begin
     * @param DateTime|null $expiresOn When the window should end (null = no expiration)
     * @param int|null $termYears Alternative to expiresOn - calculate end date from term length
     * @param int|null $grantRoleId Optional role to grant to entity's member when window starts
     * @param bool $closeExisting Whether to automatically close overlapping existing windows
     * @param int|null $branchId Branch context for role assignments (required for member roles)
     * 
     * @return ServiceResult Success if window started, failure with specific error details
     * 
     * @example
     * ```php
     * // Start a member role with automatic role grant
     * $connection->begin();
     * $result = $activeWindowManager->start(
     *     'MemberRoles',
     *     $roleId,
     *     $approverId,
     *     new DateTime('2025-01-01'),
     *     new DateTime('2025-12-31'),
     *     null,
     *     $seneschalRoleId,
     *     true,
     *     $branchId
     * );
     * if ($result->success) {
     *     $connection->commit();
     * } else {
     *     $connection->rollback();
     * }
     * ```
     * 
     * @throws \Exception If entity type doesn't support active windows
     * @throws \Cake\Datasource\Exception\RecordNotFoundException If entity ID not found
     */
    public function start(
        string $entityType,
        int $entityId,
        int $memberId,
        DateTime $startOn,
        ?DateTime $expiresOn = null,
        ?int $termYears = null,
        ?int $grantRoleId = null,
        bool $closeExisting = true,
        ?int $branchId = null,
    ): ServiceResult;

    /**
     * Stop an active window for an entity
     * 
     * Terminates an active window by setting its expiration date, status, and reason.
     * This method handles the clean shutdown of time-bounded entities including
     * any associated role grants or dependent relationships.
     * 
     * ## Key Behaviors
     * 
     * 1. **Window Termination**: Sets expiration date and final status
     * 2. **Role Cleanup**: Automatically revokes any roles granted by this window
     * 3. **Audit Trail**: Records who stopped the window and why
     * 4. **Status Setting**: Updates entity status to reflect termination reason
     * 5. **Transaction Requirement**: Caller MUST wrap in database transaction
     * 
     * ## Common Use Cases
     * 
     * - **Role Expiration**: Officer steps down before term end
     * - **Activity Cancellation**: Event cancelled or postponed
     * - **Authorization Revocation**: Access permissions withdrawn
     * - **Warrant Decline**: Position authorization rejected
     * 
     * @param string $entityType Table name of the entity type
     * @param int $entityId Primary key ID of the specific entity instance
     * @param int $memberId ID of the member stopping this window (for audit trail)
     * @param string $status Final status code (DEACTIVATED_STATUS, EXPIRED_STATUS, etc.)
     * @param string $reason Human-readable explanation for stopping the window
     * @param DateTime $expiresOn When the window should be considered terminated
     * 
     * @return ServiceResult Success if window stopped, failure with specific error details
     * 
     * @example
     * ```php
     * // Stop a member role due to resignation
     * $connection->begin();
     * $result = $activeWindowManager->stop(
     *     'MemberRoles',
     *     $roleId,
     *     $managerId,
     *     ActiveWindowBaseEntity::DEACTIVATED_STATUS,
     *     'Officer resigned position',
     *     DateTime::now()
     * );
     * if ($result->success) {
     *     $connection->commit();
     * } else {
     *     $connection->rollback();
     * }
     * ```
     * 
     * @throws \Exception If entity type doesn't support active windows
     * @throws \Cake\Datasource\Exception\RecordNotFoundException If entity ID not found
     */
    public function stop(
        string $entityType,
        int $entityId,
        int $memberId,
        string $status,
        string $reason,
        DateTime $expiresOn,
    ): ServiceResult;
}
