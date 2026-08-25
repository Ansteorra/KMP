<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use App\Services\ServiceResult;
use App\Services\WorkflowEngine\Conditions\CoreConditions;
use App\Services\WorkflowEngine\DefaultWorkflowApprovalManager;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
use App\Services\WorkflowEngine\TriggerDispatcher;
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
use Awards\Services\RecommendationApprovalProcessService;
use Awards\Services\RecommendationApprovalWorkflowSyncService;
use Awards\Services\RecommendationGroupingService;
use Cake\Core\ContainerInterface;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventManager;
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

    public function testSyncCancelsInflightProgressAndStartsFreshCurrentWorkflows(): void
    {
        $scenario = $this->createSubmittedSyncScenario();
        $submitted = $this->startPartiallyApprovedSubmittedWorkflow(
            $scenario['engine'],
            (int)$scenario['award']->id,
        );
        $recommendation = $this->createRecommendation((int)$scenario['award']->id);
        $migratedStart = $scenario['engine']->startWorkflow('awards-existing-recommendation-approval', [
            'recommendationId' => (int)$recommendation->id,
            'actorId' => self::ADMIN_MEMBER_ID,
        ], self::ADMIN_MEMBER_ID);
        $this->assertTrue($migratedStart->isSuccess(), $migratedStart->getError() ?? 'Existing workflow failed.');
        $migrated = $this->partiallyApproveInstance((int)$migratedStart->data['instanceId']);

        $oldApproval = $this->getTableLocator()->get('WorkflowApprovals')->get($migrated['approvalId']);
        $oldConfig = $oldApproval->approver_config;
        $oldConfig['award_approval_step_key'] = 'obsolete-local-key';
        $oldApproval->approver_config = $oldConfig;
        $this->getTableLocator()->get('WorkflowApprovals')->saveOrFail($oldApproval);
        $this->changeProcessThresholdToAny((int)$scenario['process']->id);

        $otherProcess = $this->createApprovalProcess(
            (int)$scenario['process']->approval_process_steps[0]->approver_source_id,
        );
        $otherAward = $this->createAward((int)$otherProcess->id);
        $otherRecommendation = $this->createRecommendation((int)$otherAward->id);
        $otherStart = $scenario['engine']->startWorkflow('awards-existing-recommendation-approval', [
            'recommendationId' => (int)$otherRecommendation->id,
            'actorId' => self::ADMIN_MEMBER_ID,
        ], self::ADMIN_MEMBER_ID);
        $this->assertTrue($otherStart->isSuccess(), $otherStart->getError() ?? 'Unrelated workflow failed.');
        $otherRun = $this->activeRunForRecommendation((int)$otherRecommendation->id);

        $connection = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->getConnection();
        $connection->disableSavePoints();
        $service = $this->createWorkflowSyncService($scenario['engine']);
        $this->assertSame(2, $service->countOutdatedRecommendations((int)$scenario['process']->id));
        $this->assertSame(0, $service->countOutdatedRecommendations((int)$otherProcess->id));
        $result = $service->syncApprovalProcess(
            (int)$scenario['process']->id,
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($result->isSuccess(), json_encode($result->data['failures'] ?? []));
        $this->assertFalse($connection->isSavePointsEnabled());
        $this->assertSame(2, $result->data['processedCount']);
        $this->assertSame(2, $result->data['restartedCount']);
        $this->assertSame(2, $result->data['cancelledRunCount']);
        $this->assertSame(0, $result->data['failedCount']);
        $currentProcessSignature = $this->getTableLocator()->get('Awards.ApprovalProcesses')
            ->get((int)$scenario['process']->id, contain: ['ApprovalProcessSteps'])
            ->configuration_signature;

        $firstReplacementRunIds = [];
        foreach (['submitted' => $submitted, 'migrated' => $migrated] as $origin => $fixture) {
            $oldRun = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get($fixture['runId']);
            $this->assertSame(RecommendationApprovalRun::STATUS_CANCELLED, $oldRun->status, $origin);
            $this->assertSame(
                RecommendationApprovalRun::TERMINAL_REASON_PROCESS_RESTARTED,
                $oldRun->terminal_reason,
                $origin,
            );
            $this->assertNotNull($oldRun->completed, $origin);

            $oldInstance = $this->getTableLocator()->get('WorkflowInstances')->get($fixture['instanceId']);
            $this->assertSame(WorkflowInstance::STATUS_CANCELLED, $oldInstance->status, $origin);
            $this->assertSame(
                RecommendationApprovalRun::TERMINAL_REASON_PROCESS_RESTARTED,
                $oldInstance->error_info['cancellation_reason'] ?? null,
                $origin,
            );
            $oldApproval = $this->getTableLocator()->get('WorkflowApprovals')->get($fixture['approvalId']);
            $this->assertSame(WorkflowApproval::STATUS_CANCELLED, $oldApproval->status, $origin);
            $this->assertSame(1, (int)$oldApproval->approved_count, $origin);
            $this->assertSame(
                1,
                $this->getTableLocator()->get('WorkflowApprovalResponses')->find()
                    ->where(['workflow_approval_id' => $fixture['approvalId']])
                    ->count(),
                "{$origin} response history must remain attached to the cancelled gate.",
            );
            $this->assertSame(
                $fixture['responseId'],
                (int)$this->getTableLocator()->get('WorkflowApprovalResponses')->get($fixture['responseId'])->id,
                $origin,
            );

            $replacementRun = $this->activeRunForRecommendation($fixture['recommendationId']);
            $firstReplacementRunIds[$origin] = (int)$replacementRun->id;
            $this->assertNotSame($fixture['runId'], (int)$replacementRun->id, $origin);
            $this->assertSame((int)$scenario['process']->id, (int)$replacementRun->approval_process_id, $origin);
            $this->assertSame(
                $currentProcessSignature,
                $replacementRun->approval_process_signature,
                $origin,
            );
            $this->assertSame('crown', $replacementRun->current_step_key, $origin);
            $replacementApproval = $this->pendingApprovalForInstance((int)$replacementRun->workflow_instance_id);
            $this->assertSame(1, (int)$replacementApproval->required_count, $origin);
            $this->assertSame(0, (int)$replacementApproval->approved_count, $origin);
            $this->assertSame(
                0,
                $this->getTableLocator()->get('WorkflowApprovalResponses')->find()
                    ->where(['workflow_approval_id' => (int)$replacementApproval->id])
                    ->count(),
                "{$origin} must restart without copied responses.",
            );
            $freshRecommendation = $this->getTableLocator()->get('Awards.Recommendations')
                ->get($fixture['recommendationId']);
            $this->assertNull($freshRecommendation->bestowal_id, $origin);
        }
        $unchangedOtherRun = $this->activeRunForRecommendation((int)$otherRecommendation->id);
        $this->assertSame((int)$otherRun->id, (int)$unchangedOtherRun->id);
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $unchangedOtherRun->status);
        $this->assertSame(0, $this->getTableLocator()->get('Awards.Bestowals')->find()->count());

        $connection->enableSavePoints();
        $this->assertSame(0, $service->countOutdatedRecommendations((int)$scenario['process']->id));
        $secondResult = $service->syncApprovalProcess(
            (int)$scenario['process']->id,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($secondResult->isSuccess(), $secondResult->getError() ?? 'Second restart failed.');
        $this->assertTrue($connection->isSavePointsEnabled());
        $this->assertSame(0, $secondResult->data['candidateCount']);
        $this->assertSame(0, $secondResult->data['restartedCount']);
        foreach (['submitted' => $submitted, 'migrated' => $migrated] as $origin => $fixture) {
            $secondReplacement = $this->activeRunForRecommendation($fixture['recommendationId']);
            $this->assertSame($firstReplacementRunIds[$origin], (int)$secondReplacement->id, $origin);
        }
        $this->assertSame(0, $this->getTableLocator()->get('Awards.Bestowals')->find()->count());
    }

    public function testUngroupStartsChildFreshOnCurrentApprovalProcess(): void
    {
        $scenario = $this->createSubmittedSyncScenario();
        $head = $this->createRecommendation((int)$scenario['award']->id);
        $child = $this->createRecommendation((int)$scenario['award']->id);

        $headStart = $scenario['engine']->startWorkflow('awards-existing-recommendation-approval', [
            'recommendationId' => (int)$head->id,
            'actorId' => self::ADMIN_MEMBER_ID,
        ], self::ADMIN_MEMBER_ID);
        $childStart = $scenario['engine']->startWorkflow('awards-existing-recommendation-approval', [
            'recommendationId' => (int)$child->id,
            'actorId' => self::ADMIN_MEMBER_ID,
        ], self::ADMIN_MEMBER_ID);
        $this->assertTrue($headStart->isSuccess(), $headStart->getError() ?? 'Head workflow failed to start.');
        $this->assertTrue($childStart->isSuccess(), $childStart->getError() ?? 'Child workflow failed to start.');

        $headProgress = $this->partiallyApproveInstance((int)$headStart->data['instanceId']);
        $childProgress = $this->partiallyApproveInstance((int)$childStart->data['instanceId']);
        $groupingService = new RecommendationGroupingService();
        $group = $groupingService->groupRecommendations(
            [(int)$head->id, (int)$child->id],
            self::ADMIN_MEMBER_ID,
        );
        $this->assertSame((int)$head->id, (int)$group->id);

        $cancelledChildRun = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')
            ->get($childProgress['runId']);
        $this->assertSame(RecommendationApprovalRun::STATUS_CANCELLED, $cancelledChildRun->status);
        $this->assertSame(
            RecommendationApprovalRun::TERMINAL_REASON_SUPERSEDED_BY_GROUPING,
            $cancelledChildRun->terminal_reason,
        );

        $oldStep = $this->getTableLocator()->get('Awards.ApprovalProcessSteps')->find()
            ->where(['approval_process_id' => (int)$scenario['process']->id])
            ->firstOrFail();
        $currentProcess = $this->createApprovalProcess((int)$oldStep->approver_source_id);
        $this->changeProcessThresholdToAny((int)$currentProcess->id);
        $award = $this->getTableLocator()->get('Awards.Awards')->get((int)$scenario['award']->id);
        $award->approval_process_id = (int)$currentProcess->id;
        $this->getTableLocator()->get('Awards.Awards')->saveOrFail($award);

        $syncResult = $this->createWorkflowSyncService($scenario['engine'])
            ->syncApprovalProcess((int)$currentProcess->id, self::ADMIN_MEMBER_ID);
        $this->assertTrue($syncResult->isSuccess(), $syncResult->getError() ?? 'Workflow sync failed.');
        $this->assertSame(1, $syncResult->data['restartedCount']);
        $syncedHeadRun = $this->activeRunForRecommendation((int)$head->id);
        $this->assertSame((int)$currentProcess->id, (int)$syncedHeadRun->approval_process_id);
        $this->assertNotSame($headProgress['runId'], (int)$syncedHeadRun->id);

        $dispatcher = new TriggerDispatcher($scenario['engine']);
        $dispatcher->attachToEventManager();
        try {
            $groupingService->ungroupRecommendations((int)$head->id, self::ADMIN_MEMBER_ID);
        } finally {
            EventManager::instance()->off($dispatcher);
        }

        $replacementRun = $this->activeRunForRecommendation((int)$child->id);
        $this->assertNotSame($childProgress['runId'], (int)$replacementRun->id);
        $this->assertSame((int)$currentProcess->id, (int)$replacementRun->approval_process_id);
        $this->assertSame((int)$cancelledChildRun->id, (int)$replacementRun->rehydrated_from_run_id);
        $replacementApproval = $this->pendingApprovalForInstance((int)$replacementRun->workflow_instance_id);
        $this->assertSame(1, (int)$replacementApproval->required_count);
        $this->assertSame(0, (int)$replacementApproval->approved_count);
        $this->assertSame(
            0,
            $this->getTableLocator()->get('WorkflowApprovalResponses')->find()
                ->where(['workflow_approval_id' => (int)$replacementApproval->id])
                ->count(),
        );

        $oldApproval = $this->getTableLocator()->get('WorkflowApprovals')->get($childProgress['approvalId']);
        $this->assertSame(WorkflowApproval::STATUS_CANCELLED, $oldApproval->status);
        $this->assertSame(1, (int)$oldApproval->approved_count);
        $this->assertSame(
            1,
            $this->getTableLocator()->get('WorkflowApprovalResponses')->find()
                ->where(['workflow_approval_id' => $childProgress['approvalId']])
                ->count(),
        );
        $restoredChild = $this->getTableLocator()->get('Awards.Recommendations')->get((int)$child->id);
        $this->assertNull($restoredChild->recommendation_group_id);
        $this->assertSame('Submitted', $restoredChild->state);
        $this->assertSame(
            (int)$syncedHeadRun->id,
            (int)$this->activeRunForRecommendation((int)$head->id)->id,
        );
        $this->assertSame(0, $this->getTableLocator()->get('Awards.Bestowals')->find()->count());
    }

    public function testSyncSkipsClosedAndApprovedRecommendations(): void
    {
        $scenario = $this->createSubmittedSyncScenario();
        $closed = $this->startPartiallyApprovedSubmittedWorkflow(
            $scenario['engine'],
            (int)$scenario['award']->id,
        );
        $closedRecommendation = $this->getTableLocator()->get('Awards.Recommendations')
            ->get($closed['recommendationId']);
        $closedRecommendation->status = 'Closed';
        $this->getTableLocator()->get('Awards.Recommendations')->saveOrFail($closedRecommendation);

        $approved = $this->startPartiallyApprovedSubmittedWorkflow(
            $scenario['engine'],
            (int)$scenario['award']->id,
        );
        $approvedRun = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->get($approved['runId']);
        $approvedRun->status = RecommendationApprovalRun::STATUS_APPROVED;
        $approvedRun->completed = DateTime::now();
        $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->saveOrFail($approvedRun);

        $completed = $this->startPartiallyApprovedSubmittedWorkflow(
            $scenario['engine'],
            (int)$scenario['award']->id,
        );
        $completedInstance = $this->getTableLocator()->get('WorkflowInstances')->get($completed['instanceId']);
        $completedInstance->status = WorkflowInstance::STATUS_COMPLETED;
        $completedInstance->completed_at = DateTime::now();
        $this->getTableLocator()->get('WorkflowInstances')->saveOrFail($completedInstance);

        $result = $this->createWorkflowSyncService($scenario['engine'])
            ->syncApprovalProcess((int)$scenario['process']->id, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result->isSuccess(), $result->getError() ?? 'Sync failed.');
        $this->assertSame(0, $result->data['processedCount']);
        $this->assertSame(0, $result->data['restartedCount']);
        $this->assertSame(0, $result->data['activeRunSkippedCount']);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $this->getTableLocator()->get('WorkflowApprovals')
            ->get($closed['approvalId'])->status);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $this->getTableLocator()->get('WorkflowInstances')
            ->get($closed['instanceId'])->status);
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $this->getTableLocator()
            ->get('Awards.RecommendationApprovalRuns')->get($closed['runId'])->status);
        $this->assertSame(RecommendationApprovalRun::STATUS_APPROVED, $this->getTableLocator()
            ->get('Awards.RecommendationApprovalRuns')->get($approved['runId'])->status);
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $this->getTableLocator()
            ->get('Awards.RecommendationApprovalRuns')->get($completed['runId'])->status);
        $this->assertSame(WorkflowInstance::STATUS_COMPLETED, $this->getTableLocator()->get('WorkflowInstances')
            ->get($completed['instanceId'])->status);
    }

    public function testSyncRollsBackFailedRestartAndContinuesWithHealthyRecommendation(): void
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
            ->method('dispatchTrigger')
            ->willReturnCallback(static function (
                string $eventName,
                array $eventData,
                ?int $triggeredBy,
            ) use (
                $failed,
                $realEngine,
            ): array {
                if ((int)$eventData['recommendationId'] === $failed['recommendationId']) {
                    return [new ServiceResult(false, 'Synthetic private restart failure.')];
                }

                return $realEngine->dispatchTrigger($eventName, $eventData, $triggeredBy);
            });

        $result = $this->createWorkflowSyncService($selectiveEngine)
            ->syncApprovalProcess((int)$scenario['process']->id, self::ADMIN_MEMBER_ID);

        $this->assertFalse($result->isSuccess());
        $this->assertSame(2, $result->data['processedCount']);
        $this->assertSame(1, $result->data['restartedCount']);
        $this->assertSame(1, $result->data['activeRunFailedCount']);
        $this->assertSame($failed['recommendationId'], $result->data['failures'][0]['recommendationId']);
        $this->assertStringNotContainsString('Synthetic private', json_encode($result->data));

        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $this->getTableLocator()
            ->get('Awards.RecommendationApprovalRuns')->get($failed['runId'])->status);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $this->getTableLocator()->get('WorkflowInstances')
            ->get($failed['instanceId'])->status);
        $this->assertSame(WorkflowApproval::STATUS_PENDING, $this->getTableLocator()->get('WorkflowApprovals')
            ->get($failed['approvalId'])->status);

        $healthyOldRun = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')
            ->get($healthy['runId']);
        $this->assertSame(RecommendationApprovalRun::STATUS_CANCELLED, $healthyOldRun->status);
        $healthyReplacement = $this->activeRunForRecommendation($healthy['recommendationId']);
        $this->assertNotSame($healthy['runId'], (int)$healthyReplacement->id);
        $this->assertSame(0, $this->getTableLocator()->get('Awards.Bestowals')->find()->count());
    }

    private function createWorkflowSyncService(
        WorkflowEngineInterface $engine,
    ): RecommendationApprovalWorkflowSyncService {
        return new RecommendationApprovalWorkflowSyncService($engine);
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

        return $this->partiallyApproveInstance((int)$started->data['instanceId']);
    }

    /**
     * @return array{instanceId:int,runId:int,recommendationId:int,approvalId:int,responseId:int}
     */
    private function partiallyApproveInstance(int $instanceId): array
    {
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

    private function activeRunForRecommendation(int $recommendationId): RecommendationApprovalRun
    {
        return $this->getTableLocator()->get('Awards.RecommendationApprovalRuns')->find()
            ->where([
                'recommendation_id' => $recommendationId,
                'status IN' => [
                    RecommendationApprovalRun::STATUS_IN_PROGRESS,
                    RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
                ],
            ])
            ->firstOrFail();
    }

    private function pendingApprovalForInstance(int $instanceId): WorkflowApproval
    {
        return $this->getTableLocator()->get('WorkflowApprovals')->find()
            ->where([
                'workflow_instance_id' => $instanceId,
                'status' => WorkflowApproval::STATUS_PENDING,
            ])
            ->firstOrFail();
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
