<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Controller;

use App\Model\Entity\WorkflowApproval;
use App\Model\Entity\WorkflowApprovalResponse;
use App\Model\Entity\WorkflowInstance;
use App\Test\TestCase\Support\HttpIntegrationTestCase;
use Awards\KMP\GridColumns\RecommendationsGridColumns;
use Awards\Model\Entity\Bestowal;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationApprovalRun;
use DateTimeImmutable;

class RecommendationsGridTest extends HttpIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
        $this->authenticateAsSuperUser();
    }

    public function testGridDataIncludesAwardLevelFilterAndAppliesIt(): void
    {
        [$matchingAward, $otherAward] = $this->getAwardsWithDifferentLevels();

        $matchingReason = 'award-level-filter-match-' . uniqid();
        $otherReason = 'award-level-filter-other-' . uniqid();

        $this->createRecommendation($matchingAward->id, $matchingReason);
        $this->createRecommendation($otherAward->id, $otherReason);

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'view_id' => 'sys-recs-all',
            'search' => 'award-level-filter-',
            'filter' => [
                'level_name' => (string)$matchingAward->level_id,
            ],
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('Award Level');
        $this->assertResponseContains($matchingReason);
        $this->assertResponseNotContains($otherReason);
    }

    public function testGridDataShowsOrderOfPrecedenceLink(): void
    {
        $appSettings = $this->getTableLocator()->get('AppSettings');
        $members = $this->getTableLocator()->get('Members');
        $awards = $this->getTableLocator()->get('Awards.Awards');

        $appSettings->setAppSetting(
            'Member.ExternalLink.Order of Precedence',
            'https://op.example.test/people/id/{{additional_info->OrderOfPrecedence_Id}}',
            'string',
            false,
        );

        $member = $members->get(self::ADMIN_MEMBER_ID);
        $member->additional_info = ['OrderOfPrecedence_Id' => '424242'];
        $savedMember = $members->save($member);
        $this->assertNotFalse($savedMember, 'Failed to save test member OP data');

        $award = $awards->find()->select(['id'])->firstOrFail();
        $reason = 'op-link-grid-test-' . uniqid();
        $this->createRecommendation($award->id, $reason);

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'view_id' => 'sys-recs-all',
            'search' => $reason,
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('https://op.example.test/people/id/424242');
    }

    public function testInApprovalSystemViewUsesApprovalQueueState(): void
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $queuedReason = 'approval-queue-filter-match-' . uniqid();
        $changesRequestedReason = 'approval-queue-filter-changes-' . uniqid();
        $declinedChangesRequestedReason = 'approval-queue-filter-declined-' . uniqid();
        $closedRunReason = 'approval-queue-filter-closed-' . uniqid();
        $noQueueReason = 'approval-queue-filter-none-' . uniqid();

        $queuedRecommendation = $this->createRecommendation($award->id, $queuedReason);
        $changesRequestedRecommendation = $this->createRecommendation($award->id, $changesRequestedReason);
        $declinedChangesRequestedRecommendation = $this->createRecommendation($award->id, $declinedChangesRequestedReason);
        $closedRunRecommendation = $this->createRecommendation($award->id, $closedRunReason);
        $this->createRecommendation($award->id, $noQueueReason);

        $this->createApprovalRun(
            (int)$queuedRecommendation->id,
            RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'Crown Review',
        );
        $this->createApprovalRun(
            (int)$changesRequestedRecommendation->id,
            RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
            'Changes Requested',
        );
        $declinedChangesRequestedRun = $this->createApprovalRun(
            (int)$declinedChangesRequestedRecommendation->id,
            RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
            'Changes Requested',
        );
        $this->createWorkflowRejectionResponseForRun($declinedChangesRequestedRun, self::ADMIN_MEMBER_ID);
        $this->createApprovalRun(
            (int)$closedRunRecommendation->id,
            RecommendationApprovalRun::STATUS_APPROVED,
            'Approved',
        );

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'view_id' => 'sys-recs-in-approval',
            'search' => 'approval-queue-filter-',
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('Crown Review');
        $this->assertResponseContains($queuedReason);
        $this->assertResponseContains($changesRequestedReason);
        $this->assertResponseNotContains($declinedChangesRequestedReason);
        $this->assertResponseNotContains($closedRunReason);
        $this->assertResponseNotContains($noQueueReason);
    }

    public function testDefaultSystemViewIsInApproval(): void
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $queuedReason = 'default-in-approval-filter-match-' . uniqid();
        $noQueueReason = 'default-in-approval-filter-none-' . uniqid();

        $queuedRecommendation = $this->createRecommendation($award->id, $queuedReason);
        $this->createRecommendation($award->id, $noQueueReason);

        $this->createApprovalRun(
            (int)$queuedRecommendation->id,
            RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'Crown Review',
        );

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'search' => 'default-in-approval-filter-',
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains($queuedReason);
        $this->assertResponseNotContains($noQueueReason);
    }

    public function testWorkflowCentricSystemViewsExcludeLegacyRecommendationStateTabs(): void
    {
        $systemViews = RecommendationsGridColumns::getSystemViews([]);

        $this->assertArrayHasKey('sys-recs-in-approval', $systemViews);
        $this->assertArrayHasKey('sys-recs-needs-my-approval', $systemViews);
        $this->assertArrayHasKey('sys-recs-approved-by-me', $systemViews);
        $this->assertArrayNotHasKey('sys-recs-needs-approval', $systemViews);
        $this->assertArrayNotHasKey('sys-recs-ready-for-bestowal', $systemViews);
        $this->assertSame([], $systemViews['sys-recs-in-approval']['config']['filters']);
        $this->assertSame([], $systemViews['sys-recs-needs-my-approval']['config']['filters']);
        $this->assertSame([
            'sys-recs-in-approval',
            'sys-recs-needs-my-approval',
            'sys-recs-approved-by-me',
            'sys-recs-converted',
        ], array_slice(array_keys($systemViews), 0, 4));
    }

    public function testNeedsMyApprovalSystemViewShowsOnlyCurrentUserPendingWorkflowRecommendations(): void
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $needsMeReason = 'needs-my-approval-filter-match-' . uniqid();
        $needsOtherReason = 'needs-my-approval-filter-other-' . uniqid();
        $approvedByMeReason = 'needs-my-approval-filter-approved-' . uniqid();
        $closedRunReason = 'needs-my-approval-filter-closed-' . uniqid();

        $needsMeRecommendation = $this->createRecommendation($award->id, $needsMeReason);
        $needsOtherRecommendation = $this->createRecommendation($award->id, $needsOtherReason);
        $approvedByMeRecommendation = $this->createRecommendation($award->id, $approvedByMeReason);
        $closedRunRecommendation = $this->createRecommendation($award->id, $closedRunReason);

        $needsMeRun = $this->createApprovalRun(
            (int)$needsMeRecommendation->id,
            RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'Crown Review',
        );
        $needsOtherRun = $this->createApprovalRun(
            (int)$needsOtherRecommendation->id,
            RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'Crown Review',
        );
        $approvedByMeRun = $this->createApprovalRun(
            (int)$approvedByMeRecommendation->id,
            RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'Crown Review',
        );
        $closedRun = $this->createApprovalRun(
            (int)$closedRunRecommendation->id,
            RecommendationApprovalRun::STATUS_APPROVED,
            'Approved',
        );
        $this->createPendingWorkflowApprovalForRun($needsMeRun, self::ADMIN_MEMBER_ID);
        $this->createPendingWorkflowApprovalForRun($needsOtherRun, self::TEST_MEMBER_AGATHA_ID);
        $this->createWorkflowApprovalResponseForRun($approvedByMeRun, self::ADMIN_MEMBER_ID);
        $this->createPendingWorkflowApprovalForRun($closedRun, self::ADMIN_MEMBER_ID);

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'view_id' => 'sys-recs-needs-my-approval',
            'search' => 'needs-my-approval-filter-',
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains($needsMeReason);
        $this->assertResponseNotContains($needsOtherReason);
        $this->assertResponseNotContains($approvedByMeReason);
        $this->assertResponseNotContains($closedRunReason);
    }

    public function testApprovedByMeSystemViewShowsActiveWorkflowRecommendationsApprovedByCurrentUser(): void
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $approvedByMeReason = 'approved-by-me-filter-match-' . uniqid();
        $approvedByOtherReason = 'approved-by-me-filter-other-' . uniqid();
        $closedRunReason = 'approved-by-me-filter-closed-' . uniqid();
        $noResponseReason = 'approved-by-me-filter-none-' . uniqid();

        $approvedByMeRecommendation = $this->createRecommendation($award->id, $approvedByMeReason);
        $approvedByOtherRecommendation = $this->createRecommendation($award->id, $approvedByOtherReason);
        $closedRunRecommendation = $this->createRecommendation($award->id, $closedRunReason);
        $this->createRecommendation($award->id, $noResponseReason);

        $approvedByMeRun = $this->createApprovalRun(
            (int)$approvedByMeRecommendation->id,
            RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'Crown Review',
        );
        $approvedByOtherRun = $this->createApprovalRun(
            (int)$approvedByOtherRecommendation->id,
            RecommendationApprovalRun::STATUS_IN_PROGRESS,
            'Crown Review',
        );
        $closedRun = $this->createApprovalRun(
            (int)$closedRunRecommendation->id,
            RecommendationApprovalRun::STATUS_APPROVED,
            'Approved',
        );
        $this->createWorkflowApprovalResponseForRun($approvedByMeRun, self::ADMIN_MEMBER_ID);
        $this->createWorkflowApprovalResponseForRun($approvedByOtherRun, self::TEST_MEMBER_AGATHA_ID);
        $this->createWorkflowApprovalResponseForRun($closedRun, self::ADMIN_MEMBER_ID);

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'view_id' => 'sys-recs-approved-by-me',
            'search' => 'approved-by-me-filter-',
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('Approved by Me');
        $this->assertResponseContains($approvedByMeReason);
        $this->assertResponseNotContains($approvedByOtherReason);
        $this->assertResponseNotContains($closedRunReason);
        $this->assertResponseNotContains($noResponseReason);
    }

    public function testConvertedSystemViewShowsLinkedRecommendationsWithActiveBestowalsOnly(): void
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $linkedReason = 'converted-filter-match-' . uniqid();
        $givenReason = 'converted-filter-given-' . uniqid();
        $unlinkedReason = 'converted-filter-unlinked-' . uniqid();

        $linkedRecommendation = $this->createRecommendation($award->id, $linkedReason, ['state' => 'Need to Schedule']);
        $givenRecommendation = $this->createRecommendation($award->id, $givenReason, ['state' => 'Need to Schedule']);
        $this->createRecommendation($award->id, $unlinkedReason, ['state' => 'Need to Schedule']);
        $linkedBestowal = $this->createBestowalForRecommendation($linkedRecommendation);
        $givenBestowal = $this->createBestowalForRecommendation($givenRecommendation, 'Given');

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'view_id' => 'sys-recs-converted',
            'search' => 'converted-filter-',
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('Converted to Bestowals');
        $this->assertResponseContains('/awards/bestowals/view/' . $linkedBestowal->id);
        $this->assertResponseNotContains('/awards/bestowals/view/' . $givenBestowal->id);
        $this->assertResponseNotContains($unlinkedReason);
    }

    public function testArchivedSystemViewShowsClosedUnlinkedRecommendationsAndGivenBestowals(): void
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->firstOrFail();

        $archivedReason = 'archived-filter-match-' . uniqid();
        $givenReason = 'archived-filter-given-' . uniqid();
        $declinedReason = 'archived-filter-declined-' . uniqid();
        $submittedReason = 'archived-filter-submitted-' . uniqid();
        $archivedState = RecommendationsGridColumns::getArchivedStates()[0] ?? 'No Action';

        $archivedRecommendation = $this->createRecommendation($award->id, $archivedReason, [
            'state' => $archivedState,
            'close_reason' => 'Grid archive test',
        ]);
        $givenRecommendation = $this->createRecommendation($award->id, $givenReason, ['state' => 'Need to Schedule']);
        $givenBestowal = $this->createBestowalForRecommendation($givenRecommendation, 'Given');
        $declinedRecommendation = $this->createRecommendation($award->id, $declinedReason, ['state' => 'Submitted']);
        $declinedRun = $this->createApprovalRun(
            (int)$declinedRecommendation->id,
            RecommendationApprovalRun::STATUS_CHANGES_REQUESTED,
            'Changes Requested',
        );
        $this->createWorkflowRejectionResponseForRun($declinedRun, self::ADMIN_MEMBER_ID);
        $submittedRecommendation = $this->createRecommendation($award->id, $submittedReason, ['state' => 'Submitted']);

        $url = '/awards/recommendations/grid-data?' . http_build_query([
            'view_id' => 'sys-recs-archived',
            'search' => 'archived-filter-',
        ]);

        $this->get($url);

        $this->assertResponseOk();
        $this->assertResponseContains('Archived');
        $this->assertResponseContains('Grid archive test');
        $this->assertResponseContains('/awards/recommendations/view/' . $archivedRecommendation->id);
        $this->assertResponseContains('/awards/recommendations/view/' . $givenRecommendation->id);
        $this->assertResponseContains('/awards/recommendations/view/' . $declinedRecommendation->id);
        $this->assertResponseContains('/awards/bestowals/view/' . $givenBestowal->id);
        $this->assertResponseNotContains('edit-rec');
        $this->assertResponseNotContains('data-bulk-action-key="workflow-decision"');
        $this->assertResponseNotContains('data-bulk-action-key="group-recs"');
        $this->assertResponseNotContains('data-bulk-action-key="request-feedback"');
        $this->assertResponseNotContains('#requestRecommendationFeedbackModal');
        $this->assertResponseNotContains('/awards/recommendations/view/' . $submittedRecommendation->id);
    }

    public function testConvertedSystemViewUsesBestowalWorkColumnsAndLinkedActions(): void
    {
        $systemViews = RecommendationsGridColumns::getSystemViews([]);
        $convertedConfig = $systemViews['sys-recs-converted']['config'];

        $this->assertSame([
            'member_sca_name',
            'branch_id',
            'domain_name',
            'award_name',
            'gatherings',
            'notes',
        ], $convertedConfig['columns']);

        $rowActions = RecommendationsGridColumns::getRowActions();
        $this->assertSame(['bestowal_linked' => false], $rowActions['edit']['condition']);
        $this->assertSame(['bestowal_viewable' => true], $rowActions['bestowal']['condition']);
        $this->assertSame('Bestowals', $rowActions['bestowal']['url']['controller']);
    }

    /**
     * @return array{0: \Awards\Model\Entity\Award, 1: \Awards\Model\Entity\Award}
     */
    private function getAwardsWithDifferentLevels(): array
    {
        $awards = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id', 'level_id'])
            ->where(['Awards.deleted IS' => null])
            ->orderByAsc('Awards.id')
            ->all()
            ->toList();

        $firstAward = null;
        $secondAward = null;

        foreach ($awards as $award) {
            if ($firstAward === null) {
                $firstAward = $award;
                continue;
            }

            if ($award->level_id !== $firstAward->level_id) {
                $secondAward = $award;
                break;
            }
        }

        $this->assertNotNull($firstAward, 'Expected at least one seeded award');
        $this->assertNotNull($secondAward, 'Expected seeded awards with different levels');

        return [$firstAward, $secondAward];
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function createRecommendation(int $awardId, string $reason, array $fields = []): Recommendation
    {
        $recommendations = $this->getTableLocator()->get('Awards.Recommendations');
        $states = Recommendation::getStates();
        $this->assertNotEmpty($states, 'Expected configured recommendation states');

        $rankResult = $recommendations->find()
            ->select(['max_rank' => $recommendations->find()->func()->max('stack_rank')])
            ->disableHydration()
            ->first();
        $maxStackRank = (int)($rankResult['max_rank'] ?? 0);

        $recommendation = $recommendations->newEntity(array_merge([
            'stack_rank' => $maxStackRank + 1,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'member_id' => self::ADMIN_MEMBER_ID,
            'branch_id' => self::KINGDOM_BRANCH_ID,
            'award_id' => $awardId,
            'state' => $states[0],
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@amp.ansteorra.org',
            'contact_number' => '555-555-0100',
            'reason' => $reason,
            'call_into_court' => 'Never',
            'court_availability' => 'Any',
            'created_by' => self::ADMIN_MEMBER_ID,
            'modified_by' => self::ADMIN_MEMBER_ID,
        ], $fields));

        $saved = $recommendations->save($recommendation);
        $this->assertNotFalse($saved, 'Failed to save test recommendation');

        return $saved;
    }

    private function createBestowalForRecommendation(Recommendation $recommendation, string $state = 'Created'): Bestowal
    {
        $bestowals = $this->getTableLocator()->get('Awards.Bestowals');
        $bestowal = $bestowals->newEntity([
            'member_id' => $recommendation->member_id,
            'award_id' => $recommendation->award_id,
            'primary_recommendation_id' => $recommendation->id,
            'state' => $state,
            'status' => 'Planning',
            'source' => Bestowal::SOURCE_RECOMMENDATION,
            'stack_rank' => 0,
        ]);
        $savedBestowal = $bestowals->saveOrFail($bestowal);
        $recommendation->bestowal_id = (int)$savedBestowal->id;
        $this->getTableLocator()->get('Awards.Recommendations')->saveOrFail($recommendation, ['systemSync' => true]);

        return $savedBestowal;
    }

    private function createApprovalRun(
        int $recommendationId,
        string $status,
        string $stepLabel,
        ?string $terminalReason = null,
    ): RecommendationApprovalRun {
        $workflowDefinitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $workflowDefinition = $workflowDefinitions->newEntity([
            'name' => 'Recommendation Approval Queue Test ' . uniqid(),
            'slug' => 'recommendation-approval-queue-test-' . uniqid(),
            'trigger_type' => 'manual',
            'entity_type' => 'Awards.Recommendations',
            'is_active' => true,
            'execution_mode' => 'durable',
        ]);
        $workflowDefinition = $workflowDefinitions->saveOrFail($workflowDefinition);

        $workflowVersions = $this->getTableLocator()->get('WorkflowVersions');
        $workflowVersion = $workflowVersions->newEntity([
            'workflow_definition_id' => $workflowDefinition->id,
            'version_number' => 1,
            'definition' => ['nodes' => [], 'connections' => []],
            'status' => 'published',
            'published_by' => self::ADMIN_MEMBER_ID,
        ]);
        $workflowVersion = $workflowVersions->saveOrFail($workflowVersion);

        $workflowInstances = $this->getTableLocator()->get('WorkflowInstances');
        $workflowInstance = $workflowInstances->newEntity([
            'workflow_definition_id' => $workflowDefinition->id,
            'workflow_version_id' => $workflowVersion->id,
            'entity_type' => 'Awards.Recommendations',
            'entity_id' => $recommendationId,
            'status' => WorkflowInstance::STATUS_WAITING,
            'started_by' => self::ADMIN_MEMBER_ID,
        ]);
        $workflowInstance = $workflowInstances->saveOrFail($workflowInstance);

        $approvalProcesses = $this->getTableLocator()->get('Awards.ApprovalProcesses');
        $approvalProcess = $approvalProcesses->newEntity([
            'name' => 'Recommendation Approval Queue Test ' . uniqid(),
            'description' => 'Test approval queue process',
            'is_active' => true,
        ]);
        $approvalProcess = $approvalProcesses->saveOrFail($approvalProcess);

        $approvalRuns = $this->getTableLocator()->get('Awards.RecommendationApprovalRuns');
        $approvalRun = $approvalRuns->newEntity([
            'recommendation_id' => $recommendationId,
            'approval_process_id' => $approvalProcess->id,
            'workflow_instance_id' => $workflowInstance->id,
            'status' => $status,
            'current_step_key' => 'crown_review',
            'current_step_label' => $stepLabel,
            'started' => new DateTimeImmutable(),
            'completed' => $terminalReason === null ? null : new DateTimeImmutable(),
            'terminal_reason' => $terminalReason,
        ]);

        return $approvalRuns->saveOrFail($approvalRun);
    }

    private function createWorkflowApprovalResponseForRun(RecommendationApprovalRun $run, int $memberId): void
    {
        $workflowApproval = $this->createWorkflowApprovalForRun(
            $run,
            $memberId,
            WorkflowApproval::STATUS_APPROVED,
            1,
        );

        $workflowApprovalResponses = $this->getTableLocator()->get('WorkflowApprovalResponses');
        $workflowApprovalResponses->saveOrFail($workflowApprovalResponses->newEntity([
            'workflow_approval_id' => $workflowApproval->id,
            'member_id' => $memberId,
            'decision' => WorkflowApprovalResponse::DECISION_APPROVE,
            'responded_at' => new DateTimeImmutable(),
        ]));
    }

    private function createWorkflowRejectionResponseForRun(RecommendationApprovalRun $run, int $memberId): void
    {
        $workflowApproval = $this->createWorkflowApprovalForRun(
            $run,
            $memberId,
            WorkflowApproval::STATUS_REJECTED,
            0,
        );

        $workflowApprovalResponses = $this->getTableLocator()->get('WorkflowApprovalResponses');
        $workflowApprovalResponses->saveOrFail($workflowApprovalResponses->newEntity([
            'workflow_approval_id' => $workflowApproval->id,
            'member_id' => $memberId,
            'decision' => WorkflowApprovalResponse::DECISION_REJECT,
            'responded_at' => new DateTimeImmutable(),
        ]));
    }

    private function createPendingWorkflowApprovalForRun(RecommendationApprovalRun $run, int $memberId): void
    {
        $this->createWorkflowApprovalForRun($run, $memberId, WorkflowApproval::STATUS_PENDING, 0);
    }

    private function createWorkflowApprovalForRun(
        RecommendationApprovalRun $run,
        int $memberId,
        string $status,
        int $approvedCount,
    ): WorkflowApproval {
        $workflowExecutionLogs = $this->getTableLocator()->get('WorkflowExecutionLogs');
        $workflowExecutionLog = $workflowExecutionLogs->newEntity([
            'workflow_instance_id' => $run->workflow_instance_id,
            'node_id' => 'recommendation_approval',
            'node_type' => 'approval',
            'status' => $status === WorkflowApproval::STATUS_PENDING ? 'waiting' : 'completed',
        ]);
        $workflowExecutionLog = $workflowExecutionLogs->saveOrFail($workflowExecutionLog);

        $workflowApprovals = $this->getTableLocator()->get('WorkflowApprovals');
        $workflowApproval = $workflowApprovals->newEntity([
            'workflow_instance_id' => $run->workflow_instance_id,
            'node_id' => 'recommendation_approval',
            'execution_log_id' => $workflowExecutionLog->id,
            'approver_type' => WorkflowApproval::APPROVER_TYPE_MEMBER,
            'approver_config' => ['member_id' => $memberId],
            'required_count' => 1,
            'approved_count' => $approvedCount,
            'rejected_count' => 0,
            'status' => $status,
            'allow_parallel' => false,
            'version' => 1,
        ]);

        return $workflowApprovals->saveOrFail($workflowApproval);
    }
}
