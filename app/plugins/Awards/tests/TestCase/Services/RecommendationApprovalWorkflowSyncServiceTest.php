<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\WorkflowApproval;
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
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Services\AwardsWorkflowActions;
use Awards\Services\AwardsWorkflowProvider;
use Awards\Services\RecommendationApprovalProcessService;
use Awards\Services\RecommendationApprovalWorkflowSyncService;
use Awards\Services\RecommendationMigrationService;
use Cake\Core\ContainerInterface;
use Cake\Datasource\EntityInterface;
use Cake\I18n\DateTime;
use RuntimeException;

class RecommendationApprovalWorkflowSyncServiceTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->clearWorkflowRegistries();
    }

    protected function tearDown(): void
    {
        $this->clearWorkflowRegistries();

        parent::tearDown();
    }

    public function testThresholdAnyUpgradeCompletesSubmittedAndMigratedWorkflowsWithoutLosingEvidence(): void
    {
        $this->registerWorkflowRuntime();
        $this->publishWorkflow(
            'awards-recommendation-submitted',
            'Award Recommendation Submitted',
            'Awards.RecommendationCreateRequested',
            'Awards',
        );
        $this->publishWorkflow(
            'awards-existing-recommendation-approval',
            'Existing Award Recommendation Approval',
            'Awards.ExistingRecommendationApprovalRequested',
            'Awards.Recommendations',
        );

        $role = $this->createRole();
        $this->createMemberRole(self::ADMIN_MEMBER_ID, (int)$role->id);
        $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);
        $process = $this->createApprovalProcess((int)$role->id);
        $award = $this->createAward((int)$process->id);
        $engine = new DefaultWorkflowEngine($this->buildWorkflowContainer());

        $submitted = $this->startSubmittedWorkflow($engine, (int)$award->id);
        $migratedRecommendation = $this->createRecommendation((int)$award->id);
        $migrated = $engine->startWorkflow('awards-existing-recommendation-approval', [
            'recommendationId' => (int)$migratedRecommendation->id,
            'actorId' => self::ADMIN_MEMBER_ID,
            'migration' => true,
        ], self::ADMIN_MEMBER_ID);

        $this->assertTrue($submitted->isSuccess(), $submitted->getError() ?? 'Submitted workflow did not start.');
        $this->assertTrue($migrated->isSuccess(), $migrated->getError() ?? 'Migrated workflow did not start.');

        $fixtures = [];
        foreach (
            [
                'normal submission' => (int)$submitted->data['instanceId'],
                'migrated existing' => (int)$migrated->data['instanceId'],
            ] as $origin => $instanceId
        ) {
            $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
                ->where(['workflow_instance_id' => $instanceId])
                ->firstOrFail();
            $approval = $this->getTableLocator()->get('WorkflowApprovals')->find()
                ->where([
                    'workflow_instance_id' => $instanceId,
                    'status' => WorkflowApproval::STATUS_PENDING,
                ])
                ->firstOrFail();
            $this->assertSame(2, (int)$approval->required_count, "{$origin} must begin as a two-person gate.");

            $decision = (new DefaultWorkflowApprovalManager())->recordResponse(
                (int)$approval->id,
                self::ADMIN_MEMBER_ID,
                'approve',
            );
            $this->assertTrue($decision->isSuccess(), $decision->getError() ?? "{$origin} approval failed.");
            $this->assertSame(WorkflowApproval::STATUS_PENDING, $decision->data['approvalStatus']);

            $response = $this->getTableLocator()->get('WorkflowApprovalResponses')->find()
                ->where([
                    'workflow_approval_id' => (int)$approval->id,
                    'member_id' => self::ADMIN_MEMBER_ID,
                ])
                ->firstOrFail();
            $fixtures[$origin] = [
                'instanceId' => $instanceId,
                'runId' => (int)$run->id,
                'recommendationId' => (int)$run->recommendation_id,
                'approvalId' => (int)$approval->id,
                'responseId' => (int)$response->id,
            ];
        }

        $step = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where([
                'approval_process_id' => (int)$process->id,
                'step_key' => 'crown',
            ])
            ->firstOrFail();
        $step->threshold_mode = ApprovalProcessStep::THRESHOLD_ANY;
        $step->required_count = null;
        $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->saveOrFail($step);

        $connection = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->getConnection();
        // Prove the sync temporarily owns the savepoint setup needed inside BaseTestCase's outer transaction.
        $connection->disableSavePoints();
        $service = $this->createWorkflowSyncService($engine);
        $result = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertTrue(
            $result->isSuccess(),
            json_encode($result->data['failures'] ?? [], JSON_THROW_ON_ERROR),
        );
        $this->assertFalse(
            $connection->isSavePointsEnabled(),
            'The sync must restore the connection savepoint setting after nested workflow transactions.',
        );
        $this->assertSame(2, $result->data['processedCount']);
        $this->assertSame(2, $result->data['synchronizedCount']);
        $this->assertSame(2, $result->data['advancedCount']);
        $this->assertSame(0, $result->data['failedCount']);

        $bestowalIds = [];
        foreach ($fixtures as $origin => $fixture) {
            $approval = $this->getTableLocator()->get('WorkflowApprovals')->get($fixture['approvalId']);
            $this->assertSame(WorkflowApproval::STATUS_APPROVED, $approval->status, $origin);
            $this->assertSame(1, (int)$approval->required_count, $origin);
            $this->assertSame(1, (int)$approval->approved_count, $origin);
            $this->assertSame(
                ApprovalProcessStep::THRESHOLD_ANY,
                $approval->approver_config['award_approval_threshold_mode'],
                $origin,
            );

            $response = $this->getTableLocator()->get('WorkflowApprovalResponses')->get($fixture['responseId']);
            $this->assertSame($fixture['approvalId'], (int)$response->workflow_approval_id, $origin);
            $this->assertSame(self::ADMIN_MEMBER_ID, (int)$response->member_id, $origin);
            $this->assertSame('approve', $response->decision, $origin);
            $this->assertSame(
                1,
                $this->getTableLocator()->get('WorkflowApprovalResponses')->find()
                    ->where(['workflow_approval_id' => $fixture['approvalId']])
                    ->count(),
                "{$origin} must retain exactly its original response.",
            );

            $instance = $this->getTableLocator()->get('WorkflowInstances')->get($fixture['instanceId']);
            $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $instance->status, $origin);

            $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get($fixture['runId']);
            $this->assertSame(RecommendationApprovalRun::STATUS_CONSUMED, $run->status, $origin);
            $this->assertSame(
                RecommendationApprovalRun::TERMINAL_REASON_CONSUMED_BY_BESTOWAL,
                $run->terminal_reason,
                $origin,
            );
            $this->assertNotNull($run->completed, $origin);

            $bestowal = $this->getTableLocator()->get('Awards.Bestowals')->find()
                ->where(['primary_recommendation_id' => $fixture['recommendationId']])
                ->firstOrFail();
            $bestowalIds[] = (int)$bestowal->id;
            $this->assertSame(Bestowal::SOURCE_RECOMMENDATION, $bestowal->source, $origin);
            $this->assertSame(Bestowal::LIFECYCLE_OPEN, $bestowal->lifecycle_status, $origin);
            $this->assertSame($fixture['runId'], (int)$bestowal->source_approval_run_id, $origin);
            $this->assertNull($bestowal->gathering_id, $origin);
            $this->assertNull($bestowal->gathering_scheduled_activity_id, $origin);
            $this->assertNull($bestowal->bestowed_at, $origin);
            $this->assertSame((int)$bestowal->id, (int)$run->consumed_by_bestowal_id, $origin);

            $recommendation = $this->getTableLocator()->get('Awards.Recommendations')
                ->get($fixture['recommendationId']);
            $this->assertSame('Scheduling', $recommendation->status, $origin);
            $this->assertSame('Need to Schedule', $recommendation->state, $origin);
            $this->assertSame((int)$bestowal->id, (int)$recommendation->bestowal_id, $origin);
        }

        $connection->enableSavePoints();
        $secondResult = $service->syncOpenRecommendations(self::ADMIN_MEMBER_ID);
        $this->assertTrue($secondResult->isSuccess(), $secondResult->getError() ?? 'Second sync failed.');
        $this->assertTrue(
            $connection->isSavePointsEnabled(),
            'The sync must preserve an already-enabled connection savepoint setting.',
        );
        $this->assertSame(0, $secondResult->data['processedCount']);
        $this->assertSame(0, $secondResult->data['synchronizedCount']);
        $this->assertSame(0, $secondResult->data['advancedCount']);
        $this->assertSame(
            $bestowalIds,
            $this->getTableLocator()->get('Awards.Bestowals')->find()
                ->select(['id'])
                ->where(['source_approval_run_id IN' => array_column($fixtures, 'runId')])
                ->orderByAsc('id')
                ->all()
                ->extract('id')
                ->map(static fn($id): int => (int)$id)
                ->toList(),
            'A repeated sync must not create replacement or duplicate bestowals.',
        );
    }

    public function testSyncRefusesPendingGateWhoseStableStepKeyDoesNotMatchRun(): void
    {
        $scenario = $this->createSubmittedSyncScenario();
        $fixture = $this->startPartiallyApprovedSubmittedWorkflow(
            $scenario['engine'],
            (int)$scenario['award']->id,
        );
        $approvals = $this->getTableLocator()->get('WorkflowApprovals');
        $approval = $approvals->get($fixture['approvalId']);
        $config = $approval->approver_config;
        $config['award_approval_step_key'] = 'legacy-local';
        $approval->approver_config = $config;
        $approvals->saveOrFail($approval);
        $this->changeProcessThresholdToAny((int)$scenario['process']->id);

        $result = $this->createWorkflowSyncService($scenario['engine'])
            ->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(
            'One or more recommendation approval workflows could not be synchronized.',
            $result->getError(),
        );
        $this->assertSame(1, $result->data['processedCount']);
        $this->assertSame(1, $result->data['activeRunFailedCount']);
        $this->assertSame(1, $result->data['failedCount']);
        $this->assertSame(0, $result->data['synchronizedCount']);
        $this->assertSame($fixture['runId'], $result->data['failures'][0]['runId']);
        $this->assertSame(
            'Active recommendation approval workflow synchronization failed. Review server logs for details.',
            $result->data['failures'][0]['reason'],
        );
        $this->assertStringNotContainsString('legacy-local', json_encode($result->data, JSON_THROW_ON_ERROR));

        $approval = $approvals->get($fixture['approvalId']);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $approval->status);
        $this->assertSame(2, (int)$approval->required_count);
        $this->assertSame(1, (int)$approval->approved_count);
        $this->assertSame('legacy-local', $approval->approver_config['award_approval_step_key']);
        $this->assertSame(
            ApprovalProcessStep::THRESHOLD_COUNT,
            $approval->approver_config['award_approval_threshold_mode'],
        );
        $this->assertArrayNotHasKey('award_workflow_sync', $approval->approver_config);
        $this->assertSame(
            $fixture['responseId'],
            (int)$this->getTableLocator()->get('WorkflowApprovalResponses')
                ->get($fixture['responseId'])->id,
        );

        $run = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get($fixture['runId']);
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $run->status);
        $this->assertSame('crown', $run->current_step_key);
        $instance = $this->getTableLocator()->get('WorkflowInstances')->get($fixture['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $instance->status);
        $this->assertSame(
            0,
            $this->getTableLocator()->get('Awards.Bestowals')->find()
                ->where(['source_approval_run_id' => $fixture['runId']])
                ->count(),
            'An unsafe legacy gate must not be retagged, resumed, or converted into a bestowal.',
        );
    }

    public function testSyncRollsBackFailedRunAndContinuesWithHealthyRun(): void
    {
        $scenario = $this->createSubmittedSyncScenario();
        $failed = $this->startPartiallyApprovedSubmittedWorkflow(
            $scenario['engine'],
            (int)$scenario['award']->id,
        );
        $healthy = $this->startPartiallyApprovedSubmittedWorkflow(
            $scenario['engine'],
            (int)$scenario['award']->id,
        );
        $this->changeProcessThresholdToAny((int)$scenario['process']->id);

        $realEngine = $scenario['engine'];
        $selectiveEngine = $this->createMock(WorkflowEngineInterface::class);
        $selectiveEngine->expects($this->exactly(2))
            ->method('resumeWorkflow')
            ->willReturnCallback(
                static function (
                    int $instanceId,
                    string $nodeId,
                    string $outputPort,
                    array $additionalData,
                ) use (
                    $failed,
                    $realEngine,
                ): ServiceResult {
                    if ($instanceId === $failed['instanceId']) {
                        return new ServiceResult(
                            false,
                            "Synthetic private resume failure for instance {$instanceId}.",
                        );
                    }

                    return $realEngine->resumeWorkflow($instanceId, $nodeId, $outputPort, $additionalData);
                },
            );

        $result = $this->createWorkflowSyncService($selectiveEngine)
            ->syncOpenRecommendations(self::ADMIN_MEMBER_ID);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(2, $result->data['processedCount']);
        $this->assertSame(1, $result->data['activeRunFailedCount']);
        $this->assertSame(1, $result->data['failedCount']);
        $this->assertSame(1, $result->data['synchronizedCount']);
        $this->assertSame(1, $result->data['advancedCount']);
        $this->assertSame($failed['runId'], $result->data['failures'][0]['runId']);
        $this->assertSame(
            'Active recommendation approval workflow synchronization failed. Review server logs for details.',
            $result->data['failures'][0]['reason'],
        );
        $this->assertStringNotContainsString(
            'Synthetic private resume failure',
            json_encode($result->data, JSON_THROW_ON_ERROR),
        );

        $approvals = $this->getTableLocator()->get('WorkflowApprovals');
        $failedApproval = $approvals->get($failed['approvalId']);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $failedApproval->status);
        $this->assertSame(2, (int)$failedApproval->required_count);
        $this->assertSame(1, (int)$failedApproval->approved_count);
        $this->assertSame(
            ApprovalProcessStep::THRESHOLD_COUNT,
            $failedApproval->approver_config['award_approval_threshold_mode'],
        );
        $this->assertArrayNotHasKey('award_workflow_sync', $failedApproval->approver_config);
        $failedRun = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get($failed['runId']);
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $failedRun->status);
        $failedInstance = $this->getTableLocator()->get('WorkflowInstances')->get($failed['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $failedInstance->status);
        $this->assertSame(
            0,
            $this->getTableLocator()->get('Awards.Bestowals')->find()
                ->where(['source_approval_run_id' => $failed['runId']])
                ->count(),
            'The failed run must roll back all synchronization and handoff writes.',
        );

        $healthyApproval = $approvals->get($healthy['approvalId']);
        $this->assertSame(WorkflowApproval::STATUS_APPROVED, $healthyApproval->status);
        $this->assertSame(1, (int)$healthyApproval->required_count);
        $this->assertSame(1, (int)$healthyApproval->approved_count);
        $healthyRun = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get($healthy['runId']);
        $this->assertSame(RecommendationApprovalRun::STATUS_CONSUMED, $healthyRun->status);
        $healthyInstance = $this->getTableLocator()->get('WorkflowInstances')->get($healthy['instanceId']);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $healthyInstance->status);
        $this->assertSame(
            1,
            $this->getTableLocator()->get('Awards.Bestowals')->find()
                ->where(['source_approval_run_id' => $healthy['runId']])
                ->count(),
            'A later healthy run must still synchronize after an earlier run rolls back.',
        );
    }

    private function createWorkflowSyncService(
        WorkflowEngineInterface $engine,
    ): RecommendationApprovalWorkflowSyncService {
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

    /**
     * @return array{engine:\App\Services\WorkflowEngine\DefaultWorkflowEngine,process:\Cake\Datasource\EntityInterface,
     *     award:\Cake\Datasource\EntityInterface}
     */
    private function createSubmittedSyncScenario(): array
    {
        $this->registerWorkflowRuntime();
        $this->publishWorkflow(
            'awards-recommendation-submitted',
            'Award Recommendation Submitted',
            'Awards.RecommendationCreateRequested',
            'Awards',
        );
        $role = $this->createRole();
        $this->createMemberRole(self::ADMIN_MEMBER_ID, (int)$role->id);
        $this->createMemberRole(self::TEST_MEMBER_BRYCE_ID, (int)$role->id);
        $process = $this->createApprovalProcess((int)$role->id);
        $award = $this->createAward((int)$process->id);

        return [
            'engine' => new DefaultWorkflowEngine($this->buildWorkflowContainer()),
            'process' => $process,
            'award' => $award,
        ];
    }

    /**
     * @return array{instanceId:int,runId:int,recommendationId:int,approvalId:int,responseId:int}
     */
    private function startPartiallyApprovedSubmittedWorkflow(DefaultWorkflowEngine $engine, int $awardId): array
    {
        $started = $this->startSubmittedWorkflow($engine, $awardId);
        $this->assertTrue($started->isSuccess(), $started->getError() ?? 'Submitted workflow did not start.');
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
        $this->assertTrue($decision->isSuccess(), $decision->getError() ?? 'Partial approval failed.');
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $decision->data['approvalStatus']);
        $response = $this->getTableLocator()->get('WorkflowApprovalResponses')->find()
            ->where([
                'workflow_approval_id' => (int)$approval->id,
                'member_id' => self::ADMIN_MEMBER_ID,
            ])
            ->firstOrFail();

        return [
            'instanceId' => $instanceId,
            'runId' => (int)$run->id,
            'recommendationId' => (int)$run->recommendation_id,
            'approvalId' => (int)$approval->id,
            'responseId' => (int)$response->id,
        ];
    }

    private function changeProcessThresholdToAny(int $processId): void
    {
        $steps = $this->getTableLocator()->get('Awards.ApprovalProcessSteps');
        $step = $steps->find()
            ->where([
                'approval_process_id' => $processId,
                'step_key' => 'crown',
            ])
            ->firstOrFail();
        $step->threshold_mode = ApprovalProcessStep::THRESHOLD_ANY;
        $step->required_count = null;
        $steps->saveOrFail($step);
    }

    private function createApprovalProcess(int $roleId): EntityInterface
    {
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses');

        return $processes->saveOrFail($processes->newEntity([
            'name' => 'Production-shaped Workflow Sync ' . uniqid('', true),
            'is_active' => true,
            'approval_process_steps' => [[
                'step_key' => 'crown',
                'label' => 'Crown approval',
                'sequence' => 1,
                'step_type' => ApprovalProcessStep::STEP_TYPE_APPROVAL,
                'approver_type' => ApprovalProcessStep::APPROVER_TYPE_ROLE,
                'approver_source_id' => $roleId,
                'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
                'threshold_mode' => ApprovalProcessStep::THRESHOLD_COUNT,
                'required_count' => 2,
                'on_reject' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
                'on_request_changes' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
                'retain_read_visibility' => true,
            ]],
        ], ['associated' => ['ApprovalProcessSteps']]));
    }

    private function createRole(): EntityInterface
    {
        $roles = $this->getTableLocator()->get('Roles');

        return $roles->saveOrFail($roles->newEntity([
            'name' => 'Workflow Sync Crown Pool ' . uniqid('', true),
        ]));
    }

    private function createMemberRole(int $memberId, int $roleId): void
    {
        $memberRoles = $this->getTableLocator()->get('MemberRoles');
        $memberRoles->saveOrFail($memberRoles->newEntity([
            'member_id' => $memberId,
            'role_id' => $roleId,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'approver_id' => self::ADMIN_MEMBER_ID,
            'start_on' => DateTime::now()->modify('-1 day'),
            'expires_on' => DateTime::now()->modify('+30 days'),
        ]));
    }

    private function createAward(int $processId): EntityInterface
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');

        return $awards->saveOrFail($awards->newEntity([
            'name' => 'Workflow Sync Award ' . uniqid('', true),
            'abbreviation' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'domain_id' => $this->seededAwardForeignKey('Awards.Domains'),
            'level_id' => $this->seededAwardForeignKey('Awards.Levels'),
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
            'reason' => 'Testing migrated recommendation workflow synchronization',
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]));
    }

    private function seededAwardForeignKey(string $tableAlias): int
    {
        $record = $this->getTableLocator()->get($tableAlias)->find()
            ->select(['id'])
            ->orderBy(['id' => 'ASC'])
            ->firstOrFail();

        return (int)$record->id;
    }

    private function startSubmittedWorkflow(DefaultWorkflowEngine $engine, int $awardId): ServiceResult
    {
        return $engine->startWorkflow('awards-recommendation-submitted', [
            'data' => [
                'requester_id' => self::ADMIN_MEMBER_ID,
                'member_id' => self::ADMIN_MEMBER_ID,
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'award_id' => $awardId,
                'requester_sca_name' => 'Admin von Admin',
                'member_sca_name' => 'Admin von Admin',
                'contact_email' => 'admin@amp.ansteorra.org',
                'contact_number' => '555-555-0100',
                'reason' => 'Testing submitted recommendation workflow synchronization',
                'call_into_court' => 'No',
                'court_availability' => 'Anytime',
            ],
            'requesterContext' => ['member_id' => self::ADMIN_MEMBER_ID],
            'submissionMode' => 'internal',
            'actorId' => self::ADMIN_MEMBER_ID,
            'branchId' => self::KINGDOM_BRANCH_ID,
        ], self::ADMIN_MEMBER_ID);
    }

    private function publishWorkflow(string $slug, string $name, string $event, string $entityType): void
    {
        $definition = json_decode(
            (string)file_get_contents(CONFIG . "Seeds/WorkflowDefinitions/{$slug}.json"),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $definitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $versions = $this->getTableLocator()->get('WorkflowVersions');
        $workflow = $definitions->find()->where(['slug' => $slug])->first();
        if ($workflow === null) {
            $workflow = $definitions->newEntity([
                'name' => $name,
                'slug' => $slug,
                'trigger_type' => 'event',
            ]);
        }
        $workflow->trigger_config = ['event' => $event];
        $workflow->entity_type = $entityType;
        $workflow->execution_mode = 'durable';
        $workflow->is_active = true;
        $workflow = $definitions->saveOrFail($workflow);
        $version = $versions->saveOrFail($versions->newEntity([
            'workflow_definition_id' => (int)$workflow->id,
            'version_number' => (int)$versions->find()
                ->where(['workflow_definition_id' => (int)$workflow->id])
                ->count() + 1,
            'definition' => $definition,
            'status' => 'published',
        ]));
        $workflow->current_version_id = (int)$version->id;
        $definitions->saveOrFail($workflow);
    }

    private function registerWorkflowRuntime(): void
    {
        AwardsWorkflowProvider::register();
        WorkflowConditionRegistry::register('Core', [[
            'condition' => 'Core.FieldEquals',
            'label' => 'Field Equals Value',
            'description' => 'Check if a context field equals a specific value',
            'evaluatorClass' => CoreConditions::class,
            'evaluatorMethod' => 'fieldEquals',
            'inputSchema' => [],
        ]]);
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
