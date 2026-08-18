<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\KMP\GridColumns\ApprovalsGridColumns;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\Conditions\CoreConditions;
use App\Services\WorkflowEngine\DefaultWorkflowApprovalManager;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Services\WorkflowEngine\DefaultWorkflowVersionManager;
use App\Services\WorkflowEngine\WorkflowEngineInterface;
use App\Services\WorkflowRegistry\WorkflowActionRegistry;
use App\Services\WorkflowRegistry\WorkflowApproverResolverRegistry;
use App\Services\WorkflowRegistry\WorkflowConditionRegistry;
use App\Services\WorkflowRegistry\WorkflowEntityRegistry;
use App\Services\WorkflowRegistry\WorkflowTriggerRegistry;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Services\AwardsWorkflowActions;
use Awards\Services\AwardsWorkflowProvider;
use Awards\Services\RecommendationApprovalDecisionService;
use Awards\Services\RecommendationApprovalProcessService;
use Awards\Services\RecommendationApprovalWorkflowSyncService;
use Awards\Services\RecommendationMigrationService;
use Cake\Core\ContainerInterface;
use Cake\I18n\DateTime;

class RecommendationApprovalProcessServiceTest extends BaseTestCase
{
    private RecommendationApprovalProcessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $this->clearWorkflowRegistries();
        $this->service = new RecommendationApprovalProcessService();
    }

    protected function tearDown(): void
    {
        $this->clearWorkflowRegistries();

        parent::tearDown();
    }

    public function testStartProcessCreatesRunAndDynamicApprovalConfig(): void
    {
        [$recommendation, $instanceId] = $this->buildApprovalScenario([
            $this->stepData('local', 'Local approval', 1),
        ]);

        $result = $this->service->startProcess(
            ['instanceId' => $instanceId],
            ['recommendationId' => (int)$recommendation->id],
        );

        $this->assertTrue($result->isSuccess(), $result->getError() ?? '');
        $this->assertSame('local', $result->data['currentStepKey']);
        $this->assertSame([self::ADMIN_MEMBER_ID], $result->data['approverIds']);
        $this->assertSame(1, $result->data['requiredCount']);
        $this->assertSame(
            'Awards.ResolveApprovalStepApprovers',
            $result->data['approvalApproverConfig']['service'],
        );
        $this->assertArrayNotHasKey('eligible_member_ids', $result->data['approvalApproverConfig']);
        $this->assertSame(
            ApprovalProcessStep::APPROVER_TYPE_MEMBER,
            $result->data['approvalApproverConfig']['award_approval_approver_type'],
        );
        $this->assertSame(self::ADMIN_MEMBER_ID, $result->data['approvalApproverConfig']['member_id']);
        $this->assertTrue($result->data['approvalApproverConfig']['award_approval_is_final_step']);
        $this->assertSame('Submitted', $this->freshRecommendationState((int)$recommendation->id));
    }

    public function testAdvanceProcessMovesToNextStepAndFinalApprovalCompletesRun(): void
    {
        [$recommendation, $instanceId] = $this->buildApprovalScenario([
            $this->stepData('local', 'Local approval', 1),
            $this->stepData('crown', 'Crown approval', 2),
        ]);
        $started = $this->service->startProcess(
            ['instanceId' => $instanceId],
            ['recommendationId' => (int)$recommendation->id],
        );
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $this->assertFalse($started->data['approvalApproverConfig']['award_approval_is_final_step']);

        $advanced = $this->service->advanceProcess(
            ['instanceId' => $instanceId, 'approval' => ['approvalStatus' => 'approved']],
            [],
        );

        $this->assertTrue($advanced->isSuccess(), $advanced->getError() ?? '');
        $this->assertSame('crown', $advanced->data['currentStepKey']);
        $this->assertTrue($advanced->data['approvalApproverConfig']['award_approval_is_final_step']);

        $completed = $this->service->advanceProcess(
            ['instanceId' => $instanceId, 'approval' => ['approvalStatus' => 'approved']],
            [],
        );

        $this->assertTrue($completed->isSuccess(), $completed->getError() ?? '');
        $this->assertTrue($completed->data['completed']);
        $this->assertSame(RecommendationApprovalRun::STATUS_APPROVED, $completed->data['status']);
        $this->assertSame((int)$recommendation->id, $completed->data['recommendationId']);
        $this->assertSame('Submitted', $this->freshRecommendationState((int)$recommendation->id));
    }

    public function testRejectedLaterStepClosesRunAsRejected(): void
    {
        [$recommendation, $instanceId] = $this->buildApprovalScenario([
            $this->stepData('local', 'Local approval', 1),
            $this->stepData('crown', 'Crown approval', 2),
        ]);
        $this->service->startProcess(['instanceId' => $instanceId], ['recommendationId' => (int)$recommendation->id]);
        $this->service->advanceProcess(
            ['instanceId' => $instanceId, 'approval' => ['approvalStatus' => 'approved']],
            [],
        );

        $rejectionComment = 'Not enough supporting evidence for this award.';
        $closed = $this->service->advanceProcess(
            [
                'instanceId' => $instanceId,
                'approval' => ['approvalStatus' => 'rejected'],
                'resumeData' => ['comment' => $rejectionComment],
            ],
            [],
        );

        $this->assertTrue($closed->isSuccess(), $closed->getError() ?? '');
        $this->assertTrue($closed->data['closed']);
        $this->assertSame(RecommendationApprovalRun::STATUS_CLOSED, $closed->data['status']);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')
            ->get((int)$closed->data['runId']);
        $this->assertSame(RecommendationApprovalRun::TERMINAL_REASON_REJECTED, $run->terminal_reason);
        $freshRecommendation = $this->getTableLocator()->get('Awards.Recommendations')->get((int)$recommendation->id);
        $this->assertSame('Submitted', $freshRecommendation->state);
        $this->assertSame($rejectionComment, $freshRecommendation->close_reason);
    }

    public function testDynamicResolverUsesCurrentConfiguredRoleTarget(): void
    {
        $role = $this->createRole();
        $oldRole = $this->createMemberRole(self::TEST_MEMBER_AGATHA_ID, (int)$role->id);
        [$recommendation, $instanceId] = $this->buildApprovalScenario([
            $this->roleStepData('role_approval', 'Role approval', 1, (int)$role->id),
        ]);
        $result = $this->service->startProcess(
            ['instanceId' => $instanceId],
            ['recommendationId' => (int)$recommendation->id],
        );

        $this->assertTrue(
            $result->isSuccess(),
            json_encode($result->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame([self::TEST_MEMBER_AGATHA_ID], $result->data['approverIds']);
        $this->assertArrayNotHasKey('eligible_member_ids', $result->data['approvalApproverConfig']);

        $memberRoles = $this->getTableLocator()->get('MemberRoles');
        $oldRole->expires_on = DateTime::now()->modify('-1 day');
        $memberRoles->saveOrFail($oldRole);
        $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);

        $approval = new WorkflowApproval([
            'workflow_instance_id' => $instanceId,
            'approver_config' => $result->data['approvalApproverConfig'],
        ]);

        $this->assertSame([self::TEST_MEMBER_BRYCE_ID], $this->service->resolveConfiguredApproverIds($approval));
    }

    public function testAllThresholdRefreshesWhenDynamicRoleTargetGainsApprover(): void
    {
        AwardsWorkflowProvider::register();
        $role = $this->createRole();
        $this->createMemberRole(self::TEST_MEMBER_AGATHA_ID, (int)$role->id);
        [$recommendation, $instanceId] = $this->buildApprovalScenario([
            $this->roleStepData(
                'role_approval_all',
                'Role approval',
                1,
                (int)$role->id,
                ApprovalProcessStep::THRESHOLD_ALL,
            ),
        ]);
        $result = $this->service->startProcess(
            ['instanceId' => $instanceId],
            ['recommendationId' => (int)$recommendation->id],
        );
        $this->assertTrue($result->isSuccess(), $result->getError() ?? '');
        $this->assertSame(1, $result->data['requiredCount']);

        $approvalId = $this->createWorkflowApproval(
            $instanceId,
            $result->data['approvalApproverConfig'],
            $result->data['requiredCount'],
        );

        $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);
        $manager = new DefaultWorkflowApprovalManager();
        $bryceApprovals = $manager->getPendingApprovalsForMember(self::TEST_MEMBER_BRYCE_ID);
        $this->assertContains($approvalId, array_map(static fn($approval): int => (int)$approval->id, $bryceApprovals));

        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get($approvalId);
        $this->assertSame(2, (int)$approval->required_count);
        $this->assertSame(0, (int)$approval->approved_count);

        $response = $manager->recordResponse($approvalId, self::TEST_MEMBER_AGATHA_ID, 'approve');
        $this->assertTrue($response->isSuccess(), $response->getError() ?? '');
        $this->assertSame('pending', $response->data['approvalStatus']);
        $this->assertTrue($response->data['needsMore']);

        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get($approvalId);
        $this->assertSame(2, (int)$approval->required_count);
        $this->assertSame(1, (int)$approval->approved_count);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $approval->status);
    }

    public function testStartProcessWithVacantApproverPoolCreatesBlockedGateThatSelfHeals(): void
    {
        AwardsWorkflowProvider::register();
        $role = $this->createRole();
        [$recommendation, $instanceId] = $this->buildApprovalScenario([
            $this->roleStepData('vacant', 'Vacant approval', 1, (int)$role->id),
        ]);

        // A vacant approver pool must not fail the submission workflow.
        $result = $this->service->startProcess(
            ['instanceId' => $instanceId],
            ['recommendationId' => (int)$recommendation->id],
        );
        $this->assertTrue($result->isSuccess(), $result->getError() ?? '');
        $this->assertSame([], $result->data['approverIds']);
        $this->assertTrue($result->data['blocked']);
        $this->assertSame(1, $result->data['requiredCount']);

        $approvalId = $this->createWorkflowApproval(
            $instanceId,
            $result->data['approvalApproverConfig'],
            $result->data['requiredCount'],
        );

        // Resolution marks the pending gate as blocked for admin surfacing.
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get($approvalId);
        $this->assertSame([], $this->service->resolveConfiguredApproverIds($approval));
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get($approvalId);
        $this->assertTrue((bool)($approval->approver_config['blocked_no_approvers'] ?? false));
        $this->assertSame(
            'Blocked — no eligible approvers',
            ApprovalsGridColumns::getPendingStatusLabel($approval),
        );

        // Filling the role self-heals the gate: it enters the member's queue
        // and the blocked flag clears.
        $this->createMemberRole(self::TEST_MEMBER_AGATHA_ID, (int)$role->id);
        $manager = new DefaultWorkflowApprovalManager();
        $pending = $manager->getPendingApprovalsForMember(self::TEST_MEMBER_AGATHA_ID);
        $this->assertContains(
            $approvalId,
            array_map(static fn($pendingApproval): int => (int)$pendingApproval->id, $pending),
        );
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get($approvalId);
        $this->assertFalse((bool)($approval->approver_config['blocked_no_approvers'] ?? false));
    }

    public function testLaterApprovalStepExcludesPriorResponderEvenWhenTheyStillQualify(): void
    {
        $role = $this->createRole();
        $this->createMemberRole(self::ADMIN_MEMBER_ID, (int)$role->id);
        $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);
        [$recommendation, $instanceId] = $this->buildApprovalScenario([
            $this->roleStepData('local', 'Local approval', 1, (int)$role->id),
            $this->roleStepData('crown', 'Crown approval', 2, (int)$role->id),
        ]);

        $started = $this->service->startProcess(
            ['instanceId' => $instanceId],
            ['recommendationId' => (int)$recommendation->id],
        );
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $this->assertContains(self::ADMIN_MEMBER_ID, $started->data['approverIds']);
        $this->assertContains(self::TEST_MEMBER_BRYCE_ID, $started->data['approverIds']);

        $approvalId = $this->createWorkflowApproval(
            $instanceId,
            $started->data['approvalApproverConfig'],
            $started->data['requiredCount'],
        );
        $this->createWorkflowApprovalResponse($approvalId, self::ADMIN_MEMBER_ID);

        $advanced = $this->service->advanceProcess(
            ['instanceId' => $instanceId, 'approval' => ['approvalStatus' => 'approved']],
            [],
        );

        $this->assertTrue($advanced->isSuccess(), $advanced->getError() ?? '');
        $this->assertSame('crown', $advanced->data['currentStepKey']);
        $this->assertSame([self::TEST_MEMBER_BRYCE_ID], $advanced->data['approverIds']);
        $this->assertSame(1, $advanced->data['requiredCount']);
    }

    public function testSubmittedWorkflowStartsAndAdvancesConfiguredApprovalProcess(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);

        $process = $this->getTableLocator()->get('Awards.ApprovalProcesses')->saveOrFail(
            $this->getTableLocator()->get('Awards.ApprovalProcesses')->newEntity([
                'name' => 'Workflow Submitted Approval Process ' . uniqid('', true),
                'is_active' => true,
                'approval_process_steps' => [
                    $this->stepData('local', 'Local approval', 1),
                    $this->stepData('crown', 'Crown approval', 2),
                ],
            ], ['associated' => ['ApprovalProcessSteps']]),
        );
        $award = $this->createAward((int)$process->id);

        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $result = $engine->startWorkflow('awards-recommendation-submitted', [
            'data' => [
                'requester_id' => self::ADMIN_MEMBER_ID,
                'member_id' => self::ADMIN_MEMBER_ID,
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'award_id' => (int)$award->id,
                'requester_sca_name' => 'Admin von Admin',
                'member_sca_name' => 'Admin von Admin',
                'contact_email' => 'admin@amp.ansteorra.org',
                'contact_number' => '555-555-0100',
                'reason' => 'Testing submitted workflow approval runtime',
                'call_into_court' => 'No',
                'court_availability' => 'Anytime',
            ],
            'requesterContext' => ['member_id' => self::ADMIN_MEMBER_ID],
            'submissionMode' => 'internal',
            'actorId' => self::ADMIN_MEMBER_ID,
            'branchId' => self::KINGDOM_BRANCH_ID,
        ], self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), $result->getError() ?? '');
        $this->assertFalse($result->data['ephemeral']);
        $workflowResult = $result->data['workflowResult'] ?? null;
        $this->assertIsArray($workflowResult);
        $this->assertTrue($workflowResult['success']);
        $this->assertArrayHasKey('recommendationId', $workflowResult['data']);
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get((int)$result->data['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instance->id])
            ->firstOrFail();
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $run->status);
        $this->assertSame('local', $run->current_step_key);

        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instance->id,
                'node_id' => 'award-approval-gate',
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $this->assertSame(WorkflowApproval::APPROVER_TYPE_DYNAMIC, $approval->approver_type);
        $this->assertSame([self::ADMIN_MEMBER_ID], $approval->approver_config['eligible_member_ids']);
        $this->assertSame(
            ApprovalProcessStep::APPROVER_TYPE_MEMBER,
            $approval->approver_config['award_approval_approver_type'],
        );
        $this->assertSame(self::ADMIN_MEMBER_ID, $approval->approver_config['member_id']);

        $firstResume = $engine->resumeWorkflow((int)$instance->id, 'award-approval-gate', 'approved', [
            'approverId' => self::ADMIN_MEMBER_ID,
            'decision' => 'approved',
        ]);
        $this->assertTrue($firstResume->isSuccess(), $firstResume->getError() ?? '');

        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get((int)$run->id);
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $run->status);
        $this->assertSame('crown', $run->current_step_key);
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get((int)$instance->id);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        $secondApproval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instance->id,
                'node_id' => 'award-approval-gate',
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame('crown', $secondApproval->approver_config['award_approval_step_key']);

        $secondResume = $engine->resumeWorkflow((int)$instance->id, 'award-approval-gate', 'approved', [
            'approverId' => self::ADMIN_MEMBER_ID,
            'decision' => 'approved',
        ]);
        $this->assertTrue($secondResume->isSuccess(), $secondResume->getError() ?? '');

        $instance = $this->getTableLocator()->get('WorkflowInstances')->get((int)$instance->id);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);

        $recommendationId = (int)$workflowResult['data']['recommendationId'];
        $bestowal = $this->getTableLocator()->get('Awards.Bestowals')->find()
            ->where(['primary_recommendation_id' => $recommendationId])
            ->firstOrFail();
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get((int)$run->id);
        $this->assertSame(RecommendationApprovalRun::STATUS_CONSUMED, $run->status);
        $this->assertSame(RecommendationApprovalRun::TERMINAL_REASON_CONSUMED_BY_BESTOWAL, $run->terminal_reason);
        $this->assertSame((int)$bestowal->id, (int)$run->consumed_by_bestowal_id);
        $this->assertSame((int)$award->id, (int)$bestowal->award_id);
        // Conversion advances the recommendation onto the board (no gathering yet).
        $this->assertSame('Need to Schedule', $this->freshRecommendationState($recommendationId));
    }

    public function testSyncBackfillsBeforeScanningActiveRuns(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $process = $this->createApprovalProcess([
            $this->stepData('crown', 'Crown approval', 1),
        ]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $migrationService = $this->createMock(RecommendationMigrationService::class);
        $migrationService->expects($this->once())
            ->method('backfillOpenApprovalRecommendations')
            ->with(self::ADMIN_MEMBER_ID)
            ->willReturnCallback(function () use ($engine, $process): ServiceResult {
                $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
                $this->assertTrue($started->isSuccess(), $started->getError() ?? '');

                return new ServiceResult(true, null, [
                    'candidateCount' => 1,
                    'startedCount' => 1,
                    'unchangedCount' => 0,
                    'skippedCount' => 0,
                    'failedCount' => 0,
                    'failures' => [],
                    'skips' => [],
                ]);
            });
        $service = new RecommendationApprovalWorkflowSyncService(
            new RecommendationApprovalProcessService(),
            $engine,
            new DefaultWorkflowVersionManager(),
            $migrationService,
        );

        $result = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), $result->getError() ?? '');
        $this->assertSame(1, $result->data['backfillCandidateCount']);
        $this->assertSame(1, $result->data['backfilledCount']);
        $this->assertSame(1, $result->data['processedCount']);
        $this->assertSame(
            1,
            $result->data['synchronizedCount'] + $result->data['unchangedCount'],
        );
    }

    public function testSyncClearsCurrentApproverAssignmentWhenCurrentStepSourceChanges(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $process = $this->createApprovalProcess([
            $this->stepData('crown', 'Crown approval', 1),
        ]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $instanceId = (int)$started->data['instanceId'];
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$approval->current_approver_id);

        $role = $this->createRole();
        $this->createMemberRole(self::ADMIN_MEMBER_ID, (int)$role->id);
        $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);
        $processStep = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where(['approval_process_id' => (int)$process->id, 'step_key' => 'crown'])
            ->firstOrFail();
        $processStep->approver_type = ApprovalProcessStep::APPROVER_TYPE_ROLE;
        $processStep->approver_source_id = (int)$role->id;
        $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->saveOrFail($processStep);

        $result = $this->createWorkflowSyncService($engine)
            ->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue(
            $result->isSuccess(),
            json_encode($result->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $result->data['synchronizedCount']);
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get((int)$approval->id);
        $this->assertSame(
            ApprovalProcessStep::APPROVER_TYPE_ROLE,
            $approval->approver_config['award_approval_approver_type'],
        );
        $this->assertEqualsCanonicalizing(
            [self::ADMIN_MEMBER_ID, self::TEST_MEMBER_BRYCE_ID],
            $approval->approver_config['eligible_member_ids'],
        );
        $this->assertNull(
            $approval->current_approver_id,
            'A source change to a shared pool must clear the stale single-member hard assignment.',
        );

        $decision = (new DefaultWorkflowApprovalManager())->recordResponse(
            (int)$approval->id,
            self::TEST_MEMBER_BRYCE_ID,
            'approve',
        );
        $this->assertTrue(
            $decision->isSuccess(),
            $decision->getError() ?? 'A newly resolved approver should be able to respond after synchronization.',
        );
    }

    public function testSyncClearsCurrentApproverAssignmentWhenSameRolePoolExpands(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $role = $this->createRole();
        $this->createMemberRole(self::ADMIN_MEMBER_ID, (int)$role->id);
        $process = $this->createApprovalProcess([
            $this->roleStepData('crown', 'Crown approval', 1, (int)$role->id),
        ]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $instanceId = (int)$started->data['instanceId'];
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$approval->current_approver_id);

        $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);
        $result = $this->createWorkflowSyncService($engine)
            ->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue(
            $result->isSuccess(),
            json_encode($result->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $result->data['synchronizedCount']);
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get((int)$approval->id);
        $this->assertEqualsCanonicalizing(
            [self::ADMIN_MEMBER_ID, self::TEST_MEMBER_BRYCE_ID],
            $approval->approver_config['eligible_member_ids'],
        );
        $this->assertNull(
            $approval->current_approver_id,
            'A shared pool must not remain pinned when the configured source itself is unchanged.',
        );

        $decision = (new DefaultWorkflowApprovalManager())->recordResponse(
            (int)$approval->id,
            self::TEST_MEMBER_BRYCE_ID,
            'approve',
        );
        $this->assertTrue($decision->isSuccess(), $decision->getError() ?? '');
    }

    public function testSyncCountsExistingApprovalWhenRequiredCountDecreases(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $role = $this->createRole();
        $this->createMemberRole(self::ADMIN_MEMBER_ID, (int)$role->id);
        $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);
        $step = $this->roleStepData(
            'crown',
            'Crown approval',
            1,
            (int)$role->id,
            ApprovalProcessStep::THRESHOLD_COUNT,
        );
        $step['required_count'] = 2;
        $process = $this->createApprovalProcess([$step]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $instanceId = (int)$started->data['instanceId'];

        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $this->assertSame(2, (int)$approval->required_count);
        $response = (new DefaultWorkflowApprovalManager())->recordResponse(
            (int)$approval->id,
            self::ADMIN_MEMBER_ID,
            'approve',
        );
        $this->assertTrue($response->isSuccess(), $response->getError() ?? '');
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $response->data['approvalStatus']);

        $currentStep = $step;
        $currentStep['required_count'] = 1;
        $currentProcess = $this->createApprovalProcess([$currentStep]);
        $runBeforeSync = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->firstOrFail();
        $recommendation = $this->getTableLocator()->get('Awards.Recommendations')
            ->get((int)$runBeforeSync->recommendation_id);
        $award = $this->getTableLocator()->get('Awards.Awards')->get((int)$recommendation->award_id);
        $award->approval_process_id = (int)$currentProcess->id;
        $this->getTableLocator()->get('Awards.Awards')->saveOrFail($award);
        $instanceBeforeSync = $this->getTableLocator()->get('WorkflowInstances')->get($instanceId);
        $versions = $this->getTableLocator()->get('WorkflowVersions');
        $currentVersion = $versions->saveOrFail($versions->newEntity([
            'workflow_definition_id' => (int)$instanceBeforeSync->workflow_definition_id,
            'version_number' => (int)$versions->find()
                ->where(['workflow_definition_id' => (int)$instanceBeforeSync->workflow_definition_id])
                ->count() + 1,
            'definition' => $definition,
            'status' => 'published',
        ]));
        $workflowDefinition = $this->getTableLocator()->get('WorkflowDefinitions')
            ->get((int)$instanceBeforeSync->workflow_definition_id);
        $workflowDefinition->current_version_id = (int)$currentVersion->id;
        $this->getTableLocator()->get('WorkflowDefinitions')->saveOrFail($workflowDefinition);

        $service = $this->createWorkflowSyncService($engine);
        $result = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue(
            $result->isSuccess(),
            json_encode($result->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $result->data['processedCount']);
        $this->assertSame(1, $result->data['synchronizedCount']);
        $this->assertSame(1, $result->data['advancedCount']);
        $this->assertSame(1, $result->data['versionMigratedCount']);
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get((int)$approval->id);
        $this->assertSame(1, (int)$approval->required_count);
        $this->assertSame(1, (int)$approval->approved_count);
        $this->assertSame(WorkflowApproval::STATUS_APPROVED, $approval->status);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get((int)$runBeforeSync->id);
        $this->assertSame((int)$currentProcess->id, (int)$run->approval_process_id);
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertSame((int)$currentVersion->id, (int)$instance->workflow_version_id);

        $secondResult = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);
        $this->assertTrue($secondResult->isSuccess(), $secondResult->getError() ?? '');
        $this->assertSame(0, $secondResult->data['processedCount']);
    }

    public function testSyncIncreasesCurrentGateThresholdWithoutLosingPartialApproval(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $role = $this->createRole();
        $this->createMemberRole(self::ADMIN_MEMBER_ID, (int)$role->id);
        $this->createMemberRole(self::TEST_MEMBER_AGATHA_ID, (int)$role->id);
        $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);
        $step = $this->roleStepData(
            'crown',
            'Crown approval',
            1,
            (int)$role->id,
            ApprovalProcessStep::THRESHOLD_COUNT,
        );
        $step['required_count'] = 2;
        $process = $this->createApprovalProcess([$step]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $instanceId = (int)$started->data['instanceId'];
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->firstOrFail();
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $this->assertSame(2, (int)$approval->required_count);
        $decision = (new DefaultWorkflowApprovalManager())->recordResponse(
            (int)$approval->id,
            self::ADMIN_MEMBER_ID,
            'approve',
        );
        $this->assertTrue($decision->isSuccess(), $decision->getError() ?? '');
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $decision->data['approvalStatus']);
        $response = $this->getTableLocator()->get('WorkflowApprovalResponses')->find()
            ->where([
                'workflow_approval_id' => (int)$approval->id,
                'member_id' => self::ADMIN_MEMBER_ID,
            ])
            ->firstOrFail();

        $processStep = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where(['approval_process_id' => (int)$process->id, 'step_key' => 'crown'])
            ->firstOrFail();
        $processStep->required_count = 3;
        $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->saveOrFail($processStep);

        $service = $this->createWorkflowSyncService($engine);
        $result = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue(
            $result->isSuccess(),
            json_encode($result->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $result->data['synchronizedCount']);
        $this->assertSame(0, $result->data['advancedCount']);
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get((int)$approval->id);
        $this->assertSame(3, (int)$approval->required_count);
        $this->assertSame(1, (int)$approval->approved_count);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $approval->status);
        $preservedResponse = $this->getTableLocator()->get('WorkflowApprovalResponses')->get((int)$response->id);
        $this->assertSame((int)$approval->id, (int)$preservedResponse->workflow_approval_id);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$preservedResponse->member_id);
        $this->assertSame('approve', $preservedResponse->decision);
        $this->assertSame(
            1,
            $this->getTableLocator()->get('WorkflowApprovalResponses')->find()
                ->where(['workflow_approval_id' => (int)$approval->id])
                ->count(),
        );
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get((int)$run->id);
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $run->status);
        $this->assertSame('crown', $run->current_step_key);
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        $secondResult = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);
        $this->assertTrue($secondResult->isSuccess(), $secondResult->getError() ?? '');
        $this->assertSame(1, $secondResult->data['unchangedCount']);
    }

    public function testSyncReopensApprovedGateWhenExpandedPoolRaisesThreshold(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $role = $this->createRole();
        $this->createMemberRole(self::ADMIN_MEMBER_ID, (int)$role->id);
        $step = $this->roleStepData(
            'crown',
            'Crown approval',
            1,
            (int)$role->id,
            ApprovalProcessStep::THRESHOLD_COUNT,
        );
        $step['required_count'] = 1;
        $process = $this->createApprovalProcess([$step]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $instanceId = (int)$started->data['instanceId'];
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->firstOrFail();
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $firstDecision = (new DefaultWorkflowApprovalManager())->recordResponse(
            (int)$approval->id,
            self::ADMIN_MEMBER_ID,
            'approve',
        );
        $this->assertTrue($firstDecision->isSuccess(), $firstDecision->getError() ?? '');
        $this->assertSame(WorkflowApproval::STATUS_APPROVED, $firstDecision->data['approvalStatus']);

        $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);
        $processStep = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where(['approval_process_id' => (int)$process->id, 'step_key' => 'crown'])
            ->firstOrFail();
        $processStep->required_count = 2;
        $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->saveOrFail($processStep);

        $service = $this->createWorkflowSyncService($engine);
        $result = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue(
            $result->isSuccess(),
            json_encode($result->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $result->data['synchronizedCount']);
        $this->assertSame(0, $result->data['advancedCount']);
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get((int)$approval->id);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $approval->status);
        $this->assertSame(2, (int)$approval->required_count);
        $this->assertSame(1, (int)$approval->approved_count);
        $this->assertNull($approval->current_approver_id);
        $this->assertSame(
            1,
            $this->getTableLocator()->get('WorkflowApprovalResponses')->find()
                ->where(['workflow_approval_id' => (int)$approval->id])
                ->count(),
        );

        $secondDecision = (new DefaultWorkflowApprovalManager())->recordResponse(
            (int)$approval->id,
            self::TEST_MEMBER_BRYCE_ID,
            'approve',
        );
        $this->assertTrue($secondDecision->isSuccess(), $secondDecision->getError() ?? '');
        $this->assertSame(WorkflowApproval::STATUS_APPROVED, $secondDecision->data['approvalStatus']);

        $resumeResult = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);
        $this->assertTrue($resumeResult->isSuccess(), $resumeResult->getError() ?? '');
        $this->assertSame(1, $resumeResult->data['advancedCount']);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get((int)$run->id);
        $this->assertSame(RecommendationApprovalRun::STATUS_CONSUMED, $run->status);
        $this->assertSame(
            1,
            $this->getTableLocator()->get('Awards.Bestowals')->find()
                ->where(['primary_recommendation_id' => (int)$run->recommendation_id])
                ->count(),
        );
    }

    public function testSyncDoesNotRepeatCompletedStepAfterProcessReorder(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $secondStep = $this->stepData('crown', 'Crown approval', 2);
        $secondStep['approver_source_id'] = self::TEST_MEMBER_BRYCE_ID;
        $process = $this->createApprovalProcess([
            $this->stepData('local', 'Local approval', 1),
            $secondStep,
        ]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $instanceId = (int)$started->data['instanceId'];
        $approvals = $this->getTableLocator()->get('WorkflowApprovals');
        $firstApproval = $approvals->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $decisionService = new RecommendationApprovalDecisionService(
            new DefaultWorkflowApprovalManager(),
            $engine,
        );

        $firstDecision = $decisionService->decide(
            $firstApproval,
            self::ADMIN_MEMBER_ID,
            'approve',
            null,
        );
        $this->assertTrue($firstDecision->isSuccess(), $firstDecision->getError() ?? '');
        $this->assertSame(WorkflowApproval::STATUS_APPROVED, $firstDecision->data['approvalStatus']);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->firstOrFail();
        $this->assertSame('crown', $run->current_step_key);

        $steps = $this->getTableLocator()->get('Awards.ApprovalProcessSteps');
        $localStep = $steps->find()
            ->where(['approval_process_id' => (int)$process->id, 'step_key' => 'local'])
            ->firstOrFail();
        $crownStep = $steps->find()
            ->where(['approval_process_id' => (int)$process->id, 'step_key' => 'crown'])
            ->firstOrFail();
        $localStep->sequence = 2;
        $crownStep->sequence = 1;
        $steps->saveOrFail($localStep);
        $steps->saveOrFail($crownStep);

        $syncResult = $this->createWorkflowSyncService($engine)
            ->syncOpenRecommendations(self::ADMIN_MEMBER_ID);
        $this->assertTrue(
            $syncResult->isSuccess(),
            json_encode($syncResult->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $syncResult->data['synchronizedCount']);
        $currentApproval = $approvals->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame('crown', $currentApproval->approver_config['award_approval_step_key']);
        $this->assertTrue((bool)$currentApproval->approver_config['award_approval_is_final_step']);
        $this->assertTrue((bool)$currentApproval->approver_config['requires_bestowal_gathering']);

        $secondDecision = $decisionService->decide(
            $currentApproval,
            self::TEST_MEMBER_BRYCE_ID,
            'approve',
            null,
        );
        $this->assertTrue($secondDecision->isSuccess(), $secondDecision->getError() ?? '');
        $pendingStepKeys = $approvals->find()
            ->select(['approver_config'])
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->all()
            ->map(static fn(WorkflowApproval $approval): ?string => (
                $approval->approver_config['award_approval_step_key'] ?? null
            ))
            ->toList();
        $this->assertSame(
            [],
            $pendingStepKeys,
            'A completed step must not be requested again after it is reordered behind the current step.',
        );
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
    }

    public function testSyncFinalThresholdDecreasePreservesSelectedBestowalGathering(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $role = $this->createRole();
        $this->createMemberRole(self::ADMIN_MEMBER_ID, (int)$role->id);
        $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);
        $step = $this->roleStepData(
            'crown',
            'Crown approval',
            1,
            (int)$role->id,
            ApprovalProcessStep::THRESHOLD_COUNT,
        );
        $step['required_count'] = 2;
        $process = $this->createApprovalProcess([$step]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $instanceId = (int)$started->data['instanceId'];
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->firstOrFail();
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $gathering = $this->getTableLocator()->get('Gatherings')->find()
            ->select(['id'])
            ->firstOrFail();
        $decisionService = new RecommendationApprovalDecisionService(
            new DefaultWorkflowApprovalManager(),
            $engine,
        );

        $firstDecision = $decisionService->decide(
            $approval,
            self::ADMIN_MEMBER_ID,
            'approve',
            null,
            (int)$gathering->id,
        );
        $this->assertTrue($firstDecision->isSuccess(), $firstDecision->getError() ?? '');
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $firstDecision->data['approvalStatus']);

        $processStep = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where(['approval_process_id' => (int)$process->id, 'step_key' => 'crown'])
            ->firstOrFail();
        $processStep->required_count = 1;
        $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->saveOrFail($processStep);

        $syncResult = $this->createWorkflowSyncService($engine)
            ->syncOpenRecommendations(self::ADMIN_MEMBER_ID);
        $this->assertTrue(
            $syncResult->isSuccess(),
            json_encode($syncResult->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $syncResult->data['advancedCount']);
        $bestowal = $this->getTableLocator()->get('Awards.Bestowals')->find()
            ->where(['primary_recommendation_id' => (int)$run->recommendation_id])
            ->firstOrFail();
        $actualGatheringId = $bestowal->gathering_id === null ? null : (int)$bestowal->gathering_id;
        $this->assertSame(
            (int)$gathering->id,
            $actualGatheringId,
            'The gathering selected with the first approval must survive threshold-driven auto-advance.',
        );
    }

    public function testSyncDoesNotAdvanceSatisfiedGateWhileCurrentApproverPoolIsEmpty(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $role = $this->createRole();
        $adminRole = $this->createMemberRole(self::ADMIN_MEMBER_ID, (int)$role->id);
        $bryceRole = $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);
        $step = $this->roleStepData(
            'crown',
            'Crown approval',
            1,
            (int)$role->id,
            ApprovalProcessStep::THRESHOLD_COUNT,
        );
        $step['required_count'] = 2;
        $process = $this->createApprovalProcess([$step]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $instanceId = (int)$started->data['instanceId'];
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $response = (new DefaultWorkflowApprovalManager())->recordResponse(
            (int)$approval->id,
            self::ADMIN_MEMBER_ID,
            'approve',
        );
        $this->assertTrue($response->isSuccess(), $response->getError() ?? '');
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $response->data['approvalStatus']);

        $memberRoles = $this->getTableLocator()->get('MemberRoles');
        $adminRole->expires_on = DateTime::now()->modify('-1 day');
        $bryceRole->expires_on = DateTime::now()->modify('-1 day');
        $memberRoles->saveOrFail($adminRole);
        $memberRoles->saveOrFail($bryceRole);
        $processStep = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where(['approval_process_id' => (int)$process->id, 'step_key' => 'crown'])
            ->firstOrFail();
        $processStep->required_count = 1;
        $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->saveOrFail($processStep);

        $service = $this->createWorkflowSyncService($engine);
        $result = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), $result->getError() ?? '');
        $this->assertSame(0, $result->data['advancedCount']);
        $this->assertSame(1, $result->data['synchronizedCount']);
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get((int)$approval->id);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $approval->status);
        $this->assertSame(1, (int)$approval->required_count);
        $this->assertSame(1, (int)$approval->approved_count);
        $this->assertTrue((bool)$approval->approver_config['blocked_no_approvers']);
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        $secondResult = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);
        $this->assertTrue($secondResult->isSuccess(), $secondResult->getError() ?? '');
        $this->assertSame(1, $secondResult->data['unchangedCount']);
    }

    public function testSyncRetargetsRemovedCurrentStepWithoutDeletingHistory(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $process = $this->createApprovalProcess([
            $this->stepData('old_crown', 'Old Crown approval', 1),
        ]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $instanceId = (int)$started->data['instanceId'];
        $oldApproval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();

        $processStep = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where(['approval_process_id' => (int)$process->id, 'step_key' => 'old_crown'])
            ->firstOrFail();
        $processStep->step_key = 'new_crown';
        $processStep->label = 'New Crown approval';
        $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->saveOrFail($processStep);

        $service = $this->createWorkflowSyncService($engine);
        $result = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), $result->getError() ?? '');
        $this->assertSame(1, $result->data['synchronizedCount']);
        $oldApproval = $this->getTableLocator()->get('WorkflowApprovals')->get((int)$oldApproval->id);
        $this->assertSame(WorkflowApproval::STATUS_CANCELLED, $oldApproval->status);
        $replacement = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $this->assertSame('new_crown', $replacement->approver_config['award_approval_step_key']);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->firstOrFail();
        $this->assertSame('new_crown', $run->current_step_key);
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);

        $secondResult = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);
        $this->assertTrue($secondResult->isSuccess(), $secondResult->getError() ?? '');
        $this->assertSame(1, $secondResult->data['unchangedCount']);
        $pendingApprovalIds = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->select(['id'])
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->all()
            ->extract('id')
            ->map(static fn($id): int => (int)$id)
            ->toList();
        $this->assertSame([(int)$replacement->id], $pendingApprovalIds);
    }

    public function testSyncCompletesWhenRemovedCurrentStepLeavesNoIncompleteWork(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $finalStep = $this->stepData('crown', 'Crown approval', 2);
        $finalStep['approver_source_id'] = self::TEST_MEMBER_BRYCE_ID;
        $process = $this->createApprovalProcess([
            $this->stepData('local', 'Local approval', 1),
            $finalStep,
        ]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $instanceId = (int)$started->data['instanceId'];
        $firstApproval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $decisionService = new RecommendationApprovalDecisionService(
            new DefaultWorkflowApprovalManager(),
            $engine,
        );
        $firstDecision = $decisionService->decide(
            $firstApproval,
            self::ADMIN_MEMBER_ID,
            'approve',
            null,
        );
        $this->assertTrue($firstDecision->isSuccess(), $firstDecision->getError() ?? '');
        $obsoleteApproval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame('crown', $obsoleteApproval->approver_config['award_approval_step_key']);
        $steps = $this->getTableLocator()->get('Awards.ApprovalProcessSteps');
        $removedStep = $steps->find()
            ->where(['approval_process_id' => (int)$process->id, 'step_key' => 'crown'])
            ->firstOrFail();
        $steps->deleteOrFail($removedStep);

        $result = $this->createWorkflowSyncService($engine)
            ->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue(
            $result->isSuccess(),
            json_encode($result->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $result->data['advancedCount']);
        $obsoleteApproval = $this->getTableLocator()->get('WorkflowApprovals')->get((int)$obsoleteApproval->id);
        $this->assertSame(WorkflowApproval::STATUS_CANCELLED, $obsoleteApproval->status);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->firstOrFail();
        $this->assertSame(RecommendationApprovalRun::STATUS_CONSUMED, $run->status);
        $this->assertSame(
            1,
            $this->getTableLocator()->get('Awards.Bestowals')->find()
                ->where(['primary_recommendation_id' => (int)$run->recommendation_id])
                ->count(),
        );
    }

    public function testSyncDoesNotResumeSatisfiedGateForClosedRecommendation(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $role = $this->createRole();
        $this->createMemberRole(self::ADMIN_MEMBER_ID, (int)$role->id);
        $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);
        $step = $this->roleStepData(
            'crown',
            'Crown approval',
            1,
            (int)$role->id,
            ApprovalProcessStep::THRESHOLD_COUNT,
        );
        $step['required_count'] = 2;
        $process = $this->createApprovalProcess([$step]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $instanceId = (int)$started->data['instanceId'];
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $response = (new DefaultWorkflowApprovalManager())->recordResponse(
            (int)$approval->id,
            self::ADMIN_MEMBER_ID,
            'approve',
        );
        $this->assertTrue($response->isSuccess(), $response->getError() ?? '');
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $response->data['approvalStatus']);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->firstOrFail();
        $recommendationId = (int)$run->recommendation_id;
        $this->getTableLocator()->get('Awards.Recommendations')->updateAll(
            ['status' => 'Closed', 'state' => 'No Action'],
            ['id' => $recommendationId],
        );
        $processStep = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where(['approval_process_id' => (int)$process->id, 'step_key' => 'crown'])
            ->firstOrFail();
        $processStep->required_count = 1;
        $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->saveOrFail($processStep);

        $result = $this->createWorkflowSyncService($engine)
            ->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertSame(
            0,
            $result->data['advancedCount'],
            'A terminal recommendation must not be advanced by approval synchronization.',
        );
        $this->assertSame(
            0,
            $this->getTableLocator()->get('Awards.Bestowals')->find()
                ->where(['primary_recommendation_id' => $recommendationId])
                ->count(),
            'Approval synchronization must not create a bestowal for a terminal recommendation.',
        );
        $recommendation = $this->getTableLocator()->get('Awards.Recommendations')->get($recommendationId);
        $this->assertSame('Closed', $recommendation->status);
        $this->assertSame('No Action', $recommendation->state);
    }

    public function testSyncHealsApprovedGateWhoseWorkflowHasNotResumed(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $process = $this->createApprovalProcess([
            $this->stepData('crown', 'Crown approval', 1),
        ]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $instanceId = (int)$started->data['instanceId'];
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $response = (new DefaultWorkflowApprovalManager())->recordResponse(
            (int)$approval->id,
            self::ADMIN_MEMBER_ID,
            'approve',
        );
        $this->assertTrue($response->isSuccess(), $response->getError() ?? '');
        $this->assertSame(WorkflowApproval::STATUS_APPROVED, $response->data['approvalStatus']);
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->firstOrFail();
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $run->status);

        $result = $this->createWorkflowSyncService($engine)
            ->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue(
            $result->isSuccess(),
            json_encode($result->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $result->data['advancedCount']);
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get((int)$approval->id);
        $this->assertSame(
            WorkflowApproval::STATUS_APPROVED,
            $approval->status,
            'Crash recovery must preserve the committed approval audit state.',
        );
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get((int)$run->id);
        $this->assertSame(RecommendationApprovalRun::STATUS_CONSUMED, $run->status);
        $this->assertSame(
            1,
            $this->getTableLocator()->get('Awards.Bestowals')->find()
                ->where(['primary_recommendation_id' => (int)$run->recommendation_id])
                ->count(),
        );
    }

    public function testSyncRecoversFinalGatheringAfterApprovalResumeFailure(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $process = $this->createApprovalProcess([
            $this->stepData('crown', 'Crown approval', 1),
        ]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $instanceId = (int)$started->data['instanceId'];
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $gathering = $this->getTableLocator()->get('Gatherings')->find()
            ->select(['id'])
            ->firstOrFail();
        $failingEngine = $this->createMock(WorkflowEngineInterface::class);
        $failingEngine->expects($this->once())
            ->method('resumeWorkflow')
            ->willReturn(new ServiceResult(false, 'Simulated workflow resume interruption.'));
        $decisionResult = (new RecommendationApprovalDecisionService(
            new DefaultWorkflowApprovalManager(),
            $failingEngine,
        ))->decide(
            $approval,
            self::ADMIN_MEMBER_ID,
            'approve',
            null,
            (int)$gathering->id,
        );
        $this->assertFalse($decisionResult->isSuccess());
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get((int)$approval->id);
        $this->assertSame(WorkflowApproval::STATUS_APPROVED, $approval->status);
        $this->assertSame(
            (int)$gathering->id,
            (int)$approval->approver_config['bestowal_gathering_id'],
            'The final gathering must commit with the vote before workflow resumption.',
        );

        $syncResult = $this->createWorkflowSyncService($engine)
            ->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue(
            $syncResult->isSuccess(),
            json_encode($syncResult->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $syncResult->data['advancedCount']);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->firstOrFail();
        $bestowal = $this->getTableLocator()->get('Awards.Bestowals')->find()
            ->where(['primary_recommendation_id' => (int)$run->recommendation_id])
            ->firstOrFail();
        $this->assertSame((int)$gathering->id, (int)$bestowal->gathering_id);
    }

    public function testSyncHealsRejectedGateAfterItsConfiguredStepWasRemoved(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $step = $this->stepData('crown', 'Crown approval', 1);
        $step['approver_source_id'] = self::TEST_MEMBER_BRYCE_ID;
        $process = $this->createApprovalProcess([$step]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$process->id);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $instanceId = (int)$started->data['instanceId'];
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $rejectionComment = 'The submitted evidence does not support this award.';
        $response = (new DefaultWorkflowApprovalManager())->recordResponse(
            (int)$approval->id,
            self::TEST_MEMBER_BRYCE_ID,
            'reject',
            $rejectionComment,
        );
        $this->assertTrue($response->isSuccess(), $response->getError() ?? '');
        $this->assertSame(WorkflowApproval::STATUS_REJECTED, $response->data['approvalStatus']);
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->firstOrFail();
        $processStep = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where(['approval_process_id' => (int)$process->id, 'step_key' => 'crown'])
            ->firstOrFail();
        $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->deleteOrFail($processStep);

        $result = $this->createWorkflowSyncService($engine)
            ->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue(
            $result->isSuccess(),
            json_encode($result->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $result->data['advancedCount']);
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->get((int)$approval->id);
        $this->assertSame(WorkflowApproval::STATUS_REJECTED, $approval->status);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get((int)$run->id);
        $this->assertSame(RecommendationApprovalRun::STATUS_CLOSED, $run->status);
        $this->assertSame(RecommendationApprovalRun::TERMINAL_REASON_REJECTED, $run->terminal_reason);
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, (int)$run->modified_by);
        $recommendation = $this->getTableLocator()->get('Awards.Recommendations')
            ->get((int)$run->recommendation_id);
        $this->assertSame($rejectionComment, $recommendation->close_reason);
        $this->assertSame(self::TEST_MEMBER_BRYCE_ID, (int)$recommendation->modified_by);
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertSame(
            0,
            $this->getTableLocator()->get('Awards.Bestowals')->find()
                ->where(['primary_recommendation_id' => (int)$run->recommendation_id])
                ->count(),
        );
    }

    public function testSyncHealsRejectedGateAfterHistoricalApprovalProcessWasSoftDeleted(): void
    {
        $this->registerWorkflowRuntime();
        $definition = json_decode(
            (string)file_get_contents(CONFIG . 'Seeds/WorkflowDefinitions/awards-recommendation-submitted.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->publishSubmittedWorkflow($definition);
        $step = $this->stepData('crown', 'Crown approval', 1);
        $step['approver_source_id'] = self::TEST_MEMBER_BRYCE_ID;
        $historicalProcess = $this->createApprovalProcess([$step]);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());
        $started = $this->startSubmittedWorkflow($engine, (int)$historicalProcess->id);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? '');
        $instanceId = (int)$started->data['instanceId'];
        $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
        $rejectionComment = 'The recommendation should close even after the former process is retired.';
        $response = (new DefaultWorkflowApprovalManager())->recordResponse(
            (int)$approval->id,
            self::TEST_MEMBER_BRYCE_ID,
            'reject',
            $rejectionComment,
        );
        $this->assertTrue($response->isSuccess(), $response->getError() ?? '');
        $this->assertSame(WorkflowApproval::STATUS_REJECTED, $response->data['approvalStatus']);

        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where(['workflow_instance_id' => $instanceId])
            ->firstOrFail();
        $recommendation = $this->getTableLocator()->get('Awards.Recommendations')
            ->get((int)$run->recommendation_id);
        $replacementProcess = $this->createApprovalProcess([
            $this->stepData('new_crown', 'New Crown approval', 1),
        ]);
        $awards = $this->getTableLocator()->get('Awards.Awards');
        $award = $awards->get((int)$recommendation->award_id);
        $award->approval_process_id = (int)$replacementProcess->id;
        $awards->saveOrFail($award);
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses');
        $processes->deleteOrFail($historicalProcess);
        $this->assertFalse($processes->exists(['id' => (int)$historicalProcess->id]));

        $result = $this->createWorkflowSyncService($engine)
            ->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue(
            $result->isSuccess(),
            json_encode($result->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertSame(1, $result->data['advancedCount']);
        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get((int)$run->id);
        $this->assertSame(RecommendationApprovalRun::STATUS_CLOSED, $run->status);
        $this->assertSame(RecommendationApprovalRun::TERMINAL_REASON_REJECTED, $run->terminal_reason);
        $recommendation = $this->getTableLocator()->get('Awards.Recommendations')
            ->get((int)$run->recommendation_id);
        $this->assertSame($rejectionComment, $recommendation->close_reason);
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($instanceId);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status);
        $this->assertSame(
            0,
            $this->getTableLocator()->get('Awards.Bestowals')->find()
                ->where(['primary_recommendation_id' => (int)$run->recommendation_id])
                ->count(),
        );
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     * @return array{0: \Awards\Model\Entity\Recommendation, 1: int}
     */
    private function buildApprovalScenario(array $steps): array
    {
        $process = $this->getTableLocator()->get('Awards.ApprovalProcesses')->saveOrFail(
            $this->getTableLocator()->get('Awards.ApprovalProcesses')->newEntity([
                'name' => 'Test Approval Process ' . uniqid('', true),
                'is_active' => true,
                'approval_process_steps' => $steps,
            ], ['associated' => ['ApprovalProcessSteps']]),
        );
        $award = $this->createAward((int)$process->id);
        $recommendation = $this->createRecommendation((int)$award->id);
        $instanceId = $this->createWorkflowInstance();

        return [$recommendation, $instanceId];
    }

    /**
     * @param array<int, array<string, mixed>> $steps Approval steps.
     */
    private function createApprovalProcess(array $steps)
    {
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses');

        return $processes->saveOrFail($processes->newEntity([
            'name' => 'Workflow Sync Approval Process ' . uniqid('', true),
            'is_active' => true,
            'approval_process_steps' => $steps,
        ], ['associated' => ['ApprovalProcessSteps']]));
    }

    private function createWorkflowSyncService(DefaultWorkflowEngine $engine): RecommendationApprovalWorkflowSyncService
    {
        $migrationService = $this->createStub(RecommendationMigrationService::class);
        $migrationService->method('backfillOpenApprovalRecommendations')->willReturn(new ServiceResult(true, null, [
            'candidateCount' => 0,
            'startedCount' => 0,
            'unchangedCount' => 0,
            'skippedCount' => 0,
            'failedCount' => 0,
            'failures' => [],
            'skips' => [],
        ]));

        return new RecommendationApprovalWorkflowSyncService(
            new RecommendationApprovalProcessService(),
            $engine,
            new DefaultWorkflowVersionManager(),
            $migrationService,
        );
    }

    private function startSubmittedWorkflow(DefaultWorkflowEngine $engine, int $processId)
    {
        $award = $this->createAward($processId);

        return $engine->startWorkflow('awards-recommendation-submitted', [
            'data' => [
                'requester_id' => self::ADMIN_MEMBER_ID,
                'member_id' => self::ADMIN_MEMBER_ID,
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'award_id' => (int)$award->id,
                'requester_sca_name' => 'Admin von Admin',
                'member_sca_name' => 'Admin von Admin',
                'contact_email' => 'admin@amp.ansteorra.org',
                'contact_number' => '555-555-0100',
                'reason' => 'Testing approval workflow synchronization',
                'call_into_court' => 'No',
                'court_availability' => 'Anytime',
            ],
            'requesterContext' => ['member_id' => self::ADMIN_MEMBER_ID],
            'submissionMode' => 'internal',
            'actorId' => self::ADMIN_MEMBER_ID,
            'branchId' => self::KINGDOM_BRANCH_ID,
        ], self::ADMIN_MEMBER_ID);
    }

    /**
     * @return array<string, mixed>
     */
    private function stepData(string $key, string $label, int $sequence): array
    {
        return [
            'step_key' => $key,
            'label' => $label,
            'sequence' => $sequence,
            'step_type' => ApprovalProcessStep::STEP_TYPE_APPROVAL,
            'approver_type' => ApprovalProcessStep::APPROVER_TYPE_MEMBER,
            'approver_source_id' => self::ADMIN_MEMBER_ID,
            'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
            'threshold_mode' => ApprovalProcessStep::THRESHOLD_ANY,
            'on_reject' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'on_request_changes' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'retain_read_visibility' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function roleStepData(
        string $key,
        string $label,
        int $sequence,
        int $roleId,
        string $thresholdMode = ApprovalProcessStep::THRESHOLD_ANY,
    ): array {
        return [
            'step_key' => $key,
            'label' => $label,
            'sequence' => $sequence,
            'step_type' => ApprovalProcessStep::STEP_TYPE_APPROVAL,
            'approver_type' => ApprovalProcessStep::APPROVER_TYPE_ROLE,
            'approver_source_id' => $roleId,
            'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
            'threshold_mode' => $thresholdMode,
            'on_reject' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'on_request_changes' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
            'retain_read_visibility' => true,
        ];
    }

    private function createWorkflowApproval(int $instanceId, array $approverConfig, int $requiredCount): int
    {
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
            'execution_log_id' => $log->id,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            'approver_config' => $approverConfig,
            'required_count' => $requiredCount,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'allow_parallel' => false,
            'version' => 1,
        ]));

        return (int)$approval->id;
    }

    private function createWorkflowApprovalResponse(int $approvalId, int $memberId): void
    {
        $responses = $this->getTableLocator()->get('WorkflowApprovalResponses');
        $responses->saveOrFail($responses->newEntity([
            'workflow_approval_id' => $approvalId,
            'member_id' => $memberId,
            'decision' => 'approved',
            'responded_at' => DateTime::now(),
        ]));
    }

    private function createRole()
    {
        $roles = $this->getTableLocator()->get('Roles');

        return $roles->saveOrFail($roles->newEntity([
            'name' => 'Approval Dynamic Role ' . uniqid('', true),
        ]));
    }

    private function createMemberRole(int $memberId, int $roleId)
    {
        $memberRoles = $this->getTableLocator()->get('MemberRoles');
        $memberRole = $memberRoles->newEmptyEntity();
        $memberRole->member_id = $memberId;
        $memberRole->role_id = $roleId;
        $memberRole->branch_id = self::KINGDOM_BRANCH_ID;
        $memberRole->approver_id = self::ADMIN_MEMBER_ID;
        $memberRole->start_on = DateTime::now()->modify('-1 day');
        $memberRole->expires_on = DateTime::now()->modify('+30 days');

        return $memberRoles->saveOrFail($memberRole);
    }

    /**
     * @return \Awards\Model\Entity\Award
     */
    private function createAward(int $processId)
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');

        return $awards->saveOrFail($awards->newEntity([
            'name' => 'Approval Runtime Award ' . uniqid('', true),
            'abbreviation' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approval_process_id' => $processId,
            'is_active' => true,
        ]));
    }

    private function createRecommendation(int $awardId): Recommendation
    {
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');

        return $recommendations->saveOrFail($recommendations->newEntity([
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'award_id' => $awardId,
            'status' => 'In Progress',
            'state' => 'Submitted',
            'state_date' => DateTime::now(),
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@amp.ansteorra.org',
            'contact_number' => '555-555-0100',
            'reason' => 'Testing approval runtime',
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]));
    }

    private function createWorkflowInstance(): int
    {
        $definitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $versions = $this->getTableLocator()->get('WorkflowVersions');
        $instances = $this->getTableLocator()->get('WorkflowInstances');

        $definition = $definitions->saveOrFail($definitions->newEntity([
            'name' => 'Award Approval Runtime ' . uniqid('', true),
            'slug' => 'award-approval-runtime-' . uniqid(),
            'trigger_type' => 'manual',
            'is_active' => true,
        ]));
        $version = $versions->saveOrFail($versions->newEntity([
            'workflow_definition_id' => $definition->id,
            'version_number' => 1,
            'definition' => [
                'nodes' => [
                    'trigger' => ['type' => 'trigger', 'outputs' => [['target' => 'end']]],
                    'end' => ['type' => 'end', 'outputs' => []],
                ],
            ],
            'status' => 'published',
        ]));

        $definition->current_version_id = $version->id;
        $definitions->saveOrFail($definition);

        $instance = $instances->saveOrFail($instances->newEntity([
            'workflow_definition_id' => $definition->id,
            'workflow_version_id' => $version->id,
            'status' => 'waiting',
        ]));

        return (int)$instance->id;
    }

    private function freshRecommendationState(int $recommendationId): string
    {
        return (string)$this->getTableLocator()
            ->get('Awards.Recommendations')
            ->get($recommendationId)
            ->state;
    }

    private function publishSubmittedWorkflow(array $definition): void
    {
        $definitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $versions = $this->getTableLocator()->get('WorkflowVersions');
        $definitionEntity = $definitions->find()
            ->where(['slug' => 'awards-recommendation-submitted'])
            ->first();
        if (!$definitionEntity) {
            $definitionEntity = $definitions->newEntity([
                'name' => 'Award Recommendation Submitted',
                'slug' => 'awards-recommendation-submitted',
                'trigger_type' => 'event',
                'trigger_config' => ['event' => 'Awards.RecommendationCreateRequested'],
                'entity_type' => 'Awards',
                'is_active' => true,
            ]);
        }
        $definitionEntity->execution_mode = 'durable';
        $definitionEntity->is_active = true;
        $definitionEntity = $definitions->saveOrFail($definitionEntity);
        $versionNumber = (int)$versions->find()
            ->where(['workflow_definition_id' => $definitionEntity->id])
            ->count() + 1;
        $version = $versions->saveOrFail($versions->newEntity([
            'workflow_definition_id' => $definitionEntity->id,
            'version_number' => $versionNumber,
            'definition' => $definition,
            'status' => 'published',
        ]));

        $definitionEntity->current_version_id = $version->id;
        $definitions->saveOrFail($definitionEntity);
    }

    private function registerWorkflowRuntime(): void
    {
        AwardsWorkflowProvider::register();
        WorkflowConditionRegistry::register('Core', [
            [
                'condition' => 'Core.FieldEquals',
                'label' => 'Field Equals Value',
                'description' => 'Check if a context field equals a specific value',
                'evaluatorClass' => CoreConditions::class,
                'evaluatorMethod' => 'fieldEquals',
                'inputSchema' => [],
            ],
        ]);
    }

    private function buildWorkflowContainer(): ContainerInterface
    {
        $actions = new AwardsWorkflowActions(
            approvalProcessService: new RecommendationApprovalProcessService(),
        );
        $conditions = new CoreConditions();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            static fn($class): bool => in_array($class, [AwardsWorkflowActions::class, CoreConditions::class], true),
        );
        $container->method('get')->willReturnCallback(
            static fn($class): object => match ($class) {
                AwardsWorkflowActions::class => $actions,
                CoreConditions::class => $conditions,
                default => throw new RuntimeException("Unexpected workflow service {$class}"),
            },
        );

        return $container;
    }

    private function clearWorkflowRegistries(): void
    {
        WorkflowTriggerRegistry::clear();
        WorkflowActionRegistry::clear();
        WorkflowApproverResolverRegistry::clear();
        WorkflowConditionRegistry::clear();
        WorkflowEntityRegistry::clear();
    }
}
