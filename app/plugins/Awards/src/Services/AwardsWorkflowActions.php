<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Services\WorkflowEngine\StateMachine\StateMachineHandler;
use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Awards\Model\Entity\Recommendation;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use Throwable;

/**
 * Workflow action implementations for award recommendation operations.
 *
 * Provides state machine transitions, bulk updates, and gathering assignment
 * for the Awards plugin workflow engine integration.
 *
 * @property \Awards\Model\Table\RecommendationsTable $Recommendations
 * @property \Awards\Model\Table\RecommendationsStatesLogsTable $RecommendationsStatesLogs
 * @property \Awards\Model\Table\BestowalsTable $Bestowals
 */
class AwardsWorkflowActions
{
    use LocatorAwareTrait;
    use WorkflowContextAwareTrait;

    private Table $recommendationsTable;
    private Table $bestowalsTable;
    private StateMachineHandler $stateMachineHandler;
    private RecommendationSubmissionService $submissionService;
    private RecommendationUpdateService $updateService;
    private RecommendationTransitionService $transitionService;
    private RecommendationGroupingService $groupingService;
    private RecommendationDeletionService $deletionService;
    private RecommendationStateLogService $stateLogService;
    private BestowalCreationService $bestowalCreationService;
    private BestowalTransitionService $bestowalTransitionService;
    private BestowalRecommendationSyncService $bestowalRecommendationSyncService;
    private BestowalCancellationService $bestowalCancellationService;
    private BestowalUpdateService $bestowalUpdateService;
    private AdHocBestowalService $adHocBestowalService;

    /**
     * @param \App\Services\WorkflowEngine\StateMachine\StateMachineHandler|null $stateMachineHandler State machine helper.
     * @param \Awards\Services\RecommendationSubmissionService|null $submissionService Submission workflow service.
     * @param \Awards\Services\RecommendationUpdateService|null $updateService Update workflow service.
     * @param \Awards\Services\RecommendationTransitionService|null $transitionService Transition workflow service.
     * @param \Awards\Services\RecommendationGroupingService|null $groupingService Grouping workflow service.
     * @param \Awards\Services\RecommendationDeletionService|null $deletionService Deletion workflow service.
     * @param \Awards\Services\RecommendationStateLogService|null $stateLogService State-log workflow service.
     * @param \Awards\Services\BestowalCreationService|null $bestowalCreationService Bestowal creation service.
     * @param \Awards\Services\BestowalTransitionService|null $bestowalTransitionService Bestowal transition service.
     * @param \Awards\Services\BestowalRecommendationSyncService|null $bestowalRecommendationSyncService Bestowal sync service.
     * @param \Awards\Services\BestowalCancellationService|null $bestowalCancellationService Bestowal cancellation service.
     * @param \Awards\Services\BestowalUpdateService|null $bestowalUpdateService Bestowal update service.
     * @param \Awards\Services\AdHocBestowalService|null $adHocBestowalService Ad-hoc bestowal service.
     */
    public function __construct(
        ?StateMachineHandler $stateMachineHandler = null,
        ?RecommendationSubmissionService $submissionService = null,
        ?RecommendationUpdateService $updateService = null,
        ?RecommendationTransitionService $transitionService = null,
        ?RecommendationGroupingService $groupingService = null,
        ?RecommendationDeletionService $deletionService = null,
        ?RecommendationStateLogService $stateLogService = null,
        ?BestowalCreationService $bestowalCreationService = null,
        ?BestowalTransitionService $bestowalTransitionService = null,
        ?BestowalRecommendationSyncService $bestowalRecommendationSyncService = null,
        ?BestowalCancellationService $bestowalCancellationService = null,
        ?BestowalUpdateService $bestowalUpdateService = null,
        ?AdHocBestowalService $adHocBestowalService = null,
    ) {
        $this->recommendationsTable = $this->fetchTable('Awards.Recommendations');
        $this->bestowalsTable = $this->fetchTable('Awards.Bestowals');
        $this->stateMachineHandler = $stateMachineHandler ?? new StateMachineHandler();
        $this->stateLogService = $stateLogService ?? new RecommendationStateLogService();
        $this->groupingService = $groupingService ?? new RecommendationGroupingService(
            $this->recommendationsTable,
            $this->stateLogService,
        );
        $this->submissionService = $submissionService ?? new RecommendationSubmissionService($this->stateLogService);
        $this->updateService = $updateService ?? new RecommendationUpdateService(
            $this->groupingService,
            $this->stateLogService,
        );
        $this->transitionService = $transitionService ?? new RecommendationTransitionService(
            $this->groupingService,
            $this->stateLogService,
        );
        $this->deletionService = $deletionService ?? new RecommendationDeletionService($this->groupingService);
        $this->bestowalTransitionService = $bestowalTransitionService ?? new BestowalTransitionService();
        $this->bestowalRecommendationSyncService = $bestowalRecommendationSyncService
            ?? new BestowalRecommendationSyncService();
        $this->bestowalCreationService = $bestowalCreationService ?? new BestowalCreationService();
        $this->bestowalCancellationService = $bestowalCancellationService ?? new BestowalCancellationService(
            transitionService: $this->bestowalTransitionService,
            syncService: $this->bestowalRecommendationSyncService,
        );
        $this->bestowalUpdateService = $bestowalUpdateService ?? new BestowalUpdateService(
            transitionService: $this->bestowalTransitionService,
            syncService: $this->bestowalRecommendationSyncService,
        );
        $this->adHocBestowalService = $adHocBestowalService ?? new AdHocBestowalService();
    }

    /**
     * Create a new recommendation with initial status and state.
     *
     * @param array $context Current workflow context
     * @param array $config Config with awardId, requesterScaName, memberScaName, contactEmail, reason, and optional fields
     * @return array Output with success, recommendationId
     */
    public function createRecommendation(array $context, array $config): array
    {
        try {
            $data = $this->extractRecommendationData($context, $config);
            $requesterContext = $this->extractRequesterContext($context, $config);
            $submissionMode = (string)$this->resolveValue(
                $config['submissionMode'] ?? $config['mode'] ?? '',
                $context,
            );

            if ($requesterContext !== null || $submissionMode === 'authenticated') {
                if ($requesterContext === null) {
                    return [
                        'success' => false,
                        'error' => 'Authenticated submissions require requesterContext.',
                        'data' => ['errors' => []],
                    ];
                }

                $result = $this->submissionService->submitAuthenticated(
                    $this->recommendationsTable,
                    $data,
                    $requesterContext,
                );
            } else {
                $result = $this->submissionService->submitPublic($this->recommendationsTable, $data);
            }

            return $this->formatMutationResult($result);
        } catch (Throwable $e) {
            Log::error('Workflow CreateRecommendation failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage(), 'data' => ['errors' => []]];
        }
    }

    /**
     * Transition a recommendation to a new state using the state machine handler.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationId, targetState, actorId
     * @return array Output with success, previousState, newState, newStatus
     */
    public function transitionState(array $context, array $config): array
    {
        try {
            $recommendationId = (int)$this->resolveValue($config['recommendationId'], $context);
            $transitionData = $this->extractTransitionData($context, $config);
            $actorId = (int)$this->resolveValue($config['actorId'] ?? 0, $context);

            return $this->transitionService->transition(
                $this->recommendationsTable,
                $recommendationId,
                $transitionData,
                $actorId,
            );
        } catch (Throwable $e) {
            Log::error('Workflow TransitionState failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Batch state transition for multiple recommendations.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationIds (array), targetState, actorId, note, gatheringId, given, closeReason
     * @return array Output with success, results per entity
     */
    public function bulkTransitionState(array $context, array $config): array
    {
        try {
            $bulkData = $this->extractTransitionData($context, $config);
            $actorId = (int)$this->resolveValue($config['actorId'] ?? 0, $context);

            return $this->transitionService->transitionMany(
                $this->recommendationsTable,
                $bulkData,
                $actorId,
            );
        } catch (Throwable $e) {
            Log::error('Workflow BulkTransitionState failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Apply field set rules for a target state without performing the transition.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationId, targetState
     * @return array Output with success, modified entity data
     */
    public function applyStateRules(array $context, array $config): array
    {
        try {
            $recommendationId = (int)$this->resolveValue($config['recommendationId'], $context);
            $targetState = (string)$this->resolveValue($config['state'] ?? $config['targetState'] ?? null, $context);

            $recommendation = $this->recommendationsTable->get($recommendationId);
            $entityData = $recommendation->toArray();

            $smConfig = $this->buildStateMachineConfig();
            $stateRules = $smConfig['stateRules'][$targetState] ?? [];

            $modifiedData = $this->stateMachineHandler->applySetRules($entityData, $stateRules);

            // Apply changes to entity
            foreach ($modifiedData as $field => $value) {
                if ($recommendation->isAccessible($field)) {
                    $recommendation->set($field, $value);
                }
            }

            $saved = $this->recommendationsTable->save($recommendation);
            if (!$saved) {
                return ['success' => false, 'error' => 'Failed to save recommendation after applying rules'];
            }

            return [
                'success' => true,
                'data' => [
                    'recommendationId' => $recommendationId,
                    'appliedRules' => $stateRules['set'] ?? [],
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow ApplyStateRules failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Record a state transition in the audit log.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationId, fromState, toState, fromStatus, toStatus, actorId
     * @return array Output with success, logId
     */
    public function createStateLog(array $context, array $config): array
    {
        try {
            $recommendationId = (int)$this->resolveValue($config['recommendationId'], $context);
            $fromState = (string)$this->resolveValue($config['fromState'], $context);
            $toState = (string)$this->resolveValue($config['toState'], $context);
            $fromStatus = (string)$this->resolveValue($config['fromStatus'] ?? 'Unknown', $context);
            $toStatus = (string)$this->resolveValue($config['toStatus'] ?? 'Unknown', $context);
            $actorId = $this->resolveValue($config['actorId'] ?? null, $context);

            $log = $this->stateLogService->createLog(
                $recommendationId,
                $fromState,
                $toState,
                $fromStatus,
                $toStatus,
                $actorId ? (int)$actorId : null,
            );

            return [
                'success' => true,
                'data' => ['logId' => $log->id],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow CreateStateLog failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update an existing recommendation through the shared mutation service.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationId, data, actorId
     * @return array Workflow action result
     */
    public function updateRecommendation(array $context, array $config): array
    {
        try {
            $recommendationId = (int)$this->resolveValue($config['recommendationId'], $context);
            $actorId = (int)$this->resolveValue($config['actorId'] ?? 0, $context);
            $data = $this->extractRecommendationData($context, $config);

            $recommendation = $this->recommendationsTable->get($recommendationId, contain: ['Gatherings']);
            $result = $this->updateService->update(
                $this->recommendationsTable,
                $recommendation,
                $data,
                $actorId,
            );

            return $this->formatMutationResult($result);
        } catch (Throwable $e) {
            Log::error('Workflow UpdateRecommendation failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage(), 'data' => ['errors' => []]];
        }
    }

    /**
     * Group recommendations under a shared head recommendation.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationIds, actorId
     * @return array Workflow action result
     */
    public function groupRecommendations(array $context, array $config): array
    {
        try {
            $ids = $this->extractRecommendationIds($context, $config);
            $actorId = $this->extractNullableActorId($context, $config);
            $head = $this->groupingService->groupRecommendations($ids, $actorId);

            return [
                'success' => true,
                'data' => [
                    'headId' => (int)$head->id,
                    'recommendationIds' => $ids,
                    'groupedCount' => count($ids),
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow GroupRecommendations failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Ungroup all children from a recommendation head.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationId/headId, actorId
     * @return array Workflow action result
     */
    public function ungroupRecommendations(array $context, array $config): array
    {
        try {
            $headId = (int)$this->resolveValue(
                $config['headId'] ?? $config['recommendationId'] ?? 0,
                $context,
            );
            $actorId = $this->extractNullableActorId($context, $config);
            $restored = $this->groupingService->ungroupRecommendations($headId, $actorId);

            return [
                'success' => true,
                'data' => [
                    'headId' => $headId,
                    'restoredCount' => count($restored),
                    'restoredIds' => array_map(
                        static fn(Recommendation $recommendation): int => (int)$recommendation->id,
                        $restored,
                    ),
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow UngroupRecommendations failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Remove a single recommendation from its current group.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationId/childId, actorId
     * @return array Workflow action result
     */
    public function removeRecommendationFromGroup(array $context, array $config): array
    {
        try {
            $childId = (int)$this->resolveValue(
                $config['childId'] ?? $config['recommendationId'] ?? 0,
                $context,
            );
            $actorId = $this->extractNullableActorId($context, $config);
            $formerHeadId = $this->groupingService->removeFromGroup($childId, $actorId);

            return [
                'success' => true,
                'data' => [
                    'recommendationId' => $childId,
                    'formerHeadId' => $formerHeadId,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow RemoveRecommendationFromGroup failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete a recommendation through the shared mutation service.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationId, actorId
     * @return array Workflow action result
     */
    public function deleteRecommendation(array $context, array $config): array
    {
        try {
            $recommendationId = (int)$this->resolveValue($config['recommendationId'], $context);
            $actorId = $this->extractNullableActorId($context, $config);
            $recommendation = $this->recommendationsTable->get($recommendationId);
            $result = $this->deletionService->delete($this->recommendationsTable, $recommendation, $actorId);

            return $this->formatMutationResult($result);
        } catch (Throwable $e) {
            Log::error('Workflow DeleteRecommendation failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage(), 'data' => ['errors' => []]];
        }
    }

    /**
     * Assign a gathering to a recommendation.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationId, gatheringId
     * @return array Output with success
     */
    public function assignGathering(array $context, array $config): array
    {
        try {
            $recommendationId = (int)$this->resolveValue($config['recommendationId'], $context);
            $gatheringId = (int)$this->resolveValue($config['gatheringId'], $context);

            $recommendation = $this->recommendationsTable->get($recommendationId);

            if (!Recommendation::supportsGatheringAssignmentForState((string)$recommendation->state)) {
                return [
                    'success' => false,
                    'error' => "State '{$recommendation->state}' does not support gathering assignment",
                ];
            }

            $recommendation->gathering_id = $gatheringId;
            $saved = $this->recommendationsTable->save($recommendation);

            if (!$saved) {
                return ['success' => false, 'error' => 'Failed to save gathering assignment'];
            }

            return [
                'success' => true,
                'data' => [
                    'recommendationId' => $recommendationId,
                    'gatheringId' => $gatheringId,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow AssignGathering failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Look up court availability preferences for the recommendation's member.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationId
     * @return array Output with success, courtAvailability
     */
    public function pullCourtPreferences(array $context, array $config): array
    {
        try {
            $recommendationId = (int)$this->resolveValue($config['recommendationId'], $context);

            $recommendation = $this->recommendationsTable->get($recommendationId);

            return [
                'success' => true,
                'data' => [
                    'recommendationId' => $recommendationId,
                    'courtAvailability' => $recommendation->court_availability,
                    'callIntoCourt' => $recommendation->call_into_court,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow PullCourtPreferences failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create a bestowal from a single recommendation.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationId, actorId
     * @return array Workflow action result
     */
    public function createBestowal(array $context, array $config): array
    {
        try {
            $recommendationId = (int)$this->resolveValue($config['recommendationId'], $context);
            $actorId = (int)$this->resolveValue($config['actorId'] ?? 0, $context);

            return $this->bestowalCreationService->createFromRecommendation($recommendationId, $actorId);
        } catch (Throwable $e) {
            Log::error('Workflow CreateBestowal failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create bestowals for each recommendation ID in the payload.
     *
     * @param array $context Current workflow context
     * @param array $config Config with recommendationIds, actorId
     * @return array Workflow action result
     */
    public function createBestowalsForRecommendations(array $context, array $config): array
    {
        try {
            $recommendationIds = $this->extractRecommendationIds($context, $config);
            $actorId = (int)$this->resolveValue($config['actorId'] ?? 0, $context);

            if ($recommendationIds === []) {
                return [
                    'success' => false,
                    'error' => 'At least one recommendation ID is required',
                    'data' => ['processedCount' => 0, 'results' => []],
                ];
            }

            $results = [];
            $bestowalIds = [];
            $errors = [];

            foreach ($recommendationIds as $recommendationId) {
                $result = $this->bestowalCreationService->createFromRecommendation($recommendationId, $actorId);
                $results[] = $result;

                if (!($result['success'] ?? false)) {
                    $errors[] = (string)($result['error'] ?? 'Bestowal creation failed');
                    continue;
                }

                if (($result['skipped'] ?? false) || !isset($result['data']['bestowalId'])) {
                    continue;
                }

                $bestowalIds[] = (int)$result['data']['bestowalId'];
            }

            if ($errors !== []) {
                return [
                    'success' => false,
                    'error' => implode('; ', $errors),
                    'data' => [
                        'processedCount' => count($recommendationIds),
                        'bestowalIds' => $bestowalIds,
                        'results' => $results,
                    ],
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'processedCount' => count($recommendationIds),
                    'bestowalIds' => $bestowalIds,
                    'results' => $results,
                ],
            ];
        } catch (Throwable $e) {
            Log::error('Workflow CreateBestowalsForRecommendations failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Transition a bestowal to a new state.
     *
     * @param array $context Current workflow context
     * @param array $config Config with bestowalId, targetState, data, actorId
     * @return array Workflow action result
     */
    public function transitionBestowal(array $context, array $config): array
    {
        try {
            $bestowalId = (int)$this->resolveValue($config['bestowalId'], $context);
            $transitionData = $this->extractBestowalTransitionData($context, $config);
            $actorId = (int)$this->resolveValue($config['actorId'] ?? 0, $context);

            return $this->bestowalTransitionService->transition(
                $this->bestowalsTable,
                $bestowalId,
                $transitionData,
                $actorId,
            );
        } catch (Throwable $e) {
            Log::error('Workflow TransitionBestowal failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update a bestowal from edit form data including link changes.
     *
     * @param array $context Current workflow context
     * @param array $config Config with bestowalId, data, actorId
     * @return array Workflow action result
     */
    public function updateBestowal(array $context, array $config): array
    {
        try {
            $bestowalId = (int)$this->resolveValue($config['bestowalId'], $context);
            $data = $this->resolveConfigArray($config['data'] ?? null, $context);
            $actorId = (int)$this->resolveValue($config['actorId'] ?? 0, $context);

            return $this->bestowalUpdateService->update(
                $this->bestowalsTable,
                $bestowalId,
                $data,
                $actorId,
            );
        } catch (Throwable $e) {
            Log::error('Workflow UpdateBestowal failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Bulk transition bestowals and sync linked recommendations.
     *
     * @param array $context Current workflow context
     * @param array $config Config with bestowalIds, targetState, data, actorId
     * @return array Workflow action result
     */
    public function bulkTransitionBestowals(array $context, array $config): array
    {
        try {
            $transitionData = $this->extractBestowalTransitionData($context, $config);
            $actorId = (int)$this->resolveValue($config['actorId'] ?? 0, $context);

            return $this->bestowalUpdateService->bulkTransition(
                $this->bestowalsTable,
                $transitionData,
                $actorId,
            );
        } catch (Throwable $e) {
            Log::error('Workflow BulkTransitionBestowals failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync linked recommendations from a bestowal's current state mapping.
     *
     * @param array $context Current workflow context
     * @param array $config Config with bestowalId, actorId
     * @return array Workflow action result
     */
    public function syncRecommendationsFromBestowal(array $context, array $config): array
    {
        try {
            $bestowalId = (int)$this->resolveValue($config['bestowalId'], $context);
            $actorId = (int)$this->resolveValue($config['actorId'] ?? 0, $context);

            return $this->bestowalRecommendationSyncService->syncFromBestowal($bestowalId, $actorId);
        } catch (Throwable $e) {
            Log::error('Workflow SyncRecommendationsFromBestowal failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cancel a bestowal and unwind linked recommendations.
     *
     * @param array $context Current workflow context
     * @param array $config Config with bestowalId, closeReason, actorId
     * @return array Workflow action result
     */
    public function cancelBestowal(array $context, array $config): array
    {
        try {
            $bestowalId = (int)$this->resolveValue($config['bestowalId'], $context);
            $actorId = (int)$this->resolveValue($config['actorId'] ?? 0, $context);
            $closeReason = (string)$this->resolveValue(
                $config['closeReason'] ?? $config['close_reason'] ?? '',
                $context,
            );

            return $this->bestowalCancellationService->cancel($bestowalId, $actorId, $closeReason);
        } catch (Throwable $e) {
            Log::error('Workflow CancelBestowal failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Record an ad-hoc bestowal with linked recommendations.
     *
     * @param array $context Current workflow context
     * @param array $config Config with data payload and actorId
     * @return array Workflow action result
     */
    public function recordAdHocBestowal(array $context, array $config): array
    {
        try {
            $data = $this->extractAdHocBestowalData($context, $config);
            $actorId = (int)$this->resolveValue($config['actorId'] ?? 0, $context);

            return $this->adHocBestowalService->record($data, $actorId);
        } catch (Throwable $e) {
            Log::error('Workflow RecordAdHocBestowal failed: ' . $e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Build the state machine config from the database-backed recommendation configuration.
     *
     * @return array State machine config with transitions, statuses, stateRules, stateField, statusField
     */
    private function buildStateMachineConfig(): array
    {
        $statuses = Recommendation::getStatuses();
        $allStates = Recommendation::getStates();

        // Build transitions from database
        $transitions = [];
        foreach ($allStates as $state) {
            $transitions[$state] = Recommendation::getValidTransitionsFrom($state);
        }

        $stateRulesRaw = Recommendation::getStateRules();

        // Normalize rule keys to match StateMachineHandler expectations
        $normalizedRules = [];
        foreach ($stateRulesRaw as $state => $rules) {
            $normalized = [];
            if (isset($rules['Set'])) {
                $normalized['set'] = $rules['Set'];
            }
            if (isset($rules['Required'])) {
                $normalized['required'] = $rules['Required'];
            }
            $normalizedRules[$state] = $normalized;
        }

        return [
            'transitions' => $transitions,
            'statuses' => $statuses,
            'stateRules' => $normalizedRules,
            'stateField' => 'state',
            'statusField' => 'status',
        ];
    }

    /**
     * Build mutation input data from either a nested data payload or flat config fields.
     *
     * @param array $context Current workflow context
     * @param array $config Workflow node/action config
     * @return array<string, mixed>
     */
    private function extractRecommendationData(array $context, array $config): array
    {
        $data = $this->resolveConfigArray($config['data'] ?? null, $context);
        $flatData = $this->mapConfigFields($context, $config, [
            'awardId' => 'award_id',
            'requesterId' => 'requester_id',
            'memberId' => 'member_id',
            'memberPublicId' => 'member_public_id',
            'branchId' => 'branch_id',
            'requesterScaName' => 'requester_sca_name',
            'memberScaName' => 'member_sca_name',
            'contactEmail' => 'contact_email',
            'contactNumber' => 'contact_number',
            'reason' => 'reason',
            'specialty' => 'specialty',
            'callIntoCourt' => 'call_into_court',
            'courtAvailability' => 'court_availability',
            'personToNotify' => 'person_to_notify',
            'closeReason' => 'close_reason',
            'notFound' => 'not_found',
            'given' => 'given',
            'note' => 'note',
            'state' => 'state',
        ]);

        $data = array_replace($data, $flatData);

        $gatherings = $this->resolveValue($config['gatherings'] ?? null, $context);
        if (is_array($gatherings)) {
            $data['gatherings'] = $gatherings;
        }

        $gatheringIds = $this->resolveValue($config['gatheringIds'] ?? null, $context);
        if (is_array($gatheringIds)) {
            $data['gatherings'] = ['_ids' => array_values($gatheringIds)];
        }

        return $data;
    }

    /**
     * Build transition input data from nested or flat workflow params.
     *
     * @param array $context Current workflow context
     * @param array $config Workflow node/action config
     * @return array<string, mixed>
     */
    private function extractTransitionData(array $context, array $config): array
    {
        $data = $this->resolveConfigArray($config['data'] ?? null, $context);
        $flatData = $this->mapConfigFields($context, $config, [
            'newState' => 'newState',
            'targetState' => 'targetState',
            'toState' => 'toState',
            'state' => 'state',
            'gatheringId' => 'gathering_id',
            'gathering_id' => 'gathering_id',
            'given' => 'given',
            'note' => 'note',
            'closeReason' => 'close_reason',
            'close_reason' => 'close_reason',
        ]);
        $data = array_replace($data, $flatData);

        $ids = $this->resolveValue($config['recommendationIds'] ?? $config['ids'] ?? null, $context);
        if (is_array($ids)) {
            $data['ids'] = array_values($ids);
        }

        return $data;
    }

    /**
     * Build bestowal transition input data from nested or flat workflow params.
     *
     * @param array $context Current workflow context
     * @param array $config Workflow node/action config
     * @return array<string, mixed>
     */
    private function extractBestowalTransitionData(array $context, array $config): array
    {
        $data = $this->resolveConfigArray($config['data'] ?? null, $context);
        $flatData = $this->mapConfigFields($context, $config, [
            'newState' => 'newState',
            'targetState' => 'targetState',
            'toState' => 'toState',
            'state' => 'state',
            'gatheringId' => 'gathering_id',
            'gathering_id' => 'gathering_id',
            'gatheringScheduledActivityId' => 'gathering_scheduled_activity_id',
            'gathering_scheduled_activity_id' => 'gathering_scheduled_activity_id',
            'bestowedAt' => 'bestowed_at',
            'bestowed_at' => 'bestowed_at',
            'closeReason' => 'close_reason',
            'close_reason' => 'close_reason',
            'stackRank' => 'stack_rank',
            'stack_rank' => 'stack_rank',
            'nobleNotes' => 'noble_notes',
            'noble_notes' => 'noble_notes',
            'heraldNotes' => 'herald_notes',
            'herald_notes' => 'herald_notes',
            'note' => 'note',
            'noteSubject' => 'note_subject',
            'note_subject' => 'note_subject',
        ]);
        $data = array_replace($data, $flatData);

        $ids = $this->resolveValue($config['bestowalIds'] ?? $config['ids'] ?? null, $context);
        if (is_array($ids)) {
            $data['ids'] = array_values($ids);
        }

        return $data;
    }

    /**
     * Build ad-hoc bestowal input data from nested or flat workflow params.
     *
     * @param array $context Current workflow context
     * @param array $config Workflow node/action config
     * @return array<string, mixed>
     */
    private function extractAdHocBestowalData(array $context, array $config): array
    {
        $data = $this->resolveConfigArray($config['data'] ?? null, $context);

        return array_replace($data, $this->mapConfigFields($context, $config, [
            'memberId' => 'memberId',
            'member_id' => 'member_id',
            'awardIds' => 'awardIds',
            'award_ids' => 'award_ids',
            'gatheringId' => 'gatheringId',
            'gathering_id' => 'gathering_id',
            'bestowedAt' => 'bestowedAt',
            'bestowed_at' => 'bestowed_at',
            'reason' => 'reason',
            'nobleNotes' => 'nobleNotes',
            'noble_notes' => 'noble_notes',
            'heraldNotes' => 'heraldNotes',
            'herald_notes' => 'herald_notes',
            'callIntoCourt' => 'callIntoCourt',
            'call_into_court' => 'call_into_court',
            'courtAvailability' => 'courtAvailability',
            'court_availability' => 'court_availability',
            'personToNotify' => 'personToNotify',
            'person_to_notify' => 'person_to_notify',
        ]));
    }

    /**
     * Extract a normalized authenticated requester context.
     *
     * @param array $context Current workflow context
     * @param array $config Workflow node/action config
     * @return array{id: int, sca_name: string, email_address: string, phone_number: string|null}|null
     */
    private function extractRequesterContext(array $context, array $config): ?array
    {
        $resolved = $this->resolveValue($config['requesterContext'] ?? null, $context);
        if (!is_array($resolved)) {
            return null;
        }

        $id = $resolved['id'] ?? $resolved['requesterId'] ?? null;
        $scaName = $resolved['sca_name'] ?? $resolved['scaName'] ?? null;
        $email = $resolved['email_address'] ?? $resolved['emailAddress'] ?? null;
        $phone = $resolved['phone_number'] ?? $resolved['phoneNumber'] ?? null;

        if ($id === null || $scaName === null || $email === null) {
            return null;
        }

        return [
            'id' => (int)$id,
            'sca_name' => (string)$scaName,
            'email_address' => (string)$email,
            'phone_number' => $phone !== null ? (string)$phone : null,
        ];
    }

    /**
     * Convert a service mutation result to workflow-engine action output.
     *
     * @param array<string, mixed> $result Shared mutation service result
     * @return array<string, mixed>
     */
    private function formatMutationResult(array $result): array
    {
        if (!($result['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $result['message'] ?? $result['errorCode'] ?? 'Mutation failed',
                'data' => [
                    'errors' => $result['errors'] ?? [],
                    'errorCode' => $result['errorCode'] ?? null,
                ],
            ];
        }

        $data = is_array($result['output'] ?? null) ? $result['output'] : [];
        if (isset($result['eventPayload']) && is_array($result['eventPayload'])) {
            $data['eventPayload'] = $result['eventPayload'];
        }
        if (!empty($result['eventName'])) {
            $data['eventName'] = (string)$result['eventName'];
        }

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * Resolve recommendation IDs from workflow config.
     *
     * @param array $context Current workflow context
     * @param array $config Workflow node/action config
     * @return array<int, int>
     */
    private function extractRecommendationIds(array $context, array $config): array
    {
        $ids = $this->resolveValue($config['recommendationIds'] ?? $config['ids'] ?? null, $context);
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Resolve an actor ID when present.
     *
     * @param array $context Current workflow context
     * @param array $config Workflow node/action config
     * @return int|null
     */
    private function extractNullableActorId(array $context, array $config): ?int
    {
        $actorId = $this->resolveValue($config['actorId'] ?? null, $context);

        return $actorId === null || $actorId === '' ? null : (int)$actorId;
    }

    /**
     * Resolve a nested object/array config value.
     *
     * @param mixed $value Config value or context path
     * @param array $context Current workflow context
     * @return array<string, mixed>
     */
    private function resolveConfigArray(mixed $value, array $context): array
    {
        $resolved = $this->resolveValue($value, $context);

        return is_array($resolved) ? $resolved : [];
    }

    /**
     * Map flat action params to recommendation field names.
     *
     * @param array $context Current workflow context
     * @param array $config Workflow node/action config
     * @param array<string, string> $fieldMap Config key => entity field
     * @return array<string, mixed>
     */
    private function mapConfigFields(array $context, array $config, array $fieldMap): array
    {
        $data = [];
        foreach ($fieldMap as $configKey => $entityField) {
            if (!array_key_exists($configKey, $config)) {
                continue;
            }

            $data[$entityField] = $this->resolveValue($config[$configKey], $context);
        }

        return $data;
    }
}
