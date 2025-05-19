<?php
declare(strict_types=1);

namespace App\Services\WarrantManager;

use App\Model\Entity\WarrantPeriod;
use App\Services\ServiceResult;
use Cake\I18n\DateTime;

interface WarrantManagerInterface
{
    public function request($request_name, $desc, $warrantRequests): ServiceResult;

    public function approve($warrant_roster_id, $approver_id): ServiceResult;

    public function decline($warrant_roster_id, $rejecter_id, $reason): ServiceResult;

    public function cancel($warrant_id, $reason, $rejecter_id, $expiresOn): ServiceResult;

    public function cancelByEntity($entityType, $entityId, $reason, $rejecter_id, $expiresOn): ServiceResult;

    public function declineSingleWarrant($warrant_id, $reason, $rejecter_id): ServiceResult;

    public function getWarrantPeriod(DateTime $startOn, ?DateTime $endOn): ?WarrantPeriod;
}
