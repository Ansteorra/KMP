<?php

namespace Activities\Services;

use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;

interface AuthorizationManagerInterface
{
    /**
     * Approves an authorization approval - Make sure to create a transaction before calling this service
     *
     * @param ActiveWindowManagerInterface $activeWindowManager
     * @param int $authorizationApprovalId
     * @param int $approverId
     * @param int|null $nextApproverId
     * @return bool
     */
    public function approve(
        int $authorizationApprovalId,
        int $approverId,
        int $nextApproverId = null
    ): ServiceResult;

    /**
     * Denies an authorization approval - Make sure to create a transaction before calling this service
     *
     * @param int $authorizationApprovalId
     * @param int $approverId
     * @param string $denyReason
     * @return bool
     */
    public function deny(
        int $authorizationApprovalId,
        int $approverId,
        string $denyReason,
    ): ServiceResult;

    /**
     * Requests an authorization - Make sure to create a transaction before calling this service
     *
     * @param int $requesterId
     * @param int $activityId
     * @param int $approverId
     * @param bool $isRenewal
     * @return bool
     */
    public function request(
        int $requesterId,
        int $activityId,
        int $approverId,
        bool $isRenewal,
    ): ServiceResult;

    /**
     * Revokes an authorization - Make sure to create a transaction before calling this service
     *
     * @param ActiveWindowManagerInterface $activeWindowManager
     * @param int $authorizationId
     * @param int $revokerId
     * @param string $revokedReason
     * @return bool
     */
    public function revoke(
        int $authorizationId,
        int $revokerId,
        string $revokedReason
    ): ServiceResult;
}