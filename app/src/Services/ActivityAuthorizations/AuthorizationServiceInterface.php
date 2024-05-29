<?php

namespace App\Services\ActivityAuthorizations;

interface AuthorizationServiceInterface
{
    public function approve(
        int $authorizationApprovalId,
        int $approverId,
        int $nextApproverId = null,
    ): bool;

    public function deny(
        int $authorizationApprovalId,
        int $approverId,
        string $denyReason,
    ): bool;

    public function request(
        int $requesterId,
        int $authorizationTypeId,
        int $approverId,
        bool $isRenewal,
    ): bool;

    public function revoke(
        int $revokerId,
        int $authorizationId,
        string $revokedReason,
    ): bool;
}
