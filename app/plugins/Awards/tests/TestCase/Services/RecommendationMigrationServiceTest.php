<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\Member;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\TriggerDispatcher;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use App\Services\WorkflowEngine\WorkflowVersionManagerInterface;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\ApprovalProcess;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Model\Entity\Award;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Model\Entity\RecommendationFeedbackRequest;
use Awards\Model\Entity\RecommendationMigrationResult;
use Awards\Model\Entity\RecommendationMigrationRun;
use Awards\Services\AwardApprovalResolverService;
use Awards\Services\RecommendationApprovalProcessService;
use Awards\Services\RecommendationApprovalWorkflowSyncService;
use Awards\Services\RecommendationBestowalStatePolicyService;
use Awards\Services\RecommendationMigrationService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use Closure;
use ReflectionMethod;

/**
 * RecommendationMigrationService tests.
 */
class RecommendationMigrationServiceTest extends BaseTestCase
{
    private Table $recommendationsTable;
    private RecommendationMigrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->service = new RecommendationMigrationService(
            approvalResolver: $this->resolverReturningApprover(),
        );
    }

    public function testClassifiesClosedStates(): void
    {
        foreach (['Given', 'No Action', 'Deferred till Later', 'Linked'] as $state) {
            $recommendation = $this->recommendationsTable->get($this->createRecommendation($state));

            $classification = $this->service->classify($recommendation);

            $this->assertSame(
                RecommendationMigrationResult::TARGET_CLOSED,
                $classification['target'],
                "Expected {$state} to classify as closed.",
            );
        }
    }

    public function testClassifiesBestowalStatesAndExistingBestowalLink(): void
    {
        foreach (
            [
                RecommendationBestowalStatePolicyService::HANDOFF_STATE,
                'Scheduled',
                'Announced Not Given',
                'King Approved',
                'Queen Approved',
            ] as $state
        ) {
            $recommendation = $this->recommendationsTable->get($this->createRecommendation($state));

            $classification = $this->service->classify($recommendation);

            $this->assertSame(
                RecommendationMigrationResult::TARGET_BESTOWAL,
                $classification['target'],
                "Expected {$state} to classify as bestowal-owned.",
            );
        }

        $linkedRecommendation = new Recommendation([
            'id' => 123459,
            'state' => 'Submitted',
            'award_id' => 1,
            'member_id' => self::ADMIN_MEMBER_ID,
            'bestowal_id' => 99,
        ]);

        $classification = $this->service->classify($linkedRecommendation);

        $this->assertSame(RecommendationMigrationResult::TARGET_BESTOWAL, $classification['target']);
    }

    public function testClassifiesBestowalStateWithMissingGatheringForManualReview(): void
    {
        $recommendation = new Recommendation([
            'id' => 123461,
            'state' => 'Scheduled',
            'award_id' => 1,
            'member_id' => self::ADMIN_MEMBER_ID,
            'gathering_id' => 999999,
        ]);

        $classification = $this->service->classify($recommendation);

        $this->assertSame(RecommendationMigrationResult::TARGET_MANUAL_REVIEW, $classification['target']);
        $this->assertStringContainsString('references missing gathering', $classification['reason']);
    }

    public function testClassifiesApprovalStates(): void
    {
        foreach (['Submitted', 'In Consideration', 'Awaiting Feedback'] as $state) {
            $recommendation = $this->approvalReadyRecommendation($state);

            $classification = $this->service->classify($recommendation);

            $this->assertSame(
                RecommendationMigrationResult::TARGET_APPROVAL_WORKFLOW,
                $classification['target'],
                "Expected {$state} to classify as approval-owned.",
            );
        }
    }

    public function testClassifiesApprovalStateWithRecipientNameAndNoMemberId(): void
    {
        $recommendation = $this->approvalReadyRecommendation('Submitted');
        $recommendation->member_id = null;
        $recommendation->member_sca_name = 'Sam of Hellsgate';

        $classification = $this->service->classify($recommendation);

        $this->assertSame(RecommendationMigrationResult::TARGET_APPROVAL_WORKFLOW, $classification['target']);
    }

    public function testClassifiesOutOfScopeAndIncompleteRecommendationsForManualReview(): void
    {
        $grouped = new Recommendation([
            'id' => 123456,
            'state' => 'Submitted',
            'award_id' => 1,
            'member_id' => self::ADMIN_MEMBER_ID,
            'recommendation_group_id' => 42,
        ]);
        $missingData = new Recommendation([
            'id' => 123457,
            'state' => 'Submitted',
            'award_id' => null,
            'member_id' => self::ADMIN_MEMBER_ID,
        ]);
        $missingRecipient = $this->approvalReadyRecommendation('Submitted');
        $missingRecipient->member_id = null;
        $missingRecipient->member_sca_name = '';
        $unknownState = new Recommendation();
        $unknownState->patch([
            'id' => 123458,
            'state' => 'Unexpected State',
            'award_id' => 1,
            'member_id' => self::ADMIN_MEMBER_ID,
        ], ['setter' => false]);

        $this->assertSame(
            RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
            $this->service->classify($grouped)['target'],
        );
        $this->assertSame(
            RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
            $this->service->classify($missingData)['target'],
        );
        $this->assertSame(
            RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
            $this->service->classify($missingRecipient)['target'],
        );
        $this->assertSame(
            RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
            $this->service->classify($unknownState)['target'],
        );
    }

    public function testClassifiesApprovalStateWithoutEligibleApproversForManualReview(): void
    {
        $service = new RecommendationMigrationService(
            approvalResolver: $this->resolverReturningNoApprovers(),
        );
        $recommendation = $this->approvalReadyRecommendation('Submitted');

        $classification = $service->classify($recommendation);

        $this->assertSame(RecommendationMigrationResult::TARGET_MANUAL_REVIEW, $classification['target']);
        $this->assertStringContainsString('has no eligible approvers', $classification['reason']);
    }

    public function testAuditsOpenRecommendationsWithoutWorkflow(): void
    {
        $recommendationId = $this->createLegacyOpenRecommendation();

        $audit = $this->service->auditOpenRecommendationsWithoutWorkflow([
            'recommendation_id' => $recommendationId,
        ]);

        $this->assertSame(1, $audit['count']);
        $this->assertSame($recommendationId, $audit['recommendations'][0]['id']);
        $this->assertSame('In Progress', $audit['recommendations'][0]['status']);
        $this->assertSame('Legacy Open State', $audit['recommendations'][0]['state']);
    }

    public function testAuditIgnoresOpenRecommendationsWithActiveMigrationWorkflow(): void
    {
        $recommendationId = $this->createLegacyOpenRecommendation();
        $this->createMigrationWorkflowInstance($recommendationId);

        $audit = $this->service->auditOpenRecommendationsWithoutWorkflow([
            'recommendation_id' => $recommendationId,
        ]);

        $this->assertSame(0, $audit['count']);
        $this->assertSame([], $audit['recommendations']);
    }

    public function testApplyRunFailsWhenOpenRecommendationStillLacksWorkflow(): void
    {
        $recommendationId = $this->createLegacyOpenRecommendation();

        $result = $this->service->run(
            RecommendationMigrationRun::MODE_APPLY,
            ['recommendation_id' => $recommendationId],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('open recommendations still lack workflow', (string)$result->getError());

        $runId = (int)$result->getData()['runId'];
        $run = $this->getTableLocator()->get('Awards.RecommendationMigrationRuns')->get($runId);
        $this->assertSame(RecommendationMigrationRun::STATUS_FAILED, $run->status);

        $migrationResult = $this->getTableLocator()->get('Awards.RecommendationMigrationResults')->find()
            ->where([
                'migration_run_id' => $runId,
                'recommendation_id' => $recommendationId,
            ])
            ->firstOrFail();
        $this->assertSame(RecommendationMigrationResult::TARGET_MANUAL_REVIEW, $migrationResult->target_action);
        $this->assertSame(RecommendationMigrationResult::STATUS_SKIPPED, $migrationResult->result_status);
    }

    public function testApplyRunCanAllowOpenManualReviewRecommendations(): void
    {
        $recommendationId = $this->createLegacyOpenRecommendation();

        $result = $this->service->run(
            RecommendationMigrationRun::MODE_APPLY,
            ['recommendation_id' => $recommendationId],
            self::ADMIN_MEMBER_ID,
            true,
        );

        $this->assertTrue($result->isSuccess(), (string)$result->getError());

        $runId = (int)$result->getData()['runId'];
        $run = $this->getTableLocator()->get('Awards.RecommendationMigrationRuns')->get($runId);
        $this->assertSame(RecommendationMigrationRun::STATUS_COMPLETED, $run->status);

        $migrationResult = $this->getTableLocator()->get('Awards.RecommendationMigrationResults')->find()
            ->where([
                'migration_run_id' => $runId,
                'recommendation_id' => $recommendationId,
            ])
            ->firstOrFail();
        $this->assertSame(RecommendationMigrationResult::TARGET_MANUAL_REVIEW, $migrationResult->target_action);
        $this->assertSame(RecommendationMigrationResult::STATUS_SKIPPED, $migrationResult->result_status);
    }

    public function testApprovalWorkflowStartFailureWithoutApproversFallsBackToManualReview(): void
    {
        $recommendation = $this->recommendationsTable->get($this->createRecommendation('Submitted'));
        $runs = $this->getTableLocator()->get('Awards.RecommendationMigrationRuns');
        $run = $runs->saveOrFail($runs->newEntity([
            'mode' => RecommendationMigrationRun::MODE_APPLY,
            'status' => RecommendationMigrationRun::STATUS_RUNNING,
            'started' => DateTime::now(),
        ]));
        $service = new RecommendationMigrationService(
            triggerDispatcher: $this->dispatcherReturningFailure('The approval step resolved zero eligible approvers.'),
            approvalResolver: $this->resolverReturningApprover(),
        );

        $migrationResult = $this->invokePrivate($service, 'applyClassification', [
            (int)$run->id,
            $recommendation,
            [
                'target' => RecommendationMigrationResult::TARGET_APPROVAL_WORKFLOW,
                'reason' => 'State needs approval workflow ownership.',
            ],
            RecommendationMigrationRun::MODE_APPLY,
            self::ADMIN_MEMBER_ID,
        ]);

        $this->assertSame(RecommendationMigrationResult::TARGET_MANUAL_REVIEW, $migrationResult->target_action);
        $this->assertSame(RecommendationMigrationResult::STATUS_SKIPPED, $migrationResult->result_status);
        $this->assertSame(
            'Approval workflow could not start during migration: '
            . 'Recommendation approval process has no eligible approvers.',
            $migrationResult->reason,
        );
        $this->assertStringNotContainsString('resolved zero eligible approvers', $migrationResult->reason);
    }

    public function testBackfillOpenApprovalRecommendationsStartsOnlyEligibleRecords(): void
    {
        $this->neutralizeSeededApprovalBackfillCandidates();
        $process = $this->createBackfillApprovalProcess();
        $award = $this->createBackfillAward((int)$process->id);
        $eligibleId = $this->createRecommendation('Submitted', ['award_id' => (int)$award->id]);

        $groupHeadId = $this->createRecommendation('Submitted', ['award_id' => (int)$award->id]);
        $this->recommendationsTable->updateAll(['status' => 'Closed'], ['id' => $groupHeadId]);
        $groupedId = $this->createRecommendation('In Consideration', [
            'award_id' => (int)$award->id,
            'recommendation_group_id' => $groupHeadId,
        ]);

        $noProcessAward = $this->createBackfillAward(null);
        $noProcessId = $this->createRecommendation('Awaiting Feedback', [
            'award_id' => (int)$noProcessAward->id,
        ]);
        $activeFeedbackId = $this->createRecommendation('Awaiting Feedback', [
            'award_id' => (int)$award->id,
        ]);
        $this->createPendingFeedbackRequest($activeFeedbackId);
        $closedId = $this->createRecommendation('Submitted', [
            'award_id' => (int)$award->id,
            'status' => 'Closed',
        ]);
        $otherStateId = $this->createRecommendation('Scheduled', ['award_id' => (int)$award->id]);
        $deletedId = $this->createRecommendation('Submitted', ['award_id' => (int)$award->id]);
        $this->recommendationsTable->updateAll(['deleted' => DateTime::now()], ['id' => $deletedId]);
        $activeId = $this->createRecommendation('Submitted', ['award_id' => (int)$award->id]);
        $this->createBackfilledApprovalRun($activeId, self::ADMIN_MEMBER_ID);

        $events = [];
        $service = new RecommendationMigrationService(
            triggerDispatcher: $this->dispatcherStartingApprovalRuns($events),
            approvalResolver: $this->resolverReturningApprover(),
        );

        $result = $service->backfillOpenApprovalRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $summary = $result->getData();
        $this->assertSame(3, $summary['candidateCount']);
        $this->assertSame(1, $summary['startedCount']);
        $this->assertSame(0, $summary['unchangedCount']);
        $this->assertSame(2, $summary['skippedCount']);
        $this->assertSame(0, $summary['failedCount']);
        $this->assertSame([], $summary['failures']);
        $skipIds = array_column($summary['skips'], 'recommendationId');
        sort($skipIds);
        $expectedSkipIds = [$noProcessId, $activeFeedbackId];
        sort($expectedSkipIds);
        $this->assertSame($expectedSkipIds, $skipIds);

        $this->assertCount(1, $events);
        $this->assertSame(RecommendationMigrationService::WORKFLOW_EVENT, $events[0]['eventName']);
        $this->assertSame($eligibleId, $events[0]['eventData']['recommendationId']);
        $this->assertSame(self::ADMIN_MEMBER_ID, $events[0]['eventData']['actorId']);
        $this->assertTrue($events[0]['eventData']['migration']);
        $this->assertSame(self::ADMIN_MEMBER_ID, $events[0]['triggeredBy']);
        $this->assertSame(1, $this->activeApprovalRunCount($eligibleId));
        $this->assertSame(1, $this->activeApprovalRunCount($activeId));
        $this->assertSame(0, $this->activeApprovalRunCount($groupedId));
        $this->assertSame('Closed', $this->recommendationsTable->get($closedId)->status);
        $this->assertSame('Scheduled', $this->recommendationsTable->get($otherStateId)->state);

        $secondResult = $service->backfillOpenApprovalRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue($secondResult->isSuccess(), (string)$secondResult->getError());
        $secondSummary = $secondResult->getData();
        $this->assertSame(2, $secondSummary['candidateCount']);
        $this->assertSame(0, $secondSummary['startedCount']);
        $this->assertSame(2, $secondSummary['skippedCount']);
        $this->assertCount(1, $events);
        $this->assertSame(1, $this->activeApprovalRunCount($eligibleId));
    }

    public function testBackfillOpenApprovalRecommendationsIsolatesFailures(): void
    {
        $this->neutralizeSeededApprovalBackfillCandidates();
        $process = $this->createBackfillApprovalProcess();
        $award = $this->createBackfillAward((int)$process->id);
        $failedId = $this->createRecommendation('Submitted', ['award_id' => (int)$award->id]);
        $startedId = $this->createRecommendation('In Consideration', ['award_id' => (int)$award->id]);
        $events = [];
        $service = new RecommendationMigrationService(
            triggerDispatcher: $this->dispatcherStartingApprovalRuns($events, [$failedId]),
            approvalResolver: $this->resolverReturningApprover(),
        );

        $result = $service->backfillOpenApprovalRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertFalse($result->isSuccess());
        $summary = $result->getData();
        $this->assertSame(2, $summary['candidateCount']);
        $this->assertSame(1, $summary['startedCount']);
        $this->assertSame(0, $summary['unchangedCount']);
        $this->assertSame(0, $summary['skippedCount']);
        $this->assertSame(1, $summary['failedCount']);
        $this->assertSame($failedId, $summary['failures'][0]['recommendationId']);
        $this->assertSame(
            'Recommendation approval workflow could not be started. Review server logs for details.',
            $summary['failures'][0]['reason'],
        );
        $this->assertStringNotContainsString('Synthetic trigger failure', $summary['failures'][0]['reason']);
        $this->assertSame(0, $this->activeApprovalRunCount($failedId));
        $this->assertSame(1, $this->activeApprovalRunCount($startedId));
        $this->assertCount(2, $events);
    }

    public function testBackfillScanExcludesGroupedRecommendations(): void
    {
        $this->neutralizeSeededApprovalBackfillCandidates();
        $process = $this->createBackfillApprovalProcess();
        $award = $this->createBackfillAward((int)$process->id);
        $groupHeadId = $this->createRecommendation('Submitted', ['award_id' => (int)$award->id]);
        $this->recommendationsTable->updateAll(['status' => 'Closed'], ['id' => $groupHeadId]);
        $groupedId = $this->createRecommendation('Submitted', [
            'award_id' => (int)$award->id,
            'recommendation_group_id' => $groupHeadId,
        ]);
        $events = [];
        $service = new RecommendationMigrationService(
            triggerDispatcher: $this->dispatcherStartingApprovalRuns($events),
            approvalResolver: $this->resolverReturningApprover(),
        );

        $result = $service->backfillOpenApprovalRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $summary = $result->getData();
        $this->assertSame(0, $summary['candidateCount']);
        $this->assertSame(0, $summary['startedCount']);
        $this->assertSame(0, $summary['unchangedCount']);
        $this->assertSame(0, $summary['skippedCount']);
        $this->assertSame(0, $summary['failedCount']);
        $this->assertSame([], $events);
        $this->assertSame(0, $this->activeApprovalRunCount($groupedId));
        $this->assertSame(0, $this->activeMigrationWorkflowCount($groupedId));
    }

    public function testBackfillOpenApprovalRecommendationsReportsNoApproversAsSkipped(): void
    {
        $this->neutralizeSeededApprovalBackfillCandidates();
        $process = $this->createBackfillApprovalProcess();
        $award = $this->createBackfillAward((int)$process->id);
        $recommendationId = $this->createRecommendation('Submitted', ['award_id' => (int)$award->id]);
        $service = new RecommendationMigrationService(
            triggerDispatcher: $this->dispatcherReturningFailure('Dispatcher should not be reached.'),
            approvalResolver: $this->resolverReturningNoApprovers(),
        );

        $result = $service->backfillOpenApprovalRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $summary = $result->getData();
        $this->assertSame(1, $summary['candidateCount']);
        $this->assertSame(0, $summary['startedCount']);
        $this->assertSame(1, $summary['skippedCount']);
        $this->assertSame($recommendationId, $summary['skips'][0]['recommendationId']);
        $this->assertStringContainsString('has no eligible approvers', $summary['skips'][0]['reason']);
        $this->assertSame(0, $this->activeApprovalRunCount($recommendationId));
    }

    public function testBackfillOpenApprovalRecommendationsTreatsRaceCreatedRunAsUnchanged(): void
    {
        $this->neutralizeSeededApprovalBackfillCandidates();
        $process = $this->createBackfillApprovalProcess();
        $award = $this->createBackfillAward((int)$process->id);
        $recommendationId = $this->createRecommendation('Submitted', ['award_id' => (int)$award->id]);
        $createRun = function () use ($recommendationId): void {
            $this->createBackfilledApprovalRun($recommendationId, self::ADMIN_MEMBER_ID);
        };
        $resolver = new class ($createRun) extends AwardApprovalResolverService {
            private bool $created = false;

            public function __construct(private Closure $createRun)
            {
            }

            /**
             * @inheritDoc
             */
            public function resolveApprovers(ApprovalProcessStep $step, Award $award): array
            {
                if (!$this->created) {
                    ($this->createRun)();
                    $this->created = true;
                }

                return [new Member(['id' => BaseTestCase::ADMIN_MEMBER_ID])];
            }
        };
        $service = new RecommendationMigrationService(
            triggerDispatcher: $this->dispatcherReturningFailure('Dispatcher should not be reached.'),
            approvalResolver: $resolver,
        );

        $result = $service->backfillOpenApprovalRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $summary = $result->getData();
        $this->assertSame(1, $summary['candidateCount']);
        $this->assertSame(0, $summary['startedCount']);
        $this->assertSame(1, $summary['unchangedCount']);
        $this->assertSame(0, $summary['skippedCount']);
        $this->assertSame(0, $summary['failedCount']);
        $this->assertSame(1, $this->activeApprovalRunCount($recommendationId));
    }

    public function testBackfillRepairsOrphanedActiveWorkflowWithoutReplacingEvidence(): void
    {
        $this->neutralizeSeededApprovalBackfillCandidates();
        $process = $this->createBackfillApprovalProcess();
        $award = $this->createBackfillAward((int)$process->id);
        $recommendationId = $this->createRecommendation('Submitted', ['award_id' => (int)$award->id]);
        $fixture = $this->createOrphanedApprovalWorkflow($recommendationId, 987654321);
        $service = new RecommendationMigrationService(
            approvalResolver: $this->resolverReturningApprover(),
        );

        $result = $service->backfillOpenApprovalRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $summary = $result->getData();
        $this->assertSame(1, $summary['candidateCount']);
        $this->assertSame(1, $summary['startedCount']);
        $this->assertSame(0, $summary['unchangedCount']);
        $this->assertSame(0, $summary['skippedCount']);
        $this->assertSame(0, $summary['failedCount']);

        $runs = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $run = $runs->find()->where(['recommendation_id' => $recommendationId])->firstOrFail();
        $this->assertSame($fixture['instanceId'], (int)$run->workflow_instance_id);
        $this->assertSame((int)$process->id, (int)$run->approval_process_id);
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $run->status);
        $this->assertSame('crown', $run->current_step_key);
        $this->assertSame('Crown Approval', $run->current_step_label);
        $this->assertNull($run->rehydrated_from_run_id);

        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get($fixture['approvalId']);
        $this->assertSame($fixture['approvalId'], (int)$approval->id);
        $this->assertSame((int)$run->id, (int)$approval->approver_config['award_approval_run_id']);
        $this->assertSame('preserve-me', $approval->approver_config['evidence_marker']);
        $this->assertSame(1, (int)$approval->approved_count);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $approval->status);

        $response = $this->getTableLocator()->get('WorkflowApprovalResponses')->get($fixture['responseId']);
        $this->assertSame($fixture['responseId'], (int)$response->id);
        $this->assertSame($fixture['approvalId'], (int)$response->workflow_approval_id);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$response->member_id);
        $this->assertSame('approve', $response->decision);
        $this->assertSame('Historical approval evidence', $response->comment);
        $this->assertSame($fixture['respondedAt'], $response->responded_at->toAtomString());

        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($fixture['instanceId']);
        $this->assertSame(
            (int)$run->id,
            (int)$instance->context['awardApprovalCurrentStep']['approvalApproverConfig']['award_approval_run_id'],
        );
        $this->assertSame(
            (int)$run->id,
            (int)$instance->context['nodes']['start-approval-process']['result']['runId'],
        );
        $this->assertSame(1, $this->activeApprovalRunCount($recommendationId));
        $this->assertSame(1, $this->activeMigrationWorkflowCount($recommendationId));
        $this->assertSame(1, $this->workflowApprovalCount($fixture['instanceId']));

        $secondResult = $service->backfillOpenApprovalRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue($secondResult->isSuccess(), (string)$secondResult->getError());
        $this->assertSame(0, $secondResult->getData()['candidateCount']);
        $this->assertSame(1, $this->activeApprovalRunCount($recommendationId));
        $this->assertSame(1, $this->activeMigrationWorkflowCount($recommendationId));
        $this->assertSame(1, $this->workflowApprovalCount($fixture['instanceId']));
        $this->assertSame(
            1,
            $this->getTableLocator()->get('WorkflowApprovalResponses')->find()
                ->where(['workflow_approval_id' => $fixture['approvalId']])
                ->count(),
        );
    }

    public function testSyncBackfillsOrphanAndSynchronizesItInTheSameCall(): void
    {
        $this->neutralizeSeededApprovalBackfillCandidates();
        $process = $this->createBackfillApprovalProcess();
        $award = $this->createBackfillAward((int)$process->id);
        $recommendationId = $this->createRecommendation('Submitted', ['award_id' => (int)$award->id]);
        $fixture = $this->createOrphanedApprovalWorkflow($recommendationId, 987654322);
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($fixture['instanceId']);
        $currentVersion = $this->getTableLocator()->get('WorkflowVersions')->get((int)$instance->workflow_version_id);

        $engine = $this->createMock(WorkflowEngineInterface::class);
        $engine->expects($this->once())
            ->method('resumeWorkflow')
            ->with(
                $fixture['instanceId'],
                'award-approval-gate',
                'approved',
                $this->callback(static function (array $resumeData) use ($fixture): bool {
                    return (int)($resumeData['approval']['approvalId'] ?? 0) === $fixture['approvalId']
                        && (int)($resumeData['approverId'] ?? 0) === BaseTestCase::ADMIN_MEMBER_ID
                        && ($resumeData['decision'] ?? null) === 'approve'
                        && !empty($resumeData['synchronized']);
                }),
            )
            ->willReturn(new ServiceResult(true));
        $versionManager = $this->createMock(WorkflowVersionManagerInterface::class);
        $versionManager->expects($this->once())
            ->method('getCurrentVersion')
            ->with((int)$instance->workflow_definition_id)
            ->willReturn($currentVersion);
        $versionManager->expects($this->never())->method('migrateInstance');
        $resolver = $this->resolverReturningApprover();
        $migrationService = new RecommendationMigrationService(approvalResolver: $resolver);
        $syncService = new RecommendationApprovalWorkflowSyncService(
            new RecommendationApprovalProcessService($resolver),
            $engine,
            $versionManager,
            $migrationService,
        );

        $result = $syncService->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), json_encode($result->getData()['failures'] ?? []));
        $summary = $result->getData();
        $this->assertSame(1, $summary['backfillCandidateCount']);
        $this->assertSame(1, $summary['backfilledCount']);
        $this->assertSame(1, $summary['processedCount']);
        $this->assertSame(1, $summary['synchronizedCount']);
        $this->assertSame(1, $summary['advancedCount']);
        $this->assertSame(0, $summary['failedCount']);

        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['recommendation_id' => $recommendationId])
            ->firstOrFail();
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get($fixture['approvalId']);
        $this->assertSame((int)$run->id, (int)$approval->approver_config['award_approval_run_id']);
        $this->assertSame(WorkflowApproval::STATUS_APPROVED, $approval->status);
        $this->assertSame(1, (int)$approval->required_count);
        $this->assertSame(1, (int)$approval->approved_count);
        $this->assertSame($fixture['responseId'], (int)$this->getTableLocator()
            ->get('WorkflowApprovalResponses')
            ->get($fixture['responseId'])->id);
        $this->assertSame(1, $this->activeApprovalRunCount($recommendationId));
        $this->assertSame(1, $this->activeMigrationWorkflowCount($recommendationId));
        $this->assertSame(1, $this->workflowApprovalCount($fixture['instanceId']));
    }

    public function testBackfillRefusesToRelinkEvidenceOwnedByAnotherWorkflow(): void
    {
        $this->neutralizeSeededApprovalBackfillCandidates();
        $process = $this->createBackfillApprovalProcess();
        $award = $this->createBackfillAward((int)$process->id);
        $recommendationId = $this->createRecommendation('Submitted', ['award_id' => (int)$award->id]);
        $otherRecommendationId = $this->createRecommendation('Submitted', ['award_id' => (int)$award->id]);
        $otherRunId = $this->createBackfilledApprovalRun($otherRecommendationId, self::ADMIN_MEMBER_ID);
        $fixture = $this->createOrphanedApprovalWorkflow($recommendationId, $otherRunId);
        $service = new RecommendationMigrationService(
            approvalResolver: $this->resolverReturningApprover(),
        );

        $result = $service->backfillOpenApprovalRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), (string)$result->getError());
        $summary = $result->getData();
        $this->assertSame(1, $summary['candidateCount']);
        $this->assertSame(0, $summary['startedCount']);
        $this->assertSame(1, $summary['skippedCount']);
        $this->assertSame(0, $summary['failedCount']);
        $this->assertSame($recommendationId, $summary['skips'][0]['recommendationId']);
        $this->assertSame(
            'Recommendation approval workflow ownership is ambiguous and requires manual review.',
            $summary['skips'][0]['reason'],
        );
        $this->assertSame(0, $this->activeApprovalRunCount($recommendationId));
        $this->assertSame(1, $this->activeApprovalRunCount($otherRecommendationId));
        $this->assertSame(1, $this->activeMigrationWorkflowCount($recommendationId));
        $this->assertSame($otherRunId, (int)$this->getTableLocator()->get('WorkflowApprovals')
            ->get($fixture['approvalId'])->approver_config['award_approval_run_id']);
        $this->assertSame($fixture['responseId'], (int)$this->getTableLocator()
            ->get('WorkflowApprovalResponses')
            ->get($fixture['responseId'])->id);
    }

    /**
     * @param array<string, mixed> $overrides Field overrides
     */
    private function createRecommendation(string $state, array $overrides = []): int
    {
        $entity = $this->recommendationsTable->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $this->getFirstAwardId(),
            'reason' => 'Test recommendation migration',
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@test.com',
            'status' => 'In Progress',
            'state' => $state,
            'state_date' => new DateTime('2024-01-01 00:00:00'),
            'call_into_court' => 'Not Set',
            'court_availability' => 'Not Set',
            'person_to_notify' => '',
            'branch_id' => self::KINGDOM_BRANCH_ID,
        ]);

        foreach ($overrides as $field => $value) {
            $entity->set($field, $value);
        }

        $saved = $this->recommendationsTable->saveOrFail($entity);

        return (int)$saved->id;
    }

    private function createLegacyOpenRecommendation(): int
    {
        $recommendationId = $this->createRecommendation('Submitted');
        $this->recommendationsTable->updateAll(
            [
                'state' => 'Legacy Open State',
                'status' => 'In Progress',
            ],
            ['id' => $recommendationId],
        );

        return $recommendationId;
    }

    private function createMigrationWorkflowInstance(int $recommendationId): int
    {
        $workflowDefinitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $workflowDefinition = $workflowDefinitions->find()
            ->where(['slug' => RecommendationMigrationService::WORKFLOW_SLUG])
            ->firstOrFail();

        $workflowVersionId = (int)$workflowDefinition->current_version_id;
        if ($workflowVersionId === 0) {
            $workflowVersions = $this->getTableLocator()->get('WorkflowVersions');
            $workflowVersion = $workflowVersions->newEntity([
                'workflow_definition_id' => $workflowDefinition->id,
                'version_number' => 1,
                'definition' => ['nodes' => [], 'connections' => []],
                'status' => 'published',
                'published_by' => self::ADMIN_MEMBER_ID,
            ]);
            $workflowVersion = $workflowVersions->saveOrFail($workflowVersion);
            $workflowVersionId = (int)$workflowVersion->id;
            $workflowDefinition->current_version_id = $workflowVersionId;
            $workflowDefinitions->saveOrFail($workflowDefinition);
        }

        $workflowInstances = $this->getTableLocator()->get('WorkflowInstances');
        $instance = $workflowInstances->saveOrFail($workflowInstances->newEntity([
            'workflow_definition_id' => $workflowDefinition->id,
            'workflow_version_id' => $workflowVersionId,
            'entity_type' => 'Awards.Recommendations',
            'entity_id' => $recommendationId,
            'status' => WorkflowInstance::STATUS_WAITING,
            'started_by' => self::ADMIN_MEMBER_ID,
        ]));

        return (int)$instance->id;
    }

    private function neutralizeSeededApprovalBackfillCandidates(): void
    {
        $this->recommendationsTable->updateAll(
            ['status' => 'Closed'],
            [
                'status !=' => 'Closed',
                'state IN' => ['Submitted', 'In Consideration', 'Awaiting Feedback'],
            ],
        );
    }

    private function createPendingFeedbackRequest(int $recommendationId): void
    {
        $requests = $this->getTableLocator()->get('Awards.RecommendationFeedbackRequests');
        $request = $requests->saveOrFail($requests->newEntity([
            'requester_id' => self::ADMIN_MEMBER_ID,
            'status' => RecommendationFeedbackRequest::STATUS_PENDING,
            'deadline' => DateTime::now()->modify('+1 day'),
            'created_by' => self::ADMIN_MEMBER_ID,
            'modified_by' => self::ADMIN_MEMBER_ID,
        ]));
        $items = $this->getTableLocator()->get('Awards.RecommendationFeedbackRequestItems');
        $items->saveOrFail($items->newEntity([
            'feedback_request_id' => (int)$request->id,
            'recommendation_id' => $recommendationId,
            'snapshot' => ['recommendationId' => $recommendationId],
        ]));
    }

    private function createBackfillApprovalProcess(): ApprovalProcess
    {
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses');

        return $processes->saveOrFail($processes->newEntity([
            'name' => 'Recommendation Backfill Process ' . uniqid('', true),
            'is_active' => true,
            'approval_process_steps' => [
                [
                    'step_key' => 'crown',
                    'label' => 'Crown Approval',
                    'sequence' => 1,
                    'step_type' => ApprovalProcessStep::STEP_TYPE_APPROVAL,
                    'approver_type' => ApprovalProcessStep::APPROVER_TYPE_MEMBER,
                    'approver_source_id' => self::ADMIN_MEMBER_ID,
                    'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
                    'threshold_mode' => ApprovalProcessStep::THRESHOLD_ANY,
                    'on_reject' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
                    'on_request_changes' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
                    'retain_read_visibility' => true,
                ],
            ],
        ], ['associated' => ['ApprovalProcessSteps']]));
    }

    private function createBackfillAward(?int $processId): Award
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');

        return $awards->saveOrFail($awards->newEntity([
            'name' => 'Recommendation Backfill Award ' . uniqid('', true),
            'abbreviation' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approval_process_id' => $processId,
            'is_active' => true,
        ]));
    }

    private function createBackfilledApprovalRun(int $recommendationId, int $actorId): int
    {
        $instanceId = $this->createMigrationWorkflowInstance($recommendationId);
        $recommendation = $this->recommendationsTable->get($recommendationId, contain: [
            'Awards.ApprovalProcesses.ApprovalProcessSteps',
        ]);
        $process = $recommendation->award->approval_process;
        $step = array_values($process->approval_process_steps)[0];
        $runs = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $run = $runs->saveOrFail($runs->newEntity([
            'recommendation_id' => $recommendationId,
            'approval_process_id' => (int)$process->id,
            'workflow_instance_id' => $instanceId,
            'status' => RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'current_step_key' => (string)$step->step_key,
            'current_step_label' => (string)$step->label,
            'started' => DateTime::now(),
            'created_by' => $actorId,
            'modified_by' => $actorId,
        ]));

        $logs = $this->getTableLocator()->get('WorkflowExecutionLogs');
        $log = $logs->saveOrFail($logs->newEntity([
            'workflow_instance_id' => $instanceId,
            'node_id' => 'award-approval-gate',
            'node_type' => 'approval',
            'attempt_number' => 1,
            'status' => WorkflowExecutionLog::STATUS_WAITING,
        ]));
        $approvals = $this->getTableLocator()->get('WorkflowApprovals');
        $approvals->saveOrFail($approvals->newEntity([
            'workflow_instance_id' => $instanceId,
            'node_id' => 'award-approval-gate',
            'execution_log_id' => (int)$log->id,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            'approver_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'required_count' => 1,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'allow_parallel' => false,
            'version' => 1,
        ]));

        return (int)$run->id;
    }

    /**
     * @return array{instanceId:int,approvalId:int,responseId:int,respondedAt:string}
     */
    private function createOrphanedApprovalWorkflow(int $recommendationId, int $staleRunId): array
    {
        $instanceId = $this->createMigrationWorkflowInstance($recommendationId);
        $recommendation = $this->recommendationsTable->get($recommendationId, contain: [
            'Awards.ApprovalProcesses.ApprovalProcessSteps',
        ]);
        $process = $recommendation->award->approval_process;
        $step = array_values($process->approval_process_steps)[0];
        $config = [
            'service' => 'Awards.ResolveApprovalStepApprovers',
            'method' => 'resolveConfiguredApproverIds',
            'award_approval_run_id' => $staleRunId,
            'award_approval_step_key' => (string)$step->step_key,
            'award_approval_is_final_step' => true,
            'award_approval_approver_type' => ApprovalProcessStep::APPROVER_TYPE_MEMBER,
            'award_approval_approver_source_id' => self::ADMIN_MEMBER_ID,
            'award_approval_branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
            'award_approval_threshold_mode' => ApprovalProcessStep::THRESHOLD_COUNT,
            'award_approval_required_count' => 2,
            'member_id' => self::ADMIN_MEMBER_ID,
            'eligible_member_ids' => [self::ADMIN_MEMBER_ID, self::TEST_MEMBER_BRYCE_ID],
            'evidence_marker' => 'preserve-me',
        ];

        $logs = $this->getTableLocator()->get('WorkflowExecutionLogs');
        $log = $logs->saveOrFail($logs->newEntity([
            'workflow_instance_id' => $instanceId,
            'node_id' => 'award-approval-gate',
            'node_type' => 'approval',
            'attempt_number' => 1,
            'status' => WorkflowExecutionLog::STATUS_WAITING,
        ]));
        $approvals = $this->getTableLocator()->get('WorkflowApprovals');
        $approval = $approvals->saveOrFail($approvals->newEntity([
            'workflow_instance_id' => $instanceId,
            'node_id' => 'award-approval-gate',
            'execution_log_id' => (int)$log->id,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            'approver_config' => $config,
            'required_count' => 2,
            'approved_count' => 1,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'allow_parallel' => true,
            'version' => 1,
        ]));
        $responses = $this->getTableLocator()->get('WorkflowApprovalResponses');
        $response = $responses->saveOrFail($responses->newEntity([
            'workflow_approval_id' => (int)$approval->id,
            'member_id' => self::ADMIN_MEMBER_ID,
            'decision' => 'approve',
            'comment' => 'Historical approval evidence',
            'responded_at' => new DateTime('2025-03-04 05:06:07'),
        ]));

        $instances = $this->getTableLocator()->get('WorkflowInstances');
        $instance = $instances->get($instanceId);
        $instance->context = [
            'trigger' => [
                'recommendationId' => $recommendationId,
                'actorId' => self::ADMIN_MEMBER_ID,
                'migration' => true,
            ],
            'awardApprovalCurrentStep' => [
                'approvalApproverConfig' => $config,
                'requiredCount' => 2,
                'currentStepKey' => (string)$step->step_key,
                'currentStepLabel' => (string)$step->label,
            ],
            'nodes' => [
                'start-approval-process' => [
                    'result' => [
                        'success' => true,
                        'runId' => $staleRunId,
                        'recommendationId' => $recommendationId,
                    ],
                ],
            ],
        ];
        $instances->saveOrFail($instance);

        return [
            'instanceId' => $instanceId,
            'approvalId' => (int)$approval->id,
            'responseId' => (int)$response->id,
            'respondedAt' => $response->responded_at->toAtomString(),
        ];
    }

    private function activeApprovalRunCount(int $recommendationId): int
    {
        return $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where([
                'recommendation_id' => $recommendationId,
                'status IN' => [
                    RecommendationApprovalRun::STATUS_IN_PROGRESS,
                    RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                ],
            ])
            ->count();
    }

    private function activeMigrationWorkflowCount(int $recommendationId): int
    {
        return $this->getTableLocator()->get('WorkflowInstances')->find()
            ->innerJoinWith('WorkflowDefinitions')
            ->where([
                'WorkflowDefinitions.slug' => RecommendationMigrationService::WORKFLOW_SLUG,
                'WorkflowInstances.entity_type' => 'Awards.Recommendations',
                'WorkflowInstances.entity_id' => $recommendationId,
                'WorkflowInstances.status IN' => WorkflowInstance::ACTIVE_STATUSES,
            ])
            ->count();
    }

    private function workflowApprovalCount(int $instanceId): int
    {
        return $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->count();
    }

    private function getFirstAwardId(): int
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->first();

        $this->assertNotNull($award, 'Expected seeded awards data for migration tests.');

        return (int)$award->id;
    }

    private function approvalReadyRecommendation(string $state): Recommendation
    {
        return new Recommendation([
            'id' => 123460,
            'state' => $state,
            'award_id' => 1,
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Admin von Admin',
            'award' => new Award([
                'id' => 1,
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'approval_process_id' => 1,
                'approval_process' => new ApprovalProcess([
                    'id' => 1,
                    'is_active' => true,
                    'approval_process_steps' => [
                        new ApprovalProcessStep([
                            'id' => 1,
                            'step_key' => 'local',
                            'label' => 'Local Approval',
                            'approver_type' => ApprovalProcessStep::APPROVER_TYPE_MEMBER,
                            'approver_source_id' => self::ADMIN_MEMBER_ID,
                            'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
                        ]),
                    ],
                ]),
            ]),
        ]);
    }

    private function resolverReturningApprover(): AwardApprovalResolverService
    {
        return new class extends AwardApprovalResolverService {
            /**
             * @inheritDoc
             */
            public function resolveApprovers(ApprovalProcessStep $step, Award $award): array
            {
                return [new Member(['id' => BaseTestCase::ADMIN_MEMBER_ID])];
            }
        };
    }

    private function resolverReturningNoApprovers(): AwardApprovalResolverService
    {
        return new class extends AwardApprovalResolverService {
            /**
             * @inheritDoc
             */
            public function resolveApprovers(ApprovalProcessStep $step, Award $award): array
            {
                return [];
            }
        };
    }

    private function dispatcherReturningFailure(string $message): TriggerDispatcher
    {
        return $this->dispatcherUsing(
            static fn(): array => [new ServiceResult(false, $message)],
        );
    }

    /**
     * @param array<int, array<string, mixed>> $events Captured trigger events
     * @param array<int> $failureIds Recommendation IDs that should fail
     */
    private function dispatcherStartingApprovalRuns(array &$events, array $failureIds = []): TriggerDispatcher
    {
        return $this->dispatcherUsing(function (
            string $eventName,
            array $eventData,
            ?int $triggeredBy,
        ) use (
            &$events,
            $failureIds,
        ): array {
            $events[] = compact('eventName', 'eventData', 'triggeredBy');
            $recommendationId = (int)($eventData['recommendationId'] ?? 0);
            if (in_array($recommendationId, $failureIds, true)) {
                return [new ServiceResult(false, 'Synthetic trigger failure.')];
            }

            $runId = $this->createBackfilledApprovalRun($recommendationId, (int)$triggeredBy);
            $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get($runId);

            return [new ServiceResult(true, null, [
                'instanceId' => (int)$run->workflow_instance_id,
            ])];
        });
    }

    private function dispatcherUsing(Closure $callback): TriggerDispatcher
    {
        return new TriggerDispatcher(new class ($callback) implements WorkflowEngineInterface {
            public function __construct(private Closure $callback)
            {
            }

            public function startWorkflow(
                string $workflowSlug,
                array $triggerData = [],
                ?int $startedBy = null,
                ?string $entityType = null,
                ?int $entityId = null,
            ): ServiceResult {
                return new ServiceResult(false, 'Not implemented for this test.');
            }

            public function resumeWorkflow(
                int $instanceId,
                string $nodeId,
                string $outputPort,
                array $additionalData = [],
            ): ServiceResult {
                return new ServiceResult(false, 'Not implemented for this test.');
            }

            public function cancelWorkflow(int $instanceId, ?string $reason = null): ServiceResult
            {
                return new ServiceResult(false, 'Not implemented for this test.');
            }

            public function getInstanceState(int $instanceId): ?array
            {
                return null;
            }

            public function dispatchTrigger(
                string $eventName,
                array $eventData = [],
                ?int $triggeredBy = null,
            ): array {
                return ($this->callback)($eventName, $eventData, $triggeredBy);
            }

            public function fireIntermediateApprovalActions(
                int $instanceId,
                string $nodeId,
                array $approvalData,
                string $outputPort = 'on_each_approval',
            ): ServiceResult {
                return new ServiceResult(false, 'Not implemented for this test.');
            }

            public function completeHumanTask(int $taskId, array $formData, int $completedBy): ServiceResult
            {
                return new ServiceResult(false, 'Not implemented for this test.');
            }

            public function cancelHumanTask(int $taskId, ?string $reason = null): ServiceResult
            {
                return new ServiceResult(false, 'Not implemented for this test.');
            }
        });
    }

    /**
     * @param array<int, mixed> $args
     */
    private function invokePrivate(object $object, string $methodName, array $args): mixed
    {
        $method = new ReflectionMethod($object, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
