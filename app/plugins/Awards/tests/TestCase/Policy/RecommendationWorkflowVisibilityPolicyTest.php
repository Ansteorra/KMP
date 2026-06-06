<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Policy;

use App\Model\Entity\Member;
use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowExecutionLog;
use App\Model\Entity\WorkflowInstance;
use App\Model\Entity\WorkflowVersion;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Policy\RecommendationPolicy;
use Awards\Policy\RecommendationsTablePolicy;
use Cake\I18n\DateTime;

class RecommendationWorkflowVisibilityPolicyTest extends BaseTestCase
{
    private const WORKFLOW_APPROVER_ID = self::TEST_MEMBER_AGATHA_ID;
    private const FUTURE_APPROVER_ID = self::TEST_MEMBER_BRYCE_ID;

    public function testCurrentWorkflowApproverCanViewRecommendation(): void
    {
        [$recommendation, $instanceId] = $this->createRecommendationWithApprovalRun();
        $this->createWorkflowApproval($instanceId, WorkflowApproval::STATUS_PENDING, [
            'member_id' => self::WORKFLOW_APPROVER_ID,
        ]);

        $policy = new RecommendationPolicy();

        $this->assertTrue($policy->canView($this->syntheticMember(self::WORKFLOW_APPROVER_ID), $recommendation));
        $this->assertFalse($policy->canView($this->syntheticMember(self::FUTURE_APPROVER_ID), $recommendation));
    }

    public function testRetainedPriorApproverCanViewRecommendationReadOnly(): void
    {
        [$recommendation, $instanceId] = $this->createRecommendationWithApprovalRun();
        $approvalId = $this->createWorkflowApproval($instanceId, WorkflowApproval::STATUS_APPROVED, [
            'member_id' => self::WORKFLOW_APPROVER_ID,
            'retain_read_visibility' => true,
        ]);
        $this->createWorkflowApprovalResponse($approvalId, self::WORKFLOW_APPROVER_ID);

        $policy = new RecommendationPolicy();
        $approver = $this->syntheticMember(self::WORKFLOW_APPROVER_ID);

        $this->assertTrue($policy->canView($approver, $recommendation));
        $this->assertFalse($policy->canView($this->syntheticMember(self::FUTURE_APPROVER_ID), $recommendation));
        $this->assertFalse($policy->canEdit($approver, $recommendation));
    }

    public function testRecommendationScopeIncludesWorkflowVisibleRecommendation(): void
    {
        [$visibleRecommendation, $instanceId] = $this->createRecommendationWithApprovalRun();
        [$hiddenRecommendation] = $this->createRecommendationWithApprovalRun();
        $this->createWorkflowApproval($instanceId, WorkflowApproval::STATUS_PENDING, [
            'member_id' => self::WORKFLOW_APPROVER_ID,
        ]);

        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $query = $recommendations->find()
            ->select(['id'])
            ->where([
                'Recommendations.id IN' => [
                    $visibleRecommendation->id,
                    $hiddenRecommendation->id,
                ],
            ]);

        $policy = new RecommendationsTablePolicy();
        $ids = $policy->scopeIndex($this->syntheticMember(self::WORKFLOW_APPROVER_ID), $query)
            ->all()
            ->extract('id')
            ->toList();

        $this->assertContains((int)$visibleRecommendation->id, array_map('intval', $ids));
        $this->assertNotContains((int)$hiddenRecommendation->id, array_map('intval', $ids));
    }

    public function testRecommendationScopeIncludesDynamicRoleScopedWorkflowVisibleRecommendation(): void
    {
        $scope = $this->findCurrentBranchRoleScope();
        [$visibleRecommendation, $instanceId, , $runId] = $this->createRecommendationWithApprovalRun($scope['branch_id']);
        [$hiddenRecommendation] = $this->createRecommendationWithApprovalRun($scope['branch_id']);
        $this->createWorkflowApproval($instanceId, WorkflowApproval::STATUS_PENDING, [
            'service' => 'Awards.ResolveApprovalStepApprovers',
            'method' => 'resolveConfiguredApproverIds',
            'award_approval_run_id' => $runId,
            'award_approval_approver_type' => 'role',
            'award_approval_approver_source_id' => $scope['role_id'],
            'award_approval_branch_mode' => 'award_branch',
        ], WorkflowApproval::APPROVER_TYPE_DYNAMIC);

        $this->assertWorkflowVisibleScopeForMember($scope['member_id'], $visibleRecommendation, $hiddenRecommendation);
    }

    public function testRecommendationScopeIncludesDynamicPermissionScopedWorkflowVisibleRecommendation(): void
    {
        $scope = $this->findCurrentBranchPermissionScope();
        [$visibleRecommendation, $instanceId, , $runId] = $this->createRecommendationWithApprovalRun($scope['branch_id']);
        [$hiddenRecommendation] = $this->createRecommendationWithApprovalRun($scope['branch_id']);
        $this->createWorkflowApproval($instanceId, WorkflowApproval::STATUS_PENDING, [
            'service' => 'Awards.ResolveApprovalStepApprovers',
            'method' => 'resolveConfiguredApproverIds',
            'award_approval_run_id' => $runId,
            'award_approval_approver_type' => 'permission',
            'award_approval_approver_source_id' => $scope['permission_id'],
            'award_approval_branch_mode' => 'award_branch',
        ], WorkflowApproval::APPROVER_TYPE_DYNAMIC);

        $this->assertWorkflowVisibleScopeForMember($scope['member_id'], $visibleRecommendation, $hiddenRecommendation);
    }

    public function testRecommendationScopeIncludesDynamicOfficeScopedWorkflowVisibleRecommendation(): void
    {
        $scope = $this->findCurrentBranchOfficeScope();
        [$visibleRecommendation, $instanceId, , $runId] = $this->createRecommendationWithApprovalRun($scope['branch_id']);
        [$hiddenRecommendation] = $this->createRecommendationWithApprovalRun($scope['branch_id']);
        $this->createWorkflowApproval($instanceId, WorkflowApproval::STATUS_PENDING, [
            'service' => 'Awards.ResolveApprovalStepApprovers',
            'method' => 'resolveConfiguredApproverIds',
            'award_approval_run_id' => $runId,
            'award_approval_approver_type' => 'office',
            'award_approval_approver_source_id' => $scope['office_id'],
            'award_approval_branch_mode' => 'ancestor_branch_type',
            'award_approval_branch_type' => $this->branchType($scope['branch_id']),
        ], WorkflowApproval::APPROVER_TYPE_DYNAMIC);

        $this->assertWorkflowVisibleScopeForMember($scope['member_id'], $visibleRecommendation, $hiddenRecommendation);
    }

    /**
     * @return array{0: \Awards\Model\Entity\Recommendation, 1: int, 2: int, 3: int}
     */
    private function createRecommendationWithApprovalRun(?int $branchId = null): array
    {
        $process = $this->createApprovalProcess();
        $award = $this->createAward((int)$process->id, $branchId);
        $recommendation = $this->createRecommendation((int)$award->id, $branchId);
        $instanceId = $this->createWorkflowInstance();

        $runs = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $run = $runs->saveOrFail($runs->newEntity([
            'recommendation_id' => $recommendation->id,
            'approval_process_id' => $process->id,
            'workflow_instance_id' => $instanceId,
            'status' => RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'current_step_key' => 'approval',
            'current_step_label' => 'Approval',
            'started' => DateTime::now(),
        ]));

        return [$recommendation, $instanceId, (int)$process->id, (int)$run->id];
    }

    private function createApprovalProcess()
    {
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses');

        return $processes->saveOrFail($processes->newEntity([
            'name' => 'Policy Visibility Process ' . uniqid('', true),
            'is_active' => true,
            'approval_process_steps' => [[
                'step_key' => 'approval',
                'label' => 'Approval',
                'sequence' => 1,
                'step_type' => ApprovalProcessStep::STEP_TYPE_APPROVAL,
                'approver_type' => ApprovalProcessStep::APPROVER_TYPE_MEMBER,
                'approver_source_id' => self::ADMIN_MEMBER_ID,
                'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
                'threshold_mode' => ApprovalProcessStep::THRESHOLD_ANY,
                'on_reject' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
                'on_request_changes' => ApprovalProcessStep::ACTION_RETURN_PREVIOUS,
                'retain_read_visibility' => true,
            ]],
        ], ['associated' => ['ApprovalProcessSteps']]));
    }

    private function createAward(int $processId, ?int $branchId = null)
    {
        $awards = $this->getTableLocator()->get('Awards.Awards');

        return $awards->saveOrFail($awards->newEntity([
            'name' => 'Policy Visibility Award ' . uniqid('', true),
            'abbreviation' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => $branchId ?? self::KINGDOM_BRANCH_ID,
            'approval_process_id' => $processId,
            'is_active' => true,
        ]));
    }

    private function createRecommendation(int $awardId, ?int $branchId = null): Recommendation
    {
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');

        return $recommendations->saveOrFail($recommendations->newEntity([
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => $branchId ?? self::KINGDOM_BRANCH_ID,
            'award_id' => $awardId,
            'status' => 'In Progress',
            'state' => 'Submitted',
            'state_date' => DateTime::now(),
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@amp.ansteorra.org',
            'contact_number' => '555-555-0100',
            'reason' => 'Testing approval visibility',
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
            'name' => 'Policy Visibility Workflow ' . uniqid('', true),
            'slug' => 'policy-visibility-workflow-' . uniqid(),
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
            'status' => WorkflowVersion::STATUS_PUBLISHED,
        ]));

        $definition->current_version_id = $version->id;
        $definitions->saveOrFail($definition);

        $instance = $instances->saveOrFail($instances->newEntity([
            'workflow_definition_id' => $definition->id,
            'workflow_version_id' => $version->id,
            'status' => WorkflowInstance::STATUS_WAITING,
        ]));

        return (int)$instance->id;
    }

    private function createWorkflowApproval(
        int $instanceId,
        string $status,
        array $approverConfig,
        string $approverType = WorkflowApproval::APPROVER_TYPE_MEMBER,
    ): int {
        $logs = $this->getTableLocator()->get('WorkflowExecutionLogs');
        $log = $logs->saveOrFail($logs->newEntity([
            'workflow_instance_id' => $instanceId,
            'node_id' => 'approval',
            'node_type' => 'approval',
            'attempt_number' => 1,
            'status' => WorkflowExecutionLog::STATUS_WAITING,
        ]));

        $approvals = $this->getTableLocator()->get('WorkflowApprovals');
        $approval = $approvals->saveOrFail($approvals->newEntity([
            'workflow_instance_id' => $instanceId,
            'node_id' => 'approval',
            'execution_log_id' => $log->id,
            'approver_type' => $approverType,
            'approver_config' => $approverConfig,
            'required_count' => 1,
            'approved_count' => $status === WorkflowApproval::STATUS_APPROVED ? 1 : 0,
            'rejected_count' => 0,
            'status' => $status,
            'allow_parallel' => false,
            'version' => 1,
        ]));

        return (int)$approval->id;
    }

    private function assertWorkflowVisibleScopeForMember(
        int $memberId,
        Recommendation $visibleRecommendation,
        Recommendation $hiddenRecommendation,
    ): void {
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $query = $recommendations->find()
            ->select(['id'])
            ->where([
                'Recommendations.id IN' => [
                    $visibleRecommendation->id,
                    $hiddenRecommendation->id,
                ],
            ]);

        $tablePolicy = new RecommendationsTablePolicy();
        $ids = $tablePolicy->scopeIndex($this->syntheticMember($memberId), $query)
            ->all()
            ->extract('id')
            ->map(static fn($id): int => (int)$id)
            ->toList();

        $entityPolicy = new RecommendationPolicy();
        $member = $this->syntheticMember($memberId);

        $this->assertContains((int)$visibleRecommendation->id, $ids);
        $this->assertNotContains((int)$hiddenRecommendation->id, $ids);
        $this->assertTrue($entityPolicy->canView($member, $visibleRecommendation));
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

    private function syntheticMember(int $id): Member
    {
        $member = new Member();
        $member->id = $id;
        $member->sca_name = 'Workflow Approver ' . $id;
        $member->status = Member::STATUS_ACTIVE;

        return $member;
    }

    /**
     * @return array{member_id:int,role_id:int,branch_id:int}
     */
    private function findCurrentBranchRoleScope(): array
    {
        $memberRole = $this->getTableLocator()->get('MemberRoles')->find('current')
            ->select(['member_id', 'role_id', 'branch_id'])
            ->where(['MemberRoles.branch_id IS NOT' => null])
            ->firstOrFail();

        return [
            'member_id' => (int)$memberRole->member_id,
            'role_id' => (int)$memberRole->role_id,
            'branch_id' => (int)$memberRole->branch_id,
        ];
    }

    /**
     * @return array{member_id:int,permission_id:int,branch_id:int}
     */
    private function findCurrentBranchPermissionScope(): array
    {
        $memberRole = $this->getTableLocator()->get('MemberRoles')->find('current')
            ->select([
                'member_id' => 'MemberRoles.member_id',
                'branch_id' => 'MemberRoles.branch_id',
                'permission_id' => 'Permissions.id',
            ])
            ->matching('Roles.Permissions')
            ->where(['MemberRoles.branch_id IS NOT' => null])
            ->enableHydration(false)
            ->firstOrFail();

        return [
            'member_id' => (int)$memberRole['member_id'],
            'permission_id' => (int)$memberRole['permission_id'],
            'branch_id' => (int)$memberRole['branch_id'],
        ];
    }

    /**
     * @return array{member_id:int,office_id:int,branch_id:int}
     */
    private function findCurrentBranchOfficeScope(): array
    {
        $officer = $this->getTableLocator()->get('Officers.Officers')->find()
            ->select(['member_id', 'office_id', 'branch_id'])
            ->where([
                'Officers.status' => 'Current',
                'Officers.branch_id IS NOT' => null,
                'Officers.member_id IS NOT' => null,
            ])
            ->firstOrFail();

        return [
            'member_id' => (int)$officer->member_id,
            'office_id' => (int)$officer->office_id,
            'branch_id' => (int)$officer->branch_id,
        ];
    }

    private function branchType(int $branchId): string
    {
        $branch = $this->getTableLocator()->get('Branches')->get($branchId);

        return (string)$branch->type;
    }
}
