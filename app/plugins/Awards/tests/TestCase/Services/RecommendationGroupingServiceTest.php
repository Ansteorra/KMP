<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowInstance;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Services\RecommendationDeletionService;
use Awards\Services\RecommendationGroupingService;
use Awards\Services\RecommendationTransitionService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use InvalidArgumentException;

class RecommendationGroupingServiceTest extends BaseTestCase
{
    private RecommendationGroupingService $service;
    private Table $recommendationsTable;
    private Table $stateLogsTable;
    private Table $approvalRunsTable;
    private Table $workflowInstancesTable;
    private Table $workflowApprovalsTable;
    private int $awardId;
    private int $approvalProcessId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->stateLogsTable = $this->getTableLocator()->get('Awards.RecommendationsStatesLogs');
        $this->approvalRunsTable = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $this->workflowInstancesTable = $this->getTableLocator()->get('WorkflowInstances');
        $this->workflowApprovalsTable = $this->getTableLocator()->get('WorkflowApprovals');
        $this->service = new RecommendationGroupingService($this->recommendationsTable);
        $this->awardId = $this->getFirstAwardId();
        $this->approvalProcessId = $this->createApprovalProcess();
    }

    public function testGroupRecommendationsSnapshotsOriginStateAndWritesLinkedLog(): void
    {
        $head = $this->createTestRecommendation(['state' => $this->stateForStatus('In Progress', ['Linked'])]);
        $childOriginState = $this->differentNonLinkedState((string)$head->state);
        $child = $this->createTestRecommendation(['state' => $childOriginState]);

        $this->service->groupRecommendations([(int)$head->id, (int)$child->id], self::ADMIN_MEMBER_ID);

        $freshChild = $this->recommendationsTable->get((int)$child->id);
        $this->assertSame((int)$head->id, (int)$freshChild->recommendation_group_id);
        $this->assertSame('Linked', $freshChild->state);
        $this->assertSame($childOriginState, $freshChild->group_origin_state);
        $this->assertSame($this->statusForState($childOriginState), $freshChild->group_origin_status);

        $log = $this->latestStateLogFor((int)$child->id);
        $this->assertSame($childOriginState, $log->from_state);
        $this->assertSame('Linked', $log->to_state);
        $this->assertSame(self::ADMIN_MEMBER_ID, (int)$log->created_by);
    }

    public function testUngroupRecommendationsRestoresOriginalStateAfterLinkedClosedSync(): void
    {
        $head = $this->createTestRecommendation(['state' => $this->stateForStatus('In Progress', ['Linked'])]);
        $childOriginState = $this->differentNonLinkedState((string)$head->state);
        $child = $this->createTestRecommendation(['state' => $childOriginState]);

        $this->service->groupRecommendations([(int)$head->id, (int)$child->id], self::ADMIN_MEMBER_ID);

        $transitionService = new RecommendationTransitionService($this->service);
        $transitionService->transition(
            $this->recommendationsTable,
            (int)$head->id,
            ['targetState' => $this->stateForStatus('Closed', ['Linked - Closed', 'Given'])],
            self::ADMIN_MEMBER_ID,
        );

        $syncedChild = $this->recommendationsTable->get((int)$child->id);
        $this->assertSame('Linked - Closed', $syncedChild->state);

        $this->service->ungroupRecommendations((int)$head->id, self::ADMIN_MEMBER_ID);

        $restoredChild = $this->recommendationsTable->get((int)$child->id);
        $this->assertNull($restoredChild->recommendation_group_id);
        $this->assertSame($childOriginState, $restoredChild->state);
        $this->assertNull($restoredChild->group_origin_state);
        $this->assertNull($restoredChild->group_origin_status);

        $log = $this->latestStateLogFor((int)$child->id);
        $this->assertSame('Linked - Closed', $log->from_state);
        $this->assertSame($childOriginState, $log->to_state);
    }

    public function testRemoveFromGroupAutoUngroupsFinalChildAndRestoresBothOrigins(): void
    {
        $head = $this->createTestRecommendation(['state' => $this->stateForStatus('In Progress', ['Linked'])]);
        $firstChildOrigin = $this->differentNonLinkedState((string)$head->state);
        $secondChildOrigin = $this->differentNonLinkedState($firstChildOrigin, [(string)$head->state]);
        $firstChild = $this->createTestRecommendation(['state' => $firstChildOrigin]);
        $secondChild = $this->createTestRecommendation(['state' => $secondChildOrigin]);

        $this->service->groupRecommendations(
            [(int)$head->id, (int)$firstChild->id, (int)$secondChild->id],
            self::ADMIN_MEMBER_ID,
        );

        $formerHeadId = $this->service->removeFromGroup((int)$firstChild->id, self::ADMIN_MEMBER_ID);

        $this->assertSame((int)$head->id, $formerHeadId);

        $freshFirstChild = $this->recommendationsTable->get((int)$firstChild->id);
        $freshSecondChild = $this->recommendationsTable->get((int)$secondChild->id);
        $this->assertNull($freshFirstChild->recommendation_group_id);
        $this->assertNull($freshSecondChild->recommendation_group_id);
        $this->assertSame($firstChildOrigin, $freshFirstChild->state);
        $this->assertSame($secondChildOrigin, $freshSecondChild->state);
        $this->assertNull($freshFirstChild->group_origin_state);
        $this->assertNull($freshSecondChild->group_origin_state);

        $secondChildLog = $this->latestStateLogFor((int)$secondChild->id);
        $this->assertSame('Linked', $secondChildLog->from_state);
        $this->assertSame($secondChildOrigin, $secondChildLog->to_state);
    }

    public function testSoftDeletingHeadRestoresChildrenUsingOriginSnapshots(): void
    {
        $head = $this->createTestRecommendation(['state' => $this->stateForStatus('In Progress', ['Linked'])]);
        $childOriginState = $this->differentNonLinkedState((string)$head->state);
        $child = $this->createTestRecommendation(['state' => $childOriginState]);

        $this->service->groupRecommendations([(int)$head->id, (int)$child->id], self::ADMIN_MEMBER_ID);

        $transitionService = new RecommendationTransitionService($this->service);
        $transitionService->transition(
            $this->recommendationsTable,
            (int)$head->id,
            ['targetState' => $this->stateForStatus('Closed', ['Linked - Closed', 'Given'])],
            self::ADMIN_MEMBER_ID,
        );

        $deleteService = new RecommendationDeletionService($this->service);
        $deleteResult = $deleteService->delete(
            $this->recommendationsTable,
            $this->recommendationsTable->get((int)$head->id),
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($deleteResult['success']);

        $restoredChild = $this->recommendationsTable->get((int)$child->id);
        $this->assertNull($restoredChild->recommendation_group_id);
        $this->assertSame($childOriginState, $restoredChild->state);
        $this->assertNull($restoredChild->group_origin_state);

        $log = $this->latestStateLogFor((int)$child->id);
        $this->assertSame('Linked - Closed', $log->from_state);
        $this->assertSame($childOriginState, $log->to_state);
    }

    public function testDeletingHeadCancelsActiveApprovalRunBeforeRestoringChildren(): void
    {
        $head = $this->createTestRecommendation(['state' => 'Submitted']);
        $child = $this->createTestRecommendation(['state' => 'Submitted']);
        $this->service->groupRecommendations([(int)$head->id, (int)$child->id], self::ADMIN_MEMBER_ID);
        $approvalRunId = $this->createActiveApprovalRun((int)$head->id, RecommendationApprovalRun::STATUS_IN_PROGRESS);

        $deleteService = new RecommendationDeletionService($this->service);
        $deleteResult = $deleteService->delete(
            $this->recommendationsTable,
            $this->recommendationsTable->get((int)$head->id),
            self::ADMIN_MEMBER_ID,
        );

        $this->assertTrue($deleteResult['success'], $deleteResult['message'] ?? json_encode($deleteResult));
        $run = $this->approvalRunsTable->get($approvalRunId);
        $this->assertSame(RecommendationApprovalRun::STATUS_CANCELLED, $run->status);
        $this->assertSame(RecommendationApprovalRun::TERMINAL_REASON_RECOMMENDATION_DELETED, $run->terminal_reason);
    }

    private function createTestRecommendation(array $overrides = []): Recommendation
    {
        $state = (string)($overrides['state'] ?? $this->stateForStatus('In Progress', ['Linked']));
        $status = (string)($overrides['status'] ?? $this->statusForState($state));

        $data = array_merge([
            'award_id' => $this->awardId,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'requester_sca_name' => 'Test Requester',
            'member_sca_name' => 'Test Member',
            'contact_email' => 'grouping@example.com',
            'reason' => 'Testing recommendation grouping',
            'call_into_court' => 'No preference',
            'court_availability' => 'Available anytime',
        ], $overrides);

        unset($data['state'], $data['status']);

        /** @var \Awards\Model\Entity\Recommendation $entity */
        $entity = $this->recommendationsTable->newEmptyEntity();
        foreach ($data as $field => $value) {
            $entity->$field = $value;
        }
        $entity->status = $status;
        $entity->state = $state;

        return $this->recommendationsTable->saveOrFail($entity);
    }

    private function latestStateLogFor(int $recommendationId): object
    {
        $log = $this->stateLogsTable->find()
            ->where(['recommendation_id' => $recommendationId])
            ->orderBy(['id' => 'DESC'])
            ->first();

        $this->assertNotNull($log, "Expected a state log for recommendation {$recommendationId}");

        return $log;
    }

    public function testGroupingAllowedDuringActiveApprovalWithNullActor(): void
    {
        $rec1 = $this->createTestRecommendation(['state' => 'Submitted']);
        $rec2 = $this->createTestRecommendation(['state' => 'Submitted']);
        $this->createActiveApprovalRun((int)$rec1->id, RecommendationApprovalRun::STATUS_IN_PROGRESS);

        $head = $this->service->groupRecommendations([(int)$rec1->id, (int)$rec2->id], null);

        $this->assertNotNull($head);
    }

    public function testGroupingAllowedDuringChangesRequestedApproval(): void
    {
        $rec1 = $this->createTestRecommendation(['state' => 'Submitted']);
        $rec2 = $this->createTestRecommendation(['state' => 'Submitted']);
        $this->createActiveApprovalRun((int)$rec1->id, RecommendationApprovalRun::STATUS_CHANGES_REQUESTED);

        $head = $this->service->groupRecommendations([(int)$rec1->id, (int)$rec2->id], null);

        $this->assertNotNull($head);
    }

    public function testGroupingBlocksDifferentMembers(): void
    {
        $rec1 = $this->createTestRecommendation([
            'state' => 'Submitted',
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Admin von Admin',
        ]);
        $rec2 = $this->createTestRecommendation([
            'state' => 'Submitted',
            'member_id' => self::TEST_MEMBER_AGATHA_ID,
            'member_sca_name' => 'Agatha von Test',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Recommendations with different members cannot be grouped together.');

        $this->service->groupRecommendations([(int)$rec1->id, (int)$rec2->id], self::ADMIN_MEMBER_ID);
    }

    public function testGroupingAllowsSameMemberWithNullMember(): void
    {
        $rec1 = $this->createTestRecommendation([
            'state' => 'Submitted',
            'member_id' => self::ADMIN_MEMBER_ID,
            'member_sca_name' => 'Admin von Admin',
        ]);
        $rec2 = $this->createTestRecommendation([
            'state' => 'Submitted',
            'member_id' => null,
            'member_sca_name' => 'Unknown Member',
        ]);

        $head = $this->service->groupRecommendations([(int)$rec1->id, (int)$rec2->id], self::ADMIN_MEMBER_ID);

        $this->assertSame((int)$rec1->id, (int)$head->id);
        $child = $this->recommendationsTable->get((int)$rec2->id);
        $this->assertSame((int)$head->id, (int)$child->recommendation_group_id);
    }

    public function testGroupingCancelsChildWorkflowButKeepsHeadWorkflowRunning(): void
    {
        $head = $this->createTestRecommendation(['state' => 'Submitted']);
        $child = $this->createTestRecommendation(['state' => 'Submitted']);
        $headRunId = $this->createActiveApprovalRun((int)$head->id, RecommendationApprovalRun::STATUS_IN_PROGRESS);
        $childRunId = $this->createActiveApprovalRun((int)$child->id, RecommendationApprovalRun::STATUS_CHANGES_REQUESTED);
        $childRun = $this->approvalRunsTable->get($childRunId);
        $approvalId = $this->createPendingWorkflowApproval((int)$childRun->workflow_instance_id);

        $this->service->groupRecommendations([(int)$head->id, (int)$child->id], self::ADMIN_MEMBER_ID);

        $savedHeadRun = $this->approvalRunsTable->get($headRunId);
        $this->assertSame(RecommendationApprovalRun::STATUS_IN_PROGRESS, $savedHeadRun->status);
        $this->assertNull($savedHeadRun->terminal_reason);
        $headInstance = $this->workflowInstancesTable->get((int)$savedHeadRun->workflow_instance_id);
        $this->assertSame(WorkflowInstance::STATUS_WAITING, $headInstance->status);

        $savedChildRun = $this->approvalRunsTable->get($childRunId);
        $this->assertSame(RecommendationApprovalRun::STATUS_CANCELLED, $savedChildRun->status);
        $this->assertSame(RecommendationApprovalRun::TERMINAL_REASON_SUPERSEDED_BY_GROUPING, $savedChildRun->terminal_reason);
        $childInstance = $this->workflowInstancesTable->get((int)$savedChildRun->workflow_instance_id);
        $this->assertSame(WorkflowInstance::STATUS_CANCELLED, $childInstance->status);
        $approval = $this->workflowApprovalsTable->get($approvalId);
        $this->assertSame(WorkflowApproval::STATUS_CANCELLED, $approval->status);
    }

    public function testGroupingBlockedForClosedArchivedRecommendation(): void
    {
        $rec1 = $this->createTestRecommendation(['state' => $this->stateForStatus('Closed', ['Linked - Closed'])]);
        $rec2 = $this->createTestRecommendation(['state' => 'Submitted']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Archived recommendations cannot be grouped.');

        $this->service->groupRecommendations([(int)$rec1->id, (int)$rec2->id], self::ADMIN_MEMBER_ID);
    }

    public function testGroupingBlockedForRecommendationWithGivenBestowal(): void
    {
        $rec1 = $this->createTestRecommendation(['state' => 'Need to Schedule']);
        $rec2 = $this->createTestRecommendation(['state' => 'Submitted']);
        $this->createBestowalForRecommendation($rec1, 'Given');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Archived recommendations cannot be grouped.');

        $this->service->groupRecommendations([(int)$rec1->id, (int)$rec2->id], self::ADMIN_MEMBER_ID);
    }

    public function testGroupingAllowedAfterApprovalRunCompleted(): void
    {
        $rec1 = $this->createTestRecommendation(['state' => 'King Approved']);
        $rec2 = $this->createTestRecommendation(['state' => 'King Approved']);
        $this->createApprovalRun((int)$rec1->id, RecommendationApprovalRun::STATUS_APPROVED);

        // No exception expected — completed run should not block grouping.
        $head = $this->service->groupRecommendations([(int)$rec1->id, (int)$rec2->id], self::ADMIN_MEMBER_ID);

        $this->assertNotNull($head);
    }

    public function testRemoveFromGroupBlockedWhenHeadHasActiveApprovalRun(): void
    {
        $head = $this->createTestRecommendation(['state' => 'Submitted']);
        $child = $this->createTestRecommendation(['state' => 'Submitted']);
        $this->service->groupRecommendations([(int)$head->id, (int)$child->id], self::ADMIN_MEMBER_ID);
        $this->createActiveApprovalRun((int)$head->id, RecommendationApprovalRun::STATUS_IN_PROGRESS);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot remove a recommendation from a group while one or more recommendations in the group are under active approval review.');

        $this->service->removeFromGroup((int)$child->id, self::ADMIN_MEMBER_ID);
    }

    public function testRemoveFromGroupBlockedWhenChildResolvesToHeadActiveApprovalRun(): void
    {
        $head = $this->createTestRecommendation(['state' => 'Submitted']);
        $child = $this->createTestRecommendation(['state' => 'Submitted']);
        $this->service->groupRecommendations([(int)$head->id, (int)$child->id], self::ADMIN_MEMBER_ID);
        $this->createActiveApprovalRun((int)$head->id, RecommendationApprovalRun::STATUS_IN_PROGRESS);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot remove a recommendation from a group while one or more recommendations in the group are under active approval review.');

        $this->service->removeFromGroup((int)$child->id, self::ADMIN_MEMBER_ID);
    }

    private function createActiveApprovalRun(int $recommendationId, string $status): int
    {
        return $this->createApprovalRun($recommendationId, $status);
    }

    private function createApprovalRun(int $recommendationId, string $status): int
    {
        $workflowInstanceId = $this->createWorkflowInstance();
        $entity = $this->approvalRunsTable->newEntity([
            'recommendation_id' => $recommendationId,
            'workflow_instance_id' => $workflowInstanceId,
            'approval_process_id' => $this->approvalProcessId,
            'status' => $status,
            'started' => new DateTime('2024-01-01 00:00:00'),
        ]);

        if ($status === RecommendationApprovalRun::STATUS_APPROVED) {
            $entity->completed = new DateTime('2024-01-02 00:00:00');
        }

        return (int)$this->approvalRunsTable->saveOrFail($entity)->id;
    }

    private function createBestowalForRecommendation(Recommendation $recommendation, string $state): Bestowal
    {
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $rankResult = $bestowals->find()
            ->select(['max_rank' => $bestowals->find()->func()->max('stack_rank')])
            ->disableHydration()
            ->first();

        $bestowal = $bestowals->saveOrFail($bestowals->newEntity([
            'member_id' => $recommendation->member_id,
            'award_id' => $recommendation->award_id,
            'primary_recommendation_id' => $recommendation->id,
            'state' => $state,
            'status' => 'Planning',
            'source' => Bestowal::SOURCE_RECOMMENDATION,
            'stack_rank' => (int)($rankResult['max_rank'] ?? 0) + 1,
            'created_by' => self::ADMIN_MEMBER_ID,
            'modified_by' => self::ADMIN_MEMBER_ID,
        ]));

        $recommendation->bestowal_id = $bestowal->id;
        $this->recommendationsTable->saveOrFail($recommendation, ['systemSync' => true]);

        return $bestowal;
    }

    private function createApprovalProcess(): int
    {
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses');

        return (int)$processes->saveOrFail($processes->newEntity([
            'name' => 'Grouping Test Process ' . uniqid('', true),
            'is_active' => true,
        ]))->id;
    }

    private function createWorkflowInstance(): int
    {
        $definitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $versions = $this->getTableLocator()->get('WorkflowVersions');
        $instances = $this->getTableLocator()->get('WorkflowInstances');

        $definition = $definitions->saveOrFail($definitions->newEntity([
            'name' => 'Grouping Guard Test ' . uniqid('', true),
            'slug' => 'grouping-guard-' . uniqid(),
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

        return (int)$instances->saveOrFail($instances->newEntity([
            'workflow_definition_id' => $definition->id,
            'workflow_version_id' => $version->id,
            'status' => 'waiting',
        ]))->id;
    }

    private function createPendingWorkflowApproval(int $workflowInstanceId): int
    {
        $logs = $this->getTableLocator()->get('WorkflowExecutionLogs');
        $log = $logs->saveOrFail($logs->newEntity([
            'workflow_instance_id' => $workflowInstanceId,
            'node_id' => 'approval',
            'node_type' => 'approval',
            'status' => 'running',
            'started_at' => new DateTime('2024-01-01 00:00:00'),
        ]));

        return (int)$this->workflowApprovalsTable->saveOrFail($this->workflowApprovalsTable->newEntity([
            'workflow_instance_id' => $workflowInstanceId,
            'node_id' => 'approval',
            'execution_log_id' => $log->id,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approver_config' => ['member_id' => self::ADMIN_MEMBER_ID],
            'current_approver_id' => self::ADMIN_MEMBER_ID,
            'required_count' => 1,
            'approved_count' => 0,
            'rejected_count' => 0,
            'status' => WorkflowApproval::STATUS_PENDING,
            'approval_token' => 'grouping-test-token-' . uniqid(),
        ]))->id;
    }

    private function getFirstAwardId(): int
    {
        $awardsTable = $this->getTableLocator()->get('Awards.Awards');
        $award = $awardsTable->find()->select(['id'])->first();
        if ($award === null) {
            $this->markTestSkipped('No awards in test database');
        }

        return (int)$award->id;
    }

    private function stateForStatus(string $status, array $exclude = []): string
    {
        $states = Recommendation::getStatuses()[$status] ?? [];
        foreach ($states as $state) {
            if (!in_array($state, $exclude, true)) {
                return $state;
            }
        }

        $this->markTestSkipped("No usable {$status} state available");
    }

    private function statusForState(string $state): string
    {
        foreach (Recommendation::getStatuses() as $status => $states) {
            if (in_array($state, $states, true)) {
                return (string)$status;
            }
        }

        $this->fail("Unknown status for state {$state}");
    }

    private function differentNonLinkedState(string $excludeState, array $extraExcludes = []): string
    {
        $excluded = array_merge(['Linked', 'Linked - Closed', $excludeState], $extraExcludes);
        foreach (Recommendation::getStates() as $state) {
            if (!in_array($state, $excluded, true)) {
                return $state;
            }
        }

        $this->markTestSkipped('Need at least two non-linked states for grouping tests');
    }
}
