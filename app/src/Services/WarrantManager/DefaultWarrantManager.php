<?php

namespace App\Services\WarrantManager;

use App\Services\WarrantManager\WarrantManagerInterface;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use App\Services\ServiceResult;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;

class DefaultWarrantManager implements WarrantManagerInterface
{
    public function __construct(ActiveWindowManagerInterface $activeWindowManager)
    {
        $this->activeWindowManager = $activeWindowManager;
    }

    public function requestWarrant($warrantRequest): ServiceResult
    {
        return new ServiceResult(true);
    }

    public function requestWarrants($warrantRequests): ServiceResult
    {
        return new ServiceResult(true);
    }

    public function approveWarrant($warrant_approval_set_id, $approver_id): ServiceResult
    {
        return new ServiceResult(true);
    }

    public function rejectWarrant($warrant_approval_set_id, $rejecter_id, $reason): ServiceResult
    {
        return new ServiceResult(true);
    }

    public function cancelWarrantForObject($entityType, $entityId, $reason, $rejecter_id, $expiresOn): ServiceResult
    {
        return new ServiceResult(true);
    }
}