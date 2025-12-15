<?php

declare(strict_types=1);

namespace Activities\Services;

use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;

/**
 * Contract for managing member activity authorizations.
 *
 * Defines the service layer for authorization workflows including requests,
 * approvals, denials, revocations, and retractions. All methods return
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
     * Process approval of an authorization request.
     *
     * Validates approver authority, records approval, and activates authorization
     * when all required approvals are collected. Requires external transaction.
     *
     * @param int $authorizationApprovalId ID of approval record to process
     * @param int $approverId ID of member providing approval
     * @param int|null $nextApproverId Optional next approver for multi-level workflow
     * @return ServiceResult Success with authorization data or error details
     */
    public function approve(
        int $authorizationApprovalId,
        int $approverId,
        ?int $nextApproverId = null
    ): ServiceResult;

    /**
     * Process denial of an authorization request.
     *
     * Records denial reasoning, updates status, and maintains audit trail.
     * Requires external transaction.
     *
     * @param int $authorizationApprovalId ID of approval record to deny
     * @param int $approverId ID of member providing denial
     * @param string $denyReason Detailed reason for denial
     * @return ServiceResult Success with denial confirmation or error details
     */
    public function deny(
        int $authorizationApprovalId,
        int $approverId,
        string $denyReason,
    ): ServiceResult;

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
