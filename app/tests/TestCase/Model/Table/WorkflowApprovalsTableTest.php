<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Model\Table\WorkflowApprovalsTable;
use App\Test\TestCase\BaseTestCase;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use ReflectionMethod;

class WorkflowApprovalsTableTest extends BaseTestCase
{
    private $approvalsTable;
    private $responsesTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->approvalsTable = TableRegistry::getTableLocator()->get('WorkflowApprovals');
        $this->responsesTable = TableRegistry::getTableLocator()->get('WorkflowApprovalResponses');
    }

    public function testPendingCountIncludesDirectMemberApproval(): void
    {
        $countBefore = WorkflowApprovalsTable::getPendingApprovalCountForMember(self::ADMIN_MEMBER_ID);
        [$instanceId, $executionLogId] = $this->createWorkflowContext();

        $approvalId = $this->createApproval($instanceId, $executionLogId, [
            'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approver_config' => ['member_id' => self::ADMIN_MEMBER_ID],
        ]);

        $this->assertSame(
            $countBefore + 1,
            WorkflowApprovalsTable::getPendingApprovalCountForMember(self::ADMIN_MEMBER_ID),
        );
        $this->assertContains(
            $approvalId,
            WorkflowApprovalsTable::getPendingApprovalIdsForMember(self::ADMIN_MEMBER_ID),
        );
        $this->assertContains(
            $instanceId,
            WorkflowApprovalsTable::getPendingApprovalWorkflowInstanceIdsForMember(self::ADMIN_MEMBER_ID),
        );
        $this->assertTrue(
            WorkflowApprovalsTable::isPendingApprovalForMember($approvalId, self::ADMIN_MEMBER_ID),
        );
    }

    public function testPendingCountUsesCurrentBranchScopedRoleForAwardDynamicApproval(): void
    {
        $scope = $this->findCurrentBranchRoleScope();
        $memberScope = $this->getMemberApprovalScope($scope['member_id']);
        $this->assertArrayHasKey($scope['branch_id'], $memberScope['roleIdsByBranch']);
        $this->assertArrayHasKey($scope['role_id'], $memberScope['roleIdsByBranch'][$scope['branch_id']]);
        $countBefore = WorkflowApprovalsTable::getPendingApprovalCountForMember($scope['member_id']);
        [$instanceId, $executionLogId] = $this->createWorkflowContext();

        $approvalId = $this->createApproval($instanceId, $executionLogId, [
            'approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            'approver_config' => [
                'service' => 'Awards.ResolveApprovalStepApprovers',
                'method' => 'resolveConfiguredApproverIds',
                'award_approval_approver_type' => 'role',
                'award_approval_approver_source_id' => $scope['role_id'],
                'award_approval_branch_id' => $scope['branch_id'],
            ],
        ]);

        $this->assertSame(
            $countBefore + 1,
            WorkflowApprovalsTable::getPendingApprovalCountForMember($scope['member_id']),
        );
        $this->assertContains(
            $approvalId,
            WorkflowApprovalsTable::getPendingApprovalIdsForMember($scope['member_id']),
        );
        $this->assertContains(
            $instanceId,
            WorkflowApprovalsTable::getPendingApprovalWorkflowInstanceIdsForMember($scope['member_id']),
        );
    }

    public function testPendingCountUsesAwardRunBranchScopeForRoleApproval(): void
    {
        $scope = $this->findCurrentBranchRoleScope();
        $countBefore = WorkflowApprovalsTable::getPendingApprovalCountForMember($scope['member_id']);
        [$instanceId, $executionLogId, $runId] = $this->createAwardWorkflowContext($scope['branch_id']);

        $approvalId = $this->createApproval($instanceId, $executionLogId, [
            'approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            'approver_config' => [
                'service' => 'Awards.ResolveApprovalStepApprovers',
                'method' => 'resolveConfiguredApproverIds',
                'award_approval_run_id' => $runId,
                'award_approval_approver_type' => 'role',
                'award_approval_approver_source_id' => $scope['role_id'],
                'award_approval_branch_mode' => 'award_branch',
            ],
        ]);

        $this->assertSame(
            $countBefore + 1,
            WorkflowApprovalsTable::getPendingApprovalCountForMember($scope['member_id']),
        );
        $this->assertContains(
            $approvalId,
            WorkflowApprovalsTable::getPendingApprovalIdsForMember($scope['member_id']),
        );
        $this->assertContains(
            $instanceId,
            WorkflowApprovalsTable::getPendingApprovalWorkflowInstanceIdsForMember($scope['member_id']),
        );
    }

    public function testPendingCountUsesAncestorBranchTypeScopeForOfficeApproval(): void
    {
        $scope = $this->findCurrentBranchOfficeScope();
        $branchType = $this->branchType((int)$scope['branch_id']);
        $countBefore = WorkflowApprovalsTable::getPendingApprovalCountForMember($scope['member_id']);
        [$instanceId, $executionLogId, $runId] = $this->createAwardWorkflowContext($scope['branch_id']);

        $approvalId = $this->createApproval($instanceId, $executionLogId, [
            'approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            'approver_config' => [
                'service' => 'Awards.ResolveApprovalStepApprovers',
                'method' => 'resolveConfiguredApproverIds',
                'award_approval_run_id' => $runId,
                'award_approval_approver_type' => 'office',
                'award_approval_approver_source_id' => $scope['office_id'],
                'award_approval_branch_mode' => 'ancestor_branch_type',
                'award_approval_branch_type' => $branchType,
            ],
        ]);

        $this->assertSame(
            $countBefore + 1,
            WorkflowApprovalsTable::getPendingApprovalCountForMember($scope['member_id']),
        );
        $this->assertContains(
            $approvalId,
            WorkflowApprovalsTable::getPendingApprovalIdsForMember($scope['member_id']),
        );
    }

    public function testPendingCountSkipsBranchScopedRoleOutsideMemberScope(): void
    {
        $scope = $this->findCurrentBranchRoleScope();
        $otherBranchId = $this->findBranchOutsideCurrentRoleScope($scope['member_id'], $scope['role_id']);
        $countBefore = WorkflowApprovalsTable::getPendingApprovalCountForMember($scope['member_id']);
        [$instanceId, $executionLogId] = $this->createWorkflowContext();

        $approvalId = $this->createApproval($instanceId, $executionLogId, [
            'approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            'approver_config' => [
                'service' => 'Awards.ResolveApprovalStepApprovers',
                'method' => 'resolveConfiguredApproverIds',
                'award_approval_approver_type' => 'role',
                'award_approval_approver_source_id' => $scope['role_id'],
                'award_approval_branch_id' => $otherBranchId,
            ],
        ]);

        $this->assertSame(
            $countBefore,
            WorkflowApprovalsTable::getPendingApprovalCountForMember($scope['member_id']),
        );
        $this->assertNotContains(
            $approvalId,
            WorkflowApprovalsTable::getPendingApprovalIdsForMember($scope['member_id']),
        );
        $this->assertNotContains(
            $instanceId,
            WorkflowApprovalsTable::getPendingApprovalWorkflowInstanceIdsForMember($scope['member_id']),
        );
    }

    public function testPendingCountUsesCurrentBranchScopedPermissionForAwardDynamicApproval(): void
    {
        $scope = $this->findCurrentBranchPermissionScope();
        $countBefore = WorkflowApprovalsTable::getPendingApprovalCountForMember($scope['member_id']);
        [$instanceId, $executionLogId] = $this->createWorkflowContext();

        $this->createApproval($instanceId, $executionLogId, [
            'approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            'approver_config' => [
                'service' => 'Awards.ResolveApprovalStepApprovers',
                'method' => 'resolveConfiguredApproverIds',
                'award_approval_approver_type' => 'permission',
                'award_approval_approver_source_id' => $scope['permission_id'],
                'award_approval_branch_id' => $scope['branch_id'],
            ],
        ]);

        $this->assertSame(
            $countBefore + 1,
            WorkflowApprovalsTable::getPendingApprovalCountForMember($scope['member_id']),
        );
    }

    public function testPendingCountUsesCurrentBranchScopedOfficeForAwardDynamicApproval(): void
    {
        $scope = $this->findCurrentBranchOfficeScope();
        $countBefore = WorkflowApprovalsTable::getPendingApprovalCountForMember($scope['member_id']);
        [$instanceId, $executionLogId] = $this->createWorkflowContext();

        $this->createApproval($instanceId, $executionLogId, [
            'approver_type' => WorkflowApproval::APPROVER_TYPE_DYNAMIC,
            'approver_config' => [
                'service' => 'Awards.ResolveApprovalStepApprovers',
                'method' => 'resolveConfiguredApproverIds',
                'award_approval_approver_type' => 'office',
                'award_approval_approver_source_id' => $scope['office_id'],
                'award_approval_branch_id' => $scope['branch_id'],
            ],
        ]);

        $this->assertSame(
            $countBefore + 1,
            WorkflowApprovalsTable::getPendingApprovalCountForMember($scope['member_id']),
        );
    }

    public function testPendingCountExcludesRespondedApproval(): void
    {
        $countBefore = WorkflowApprovalsTable::getPendingApprovalCountForMember(self::ADMIN_MEMBER_ID);
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $approvalId = $this->createApproval($instanceId, $executionLogId, [
            'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approver_config' => ['member_id' => self::ADMIN_MEMBER_ID],
        ]);

        $response = $this->responsesTable->newEntity([
            'workflow_approval_id' => $approvalId,
            'member_id' => self::ADMIN_MEMBER_ID,
            'decision' => WorkflowApprovalResponse::DECISION_APPROVE,
            'responded_at' => DateTime::now(),
        ]);
        $this->responsesTable->saveOrFail($response);

        $this->assertSame(
            $countBefore,
            WorkflowApprovalsTable::getPendingApprovalCountForMember(self::ADMIN_MEMBER_ID),
        );
        $this->assertFalse(
            WorkflowApprovalsTable::isPendingApprovalForMember($approvalId, self::ADMIN_MEMBER_ID),
        );
    }

    /**
     * @return array{0:int,1:int}
     */
    private function createWorkflowContext(): array
    {
        $defTable = TableRegistry::getTableLocator()->get('WorkflowDefinitions');
        $def = $defTable->newEntity([
            'name' => 'Workflow Approvals Table Test ' . uniqid(),
            'slug' => 'workflow-approvals-table-test-' . uniqid(),
            'trigger_type' => 'manual',
        ]);
        $defTable->saveOrFail($def);

        $versionsTable = TableRegistry::getTableLocator()->get('WorkflowVersions');
        $version = $versionsTable->newEntity([
            'workflow_definition_id' => $def->id,
            'version_number' => 1,
            'definition' => [
                'nodes' => [
                    'trigger' => ['type' => 'trigger', 'outputs' => [['target' => 'end']]],
                    'end' => ['type' => 'end', 'outputs' => []],
                ],
            ],
            'status' => 'published',
        ]);
        $versionsTable->saveOrFail($version);

        $instancesTable = TableRegistry::getTableLocator()->get('WorkflowInstances');
        $instance = $instancesTable->newEntity([
            'workflow_definition_id' => $def->id,
            'workflow_version_id' => $version->id,
            'status' => 'waiting',
        ]);
        $instancesTable->saveOrFail($instance);

        $logsTable = TableRegistry::getTableLocator()->get('WorkflowExecutionLogs');
        $log = $logsTable->newEntity([
            'workflow_instance_id' => $instance->id,
            'node_id' => 'approval_node',
            'node_type' => 'approval',
            'status' => 'waiting',
        ]);
        $logsTable->saveOrFail($log);

        return [(int)$instance->id, (int)$log->id];
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function createAwardWorkflowContext(int $branchId): array
    {
        [$instanceId, $executionLogId] = $this->createWorkflowContext();
        $process = $this->createApprovalProcess();
        $award = $this->createAward((int)$process->id, $branchId);
        $recommendation = $this->createRecommendation((int)$award->id, $branchId);

        $runs = TableRegistry::getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $run = $runs->saveOrFail($runs->newEntity([
            'recommendation_id' => $recommendation->id,
            'approval_process_id' => $process->id,
            'workflow_instance_id' => $instanceId,
            'status' => 'in_progress',
            'current_step_key' => 'approval',
            'current_step_label' => 'Approval',
            'started' => DateTime::now(),
        ]));

        return [$instanceId, $executionLogId, (int)$run->id];
    }

    private function createApprovalProcess()
    {
        $processes = TableRegistry::getTableLocator()->get('Awards.ApprovalProcesses');

        return $processes->saveOrFail($processes->newEntity([
            'name' => 'Workflow Approval Scope Test ' . uniqid('', true),
            'is_active' => true,
        ]));
    }

    private function createAward(int $processId, int $branchId)
    {
        $awards = TableRegistry::getTableLocator()->get('Awards.Awards');

        return $awards->saveOrFail($awards->newEntity([
            'name' => 'Workflow Approval Scope Award ' . uniqid('', true),
            'abbreviation' => strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'domain_id' => 2,
            'level_id' => 1,
            'branch_id' => $branchId,
            'approval_process_id' => $processId,
            'is_active' => true,
        ]));
    }

    private function createRecommendation(int $awardId, int $branchId)
    {
        $recommendations = TableRegistry::getTableLocator()->get('Awards.Recommendations');

        return $recommendations->saveOrFail($recommendations->newEntity([
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => $branchId,
            'award_id' => $awardId,
            'status' => 'In Progress',
            'state' => 'Submitted',
            'state_date' => DateTime::now(),
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@amp.ansteorra.org',
            'contact_number' => '555-555-0100',
            'reason' => 'Testing approval scope',
            'call_into_court' => 'No',
            'court_availability' => 'Anytime',
        ]));
    }

    /**
     * @param array<string, mixed> $overrides Approval field overrides.
     * @return int Approval ID.
     */
    private function createApproval(int $instanceId, int $executionLogId, array $overrides = []): int
    {
        $approval = $this->approvalsTable->newEntity(array_merge([
            'workflow_instance_id' => $instanceId,
            'node_id' => 'approval_node',
            'execution_log_id' => $executionLogId,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approver_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'required_count' => 1,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'allow_parallel' => true,
            'version' => 1,
        ], $overrides));
        $this->approvalsTable->saveOrFail($approval);

        return (int)$approval->id;
    }

    /**
     * @return array{member_id:int,role_id:int,branch_id:int}
     */
    private function findCurrentBranchRoleScope(): array
    {
        $memberRole = TableRegistry::getTableLocator()->get('MemberRoles')->find('current')
            ->select(['member_id', 'role_id', 'branch_id'])
            ->where(['MemberRoles.branch_id IS NOT' => null])
            ->firstOrFail();

        return [
            'member_id' => (int)$memberRole->member_id,
            'role_id' => (int)$memberRole->role_id,
            'branch_id' => (int)$memberRole->branch_id,
        ];
    }

    private function findBranchOutsideCurrentRoleScope(int $memberId, int $roleId): int
    {
        $memberRoles = TableRegistry::getTableLocator()->get('MemberRoles');
        $currentBranchIds = $memberRoles->find('current')
            ->select(['branch_id'])
            ->where([
                'MemberRoles.member_id' => $memberId,
                'MemberRoles.role_id' => $roleId,
            ])
            ->all()
            ->extract('branch_id')
            ->map(static fn($id): int => (int)$id)
            ->toList();

        $branches = TableRegistry::getTableLocator()->get('Branches');
        $query = $branches->find()
            ->select(['id'])
            ->orderBy(['id' => 'ASC']);
        if ($currentBranchIds !== []) {
            $query->where(['id NOT IN' => $currentBranchIds]);
        }

        return (int)$query->firstOrFail()->id;
    }

    /**
     * @return array{member_id:int,permission_id:int,branch_id:int}
     */
    private function findCurrentBranchPermissionScope(): array
    {
        $memberRole = TableRegistry::getTableLocator()->get('MemberRoles')->find('current')
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
        $officer = TableRegistry::getTableLocator()->get('Officers.Officers')->find()
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
        $branch = TableRegistry::getTableLocator()->get('Branches')->get($branchId);

        return (string)$branch->type;
    }

    /**
     * @return array<string, mixed>
     */
    private function getMemberApprovalScope(int $memberId): array
    {
        $reflection = new ReflectionMethod(WorkflowApprovalsTable::class, 'getMemberApprovalScope');
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $memberId);
    }
}
