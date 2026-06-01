<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowEntityRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;
use Awards\Model\Table\BestowalsTable;
use Awards\Model\Table\RecommendationsTable;

/**
 * Registers award recommendation workflow triggers, actions, conditions,
 * and entities with the workflow registries.
 */
class AwardsWorkflowProvider
{
    private const SOURCE = 'Awards';

    /**
     * Register all award workflow components.
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
                'event' => 'Awards.RecommendationCreateRequested',
                'label' => 'Recommendation Create Requested',
                'description' => 'When a workflow should create a recommendation from submitted form data',
                'payloadSchema' => [
                    'data' => ['type' => 'object', 'label' => 'Recommendation Data'],
                    'requesterContext' => ['type' => 'object', 'label' => 'Authenticated Requester Context'],
                    'submissionMode' => ['type' => 'string', 'label' => 'Submission Mode'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                    'branchId' => ['type' => 'integer', 'label' => 'Branch ID'],
                ],
            ],
            [
                'event' => 'Awards.RecommendationSubmitted',
                'label' => 'Recommendation Submitted',
                'description' => 'When a new award recommendation is submitted',
                'payloadSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID'],
                    'awardId' => ['type' => 'integer', 'label' => 'Award ID'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'requesterId' => ['type' => 'integer', 'label' => 'Requester ID'],
                    'branchId' => ['type' => 'integer', 'label' => 'Branch ID'],
                    'state' => ['type' => 'string', 'label' => 'Initial State'],
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name'],
                    'awardName' => ['type' => 'string', 'label' => 'Award Name'],
                    'reason' => ['type' => 'string', 'label' => 'Recommendation Reason'],
                    'contactEmail' => ['type' => 'string', 'label' => 'Contact Email'],
                ],
            ],
            [
                'event' => 'Awards.RecommendationUpdateRequested',
                'label' => 'Recommendation Update Requested',
                'description' => 'When a workflow should update an existing recommendation',
                'payloadSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID'],
                    'data' => ['type' => 'object', 'label' => 'Updated Recommendation Data'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.RecommendationTransitionRequested',
                'label' => 'Recommendation Transition Requested',
                'description' => 'When a workflow should transition a single recommendation',
                'payloadSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID'],
                    'targetState' => ['type' => 'string', 'label' => 'Target State'],
                    'data' => ['type' => 'object', 'label' => 'Transition Data'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.RecommendationStateChanged',
                'label' => 'Recommendation State Changed',
                'description' => 'When a recommendation transitions to a new state',
                'payloadSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID'],
                    'previousState' => ['type' => 'string', 'label' => 'Previous State'],
                    'newState' => ['type' => 'string', 'label' => 'New State'],
                    'previousStatus' => ['type' => 'string', 'label' => 'Previous Status'],
                    'newStatus' => ['type' => 'string', 'label' => 'New Status'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.RecommendationBulkTransitionRequested',
                'label' => 'Recommendation Bulk Transition Requested',
                'description' => 'When a workflow should perform a bulk recommendation transition',
                'payloadSchema' => [
                    'recommendationIds' => ['type' => 'array', 'label' => 'Recommendation IDs'],
                    'targetState' => ['type' => 'string', 'label' => 'Target State'],
                    'data' => ['type' => 'object', 'label' => 'Bulk Transition Data'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.BulkStateTransition',
                'label' => 'Bulk State Transition',
                'description' => 'When multiple recommendations are transitioned in bulk',
                'payloadSchema' => [
                    'recommendationIds' => ['type' => 'array', 'label' => 'Recommendation IDs'],
                    'targetState' => ['type' => 'string', 'label' => 'Target State'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.RecommendationsGroupRequested',
                'label' => 'Recommendations Group Requested',
                'description' => 'When selected recommendations should be grouped under a shared head',
                'payloadSchema' => [
                    'recommendationIds' => ['type' => 'array', 'label' => 'Recommendation IDs'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.RecommendationsUngroupRequested',
                'label' => 'Recommendations Ungroup Requested',
                'description' => 'When all children should be removed from a recommendation group',
                'payloadSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Group Head Recommendation ID'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.RecommendationRemoveFromGroupRequested',
                'label' => 'Recommendation Remove From Group Requested',
                'description' => 'When a single grouped recommendation should be restored to its origin state',
                'payloadSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Grouped Recommendation ID'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.RecommendationDeleteRequested',
                'label' => 'Recommendation Delete Requested',
                'description' => 'When a workflow should delete an existing recommendation',
                'payloadSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.BestowalTransitionRequested',
                'label' => 'Bestowal Transition Requested',
                'description' => 'When a workflow should transition a single bestowal',
                'payloadSchema' => [
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID'],
                    'targetState' => ['type' => 'string', 'label' => 'Target State'],
                    'data' => ['type' => 'object', 'label' => 'Transition Data'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.BestowalUpdateRequested',
                'label' => 'Bestowal Update Requested',
                'description' => 'When a bestowal edit form is submitted',
                'payloadSchema' => [
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID'],
                    'data' => ['type' => 'object', 'label' => 'Update Data'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.BestowalBulkTransitionRequested',
                'label' => 'Bestowal Bulk Transition Requested',
                'description' => 'When multiple bestowals should be bulk updated',
                'payloadSchema' => [
                    'bestowalIds' => ['type' => 'array', 'label' => 'Bestowal IDs'],
                    'targetState' => ['type' => 'string', 'label' => 'Target State'],
                    'data' => ['type' => 'object', 'label' => 'Transition Data'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.BestowalStateChanged',
                'label' => 'Bestowal State Changed',
                'description' => 'When a bestowal transitions to a new state',
                'payloadSchema' => [
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID'],
                    'previousState' => ['type' => 'string', 'label' => 'Previous State'],
                    'newState' => ['type' => 'string', 'label' => 'New State'],
                    'previousStatus' => ['type' => 'string', 'label' => 'Previous Status'],
                    'newStatus' => ['type' => 'string', 'label' => 'New Status'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.BestowalCreated',
                'label' => 'Bestowal Created',
                'description' => 'When a new bestowal is created from recommendations or ad-hoc entry',
                'payloadSchema' => [
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID'],
                    'recommendationIds' => ['type' => 'array', 'label' => 'Recommendation IDs'],
                    'primaryRecommendationId' => ['type' => 'integer', 'label' => 'Primary Recommendation ID'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name'],
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'status' => ['type' => 'string', 'label' => 'Status'],
                    'state' => ['type' => 'string', 'label' => 'State'],
                    'source' => ['type' => 'string', 'label' => 'Source'],
                ],
            ],
            [
                'event' => 'Awards.BestowalCancelRequested',
                'label' => 'Bestowal Cancel Requested',
                'description' => 'When a workflow should cancel an in-flight bestowal',
                'payloadSchema' => [
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID'],
                    'closeReason' => ['type' => 'string', 'label' => 'Close Reason'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
            [
                'event' => 'Awards.BestowalCancelled',
                'label' => 'Bestowal Cancelled',
                'description' => 'When a bestowal has been cancelled and recommendations unwound',
                'payloadSchema' => [
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID'],
                    'recommendationIds' => ['type' => 'array', 'label' => 'Recommendation IDs'],
                    'closeReason' => ['type' => 'string', 'label' => 'Close Reason'],
                    'unwindState' => ['type' => 'string', 'label' => 'Unwind Recommendation State'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'previousState' => ['type' => 'string', 'label' => 'Previous State'],
                    'newState' => ['type' => 'string', 'label' => 'New State'],
                ],
            ],
            [
                'event' => 'Awards.AdHocBestowalRequested',
                'label' => 'Ad Hoc Bestowal Requested',
                'description' => 'When a workflow should record an ad-hoc bestowal backfill entry',
                'payloadSchema' => [
                    'data' => ['type' => 'object', 'label' => 'Ad Hoc Bestowal Data'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerActions(): void
    {
        $actionsClass = AwardsWorkflowActions::class;

        WorkflowActionRegistry::register(self::SOURCE, [
            [
                'action' => 'Awards.CreateRecommendation',
                'label' => 'Create Recommendation',
                'description' => 'Create a new award recommendation with initial status and state',
                'inputSchema' => [
                    'awardId' => ['type' => 'integer', 'label' => 'Award ID', 'required' => true],
                    'requesterScaName' => ['type' => 'string', 'label' => 'Requester SCA Name', 'required' => true],
                    'memberScaName' => ['type' => 'string', 'label' => 'Member SCA Name', 'required' => true],
                    'contactEmail' => ['type' => 'string', 'label' => 'Contact Email', 'required' => true],
                    'reason' => ['type' => 'string', 'label' => 'Reason', 'required' => true],
                    'requesterId' => ['type' => 'integer', 'label' => 'Requester ID'],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'memberPublicId' => ['type' => 'string', 'label' => 'Member Public ID'],
                    'branchId' => ['type' => 'integer', 'label' => 'Branch ID'],
                    'data' => ['type' => 'object', 'label' => 'Recommendation Data'],
                    'requesterContext' => ['type' => 'object', 'label' => 'Authenticated Requester Context'],
                    'submissionMode' => ['type' => 'string', 'label' => 'Submission Mode'],
                    'notFound' => ['type' => 'boolean', 'label' => 'Member Not Found'],
                    'gatheringIds' => ['type' => 'array', 'label' => 'Gathering IDs'],
                    'gatherings' => ['type' => 'object', 'label' => 'Gathering Association Data'],
                    'status' => ['type' => 'string', 'label' => 'Initial Status'],
                    'state' => ['type' => 'string', 'label' => 'Initial State'],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Creation Successful'],
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID'],
                    'eventPayload' => ['type' => 'object', 'label' => 'Submission Event Payload'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'createRecommendation',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.UpdateRecommendation',
                'label' => 'Update Recommendation',
                'description' => 'Update an existing recommendation using the shared mutation service',
                'inputSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID', 'required' => true],
                    'data' => ['type' => 'object', 'label' => 'Recommendation Data'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID', 'required' => true],
                    'memberPublicId' => ['type' => 'string', 'label' => 'Member Public ID'],
                    'gatheringIds' => ['type' => 'array', 'label' => 'Gathering IDs'],
                    'note' => ['type' => 'string', 'label' => 'Update Note'],
                    'given' => ['type' => 'string', 'label' => 'Given Date'],
                    'notFound' => ['type' => 'boolean', 'label' => 'Member Not Found'],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Update Successful'],
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID'],
                    'noteId' => ['type' => 'integer', 'label' => 'Created Note ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'updateRecommendation',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.TransitionState',
                'label' => 'Transition Recommendation State',
                'description' => 'Move a recommendation to a new state using the state machine',
                'inputSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID', 'required' => true],
                    'targetState' => ['type' => 'string', 'label' => 'Target State', 'required' => true],
                    'toState' => ['type' => 'string', 'label' => 'Target State Alias'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                    'data' => ['type' => 'object', 'label' => 'Transition Data'],
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'given' => ['type' => 'string', 'label' => 'Given Date'],
                    'note' => ['type' => 'string', 'label' => 'Note'],
                    'closeReason' => ['type' => 'string', 'label' => 'Close Reason'],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Transition Successful'],
                    'previousState' => ['type' => 'string', 'label' => 'Previous State'],
                    'newState' => ['type' => 'string', 'label' => 'New State'],
                    'newStatus' => ['type' => 'string', 'label' => 'New Status'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'transitionState',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.BulkTransitionState',
                'label' => 'Bulk Transition State',
                'description' => 'Batch state transition for multiple recommendations',
                'inputSchema' => [
                    'recommendationIds' => ['type' => 'array', 'label' => 'Recommendation IDs', 'required' => true],
                    'targetState' => ['type' => 'string', 'label' => 'Target State', 'required' => true],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                    'data' => ['type' => 'object', 'label' => 'Bulk Transition Data'],
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'given' => ['type' => 'string', 'label' => 'Given Date'],
                    'note' => ['type' => 'string', 'label' => 'Note'],
                    'closeReason' => ['type' => 'string', 'label' => 'Close Reason'],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Bulk Transition Successful'],
                    'processedCount' => ['type' => 'integer', 'label' => 'Processed Count'],
                    'targetState' => ['type' => 'string', 'label' => 'Target State'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'bulkTransitionState',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.GroupRecommendations',
                'label' => 'Group Recommendations',
                'description' => 'Group multiple recommendations under a shared head recommendation',
                'inputSchema' => [
                    'recommendationIds' => ['type' => 'array', 'label' => 'Recommendation IDs', 'required' => true],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Grouping Successful'],
                    'headId' => ['type' => 'integer', 'label' => 'Group Head Recommendation ID'],
                    'groupedCount' => ['type' => 'integer', 'label' => 'Grouped Count'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'groupRecommendations',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.UngroupRecommendations',
                'label' => 'Ungroup Recommendations',
                'description' => 'Restore all grouped children back to their origin states',
                'inputSchema' => [
                    'recommendationId' => [
                        'type' => 'integer',
                        'label' => 'Group Head Recommendation ID',
                        'required' => true,
                    ],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Ungroup Successful'],
                    'headId' => ['type' => 'integer', 'label' => 'Group Head Recommendation ID'],
                    'restoredCount' => ['type' => 'integer', 'label' => 'Restored Child Count'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'ungroupRecommendations',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.RemoveRecommendationFromGroup',
                'label' => 'Remove Recommendation From Group',
                'description' => 'Restore a single grouped recommendation and auto-restore the final child when needed',
                'inputSchema' => [
                    'recommendationId' => [
                        'type' => 'integer',
                        'label' => 'Grouped Recommendation ID',
                        'required' => true,
                    ],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Removal Successful'],
                    'formerHeadId' => ['type' => 'integer', 'label' => 'Former Group Head Recommendation ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'removeRecommendationFromGroup',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.DeleteRecommendation',
                'label' => 'Delete Recommendation',
                'description' => 'Soft-delete a recommendation and restore grouped children when deleting a head',
                'inputSchema' => [
                    'recommendationId' => [
                        'type' => 'integer',
                        'label' => 'Recommendation ID',
                        'required' => true,
                    ],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Delete Successful'],
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID'],
                    'restoredChildCount' => ['type' => 'integer', 'label' => 'Restored Child Count'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'deleteRecommendation',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.ApplyStateRules',
                'label' => 'Apply State Rules',
                'description' => 'Apply field visibility and set rules for a target state',
                'inputSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID', 'required' => true],
                    'targetState' => ['type' => 'string', 'label' => 'Target State', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Rules Applied'],
                    'appliedRules' => ['type' => 'object', 'label' => 'Applied Set Rules'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'applyStateRules',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.CreateStateLog',
                'label' => 'Create State Log',
                'description' => 'Record a state transition in the audit log',
                'inputSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID', 'required' => true],
                    'fromState' => ['type' => 'string', 'label' => 'From State', 'required' => true],
                    'toState' => ['type' => 'string', 'label' => 'To State', 'required' => true],
                    'fromStatus' => ['type' => 'string', 'label' => 'From Status'],
                    'toStatus' => ['type' => 'string', 'label' => 'To Status'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Log Created'],
                    'logId' => ['type' => 'integer', 'label' => 'Log Entry ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'createStateLog',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.AssignGathering',
                'label' => 'Assign Gathering',
                'description' => 'Link a recommendation to a gathering for presentation',
                'inputSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID', 'required' => true],
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Assignment Successful'],
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID'],
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'assignGathering',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.PullCourtPreferences',
                'label' => 'Pull Court Preferences',
                'description' => 'Look up court availability preferences for the recommendation member',
                'inputSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Lookup Successful'],
                    'courtAvailability' => ['type' => 'string', 'label' => 'Court Availability'],
                    'callIntoCourt' => ['type' => 'string', 'label' => 'Call Into Court'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'pullCourtPreferences',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.CreateBestowal',
                'label' => 'Create Bestowal',
                'description' => 'Create a bestowal from a single recommendation',
                'inputSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID', 'required' => true],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Creation Successful'],
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID'],
                    'eventPayload' => ['type' => 'object', 'label' => 'Creation Event Payload'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'createBestowal',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.CreateBestowalsForRecommendations',
                'label' => 'Create Bestowals For Recommendations',
                'description' => 'Create bestowals for each recommendation ID in the payload',
                'inputSchema' => [
                    'recommendationIds' => ['type' => 'array', 'label' => 'Recommendation IDs', 'required' => true],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Creation Successful'],
                    'processedCount' => ['type' => 'integer', 'label' => 'Processed Count'],
                    'bestowalIds' => ['type' => 'array', 'label' => 'Created Bestowal IDs'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'createBestowalsForRecommendations',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.TransitionBestowal',
                'label' => 'Transition Bestowal',
                'description' => 'Move a bestowal to a new state using the state machine',
                'inputSchema' => [
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID', 'required' => true],
                    'targetState' => ['type' => 'string', 'label' => 'Target State', 'required' => true],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID'],
                    'data' => ['type' => 'object', 'label' => 'Transition Data'],
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'gatheringScheduledActivityId' => ['type' => 'integer', 'label' => 'Scheduled Activity ID'],
                    'bestowedAt' => ['type' => 'string', 'label' => 'Bestowed At'],
                    'closeReason' => ['type' => 'string', 'label' => 'Close Reason'],
                    'note' => ['type' => 'string', 'label' => 'Note'],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Transition Successful'],
                    'previousState' => ['type' => 'string', 'label' => 'Previous State'],
                    'newState' => ['type' => 'string', 'label' => 'New State'],
                    'newStatus' => ['type' => 'string', 'label' => 'New Status'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'transitionBestowal',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.UpdateBestowal',
                'label' => 'Update Bestowal',
                'description' => 'Update a bestowal from the edit form including link changes',
                'inputSchema' => [
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID', 'required' => true],
                    'data' => ['type' => 'object', 'label' => 'Update Data'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Update Successful'],
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'updateBestowal',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.BulkTransitionBestowals',
                'label' => 'Bulk Transition Bestowals',
                'description' => 'Transition multiple bestowals and sync linked recommendations',
                'inputSchema' => [
                    'bestowalIds' => ['type' => 'array', 'label' => 'Bestowal IDs', 'required' => true],
                    'targetState' => ['type' => 'string', 'label' => 'Target State', 'required' => true],
                    'data' => ['type' => 'object', 'label' => 'Transition Data'],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Transition Successful'],
                    'processedCount' => ['type' => 'integer', 'label' => 'Processed Count'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'bulkTransitionBestowals',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.SyncRecommendationsFromBestowal',
                'label' => 'Sync Recommendations From Bestowal',
                'description' => 'Synchronize linked recommendation states from the bestowal state mapping',
                'inputSchema' => [
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID', 'required' => true],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Sync Successful'],
                    'syncedCount' => ['type' => 'integer', 'label' => 'Synced Count'],
                    'targetState' => ['type' => 'string', 'label' => 'Target Recommendation State'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'syncRecommendationsFromBestowal',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.CancelBestowal',
                'label' => 'Cancel Bestowal',
                'description' => 'Cancel an in-flight bestowal and unwind linked recommendations',
                'inputSchema' => [
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID', 'required' => true],
                    'closeReason' => ['type' => 'string', 'label' => 'Close Reason', 'required' => true],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID', 'required' => true],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Cancellation Successful'],
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID'],
                    'eventPayload' => ['type' => 'object', 'label' => 'Cancellation Event Payload'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'cancelBestowal',
                'isAsync' => false,
            ],
            [
                'action' => 'Awards.RecordAdHocBestowal',
                'label' => 'Record Ad Hoc Bestowal',
                'description' => 'Create ad-hoc recommendations and a linked bestowal in one transaction',
                'inputSchema' => [
                    'data' => ['type' => 'object', 'label' => 'Ad Hoc Bestowal Data', 'required' => true],
                    'actorId' => ['type' => 'integer', 'label' => 'Actor ID', 'required' => true],
                    'memberId' => ['type' => 'integer', 'label' => 'Member ID'],
                    'awardIds' => ['type' => 'array', 'label' => 'Award IDs'],
                    'gatheringId' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'bestowedAt' => ['type' => 'string', 'label' => 'Bestowed At'],
                ],
                'outputSchema' => [
                    'success' => ['type' => 'boolean', 'label' => 'Record Successful'],
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID'],
                    'eventPayload' => ['type' => 'object', 'label' => 'Creation Event Payload'],
                ],
                'serviceClass' => $actionsClass,
                'serviceMethod' => 'recordAdHocBestowal',
                'isAsync' => false,
            ],
        ]);
    }

    /**
     * @return void
     */
    private static function registerConditions(): void
    {
        $conditionsClass = AwardsWorkflowConditions::class;

        WorkflowConditionRegistry::register(self::SOURCE, [
            [
                'condition' => 'Awards.IsValidTransition',
                'label' => 'Is Valid Transition',
                'description' => 'Check if a state transition is allowed per state machine configuration',
                'inputSchema' => [
                    'currentState' => ['type' => 'string', 'label' => 'Current State', 'required' => true],
                    'targetState' => ['type' => 'string', 'label' => 'Target State', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'isValidTransition',
            ],
            [
                'condition' => 'Awards.HasRequiredFields',
                'label' => 'Has Required Fields',
                'description' => 'Validate that all required fields for the target state are present',
                'inputSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID', 'required' => true],
                    'targetState' => ['type' => 'string', 'label' => 'Target State', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'hasRequiredFields',
            ],
            [
                'condition' => 'Awards.RequiresGathering',
                'label' => 'Requires Gathering',
                'description' => 'Check if the target state needs a gathering_id to be set',
                'inputSchema' => [
                    'targetState' => ['type' => 'string', 'label' => 'Target State', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'requiresGathering',
            ],
            [
                'condition' => 'Awards.RequiresGivenDate',
                'label' => 'Requires Given Date',
                'description' => 'Check if the target state needs a given date to be set',
                'inputSchema' => [
                    'targetState' => ['type' => 'string', 'label' => 'Target State', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'requiresGivenDate',
            ],
            [
                'condition' => 'Awards.BestowalIsValidTransition',
                'label' => 'Bestowal Is Valid Transition',
                'description' => 'Check if a bestowal state transition is allowed per state machine configuration',
                'inputSchema' => [
                    'currentState' => ['type' => 'string', 'label' => 'Current State', 'required' => true],
                    'targetState' => ['type' => 'string', 'label' => 'Target State', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'bestowalIsValidTransition',
            ],
            [
                'condition' => 'Awards.BestowalHasRequiredFields',
                'label' => 'Bestowal Has Required Fields',
                'description' => 'Validate that all required fields for the target bestowal state are present',
                'inputSchema' => [
                    'bestowalId' => ['type' => 'integer', 'label' => 'Bestowal ID', 'required' => true],
                    'targetState' => ['type' => 'string', 'label' => 'Target State', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'bestowalHasRequiredFields',
            ],
            [
                'condition' => 'Awards.RecommendationHasActiveBestowal',
                'label' => 'Recommendation Has Active Bestowal',
                'description' => 'Check whether a recommendation is linked to an active bestowal',
                'inputSchema' => [
                    'recommendationId' => ['type' => 'integer', 'label' => 'Recommendation ID', 'required' => true],
                ],
                'evaluatorClass' => $conditionsClass,
                'evaluatorMethod' => 'recommendationHasActiveBestowal',
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
                'entityType' => 'Awards.Recommendations',
                'label' => 'Recommendation',
                'description' => 'Award recommendation with state machine workflow',
                'tableClass' => RecommendationsTable::class,
                'fields' => [
                    'id' => ['type' => 'integer', 'label' => 'ID'],
                    'award_id' => ['type' => 'integer', 'label' => 'Award ID'],
                    'member_id' => ['type' => 'integer', 'label' => 'Member ID'],
                    'requester_id' => ['type' => 'integer', 'label' => 'Requester ID'],
                    'branch_id' => ['type' => 'integer', 'label' => 'Branch ID'],
                    'status' => ['type' => 'string', 'label' => 'Status'],
                    'state' => ['type' => 'string', 'label' => 'State'],
                    'state_date' => ['type' => 'datetime', 'label' => 'State Date'],
                    'gathering_id' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'given' => ['type' => 'date', 'label' => 'Given Date'],
                    'close_reason' => ['type' => 'string', 'label' => 'Close Reason'],
                ],
            ],
            [
                'entityType' => 'Awards.Bestowals',
                'label' => 'Bestowal',
                'description' => 'Award bestowal with state machine workflow',
                'tableClass' => BestowalsTable::class,
                'fields' => [
                    'id' => ['type' => 'integer', 'label' => 'ID'],
                    'member_id' => ['type' => 'integer', 'label' => 'Member ID'],
                    'gathering_id' => ['type' => 'integer', 'label' => 'Gathering ID'],
                    'gathering_scheduled_activity_id' => ['type' => 'integer', 'label' => 'Scheduled Activity ID'],
                    'primary_recommendation_id' => ['type' => 'integer', 'label' => 'Primary Recommendation ID'],
                    'status' => ['type' => 'string', 'label' => 'Status'],
                    'state' => ['type' => 'string', 'label' => 'State'],
                    'state_date' => ['type' => 'datetime', 'label' => 'State Date'],
                    'stack_rank' => ['type' => 'integer', 'label' => 'Stack Rank'],
                    'bestowed_at' => ['type' => 'datetime', 'label' => 'Bestowed At'],
                    'source' => ['type' => 'string', 'label' => 'Source'],
                    'close_reason' => ['type' => 'string', 'label' => 'Close Reason'],
                ],
            ],
        ]);
    }
}
