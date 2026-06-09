<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Services\WorkflowEngine\Conditions\CoreConditions;
use App\Services\WorkflowEngine\DefaultWorkflowApprovalManager;
use App\Services\WorkflowEngine\DefaultWorkflowEngine;
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
        $this->assertSame('Submitted', $this->freshRecommendationState((int)$recommendation->id));
    }

    public function testAdvanceProcessMovesToNextStepAndFinalApprovalCompletesRun(): void
    {
        [$recommendation, $instanceId] = $this->buildApprovalScenario([
            $this->stepData('local', 'Local approval', 1),
            $this->stepData('crown', 'Crown approval', 2),
        ]);
        $this->service->startProcess(['instanceId' => $instanceId], ['recommendationId' => (int)$recommendation->id]);

        $advanced = $this->service->advanceProcess(
            ['instanceId' => $instanceId, 'approval' => ['approvalStatus' => 'approved']],
            [],
        );

        $this->assertTrue($advanced->isSuccess(), $advanced->getError() ?? '');
        $this->assertSame('crown', $advanced->data['currentStepKey']);

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
        $this->assertSame('No Action', $freshRecommendation->state);
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

        $this->assertTrue($result->isSuccess(), $result->getError() ?? '');
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
        $this->assertArrayNotHasKey('eligible_member_ids', $approval->approver_config);
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
        $this->assertSame('Submitted', $this->freshRecommendationState($recommendationId));
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
