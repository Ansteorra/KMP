<?php

namespace App\Services\ActivityAuthorizations;

interface AuthorizationServiceInterface
{
    public function assign(
        int $officeId,
        int $memberId,
        int $branchId,
        int $approverId,
    ): bool;

    public function release(
        int $officerId,
        int $releaseApproverId,
        string $releaseReason,
    ): bool;
}
