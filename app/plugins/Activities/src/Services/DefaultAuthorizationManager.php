<?php

namespace Activities\Services;

use Activities\Model\Entity\Authorization;
use App\KMP\StaticHelpers;
use Activities\Services\AuthorizationManagerInterface;
use Cake\I18n\DateTime;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\TableRegistry;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;

class DefaultAuthorizationManager implements AuthorizationManagerInterface
{
    #region
    use MailerAwareTrait;

    #endregion

    public function __construct(ActiveWindowManagerInterface $activeWindowManager)
    {
        $this->activeWindowManager = $activeWindowManager;
    }

    #region public methods
    /**
     * Requests an authorization - Make sure to create a transaction before calling this service
     *
     * @param int $requesterId
     * @param int $activityId
     * @param int $approverId
     * @param bool $isRenewal
     * @return ServiceResult
     */
    public function request(
        int $requesterId,
        int $activityId,
        int $approverId,
        bool $isRenewal
    ): ServiceResult {
        $table = TableRegistry::getTableLocator()->get("Activities.Authorizations");
        // If its a renewal we will only create the auth if there is an existing auth that has not expired
        if ($isRenewal) {
            $existingAuths = $table
                ->find()
                ->where([
                    "member_id" => $requesterId,
                    "activity_id" => $activityId,
                    "status" => Authorization::APPROVED_STATUS,
                    "expires_on >" => DateTime::now(),
                ])
                ->count();
            if ($existingAuths == 0) {
                return new ServiceResult(false, "There is no existing authorization to renew");
            }
        }
        //Checking for existing pending requests
        $existingRequests = $table
            ->find()
            ->where([
                "member_id" => $requesterId,
                "activity_id" => $activityId,
                "status" => Authorization::PENDING_STATUS
            ])
            ->count();
        if ($existingRequests > 0) {
            return new ServiceResult(false, "There is already a pending request for this activity");
        }


        $auth = $table->newEmptyEntity();
        $auth->member_id = $requesterId;
        $auth->activity_id = $activityId;
        $auth->requested_on = DateTime::now();
        $auth->status = Authorization::PENDING_STATUS;
        $auth->is_renewal = $isRenewal;
        $table->getConnection()->begin();
        if (!$table->save($auth)) {
            $table->getConnection()->rollback();

            return new ServiceResult(false, "Failed to save authorization");
        }
        $approval = $table->AuthorizationApprovals->newEmptyEntity();
        $approval->authorization_id = $auth->id;
        $approval->approver_id = $approverId;
        $approval->requested_on = DateTime::now();
        $approval->authorization_token = StaticHelpers::generateToken(32);
        if (!$table->AuthorizationApprovals->save($approval)) {
            $table->getConnection()->rollback();

            return new ServiceResult(false, "Failed to save authorization approval");
        }
        if (
            !$this->sendApprovalRequestNotification(
                $activityId,
                $requesterId,
                $approverId,
                $approval->authorization_token,
            )
        ) {
            $table->getConnection()->rollback();

            return new ServiceResult(false, "Failed to send approval request notification");
        }
        $table->getConnection()->commit();

        return new ServiceResult(true);
    }
    /**
     * Approves an authorization approval - Make sure to create a transaction before calling this service
     *
     * @param int $authorizationApprovalId
     * @param int $approverId
     * @param int|null $nextApproverId
     * @return ServiceResult
     */
    public function approve(
        int $authorizationApprovalId,
        int $approverId,
        int $nextApproverId = null
    ): ServiceResult {
        $approvalTable = TableRegistry::getTableLocator()->get(
            "Activities.AuthorizationApprovals",
        );
        $authTable = $approvalTable->Authorizations;
        $transConnection = $approvalTable->getConnection();
        $transConnection->begin();
        $approval = $approvalTable->get(
            $authorizationApprovalId,
            contain: ["Authorizations.Activities"]
        );
        if (!$approval) {
            $transConnection->rollback();

            return new ServiceResult(false, "Approval not found");
        }
        $authorization = $approval->authorization;
        if (!$authorization) {
            $transConnection->rollback();

            return new ServiceResult(false, "Authorization not found");
        }
        $activity = $authorization->activity;
        if (!$activity) {
            $transConnection->rollback();

            return new ServiceResult(false, "Activity not found");
        }
        $this->saveAuthorizationApproval(
            $approverId,
            $approval,
            $approvalTable,
            $transConnection,
        );

        // Check if the authorization needs multiple approvers and process accordingly
        $requiredApprovalCount = $this->getApprovalsRequiredCount(
            $authorization->is_renewal,
            $activity,
        );
        if (
            $this->getNeedsMoreRenewals(
                $requiredApprovalCount,
                $authorization->id,
                $approvalTable,
            )
        ) {
            if (
                !$this->processForwardToNextApprover(
                    $approverId,
                    $nextApproverId,
                    $authorization,
                    $approvalTable,
                    $authTable,
                )
            ) {
                $transConnection->rollback();

                return new ServiceResult(false, "Failed to forward to next approver");
            } else {
                $transConnection->commit();

                return new ServiceResult(true);
            }
        } else {
            // Authorization is ready to approve
            if (
                !$this->processApprovedAuthorization(
                    $authorization,
                    $approverId,
                    $authTable
                )
            ) {
                $transConnection->rollback();

                return new ServiceResult(false, "Failed to process approved authorization");
            }
        }
        $transConnection->commit();

        return new ServiceResult(true);
    }
    /**
     * Denies an authorization approval - Make sure to create a transaction before calling this service
     *
     * @param int $authorizationApprovalId
     * @param int $approverId
     * @param string $denyReason
     * @return ServiceResult
     */
    public function deny(
        int $authorizationApprovalId,
        int $approverId,
        string $denyReason,
    ): ServiceResult {
        $table = TableRegistry::getTableLocator()->get(
            "Activities.AuthorizationApprovals",
        );
        $approval = $table->get(
            $authorizationApprovalId,
            contain: ["Authorizations"],
        );
        $approval->responded_on = DateTime::now();
        $approval->approved = false;
        $approval->approver_id = $approverId;
        $approval->approver_notes = $denyReason;
        $approval->authorization->revoker_id = $approverId;
        $approval->authorization->revoked_reason = $denyReason;
        $approval->authorization->status = Authorization::DENIED_STATUS;
        $approval->authorization->start_on = DateTime::now()->subSeconds(1);
        $approval->authorization->expires_on = DateTime::now()->subSeconds(1);
        $table->getConnection()->begin();
        if (
            !$table->save($approval) ||
            !$table->Authorizations->save($approval->authorization)
        ) {
            $table->getConnection()->rollback();
            return new ServiceResult(false, "Failed to deny authorization approval");
        }
        if (
            !$this->sendAuthorizationStatusToRequester(
                $approval->authorization->activity_id,
                $approval->authorization->member_id,
                $approverId,
                $approval->authorization->status,
                null,
            )
        ) {
            $table->getConnection()->rollback();

            return new ServiceResult(false, "Failed to send authorization status to requester");
        }
        $table->getConnection()->commit();

        return new ServiceResult(true);
    }

    /**
     * Revokes an authorization - Make sure to create a transaction before calling this service
     *
     * @param int $authorizationId
     * @param int $revokerId
     * @param string $revokedReason
     * @return ServiceResult
     */
    public function revoke(
        int $authorizationId,
        int $revokerId,
        string $revokedReason,
    ): ServiceResult {
        $table = TableRegistry::getTableLocator()->get("Activities.Authorizations");
        $table->getConnection()->begin();


        // revoke the member_role if it was granted
        $awResult = $this->activeWindowManager->stop(
            "Activities.Authorizations",
            $authorizationId,
            $revokerId,
            Authorization::REVOKED_STATUS,
            $revokedReason,
            DateTime::now()
        );
        if (!$awResult->success) {
            $table->getConnection()->rollback();
            return new ServiceResult(false, "Failed to revoke member role");
        }
        $authorization = $table->get($authorizationId);
        if (!$this->sendAuthorizationStatusToRequester(
            $authorization->activity_id,
            $authorization->member_id,
            $revokerId,
            $authorization->status,
            null,
        )) {
            $table->getConnection()->rollback();

            return new ServiceResult(false, "Failed to send authorization status to requester");
        }
        $table->getConnection()->commit();

        return new ServiceResult(true);
    }
    #endregion

    #region notifications
    protected function sendAuthorizationStatusToRequester(
        int $activityId,
        int $requesterId,
        int $approverId,
        string $status,
        int $nextApproverId = null,
    ): bool {
        $authTypesTable = TableRegistry::getTableLocator()->get(
            "Activities.Activities",
        );
        $membersTable = TableRegistry::getTableLocator()->get("Members");
        $activity = $authTypesTable
            ->find()
            ->where(["id" => $activityId])
            ->select(["name"])
            ->all()
            ->first();
        $member = $membersTable
            ->find()
            ->where(["id" => $requesterId])
            ->select(["sca_name", "email_address"])
            ->all()
            ->first();
        $approver = $membersTable
            ->find()
            ->where(["id" => $approverId])
            ->select(["sca_name"])
            ->all()
            ->first();
        if ($nextApproverId) {
            $nextApprover = $membersTable
                ->find()
                ->where(["id" => $nextApproverId])
                ->select(["sca_name"])
                ->all()
                ->first();
            $nextApproverScaName = $nextApprover->sca_name;
        } else {
            $nextApproverScaName = '';
        }
        $this->getMailer("Activities.Activities")->send("notifyRequester", [
            $member->email_address,
            $status,
            $member->sca_name,
            $requesterId,
            $approver->sca_name,
            $nextApproverScaName,
            $activity->name,
        ]);

        return true;
    }

    private function sendApprovalRequestNotification(
        int $activityId,
        int $requesterId,
        int $approverId,
        string $authorizationToken,
    ): bool {
        $authTypesTable = TableRegistry::getTableLocator()->get(
            "Activities.Activities",
        );
        $membersTable = TableRegistry::getTableLocator()->get("Members");
        $activity = $authTypesTable
            ->find()
            ->where(["id" => $activityId])
            ->select(["name"])
            ->all()
            ->first();
        $member = $membersTable
            ->find()
            ->where(["id" => $requesterId])
            ->select(["sca_name"])
            ->all()
            ->first();
        $approver = $membersTable
            ->find()
            ->where(["id" => $approverId])
            ->select(["sca_name", "email_address"])
            ->all()
            ->first();
        $this->getMailer("Activities.Activities")->send("notifyApprover", [
            $approver->email_address,
            $authorizationToken,
            $member->sca_name,
            $approver->sca_name,
            $activity->name,
        ]);

        return true;
    }
    // endregion

    // region approval processing methods
    private function processApprovedAuthorization(
        $authorization,
        $approverId,
        $authTable
    ): bool {
        $authorization->status = Authorization::APPROVED_STATUS;
        $authorization->approval_count = $authorization->approval_count + 1;
        if (!$authTable->save($authorization)) {
            return false;
        }
        $awResult = $this->activeWindowManager->start(
            "Activities.Authorizations",
            $authorization->id,
            $approverId,
            DateTime::now(),
            null,
            $authorization->activity->term_length,
            $authorization->activity->grants_role_id,
        );
        if (!$awResult->success) {
            return false;
        }
        if (
            !$this->sendAuthorizationStatusToRequester(
                $authorization->activity_id,
                $authorization->member_id,
                $approverId,
                $authorization->status,
                null,
            )
        ) {
            return false;
        }

        return true;
    }

    private function processForwardToNextApprover(
        $approverId,
        $nextApproverId,
        $authorization,
        $approvalTable,
        $authTable,
    ): bool {
        if ($nextApproverId == null) {
            return false;
        }
        if (
            $approvalTable->Approvers
            ->find()
            ->where(["id" => $nextApproverId])
            ->count() == 0
        ) {
            return false;
        }
        $authorization->status = Authorization::PENDING_STATUS;
        $authorization->approval_count = $authorization->approval_count + 1;
        $authorization->setDirty("status", true);
        $authorization->setDirty("approval_count", true);
        if (!$authTable->save($authorization)) {
            return false;
        }

        $nextApproval = $approvalTable->newEmptyEntity();
        $nextApproval->authorization_id = $authorization->id;
        $nextApproval->approver_id = $nextApproverId;
        $nextApproval->requested_on = DateTime::now();
        $nextApproval->authorization_token = StaticHelpers::generateToken(32);
        if (!$approvalTable->save($nextApproval)) {
            return false;
        }
        if (
            !$this->sendApprovalRequestNotification(
                $authorization->activity_id,
                $authorization->member_id,
                $nextApproverId,
                $nextApproval->authorization_token,
            )
        ) {
            return false;
        }

        if (
            !$this->sendAuthorizationStatusToRequester(
                $authorization->activity_id,
                $authorization->member_id,
                $approverId,
                $authorization->status,
                $nextApproverId,
            )
        ) {
            return false;
        }

        return true;
    }

    private function getApprovalsRequiredCount(
        $isRenewal,
        $activity,
    ): int {
        return $isRenewal
            ? $activity->num_required_renewers
            : $activity->num_required_authorizors;
    }

    private function getNeedsMoreRenewals(
        $requiredApprovalCount,
        $authorizationId,
        $approvalTable,
    ): bool {
        if ($requiredApprovalCount > 1) {
            $acceptedApprovals = $approvalTable
                ->find()
                ->where([
                    "authorization_id" => $authorizationId,
                    "approved" => true,
                ])
                ->count();

            if ($acceptedApprovals < $requiredApprovalCount) {
                return true;
            }
        }

        return false;
    }

    private function saveAuthorizationApproval(
        $approverId,
        $approval,
        $approvalTable,
        $transConnection,
    ): bool {
        // Set the approval to approved
        $approval->responded_on = DateTime::now();
        $approval->approved = true;
        $approval->approver_id = $approverId;

        // Save the approval
        if (!$approvalTable->save($approval)) {
            $transConnection->rollback();

            return false;
        }

        return true;
    }

    // endregion
}