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
use App\Model\Entity\MemberRole;

use App\KMP\StaticHelpers;


class DefaultWarrantManager implements WarrantManagerInterface
{
    public function __construct(ActiveWindowManagerInterface $activeWindowManager)
    {
        $this->activeWindowManager = $activeWindowManager;
        //Datetime tomorrow
        $yesterday = new DateTime();
        $yesterday->modify("-1 day");
        $warrantCheck = StaticHelpers::getAppSetting('Warrant.LastCheck');
        if ($warrantCheck == "" || $warrantCheck < $yesterday) {
            $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
            $warrants = $warrantTable->find()
                ->where(['status' => Warrant::CURRENT_STATUS, 'expires_on <' => DateTime::now()])
                ->all();
            foreach ($warrants as $warrant) {
                $warrant->status = Warrant::EXPIRED_STATUS;
                $warrantTable->save($warrant);
            }
            StaticHelpers::setAppSetting('Warrant.LastCheck', DateTime::now());
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
        $warrantRoster->approvals_required = StaticHelpers::getAppSetting("Warrant.RosterApprovalsRequired", 2);
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

    public function decline($warrant_roster_id, $rejecter_id, $reason): ServiceResult
    {
        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $warrantRoster = $warrantRosterTable->get($warrant_roster_id);
        if ($warrantRoster == null) {
            return new ServiceResult(false, "Warrant Roster not found");
        }
        if ($warrantRoster->status != WarrantRoster::STATUS_PENDING) {
            return new ServiceResult(false, "Warrant Roster is not pending");
        }
        if ($warrantRoster->hasRequiredApprovals()) {
            return new ServiceResult(false, "Warrant approval set is already approved");
        }
        //get all of the warrants in the set
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
        $warrants = $warrantTable->find()
            ->where([
                'warrant_roster_id' => $warrant_roster_id,
                'status not IN' => [Warrant::DECLINED_STATUS, Warrant::EXPIRED_STATUS, Warrant::DEACTIVATED_STATUS]
            ])
            ->all();
        //begin transaction
        $warrantRosterTable->getConnection()->begin();
        foreach ($warrants as $warrant) {
            $result = $this->declineWarrant($warrantTable, $warrant, $rejecter_id, $reason);
            if (!$result->success) {
                $warrantRosterTable->getConnection()->rollback();
                return $result;
            }
        }
        $warrantRoster->status = WarrantRoster::STATUS_DECLINED;
        if (!$warrantRosterTable->save($warrantRoster)) {
            //rollback transaction
            $warrantRosterTable->getConnection()->rollback();
            return new ServiceResult(false, "Failed to decline Warrant Roster");
        }
        //add a note 
        $noteTbl = TableRegistry::getTableLocator()->get('Notes');
        $note = $noteTbl->newEmptyEntity();
        $note->entity_type = 'WarrantRosters';
        $note->entity_id = $warrantRoster->id;
        $note->subject = "Warrant Roster declined";
        $note->body = $reason;
        $note->author_id = $rejecter_id;
        if (!$noteTbl->save($note)) {
            //rollback transaction
            $warrantTable->getConnection()->rollback();
            return new ServiceResult(false, "Failed to decline warrant");
        }
        //commit transaction
        $warrantRosterTable->getConnection()->commit();
        return new ServiceResult(true);
    }

    public function cancel($warrant_id, $reason, $rejecter_id, $expiresOn): ServiceResult
    {
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
        $warrant = $warrantTable->get($warrant_id);
        if ($warrant == null) {
            return new ServiceResult(true);
        }
        return $this->cancelWarrant($warrantTable, $warrant, $expiresOn, $rejecter_id, $reason);
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
        return $this->cancelWarrant($warrantTable, $warrant, $expiresOn, $rejecter_id, $reason);
    }

    public function declineSingleWarrant($warrant_id, $reason, $rejecter_id): ServiceResult
    {
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');

        $warrant = $warrantTable->get($warrant_id);
        if ($warrant == null) {
            return new ServiceResult(true);
        }
        //begin transaction
        $connection = $warrantTable->getConnection();
        $connection->begin();
        $result = $this->declineWarrant($warrantTable, $warrant, $rejecter_id, $reason);
        if (!$result->success) {
            $connection->rollback();
            return $result;
        }

        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $warrantRoster = $warrantRosterTable->find()
            ->where(['id' => $warrant->warrant_roster_id])
            ->select(['id', 'status'])
            ->first();
        if ($warrantRoster == null) {
            //rollback transaction
            $connection->rollback();
            return new ServiceResult(false, "Failed to decline warrant");
        }
        if ($warrantRoster->status == WarrantRoster::STATUS_PENDING) {
            $pendingWarrantCount = $warrantTable->find()
                ->where([
                    'warrant_roster_id' => $warrantRoster->id,
                    'status' => Warrant::PENDING_STATUS
                ])
                ->count();
            if ($pendingWarrantCount == 0) {
                $warrantRoster->status = WarrantRoster::STATUS_DECLINED;
                if (!$warrantRosterTable->save($warrantRoster)) {
                    //rollback transaction
                    $connection->rollback();
                    return new ServiceResult(false, "Failed to decline warrant");
                }
                //add a note 
                $noteTbl = TableRegistry::getTableLocator()->get('Notes');
                $note = $noteTbl->newEmptyEntity();
                $note->entity_type = 'WarrantRosters';
                $note->entity_id = $warrantRoster->id;
                $note->subject = "Warrant Roster declined";
                $note->body = "All Warrants in the roster were individually declined, and so the roster was declined.";
                $note->author_id = $rejecter_id;
                if (!$noteTbl->save($note)) {
                    //rollback transaction
                    $connection->rollback();
                    return new ServiceResult(false, "Failed to decline warrant");
                }
            }
        }
        //commit transaction
        $connection->commit();
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

    protected function cancelWarrant($warrantTable, $warrant, $expiresOn, $rejecter_id, $reason): ServiceResult
    {
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

    protected function stopWarrantDependants($warrant, $rejecter_id): ServiceResult
    {
        if ($warrant->member_role_id != null) {
            $awResult = $this->activeWindowManager->stop(
                "MemberRoles",
                $warrant->member_role_id,
                $rejecter_id,
                MemberRole::DEACTIVATED_STATUS,
                "Warrant Declined",
                new DateTime()
            );
            if (!$awResult->success) {
                return new ServiceResult(false, $awResult->reason);
            }
        }
        if ($warrant->entity_type != 'Direct Grant') {
            $awResult = $this->activeWindowManager->stop(
                $warrant->entity_type,
                $warrant->entity_id,
                $rejecter_id,
                MemberRole::DEACTIVATED_STATUS,
                "Warrant Declined",
                new DateTime()
            );
            if (!$awResult->success) {
                return new ServiceResult(false, $awResult->reason);
            }
        }
        return new ServiceResult(true);
    }

    protected function declineWarrant($warrantTable, $warrant, $rejecter_id, $reason): ServiceResult
    {
        $warrant->status = Warrant::DECLINED_STATUS;
        $warrant->revoked_reason = $reason;
        $warrant->revoker_id = $rejecter_id;
        if (!$warrantTable->save($warrant)) {
            return new ServiceResult(false, "Failed to decline warrant");
        }
        $result = $this->stopWarrantDependants($warrant, $rejecter_id, $reason);
        if (!$result->success) {
            return $result;
        }
        return new ServiceResult(true);
    }
}