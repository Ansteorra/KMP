<?php
declare(strict_types=1);

namespace Awards\Test\TestCase\Services;

use App\Model\Entity\Member;
use App\Model\Entity\WorkflowInstance;
use App\Test\TestCase\BaseTestCase;
use Awards\Model\Entity\ApprovalProcess;
use Awards\Model\Entity\ApprovalProcessStep;
use Awards\Model\Entity\Award;
use Awards\Model\Entity\Recommendation;
use Awards\Model\Entity\RecommendationMigrationResult;
use Awards\Model\Entity\RecommendationMigrationRun;
use Awards\Services\AwardApprovalResolverService;
use Awards\Services\RecommendationBestowalStatePolicyService;
use Awards\Services\RecommendationMigrationService;
use Cake\I18n\DateTime;
use Cake\ORM\Table;

/**
 * RecommendationMigrationService tests.
 */
class RecommendationMigrationServiceTest extends BaseTestCase
{
    private Table $recommendationsTable;
    private RecommendationMigrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipIfPostgres();

        $this->recommendationsTable = $this->getTableLocator()->get('Awards.Recommendations');
        $this->service = new RecommendationMigrationService(
            approvalResolver: $this->resolverReturningApprover(),
        );
    }

    public function testClassifiesClosedStates(): void
    {
        foreach (['Given', 'No Action', 'Deferred till Later', 'Linked'] as $state) {
            $recommendation = $this->recommendationsTable->get($this->createRecommendation($state));

            $classification = $this->service->classify($recommendation);

            $this->assertSame(
                RecommendationMigrationResult::TARGET_CLOSED,
                $classification['target'],
                "Expected {$state} to classify as closed.",
            );
        }
    }

    public function testClassifiesBestowalStatesAndExistingBestowalLink(): void
    {
        foreach (
            [
                RecommendationBestowalStatePolicyService::HANDOFF_STATE,
                'Scheduled',
                'Announced Not Given',
                'King Approved',
                'Queen Approved',
            ] as $state
        ) {
            $recommendation = $this->recommendationsTable->get($this->createRecommendation($state));

            $classification = $this->service->classify($recommendation);

            $this->assertSame(
                RecommendationMigrationResult::TARGET_BESTOWAL,
                $classification['target'],
                "Expected {$state} to classify as bestowal-owned.",
            );
        }

        $linkedRecommendation = new Recommendation([
            'id' => 123459,
            'state' => 'Submitted',
            'award_id' => 1,
            'member_id' => self::ADMIN_MEMBER_ID,
            'bestowal_id' => 99,
        ]);

        $classification = $this->service->classify($linkedRecommendation);

        $this->assertSame(RecommendationMigrationResult::TARGET_BESTOWAL, $classification['target']);
    }

    public function testClassifiesBestowalStateWithMissingGatheringForManualReview(): void
    {
        $recommendation = new Recommendation([
            'id' => 123461,
            'state' => 'Scheduled',
            'award_id' => 1,
            'member_id' => self::ADMIN_MEMBER_ID,
            'gathering_id' => 999999,
        ]);

        $classification = $this->service->classify($recommendation);

        $this->assertSame(RecommendationMigrationResult::TARGET_MANUAL_REVIEW, $classification['target']);
        $this->assertStringContainsString('references missing gathering', $classification['reason']);
    }

    public function testClassifiesApprovalStates(): void
    {
        foreach (['Submitted', 'In Consideration', 'Awaiting Feedback'] as $state) {
            $recommendation = $this->approvalReadyRecommendation($state);

            $classification = $this->service->classify($recommendation);

            $this->assertSame(
                RecommendationMigrationResult::TARGET_APPROVAL_WORKFLOW,
                $classification['target'],
                "Expected {$state} to classify as approval-owned.",
            );
        }
    }

    public function testClassifiesOutOfScopeAndIncompleteRecommendationsForManualReview(): void
    {
        $grouped = new Recommendation([
            'id' => 123456,
            'state' => 'Submitted',
            'award_id' => 1,
            'member_id' => self::ADMIN_MEMBER_ID,
            'recommendation_group_id' => 42,
        ]);
        $missingData = new Recommendation([
            'id' => 123457,
            'state' => 'Submitted',
            'award_id' => null,
            'member_id' => self::ADMIN_MEMBER_ID,
        ]);
        $unknownState = new Recommendation();
        $unknownState->patch([
            'id' => 123458,
            'state' => 'Unexpected State',
            'award_id' => 1,
            'member_id' => self::ADMIN_MEMBER_ID,
        ], ['setter' => false]);

        $this->assertSame(
            RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
            $this->service->classify($grouped)['target'],
        );
        $this->assertSame(
            RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
            $this->service->classify($missingData)['target'],
        );
        $this->assertSame(
            RecommendationMigrationResult::TARGET_MANUAL_REVIEW,
            $this->service->classify($unknownState)['target'],
        );
    }

    public function testClassifiesApprovalStateWithoutEligibleApproversForManualReview(): void
    {
        $service = new RecommendationMigrationService(
            approvalResolver: $this->resolverReturningNoApprovers(),
        );
        $recommendation = $this->approvalReadyRecommendation('Submitted');

        $classification = $service->classify($recommendation);

        $this->assertSame(RecommendationMigrationResult::TARGET_MANUAL_REVIEW, $classification['target']);
        $this->assertStringContainsString('has no eligible approvers', $classification['reason']);
    }

    public function testAuditsOpenRecommendationsWithoutWorkflow(): void
    {
        $recommendationId = $this->createLegacyOpenRecommendation();

        $audit = $this->service->auditOpenRecommendationsWithoutWorkflow([
            'recommendation_id' => $recommendationId,
        ]);

        $this->assertSame(1, $audit['count']);
        $this->assertSame($recommendationId, $audit['recommendations'][0]['id']);
        $this->assertSame('In Progress', $audit['recommendations'][0]['status']);
        $this->assertSame('Legacy Open State', $audit['recommendations'][0]['state']);
    }

    public function testAuditIgnoresOpenRecommendationsWithActiveMigrationWorkflow(): void
    {
        $recommendationId = $this->createLegacyOpenRecommendation();
        $this->createMigrationWorkflowInstance($recommendationId);

        $audit = $this->service->auditOpenRecommendationsWithoutWorkflow([
            'recommendation_id' => $recommendationId,
        ]);

        $this->assertSame(0, $audit['count']);
        $this->assertSame([], $audit['recommendations']);
    }

    public function testApplyRunFailsWhenOpenRecommendationStillLacksWorkflow(): void
    {
        $recommendationId = $this->createLegacyOpenRecommendation();

        $result = $this->service->run(
            RecommendationMigrationRun::MODE_APPLY,
            ['recommendation_id' => $recommendationId],
            self::ADMIN_MEMBER_ID,
        );

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('open recommendations still lack workflow', (string)$result->getError());

        $runId = (int)$result->getData()['runId'];
        $run = $this->getTableLocator()->get('Awards.RecommendationMigrationRuns')->get($runId);
        $this->assertSame(RecommendationMigrationRun::STATUS_FAILED, $run->status);

        $migrationResult = $this->getTableLocator()->get('Awards.RecommendationMigrationResults')->find()
            ->where([
                'migration_run_id' => $runId,
                'recommendation_id' => $recommendationId,
            ])
            ->firstOrFail();
        $this->assertSame(RecommendationMigrationResult::TARGET_MANUAL_REVIEW, $migrationResult->target_action);
        $this->assertSame(RecommendationMigrationResult::STATUS_SKIPPED, $migrationResult->result_status);
    }

    public function testApplyRunCanAllowOpenManualReviewRecommendations(): void
    {
        $recommendationId = $this->createLegacyOpenRecommendation();

        $result = $this->service->run(
            RecommendationMigrationRun::MODE_APPLY,
            ['recommendation_id' => $recommendationId],
            self::ADMIN_MEMBER_ID,
            true,
        );

        $this->assertTrue($result->isSuccess(), (string)$result->getError());

        $runId = (int)$result->getData()['runId'];
        $run = $this->getTableLocator()->get('Awards.RecommendationMigrationRuns')->get($runId);
        $this->assertSame(RecommendationMigrationRun::STATUS_COMPLETED, $run->status);

        $migrationResult = $this->getTableLocator()->get('Awards.RecommendationMigrationResults')->find()
            ->where([
                'migration_run_id' => $runId,
                'recommendation_id' => $recommendationId,
            ])
            ->firstOrFail();
        $this->assertSame(RecommendationMigrationResult::TARGET_MANUAL_REVIEW, $migrationResult->target_action);
        $this->assertSame(RecommendationMigrationResult::STATUS_SKIPPED, $migrationResult->result_status);
    }

    /**
     * @param array<string, mixed> $overrides Field overrides
     */
    private function createRecommendation(string $state, array $overrides = []): int
    {
        $entity = $this->recommendationsTable->newEntity([
            'member_id' => self::ADMIN_MEMBER_ID,
            'requester_id' => self::ADMIN_MEMBER_ID,
            'award_id' => $this->getFirstAwardId(),
            'reason' => 'Test recommendation migration',
            'requester_sca_name' => 'Admin von Admin',
            'member_sca_name' => 'Admin von Admin',
            'contact_email' => 'admin@test.com',
            'status' => 'In Progress',
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

        $saved = $this->recommendationsTable->saveOrFail($entity);

        return (int)$saved->id;
    }

    private function createLegacyOpenRecommendation(): int
    {
        $recommendationId = $this->createRecommendation('Submitted');
        $this->recommendationsTable->updateAll(
            [
                'state' => 'Legacy Open State',
                'status' => 'In Progress',
            ],
            ['id' => $recommendationId],
        );

        return $recommendationId;
    }

    private function createMigrationWorkflowInstance(int $recommendationId): void
    {
        $workflowDefinitions = $this->getTableLocator()->get('WorkflowDefinitions');
        $workflowDefinition = $workflowDefinitions->find()
            ->where(['slug' => RecommendationMigrationService::WORKFLOW_SLUG])
            ->firstOrFail();

        $workflowVersionId = (int)$workflowDefinition->current_version_id;
        if ($workflowVersionId === 0) {
            $workflowVersions = $this->getTableLocator()->get('WorkflowVersions');
            $workflowVersion = $workflowVersions->newEntity([
                'workflow_definition_id' => $workflowDefinition->id,
                'version_number' => 1,
                'definition' => ['nodes' => [], 'connections' => []],
                'status' => 'published',
                'published_by' => self::ADMIN_MEMBER_ID,
            ]);
            $workflowVersion = $workflowVersions->saveOrFail($workflowVersion);
            $workflowVersionId = (int)$workflowVersion->id;
            $workflowDefinition->current_version_id = $workflowVersionId;
            $workflowDefinitions->saveOrFail($workflowDefinition);
        }

        $workflowInstances = $this->getTableLocator()->get('WorkflowInstances');
        $workflowInstances->saveOrFail($workflowInstances->newEntity([
            'workflow_definition_id' => $workflowDefinition->id,
            'workflow_version_id' => $workflowVersionId,
            'entity_type' => 'Awards.Recommendations',
            'entity_id' => $recommendationId,
            'status' => WorkflowInstance::STATUS_WAITING,
            'started_by' => self::ADMIN_MEMBER_ID,
        ]));
    }

    private function getFirstAwardId(): int
    {
        $award = $this->getTableLocator()->get('Awards.Awards')
            ->find()
            ->select(['id'])
            ->first();

        $this->assertNotNull($award, 'Expected seeded awards data for migration tests.');

        return (int)$award->id;
    }

    private function approvalReadyRecommendation(string $state): Recommendation
    {
        return new Recommendation([
            'id' => 123460,
            'state' => $state,
            'award_id' => 1,
            'member_id' => self::ADMIN_MEMBER_ID,
            'award' => new Award([
                'id' => 1,
                'branch_id' => self::KINGDOM_BRANCH_ID,
                'approval_process_id' => 1,
                'approval_process' => new ApprovalProcess([
                    'id' => 1,
                    'is_active' => true,
                    'approval_process_steps' => [
                        new ApprovalProcessStep([
                            'id' => 1,
                            'step_key' => 'local',
                            'label' => 'Local Approval',
                            'approver_type' => ApprovalProcessStep::APPROVER_TYPE_MEMBER,
                            'approver_source_id' => self::ADMIN_MEMBER_ID,
                            'branch_mode' => ApprovalProcessStep::BRANCH_MODE_AWARD,
                        ]),
                    ],
                ]),
            ]),
        ]);
    }

    private function resolverReturningApprover(): AwardApprovalResolverService
    {
        return new class extends AwardApprovalResolverService {
            /**
             * @inheritDoc
             */
            public function resolveApprovers(ApprovalProcessStep $step, Award $award): array
            {
                return [new Member(['id' => BaseTestCase::ADMIN_MEMBER_ID])];
            }
        };
    }

    private function resolverReturningNoApprovers(): AwardApprovalResolverService
    {
        return new class extends AwardApprovalResolverService {
            /**
             * @inheritDoc
             */
            public function resolveApprovers(ApprovalProcessStep $step, Award $award): array
            {
                return [];
            }
        };
    }
}
