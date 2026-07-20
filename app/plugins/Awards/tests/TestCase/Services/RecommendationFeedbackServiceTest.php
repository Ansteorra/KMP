<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationFeedbackRequest;
use Awards\Model\Entity\RecommendationFeedbackRequestRecipient;
use Awards\Services\RecommendationFeedbackService;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use ReflectionMethod;

class RecommendationFeedbackServiceTest extends BaseTestCase
{
    private Table $recommendationsTable;
    private Table $membersTable;
    private Table $awardsTable;
    private Table $requestsTable;
    private Table $itemsTable;
    private Table $recipientsTable;
    private Table $workflowDefinitionsTable;
    private Table $workflowInstancesTable;
    private Table $workflowExecutionLogsTable;
    private Table $workflowApprovalsTable;
    private Table $workflowApprovalResponsesTable;
    private Table $notesTable;
    private RecommendationFeedbackService $service;

    /**
     * Captured workflow trigger events during a test.
     *
     * @var array<int, array{eventName: string, eventData: array<string, mixed>, triggeredBy: int|null}>
     */
    private array $workflowEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->membersTable = $this->getTableLocator()->get('Members');
        $this->awardsTable = $this->getTableLocator()->get('Awards.Awards');
        $this->requestsTable = $this->getTableLocator()->get('Awards.RecommendationFeedbackRequests');
        $this->itemsTable = $this->getTableLocator()->get('Awards.RecommendationFeedbackRequestItems');
        $this->recipientsTable = $this->getTableLocator()->get('Awards.RecommendationFeedbackRequestRecipients');
        $this->workflowDefinitionsTable = $this->getTableLocator()->get('WorkflowDefinitions');
        $this->workflowInstancesTable = $this->getTableLocator()->get('WorkflowInstances');
        $this->workflowExecutionLogsTable = $this->getTableLocator()->get('WorkflowExecutionLogs');
        $this->workflowApprovalsTable = $this->getTableLocator()->get('WorkflowApprovals');
        $this->workflowApprovalResponsesTable = $this->getTableLocator()->get('WorkflowApprovalResponses');
        $this->notesTable = $this->getTableLocator()->get('Notes');
        $this->service = new RecommendationFeedbackService();
        $this->workflowEvents = [];
        EventManager::instance()->on('Workflow.trigger', function (EventInterface $event): void {
            $this->workflowEvents[] = $event->getData();
        });
    }

    protected function tearDown(): void
    {
        EventManager::instance()->off('Workflow.trigger');

        parent::tearDown();
    }

    public function testCreateRequestsDispatchesRequestedWorkflowTrigger(): void
    {
        $recommendation = $this->createRecommendation();
        $deadline = DateTime::now()->modify('+2 days')->format(DATE_ATOM);

        $result = $this->service->createRequests(
            [(int)$recommendation->id],
            [self::TEST_MEMBER_BRYCE_ID],
            self::ADMIN_MEMBER_ID,
            'Please share local context.',
            $deadline,
        );

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $request = $this->requestsTable->get($result->getData()['requestIds'][0]);
        $recipient = $this->recipientsTable->find()
            ->where(['feedback_request_id' => $request->id])
            ->firstOrFail();
        $this->assertNull($request->workflow_instance_id);
        $this->assertNull($recipient->workflow_approval_id);

        $event = $this->findWorkflowEvent(RecommendationFeedbackService::EVENT_FEEDBACK_REQUESTED);
        $this->assertNotNull($event);
        $this->assertSame(self::ADMIN_MEMBER_ID, $event['triggeredBy']);
        $this->assertSame(self::ADMIN_MEMBER_ID, $event['eventData']['requesterId']);
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, $event['eventData']['recipientId']);
        $this->assertSame([(int)$recommendation->id], $event['eventData']['recommendationIds']);
        $this->assertSame('Please share local context.', $event['eventData']['message']);
        $this->assertSame($deadline, $event['eventData']['deadline']);
        $this->assertSame($deadline, $event['eventData']['expires_on']);
        $this->assertSame($deadline, $event['eventData']['expiresOn']);
        $this->assertSame('Original recommendation body', $event['eventData']['recommendations'][0]['snapshot']['reason']);
    }

    public function testCreateRequestsUsesInjectedTriggerDispatcher(): void
    {
        $recommendation = $this->createRecommendation();
        $dispatcher = $this->createMock(TriggerDispatcher::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                RecommendationFeedbackService::EVENT_FEEDBACK_REQUESTED,
                $this->callback(function (array $eventData) use ($recommendation): bool {
                    return $eventData['requesterId'] === self::ADMIN_MEMBER_ID
                        && $eventData['recipientId'] === self::TEST_MEMBER_BRYCE_ID
                        && $eventData['recommendationIds'] === [(int)$recommendation->id];
                }),
                self::ADMIN_MEMBER_ID,
            )
            ->willReturn([new ServiceResult(true)]);
        $service = new RecommendationFeedbackService($dispatcher);

        $result = $service->createRequests(
            [(int)$recommendation->id],
            [self::TEST_MEMBER_BRYCE_ID],
            self::ADMIN_MEMBER_ID,
            'Please share local context.',
        );

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
    }

    public function testRecordFeedbackFromApprovedApprovalCreatesNoteAndCompletesRequest(): void
    {
        $recommendation = $this->createRecommendation();
        $request = $this->requestsTable->saveOrFail($this->requestsTable->newEntity([
            'requester_id' => self::ADMIN_MEMBER_ID,
            'status' => RecommendationFeedbackRequest::STATUS_PENDING,
            'created_by' => self::ADMIN_MEMBER_ID,
            'modified_by' => self::ADMIN_MEMBER_ID,
        ]));
        $this->itemsTable->saveOrFail($this->itemsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recommendation_id' => $recommendation->id,
            'snapshot' => ['recommendationId' => (int)$recommendation->id],
        ]));
        $approval = $this->createApprovedFeedbackApproval((int)$request->id);
        $response = $this->workflowApprovalResponsesTable->saveOrFail(
            $this->workflowApprovalResponsesTable->newEntity([
                'workflow_approval_id' => $approval->id,
                'member_id' => self::TEST_MEMBER_BRYCE_ID,
                'decision' => WorkflowApprovalResponse::DECISION_APPROVE,
                'comment' => 'Looks like a strong recommendation.',
                'responded_at' => DateTime::now(),
            ]),
        );
        $recipient = $this->recipientsTable->saveOrFail($this->recipientsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recipient_id' => self::TEST_MEMBER_BRYCE_ID,
            'workflow_approval_id' => $approval->id,
            'status' => RecommendationFeedbackRequestRecipient::STATUS_PENDING,
        ]));

        $result = $this->service->recordFeedbackFromApproval(
            (int)$approval->id,
            self::TEST_MEMBER_BRYCE_ID,
        );

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $updatedRecipient = $this->recipientsTable->get((int)$recipient->id);
        $this->assertSame(RecommendationFeedbackRequestRecipient::STATUS_RESPONDED, $updatedRecipient->status);
        $this->assertSame('Looks like a strong recommendation.', $updatedRecipient->response_comment);
        $this->assertSame((int)$response->id, (int)$updatedRecipient->workflow_approval_response_id);

        $updatedRequest = $this->requestsTable->get((int)$request->id);
        $this->assertSame(RecommendationFeedbackRequest::STATUS_COMPLETED, $updatedRequest->status);

        $note = $this->notesTable->find()
            ->where([
                'entity_type' => 'Awards.Recommendations',
                'entity_id' => $recommendation->id,
                'subject' => sprintf('Feedback Request #%d', $request->id),
            ])
            ->firstOrFail();
        $this->assertSame('Looks like a strong recommendation.', $note->body);
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, (int)$note->author_id);
        $this->assertFalse((bool)$note->private);

        $event = $this->findWorkflowEvent(RecommendationFeedbackService::EVENT_FEEDBACK_RETURNED);
        $this->assertNotNull($event);
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, $event['triggeredBy']);
        $this->assertSame((int)$request->id, $event['eventData']['feedbackRequestId']);
        $this->assertSame((int)$recipient->id, $event['eventData']['feedbackRequestRecipientId']);
        $this->assertSame(RecommendationFeedbackRequest::STATUS_COMPLETED, $event['eventData']['requestStatus']);
        $this->assertSame(RecommendationFeedbackRequestRecipient::STATUS_RESPONDED, $event['eventData']['recipientStatus']);
        $this->assertSame('Looks like a strong recommendation.', $event['eventData']['responseComment']);
        $this->assertSame((int)$response->id, $event['eventData']['workflowApprovalResponseId']);
    }

    public function testRecordFeedbackFromCustomDecisionApprovalCopiesAnswerAndCommentToNote(): void
    {
        $recommendation = $this->createRecommendation();
        $request = $this->requestsTable->saveOrFail($this->requestsTable->newEntity([
            'requester_id' => self::ADMIN_MEMBER_ID,
            'status' => RecommendationFeedbackRequest::STATUS_PENDING,
            'created_by' => self::ADMIN_MEMBER_ID,
            'modified_by' => self::ADMIN_MEMBER_ID,
        ]));
        $this->itemsTable->saveOrFail($this->itemsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recommendation_id' => $recommendation->id,
            'snapshot' => ['recommendationId' => (int)$recommendation->id],
        ]));
        $approval = $this->createApprovedFeedbackApproval((int)$request->id, [
            'decision_options' => [
                ['value' => 'support', 'label' => 'Support'],
                ['value' => 'oppose', 'label' => 'Oppose'],
            ],
        ]);
        $this->workflowApprovalResponsesTable->saveOrFail(
            $this->workflowApprovalResponsesTable->newEntity([
                'workflow_approval_id' => $approval->id,
                'member_id' => self::TEST_MEMBER_BRYCE_ID,
                'decision' => 'support',
                'comment' => 'The service record backs this.',
                'responded_at' => DateTime::now(),
            ]),
        );
        $recipient = $this->recipientsTable->saveOrFail($this->recipientsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recipient_id' => self::TEST_MEMBER_BRYCE_ID,
            'workflow_approval_id' => $approval->id,
            'status' => RecommendationFeedbackRequestRecipient::STATUS_PENDING,
        ]));

        $result = $this->service->recordFeedbackFromApproval(
            (int)$approval->id,
            self::TEST_MEMBER_BRYCE_ID,
            'The service record backs this.',
        );

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $note = $this->notesTable->find()
            ->where([
                'entity_type' => 'Awards.Recommendations',
                'entity_id' => $recommendation->id,
                'subject' => sprintf('Feedback Request #%d', $request->id),
            ])
            ->firstOrFail();
        $this->assertSame("Answer: Support\n\nThe service record backs this.", $note->body);

        $event = $this->findWorkflowEvent(RecommendationFeedbackService::EVENT_FEEDBACK_RETURNED);
        $this->assertNotNull($event);
        $this->assertSame('support', $event['eventData']['responseDecision']);
        $this->assertSame('Support', $event['eventData']['responseDecisionLabel']);
        $updatedRecipient = $this->recipientsTable->get((int)$recipient->id);
        $this->assertSame('The service record backs this.', $updatedRecipient->response_comment);
    }

    public function testExpireFeedbackForApprovalMarksRecipientAndRequestExpired(): void
    {
        $recommendation = $this->createRecommendation();
        $request = $this->createFeedbackRequestWithItem($recommendation);
        $approval = $this->createFeedbackApproval((int)$request->id);
        $recipient = $this->recipientsTable->saveOrFail($this->recipientsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recipient_id' => self::TEST_MEMBER_BRYCE_ID,
            'workflow_approval_id' => $approval->id,
            'status' => RecommendationFeedbackRequestRecipient::STATUS_PENDING,
        ]));
        $expiredAt = DateTime::now()->modify('-5 minutes');

        $result = $this->service->expireFeedbackForApproval((int)$approval->id, $expiredAt);

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $updatedRecipient = $this->recipientsTable->get((int)$recipient->id);
        $this->assertSame(RecommendationFeedbackRequestRecipient::STATUS_EXPIRED, $updatedRecipient->status);
        $this->assertNotNull($updatedRecipient->expired_at);

        $updatedRequest = $this->requestsTable->get((int)$request->id);
        $this->assertSame(RecommendationFeedbackRequest::STATUS_EXPIRED, $updatedRequest->status);
        $this->assertNotNull($updatedRequest->expired_at);

        $event = $this->findWorkflowEvent(RecommendationFeedbackService::EVENT_FEEDBACK_EXPIRED);
        $this->assertNotNull($event);
        $this->assertNull($event['triggeredBy']);
        $this->assertSame((int)$request->id, $event['eventData']['feedbackRequestId']);
        $this->assertSame((int)$recipient->id, $event['eventData']['feedbackRequestRecipientId']);
        $this->assertSame(RecommendationFeedbackRequest::STATUS_EXPIRED, $event['eventData']['requestStatus']);
        $this->assertSame(RecommendationFeedbackRequestRecipient::STATUS_EXPIRED, $event['eventData']['recipientStatus']);
        $this->assertNotEmpty($event['eventData']['expiredAt']);
    }

    public function testRetractRequestDispatchesRetractedWorkflowTriggerForEachPendingRecipient(): void
    {
        $recommendation = $this->createRecommendation();
        $request = $this->createFeedbackRequestWithItem($recommendation);
        $approval = $this->createFeedbackApproval((int)$request->id);
        $recipient = $this->recipientsTable->saveOrFail($this->recipientsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recipient_id' => self::TEST_MEMBER_BRYCE_ID,
            'workflow_approval_id' => $approval->id,
            'status' => RecommendationFeedbackRequestRecipient::STATUS_PENDING,
        ]));

        $result = $this->service->retractRequest((int)$request->id, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $event = $this->findWorkflowEvent(RecommendationFeedbackService::EVENT_FEEDBACK_RETRACTED);
        $this->assertNotNull($event);
        $this->assertSame(self::ADMIN_MEMBER_ID, $event['triggeredBy']);
        $this->assertSame(self::ADMIN_MEMBER_ID, $event['eventData']['actorId']);
        $this->assertSame((int)$request->id, $event['eventData']['feedbackRequestId']);
        $this->assertSame((int)$recipient->id, $event['eventData']['feedbackRequestRecipientId']);
        $this->assertSame(RecommendationFeedbackRequest::STATUS_RETRACTED, $event['eventData']['requestStatus']);
        $this->assertSame(RecommendationFeedbackRequestRecipient::STATUS_RETRACTED, $event['eventData']['recipientStatus']);
        $this->assertNotEmpty($event['eventData']['retractedAt']);
    }

    public function testExpireFeedbackForApprovalPreservesPendingRequestUntilAllRecipientsResolved(): void
    {
        $recommendation = $this->createRecommendation();
        $request = $this->createFeedbackRequestWithItem($recommendation);
        $expiredApproval = $this->createFeedbackApproval((int)$request->id);
        $pendingApproval = $this->createFeedbackApproval((int)$request->id, self::TEST_MEMBER_DEVON_ID);
        $this->recipientsTable->saveOrFail($this->recipientsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recipient_id' => self::TEST_MEMBER_BRYCE_ID,
            'workflow_approval_id' => $expiredApproval->id,
            'status' => RecommendationFeedbackRequestRecipient::STATUS_PENDING,
        ]));
        $this->recipientsTable->saveOrFail($this->recipientsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recipient_id' => self::TEST_MEMBER_DEVON_ID,
            'workflow_approval_id' => $pendingApproval->id,
            'status' => RecommendationFeedbackRequestRecipient::STATUS_PENDING,
        ]));

        $result = $this->service->expireFeedbackForApproval((int)$expiredApproval->id);

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $updatedRequest = $this->requestsTable->get((int)$request->id);
        $this->assertSame(RecommendationFeedbackRequest::STATUS_PENDING, $updatedRequest->status);
    }

    public function testExpireFeedbackForApprovalMarksMixedResponseRequestExpired(): void
    {
        $recommendation = $this->createRecommendation();
        $request = $this->createFeedbackRequestWithItem($recommendation);
        $expiredApproval = $this->createFeedbackApproval((int)$request->id);
        $respondedApproval = $this->createFeedbackApproval(
            (int)$request->id,
            self::TEST_MEMBER_DEVON_ID,
            WorkflowApproval::STATUS_APPROVED,
        );
        $this->recipientsTable->saveOrFail($this->recipientsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recipient_id' => self::TEST_MEMBER_BRYCE_ID,
            'workflow_approval_id' => $expiredApproval->id,
            'status' => RecommendationFeedbackRequestRecipient::STATUS_PENDING,
        ]));
        $this->recipientsTable->saveOrFail($this->recipientsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recipient_id' => self::TEST_MEMBER_DEVON_ID,
            'workflow_approval_id' => $respondedApproval->id,
            'status' => RecommendationFeedbackRequestRecipient::STATUS_RESPONDED,
            'response_comment' => 'Responded before the deadline.',
            'responded_at' => DateTime::now()->modify('-1 hour'),
        ]));

        $result = $this->service->expireFeedbackForApproval((int)$expiredApproval->id);

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $updatedRequest = $this->requestsTable->get((int)$request->id);
        $this->assertSame(RecommendationFeedbackRequest::STATUS_EXPIRED, $updatedRequest->status);
    }

    public function testExpiredFeedbackNoLongerBlocksNewRequest(): void
    {
        $recommendation = $this->createRecommendation();
        $request = $this->createFeedbackRequestWithItem($recommendation);
        $approval = $this->createFeedbackApproval((int)$request->id);
        $this->recipientsTable->saveOrFail($this->recipientsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recipient_id' => self::TEST_MEMBER_BRYCE_ID,
            'workflow_approval_id' => $approval->id,
            'status' => RecommendationFeedbackRequestRecipient::STATUS_PENDING,
        ]));

        $this->assertTrue($this->hasOpenRequest([(int)$recommendation->id], self::TEST_MEMBER_BRYCE_ID));
        $this->service->expireFeedbackForApproval((int)$approval->id);

        $this->assertFalse($this->hasOpenRequest([(int)$recommendation->id], self::TEST_MEMBER_BRYCE_ID));
    }

    private function createRecommendation(): Recommendation
    {
        $member = $this->membersTable->get(self::TEST_MEMBER_AGATHA_ID, select: ['id', 'sca_name', 'branch_id']);
        $requester = $this->membersTable->get(
            self::ADMIN_MEMBER_ID,
            select: ['id', 'sca_name', 'email_address', 'phone_number'],
        );
        $award = $this->awardsTable->find()->select(['id'])->firstOrFail();
        $statuses = Recommendation::getStatuses();
        $status = array_key_first($statuses);

        return $this->recommendationsTable->saveOrFail($this->recommendationsTable->newEntity([
            'award_id' => $award->id,
            'requester_id' => $requester->id,
            'requester_sca_name' => $requester->sca_name,
            'member_id' => $member->id,
            'member_sca_name' => $member->sca_name,
            'branch_id' => $member->branch_id,
            'contact_email' => $requester->email_address,
            'contact_number' => $requester->phone_number,
            'reason' => 'Original recommendation body',
            'status' => $status,
            'state' => $statuses[$status][0],
            'state_date' => DateTime::now(),
            'not_found' => false,
            'call_into_court' => 'Not Set',
            'court_availability' => 'Not Set',
            'person_to_notify' => '',
        ]));
    }

    /**
     * @return array{eventName: string, eventData: array<string, mixed>, triggeredBy: int|null}|null
     */
    private function findWorkflowEvent(string $eventName): ?array
    {
        foreach ($this->workflowEvents as $event) {
            if ($event['eventName'] === $eventName) {
                return $event;
            }
        }

        return null;
    }

    private function createFeedbackRequestWithItem(Recommendation $recommendation): RecommendationFeedbackRequest
    {
        $request = $this->requestsTable->saveOrFail($this->requestsTable->newEntity([
            'requester_id' => self::ADMIN_MEMBER_ID,
            'status' => RecommendationFeedbackRequest::STATUS_PENDING,
            'deadline' => DateTime::now()->modify('-1 hour'),
            'created_by' => self::ADMIN_MEMBER_ID,
            'modified_by' => self::ADMIN_MEMBER_ID,
        ]));
        $this->itemsTable->saveOrFail($this->itemsTable->newEntity([
            'feedback_request_id' => $request->id,
            'recommendation_id' => $recommendation->id,
            'snapshot' => ['recommendationId' => (int)$recommendation->id],
        ]));

        return $request;
    }

    /**
     * @param array<int> $recommendationIds
     */
    private function hasOpenRequest(array $recommendationIds, int $recipientId): bool
    {
        $method = new ReflectionMethod(RecommendationFeedbackService::class, 'hasOpenRequest');
        $method->setAccessible(true);

        return (bool)$method->invoke($this->service, $recommendationIds, $recipientId);
    }

    private function createApprovedFeedbackApproval(int $requestId, array $approverConfig = []): WorkflowApproval
    {
        return $this->createFeedbackApproval(
            $requestId,
            self::TEST_MEMBER_BRYCE_ID,
            WorkflowApproval::STATUS_APPROVED,
            $approverConfig,
        );
    }

    private function createFeedbackApproval(
        int $requestId,
        int $recipientId = self::TEST_MEMBER_BRYCE_ID,
        string $status = WorkflowApproval::STATUS_PENDING,
        array $approverConfig = [],
    ): WorkflowApproval {
        $workflowDefinition = $this->workflowDefinitionsTable->find()
            ->where(['current_version_id IS NOT' => null])
            ->firstOrFail();
        $instance = $this->workflowInstancesTable->find()
            ->where([
                'workflow_definition_id' => $workflowDefinition->id,
                'entity_type' => 'Awards.RecommendationFeedbackRequests',
                'entity_id' => $requestId,
                'status IN' => WorkflowInstance::ACTIVE_STATUSES,
            ])
            ->first();
        if ($instance === null) {
            $instance = $this->workflowInstancesTable->saveOrFail($this->workflowInstancesTable->newEntity([
                'workflow_definition_id' => $workflowDefinition->id,
                'workflow_version_id' => $workflowDefinition->current_version_id,
                'entity_type' => 'Awards.RecommendationFeedbackRequests',
                'entity_id' => $requestId,
                'status' => WorkflowInstance::STATUS_WAITING,
                'context' => [],
                'active_nodes' => ['feedback-approval'],
                'started_by' => self::ADMIN_MEMBER_ID,
                'started_at' => DateTime::now(),
            ]));
        }
        $log = $this->workflowExecutionLogsTable->saveOrFail($this->workflowExecutionLogsTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'feedback-approval-test',
            'node_type' => 'approval',
            'attempt_number' => 1,
            'status' => WorkflowExecutionLog::STATUS_WAITING,
            'input_data' => [],
            'started_at' => DateTime::now(),
        ]));

        return $this->workflowApprovalsTable->saveOrFail($this->workflowApprovalsTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => $log->node_id,
            'execution_log_id' => $log->id,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approver_config' => $approverConfig + [
                'member_id' => $recipientId,
                'feedback_response' => true,
                'requires_comment' => true,
            ],
            'current_approver_id' => $recipientId,
            'required_count' => 1,
            'approved_count' => $status === WorkflowApproval::STATUS_APPROVED ? 1 : 0,
            'rejected_count' => 0,
            'status' => $status,
            'allow_parallel' => false,
            'version' => 2,
            'deadline' => DateTime::now()->modify('-1 hour'),
        ]));
    }
}
