<?php

namespace App\Services\ActivityAuthorizations;

use App\KMP\StaticHelpers;
use App\Services\ActivityAuthorizations\AuthorizationServiceInterface;
use Cake\I18n\DateTime;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\TableRegistry;

class DefaultAuthorizationService implements AuthorizationServiceInterface
{
    #region
    use MailerAwareTrait;

    #endregion

    #region public methods
    public function request(
        int $requesterId,
        int $authorizationTypeId,
        int $approverId,
        bool $isRenewal,
    ): bool {
        $table = TableRegistry::getTableLocator()->get("Authorizations");
        // If its a renewal we will only create the auth if there is an existing auth that has not expired
        if ($isRenewal) {
            $existingAuths = $table
                ->find()
                ->where([
                    "member_id" => $requesterId,
                    "activity_id" => $authorizationTypeId,
                    "status" => "approved",
                    "expires_on >" => DateTime::now(),
                ])
                ->count();
            if ($existingAuths == 0) {
                return false;
            }
        }
        $auth = $table->newEmptyEntity();
        $auth->member_id = $requesterId;
        $auth->activity_id = $authorizationTypeId;
        $auth->requested_on = DateTime::now();
        $auth->status = "new";
        $auth->is_renewal = $isRenewal;
        $table->getConnection()->begin();
        if (!$table->save($auth)) {
            $table->getConnection()->rollback();

            return false;
        }
        $approval = $table->AuthorizationApprovals->newEmptyEntity();
        $approval->authorization_id = $auth->id;
        $approval->approver_id = $approverId;
        $approval->requested_on = DateTime::now();
        $approval->authorization_token = StaticHelpers::generateToken(32);
        if (!$table->AuthorizationApprovals->save($approval)) {
            $table->getConnection()->rollback();

            return false;
        }
        if (
            !$this->sendApprovalRequestNotification(
                $authorizationTypeId,
                $requesterId,
                $approverId,
                $approval->authorization_token,
            )
        ) {
            $table->getConnection()->rollback();

            return false;
        }
        $table->getConnection()->commit();

        return true;
    }

    public function approve(
        int $authorizationApprovalId,
        int $approverId,
        int $nextApproverId = null,
    ): bool {
        $approvalTable = TableRegistry::getTableLocator()->get(
            "AuthorizationApprovals",
        );
        $authTable = $approvalTable->Authorizations;
        $transConnection = $approvalTable->getConnection();
        $transConnection->begin();
        $approval = $approvalTable->get($authorizationApprovalId, [
            "contain" => ["Authorizations.Activities"],
        ]);
        if (!$approval) {
            $transConnection->rollback();

            return false;
        }
        $authorization = $approval->authorization;
        if (!$authorization) {
            $transConnection->rollback();

            return false;
        }
        $authorizationType = $authorization->activity;
        if (!$authorizationType) {
            $transConnection->rollback();

            return false;
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
            $authorizationType,
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

                return false;
            } else {
                $transConnection->commit();

                return true;
            }
        } else {
            // Authorization is ready to approve
            if (
                !$this->expireExistingAuthorizations($authorization, $authTable)
            ) {
                $transConnection->rollback();

                return false;
            }
            if (
                !$this->processApprovedAuthorization(
                    $authorization,
                    $approverId,
                    $authTable,
                )
            ) {
                $transConnection->rollback();

                return false;
            }
        }
        $transConnection->commit();

        return true;
    }

    public function deny(
        int $authorizationApprovalId,
        int $approverId,
        string $denyReason,
    ): bool {
        $table = TableRegistry::getTableLocator()->get(
            "AuthorizationApprovals",
        );
        $approval = $table->get(
            $authorizationApprovalId,
            contain: ["Authorizations"],
        );
        $approval->responded_on = DateTime::now();
        $approval->approved = false;
        $approval->approver_notes = $denyReason;
        $approval->authorization->status = "rejected";
        $table->getConnection()->begin();
        if (
            !$table->save($approval) ||
            !$table->Authorizations->save($approval->authorization)
        ) {
            $table->getConnection()->rollback();

            return false;
        }
        if (
            !$this->sendAuthorizationStatusToRequestor(
                $approval->authorization->activity_id,
                $approval->authorization->member_id,
                $approverId,
                $approval->authorization->status,
                null,
            )
        ) {
            $table->getConnection()->rollback();

            return false;
        }
        $table->getConnection()->commit();

        return true;
    }

    public function revoke(
        int $authorizationId,
        int $revokerId,
        string $revokedReason,
    ): bool {
        $table = TableRegistry::getTableLocator()->get("Authorizations");
        $table->getConnection()->begin();

        $authorization = $table->get($authorizationId);

        // revoke the authorization
        $authorization->status = "revoked";
        $authorization->revoked_reason = $revokedReason;
        $authorization->expires_on = DateTime::now()->subDays(1);
        $authorization->revoker_id = $revokerId;
        $authorization->setDirty("status", true);
        $authorization->setDirty("revoked_reason", true);
        $authorization->setDirty("expires_on", true);
        $authorization->setDirty("revoker_id", true);
        if (!$table->save($authorization)) {
            $table->getConnection()->rollback();

            return false;
        }
        // revoke the member_role if it was granted
        if ($authorization->granted_member_role_id) {
            $memberRole = $table->MemberRoles->get(
                $authorization->granted_member_role_id,
            );
            $memberRole->expires_on = DateTime::now()->subSeconds(1);
            $memberRole->setDirty("expires_on", true);
            if (!$table->MemberRoles->save($memberRole)) {
                $table->getConnection()->rollback();

                return false;
            }
        }
        if (
            !$this->sendAuthorizationStatusToRequestor(
                $authorization->activity_id,
                $authorization->member_id,
                $revokerId,
                $authorization->status,
                null,
            )
        ) {
            $table->getConnection()->rollback();

            return false;
        }
        $table->getConnection()->commit();

        return true;
    }
    #endregion

    #region notifications
    protected function sendAuthorizationStatusToRequestor(
        int $authorizationTypeId,
        int $requesterId,
        int $approverId,
        string $status,
        int $nextApproverId = null,
    ): bool {
        $authTypesTable = TableRegistry::getTableLocator()->get(
            "Activities",
        );
        $membersTable = TableRegistry::getTableLocator()->get("Members");
        $authorizationType = $authTypesTable
            ->find()
            ->where(["id" => $authorizationTypeId])
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
        $this->getMailer("KMP")->send("notifyRequester", [
            $member->email_address,
            $status,
            $member->sca_name,
            $requesterId,
            $approver->sca_name,
            $nextApproverScaName,
            $authorizationType->name,
        ]);

        return true;
    }

    protected function sendApprovalRequestNotification(
        int $authorizationTypeId,
        int $requesterId,
        int $approverId,
        string $authorizationToken,
    ): bool {
        $authTypesTable = TableRegistry::getTableLocator()->get(
            "Activities",
        );
        $membersTable = TableRegistry::getTableLocator()->get("Members");
        $authorizationType = $authTypesTable
            ->find()
            ->where(["id" => $authorizationTypeId])
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
        $this->getMailer("KMP")->send("notifyApprover", [
            $approver->email_address,
            $authorizationToken,
            $member->sca_name,
            $approver->sca_name,
            $authorizationType->name,
        ]);

        return true;
    }
    // endregion

    // region approval processing methods
    private function processApprovedAuthorization(
        $authorization,
        $approverId,
        $authTable,
    ): bool {
        $authorization->status = "approved";
        $authorization->approval_count = $authorization->approval_count + 1;
        $authorization->start_on = DateTime::now();
        $authorization->expires_on = DateTime::now()->addYears(
            $authorization->activity->length,
        );
        if (!$authTable->save($authorization)) {
            return false;
        }
        // add the member_role if the activity has a grants_role_id
        if ($authorization->activity->grants_role_id) {
            $memberRole = $authTable->MemberRoles->newEmptyEntity();
            $memberRole->member_id = $authorization->member_id;
            $memberRole->role_id =
                $authorization->activity->grants_role_id;
            $memberRole->start_on = $authorization->start_on;
            $memberRole->expires_on = $authorization->expires_on;
            $memberRole->approver_id = $approverId;
            if (!$authTable->MemberRoles->save($memberRole)) {
                return false;
            }
            // add the member_role id to the authorization so we can revoke it later
            $authorization->granted_member_role_id = $memberRole->id;
            $authorization->setDirty("granted_member_role_id", true);
            if (!$authTable->save($authorization)) {
                return false;
            }
        }
        if (
            !$this->sendAuthorizationStatusToRequestor(
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

    private function expireExistingAuthorizations(
        $authorization,
        $authTable,
    ): bool {
        $previous_authorizations = $authTable
            ->find()
            ->where([
                "member_id" => $authorization->member_id,
                "activity_id" =>
                $authorization->activity_id,
                "status" => "approved",
            ])
            ->where(["expires_on >" => DateTime::now()])
            ->all();

        foreach ($previous_authorizations as $previous_authorization) {
            $previous_authorization->expires_on = DateTime::now()->subDays(1);
            $previous_authorization->setDirty("expires_on", true);
            if (!$authTable->save($previous_authorization)) {
                return false;
            }
            // revoke the member_role if it was granted
            if ($previous_authorization->granted_member_role_id) {
                $memberRole = $authTable->MemberRoles->get(
                    $previous_authorization->granted_member_role_id,
                );
                $memberRole->expires_on = DateTime::now()->subSeconds(1);
                $memberRole->setDirty("expires_on", true);
                if (!$authTable->MemberRoles->save($memberRole)) {
                    return false;
                }
            }
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
        $authorization->status = "pending";
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
            !$this->sendAuthorizationStatusToRequestor(
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
        $authorizationType,
    ): int {
        return $isRenewal
            ? $authorizationType->num_required_renewers
            : $authorizationType->num_required_authorizors;
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
