<?php

namespace App\Services\WarrantManager;

use App\Model\Entity\Warrant;
use App\Services\WarrantManager\WarrantManagerInterface;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use App\Services\ServiceResult;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Model\Entity\WarrantPeriod;
use App\Model\Entity\WarrantRoster;

use App\KMP\StaticHelpers;


class DefaultWarrantManager implements WarrantManagerInterface
{
    public function __construct(ActiveWindowManagerInterface $activeWindowManager)
    {
        $this->activeWindowManager = $activeWindowManager;
        //Datetime tomorrow
        $yesterday = new DateTime();
        $yesterday->modify("-1 day");
        $warrantCheck = StaticHelpers::getAppSetting('KMP.LastWarrantCheck');
        if ($warrantCheck == "" || $warrantCheck < $yesterday) {
            $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
            $warrants = $warrantTable->find()
                ->where(['status' => Warrant::CURRENT_STATUS, 'expires_on <' => DateTime::now()])
                ->all();
            foreach ($warrants as $warrant) {
                $warrant->status = Warrant::EXPIRED_STATUS;
                $warrantTable->save($warrant);
            }
            StaticHelpers::setAppSetting('KMP.LastWarrantCheck', DateTime::now());
        }
    }

    public function request($request_name, $desc, $warrantRequests): ServiceResult
    {
        //Create a warrant approval set
        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $warrantRoster = $warrantRosterTable->newEmptyEntity();
        $warrantRoster->created_on = new DateTime();
        $warrantRoster->status = WarrantRoster::STATUS_PENDING;
        $warrantRoster->name = $request_name;
        $warrantRoster->description = $desc;
        $warrantRoster->approvals_required = 1;
        if (!$warrantRosterTable->save($warrantRoster)) {
            return new ServiceResult(false, "Failed to create warrant approval set");
        }
        $warrantRequestTable = TableRegistry::getTableLocator()->get('Warrants');
        foreach ($warrantRequests as $warrantRequest) {
            $warrantRequestEntity = $warrantRequestTable->newEmptyEntity();
            $warrantRequestEntity->entity_type = $warrantRequest->entity_type;
            $warrantRequestEntity->entity_id =  $warrantRequest->entity_id;
            $warrantRequestEntity->requester_id = $warrantRequest->requester_id;
            $warrantRequestEntity->member_id = $warrantRequest->member_id;
            //get warrant period
            $warrantPeriod = $this->getWarrantPeriod($warrantRequest->start_on, $warrantRequest->expires_on);
            if ($warrantPeriod == null) {
                return new ServiceResult(false, "Invalid warrant period");
            }
            $member = TableRegistry::getTableLocator()->get('Members')->get($warrantRequest->member_id);
            if ($member->warrantable == null) {
                return new ServiceResult(false, "$member->sca_name is not warrantable");
            }
            if ($warrantPeriod->end_on > $member->membership_expires_on) {
                return new ServiceResult(false, "Warrant period exceeds membership period for $member->sca_name");
            }
            $warrantRequestEntity->start_on = $warrantPeriod->start_date;
            $warrantRequestEntity->expires_on = $warrantPeriod->end_date;
            $warrantRequestEntity->status = Warrant::PENDING_STATUS;
            $warrantRequestEntity->warrant_roster_id = $warrantRoster->id;
            if (!$warrantRequestTable->save($warrantRequestEntity)) {
                return new ServiceResult(false, "Failed to create pending warrant for $member->sca_name");
            }
        }

        return new ServiceResult(true);
    }

    public function approve($warrant_roster_id, $approver_id): ServiceResult
    {
        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $warrantRoster = $warrantRosterTable->get($warrant_roster_id);
        if ($warrantRoster == null) {
            return new ServiceResult(false, "Warrant Roster set not found");
        }
        if ($warrantRoster->status != WarrantRoster::STATUS_PENDING) {
            return new ServiceResult(false, "Warrant Roster set is not pending");
        }
        if ($warrantRoster->hasRequiredApprovals()) {
            return new ServiceResult(false, "Warrant approval set is already approved");
        }


        //record approval
        $warrantRosterApprovalTable = TableRegistry::getTableLocator()->get('WarrantRosterApprovals');
        $warrantRosterApproval = $warrantRosterApprovalTable->newEmptyEntity();
        $warrantRosterApproval->warrant_roster_id = $warrant_roster_id;
        $warrantRosterApproval->approver_id = $approver_id;
        $warrantRosterApproval->approved_on = new DateTime();

        //start a transaction
        $warrantRosterTable->getConnection()->begin();


        if (!$warrantRosterApprovalTable->save($warrantRosterApproval)) {
            //rollback transaction
            $warrantRosterTable->getConnection()->rollback();
            return new ServiceResult(false, "Failed to record warrant approval");
        }
        $warrantRoster->approval_count++;
        if ($warrantRoster->hasRequiredApprovals()) {
            $warrantRoster->status = WarrantRoster::STATUS_APPROVED;
            //get all warrants in the set that are pending and make them active
            $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
            $warrants = $warrantTable->find()
                ->where([
                    'warrant_roster_id' => $warrant_roster_id,
                    'status' => Warrant::PENDING_STATUS
                ])
                ->all();
            foreach ($warrants as $warrant) {
                $warrant->status = Warrant::CURRENT_STATUS;
                $warrant->approved_date = new DateTime();
                if ($warrant->start_on == null || $warrant->starts_on < new DateTime()) {
                    $warrant->start_on = new DateTime();
                }
                if (!$warrantTable->save($warrant)) {
                    //rollback transaction
                    $warrantRosterTable->getConnection()->rollback();
                    return new ServiceResult(false, "Failed to acivate warrants in Roster");
                }
            }
        }
        if (!$warrantRosterTable->save($warrantRoster)) {
            //rollback transaction
            $warrantRosterTable->getConnection()->rollback();
            return new ServiceResult(false, "Failed to approve Roster");
        }
        //commit transaction
        $warrantRosterTable->getConnection()->commit();
        return new ServiceResult(true);
    }

    public function reject($warrant_roster_id, $rejecter_id, $reason): ServiceResult
    {
        return new ServiceResult(true);
    }

    public function cancel($warrant_id, $reason, $rejecter_id, $expiresOn): ServiceResult
    {
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
        $warrant = $warrantTable->find($warrant_id)
            ->first();
        if ($warrant == null) {
            return new ServiceResult(true);
        }
        if ($expiresOn < new DateTime())
            $warrant->status = Warrant::DEACTIVATED_STATUS;
        $warrant->expires_on = $expiresOn;
        $warrant->revoked_reason = $reason;
        $warrant->revoker_id = $rejecter_id;
        if (!$warrantTable->save($warrant)) {
            return new ServiceResult(false, "Failed to cancel warrant");
        }
        return new ServiceResult(true);
    }

    public function cancelByEntity($entityType, $entityId, $reason, $rejecter_id, $expiresOn): ServiceResult
    {
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
        $warrant = $warrantTable->find()
            ->where([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ])
            ->first();
        if ($warrant == null) {
            return new ServiceResult(true);
        }
        if ($expiresOn < new DateTime())
            $warrant->status = Warrant::DEACTIVATED_STATUS;
        $warrant->expires_on = $expiresOn;
        $warrant->revoked_reason = $reason;
        $warrant->revoker_id = $rejecter_id;
        if (!$warrantTable->save($warrant)) {
            return new ServiceResult(false, "Failed to cancel warrant");
        }
        return new ServiceResult(true);
    }

    public function getWarrantPeriod(DateTime $startOn, DateTime $endOn): ?WarrantPeriod
    {
        $warrantPeriodTable = TableRegistry::getTableLocator()->get('WarrantPeriods');
        $warrantPeriod = $warrantPeriodTable->find()
            ->where([
                'start_date <=' => $startOn,
                'end_date >=' => $startOn
            ])
            ->first();
        if ($warrantPeriod == null) {
            return null;
        }
        if ($warrantPeriod->end_date > $endOn) {
            $warrantPeriod->end_date = $endOn;
        }
        if ($warrantPeriod->start_date < $startOn) {
            $warrantPeriod->start_date = $startOn;
        }
        return $warrantPeriod;
    }
}