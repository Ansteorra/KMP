<?php
declare(strict_types=1);

namespace Awards\Services;

use App\KMP\StaticHelpers;
use App\KMP\WorkflowApprovalDecisionOptions;
use App\Model\Entity\WorkflowApproval;
use App\Services\WorkflowEngine\WorkflowContextAwareTrait;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationFeedbackRequestRecipient;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use RuntimeException;
use Throwable;

/**
 * Workflow action implementations for award recommendation operations.
 *
 * Provides workflow-backed award recommendation and bestowal operations.
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
    private RecommendationSubmissionService $submissionService;
    private RecommendationUpdateService $updateService;
    private RecommendationGroupingService $groupingService;
    private RecommendationDeletionService $deletionService;
    private RecommendationStateLogService $stateLogService;
    private BestowalCreationService $bestowalCreationService;
    private BestowalHandoffService $bestowalHandoffService;
    private BestowalTransitionService $bestowalTransitionService;
    private BestowalRecommendationSyncService $bestowalRecommendationSyncService;
    private BestowalCancellationService $bestowalCancellationService;
    private BestowalUpdateService $bestowalUpdateService;
    private AdHocBestowalService $adHocBestowalService;
    private RecommendationApprovalProcessService $approvalProcessService;

    /**
     * @param \Awards\Services\RecommendationSubmissionService|null $submissionService Submission workflow service.
     * @param \Awards\Services\RecommendationUpdateService|null $updateService Update workflow service.
     * @param \Awards\Services\RecommendationGroupingService|null $groupingService Grouping workflow service.
     * @param \Awards\Services\RecommendationDeletionService|null $deletionService Deletion workflow service.
     * @param \Awards\Services\RecommendationStateLogService|null $stateLogService State-log workflow service.
     * @param \Awards\Services\BestowalCreationService|null $bestowalCreationService Bestowal creation service.
     * @param \Awards\Services\BestowalTransitionService|null $bestowalTransitionService Bestowal transition service.
     * @param \Awards\Services\BestowalRecommendationSyncService|null $bestowalRecommendationSyncService Bestowal sync service.
     * @param \Awards\Services\BestowalCancellationService|null $bestowalCancellationService Bestowal cancellation service.
     * @param \Awards\Services\BestowalUpdateService|null $bestowalUpdateService Bestowal update service.
     * @param \Awards\Services\AdHocBestowalService|null $adHocBestowalService Ad-hoc bestowal service.
     * @param \Awards\Services\RecommendationApprovalProcessService|null $approvalProcessService Approval process service.
     * @param \Awards\Services\BestowalHandoffService|null $bestowalHandoffService Workflow-aware bestowal handoff service.
     */
    public function __construct(
        ?RecommendationSubmissionService $submissionService = null,
        ?RecommendationUpdateService $updateService = null,
        ?RecommendationGroupingService $groupingService = null,
        ?RecommendationDeletionService $deletionService = null,
        ?RecommendationStateLogService $stateLogService = null,
        ?BestowalCreationService $bestowalCreationService = null,
        ?BestowalTransitionService $bestowalTransitionService = null,
        ?BestowalRecommendationSyncService $bestowalRecommendationSyncService = null,
        ?BestowalCancellationService $bestowalCancellationService = null,
        ?BestowalUpdateService $bestowalUpdateService = null,
        ?AdHocBestowalService $adHocBestowalService = null,
        ?RecommendationApprovalProcessService $approvalProcessService = null,
        ?BestowalHandoffService $bestowalHandoffService = null,
    ) {
        $this->recommendationsTable = $this->fetchTable('Awards.Recommendations');
        $this->bestowalsTable = $this->fetchTable('Awards.Bestowals');
        $this->stateLogService = $stateLogService ?? new RecommendationStateLogService();
        $this->groupingService = $groupingService ?? new RecommendationGroupingService(
            $this->recommendationsTable,
            $this->stateLogService,
        );
        $this->submissionService = $submissionService ?? new RecommendationSubmissionService($this->stateLogService);
        $this->updateService = $updateService ?? new RecommendationUpdateService();
        $this->deletionService = $deletionService ?? new RecommendationDeletionService($this->groupingService);
        $this->bestowalTransitionService = $bestowalTransitionService ?? new BestowalTransitionService();
        $this->bestowalRecommendationSyncService = $bestowalRecommendationSyncService
            ?? new BestowalRecommendationSyncService();
        $this->bestowalCreationService = $bestowalCreationService ?? new BestowalCreationService();
        $this->bestowalHandoffService = $bestowalHandoffService ?? new BestowalHandoffService(
            creationService: $this->bestowalCreationService,
        );
        $this->bestowalCancellationService = $bestowalCancellationService ?? new BestowalCancellationService(
            transitionService: $this->bestowalTransitionService,
            syncService: $this->bestowalRecommendationSyncService,
        );
        $this->bestowalUpdateService = $bestowalUpdateService ?? new BestowalUpdateService(
            transitionService: $this->bestowalTransitionService,
            syncService: $this->bestowalRecommendationSyncService,
        );
        $this->adHocBestowalService = $adHocBestowalService ?? new AdHocBestowalService();
        $this->approvalProcessService = $approvalProcessService ?? new RecommendationApprovalProcessService();
    }

    /**
     * Start the configured recommendation approval process projection.
     *
     * @param array $context Current workflow context.
     * @param array $config Node config with recommendationId and optional actorId.
     * @return array<string, mixed>
     */
    public function startApprovalProcess(array $context, array $config): array
    {
        $result = $this->approvalProcessService->startProcess($context, $config);
        $data = $result->getData() ?? [];

        return [
            'success' => $result->isSuccess(),
            'error' => $result->getError(),
        ] + $data + $this->currentApprovalStepContextUpdate($result->isSuccess(), $data);
    }

    /**
     * Advance the recommendation approval process projection after an approval response.
     *
     * @param array $context Current workflow context.
     * @param array $config Node config.
     * @return array<string, mixed>
     */
    public function advanceApprovalProcess(array $context, array $config): array
    {
        $result = $this->approvalProcessService->advanceProcess($context, $config);
        $data = $result->getData() ?? [];

        return [
            'success' => $result->isSuccess(),
            'error' => $result->getError(),
        ] + $data + $this->currentApprovalStepContextUpdate($result->isSuccess(), $data);
    }

    /**
     * Publish the active approval-step output at a stable workflow context path for reusable approval gates.
     *
     * @param bool $success Whether the workflow action succeeded.
     * @param array<string, mixed> $data Action data payload.
     * @return array<string, mixed>
     */
    private function currentApprovalStepContextUpdate(bool $success, array $data): array
    {
        if (
            !$success
            || !empty($data['completed'])
            || empty($data['approvalApproverConfig'])
            || empty($data['requiredCount'])
        ) {
            return [];
        }

        return [
            '_contextUpdates' => [
                'awardApprovalCurrentStep' => [
                    'approvalApproverConfig' => $data['approvalApproverConfig'],
                    'requiredCount' => $data['requiredCount'],
                    'currentStepKey' => $data['currentStepKey'] ?? null,
                    'currentStepLabel' => $data['currentStepLabel'] ?? null,
                ],
            ],
        ];
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
     * Create a workflow approval for a recommendation feedback recipient and link it
     * back to the recipient row so the recipient can respond from the approvals queue.
     *
     * Invoked by the awards-recommendation-feedback-request workflow action node. Fires
     * once per recipient (one workflow instance per RecommendationFeedbackRequested event).
     * Idempotent at the recipient-row level: a replayed event returns the existing approval
     * id instead of creating a duplicate. Throws on any invariant violation so the engine
     * marks the action node failed rather than silently advancing to the end node.
     *
     * @param array $context Workflow context (includes instanceId and the trigger payload).
     * @param array $config Resolved node config: recipientId (member id), feedbackRequestRecipientId
     *     (recipient row id), deadline (ATOM string), nodeId (action node id), and optional decisionPromptLabel.
     * @return array Output with success and approvalId.
     * @throws \RuntimeException When required context is missing, the execution log or recipient
     *     row cannot be resolved, the recipient is not pending, or linking the approval fails.
     */
    public function createFeedbackApproval(array $context, array $config): array
    {
        $instanceId = (int)($context['instanceId'] ?? 0);
        $nodeId = (string)($config['nodeId'] ?? 'create-feedback-approval');
        $recipientMemberId = (int)($config['recipientId'] ?? 0);
        $recipientRowId = (int)($config['feedbackRequestRecipientId'] ?? 0);

        if ($instanceId <= 0 || $recipientMemberId <= 0 || $recipientRowId <= 0) {
            throw new RuntimeException(sprintf(
                'CreateFeedbackApproval missing required context '
                    . '(instanceId=%d, recipientId=%d, feedbackRequestRecipientId=%d).',
                $instanceId,
                $recipientMemberId,
                $recipientRowId,
            ));
        }

        $deadline = $this->parseAtomDeadline($config['deadline'] ?? null);
        $decisionOptions = WorkflowApprovalDecisionOptions::normalizeOptions($config);
        $decisionPromptLabel = trim((string)$this->resolveValue($config['decisionPromptLabel'] ?? '', $context));

        // The engine persists the action node's execution log (STATUS_RUNNING) before invoking
        // this handler; resolve its id to satisfy the required execution_log_id on the approval.
        $logsTable = $this->fetchTable('WorkflowExecutionLogs');
        $log = $logsTable->find()
            ->select(['id'])
            ->where(['workflow_instance_id' => $instanceId, 'node_id' => $nodeId])
            ->orderBy(['id' => 'DESC'])
            ->first();
        if ($log === null) {
            throw new RuntimeException(
                "CreateFeedbackApproval could not resolve execution log for instance #{$instanceId} node '{$nodeId}'.",
            );
        }
        $executionLogId = (int)$log->id;

        $recipientsTable = $this->fetchTable('Awards.RecommendationFeedbackRequestRecipients');
        $approvalsTable = $this->fetchTable('WorkflowApprovals');

        return $recipientsTable->getConnection()->transactional(function () use (
            $recipientsTable,
            $approvalsTable,
            $recipientRowId,
            $recipientMemberId,
            $instanceId,
            $nodeId,
            $executionLogId,
            $deadline,
            $decisionOptions,
            $decisionPromptLabel,
            $config,
        ) {
            $recipient = $recipientsTable->find()
                ->where(['id' => $recipientRowId])
                ->first();
            if ($recipient === null) {
                throw new RuntimeException("CreateFeedbackApproval: recipient row #{$recipientRowId} not found.");
            }
            if ((int)$recipient->recipient_id !== $recipientMemberId) {
                throw new RuntimeException(
                    "CreateFeedbackApproval: recipient row #{$recipientRowId} member mismatch "
                    . "(row={$recipient->recipient_id}, expected={$recipientMemberId}).",
                );
            }
            if (!empty($recipient->workflow_approval_id)) {
                return ['success' => true, 'approvalId' => (int)$recipient->workflow_approval_id, 'idempotent' => true];
            }
            if ($recipient->status !== RecommendationFeedbackRequestRecipient::STATUS_PENDING) {
                throw new RuntimeException(
                    "CreateFeedbackApproval: recipient row #{$recipientRowId} is not pending "
                        . "(status={$recipient->status}).",
                );
            }

            $approverConfig = [
                'feedback_response' => true,
                'requires_comment' => !array_key_exists('requiresComment', $config)
                    || filter_var($config['requiresComment'], FILTER_VALIDATE_BOOLEAN),
                'member_id' => $recipientMemberId,
            ];
            if ($decisionOptions !== []) {
                $approverConfig['decision_options'] = $decisionOptions;
            }
            if ($decisionPromptLabel !== '') {
                $approverConfig['decision_prompt_label'] = $decisionPromptLabel;
            }

            $approval = $approvalsTable->newEntity([
                'workflow_instance_id' => $instanceId,
                'node_id' => $nodeId,
                'execution_log_id' => $executionLogId,
                'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
                'approver_config' => $approverConfig,
                'current_approver_id' => $recipientMemberId,
                'required_count' => 1,
                'approved_count' => 0,
                'rejected_count' => 0,
                'status' => WorkflowApproval::STATUS_PENDING,
                'allow_parallel' => false,
                'deadline' => $deadline,
                'escalation_config' => null,
                'approval_token' => StaticHelpers::generateToken(32),
            ]);
            $approvalsTable->saveOrFail($approval);

            // Atomic link: only attach when the row is still unlinked, guarding against a
            // replayed event racing a second instance. A rolled-back txn discards the approval.
            $affected = $recipientsTable->updateAll(
                ['workflow_approval_id' => $approval->id],
                ['id' => $recipientRowId, 'workflow_approval_id IS' => null],
            );
            if ($affected !== 1) {
                throw new RuntimeException(
                    "CreateFeedbackApproval: failed to link approval to recipient row #{$recipientRowId} "
                        . "(affected={$affected}).",
                );
            }

            return ['success' => true, 'approvalId' => (int)$approval->id];
        });
    }

    /**
     * Parse an ATOM/ISO-8601 deadline string into a DateTime, tolerating empties and bad input.
     *
     * @param mixed $value Raw deadline value from the trigger payload.
     * @return \Cake\I18n\DateTime|null Parsed deadline, or null when absent/unparseable.
     */
    private function parseAtomDeadline(mixed $value): ?DateTime
    {
        if (empty($value) || !is_string($value)) {
            return null;
        }
        try {
            return new DateTime($value);
        } catch (Throwable $e) {
            Log::warning('CreateFeedbackApproval: unparseable deadline "' . $value . '": ' . $e->getMessage());

            return null;
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

            return $this->bestowalHandoffService->createBestowal($recommendationId, $actorId);
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

            return $this->bestowalHandoffService->createBestowals($recommendationIds, $actorId);
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
     * Record an ad-hoc bestowal without requiring linked recommendations.
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
     * Build mutation input data from either a nested data payload or flat config fields.
     *
     * @param array $context Current workflow context
     * @param array $config Workflow node/action config
     * @return array<string, mixed>
     */
    private function extractRecommendationData(array $context, array $config): array
    {
        $fieldMap = [
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
        ];
        $data = $this->resolveConfigArray($config['data'] ?? null, $context);
        $data = $this->mapPayloadFields($data, $fieldMap);
        $flatData = $this->mapConfigFields($context, $config, $fieldMap);

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
            'memberPublicId' => 'memberPublicId',
            'member_public_id' => 'member_public_id',
            'awardId' => 'awardId',
            'award_id' => 'award_id',
            'awardIds' => 'awardIds',
            'award_ids' => 'award_ids',
            'gatheringId' => 'gatheringId',
            'gathering_id' => 'gathering_id',
            'gatheringScheduledActivityId' => 'gatheringScheduledActivityId',
            'gathering_scheduled_activity_id' => 'gathering_scheduled_activity_id',
            'bestowedAt' => 'bestowedAt',
            'bestowed_at' => 'bestowed_at',
            'state' => 'state',
            'stackRank' => 'stackRank',
            'stack_rank' => 'stack_rank',
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
            'closeReason' => 'closeReason',
            'close_reason' => 'close_reason',
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

        $workflowResult = [
            'success' => true,
            'data' => $data,
        ];

        return $workflowResult + [
            '_contextUpdates' => [
                'workflowResult' => $workflowResult,
            ],
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

    /**
     * Map camelCase workflow payload keys to entity field names while preserving snake_case inputs.
     *
     * @param array<string, mixed> $payload Workflow payload data.
     * @param array<string, string> $fieldMap Payload key => entity field.
     * @return array<string, mixed>
     */
    private function mapPayloadFields(array $payload, array $fieldMap): array
    {
        foreach ($fieldMap as $payloadKey => $entityField) {
            if (!array_key_exists($payloadKey, $payload) || array_key_exists($entityField, $payload)) {
                continue;
            }

            $payload[$entityField] = $payload[$payloadKey];
        }

        return $payload;
    }
}
