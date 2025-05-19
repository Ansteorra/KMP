<?php
declare(strict_types=1);

namespace App\Services\WarrantManager;

use App\KMP\StaticHelpers;
use App\Mailer\QueuedMailerAwareTrait;
use App\Model\Entity\MemberRole;
use App\Model\Entity\Warrant;
use App\Model\Entity\WarrantPeriod;
use App\Model\Entity\WarrantRoster;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;
use Cake\I18n\Date;
use Cake\I18n\DateTime;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\TableRegistry;

class DefaultWarrantManager implements WarrantManagerInterface
{
    #region
    use QueuedMailerAwareTrait;
    use MailerAwareTrait;

    private ActiveWindowManagerInterface $activeWindowManager;

    public function __construct(ActiveWindowManagerInterface $activeWindowManager)
    {
        $this->activeWindowManager = $activeWindowManager;
        //Datetime tomorrow
        $yesterday = new DateTime();
        $yesterday->modify('-1 day');
        $warrantCheck = StaticHelpers::getAppSetting('Warrant.LastCheck');
        if ($warrantCheck == '' || $warrantCheck < $yesterday) {
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
        $warrantRoster->approvals_required = StaticHelpers::getAppSetting('Warrant.RosterApprovalsRequired', 2);

        //start a transaction
        $warrantRosterTable->getConnection()->begin();
        if (!$warrantRosterTable->save($warrantRoster)) {
            //rollback transaction
            $warrantRosterTable->getConnection()->rollback();

            return new ServiceResult(false, 'Failed to create warrant approval set');
        }
        $warrantRequestTable = TableRegistry::getTableLocator()->get('Warrants');
        foreach ($warrantRequests as $warrantRequest) {
            $warrantRequestEntity = $warrantRequestTable->newEmptyEntity();
            $warrantRequestEntity->name = $warrantRequest->name;
            $warrantRequestEntity->entity_type = $warrantRequest->entity_type;
            $warrantRequestEntity->entity_id =  $warrantRequest->entity_id;
            $warrantRequestEntity->requester_id = $warrantRequest->requester_id;
            $warrantRequestEntity->member_id = $warrantRequest->member_id;
            $warrantRequestEntity->member_role_id = $warrantRequest->member_role_id;
            //get warrant period
            $warrantPeriod = $this->getWarrantPeriod($warrantRequest->start_on, $warrantRequest->expires_on);
            if ($warrantPeriod == null) {
                //rollback transaction
                $warrantRosterTable->getConnection()->rollback();

                return new ServiceResult(false, 'Invalid warrant period');
            }
            $member = TableRegistry::getTableLocator()->get('Members')->get($warrantRequest->member_id);
            if ($member->warrantable == null) {
                //rollback transaction
                $warrantRosterTable->getConnection()->rollback();

                return new ServiceResult(false, "$member->sca_name is not warrantable");
            }
            if ($warrantPeriod->start_date > $member->membership_expires_on) {
                //rollback transaction
                $warrantRosterTable->getConnection()->rollback();

                return new ServiceResult(false, "Warrant period is after membership expires for $member->sca_name");
            }
            //TODO: Reactivate once we get reliable membership data
            //if ($warrantPeriod->end_on > $member->membership_expires_on) {
            //    //rollback transaction
            //    $warrantRosterTable->getConnection()->rollback();
            //    return new ServiceResult(false, "Warrant period ends after membership expires for $member->sca_name");
            //}
            $warrantRequestEntity->start_on = $warrantPeriod->start_date;
            $warrantRequestEntity->expires_on = $warrantPeriod->end_date;
            $warrantRequestEntity->status = Warrant::PENDING_STATUS;
            $warrantRequestEntity->member_role_id = $warrantRequest->member_role_id;
            $warrantRequestEntity->warrant_roster_id = $warrantRoster->id;
            if (!$warrantRequestTable->save($warrantRequestEntity)) {
                //rollback transaction
                $warrantRosterTable->getConnection()->rollback();

                return new ServiceResult(false, "Failed to create pending warrant for $member->sca_name");
            }
        }
        //commit transaction
        $warrantRosterTable->getConnection()->commit();

        return new ServiceResult(true, '', $warrantRoster->id);
    }

    public function approve($warrant_roster_id, $approver_id): ServiceResult
    {
        $warrantRosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
        $warrantRoster = $warrantRosterTable->get($warrant_roster_id);
        if ($warrantRoster == null) {
            return new ServiceResult(false, 'Warrant Roster set not found');
        }
        if ($warrantRoster->status != WarrantRoster::STATUS_PENDING) {
            return new ServiceResult(false, 'Warrant Roster set is not pending');
        }
        if ($warrantRoster->hasRequiredApprovals()) {
            return new ServiceResult(false, 'Warrant approval set is already approved');
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

            return new ServiceResult(false, 'Failed to record warrant approval');
        }
        $warrantRoster->approval_count++;
        if ($warrantRoster->hasRequiredApprovals()) {
            $warrantRoster->status = WarrantRoster::STATUS_APPROVED;
            //get all warrants in the set that are pending and make them active
            $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
            $warrants = $warrantTable->find()
                ->contain(['Members' => function ($q) {
                    return $q->select(['id', 'email_address', 'sca_name']);
                }])
                ->where([
                    'warrant_roster_id' => $warrant_roster_id,
                    'Warrants.status' => Warrant::PENDING_STATUS,
                ])
                ->all();
            foreach ($warrants as $warrant) {
                $warrant->status = Warrant::CURRENT_STATUS;
                $warrant->approved_date = new DateTime();
                $now = new DateTime();
                $warrantStart = $warrant->start_on;
                if ($warrant->start_on == null || $warrantStart < $now) {
                    $warrant->start_on = $now;
                }
                if (!$warrantTable->save($warrant)) {
                    //rollback transaction
                    $warrantRosterTable->getConnection()->rollback();

                    return new ServiceResult(false, 'Failed to acivate warrants in Roster');
                }
                //expire current warrants for the same entity_type entity_id member_id
                $warrantTable->updateAll(
                    [
                        'status' => Warrant::DEACTIVATED_STATUS,
                        'expires_on' => $warrant->start_on,
                        'revoked_reason' => 'New Warrant Approved',
                        'revoker_id' => $approver_id,
                    ],
                    [
                        'entity_type' => $warrant->entity_type,
                        'entity_id' => $warrant->entity_id,
                        'member_id' => $warrant->member_id,
                        'status' => Warrant::CURRENT_STATUS,
                        'expires_on >=' => $warrant->start_on,
                        'start_on <=' => $warrant->start_on,
                        'id !=' => $warrant->id,
                    ],
                );
                $vars = [
                    'memberScaName' => $warrant->member->sca_name,
                    'warrantName' => $warrant->name,
                    'warrantStart' => $warrant->start_on_to_string,
                    'warrantExpires' => $warrant->expires_on_to_string,
                ];
                $this->queueMail('KMP', 'notifyOfWarrant', $warrant->member->email_address, $vars);
            }
        }
        if (!$warrantRosterTable->save($warrantRoster)) {
            //rollback transaction
            $warrantRosterTable->getConnection()->rollback();

            return new ServiceResult(false, 'Failed to approve Roster');
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
            return new ServiceResult(false, 'Warrant Roster not found');
        }
        if ($warrantRoster->status != WarrantRoster::STATUS_PENDING) {
            return new ServiceResult(false, 'Warrant Roster is not pending');
        }
        if ($warrantRoster->hasRequiredApprovals()) {
            return new ServiceResult(false, 'Warrant approval set is already approved');
        }
        //get all of the warrants in the set
        $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
        $warrants = $warrantTable->find()
            ->where([
                'warrant_roster_id' => $warrant_roster_id,
                'status not IN' => [Warrant::DECLINED_STATUS, Warrant::EXPIRED_STATUS, Warrant::DEACTIVATED_STATUS],
            ])
            ->all();
        //begin transaction
        $warrantRosterTable->getConnection()->begin();
        foreach ($warrants as $warrant) {
            if ($warrant->status == Warrant::PENDING_STATUS) {
                $warrant->status = Warrant::CANCELLED_STATUS;
                $warrant->revoked_reason = 'Warrant Roster Declined: ' . $reason;
                $warrant->revoker_id = $rejecter_id;
                if (!$warrantTable->save($warrant)) {
                    //rollback transaction
                    $warrantRosterTable->getConnection()->rollback();

                    return new ServiceResult(false, 'Failed to decline warrant #' . $warrant->id);
                }
            }
        }
        $warrantRoster->status = WarrantRoster::STATUS_DECLINED;
        if (!$warrantRosterTable->save($warrantRoster)) {
            //rollback transaction
            $warrantRosterTable->getConnection()->rollback();

            return new ServiceResult(false, 'Failed to decline Warrant Roster');
        }
        //add a note
        $noteTbl = TableRegistry::getTableLocator()->get('Notes');
        $note = $noteTbl->newEmptyEntity();
        $note->entity_type = 'WarrantRosters';
        $note->entity_id = $warrantRoster->id;
        $note->subject = 'Warrant Roster declined';
        $note->body = $reason;
        $note->author_id = $rejecter_id;
        if (!$noteTbl->save($note)) {
            //rollback transaction
            $warrantTable->getConnection()->rollback();

            return new ServiceResult(false, 'Failed to decline warrant');
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

            return new ServiceResult(false, 'Failed to decline warrant');
        }
        if ($warrantRoster->status == WarrantRoster::STATUS_PENDING) {
            $pendingWarrantCount = $warrantTable->find()
                ->where([
                    'warrant_roster_id' => $warrantRoster->id,
                    'status' => Warrant::PENDING_STATUS,
                ])
                ->count();
            if ($pendingWarrantCount == 0) {
                $warrantRoster->status = WarrantRoster::STATUS_DECLINED;
                if (!$warrantRosterTable->save($warrantRoster)) {
                    //rollback transaction
                    $connection->rollback();

                    return new ServiceResult(false, 'Failed to decline warrant');
                }
                //add a note
                $noteTbl = TableRegistry::getTableLocator()->get('Notes');
                $note = $noteTbl->newEmptyEntity();
                $note->entity_type = 'WarrantRosters';
                $note->entity_id = $warrantRoster->id;
                $note->subject = 'Warrant Roster declined';
                $note->body = 'All Warrants in the roster were individually declined, and so the roster was declined.';
                $note->author_id = $rejecter_id;
                if (!$noteTbl->save($note)) {
                    //rollback transaction
                    $connection->rollback();

                    return new ServiceResult(false, 'Failed to decline warrant');
                }
            }
        }
        //commit transaction
        $connection->commit();

        return new ServiceResult(true);
    }

    public function getWarrantPeriod(DateTime $startOn, ?DateTime $endOn): ?WarrantPeriod
    {
        $today = new DateTime();
        $warrantPeriodTable = TableRegistry::getTableLocator()->get('WarrantPeriods');
        $warrantPeriod = $warrantPeriodTable->find()
            ->where([
                'start_date <=' => $today,
                'end_date >=' => $startOn,
                'end_date >' => $today,
            ])
            ->orderByDesc('start_date')
            ->first();
        if ($warrantPeriod == null) {
            return null;
        }
        if (($endOn != null) && ($warrantPeriod->end_date->toNative() > $endOn->toNative())) {
            $warrantPeriod->end_date = new Date($endOn->toDateString());
        }
        if ($warrantPeriod->start_date->toNative() < $startOn->toNative()) {
            $warrantPeriod->start_date = new Date($startOn->toDateString());
        }

        return $warrantPeriod;
    }

    protected function cancelWarrant($warrantTable, $warrant, $expiresOn, $rejecter_id, $reason): ServiceResult
    {
        if ($expiresOn < new DateTime()) {
            $warrant->status = Warrant::DEACTIVATED_STATUS;
        }
        $warrant->expires_on = $expiresOn;
        $warrant->revoked_reason = $reason;
        $warrant->revoker_id = $rejecter_id;
        if (!$warrantTable->save($warrant)) {
            return new ServiceResult(false, 'Failed to cancel warrant');
        }

        return new ServiceResult(true);
    }

    protected function stopWarrantDependants($warrant, $rejecter_id): ServiceResult
    {

        if ($warrant->member_role_id != null) {
            /**
             *
             * @var \App\Services\WarrantManager\ServiceRequest $awResult
             */
            $awResult = $this->activeWindowManager->stop(
                'MemberRoles',
                $warrant->member_role_id,
                $rejecter_id,
                MemberRole::DEACTIVATED_STATUS,
                'Warrant Declined',
                new DateTime(),
            );
            if (!$awResult->success) {
                return new ServiceResult(false, $awResult->reason);
            }
        }
        if ($warrant->entity_type != 'Direct Grant') {
            /**
             *
             * @var \App\Services\WarrantManager\ServiceRequest $awResult
             */
            $awResult = $this->activeWindowManager->stop(
                $warrant->entity_type,
                $warrant->entity_id,
                $rejecter_id,
                MemberRole::DEACTIVATED_STATUS,
                'Warrant Declined',
                new DateTime(),
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
            return new ServiceResult(false, 'Failed to decline warrant');
        }
        $result = $this->stopWarrantDependants($warrant, $rejecter_id, $reason);
        if (!$result->success) {
            return $result;
        }

        return new ServiceResult(true);
    }
}
