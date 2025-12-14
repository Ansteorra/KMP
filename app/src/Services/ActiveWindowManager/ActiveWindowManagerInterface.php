<?php

declare(strict_types=1);

namespace App\Services\ActiveWindowManager;

use App\Services\ServiceResult;
use Cake\I18n\DateTime;

/**
 * Active Window Manager Interface
 *
 * Manages date-bounded entities (member roles, activities, authorizations) in the KMP system.
 * Handles lifecycle of time periods where entities are considered "active" and effective.
 *
 * @see \App\Services\ActiveWindowManager\DefaultActiveWindowManager Default implementation
 * @see \App\Model\Entity\ActiveWindowBaseEntity Base class for window-enabled entities
 */
interface ActiveWindowManagerInterface
{
    /**
     * Start an active window for an entity.
     *
     * Creates/activates a time-bounded entity. Handles overlapping windows if closeExisting=true.
     * Caller MUST wrap in database transaction.
     *
     * @param string $entityType Table name (e.g., 'MemberRoles', 'Activities')
     * @param int $entityId Entity primary key ID
     * @param int $memberId Member performing action (audit trail)
     * @param DateTime $startOn When window should begin
     * @param DateTime|null $expiresOn When window should end (null = no expiration)
     * @param int|null $termYears Alternative: calculate end from term length
     * @param int|null $grantRoleId Optional role to grant when window starts
     * @param bool $closeExisting Auto-close overlapping existing windows
     * @param int|null $branchId Branch context for role assignments
     * @return ServiceResult Success/failure with details
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
     * Stop an active window for an entity.
     *
     * Terminates a window by setting expiration, status, and reason. Revokes any roles granted.
     * Caller MUST wrap in database transaction.
     *
     * @param string $entityType Table name of the entity type
     * @param int $entityId Entity primary key ID
     * @param int $memberId Member stopping window (audit trail)
     * @param string $status Final status code (DEACTIVATED_STATUS, etc.)
     * @param string $reason Explanation for stopping
     * @param DateTime $expiresOn When window should terminate
     * @return ServiceResult Success/failure with details
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
