<?php

namespace Officers\Services;

use Cake\I18n\DateTime;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;

interface OfficerManagerInterface
{
    /**
     * Assigns a member to an office - Make sure to create a transaction before calling this service
     *
     * @param ActiveWindowManagerInterface $activeWindowManager
     * @param int $officeId
     * @param int $memberId
     * @param int $branchId
     * @param DateTime $startOn
     * @param string $deputyDescription
     * @param int $approverId
     * @return bool
     */
    public function assign(
        ActiveWindowManagerInterface $activeWindowManager,
        int $officeId,
        int $memberId,
        int $branchId,
        DateTime $startOn,
        ?DateTime $endOn,
        ?string $deputyDescription,
        int $approverId,
    ): bool;


    /**
     * Releases a member from an office - Make sure to create a transaction before calling this service
     *
     * @param ActiveWindowManagerInterface $activeWindowManager
     * @param int $officerId
     * @param int $revokerId
     * @param DateTime $revokedOn
     * @param string $revokedReason
     * @return bool
     */
    public function release(
        ActiveWindowManagerInterface $activeWindowManager,
        int $officerId,
        int $revokerId,
        DateTime $revokedOn,
        ?string $revokedReason
    ): bool;
}