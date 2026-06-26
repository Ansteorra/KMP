<?php

declare(strict_types=1);

namespace Activities\Services;

use Activities\Model\Entity\Authorization;
use Activities\Services\AuthorizationManagerInterface;
use App\KMP\StaticHelpers;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\Mailer\MailerAwareTrait;
use Cake\ORM\TableRegistry;
use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\Router;
use App\Services\ActiveWindowManager\ActiveWindowManagerInterface;
use App\Services\ServiceResult;

/**
 * Default Authorization Manager Service
 *
 * Production implementation of AuthorizationManagerInterface providing
 * authorization lifecycle management for the Activities plugin.
 *
 * Handles authorization request creation, activation, revocation, and retraction.
 * Approval and denial workflows are handled by the unified workflow engine.
 *
 * @see AuthorizationManagerInterface Service contract definition
 * @see ActiveWindowManagerInterface Temporal validation service
 * @see ServiceResult Standardized result pattern
 * @see Authorization Authorization entity
 */
class DefaultAuthorizationManager implements AuthorizationManagerInterface
{
    #region
    use MailerAwareTrait;

    private ActiveWindowManagerInterface $activeWindowManager;

    private TriggerDispatcher $triggerDispatcher;

    #endregion

    /**
     * Constructor.
     *
     * @param ActiveWindowManagerInterface $activeWindowManager
     * @param TriggerDispatcher $triggerDispatcher
     */
    public function __construct(ActiveWindowManagerInterface $activeWindowManager, TriggerDispatcher $triggerDispatcher)
    {
        $this->activeWindowManager = $activeWindowManager;
        $this->triggerDispatcher = $triggerDispatcher;
    }

    #region public methods
    /**
     * Create a new authorization request in pending status.
     *
     * Validates renewal eligibility, prevents duplicates, and creates the
     * authorization entity. Notification and approval workflow are handled
     * by the workflow engine after this method returns.
     *
     * @param int $requesterId Member ID requesting authorization
     * @param int $activityId Activity ID for authorization request
     * @param int $approverId Member ID of designated approver
     * @param bool $isRenewal Whether this is a renewal of existing authorization
     * @return ServiceResult Success/failure result with error details
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
        if (!$table->save($auth)) {
            return new ServiceResult(false, "Failed to save authorization");
        }

        return new ServiceResult(true);
    }

    /**
     * Process Authorization Revocation
     * 
     * Handles revocation of active authorizations with ActiveWindow integration
     * and comprehensive audit trail maintenance.
     * 
     * **Revocation Workflow**:
     * - Validates authorization exists and is revocable
     * - Integrates with ActiveWindowManager to stop temporal validation
     * - Updates authorization status to revoked
     * - Records revoker ID and revocation reason
     * - Automatically removes associated role assignments
     * - Sends notification to affected member
     * 
     * **ActiveWindow Integration**:
     * Uses ActiveWindowManager.stop() to:
     * - End temporal validation window immediately
     * - Remove role assignments granted by authorization
     * - Update authorization status to revoked
     * - Create complete audit trail of revocation
     * 
     * **Role Management**:
     * Revocation automatically removes any roles that were granted by the
     * authorization, ensuring immediate cessation of elevated permissions
     * and maintaining security compliance.
     * 
     * **Audit Requirements**:
     * - Records revoker identity for accountability
     * - Captures revocation reason for compliance
     * - Timestamps revocation for temporal tracking
     * - Maintains complete authorization lifecycle history
     * 
     * **Notification Process**:
     * Automatically notifies affected member of revocation with:
     * - Revocation reason and revoker identity
     * - Effective date of revocation
     * - Impact on permissions and roles
     * - Appeal or reauthorization process information
     * 
     * @param int $authorizationId Authorization record ID to revoke
     * @param int $revokerId Member ID of person performing revocation
     * @param string $revokedReason Reason for revocation (required for audit)
     * @return ServiceResult Success/failure result with revocation confirmation
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

        // Cancel any linked workflow engine approvals/instances for this authorization
        $workflowCancelResult = $this->cancelWorkflowApprovalsForEntity($authorizationId, 'Authorization revoked');
        if (!$workflowCancelResult->success) {
            $table->getConnection()->rollback();
            return $workflowCancelResult;
        }

        $table->getConnection()->commit();

        return new ServiceResult(true);
    }

    /**
     * Activate a fully-approved authorization.
     *
     * Sets status to APPROVED, starts ActiveWindow, assigns role.
     * Does not send notifications (workflow handles those separately).
     *
     * @param int $authorizationId Authorization ID to activate
     * @param int $approverId Member ID of the final approver
     * @return ServiceResult Success with activated and memberRoleId data
     */
    public function activate(
        int $authorizationId,
        int $approverId,
    ): ServiceResult {
        $table = TableRegistry::getTableLocator()->get("Activities.Authorizations");
        $authorization = $table->get($authorizationId, contain: ['Activities']);

        if (!$authorization) {
            return new ServiceResult(false, "Authorization not found");
        }

        $activity = $authorization->activity;
        if (!$activity) {
            return new ServiceResult(false, "Activity not found for authorization");
        }

        $table->getConnection()->begin();

        $authorization->status = Authorization::APPROVED_STATUS;
        if (!$table->save($authorization)) {
            $table->getConnection()->rollback();
            return new ServiceResult(false, "Failed to save authorization");
        }

        $awResult = $this->activeWindowManager->start(
            "Activities.Authorizations",
            $authorization->id,
            $approverId,
            DateTime::now(),
            null,
            $activity->term_length,
            $activity->grants_role_id,
        );

        if (!$awResult->success) {
            $table->getConnection()->rollback();
            return new ServiceResult(false, "Failed to start active window");
        }

        $table->getConnection()->commit();

        $memberRoleId = $awResult->data['memberRoleId'] ?? ($awResult->data ?? null);

        return new ServiceResult(true, null, [
            'activated' => true,
            'memberRoleId' => $memberRoleId,
        ]);
    }
    #endregion

    #region notifications
    /**
     * Send Authorization Status Notification to Requester
     * 
     * Sends status update notifications to authorization requesters with comprehensive
     * workflow context and next steps information.
     * 
     * **Notification Context**:
     * - Activity name and details
     * - Current authorization status
     * - Approver identity for transparency
     * - Next approver in chain (if applicable)
     * - Requester personalization
     * 
     * **Status Types Handled**:
     * - Approved: Final approval with role assignment details
     * - Denied: Denial reason and appeal process
     * - Pending: Forward to next approver information
     * - Revoked: Revocation details and impact
     * 
     * @param int $activityId Activity ID for context
     * @param int $requesterId Member ID of requester
     * @param int $approverId Member ID of current approver
     * @param string $status Current authorization status
     * @param int|null $nextApproverId Next approver ID (for multi-level workflows)
     * @return bool Success/failure of notification sending
     */
    protected function sendAuthorizationStatusToRequester(
        int $activityId,
        int $requesterId,
        int $approverId,
        string $status,
        ?int $nextApproverId = null,
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
        $memberCardUrl = $this->buildMemberCardUrl($requesterId);
        $this->getMailer('KMP')->send('sendFromTemplate', [
            'to' => $member->email_address,
            '_templateId' => 'authorization-request-update',
            'status' => $status,
            'memberScaName' => $member->sca_name,
            'memberCardUrl' => $memberCardUrl,
            'approverScaName' => $approver->sca_name,
            'nextApproverScaName' => $nextApproverScaName,
            'activityName' => $activity->name,
            'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature', '', null, true),
        ]);

        return true;
    }

    // endregion

    // region retraction and cancellation methods

    /**
     * Retract Pending Authorization Request
     * 
     * Allows a member to retract their own pending authorization request. This provides
     * member autonomy to cancel requests sent to wrong approvers or no longer needed.
     * 
     * **Business Logic**:
     * - Validates authorization is in pending status
     * - Ensures requester owns the authorization
     * - Updates status to retracted
     * - Maintains audit trail
     * - Optionally notifies approver of retraction
     * 
     * **Validation Rules**:
     * - Authorization must exist
     * - Authorization must be in PENDING status
     * - Requester must match authorization member_id
     * - Authorization cannot have been approved/denied
     * 
     * **Transaction Management**:
     * Uses database transactions to ensure consistency:
     * - Status update to RETRACTED
     * - Optional notification sending
     * - Rollback on any failure
     * 
     * **Success Result Data**:
     * Returns ServiceResult with:
     * - success: true
     * - data: ['authorization' => retracted authorization entity]
     * 
     * **Error Scenarios**:
     * - Authorization not found: "Authorization not found"
     * - Not pending: "Only pending authorizations can be retracted"
     * - Wrong owner: "You can only retract your own authorization requests"
     * - Status update failure: "Failed to update authorization status"
     * 
     * @param int $authorizationId Authorization record ID to retract
     * @param int $requesterId Member ID of person retracting (must be authorization owner)
     * @return ServiceResult Success/failure result with retraction confirmation
     */
    public function retract(
        int $authorizationId,
        int $requesterId
    ): ServiceResult {
        $table = TableRegistry::getTableLocator()->get("Activities.Authorizations");

        // Get the authorization
        $authorization = $table->find()
            ->where(['id' => $authorizationId])
            ->first();

        if (!$authorization) {
            return new ServiceResult(false, "Authorization not found");
        }

        // Validate authorization is pending
        if ($authorization->status !== Authorization::PENDING_STATUS) {
            return new ServiceResult(false, "Only pending authorizations can be retracted");
        }

        // Validate requester owns this authorization
        if ($authorization->member_id !== $requesterId) {
            return new ServiceResult(false, "You can only retract your own authorization requests");
        }

        // Begin transaction
        $table->getConnection()->begin();

        // Use ActiveWindowManager to stop the authorization (same as revoke)
        $retractedReason = "Retracted by requester on " . DateTime::now()->format('Y-m-d H:i:s');
        $awResult = $this->activeWindowManager->stop(
            "Activities.Authorizations",
            $authorizationId,
            $requesterId,
            Authorization::RETRACTED_STATUS,
            $retractedReason,
            DateTime::now()
        );

        if (!$awResult->success) {
            $table->getConnection()->rollback();
            return new ServiceResult(false, "Failed to retract authorization");
        }

        // Reload the authorization to get updated status
        $authorization = $table->get($authorizationId);

        // Cancel any linked workflow engine approvals/instances for this authorization
        $workflowCancelResult = $this->cancelWorkflowApprovalsForEntity(
            $authorizationId,
            'Authorization request retracted',
        );
        if (!$workflowCancelResult->success) {
            $table->getConnection()->rollback();
            return $workflowCancelResult;
        }

        // Commit transaction
        $table->getConnection()->commit();

        return new ServiceResult(true, null, ['authorization' => $authorization]);
    }

    /**
     * Send Retraction Notification to Approver
     * 
     * Notifies the approver that an authorization request they were reviewing
     * has been retracted by the requester.
     * 
     * **Notification Context**:
     * - Activity name
     * - Requester name
     * - Retraction timestamp
     * 
     * @param int $activityId Activity ID for context
     * @param int $requesterId Member ID of requester who retracted
     * @param int $approverId Member ID of approver to notify
     * @return bool Success/failure of notification sending
     */
    private function sendRetractedNotificationToApprover(
        int $activityId,
        int $requesterId,
        int $approverId
    ): bool {
        $activitiesTable = TableRegistry::getTableLocator()->get("Activities.Activities");
        $membersTable = TableRegistry::getTableLocator()->get("Members");

        $activity = $activitiesTable->get($activityId);
        $requester = $membersTable->get($requesterId);
        $approver = $membersTable->get($approverId);

        try {
            $this->getMailer('KMP')->send('sendFromTemplate', [
                'to' => $approver->email_address,
                '_templateId' => 'authorization-request-retracted',
                'activityName' => $activity->name,
                'approverScaName' => $approver->sca_name,
                'requesterScaName' => $requester->sca_name,
                'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature', '', null, true),
            ]);
            return true;
        } catch (\Exception $e) {
            // Log but don't fail on notification errors
            return false;
        }
    }

    private function buildMemberCardUrl(int $memberId): string
    {
        try {
            return Router::url([
                'controller' => 'Members',
                'action' => 'viewCard',
                'plugin' => null,
                '_full' => true,
                $memberId,
            ]);
        } catch (MissingRouteException) {
            return rtrim(Router::fullBaseUrl(), '/') . '/members/view-card/' . $memberId;
        }
    }

    /**
     * Cancel all open workflow engine state for an authorization entity.
     *
     * Finds workflow instances linked to the given authorization ID
     * (both current and legacy entity_type values, including older instances
     * that only stored authorizationId in context) and cancels any pending
     * approvals as well as the waiting/running workflow instance itself.
     *
     * @param int $authorizationId Authorization record ID
     * @param string $cancellationReason Human-readable cancellation reason
     * @return \App\Services\ServiceResult
     */
    private function cancelWorkflowApprovalsForEntity(int $authorizationId, string $cancellationReason): ServiceResult
    {
        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $wfApprovalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');

        $instances = $instancesTable->find()
            ->where([
                'entity_type IN' => ['Activities', 'Activities.Authorizations'],
                'status IN' => [
                    WorkflowInstance::STATUS_PENDING,
                    WorkflowInstance::STATUS_RUNNING,
                    WorkflowInstance::STATUS_WAITING,
                ],
                'OR' => [
                    'entity_id' => $authorizationId,
                    'entity_id IS' => null,
                ],
            ])
            ->all();

        foreach ($instances as $instance) {
            if (!$this->workflowInstanceMatchesAuthorization($instance, $authorizationId)) {
                continue;
            }

            $pendingApprovals = $wfApprovalsTable->find()
                ->where([
                    'workflow_instance_id' => $instance->id,
                    'status' => WorkflowApproval::STATUS_PENDING,
                ])
                ->all();

            foreach ($pendingApprovals as $wfApproval) {
                $wfApproval->status = WorkflowApproval::STATUS_CANCELLED;
                if (!$wfApprovalsTable->save($wfApproval)) {
                    return new ServiceResult(false, 'Failed to cancel linked workflow approval.');
                }
            }

            $errorInfo = $instance->error_info ?? [];
            $errorInfo['cancellation_reason'] = $cancellationReason;
            $instance->status = WorkflowInstance::STATUS_CANCELLED;
            $instance->completed_at = DateTime::now();
            $instance->error_info = $errorInfo;

            if (!$instancesTable->save($instance)) {
                return new ServiceResult(false, 'Failed to cancel linked workflow instance.');
            }
        }

        return new ServiceResult(true);
    }

    /**
     * Determine whether a workflow instance belongs to the given authorization.
     *
     * @param \App\Model\Entity\WorkflowInstance $instance Workflow instance candidate
     * @param int $authorizationId Authorization record ID
     * @return bool
     */
    private function workflowInstanceMatchesAuthorization(WorkflowInstance $instance, int $authorizationId): bool
    {
        if ($instance->entity_id === $authorizationId) {
            return true;
        }

        $context = $instance->context ?? [];
        $triggerAuthorizationId = $context['trigger']['authorizationId'] ?? null;
        $createdAuthorizationId = $context['nodes']['validate-request']['result']['authorizationId'] ?? null;

        return (int)($triggerAuthorizationId ?? $createdAuthorizationId ?? 0) === $authorizationId;
    }

    // endregion
}
