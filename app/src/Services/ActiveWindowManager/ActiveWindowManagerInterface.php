<?php

namespace App\Services\ActiveWindowManager;

use Cake\I18n\DateTime;
use App\Services\ServiceResult;

interface ActiveWindowManagerInterface
{
    /**
     * Starts an active window for an entity - Make sure to create a transaction before calling this service
     *
     * @param string $entityType
     * @param int $entityId
     * @param int $memberId
     * @param DateTime $startOn
     * @param DateTime|null $expiresOn
     * @param int|null $termYears
     * @param int|null $grantRoleId
     * @return bool
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
     * Stops an active window for an entity - Make sure to create a transaction before calling this service
     *
     * @param string $entityType
     * @param int $entityId
     * @param int $memberId
     * @param string $status
     * @param string $reason
     * @param DateTime $expiresOn
     * @return bool
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