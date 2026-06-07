<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Services\BestowalHandoffService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use RuntimeException;

class BestowalHandoffServiceTest extends BaseTestCase
{
    private Table $recommendationsTable;
    private Table $bestowalsTable;
    private Table $approvalRunsTable;
    private BestowalHandoffService $service;
    private int $awardId;
    private int $approvalProcessId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        Recommendation::clearCache();
        Bestowal::clearCache();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->bestowalsTable = $this->getTableLocator()->get('Awards.Bestowals');
        $this->approvalRunsTable = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $this->service = new BestowalHandoffService(
            $this->recommendationsTable,
            $this->approvalRunsTable,
            $this->bestowalsTable,
        );
        $this->awardId = $this->getFirstAwardId();
        $this->approvalProcessId = $this->createApprovalProcess();
    }

    protected function tearDown(): void
    {
        Recommendation::clearCache();
        Bestowal::clearCache();
        parent::tearDown();
    }

    public function testCreateBestowalBlockedWhenActiveApprovalRunExists(): void
    {
        $recommendationId = $this->createRecommendation('Submitted');
        $this->createActiveApprovalRun($recommendationId, RecommendationApprovalRun::STATUS_IN_PROGRESS);

        $result = $this->service->createBestowal($recommendationId, self::ADMIN_MEMBER_ID);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('active approval review', (string)($result['error'] ?? ''));
    }

    public function testCreateBestowalBlockedWhenChangesRequestedApprovalRunExists(): void
    {
        $recommendationId = $this->createRecommendation('Submitted');
        $this->createActiveApprovalRun($recommendationId, RecommendationApprovalRun::STATUS_CHANGES_REQUESTED);

        $result = $this->service->createBestowal($recommendationId, self::ADMIN_MEMBER_ID);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('active approval review', (string)($result['error'] ?? ''));
    }

    public function testCreateBestowalAllowedWhenApprovalRunIsCompleted(): void
    {
        $recommendationId = $this->createRecommendation('King Approved');
        $this->createApprovalRun($recommendationId, RecommendationApprovalRun::STATUS_APPROVED);

        $result = $this->service->createBestowal($recommendationId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $this->assertFalse($result['skipped'] ?? false);
        $this->assertNotEmpty($result['data']['bestowalId']);
    }

    public function testCreateBestowalRecordsSourceApprovalRunIdOnBestowal(): void
    {
        $recommendationId = $this->createRecommendation('King Approved');
        $approvalRunId = $this->createApprovalRun($recommendationId, RecommendationApprovalRun::STATUS_APPROVED);

        $result = $this->service->createBestowal($recommendationId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $this->assertSame($approvalRunId, $result['data']['sourceApprovalRunId'] ?? null);

        $bestowalId = (int)$result['data']['bestowalId'];
        $bestowal = $this->bestowalsTable->get($bestowalId);
        $this->assertSame($approvalRunId, (int)$bestowal->source_approval_run_id);

        $run = $this->approvalRunsTable->get($approvalRunId);
        $this->assertSame(RecommendationApprovalRun::STATUS_CONSUMED, $run->status);
        $this->assertSame(RecommendationApprovalRun::TERMINAL_REASON_CONSUMED_BY_BESTOWAL, $run->terminal_reason);
        $this->assertSame($bestowalId, (int)$run->consumed_by_bestowal_id);
    }

    public function testCreateBestowalAllowedWithNoApprovalRunAtAll(): void
    {
        $recommendationId = $this->createRecommendation('Need to Schedule');

        $result = $this->service->createBestowal($recommendationId, self::ADMIN_MEMBER_ID);

        $this->assertTrue($result['success'], $result['error'] ?? json_encode($result));
        $this->assertNotEmpty($result['data']['bestowalId']);
        $this->assertNull($result['data']['sourceApprovalRunId'] ?? null);
    }

    public function testCreateBestowalsRejectsEmptyIdList(): void
    {
        $result = $this->service->createBestowals([], self::ADMIN_MEMBER_ID);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('At least one recommendation ID', (string)($result['error'] ?? ''));
    }

    public function testCreateBestowalsBlocksActiveApprovalInBatch(): void
    {
        $blockedId = $this->createRecommendation('Submitted');
        $this->createActiveApprovalRun($blockedId, RecommendationApprovalRun::STATUS_IN_PROGRESS);
        $allowedId = $this->createRecommendation('Need to Schedule');

        $result = $this->service->createBestowals([$blockedId, $allowedId], self::ADMIN_MEMBER_ID);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('active approval review', (string)($result['error'] ?? ''));
    }

    public function testAssertHandoffEligibleThrowsForActiveApproval(): void
    {
        $recommendationId = $this->createRecommendation('Submitted');
        $this->createActiveApprovalRun($recommendationId, RecommendationApprovalRun::STATUS_IN_PROGRESS);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('active approval review');

        $this->service->assertHandoffEligible($recommendationId);
    }

    public function testHasActiveApprovalRunDetectsInProgress(): void
    {
        $recommendationId = $this->createRecommendation('Submitted');
        $this->assertFalse($this->service->hasActiveApprovalRun($recommendationId));

        $this->createActiveApprovalRun($recommendationId, RecommendationApprovalRun::STATUS_IN_PROGRESS);

        $this->assertTrue($this->service->hasActiveApprovalRun($recommendationId));
    }

    public function testHasActiveApprovalRunIgnoresCompletedRuns(): void
    {
        $recommendationId = $this->createRecommendation('King Approved');
        $this->createApprovalRun($recommendationId, RecommendationApprovalRun::STATUS_APPROVED);

        $this->assertFalse($this->service->hasActiveApprovalRun($recommendationId));
    }

    public function testFindLatestApprovedRunIdReturnsNullWhenNoApprovedRun(): void
    {
        $recommendationId = $this->createRecommendation('Submitted');
        $this->assertNull($this->service->findLatestApprovedRunId($recommendationId));
    }

    public function testFindLatestApprovedRunIdReturnsCorrectRunId(): void
    {
        $recommendationId = $this->createRecommendation('King Approved');
        $approvalRunId = $this->createApprovalRun($recommendationId, RecommendationApprovalRun::STATUS_APPROVED);

        $foundId = $this->service->findLatestApprovedRunId($recommendationId);

        $this->assertSame($approvalRunId, $foundId);
    }

    private function createRecommendation(string $state): int
    {
        $entity = $this->recommendationsTable->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $this->awardId,
            'reason' => 'Handoff service test',
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@test.com',
            'status' => $this->statusForState($state),
            'state' => $state,
            'state_date' => new DateTime('2024-01-01 00:00:00'),
            'call_into_court' => 'Not Set',
            'court_availability' => 'Not Set',
            'person_to_notify' => '',
            'branch_id' => self::KINGDOM_BRANCH_ID,
        ]);

        return (int)$this->recommendationsTable->saveOrFail($entity)->id;
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

    private function createApprovalProcess(): int
    {
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses');

        return (int)$processes->saveOrFail($processes->newEntity([
            'name' => 'Handoff Test Process ' . uniqid('', true),
            'is_active' => true,
        ]))->id;
    }

    private function getFirstAwardId(): int
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->first();
        $this->assertNotNull($award);

        return (int)$award->id;
    }

    private function statusForState(string $state): string
    {
        foreach (Recommendation::getStatuses() as $status => $states) {
            if (in_array($state, $states, true)) {
                return $status;
            }
        }

        $this->fail('Unknown recommendation state: ' . $state);
    }
}
