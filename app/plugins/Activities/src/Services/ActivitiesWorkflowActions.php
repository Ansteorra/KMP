<?php
declare(strict_types=1);

namespace Activities\Services;

use Activities\Model\Entity\Authorization;
use App\KMP\StaticHelpers;
use App\Mailer\QueuedMailerAwareTrait;
use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\Router;
use RuntimeException;
use Throwable;

/**
 * Workflow action implementations for activity authorization operations.
 *
 * Delegates authorization lifecycle operations to AuthorizationManagerInterface
 * to avoid duplicating business logic.
 */
class ActivitiesWorkflowActions
{
    use QueuedMailerAwareTrait;
    use WorkflowContextAwareTrait;

    private AuthorizationManagerInterface $authManager;

    /**
     * Constructor.
     *
     * @param \Activities\Services\AuthorizationManagerInterface $authManager Authorization manager
     */
    public function __construct(AuthorizationManagerInterface $authManager)
    {
        $this->authManager = $authManager;
    }

    /**
     * Create authorization request.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId, activityId, approverId, isRenewal
     * @return array Output with authorizationId
     */
    public function createAuthorizationRequest(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'], $context);
            $activityId = (int)$this->resolveValue($config['activityId'], $context);
            $approverId = (int)$this->resolveValue($config['approverId'], $context);
            $isRenewal = (bool)$this->resolveValue($config['isRenewal'] ?? false, $context);

            $result = $this->authManager->request($memberId, $activityId, $approverId, $isRenewal);

            if (!$result->success) {
                Log::warning('Workflow CreateAuthorizationRequest: ' . $result->reason);

                return ['authorizationId' => null];
            }

            // Fetch the created authorization
            $authTable = TableRegistry::getTableLocator()->get('Activities.Authorizations');
            $auth = $authTable->find()
                ->where([
                    'member_id' => $memberId,
                    'activity_id' => $activityId,
                    'status' => 'Pending',
                ])
                ->orderBy(['Authorizations.id' => 'DESC'])
                ->first();

            return [
                'authorizationId' => $auth ? $auth->id : null,
            ];
        } catch (Throwable $e) {
            Log::error('Workflow CreateAuthorizationRequest failed: ' . $e->getMessage());

            return ['authorizationId' => null];
        }
    }

    /**
     * Activate a fully-approved authorization (set status, start ActiveWindow, assign role).
     *
     * @param array $context Current workflow context
     * @param array $config Config with authorizationId, approverId
     * @return array Output with activated boolean and memberRoleId
     */
    public function activateAuthorization(array $context, array $config): array
    {
        try {
            $authorizationId = (int)$this->resolveValue($config['authorizationId'], $context);
            $approverId = $this->resolveValue($config['approverId'] ?? null, $context);
            if (!$approverId) {
                $approverId = $context['resumeData']['approverId'] ?? $context['triggeredBy'] ?? 0;
            }
            $approverId = (int)$approverId;

            $result = $this->authManager->activate($authorizationId, $approverId);

            if (!$result->success) {
                Log::warning('Workflow ActivateAuthorization: ' . $result->reason);

                return ['activated' => false, 'memberRoleId' => null];
            }

            $data = $result->data ?? [];

            return [
                'activated' => true,
                'memberRoleId' => $data['memberRoleId'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('Workflow ActivateAuthorization failed: ' . $e->getMessage());

            return ['activated' => false, 'memberRoleId' => null];
        }
    }

    /**
     * Process denial of an authorization request.
     *
     * Looks up the pending approval record by authorizationId since the
     * workflow definition passes authorizationId, not authorizationApprovalId.
     *
     * @param array $context Current workflow context
     * @param array $config Config with authorizationId, approverId, denyReason
     * @return array Output with denied boolean
     */
    public function handleDenial(array $context, array $config): array
    {
        try {
            $authorizationId = (int)$this->resolveValue($config['authorizationId'], $context);
            $approverId = $this->resolveValue($config['approverId'] ?? null, $context);
            if (!$approverId) {
                $approverId = $context['resumeData']['approverId'] ?? $context['triggeredBy'] ?? 0;
            }
            $approverId = (int)$approverId;

            $denyReason = $this->resolveValue($config['denyReason'] ?? '', $context);
            if (empty($denyReason)) {
                $denyReason = $context['resumeData']['comment'] ?? 'Denied via workflow';
            }

            // Update the authorization directly
            $authTable = TableRegistry::getTableLocator()->get('Activities.Authorizations');
            $authorization = $authTable->get($authorizationId);

            if ($authorization->status !== Authorization::PENDING_STATUS) {
                Log::warning(
                    "Workflow HandleDenial: authorization {$authorizationId} is not pending "
                    . "(status: {$authorization->status})",
                );

                return ['denied' => false];
            }

            $authorization->status = Authorization::DENIED_STATUS;
            $authorization->revoker_id = $approverId;
            $authorization->revoked_reason = $denyReason;
            $authorization->start_on = DateTime::now()->subSeconds(1);
            $authorization->expires_on = DateTime::now()->subSeconds(1);

            if (!$authTable->save($authorization)) {
                Log::error("Workflow HandleDenial: failed to save authorization {$authorizationId}");

                return ['denied' => false];
            }

            return ['denied' => true];
        } catch (Throwable $e) {
            Log::error('Workflow HandleDenial failed: ' . $e->getMessage());

            return ['denied' => false];
        }
    }

    /**
     * Send approval request email to designated approver.
     *
     * @param array $context Current workflow context
     * @param array $config Config with activityId, requesterId, approverId, authorizationToken
     * @return array Output with sent boolean
     */
    public function notifyApprover(array $context, array $config): array
    {
        try {
            $activityId = (int)$this->resolveValue($config['activityId'], $context);
            $requesterId = (int)$this->resolveValue($config['requesterId'], $context);
            $approverId = (int)$this->resolveValue($config['approverId'], $context);
            $authorizationToken = $this->resolveValue($config['authorizationToken'], $context);

            $activitiesTable = TableRegistry::getTableLocator()->get('Activities.Activities');
            $membersTable = TableRegistry::getTableLocator()->get('Members');

            $activity = $activitiesTable->find()
                ->where(['id' => $activityId])
                ->select(['name'])
                ->first();
            $member = $membersTable->find()
                ->where(['id' => $requesterId])
                ->select(['sca_name'])
                ->first();
            $approver = $membersTable->find()
                ->where(['id' => $approverId])
                ->select(['sca_name', 'email_address'])
                ->first();

            if (!$activity || !$member || !$approver || empty($approver->email_address)) {
                Log::warning('Workflow NotifyApprover: missing data for notification'
                    . " activityId={$activityId} requesterId={$requesterId} approverId={$approverId}"
                    . ' activity=' . ($activity ? 'yes' : 'no')
                    . ' member=' . ($member ? 'yes' : 'no')
                    . ' approver=' . ($approver ? 'yes' : 'no')
                    . ' email=' . ($approver->email_address ?? 'null'));

                return ['sent' => false];
            }

            $authorizationResponseUrl = $authorizationToken
                ? Router::url([
                    'controller' => 'Approvals',
                    'action' => 'respond',
                    'plugin' => null,
                    '_full' => true,
                    $authorizationToken,
                ])
                : Router::url([
                    'controller' => 'Approvals',
                    'action' => 'approvals',
                    'plugin' => null,
                    '_full' => true,
                ]);

            $this->queueMail('KMP', 'sendFromTemplate', $approver->email_address, [
                '_templateId' => 'authorization-approval-request',
                'authorizationResponseUrl' => $authorizationResponseUrl,
                'memberScaName' => $member->sca_name,
                'approverScaName' => $approver->sca_name,
                'activityName' => $activity->name,
                'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature', '', null, true),
            ]);

            return ['sent' => true];
        } catch (Throwable $e) {
            Log::error('Workflow NotifyApprover failed: ' . $e->getMessage());

            return ['sent' => false];
        }
    }

    /**
     * Revoke an active authorization.
     *
     * @param array $context Current workflow context
     * @param array $config Config with authorizationId, revokerId, revokedReason
     * @return array Output with revoked boolean
     */
    public function revokeAuthorization(array $context, array $config): array
    {
        try {
            $authorizationId = (int)$this->resolveValue($config['authorizationId'], $context);
            $revokerId = (int)$this->resolveValue($config['revokerId'], $context);
            $revokedReason = (string)$this->resolveValue($config['revokedReason'] ?? 'Revoked via workflow', $context);

            $result = $this->authManager->revoke($authorizationId, $revokerId, $revokedReason);

            return ['revoked' => $result->success];
        } catch (Throwable $e) {
            Log::error('Workflow RevokeAuthorization failed: ' . $e->getMessage());

            return ['revoked' => false];
        }
    }

    /**
     * Retract (cancel) a pending authorization request.
     *
     * @param array $context Current workflow context
     * @param array $config Config with authorizationId, requesterId
     * @return array Output with retracted boolean
     */
    public function retractAuthorization(array $context, array $config): array
    {
        try {
            $authorizationId = (int)$this->resolveValue($config['authorizationId'], $context);
            $requesterId = (int)$this->resolveValue($config['requesterId'], $context);

            $result = $this->authManager->retract($authorizationId, $requesterId);
            if (!$result->success) {
                throw new RuntimeException($result->reason ?? 'Authorization retraction failed.');
            }

            return ['retracted' => true];
        } catch (Throwable $e) {
            Log::error('Workflow RetractAuthorization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if a member is eligible to renew an authorization for an activity.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId, activityId
     * @return array Output with eligible boolean and reason string
     */
    public function validateRenewalEligibility(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'], $context);
            $activityId = (int)$this->resolveValue($config['activityId'], $context);

            $authTable = TableRegistry::getTableLocator()->get('Activities.Authorizations');

            // Check for existing approved, non-expired authorization
            $existingAuth = $authTable->find()
                ->where([
                    'member_id' => $memberId,
                    'activity_id' => $activityId,
                    'status' => 'Approved',
                    'expires_on >' => DateTime::now(),
                ])
                ->first();

            if (!$existingAuth) {
                return ['eligible' => false, 'reason' => 'No active authorization exists to renew'];
            }

            // Check for existing pending request
            $pendingCount = $authTable->find()
                ->where([
                    'member_id' => $memberId,
                    'activity_id' => $activityId,
                    'status' => 'Pending',
                ])
                ->count();

            if ($pendingCount > 0) {
                return ['eligible' => false, 'reason' => 'A pending request already exists for this activity'];
            }

            return ['eligible' => true, 'reason' => 'Member is eligible for renewal'];
        } catch (Throwable $e) {
            Log::error('Workflow ValidateRenewalEligibility failed: ' . $e->getMessage());

            return ['eligible' => false, 'reason' => 'Error checking renewal eligibility'];
        }
    }

    /**
     * Resolve eligible approvers for an activity in a branch.
     *
     * @param array $context Current workflow context
     * @param array $config Config with activityId, branchId, excludeMemberIds (optional)
     * @return array Output with approvers array
     */
    public function resolveApprovers(array $context, array $config): array
    {
        try {
            $activityId = (int)$this->resolveValue($config['activityId'], $context);
            $branchId = (int)$this->resolveValue($config['branchId'], $context);
            $excludeMemberIds = $this->resolveValue($config['excludeMemberIds'] ?? [], $context);
            if (!is_array($excludeMemberIds)) {
                $excludeMemberIds = [];
            }

            $activitiesTable = TableRegistry::getTableLocator()->get('Activities.Activities');
            $activity = $activitiesTable->get($activityId);

            $query = $activity->getApproversQuery($branchId);

            if (!empty($excludeMemberIds)) {
                $query->where(['Members.id NOT IN' => $excludeMemberIds]);
            }

            $query->select(['Members.id', 'Members.sca_name']);

            $approvers = [];
            foreach ($query->all() as $member) {
                $approvers[] = [
                    'id' => $member->id,
                    'sca_name' => $member->sca_name,
                ];
            }

            return ['approvers' => $approvers];
        } catch (Throwable $e) {
            Log::error('Workflow ResolveApprovers failed: ' . $e->getMessage());

            return ['approvers' => []];
        }
    }

    /**
     * Send status update email to requesting member.
     *
     * @param array $context Current workflow context
     * @param array $config Config with activityId, requesterId, approverId, status, nextApproverId
     * @return array Output with sent boolean
     */
    public function notifyRequester(array $context, array $config): array
    {
        try {
            $activityId = (int)$this->resolveValue($config['activityId'], $context);
            $requesterId = (int)$this->resolveValue($config['requesterId'], $context);
            $approverId = (int)$this->resolveValue($config['approverId'], $context);
            $status = (string)$this->resolveValue($config['status'], $context);
            $nextApproverId = $this->resolveValue($config['nextApproverId'] ?? null, $context);

            $activitiesTable = TableRegistry::getTableLocator()->get('Activities.Activities');
            $membersTable = TableRegistry::getTableLocator()->get('Members');

            $activity = $activitiesTable->find()
                ->where(['id' => $activityId])
                ->select(['name'])
                ->first();
            $member = $membersTable->find()
                ->where(['id' => $requesterId])
                ->select(['sca_name', 'email_address'])
                ->first();
            $approver = $membersTable->find()
                ->where(['id' => $approverId])
                ->select(['sca_name'])
                ->first();

            if (!$activity || !$member || !$approver || empty($member->email_address)) {
                Log::warning('Workflow NotifyRequester: missing data for notification');

                return ['sent' => false];
            }

            $nextApproverScaName = '';
            if ($nextApproverId) {
                $nextApprover = $membersTable->find()
                    ->where(['id' => (int)$nextApproverId])
                    ->select(['sca_name'])
                    ->first();
                $nextApproverScaName = $nextApprover ? $nextApprover->sca_name : '';
            }

            $memberCardUrl = $this->buildMemberCardUrl($requesterId);

            $this->queueMail('KMP', 'sendFromTemplate', $member->email_address, [
                '_templateId' => 'authorization-request-update',
                'status' => $status,
                'memberScaName' => $member->sca_name,
                'memberCardUrl' => $memberCardUrl,
                'approverScaName' => $approver->sca_name,
                'nextApproverScaName' => $nextApproverScaName,
                'activityName' => $activity->name,
                'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature', '', null, true),
            ]);

            return ['sent' => true];
        } catch (Throwable $e) {
            Log::error('Workflow NotifyRequester failed: ' . $e->getMessage());

            return ['sent' => false];
        }
    }

    /**
     * Build a member card URL.
     *
     * @param int $memberId Member ID
     * @return string URL to member card
     */
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
}
