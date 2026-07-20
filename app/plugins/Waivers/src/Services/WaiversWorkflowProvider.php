<?php

declare(strict_types=1);

namespace Waivers\Services;

use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowEntityRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;

/**
 * Registers waiver workflow triggers, actions, conditions, and entities with the workflow registries.
 */
class WaiversWorkflowProvider
{
    private const SOURCE = 'Waivers';

    /**
     * Register all waiver workflow components.
     *
     * @return void
     */
    public static function register(): void
    {
        self::registerTriggers();
        self::registerActions();
        self::registerConditions();
        self::registerEntities();
    }

    /**
     * @return void
     */
    private static function registerTriggers(): void
    {
        WorkflowTriggerRegistry::register(self::SOURCE, [
            [
                'event' => 'Waivers.ReadyToClose',
                'label' => 'Waiver Collection Ready to Close',
                'description' => 'When a gathering waiver collection is marked as ready to close',
                'payloadSchema' => [
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'markedBy' => ['type' => 'integer', 'label' => 'Marked By Member ID'],
                ],
            ],
            [
                'event' => 'Waivers.CollectionClosed',
                'label' => 'Waiver Collection Closed',
                'description' => 'When a gathering waiver collection is officially closed',
                'payloadSchema' => [
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'closedBy' => ['type' => 'integer', 'label' => 'Closed By Member ID'],
                ],
            ],
            [
                'event' => 'Waivers.CollectionReopened',
                'label' => 'Waiver Collection Reopened',
                'description' => 'When a gathering waiver collection is reopened',
                'payloadSchema' => [
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'reopenedBy' => ['type' => 'integer', 'label' => 'Reopened By Member ID'],
                ],
            ],
            [
                'event' => 'Waivers.WaiverDeclined',
                'label' => 'Waiver Declined',
                'description' => 'When an individual gathering waiver is declined',
                'payloadSchema' => [
                    'waiverId' => ['type' => 'integer', 'label' => 'Waiver ID'],
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'declinedBy' => ['type' => 'integer', 'label' => 'Declined By Member ID'],
                    'declineReason' => ['type' => 'string', 'label' => 'Decline Reason'],
                ],
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerActions(): void
    {
        $actionsClass = WaiversWorkflowActions::class;

        WorkflowActionRegistry::register(self::SOURCE, [
            [
                'action' => 'Waivers.MarkReadyToClose',
                'label' => 'Mark Ready to Close',
                'description' => 'Mark gathering waiver collection as ready for secretary to close',
                'inputSchema' => [
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID', 'required' => true],
                    'markedBy' => ['type' => 'integer', 'label' => 'Marked By Member ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Operation Successful'],
                    'error' => ['type' => 'string', 'label' => 'Error Message'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'markReadyToClose',
                'isAsync' => false,
            ],
            [
                'action' => 'Waivers.CloseWaiverCollection',
                'label' => 'Close Waiver Collection',
                'description' => 'Close a gathering\'s waiver collection via WaiverStateService',
                'inputSchema' => [
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID', 'required' => true],
                    'closedBy' => ['type' => 'integer', 'label' => 'Closed By Member ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Operation Successful'],
                    'error' => ['type' => 'string', 'label' => 'Error Message'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'closeWaiverCollection',
                'isAsync' => false,
            ],
            [
                'action' => 'Waivers.ReopenWaiverCollection',
                'label' => 'Reopen Waiver Collection',
                'description' => 'Reopen a closed waiver collection for a gathering',
                'inputSchema' => [
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Operation Successful'],
                    'error' => ['type' => 'string', 'label' => 'Error Message'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'reopenWaiverCollection',
                'isAsync' => false,
            ],
            [
                'action' => 'Waivers.DeclineWaiver',
                'label' => 'Decline Waiver',
                'description' => 'Decline an individual gathering activity waiver',
                'inputSchema' => [
                    'waiverId' => ['type' => 'integer', 'label' => 'Waiver ID', 'required' => true],
                    'declineReason' => ['type' => 'string', 'label' => 'Decline Reason', 'required' => true],
                    'declinedBy' => ['type' => 'integer', 'label' => 'Declined By Member ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Operation Successful'],
                    'error' => ['type' => 'string', 'label' => 'Error Message'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'declineWaiver',
                'isAsync' => false,
            ],
            [
                'action' => 'Waivers.UnmarkReadyToClose',
                'label' => 'Unmark Ready to Close',
                'description' => 'Remove ready-to-close status from a gathering',
                'inputSchema' => [
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Operation Successful'],
                    'error' => ['type' => 'string', 'label' => 'Error Message'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'unmarkReadyToClose',
                'isAsync' => false,
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerConditions(): void
    {
        $conditionsClass = WaiversWorkflowConditions::class;

        WorkflowConditionRegistry::register(self::SOURCE, [
            [
                'condition' => 'Waivers.IsReadyToClose',
                'label' => 'Is Ready to Close',
                'description' => 'Check if gathering waiver collection is marked ready to close',
                'inputSchema' => [
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isReadyToClose',
            ],
            [
                'condition' => 'Waivers.IsClosed',
                'label' => 'Is Closed',
                'description' => 'Check if gathering waiver collection is closed',
                'inputSchema' => [
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isClosed',
            ],
            [
                'condition' => 'Waivers.HasUndeclinedWaivers',
                'label' => 'Has Undeclined Waivers',
                'description' => 'Check if there are any outstanding undeclined waivers for a gathering',
                'inputSchema' => [
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'hasUndeclinedWaivers',
            ],
            [
                'condition' => 'Waivers.IsPastRetentionDate',
                'label' => 'Is Past Retention Date',
                'description' => 'Check if a waiver is past its retention date',
                'inputSchema' => [
                    'waiverId' => ['type' => 'integer', 'label' => 'Waiver ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isPastRetentionDate',
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerEntities(): void
    {
        WorkflowEntityRegistry::register(self::SOURCE, [
            [
                'entityType' => 'Waivers.GatheringWaivers',
                'label' => 'Gathering Waiver',
                'description' => 'Uploaded waiver or exemption for a gathering',
                'tableClass' => \Waivers\Model\Table\GatheringWaiversTable::class,
                'fields' => [
                    'id' => ['type' => 'integer', 'label' => 'ID'],
                    'gathering_id' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'waiver_type_id' => ['type' => 'integer', 'label' => 'Waiver Type ID'],
                    'status' => ['type' => 'string', 'label' => 'Status'],
                    'retention_date' => ['type' => 'date', 'label' => 'Retention Date'],
                    'is_exemption' => ['type' => 'boolean', 'label' => 'Is Exemption'],
                ],
            ],
            [
                'entityType' => 'Waivers.GatheringWaiverClosures',
                'label' => 'Gathering Waiver Closure',
                'description' => 'Waiver collection closure status for a gathering',
                'tableClass' => \Waivers\Model\Table\GatheringWaiverClosuresTable::class,
                'fields' => [
                    'id' => ['type' => 'integer', 'label' => 'ID'],
                    'gathering_id' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'closed_at' => ['type' => 'datetime', 'label' => 'Closed At'],
                    'closed_by' => ['type' => 'integer', 'label' => 'Closed By'],
                    'ready_to_close_at' => ['type' => 'datetime', 'label' => 'Ready to Close At'],
                    'ready_to_close_by' => ['type' => 'integer', 'label' => 'Ready to Close By'],
                ],
            ],
        ]);
    }
}
