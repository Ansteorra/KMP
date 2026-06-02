<?php
declare(strict_types=1);

namespace Awards\Services;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationFeedbackRequest;
use Awards\Model\Entity\RecommendationFeedbackRequestRecipient;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use RuntimeException;
use Throwable;

/**
 * Creates and manages out-of-band feedback requests for award recommendations.
 */
class RecommendationFeedbackService
{
    use LocatorAwareTrait;

    public const EVENT_FEEDBACK_REQUESTED = 'Awards.RecommendationFeedbackRequested';
    public const EVENT_FEEDBACK_RETURNED = 'Awards.RecommendationFeedbackReturned';
    public const EVENT_FEEDBACK_RETRACTED = 'Awards.RecommendationFeedbackRetracted';
    public const EVENT_FEEDBACK_EXPIRED = 'Awards.RecommendationFeedbackExpired';

    private Table $recommendationsTable;
    private Table $requestsTable;
    private Table $itemsTable;
    private Table $recipientsTable;
    private Table $workflowApprovalsTable;
    private Table $workflowApprovalResponsesTable;
    private Table $notesTable;
    private ?TriggerDispatcher $triggerDispatcher;

    /**
     * Load tables used by the feedback lifecycle.
     */
    public function __construct(?TriggerDispatcher $triggerDispatcher = null)
    {
        $this->triggerDispatcher = $triggerDispatcher;
        $this->recommendationsTable = $this->fetchTable('Awards.Recommendations');
        $this->requestsTable = $this->fetchTable('Awards.RecommendationFeedbackRequests');
        $this->itemsTable = $this->fetchTable('Awards.RecommendationFeedbackRequestItems');
        $this->recipientsTable = $this->fetchTable('Awards.RecommendationFeedbackRequestRecipients');
        $this->workflowApprovalsTable = $this->fetchTable('WorkflowApprovals');
        $this->workflowApprovalResponsesTable = $this->fetchTable('WorkflowApprovalResponses');
        $this->notesTable = $this->fetchTable('Notes');
    }

    /**
     * Create one feedback request per selected recommendation or recommendation group.
     *
     * @param array<int|string> $recommendationIds Selected recommendation IDs.
     * @param array<int|string> $recipientIds Selected recipient member IDs.
     * @param int $requesterId Noble/request owner member ID.
     * @param string|null $message Optional request instructions.
     * @param string|null $deadline Optional date/time string.
     * @return \App\Services\ServiceResult
     */
    public function createRequests(
        array $recommendationIds,
        array $recipientIds,
        int $requesterId,
        ?string $message = null,
        ?string $deadline = null,
    ): ServiceResult {
        $recommendationIds = $this->normalizeIds($recommendationIds);
        $recipientIds = array_values(array_diff($this->normalizeIds($recipientIds), [$requesterId]));

        if ($recommendationIds === []) {
            return new ServiceResult(false, 'Select at least one recommendation.');
        }
        if ($recipientIds === []) {
            return new ServiceResult(false, 'Select at least one recipient other than yourself.');
        }

        $deadlineValue = $deadline ? new DateTime($deadline) : null;
        $connection = ConnectionManager::get('default');
        $workflowEvents = [];

        try {
            $created = $connection->transactional(function () use (
                $recommendationIds,
                $recipientIds,
                $requesterId,
                $message,
                $deadlineValue,
                &$workflowEvents,
            ) {
                $requests = [];
                foreach ($this->buildRequestUnits($recommendationIds) as $unit) {
                    $unitRecommendationIds = array_map(
                        static fn(Recommendation $recommendation): int => (int)$recommendation->id,
                        $unit,
                    );
                    foreach ($recipientIds as $recipientId) {
                        if ($this->hasOpenRequest($unitRecommendationIds, $recipientId)) {
                            throw new RuntimeException(
                                'One or more selected recipients already has an open feedback request.',
                            );
                        }
                    }

                    $request = $this->requestsTable->newEntity([
                        'requester_id' => $requesterId,
                        'status' => RecommendationFeedbackRequest::STATUS_PENDING,
                        'message' => $message,
                        'deadline' => $deadlineValue,
                        'created_by' => $requesterId,
                        'modified_by' => $requesterId,
                    ]);
                    $this->requestsTable->saveOrFail($request);

                    foreach ($unit as $recommendation) {
                        $item = $this->itemsTable->newEntity([
                            'feedback_request_id' => $request->id,
                            'recommendation_id' => $recommendation->id,
                            'snapshot' => $this->buildSnapshot($recommendation),
                        ]);
                        $this->itemsTable->saveOrFail($item);
                    }

                    foreach ($recipientIds as $recipientId) {
                        $recipient = $this->recipientsTable->newEntity([
                            'feedback_request_id' => $request->id,
                            'recipient_id' => $recipientId,
                            'status' => RecommendationFeedbackRequestRecipient::STATUS_PENDING,
                        ]);
                        $this->recipientsTable->saveOrFail($recipient);

                        $workflowEvents[] = [
                            'eventName' => self::EVENT_FEEDBACK_REQUESTED,
                            'eventData' => $this->buildWorkflowEventPayload((int)$recipient->id),
                            'triggeredBy' => $requesterId,
                        ];
                    }

                    $requests[] = (int)$request->id;
                }

                return $requests;
            });
        } catch (Throwable $e) {
            Log::error('Recommendation feedback request creation failed: ' . $e->getMessage());

            return new ServiceResult(false, $e->getMessage());
        }

        $this->dispatchWorkflowEvents($workflowEvents);

        return new ServiceResult(true, null, ['requestIds' => $created]);
    }

    /**
     * Mark feedback returned from a workflow approval response.
     */
    public function recordFeedbackFromApproval(int $approvalId, int $memberId, string $comment): ServiceResult
    {
        $comment = trim($comment);
        if ($comment === '') {
            return new ServiceResult(false, 'Feedback comment is required.');
        }

        try {
            $workflowEvents = [];
            $result = ConnectionManager::get('default')->transactional(function () use (
                $approvalId,
                $memberId,
                $comment,
                &$workflowEvents,
            ) {
                $recipientRow = $this->recipientsTable->find()
                    ->select([
                        'recipient_row_id' => $this->recipientsTable->aliasField('id'),
                        'recipient_status' => $this->recipientsTable->aliasField('status'),
                        'feedback_request_id' => $this->recipientsTable->aliasField('feedback_request_id'),
                    ])
                    ->where([
                        $this->recipientsTable->aliasField('workflow_approval_id') => $approvalId,
                        $this->recipientsTable->aliasField('recipient_id') => $memberId,
                    ])
                    ->disableHydration()
                    ->first();

                if (!$recipientRow) {
                    return new ServiceResult(false, 'Feedback request recipient was not found.');
                }
                $recipientId = $recipientRow['recipient_row_id'] ?? null;
                if (!$recipientId) {
                    return new ServiceResult(false, 'Feedback request recipient was not found.');
                }
                if ($recipientRow['recipient_status'] !== RecommendationFeedbackRequestRecipient::STATUS_PENDING) {
                    return new ServiceResult(false, 'Feedback has already been returned, retracted, or expired.');
                }

                $response = $this->workflowApprovalResponsesTable->find()
                    ->where([
                        'workflow_approval_id' => $approvalId,
                        'member_id' => $memberId,
                    ])
                    ->orderByDesc('id')
                    ->first();
                if (!$response instanceof WorkflowApprovalResponse) {
                    $approval = $this->workflowApprovalsTable->find()
                        ->select(['id', 'status'])
                        ->where(['id' => $approvalId])
                        ->first();
                    if (
                        $approval instanceof WorkflowApproval
                        && $approval->status === WorkflowApproval::STATUS_EXPIRED
                    ) {
                        return new ServiceResult(false, 'Feedback request has expired.');
                    }

                    return new ServiceResult(false, 'Feedback response was not found.');
                }

                $now = DateTime::now();
                $this->recipientsTable->updateAll(
                    [
                        'status' => RecommendationFeedbackRequestRecipient::STATUS_RESPONDED,
                        'response_comment' => $comment,
                        'responded_at' => $now,
                        'workflow_approval_response_id' => $response->id,
                    ],
                    ['id' => (int)$recipientId],
                );

                $items = $this->itemsTable->find()
                    ->select(['recommendation_id'])
                    ->where(['feedback_request_id' => $recipientRow['feedback_request_id']])
                    ->all();
                foreach ($items as $item) {
                    $this->createFeedbackNote(
                        (int)$item->recommendation_id,
                        $memberId,
                        (int)$recipientRow['feedback_request_id'],
                        $comment,
                    );
                }

                $this->refreshRequestStatus((int)$recipientRow['feedback_request_id']);
                $workflowEvents[] = [
                    'eventName' => self::EVENT_FEEDBACK_RETURNED,
                    'eventData' => $this->buildWorkflowEventPayload((int)$recipientId, [
                        'responseComment' => $comment,
                    ]),
                    'triggeredBy' => $memberId,
                ];

                return new ServiceResult(true);
            });

            if ($result instanceof ServiceResult && $result->isSuccess()) {
                $this->dispatchWorkflowEvents($workflowEvents);
            }

            return $result ?: new ServiceResult(false, 'Feedback response failed.');
        } catch (Throwable $e) {
            Log::error(sprintf(
                'Recommendation feedback response failed: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
            ));

            return new ServiceResult(false, 'Feedback response could not be recorded.');
        }
    }

    /**
     * Mark the feedback recipient linked to an expired workflow approval as expired.
     */
    public function expireFeedbackForApproval(int $approvalId, ?DateTime $expiredAt = null): ServiceResult
    {
        $expiredAt ??= DateTime::now();

        try {
            $workflowEvents = [];
            $result = ConnectionManager::get('default')->transactional(function () use (
                $approvalId,
                $expiredAt,
                &$workflowEvents,
            ) {
                $recipientRow = $this->recipientsTable->find()
                    ->select([
                        'recipient_row_id' => $this->recipientsTable->aliasField('id'),
                        'recipient_status' => $this->recipientsTable->aliasField('status'),
                        'feedback_request_id' => $this->recipientsTable->aliasField('feedback_request_id'),
                    ])
                    ->where([$this->recipientsTable->aliasField('workflow_approval_id') => $approvalId])
                    ->disableHydration()
                    ->first();

                if (!$recipientRow) {
                    return new ServiceResult(true, null, ['expired' => false]);
                }
                if ($recipientRow['recipient_status'] !== RecommendationFeedbackRequestRecipient::STATUS_PENDING) {
                    return new ServiceResult(true, null, ['expired' => false]);
                }

                $updated = $this->recipientsTable->updateAll(
                    [
                        'status' => RecommendationFeedbackRequestRecipient::STATUS_EXPIRED,
                        'expired_at' => $expiredAt,
                    ],
                    [
                        'id' => (int)$recipientRow['recipient_row_id'],
                        'status' => RecommendationFeedbackRequestRecipient::STATUS_PENDING,
                    ],
                );

                if ($updated < 1) {
                    return new ServiceResult(true, null, ['expired' => false]);
                }

                $this->refreshRequestStatus((int)$recipientRow['feedback_request_id'], $expiredAt);
                $workflowEvents[] = [
                    'eventName' => self::EVENT_FEEDBACK_EXPIRED,
                    'eventData' => $this->buildWorkflowEventPayload((int)$recipientRow['recipient_row_id'], [
                        'expiredAt' => $expiredAt->format(DATE_ATOM),
                    ]),
                    'triggeredBy' => null,
                ];

                return new ServiceResult(true, null, ['expired' => true]);
            });

            if ($result instanceof ServiceResult && $result->isSuccess()) {
                $this->dispatchWorkflowEvents($workflowEvents);
            }

            return $result ?: new ServiceResult(false, 'Feedback expiration failed.');
        } catch (Throwable $e) {
            Log::error('Recommendation feedback expiration failed: ' . $e->getMessage());

            return new ServiceResult(false, 'Feedback request could not be expired.');
        }
    }

    /**
     * Retract pending feedback recipients for a request.
     */
    public function retractRequest(int $requestId, int $actorId, bool $adminOverride = false): ServiceResult
    {
        try {
            $workflowEvents = [];
            $result = ConnectionManager::get('default')->transactional(function () use (
                $requestId,
                $actorId,
                $adminOverride,
                &$workflowEvents,
            ) {
                $request = $this->requestsTable->find()
                    ->contain(['Recipients'])
                    ->where(['RecommendationFeedbackRequests.id' => $requestId])
                    ->first();

                if (!$request) {
                    return new ServiceResult(false, 'Feedback request not found.');
                }
                if ((int)$request->requester_id !== $actorId && !$adminOverride) {
                    return new ServiceResult(false, 'Only the requester can retract this feedback request.');
                }
                if ($request->status !== RecommendationFeedbackRequest::STATUS_PENDING) {
                    return new ServiceResult(false, 'Only pending feedback requests can be retracted.');
                }

                $now = DateTime::now();
                $pendingApprovalIds = [];
                $retractedRecipientIds = [];
                foreach ($request->recipients as $recipient) {
                    if ($recipient->status !== RecommendationFeedbackRequestRecipient::STATUS_PENDING) {
                        continue;
                    }
                    $recipient->status = RecommendationFeedbackRequestRecipient::STATUS_RETRACTED;
                    $recipient->retracted_at = $now;
                    $this->recipientsTable->saveOrFail($recipient);
                    $retractedRecipientIds[] = (int)$recipient->id;
                    if ($recipient->workflow_approval_id) {
                        $pendingApprovalIds[] = (int)$recipient->workflow_approval_id;
                    }
                }

                if ($pendingApprovalIds !== []) {
                    $this->workflowApprovalsTable->updateAll(
                        ['status' => WorkflowApproval::STATUS_CANCELLED],
                        [
                            'id IN' => $pendingApprovalIds,
                            'status' => WorkflowApproval::STATUS_PENDING,
                        ],
                    );
                }

                $request->status = RecommendationFeedbackRequest::STATUS_RETRACTED;
                $request->retracted_at = $now;
                $request->modified_by = $actorId;
                $this->requestsTable->saveOrFail($request);
                foreach ($retractedRecipientIds as $recipientId) {
                    $workflowEvents[] = [
                        'eventName' => self::EVENT_FEEDBACK_RETRACTED,
                        'eventData' => $this->buildWorkflowEventPayload($recipientId, [
                            'retractedAt' => $now->format(DATE_ATOM),
                            'actorId' => $actorId,
                        ]),
                        'triggeredBy' => $actorId,
                    ];
                }

                return new ServiceResult(true);
            });

            if ($result instanceof ServiceResult && $result->isSuccess()) {
                $this->dispatchWorkflowEvents($workflowEvents);
            }

            return $result ?: new ServiceResult(false, 'Retraction failed.');
        } catch (Throwable $e) {
            Log::error('Recommendation feedback retraction failed: ' . $e->getMessage());

            return new ServiceResult(false, 'Feedback request could not be retracted.');
        }
    }

    /**
     * Dispatch feedback workflow trigger events after domain state has committed.
     *
     * @param array<int, array{eventName: string, eventData: array<string, mixed>, triggeredBy: int|null}> $events
     */
    private function dispatchWorkflowEvents(array $events): void
    {
        foreach ($events as $event) {
            try {
                if ($this->triggerDispatcher !== null) {
                    $this->triggerDispatcher->dispatch(
                        $event['eventName'],
                        $event['eventData'],
                        $event['triggeredBy'],
                    );
                } else {
                    EventManager::instance()->dispatch(new Event('Workflow.trigger', $this, [
                        'eventName' => $event['eventName'],
                        'eventData' => $event['eventData'],
                        'triggeredBy' => $event['triggeredBy'],
                    ]));
                }
            } catch (Throwable $e) {
                Log::warning(sprintf(
                    'Recommendation feedback workflow trigger "%s" failed: %s',
                    $event['eventName'],
                    $e->getMessage(),
                ));
            }
        }
    }

    /**
     * Build workflow trigger context for one feedback recipient activity.
     *
     * @param array<string, mixed> $extra Additional event-specific context
     * @return array<string, mixed>
     */
    private function buildWorkflowEventPayload(int $recipientRowId, array $extra = []): array
    {
        $recipient = $this->recipientsTable->find()
            ->contain([
                'RecipientMembers',
                'FeedbackRequests' => [
                    'Requesters',
                    'Items',
                ],
            ])
            ->where([$this->recipientsTable->aliasField('id') => $recipientRowId])
            ->firstOrFail();
        $request = $recipient->feedback_request;
        $items = $request->items ?? [];
        $recommendations = [];
        $recommendationIds = [];

        foreach ($items as $item) {
            $snapshot = is_array($item->snapshot) ? $item->snapshot : [];
            $recommendationId = (int)$item->recommendation_id;
            $recommendationIds[] = $recommendationId;
            $recommendations[] = [
                'recommendationId' => $recommendationId,
                'snapshot' => $snapshot,
            ];
        }
        $deadline = $request->deadline?->format(DATE_ATOM);

        return $extra + [
            'feedbackRequestId' => (int)$request->id,
            'feedbackRequestRecipientId' => (int)$recipient->id,
            'entityType' => 'Awards.RecommendationFeedbackRequests',
            'entityId' => (int)$request->id,
            'requestStatus' => (string)$request->status,
            'recipientStatus' => (string)$recipient->status,
            'requesterId' => (int)$request->requester_id,
            'requesterScaName' => $request->requester->sca_name ?? null,
            'requesterEmail' => $request->requester->email_address ?? null,
            'recipientId' => (int)$recipient->recipient_id,
            'recipientScaName' => $recipient->recipient_member->sca_name ?? null,
            'recipientEmail' => $recipient->recipient_member->email_address ?? null,
            'workflowInstanceId' => $request->workflow_instance_id ? (int)$request->workflow_instance_id : null,
            'workflowApprovalId' => $recipient->workflow_approval_id ? (int)$recipient->workflow_approval_id : null,
            'workflowApprovalResponseId' => $recipient->workflow_approval_response_id
                ? (int)$recipient->workflow_approval_response_id
                : null,
            'message' => $request->message,
            'deadline' => $deadline,
            'expires_on' => $deadline,
            'expiresOn' => $deadline,
            'respondedAt' => $recipient->responded_at?->format(DATE_ATOM),
            'retractedAt' => $recipient->retracted_at?->format(DATE_ATOM),
            'expiredAt' => $recipient->expired_at?->format(DATE_ATOM),
            'responseComment' => $recipient->response_comment,
            'recommendationIds' => $recommendationIds,
            'primaryRecommendationId' => $recommendationIds[0] ?? null,
            'recommendationCount' => count($recommendationIds),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * @param array<int> $recommendationIds
     * @return array<int, array<int, \Awards\Model\Entity\Recommendation>>
     */
    private function buildRequestUnits(array $recommendationIds): array
    {
        $unitHeadIds = [];
        foreach ($recommendationIds as $recommendationId) {
            $recommendation = $this->recommendationsTable->get($recommendationId);
            $headId = $recommendation->recommendation_group_id ?: $recommendation->id;
            $unitHeadIds[(int)$headId] = true;
        }

        $units = [];
        foreach (array_keys($unitHeadIds) as $headId) {
            $recommendations = $this->recommendationsTable->find()
                ->contain([
                    'Requesters',
                    'Members',
                    'Branches',
                    'Awards.Domains',
                    'Awards.Levels',
                    'AssignedGathering',
                    'Gatherings',
                ])
                ->where([
                    'OR' => [
                        'Recommendations.id' => $headId,
                        'Recommendations.recommendation_group_id' => $headId,
                    ],
                ])
                ->orderBy([
                    'Recommendations.recommendation_group_id IS NULL' => 'DESC',
                    'Recommendations.created' => 'ASC',
                ])
                ->all()
                ->toList();
            if ($recommendations !== []) {
                $units[] = $recommendations;
            }
        }

        return $units;
    }

    /**
     * Build the safe recipient-visible recommendation snapshot.
     */
    private function buildSnapshot(Recommendation $recommendation): array
    {
        $gatherings = [];
        foreach ($recommendation->gatherings ?? [] as $gathering) {
            $gatherings[] = $gathering->name;
        }
        if ($recommendation->assigned_gathering?->name) {
            $gatherings[] = $recommendation->assigned_gathering->name;
        }

        return [
            'recommendationId' => (int)$recommendation->id,
            'isGroupHead' => empty($recommendation->recommendation_group_id),
            'memberScaName' => (string)$recommendation->member_sca_name,
            'memberTitle' => $recommendation->member->title ?? null,
            'memberPronouns' => $recommendation->member->pronouns ?? null,
            'requesterScaName' => (string)$recommendation->requester_sca_name,
            'branchName' => $recommendation->branch->name ?? null,
            'awardName' => $recommendation->award->name ?? $recommendation->award->abbreviation ?? null,
            'awardAbbreviation' => $recommendation->award->abbreviation ?? null,
            'awardDomain' => $recommendation->award->domain->name ?? null,
            'awardLevel' => $recommendation->award->level->name ?? null,
            'reason' => (string)$recommendation->reason,
            'specialty' => $recommendation->specialty,
            'gatherings' => array_values(array_unique(array_filter($gatherings))),
        ];
    }

    /**
     * Mirror returned feedback into recommendation notes for owner visibility.
     */
    private function createFeedbackNote(int $recommendationId, int $authorId, int $requestId, string $comment): void
    {
        $note = $this->notesTable->newEmptyEntity();
        $note->author_id = $authorId;
        $note->entity_type = 'Awards.Recommendations';
        $note->entity_id = $recommendationId;
        $note->subject = sprintf('Feedback Request #%d', $requestId);
        $note->body = $comment;
        $note->private = false;

        $this->notesTable->saveOrFail($note);
    }

    /**
     * Resolve the request after all recipients have responded, expired, or been retracted.
     */
    private function refreshRequestStatus(int $requestId, ?DateTime $resolvedAt = null): void
    {
        $resolvedAt ??= DateTime::now();
        $pendingCount = $this->recipientsTable->find()
            ->where([
                'feedback_request_id' => $requestId,
                'status' => RecommendationFeedbackRequestRecipient::STATUS_PENDING,
            ])
            ->count();
        if ($pendingCount > 0) {
            return;
        }

        $request = $this->requestsTable->get($requestId);
        if ($request->status !== RecommendationFeedbackRequest::STATUS_PENDING) {
            return;
        }

        $expiredCount = $this->recipientsTable->find()
            ->where([
                'feedback_request_id' => $requestId,
                'status' => RecommendationFeedbackRequestRecipient::STATUS_EXPIRED,
            ])
            ->count();
        if ($expiredCount > 0) {
            $request->status = RecommendationFeedbackRequest::STATUS_EXPIRED;
            $request->expired_at = $resolvedAt;
            $this->requestsTable->saveOrFail($request);

            return;
        }

        $request->status = RecommendationFeedbackRequest::STATUS_COMPLETED;
        $request->completed_at = $resolvedAt;
        $this->requestsTable->saveOrFail($request);
    }

    /**
     * @param array<int> $recommendationIds
     */
    private function hasOpenRequest(array $recommendationIds, int $recipientId): bool
    {
        return $this->recipientsTable->find()
            ->innerJoinWith('FeedbackRequests.Items')
            ->where([
                $this->recipientsTable->aliasField('recipient_id') => $recipientId,
                $this->recipientsTable->aliasField('status') =>
                    RecommendationFeedbackRequestRecipient::STATUS_PENDING,
                'FeedbackRequests.status' => RecommendationFeedbackRequest::STATUS_PENDING,
                'Items.recommendation_id IN' => $recommendationIds,
            ])
            ->count() > 0;
    }

    /**
     * @param array<int|string> $ids
     * @return array<int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            if (is_numeric($id) && (int)$id > 0) {
                $normalized[(int)$id] = true;
            }
        }

        return array_keys($normalized);
    }
}
