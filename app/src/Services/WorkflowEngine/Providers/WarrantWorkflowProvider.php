<?php

declare(strict_types=1);

namespace App\Services\WorkflowEngine\Providers;

use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;

/**
 * Registers warrant workflow triggers and actions with the workflow registries.
 */
class WarrantWorkflowProvider
{
    private const SOURCE = 'Warrants';

    /**
     * Register all warrant workflow components.
     *
     * @return void
     */
    public static function register(): void
    {
        self::registerTriggers();
        self::registerActions();
        self::registerConditions();
    }

    /**
     * @return void
     */
    private static function registerTriggers(): void
    {
        WorkflowTriggerRegistry::register(self::SOURCE, [
            [
                'event' => 'Warrants.RosterCreated',
                'label' => 'Warrant Roster Created',
                'description' => 'When a new warrant roster is submitted for approval',
                'payloadSchema' => [
                    'rosterId' => ['type' => 'integer', 'label' => 'Roster ID'],
                    'rosterName' => ['type' => 'string', 'label' => 'Roster Name'],
                    'approvalsRequired' => ['type' => 'integer', 'label' => 'Approvals Required'],
                ],
            ],
            [
                'event' => 'Warrants.Approved',
                'label' => 'Warrant Approved',
                'description' => 'When a warrant roster receives all required approvals',
                'payloadSchema' => [
                    'rosterId' => ['type' => 'integer', 'label' => 'Roster ID'],
                ],
            ],
            [
                'event' => 'Warrants.Declined',
                'label' => 'Warrant Declined',
                'description' => 'When a warrant roster is declined',
                'payloadSchema' => [
                    'rosterId' => ['type' => 'integer', 'label' => 'Roster ID'],
                    'reason' => ['type' => 'string', 'label' => 'Decline Reason'],
                ],
            ],
            [
                'event' => 'Warrants.WarrantRevoked',
                'label' => 'Warrant Revoked',
                'description' => 'When a specific warrant is revoked/cancelled',
                'payloadSchema' => [
                    'warrantId' => ['type' => 'integer', 'label' => 'Warrant ID'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'reason' => ['type' => 'string', 'label' => 'Revocation Reason'],
                ],
            ],
            [
                'event' => 'Warrants.WarrantExpired',
                'label' => 'Warrant Expired',
                'description' => 'When a warrant reaches its expiration date',
                'payloadSchema' => [
                    'warrantId' => ['type' => 'integer', 'label' => 'Warrant ID'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                ],
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerActions(): void
    {
        $actionsClass = WarrantWorkflowActions::class;

        WorkflowActionRegistry::register(self::SOURCE, [
            [
                'action' => 'Warrants.CreateWarrantRoster',
                'label' => 'Create Warrant Roster',
                'description' => 'Create a warrant roster for approval',
                'inputSchema' => [
                    'name' => ['type' => 'string', 'label' => 'Roster Name', 'required' => true, 'description' => 'Display name for the warrant roster'],
                    'description' => ['type' => 'string', 'label' => 'Description', 'default' => ''],
                    'entityType' => ['type' => 'string', 'label' => 'Entity Type', 'required' => true, 'description' => 'CakePHP table alias for the warranted entity'],
                    'entityId' => ['type' => 'integer', 'label' => 'Entity ID', 'required' => true, 'description' => 'Primary key of the warranted entity'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true, 'description' => 'The member receiving the warrant'],
                    'startOn' => ['type' => 'datetime', 'label' => 'Start Date', 'required' => true],
                    'expiresOn' => ['type' => 'datetime', 'label' => 'Expires On'],
                    'memberRoleId' => ['type' => 'integer', 'label' => 'Member Role ID'],
                ],
                'outputSchema' => [
                    'rosterId' => ['type' => 'integer', 'label' => 'Roster ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'createWarrantRoster',
                'isAsync' => false,
            ],
            [
                'action' => 'Warrants.ActivateWarrants',
                'label' => 'Activate Warrants',
                'description' => 'Activate all warrants in an approved roster',
                'inputSchema' => [
                    'rosterId' => ['type' => 'integer', 'label' => 'Roster ID', 'required' => true, 'description' => 'The ID of the warrant roster to activate'],
                ],
                'outputSchema' => [
                    'activated' => ['type' => 'boolean', 'label' => 'Activation Successful'],
                    'count' => ['type' => 'integer', 'label' => 'Warrants Activated'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'activateWarrants',
                'isAsync' => false,
            ],
            [
                'action' => 'Warrants.CreateDirectWarrant',
                'label' => 'Create Direct Warrant',
                'description' => 'Create and immediately activate a warrant (no roster)',
                'inputSchema' => [
                    'name' => ['type' => 'string', 'label' => 'Warrant Name', 'required' => true],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                    'entityType' => ['type' => 'string', 'label' => 'Entity Type', 'required' => true],
                    'entityId' => ['type' => 'integer', 'label' => 'Entity ID', 'required' => true],
                    'startOn' => ['type' => 'datetime', 'label' => 'Start Date', 'required' => true],
                    'expiresOn' => ['type' => 'datetime', 'label' => 'Expires On'],
                    'memberRoleId' => ['type' => 'integer', 'label' => 'Member Role ID'],
                ],
                'outputSchema' => [
                    'warrantId' => ['type' => 'integer', 'label' => 'Warrant ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'createDirectWarrant',
                'isAsync' => false,
            ],
            [
                'action' => 'Warrants.DeclineRoster',
                'label' => 'Decline Warrant Roster',
                'description' => 'Decline a warrant roster and cancel its warrants',
                'inputSchema' => [
                    'rosterId' => ['type' => 'integer', 'label' => 'Roster ID', 'required' => true, 'description' => 'The ID of the warrant roster to decline'],
                    'reason' => ['type' => 'string', 'label' => 'Decline Reason', 'required' => true, 'description' => 'Reason for declining the warrant roster'],
                    'rejecterId' => ['type' => 'integer', 'label' => 'Rejecter ID', 'required' => true, 'description' => 'Member ID of the person who declined (use $.resumeData.approverId)'],
                ],
                'outputSchema' => [
                    'declined' => ['type' => 'boolean', 'label' => 'Decline Successful'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'declineRoster',
                'isAsync' => false,
            ],
            [
                'action' => 'Warrants.NotifyWarrantIssued',
                'label' => 'Notify Warrant Issued',
                'description' => 'Send warrant-issued notification emails to each member in the roster',
                'inputSchema' => [
                    'rosterId' => ['type' => 'integer', 'label' => 'Roster ID', 'required' => true, 'description' => 'The ID of the warrant roster to notify about'],
                ],
                'outputSchema' => [
                    'emailsSent' => ['type' => 'integer', 'label' => 'Emails Sent'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'notifyWarrantIssued',
                'isAsync' => false,
            ],
            [
                'action' => 'Warrants.RevokeWarrant',
                'label' => 'Revoke Warrant',
                'description' => 'Cancel/revoke a specific warrant',
                'inputSchema' => [
                    'warrantId' => ['type' => 'integer', 'label' => 'Warrant ID', 'required' => true, 'description' => 'The ID of the warrant to revoke'],
                    'reason' => ['type' => 'string', 'label' => 'Reason', 'default' => 'Revoked via workflow'],
                    'revokerId' => ['type' => 'integer', 'label' => 'Revoker ID', 'description' => 'Member ID of the revoker (defaults to triggeredBy)'],
                    'expiresOn' => ['type' => 'datetime', 'label' => 'Expires On', 'description' => 'When the warrant should terminate (defaults to now)'],
                ],
                'outputSchema' => [
                    'revoked' => ['type' => 'boolean', 'label' => 'Revocation Successful'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'revokeWarrant',
                'isAsync' => false,
            ],
            [
                'action' => 'Warrants.CancelByEntity',
                'label' => 'Cancel Warrants By Entity',
                'description' => 'Cancel all warrants for a specific entity',
                'inputSchema' => [
                    'entityType' => ['type' => 'string', 'label' => 'Entity Type', 'required' => true, 'description' => 'CakePHP table alias for the entity'],
                    'entityId' => ['type' => 'integer', 'label' => 'Entity ID', 'required' => true, 'description' => 'Primary key of the entity'],
                    'reason' => ['type' => 'string', 'label' => 'Reason', 'default' => 'Cancelled via workflow'],
                    'revokerId' => ['type' => 'integer', 'label' => 'Revoker ID', 'description' => 'Member ID of the revoker (defaults to triggeredBy)'],
                    'expiresOn' => ['type' => 'datetime', 'label' => 'Expires On', 'description' => 'When warrants should terminate (defaults to now)'],
                ],
                'outputSchema' => [
                    'cancelled' => ['type' => 'boolean', 'label' => 'Cancellation Successful'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'cancelByEntity',
                'isAsync' => false,
            ],
            [
                'action' => 'Warrants.DeclineSingleWarrant',
                'label' => 'Decline Single Warrant',
                'description' => 'Decline a specific warrant (not the entire roster)',
                'inputSchema' => [
                    'warrantId' => ['type' => 'integer', 'label' => 'Warrant ID', 'required' => true, 'description' => 'The ID of the warrant to decline'],
                    'reason' => ['type' => 'string', 'label' => 'Reason', 'default' => 'Declined via workflow'],
                    'rejecterId' => ['type' => 'integer', 'label' => 'Rejecter ID', 'description' => 'Member ID of the rejecter (defaults to triggeredBy)'],
                ],
                'outputSchema' => [
                    'declined' => ['type' => 'boolean', 'label' => 'Decline Successful'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'declineSingleWarrant',
                'isAsync' => false,
            ],
            [
                'action' => 'Warrants.ValidateWarrantability',
                'label' => 'Validate Warrantability',
                'description' => 'Check if a member is eligible to receive warrants',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true, 'description' => 'The member to check'],
                ],
                'outputSchema' => [
                    'warrantable' => ['type' => 'boolean', 'label' => 'Is Warrantable'],
                    'reason' => ['type' => 'string', 'label' => 'Reason'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'validateWarrantability',
                'isAsync' => false,
            ],
            [
                'action' => 'Warrants.GetWarrantPeriod',
                'label' => 'Get Warrant Period',
                'description' => 'Calculate warrant start/end dates from a warrant period',
                'inputSchema' => [
                    'startOn' => ['type' => 'datetime', 'label' => 'Start Date', 'required' => true],
                    'endOn' => ['type' => 'datetime', 'label' => 'End Date'],
                ],
                'outputSchema' => [
                    'startDate' => ['type' => 'string', 'label' => 'Period Start Date'],
                    'endDate' => ['type' => 'string', 'label' => 'Period End Date'],
                    'periodId' => ['type' => 'integer', 'label' => 'Period ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'getWarrantPeriod',
                'isAsync' => false,
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerConditions(): void
    {
        $conditionsClass = WarrantWorkflowConditions::class;

        WorkflowConditionRegistry::register(self::SOURCE, [
            [
                'condition' => 'Warrants.IsMemberWarrantable',
                'label' => 'Is Member Warrantable',
                'description' => 'Check if a member has the warrantable flag set and membership is not expired',
                'inputSchema' => [
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isMemberWarrantable',
            ],
            [
                'condition' => 'Warrants.HasRequiredApprovals',
                'label' => 'Has Required Approvals',
                'description' => 'Check if a warrant roster has received the required number of approvals',
                'inputSchema' => [
                    'rosterId' => ['type' => 'integer', 'label' => 'Roster ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'hasRequiredApprovals',
            ],
            [
                'condition' => 'Warrants.IsWithinWarrantPeriod',
                'label' => 'Is Within Warrant Period',
                'description' => 'Check if the current date falls within the specified warrant period',
                'inputSchema' => [
                    'startOn' => ['type' => 'datetime', 'label' => 'Period Start', 'required' => true],
                    'expiresOn' => ['type' => 'datetime', 'label' => 'Period End'],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isWithinWarrantPeriod',
            ],
            [
                'condition' => 'Warrants.IsRosterApproved',
                'label' => 'Is Roster Approved',
                'description' => 'Check if a warrant roster has been fully approved',
                'inputSchema' => [
                    'rosterId' => ['type' => 'integer', 'label' => 'Roster ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isRosterApproved',
            ],
            [
                'condition' => 'Warrants.IsWarrantActive',
                'label' => 'Is Warrant Active',
                'description' => 'Check if a specific warrant is currently active',
                'inputSchema' => [
                    'warrantId' => ['type' => 'integer', 'label' => 'Warrant ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isWarrantActive',
            ],
        ]);
    }
}
