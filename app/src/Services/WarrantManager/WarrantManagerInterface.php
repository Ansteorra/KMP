<?php

namespace App\Services\WarrantManager;

use Cake\I18n\DateTime;
use App\Services\ServiceResult;

interface WarrantManagerInterface
{
    public function requestWarrant($warrantRequest): ServiceResult;
    public function requestWarrants($warrantRequests): ServiceResult;
    public function approveWarrant($warrant_approval_set_id, $approver_id): ServiceResult;
    public function rejectWarrant($warrant_approval_set_id, $rejecter_id, $reason): ServiceResult;
    public function cancelWarrantForObject($entityType, $entityId, $reason, $rejecter_id, $expiresOn): ServiceResult;
}