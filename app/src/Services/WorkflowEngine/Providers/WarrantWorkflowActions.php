<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine\Providers;

use App\KMP\StaticHelpers;
use App\KMP\TimezoneHelper;
use App\Mailer\QueuedMailerAwareTrait;
use App\Model\Entity\Warrant;
use App\Model\Entity\WarrantRoster;
use App\Services\WarrantManager\WarrantManagerInterface;
use App\Services\WarrantManager\WarrantRequest;
use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use RuntimeException;

/**
 * Workflow action implementations for warrant operations.
 *
 * Delegates roster creation, approval, and decline to WarrantManagerInterface
 * to avoid duplicating warrant lifecycle logic.
 */
class WarrantWorkflowActions
{
    use QueuedMailerAwareTrait;
    use WorkflowContextAwareTrait;

    private WarrantManagerInterface $warrantManager;

    public function __construct(WarrantManagerInterface $warrantManager)
    {
        $this->warrantManager = $warrantManager;
    }

    /**
     * Create a warrant roster with a single warrant request for approval.
     *
     * Delegates to WarrantManagerInterface::request().
     *
     * @param array $context Current workflow context
     * @param array $config Config with name, description, entityType, entityId, memberId, startOn, expiresOn, memberRoleId
     * @return array Output with rosterId
     */
    public function createWarrantRoster(array $context, array $config): array
    {
        try {
            $name = $this->resolveValue($config['name'], $context);
            $desc = $this->resolveValue($config['description'] ?? '', $context);
            $entityType = $this->resolveValue($config['entityType'], $context);
            $entityId = (int)$this->resolveValue($config['entityId'], $context);
            $memberId = (int)$this->resolveValue($config['memberId'], $context);

            $startOnRaw = $this->resolveValue($config['startOn'], $context);
            $startOn = $startOnRaw instanceof DateTime ? $startOnRaw : new DateTime($startOnRaw);

            $expiresOnRaw = $this->resolveValue($config['expiresOn'] ?? null, $context);
            $expiresOn = null;
            if ($expiresOnRaw !== null) {
                $expiresOn = $expiresOnRaw instanceof DateTime ? $expiresOnRaw : new DateTime($expiresOnRaw);
            }

            $memberRoleId = $this->resolveValue($config['memberRoleId'] ?? null, $context);

            $requestedBy = $context['triggeredBy'] ?? null;

            $warrantRequest = new WarrantRequest(
                $name,
                $entityType,
                $entityId,
                $requestedBy ?? 0,
                $memberId,
                $startOn,
                $expiresOn,
                $memberRoleId ? (int)$memberRoleId : null,
            );

            $result = $this->warrantManager->request($name, (string)$desc, [$warrantRequest], $requestedBy);

            return ['rosterId' => $result->success ? $result->data : null];
        } catch (\Throwable $e) {
            Log::error('Workflow CreateWarrantRoster failed: ' . $e->getMessage());

            return ['rosterId' => null];
        }
    }

    /**
     * Activate all pending warrants in an approved roster.
     *
     * Syncs workflow approval data to roster tables, then delegates
     * activation to WarrantManagerInterface::activateApprovedRoster().
     *
     * @param array $context Current workflow context
     * @param array $config Config with rosterId
     * @return array Output with activated boolean and count
     */
    public function activateWarrants(array $context, array $config): array
    {
        try {
            $rosterId = (int)$this->resolveValue($config['rosterId'], $context);
            // Derive approver from workflow context — whoever responded to the approval gate
            $approverId = (int)($context['resumeData']['approverId'] ?? $context['triggeredBy'] ?? 0);

            $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
            $rosterTable = TableRegistry::getTableLocator()->get('WarrantRosters');
            $roster = $rosterTable->get($rosterId);

            // If roster was already approved (e.g. via direct WarrantManager path),
            // check if warrants are already Current and treat as success
            if ($roster->status === WarrantRoster::STATUS_APPROVED) {
                $currentCount = $warrantTable->find()
                    ->where([
                        'warrant_roster_id' => $rosterId,
                        'status' => Warrant::CURRENT_STATUS,
                    ])
                    ->count();

                return ['activated' => true, 'count' => $currentCount];
            }

            // Sync workflow approval data to roster tables
            $instanceId = $context['instanceId'] ?? null;
            if ($instanceId) {
                $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
                $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');

                $approval = $approvalsTable->find()
                    ->where([
                        'workflow_instance_id' => $instanceId,
                        'status' => 'approved',
                    ])
                    ->first();

                if ($approval) {
                    // Sync approvals_required from workflow config
                    if ($roster->approvals_required !== $approval->required_count) {
                        $roster->approvals_required = $approval->required_count;
                    }

                    // Sync each approval response to roster tables
                    $responses = $responsesTable->find()
                        ->where([
                            'workflow_approval_id' => $approval->id,
                            'decision' => 'approve',
                        ])
                        ->all();

                    foreach ($responses as $response) {
                        $this->warrantManager->syncWorkflowApprovalToRoster(
                            $rosterId,
                            $response->member_id,
                            $response->comment,
                            $response->responded_at,
                        );
                    }

                    // Set roster status to APPROVED and save
                    $roster->status = WarrantRoster::STATUS_APPROVED;
                    $rosterTable->save($roster);
                }
            }

            // Activate warrants via extracted method (no approval bookkeeping)
            $result = $this->warrantManager->activateApprovedRoster($rosterId, $approverId);

            if (!$result->success) {
                Log::warning('Workflow ActivateWarrants: activation returned: ' . $result->reason);

                return ['activated' => false, 'count' => 0];
            }

            $count = $warrantTable->find()
                ->where([
                    'warrant_roster_id' => $rosterId,
                    'status' => Warrant::CURRENT_STATUS,
                ])
                ->count();

            return ['activated' => true, 'count' => $count];
        } catch (\Throwable $e) {
            Log::error('Workflow ActivateWarrants failed: ' . $e->getMessage());

            return ['activated' => false, 'count' => 0];
        }
    }

    /**
     * Create and immediately activate a warrant without a roster.
     *
     * @param array $context Current workflow context
     * @param array $config Config with name, memberId, entityType, entityId, startOn, expiresOn, memberRoleId
     * @return array Output with warrantId
     */
    public function createDirectWarrant(array $context, array $config): array
    {
        try {
            $warrantTable = TableRegistry::getTableLocator()->get('Warrants');

            $startOnRaw = $this->resolveValue($config['startOn'], $context);
            $startOn = $startOnRaw instanceof DateTime ? $startOnRaw : new DateTime($startOnRaw);

            $expiresOnRaw = $this->resolveValue($config['expiresOn'] ?? null, $context);
            $expiresOn = null;
            if ($expiresOnRaw !== null) {
                $expiresOn = $expiresOnRaw instanceof DateTime ? $expiresOnRaw : new DateTime($expiresOnRaw);
            }

            $memberRoleId = $this->resolveValue($config['memberRoleId'] ?? null, $context);

            $warrant = $warrantTable->newEmptyEntity();
            $warrant->name = $this->resolveValue($config['name'], $context);
            $warrant->entity_type = $this->resolveValue($config['entityType'], $context);
            $warrant->entity_id = (int)$this->resolveValue($config['entityId'], $context);
            $warrant->requester_id = $context['triggeredBy'] ?? null;
            $warrant->member_id = (int)$this->resolveValue($config['memberId'], $context);
            $warrant->member_role_id = $memberRoleId ? (int)$memberRoleId : null;
            $warrant->start_on = $startOn;
            $warrant->expires_on = $expiresOn;
            $warrant->status = Warrant::CURRENT_STATUS;
            $warrant->approved_date = new DateTime();

            if (!$warrantTable->save($warrant)) {
                Log::error('Workflow CreateDirectWarrant: failed to save warrant');

                return ['warrantId' => null];
            }

            return ['warrantId' => $warrant->id];
        } catch (\Throwable $e) {
            Log::error('Workflow CreateDirectWarrant failed: ' . $e->getMessage());

            return ['warrantId' => null];
        }
    }

    /**
     * Decline a warrant roster and cancel all its pending warrants.
     *
     * Syncs any approve responses that occurred before the decline,
     * then delegates to WarrantManagerInterface::decline().
     *
     * @param array $context Current workflow context
     * @param array $config Config with rosterId, reason, rejecterId
     * @return array Output with declined boolean
     */
    public function declineRoster(array $context, array $config): array
    {
        try {
            $rosterId = (int)$this->resolveValue($config['rosterId'], $context);
            $reason = $this->resolveValue($config['reason'] ?? '', $context);
            if (empty($reason)) {
                $reason = $context['resumeData']['comment'] ?? 'Declined via workflow';
            }
            $rejecterId = $this->resolveValue($config['rejecterId'] ?? null, $context);
            if (!$rejecterId) {
                $rejecterId = $context['resumeData']['approverId'] ?? $context['triggeredBy'] ?? null;
            }
            $rejecterId = (int)$rejecterId;

            // Sync any approve responses that happened before the decline
            $instanceId = $context['instanceId'] ?? null;
            if ($instanceId) {
                $approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
                $responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');

                $approval = $approvalsTable->find()
                    ->where([
                        'workflow_instance_id' => $instanceId,
                        'status IN' => ['rejected', 'approved'],
                    ])
                    ->first();

                if ($approval) {
                    $responses = $responsesTable->find()
                        ->where(['workflow_approval_id' => $approval->id])
                        ->all();

                    foreach ($responses as $response) {
                        if ($response->decision === 'approve') {
                            $this->warrantManager->syncWorkflowApprovalToRoster(
                                $rosterId,
                                $response->member_id,
                                $response->comment,
                                $response->responded_at,
                            );
                        }
                    }
                }
            }

            $result = $this->warrantManager->decline($rosterId, $rejecterId, $reason);

            if (!$result->success) {
                // The decline-roster node has a single output port, so the engine would
                // advance to end-declined regardless of this return value. Throw so the
                // engine marks the instance failed instead of silently completing.
                throw new RuntimeException(
                    'Failed to decline warrant roster ' . $rosterId . ': ' . ($result->reason ?? 'unknown error'),
                );
            }

            return ['declined' => true];
        } catch (\Throwable $e) {
            Log::error('Workflow DeclineRoster failed: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Cancel/revoke a specific warrant by ID.
     *
     * Delegates to WarrantManagerInterface::cancel().
     *
     * @param array $context Current workflow context
     * @param array $config Config with warrantId, reason, revokerId, expiresOn
     * @return array Output with revoked boolean
     */
    public function revokeWarrant(array $context, array $config): array
    {
        try {
            $warrantId = (int)$this->resolveValue($config['warrantId'], $context);
            $reason = $this->resolveValue($config['reason'] ?? 'Revoked via workflow', $context);
            $revokerId = $this->resolveValue($config['revokerId'] ?? null, $context);
            if (!$revokerId) {
                $revokerId = $context['triggeredBy'] ?? 0;
            }
            $revokerId = (int)$revokerId;

            $expiresOnRaw = $this->resolveValue($config['expiresOn'] ?? null, $context);
            $expiresOn = $expiresOnRaw instanceof DateTime
                ? $expiresOnRaw
                : new DateTime($expiresOnRaw ?? 'now');

            $result = $this->warrantManager->cancel($warrantId, (string)$reason, $revokerId, $expiresOn);

            return ['revoked' => $result->success];
        } catch (\Throwable $e) {
            Log::error('Workflow RevokeWarrant failed: ' . $e->getMessage());

            return ['revoked' => false];
        }
    }

    /**
     * Cancel all warrants for a specific entity.
     *
     * Delegates to WarrantManagerInterface::cancelByEntity().
     *
     * @param array $context Current workflow context
     * @param array $config Config with entityType, entityId, reason, revokerId, expiresOn
     * @return array Output with cancelled boolean
     */
    public function cancelByEntity(array $context, array $config): array
    {
        try {
            $entityType = $this->resolveValue($config['entityType'], $context);
            $entityId = (int)$this->resolveValue($config['entityId'], $context);
            $reason = $this->resolveValue($config['reason'] ?? 'Cancelled via workflow', $context);
            $revokerId = $this->resolveValue($config['revokerId'] ?? null, $context);
            if (!$revokerId) {
                $revokerId = $context['triggeredBy'] ?? 0;
            }
            $revokerId = (int)$revokerId;

            $expiresOnRaw = $this->resolveValue($config['expiresOn'] ?? null, $context);
            $expiresOn = $expiresOnRaw instanceof DateTime
                ? $expiresOnRaw
                : new DateTime($expiresOnRaw ?? 'now');

            $result = $this->warrantManager->cancelByEntity(
                (string)$entityType,
                $entityId,
                (string)$reason,
                $revokerId,
                $expiresOn,
            );

            return ['cancelled' => $result->success];
        } catch (\Throwable $e) {
            Log::error('Workflow CancelByEntity failed: ' . $e->getMessage());

            return ['cancelled' => false];
        }
    }

    /**
     * Decline a single warrant (not the entire roster).
     *
     * Delegates to WarrantManagerInterface::declineSingleWarrant().
     *
     * @param array $context Current workflow context
     * @param array $config Config with warrantId, reason, rejecterId
     * @return array Output with declined boolean
     */
    public function declineSingleWarrant(array $context, array $config): array
    {
        try {
            $warrantId = (int)$this->resolveValue($config['warrantId'], $context);
            $reason = $this->resolveValue($config['reason'] ?? 'Declined via workflow', $context);
            $rejecterId = $this->resolveValue($config['rejecterId'] ?? null, $context);
            if (!$rejecterId) {
                $rejecterId = $context['triggeredBy'] ?? 0;
            }
            $rejecterId = (int)$rejecterId;

            $result = $this->warrantManager->declineSingleWarrant($warrantId, (string)$reason, $rejecterId);

            return ['declined' => $result->success];
        } catch (\Throwable $e) {
            Log::error('Workflow DeclineSingleWarrant failed: ' . $e->getMessage());

            return ['declined' => false];
        }
    }

    /**
     * Check if a member is eligible to receive warrants.
     *
     * Verifies the member.warrantable flag and that membership has not expired.
     *
     * @param array $context Current workflow context
     * @param array $config Config with memberId
     * @return array Output with warrantable boolean and reason
     */
    public function validateWarrantability(array $context, array $config): array
    {
        try {
            $memberId = (int)$this->resolveValue($config['memberId'], $context);

            $memberTable = TableRegistry::getTableLocator()->get('Members');
            $member = $memberTable->get($memberId);

            if (!$member->warrantable) {
                return ['warrantable' => false, 'reason' => 'Member is not warrantable'];
            }

            if (
                $member->membership_expires_on !== null
                && $member->membership_expires_on < DateTime::now()
            ) {
                return ['warrantable' => false, 'reason' => 'Membership has expired'];
            }

            return ['warrantable' => true, 'reason' => null];
        } catch (\Throwable $e) {
            Log::error('Workflow ValidateWarrantability failed: ' . $e->getMessage());

            return ['warrantable' => false, 'reason' => $e->getMessage()];
        }
    }

    /**
     * Calculate warrant start/end dates via WarrantManager.
     *
     * Delegates to WarrantManagerInterface::getWarrantPeriod().
     *
     * @param array $context Current workflow context
     * @param array $config Config with startOn, endOn (optional)
     * @return array Output with startDate, endDate, periodId
     */
    public function getWarrantPeriod(array $context, array $config): array
    {
        try {
            $startOnRaw = $this->resolveValue($config['startOn'], $context);
            $startOn = $startOnRaw instanceof DateTime ? $startOnRaw : new DateTime($startOnRaw);

            $endOnRaw = $this->resolveValue($config['endOn'] ?? null, $context);
            $endOn = null;
            if ($endOnRaw !== null) {
                $endOn = $endOnRaw instanceof DateTime ? $endOnRaw : new DateTime($endOnRaw);
            }

            $period = $this->warrantManager->getWarrantPeriod($startOn, $endOn);

            if ($period === null) {
                return ['startDate' => null, 'endDate' => null, 'periodId' => null];
            }

            return [
                'startDate' => $period->start_date ? $period->start_date->format('Y-m-d') : null,
                'endDate' => $period->end_date ? $period->end_date->format('Y-m-d') : null,
                'periodId' => $period->id,
            ];
        } catch (\Throwable $e) {
            Log::error('Workflow GetWarrantPeriod failed: ' . $e->getMessage());

            return ['startDate' => null, 'endDate' => null, 'periodId' => null];
        }
    }

    /**
     * Send warrant-issued notification emails to each member in the roster.
     *
     * @param array $context Current workflow context
     * @param array $config Config with rosterId
     * @return array Output with emailsSent count
     */
    public function notifyWarrantIssued(array $context, array $config): array
    {
        try {
            $rosterId = (int)$this->resolveValue($config['rosterId'], $context);

            $warrantTable = TableRegistry::getTableLocator()->get('Warrants');
            $warrants = $warrantTable->find()
                ->contain(['Members'])
                ->where([
                    'Warrants.warrant_roster_id' => $rosterId,
                    'Warrants.status' => Warrant::CURRENT_STATUS,
                ])
                ->all();

            $sent = 0;

            foreach ($warrants as $warrant) {
                if (empty($warrant->member->email_address)) {
                    continue;
                }

                $vars = [
                    'memberScaName' => $warrant->member->sca_name,
                    'warrantName' => $warrant->name,
                    'warrantStart' => TimezoneHelper::formatDate($warrant->start_on),
                    'warrantExpires' => TimezoneHelper::formatDate($warrant->expires_on),
                    'siteAdminSignature' => StaticHelpers::getAppSetting('Email.SiteAdminSignature', '', null, true),
                ];

                try {
                    $this->queueMail('KMP', 'sendFromTemplate', $warrant->member->email_address, [
                        '_templateId' => 'warrant-issued',
                        ...$vars,
                    ]);
                    $sent++;
                } catch (\Throwable $mailErr) {
                    Log::error('Workflow NotifyWarrantIssued mail send failed: ' . $mailErr->getMessage());
                }
            }

            return ['emailsSent' => $sent];
        } catch (\Throwable $e) {
            Log::error('Workflow NotifyWarrantIssued failed: ' . $e->getMessage());

            return ['emailsSent' => 0];
        }
    }
}
