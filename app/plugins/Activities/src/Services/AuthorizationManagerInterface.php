<?php

declare(strict_types=1);

namespace Activities\Services;

use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;

/**
 * Contract for managing member activity authorizations.
 *
 * Defines the service layer for authorization workflows including requests,
 * activation, revocations, and retractions. Approval and denial are now
 * handled entirely by the unified workflow engine. All methods return
 * ServiceResult objects and require external transaction management.
 *
 * @package Activities\Services
 * @see \Activities\Services\DefaultAuthorizationManager Default implementation
 * @see /docs/5.6.1-activities-plugin-architecture.md For architecture details
 * @see /docs/5.6-activities-plugin.md For workflow documentation
 */
interface AuthorizationManagerInterface
{
    /**
     * Initiate a new authorization request.
     *
     * Validates member eligibility, assigns approver, creates authorization
     * in pending status. Requires external transaction.
     *
     * @param int $requesterId ID of member requesting authorization
     * @param int $activityId ID of activity for authorization
     * @param int $approverId ID of assigned approver
     * @param bool $isRenewal Whether this is a renewal request
     * @return ServiceResult Success with request data or error details
     */
    public function request(
        int $requesterId,
        int $activityId,
        int $approverId,
        bool $isRenewal,
    ): ServiceResult;

    /**
     * Revoke an active authorization.
     *
     * Administratively revokes authorization with reasoning. Handles role
     * removal and cascade effects. Requires external transaction.
     *
     * @param int $authorizationId ID of authorization to revoke
     * @param int $revokerId ID of administrator performing revocation
     * @param string $revokedReason Detailed reason for revocation
     * @return ServiceResult Success with revocation confirmation or error details
     */
    public function revoke(
        int $authorizationId,
        int $revokerId,
        string $revokedReason
    ): ServiceResult;

    /**
     * Activate a fully-approved authorization.
     *
     * Sets status to APPROVED, starts ActiveWindow for temporal validation,
     * and assigns the activity's granted role. Does not send notifications.
     *
     * @param int $authorizationId ID of authorization to activate
     * @param int $approverId ID of the final approver triggering activation
     * @return ServiceResult Success with activation data or error details
     */
    public function activate(
        int $authorizationId,
        int $approverId,
    ): ServiceResult;

    /**
     * Retract (cancel) a pending authorization request.
     *
     * Allows requester to cancel their own pending request before
     * approval/denial. Requires external transaction.
     *
     * @param int $authorizationId ID of authorization to retract
     * @param int $requesterId ID of member retracting (must be owner)
     * @return ServiceResult Success with retraction confirmation or error details
     */
    public function retract(
        int $authorizationId,
        int $requesterId
    ): ServiceResult;
}
