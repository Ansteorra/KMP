<?php

namespace App\Services\ActivityAuthorizations;

use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;

interface AuthorizationServiceInterface
{
    public function approve(
        ActiveWindowManagerInterface $activeWindowManager,
        int $authorizationApprovalId,
        int $approverId,
        int $nextApproverId = null
    ): bool;

    public function deny(
        int $authorizationApprovalId,
        int $approverId,
        string $denyReason,
    ): bool;

    public function request(
        int $requesterId,
        int $activityId,
        int $approverId,
        bool $isRenewal,
    ): bool;

    public function revoke(
        ActiveWindowManagerInterface $activeWindowManager,
        int $authorizationId,
        int $revokerId,
        string $revokedReason
    ): bool;
}
