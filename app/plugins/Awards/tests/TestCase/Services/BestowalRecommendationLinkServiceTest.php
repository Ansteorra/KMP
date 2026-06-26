<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\WorkflowInstance;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use Awards\Services\BestowalCreationService;
use Awards\Services\BestowalRecommendationLinkService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;
use RuntimeException;

class BestowalRecommendationLinkServiceTest extends BaseTestCase
{
    private Table $recommendationsTable;
    private Table $bestowalsTable;
    private Table $approvalRunsTable;
    private BestowalCreationService $creationService;
    private BestowalRecommendationLinkService $linkService;
    private int $approvalProcessId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        Recommendation::clearCache();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->bestowalsTable = $this->getTableLocator()->get('Awards.Bestowals');
        $this->approvalRunsTable = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $this->creationService = new BestowalCreationService();
        $this->linkService = new BestowalRecommendationLinkService();
        $this->approvalProcessId = $this->createApprovalProcess();
    }

    protected function tearDown(): void
    {
        Recommendation::clearCache();
        parent::tearDown();
    }

    public function testCannotUnlinkOnlyLinkedRecommendation(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A bestowal must keep at least one linked recommendation.');

        $linkedIds = $this->linkService->getLinkedRecommendationIds($bestowalId);
        $this->linkService->unlinkRecommendations($bestowalId, $linkedIds, self::ADMIN_MEMBER_ID);
    }

    public function testCannotUnlinkAllRecommendationsWhenMultipleLinked(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(2);
        $linkedIds = $this->linkService->getLinkedRecommendationIds($bestowalId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A bestowal must keep at least one linked recommendation.');

        $this->linkService->unlinkRecommendations($bestowalId, $linkedIds, self::ADMIN_MEMBER_ID);
    }

    public function testCanUnlinkOneRecommendationWhenMultipleLinked(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(2);
        $linkedIds = $this->linkService->getLinkedRecommendationIds($bestowalId);

        $unlinked = $this->linkService->unlinkRecommendations(
            $bestowalId,
            [$linkedIds[0]],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertSame([$linkedIds[0]], $unlinked);
        $this->assertSame([$linkedIds[1]], $this->linkService->getLinkedRecommendationIds($bestowalId));
    }

    public function testLinkRepairsExistingJoinWithMissingRecommendationShortcut(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(2);
        $linkedIds = $this->linkService->getLinkedRecommendationIds($bestowalId);
        $staleRecommendationId = $linkedIds[0];

        $recommendation = $this->recommendationsTable->get($staleRecommendationId);
        $recommendation->bestowal_id = null;
        $this->recommendationsTable->saveOrFail($recommendation, ['systemSync' => true]);

        $linked = $this->linkService->linkRecommendations(
            $bestowalId,
            [$staleRecommendationId],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertSame([$staleRecommendationId], $linked);
        $updated = $this->recommendationsTable->get($staleRecommendationId);
        $this->assertSame($bestowalId, (int)$updated->bestowal_id);
    }

    public function testLinkRefreshesReasonSummary(): void
    {
        $firstRecommendationId = $this->createRecommendation('Need to Schedule', [
            'reason' => 'Original reason.',
            'requester_sca_name' => 'Original Submitter',
            'specialty' => 'Original Specialty',
        ]);
        $createResult = $this->creationService->createFromRecommendation(
            $firstRecommendationId,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($createResult['success'], $createResult['error'] ?? json_encode($createResult));
        $bestowalId = (int)$createResult['data']['bestowalId'];
        $secondRecommendationId = $this->createRecommendation('King Approved', [
            'reason' => 'Additional reason.',
            'requester_sca_name' => 'Additional Submitter',
            'specialty' => 'Additional Specialty',
        ]);

        $this->linkService->linkRecommendations(
            $bestowalId,
            [$secondRecommendationId],
            self::ADMIN_MEMBER_ID,
        );

        $bestowal = $this->bestowalsTable->get($bestowalId);
        $summary = (string)$bestowal->reason_summary;
        $this->assertStringContainsString('Submitted by Original Submitter:', $summary);
        $this->assertStringContainsString('Original reason.', $summary);
        $this->assertStringContainsString('Submitted by Additional Submitter:', $summary);
        $this->assertStringContainsString('Additional reason.', $summary);
        $this->assertSame('Original Specialty, Additional Specialty', $bestowal->specialty);
    }

    public function testAssertMinimumLinkedRecommendationsAllowsLinkBeforeUnlinkSwap(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(1);
        $linkedIds = $this->linkService->getLinkedRecommendationIds($bestowalId);
        $replacementId = $this->createRecommendation('King Approved');

        $this->linkService->assertMinimumLinkedRecommendations(
            $bestowalId,
            $linkedIds,
            [$replacementId],
        );

        $this->assertTrue(true);
    }

    public function testAssertMinimumLinkedRecommendationsRejectsUnlinkingAllWithoutReplacement(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(2);
        $linkedIds = $this->linkService->getLinkedRecommendationIds($bestowalId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A bestowal must keep at least one linked recommendation.');

        $this->linkService->assertMinimumLinkedRecommendations($bestowalId, $linkedIds, []);
    }

    /**
     * @param int $recommendationCount Number of recommendations to link to the bestowal.
     * @return int Bestowal ID.
     */
    private function createBestowalWithRecommendations(int $recommendationCount): int
    {
        $this->assertGreaterThan(0, $recommendationCount);

        $firstRecommendationId = $this->createRecommendation('Need to Schedule');
        $createResult = $this->creationService->createFromRecommendation(
            $firstRecommendationId,
            self::ADMIN_MEMBER_ID,
        );
        $this->assertTrue($createResult['success'], $createResult['error'] ?? json_encode($createResult));

        $bestowalId = (int)$createResult['data']['bestowalId'];

        for ($i = 1; $i < $recommendationCount; $i++) {
            $additionalRecommendationId = $this->createRecommendation('King Approved');
            $this->linkService->linkRecommendations(
                $bestowalId,
                [$additionalRecommendationId],
                self::ADMIN_MEMBER_ID,
            );
        }

        return $bestowalId;
    }

    private function createRecommendation(string $state, array $overrides = []): int
    {
        $entity = $this->recommendationsTable->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $this->getFirstAwardId(),
            'reason' => 'Bestowal link service test',
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

        foreach ($overrides as $field => $value) {
            $entity->set($field, $value);
        }

        return (int)$this->recommendationsTable->saveOrFail($entity)->id;
    }

    public function testLinkSupersedesActiveApprovalRun(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(1);
        $secondRecommendationId = $this->createRecommendation('Submitted');
        $approvalRunId = $this->createActiveApprovalRun(
            $secondRecommendationId,
            RecommendationApprovalRun::STATUS_IN_PROGRESS,
        );

        $linked = $this->linkService->linkRecommendations(
            $bestowalId,
            [$secondRecommendationId],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertSame([$secondRecommendationId], $linked);
        $run = $this->approvalRunsTable->get($approvalRunId);
        $this->assertSame(RecommendationApprovalRun::STATUS_CANCELLED, $run->status);
        $this->assertSame(
            RecommendationApprovalRun::TERMINAL_REASON_SUPERSEDED_BY_BESTOWAL_LINK,
            $run->terminal_reason,
        );
        $this->assertSame($bestowalId, (int)$run->superseded_by_bestowal_id);

        $workflowInstance = $this->getTableLocator()->get('WorkflowInstances')->get((int)$run->workflow_instance_id);
        $this->assertSame(WorkflowInstance::STATUS_CANCELLED, $workflowInstance->status);
    }

    public function testLinkSupersedesChangesRequestedApprovalRun(): void
    {
        $bestowalId = $this->createBestowalWithRecommendations(1);
        $secondRecommendationId = $this->createRecommendation('Submitted');
        $approvalRunId = $this->createActiveApprovalRun(
            $secondRecommendationId,
            RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
        );

        $linked = $this->linkService->linkRecommendations(
            $bestowalId,
            [$secondRecommendationId],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertSame([$secondRecommendationId], $linked);
        $run = $this->approvalRunsTable->get($approvalRunId);
        $this->assertSame(RecommendationApprovalRun::STATUS_CANCELLED, $run->status);
        $this->assertSame(
            RecommendationApprovalRun::TERMINAL_REASON_SUPERSEDED_BY_BESTOWAL_LINK,
            $run->terminal_reason,
        );
        $this->assertSame($bestowalId, (int)$run->superseded_by_bestowal_id);
    }

    private function createActiveApprovalRun(int $recommendationId, string $status): int
    {
        $workflowInstanceId = $this->createWorkflowInstance();
        $entity = $this->approvalRunsTable->newEntity([
            'recommendation_id' => $recommendationId,
            'workflow_instance_id' => $workflowInstanceId,
            'approval_process_id' => $this->approvalProcessId,
            'status' => $status,
            'started' => new DateTime('2024-01-01 00:00:00'),
        ]);

        return (int)$this->approvalRunsTable->saveOrFail($entity)->id;
    }

    private function createApprovalProcess(): int
    {
        $processes = $this->getTableLocator()->get('Awards.ApprovalProcesses');

        return (int)$processes->saveOrFail($processes->newEntity([
            'name' => 'Link Guard Test Process ' . uniqid('', true),
            'is_active' => true,
        ]))->id;
    }

    private function createWorkflowInstance(): int
    {
        $definitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $versions = $this->getTableLocator()->get('WorkflowVersions');
        $instances = $this->getTableLocator()->get('WorkflowInstances');

        $definition = $definitions->saveOrFail($definitions->newEntity([
            'name' => 'Link Guard Test ' . uniqid('', true),
            'slug' => 'link-guard-' . uniqid(),
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
