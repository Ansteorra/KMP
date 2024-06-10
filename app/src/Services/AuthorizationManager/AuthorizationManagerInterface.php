<?php

namespace App\Services\AuthorizationManager;

use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;

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
        ActiveWindowManagerInterface $activeWindowManager,
        int $authorizationApprovalId,
        int $approverId,
        int $nextApproverId = null
    ): bool;

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
    ): bool;

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
    ): bool;

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
        ActiveWindowManagerInterface $activeWindowManager,
        int $authorizationId,
        int $revokerId,
        string $revokedReason
    ): bool;
}