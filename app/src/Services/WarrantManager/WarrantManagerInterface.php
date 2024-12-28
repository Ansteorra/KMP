<?php

namespace App\Services\WarrantManager;

use Cake\I18n\DateTime;
use App\Services\ServiceResult;
use App\Model\Entity\WarrantPeriod;

interface WarrantManagerInterface
{
    public function request($request_name, $desc, $warrantRequests): ServiceResult;
    public function approve($warrant_roster_id, $approver_id): ServiceResult;
    public function reject($warrant_roster_id, $rejecter_id, $reason): ServiceResult;
    public function cancel($warrant_id, $reason, $rejecter_id, $expiresOn): ServiceResult;
    public function cancelByEntity($entityType, $entityId, $reason, $rejecter_id, $expiresOn): ServiceResult;
    public function getWarrantPeriod(DateTime $startOn, DateTime $endOn): ?WarrantPeriod;
}
