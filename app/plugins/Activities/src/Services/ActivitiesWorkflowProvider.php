<?php
declare(strict_types=1);

namespace Activities\Services;

use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowApproverResolverRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;

/**
 * Registers activity authorization workflow triggers and actions with the workflow registries.
 */
class ActivitiesWorkflowProvider
{
    private const SOURCE = 'Activities';

    /**
     * Register all activity workflow components.
     *
     * @return void
     */
    public static function register(): void
    {
        self::registerTriggers();
        self::registerActions();
        self::registerConditions();
        self::registerResolvers();
    }

    /**
     * @return void
     */
    private static function registerTriggers(): void
    {
        WorkflowTriggerRegistry::register(self::SOURCE, [
            [
                'event' => 'Activities.AuthorizationRequested',
                'label' => 'Authorization Requested',
                'description' => 'When a new authorization request is submitted',
                'payloadSchema' => [
                    'authorizationId' => ['type' => 'integer', 'label' => 'Authorization ID'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'activityId' => ['type' => 'integer', 'label' => 'Activity ID'],
                    'activityName' => ['type' => 'string', 'label' => 'Activity Name'],
                    'approverId' => ['type' => 'integer', 'label' => 'Approver ID'],
                    'isRenewal' => ['type' => 'boolean', 'label' => 'Is Renewal'],
                    'requiredApprovals' => ['type' => 'integer', 'label' => 'Required Approvals'],
                    'approvalPermission' => ['type' => 'string', 'label' => 'Approval Permission'],
                ],
            ],
            [
                'event' => 'Activities.AuthorizationRevoked',
                'label' => 'Authorization Revoked',
                'description' => 'When an active authorization is revoked',
                'payloadSchema' => [
                    'authorizationId' => ['type' => 'integer', 'label' => 'Authorization ID'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'activityId' => ['type' => 'integer', 'label' => 'Activity ID'],
                    'revokerId' => ['type' => 'integer', 'label' => 'Revoker ID'],
                    'revokedReason' => ['type' => 'string', 'label' => 'Revoked Reason'],
                ],
            ],
            [
                'event' => 'Activities.AuthorizationRetracted',
                'label' => 'Authorization Retracted',
                'description' => 'When a requester cancels their pending request',
                'payloadSchema' => [
                    'authorizationId' => ['type' => 'integer', 'label' => 'Authorization ID'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'activityId' => ['type' => 'integer', 'label' => 'Activity ID'],
                ],
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerResolvers(): void
    {
        WorkflowApproverResolverRegistry::register(self::SOURCE, [
            [
                'resolver' => 'Activities.AuthorizationApproverResolver',
                'label' => 'Authorization Approver Resolver',
                'description' => 'Resolves eligible approvers based on activity permission rules',
                'serviceClass' => AuthorizationApproverResolver::class,
                'serviceMethod' => 'getEligibleApproverIds',
                'configSchema' => [
                    'activity_id' => [
                        'type' => 'string',
                        'label' => 'Activity ID',
                        'required' => true,
                        'description' => 'The activity to resolve approvers for (supports context paths)',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerActions(): void
    {
        $actionsClass = ActivitiesWorkflowActions::class;

        WorkflowActionRegistry::register(self::SOURCE, [
            [
                'action' => 'Activities.CreateAuthorizationRequest',
                'label' => 'Create Authorization Request',
                'description' => 'Create authorization + first approval via AuthorizationManagerInterface::request()',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                    'activityId' => ['type' => 'integer', 'label' => 'Activity ID', 'required' => true],
                    'approverId' => ['type' => 'integer', 'label' => 'Approver ID', 'required' => true],
                    'isRenewal' => ['type' => 'boolean', 'label' => 'Is Renewal', 'default' => false],
                ],
                'outputSchema' => [
                    'authorizationId' => ['type' => 'integer', 'label' => 'Authorization ID'],
                    'authorizationApprovalId' => ['type' => 'integer', 'label' => 'Authorization Approval ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'createAuthorizationRequest',
                'isAsync' => false,
            ],
            [
                'action' => 'Activities.ActivateAuthorization',
                'label' => 'Activate Authorization',
                'description' => 'Final activation: set status APPROVED, start ActiveWindow, assign role',
                'inputSchema' => [
                    'authorizationId' => ['type' => 'integer', 'label' => 'Authorization ID', 'required' => true],
                    'approverId' => ['type' => 'integer', 'label' => 'Approver ID', 'required' => true],
                ],
                'outputSchema' => [
                    'activated' => ['type' => 'boolean', 'label' => 'Activation Successful'],
                    'memberRoleId' => ['type' => 'integer', 'label' => 'Member Role ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'activateAuthorization',
                'isAsync' => false,
            ],
            [
                'action' => 'Activities.HandleDenial',
                'label' => 'Handle Authorization Denial',
                'description' => 'Process denial of an authorization request',
                'inputSchema' => [
                    'authorizationId' => ['type' => 'integer', 'label' => 'Authorization ID', 'required' => true],
                    'approverId' => ['type' => 'integer', 'label' => 'Approver ID', 'required' => true],
                    'denyReason' => ['type' => 'string', 'label' => 'Deny Reason', 'required' => true],
                ],
                'outputSchema' => [
                    'denied' => ['type' => 'boolean', 'label' => 'Denial Successful'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'handleDenial',
                'isAsync' => false,
            ],
            [
                'action' => 'Activities.NotifyApprover',
                'label' => 'Notify Approver',
                'description' => 'Send approval request email to designated approver',
                'inputSchema' => [
                    'activityId' => ['type' => 'integer', 'label' => 'Activity ID', 'required' => true],
                    'requesterId' => ['type' => 'integer', 'label' => 'Requester ID', 'required' => true],
                    'approverId' => ['type' => 'integer', 'label' => 'Approver ID', 'required' => true],
                    'authorizationToken' => ['type' => 'string', 'label' => 'Authorization Token', 'required' => true],
                ],
                'outputSchema' => [
                    'sent' => ['type' => 'boolean', 'label' => 'Email Sent'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'notifyApprover',
                'isAsync' => false,
            ],
            [
                'action' => 'Activities.NotifyRequester',
                'label' => 'Notify Requester',
                'description' => 'Send status update email to requesting member',
                'inputSchema' => [
                    'activityId' => ['type' => 'integer', 'label' => 'Activity ID', 'required' => true],
                    'requesterId' => ['type' => 'integer', 'label' => 'Requester ID', 'required' => true],
                    'approverId' => ['type' => 'integer', 'label' => 'Approver ID', 'required' => true],
                    'status' => ['type' => 'string', 'label' => 'Status', 'required' => true],
                    'nextApproverId' => ['type' => 'integer', 'label' => 'Next Approver ID'],
                ],
                'outputSchema' => [
                    'sent' => ['type' => 'boolean', 'label' => 'Email Sent'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'notifyRequester',
                'isAsync' => false,
            ],
            [
                'action' => 'Activities.RevokeAuthorization',
                'label' => 'Revoke Authorization',
                'description' => 'Revoke an active authorization, stopping ActiveWindow and updating status',
                'inputSchema' => [
                    'authorizationId' => ['type' => 'integer', 'label' => 'Authorization ID', 'required' => true],
                    'revokerId' => ['type' => 'integer', 'label' => 'Revoker ID', 'required' => true],
                    'revokedReason' => ['type' => 'string', 'label' => 'Revoked Reason', 'required' => true],
                ],
                'outputSchema' => [
                    'revoked' => ['type' => 'boolean', 'label' => 'Revocation Successful'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'revokeAuthorization',
                'isAsync' => false,
            ],
            [
                'action' => 'Activities.RetractAuthorization',
                'label' => 'Retract Authorization',
                'description' => 'Member cancels own pending authorization request',
                'inputSchema' => [
                    'authorizationId' => ['type' => 'integer', 'label' => 'Authorization ID', 'required' => true],
                    'requesterId' => ['type' => 'integer', 'label' => 'Requester ID', 'required' => true],
                ],
                'outputSchema' => [
                    'retracted' => ['type' => 'boolean', 'label' => 'Retraction Successful'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'retractAuthorization',
                'isAsync' => false,
            ],
            [
                'action' => 'Activities.ValidateRenewalEligibility',
                'label' => 'Validate Renewal Eligibility',
                'description' => 'Check if member is eligible to renew an authorization',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                    'activityId' => ['type' => 'integer', 'label' => 'Activity ID', 'required' => true],
                ],
                'outputSchema' => [
                    'eligible' => ['type' => 'boolean', 'label' => 'Eligible for Renewal'],
                    'reason' => ['type' => 'string', 'label' => 'Reason'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'validateRenewalEligibility',
                'isAsync' => false,
            ],
            [
                'action' => 'Activities.ResolveApprovers',
                'label' => 'Resolve Approvers',
                'description' => 'Find eligible approvers for an activity in a branch',
                'inputSchema' => [
                    'activityId' => ['type' => 'integer', 'label' => 'Activity ID', 'required' => true],
                    'branchId' => ['type' => 'integer', 'label' => 'Branch ID', 'required' => true],
                    'excludeMemberIds' => ['type' => 'array', 'label' => 'Exclude Member IDs'],
                ],
                'outputSchema' => [
                    'approvers' => ['type' => 'array', 'label' => 'Eligible Approvers'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'resolveApprovers',
                'isAsync' => false,
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerConditions(): void
    {
        $conditionsClass = ActivitiesWorkflowConditions::class;

        WorkflowConditionRegistry::register(self::SOURCE, [
            [
                'condition' => 'Activities.IsRenewalEligible',
                'label' => 'Is Renewal Eligible',
                'description' => 'Check if member has an active authorization eligible for renewal',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                    'activityId' => ['type' => 'integer', 'label' => 'Activity ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isRenewalEligible',
            ],
            [
                'condition' => 'Activities.HasRequiredApprovals',
                'label' => 'Has Required Approvals',
                'description' => 'Check if authorization has met the required approval count',
                'inputSchema' => [
                    'authorizationId' => ['type' => 'integer', 'label' => 'Authorization ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'hasRequiredApprovals',
            ],
            [
                'condition' => 'Activities.MemberMeetsAgeRequirement',
                'label' => 'Member Meets Age Requirement',
                'description' => 'Check if member meets the minimum and maximum age requirements for an activity',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                    'activityId' => ['type' => 'integer', 'label' => 'Activity ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'memberMeetsAgeRequirement',
            ],
        ]);
    }
}
